<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends File
{
    protected $table = 'files';

    protected $primaryKey = 'id';

}
