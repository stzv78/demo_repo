<?php


namespace App\Http\Controllers\Api\v1\ContentLoader;

use App\Http\Controllers\Api\v1\ContentLoader\Traits\FileUploadTrait;
use App\Models\DepositDraft;
use App\Models\Implementation;
use App\Http\Requests\DepositDraftRequest;
use Illuminate\Support\Facades\Storage;

class ImplementationLoader implements FileLoader
{
    use FileUploadTrait;

    private $model;
    private $request;

    public function __construct(DepositDraft $model, DepositDraftRequest $request = null)
    {
        $this->request = $request;
        $this->model = $model;
    }

    public function getImplementationDisk()
    {
        return config('business.files.implementation.disk');
    }

    public function getImplementationStoragePath($filename)
    {
        $path = config('business.files.implementation.path') . $this->model->id . '/' . $filename;
        return $path;
    }

    public function uploadContent(\Illuminate\Validation\Validator $validator)
    {
        $content = $this->request->input('content') ?? $this->request->file('content');

        $languages = array_keys(config('business.deposit.type.implementation.languages'));

        if (is_string($content)) {

            //добавить валидацию поля типа text для типа ОИС implementation
            $validator->addRules([
                'content'=> 'required_if:type,implementation|string|min:10|max:8192',
                'language'=> 'required_with:content|in:'. implode(',', $languages),
            ]);
            $validator->validate();

            //проверить на существование файла по контрольной сумме
            $this->validateFileContent(md5($content), $validator);

            return $this->createFile($content, $this->request->input('language'));
        }

        return $this;
    }

    //не используется..
    public function uploadImplementationFile($file)
    {
        $disk = $this->getImplementationDisk();
        $filename = $this->getFileName($file);
        $path = $this->getImplementationStoragePath($filename);

        Storage::disk($disk)->putFileAs($path, $file, $filename);

        $implementation = (new Implementation())->fill([
            'url' => Storage::disk($disk)->path($path),
            'filename' => $filename,
            'size' => $file->getSize(),
            'md5' => md5_file($file),
        ]);

        return $implementation;
    }

    public function createFile($content, $language = null)
    {
        //создать и сохранить файл
        $disk = $this->getImplementationDisk();

        $filename = $this->getFileName($content);
        $path = $this->getImplementationStoragePath($filename);

        Storage::disk($disk)->put($path, $content);

        //создать сущность
        $implementation = (new Implementation())->fill([
            'disk' => $disk,
            'url' => Storage::disk($disk)->path($path),
            'filename' => $filename,
            'size' => Storage::disk($disk)->size($path),
            'md5' => md5($content),
            'meta' => [
                'language' => $language,
                'content' => $content,
            ],
        ]);

        $implementation->deposit()->associate($this->model);
        $implementation->save();

        return $implementation;
    }
}
