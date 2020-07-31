<?php


namespace App\Http\Controllers\Api\v1\ContentLoader;


use App\Http\Controllers\Api\v1\ContentLoader\Traits\FileUploadTrait;
use App\Models\Algorithm;
use App\Models\DepositDraft;
use App\Http\Requests\DepositDraftRequest;
use Illuminate\Support\Facades\Storage;

class AlgorithmLoader implements FileLoader
{
    use FileUploadTrait;

    private $model;
    private $request;

    //для загрузки в сидере реквест в конструктор не передается
    public function __construct(DepositDraft $model, DepositDraftRequest $request = null)
    {
        $this->request = $request;
        $this->model = $model;
    }

    public function getAlgorithmDisk()
    {
        return config('business.files.algorithm.disk');
    }

    public function getAlgorithmStoragePath($filename)
    {
        $path = config('business.files.algorithm.path') . $this->model->id . '/' . $filename;
        return $path;
    }

    public function uploadContent(\Illuminate\Validation\Validator $validator)
    {
        $content = $this->request->input('content');

        if (! ($content && is_string($content))) {
            return false;
        }

        //добавить валидацию поля типа text для типа ОИС implementation
        $validator->addRules(['content'=> 'required_if:type,algorithm|string|min:10|max:8192']);
        $validator->validate();

        //проверить на существование файла по контрольной сумме
        $this->validateFileContent(md5($content), $validator);

        return $this->createFile($content);
    }

    public function createFile($content, $language = null)
    {
        //cделать файл
        $disk = $this->getAlgorithmDisk();

        $filename = $this->getFileName($content);
        $path = $this->getAlgorithmStoragePath($filename);

        Storage::disk($disk)->put($path, $content);

        //положить в бд
        $algorithm = (new Algorithm())->fill([
            'disk' => $disk,
            'url' => Storage::disk($disk)->path($path),
            'filename' => $filename,
            'size' => Storage::disk($disk)->size($path),
            'md5' => md5($content),
            'meta' => [
                'content' => $content,
            ],
        ]);

        $algorithm->deposit()->associate($this->model);
        $algorithm->save();

        return $algorithm;
    }
}
