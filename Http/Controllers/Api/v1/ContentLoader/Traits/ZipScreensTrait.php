<?php

namespace App\Http\Controllers\Api\v1\ContentLoader\Traits;

trait ZipScreensTrait
{
    public function zipScreens()
    {
        try {
            $disk = $this->getScreensDisk();
            $screens = $this->model->files;

            foreach ($screens as $file)
            {
                // Download file
                $source = Storage::disk($file->disk)->readStream($file->path);

                //Create tmp file
                $tmpfname = tempnam(sys_get_temp_dir(), "tmp");
                $destination = fopen($tmpfname, "w");

                while (!feof($source)) {
                    fwrite($destination, fread($source,8192));
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
