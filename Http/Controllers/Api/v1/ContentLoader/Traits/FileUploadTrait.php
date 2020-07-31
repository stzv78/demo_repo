<?php


namespace App\Http\Controllers\Api\v1\ContentLoader\Traits;


trait FileUploadTrait
{
    public function validateFileContent($content, \Illuminate\Validation\Validator $validator)
    {
        if (\DB::table('files')->where('md5', $content)->exists()) {
            $validator->errors()->add('content', trans('models/deposit.validation.file_exists'));
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return $this;
    }

    public function getFileName($file)
    {
        if (is_string($file)) {
            return $this->model->isTypeScreens() ? $file->hashName() : md5($file) . '.txt';
        }

        if (is_file($file)) {
            return $file->getClientOriginalName();
        }
    }
}
