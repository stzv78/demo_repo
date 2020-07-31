<?php


namespace App\Http\Controllers\Api\v1\ContentLoader;

use Illuminate\Support\Facades\Request;

interface FileLoader
{
    public function getFileName($file);

    public function validateFileContent($content, \Illuminate\Validation\Validator $validator);

    public function uploadContent(\Illuminate\Validation\Validator $validator);

    public function createFile($source, $language = null);
}
