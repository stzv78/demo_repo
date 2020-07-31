<?php

namespace App\Models;

use App\Models\Traits\S3GetUrlTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use S3GetUrlTrait;

    protected $table = 'files';

    protected $primaryKey = 'id';

    protected $fillable = [
        'disk',
        'url',
        'filename',
        'size',
        'md5',
        'meta',
        'deposit_id',
        'parent_id',
    ];

    protected $casts = [
        'disk' =>  'string',
        'url' => 'string',
        'filename' => 'string',
        'size' => 'string',
        'md5' => 'string',
        'meta' => 'array',
        'deposit_id' => 'integer',
        'parent_id' => 'integer',
    ];

    protected $hidden = [
        'md5',
        'disk',
        'path',
        'deposit_id',
    ];

    protected $appends = ['path'];

    public function deposit()
    {
        return $this->belongsTo(Deposit::class);
    }

    public function statuses()
    {
        return $this->morphMany(Status::class, 'statusable');
    }

    public function getUrlAttribute()
    {
        return $this->getFileUrl($this->attributes['url']);
    }

    public function getPathAttribute()
    {
        return Storage::disk('s3_get')->url($this->attributes['url']);
    }
}
