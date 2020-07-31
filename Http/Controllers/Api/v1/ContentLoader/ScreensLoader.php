<?php


namespace App\Http\Controllers\Api\v1\ContentLoader;

use App\Http\Controllers\Api\v1\ContentLoader\Traits\FileUploadTrait;
use App\Http\Controllers\Api\v1\ContentLoader\Traits\ZipScreensTrait;
use App\Http\Requests\DepositDraftRequest;
use App\Models\DepositDraft;
use App\Models\Image;
use App\Models\Traits\S3GetUrlTrait;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ScreensLoader implements FileLoader 
{
    use S3GetUrlTrait, FileUploadTrait, ZipScreensTrait;

    private $model;
    private $request;

    public function __construct(DepositDraft $model, DepositDraftRequest $request = null)
    {
        $this->request = $request;
        $this->model = $model;
    }

    public function getScreensDisk()
    {
        return config('business.files.screens.disk');
    }

    public function getScreensStoragePath()
    {
        $path = config('business.files.screens.path') . $this->model->id;
        return $path;
    }

    public function getZipPath()
    {
        return $this->getScreensStoragePath() . '/file.zip';
    }

    public function getAllowedDimensions()
    {
        return array_values(config('business.files.screens.allowed_dimensions'));
    }

    public function uploadContent(\Illuminate\Validation\Validator $validator)
    {
        // content прилетает массивом с файлами:
        $files = $this->request->file('content');

        $validator->addRules([
            'content' => 'required_if:type,screens',
            'content.*' => 'file|required|mimes:png,jpg,jpeg,gif,webp|max:4096',
        ]);

        $validator->validate();

        $screens = collect($files)->map(function ($file) use ($validator) {
            //проверить на существование файла по контрольной сумме
            $content = $file->get();
            $this->validateFileContent(md5($content), $validator);

            $screen = $this->createFile($file);
            return $screen;
        });

        return $this->zipScreens();
    }

    public function createFile($file, $language = null)
    {
        $disk = $this->getScreensDisk();
        $originalPath = $this->getScreensStoragePath();
        $filename = $this->getFileName($file);

        $path = Storage::disk($disk)->putFile($originalPath, $file);

        //положить в бд
        $screen = (new Image())->fill([
            'disk' => $disk,
            'url' => $path,
            'filename' => $filename,
            'size' => Storage::disk($disk)->size($path),
            'md5' => md5($file->get()),
            'meta' => [
                'dimensions' => $this->getResizeSceens($file),
            ],
        ]);

        $screen->deposit()->associate($this->model);
        $screen->save();

        return $screen;
    }

    public function getResizeSceens($file)
    {
        $dimensions = $this->getAllowedDimensions();
        $result = [];
        foreach ($dimensions as $dimansion) {
            Arr::set($result, $dimansion, $this->resizeImage($file, $dimansion));
        }

        return $result;
    }

    public function resizeImage($file, $dimension)
    {
        $manager = new \Intervention\Image\ImageManager(array('driver' => 'imagick'));
        $image = $manager->make($file);

        $height = $image->getHeight();
        $width = $image->getWidth();

        if ($height > $dimension || $width > $dimension) {
            if ($height < $width) {
                $newHeight = $dimension;
                $newWidth = round($width / ($height / $newHeight));
            } else {
                $newWidth = $dimension;
                $newHeight = round($height / ($width / $newWidth));
            }
            $image->resize($newWidth, $newHeight);
        }

        $image->encode('png');

        $newPath = $this->getScreensStoragePath() . "/$dimension";
        Storage::disk($this->getScreensDisk())->put($newPath, $image);

        return $newPath;
    }

    public function zipScreens()
    {
        try {
            $disk = $this->getScreensDisk();
            $screens = $this->model->files;

            foreach ($screens as $file) {
                // Download file
                $source = Storage::disk($file->disk)->readStream($file->path);

                //Create tmp file
                $tmpfname = tempnam(sys_get_temp_dir(), "tmp");
                $destination = fopen($tmpfname, "w");

                while (!feof($source)) {
                    fwrite($destination, fread($source, 8192));
                }
                fclose($source);
                fclose($destination);

                // Add file to zip
                $zipfile = Storage::disk('private')->path('file.zip');
                $zip = new \ZipArchive();
                $zip->open($zipfile, \ZipArchive::CREATE);
                $zip->addFile($tmpfname, basename($file->path));
                $zip->close();
                unlink($tmpfname);
            }

            // To upload zip back to S3
            $path = $this->getZipPath();
            $result = Storage::disk($disk)->putStream($path, Storage::disk('private')->readStream('file.zip'));

            if ($result) {
                $arch = Image::create([
                    'disk' => $disk,
                    'url' => $path,
                    'filename' => basename($zipfile),
                    'size' => filesize($zipfile),
                    'md5' => md5_file($zipfile),
                ]);

                $arch->deposit()->associate($this->model);
                $arch->save();

                foreach ($screens as $screen) {
                    $screen->parent_id = $arch->id;
                    $screen->save();
                }
            }

            unlink($zipfile);

            return $screens;

        } catch (\Exception $e) {
            throw new \Exception('Error creating zip archive');
        }
    }
}

