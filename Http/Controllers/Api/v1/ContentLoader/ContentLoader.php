<?php
namespace App\Http\Controllers\Api\v1\ContentLoader;

use Illuminate\Support\Facades\Request;

class ContentLoader
{
    private $loader;

    public function setLoader(FileLoader $loader)
    {
        $this->loader = $loader;
    }

    public function getContent(\Illuminate\Validation\Validator $validator)
    {
        return $this->loader->uploadContent($validator);
    }

    public function loadContentFromSeeder($source, $language = null)
    {
        return $this->loader->createFile($source, $language);
    }
}
