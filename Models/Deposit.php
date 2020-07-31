<?php

namespace App\Models;

use App\DepositCheckList;
use App\Services\IRISApiService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class Deposit extends Model implements FilterInterface
{
    protected $primaryKey = 'id';

    protected $fillable = [
        'pds_ois_id',
        'number',
        'name',
        'description',
        'locale',
        'status',
        'type',
        'user_id',
        'project_id',
        'created_at',
        'updated_at',
    ];

    protected $guarded = [
        'user_id',
    ];

    protected $hidden = [
        'project_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'pds_ois_id' => 'string',
        'number' => 'string',
        'name' => 'string',
        'description' => 'string',
        'locale' => 'string',
        'status' => 'string',
        'type' => 'string',
        'user_id' => 'integer',
        'project_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'registered_at' => 'datetime:Y-m-d',
    ];

    protected $with = ['project:id,name', 'actors', 'files', 'ipchain'];

    protected $filterable = [
        'name',
        'number',
        'description',
        'locale',
        'status',
        'type',
        'user_id',
        'project_id',
        'created_at',
        'registered_at',
    ];

    protected $searchable = [
        'name',
        'number',
        'description',
        'locale',
        'status',
        'type',
        'user_id',
        'project_id',
        'created_at',
        'registered_at',
    ];

    protected $sortable = [
        'id',
        'name',
        'number',
        'description',
        'status',
        'type',
        'project_id',
        'created_at',
        'updated_at',
        'registered_at',
    ];

    public function getAllowedSearchables() : array
    {
        return $this->searchable;
    }

    public function getAllowedFilterables(): array
    {
        return $this->filterable;
    }

    public function getAllowedSortables(): array
    {
        return $this->sortable;
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function files()
    {
        return $this->hasMany(File::class, 'deposit_id', 'id');
    }

    public function implementation()
    {
        return $this->hasOne(Implementation::class, 'deposit_id', 'id');
    }

    public function algorithm()
    {
        return $this->hasOne(Algorithm::class, 'deposit_id', 'id');
    }
    
    public function statuses()
    {
        return $this->morphMany(Status::class, 'statusable')->orderBy('id');
    }

    public function updateable()
    {
        return $this->status === 'draft';
    }

    public function deleteable()
    {
        return $this->status === 'draft';
    }

    public function setNextStatus()
    {
        $nextStatus = array_key_first(config('business.deposit.status.' . $this->status . '.next'));

        $this->status = $nextStatus;
        $this->save();
    }

    public function clearRelations()
    {
       return $this->clearActors()->clearFiles();
    }

    /**
     * Виртуальный атрибут: status_title
     *
     * @return string
     */
    public function getStatusTitleAttribute()
    {
        if (!$this->status) {
            return null;
        }

        return trans(config('business.deposit.status')[$this->status]['title']);
    }

    /**
     * Виртуальный атрибут: type_title
     *
     * @return string
     */
    public function getTypeTitleAttribute()
    {
        if (!$this->type) {
            return null;
        }

        return trans(config('business.deposit.type')[$this->type]['title']);
    }

    public function getAdditionTypeFields()
    {
        if ($this->type === 'implementation') {
            return $this->append('content', 'language');
        } else if ($this->type === 'algorithm') {
            return $this->append('content');
        }

        return $this;
    }

    public function getContentAttribute()
    {
        if ($this->files()->exists() && in_array($this->type, ['implementation', 'algorithm'])) {
            $meta = $this->files->first()->meta;
            return $meta['content'] ?? '';
        }

        return null;
    }

    public function getLanguageAttribute()
    {
        if ($this->files()->exists() && $this->type == 'implementation') {
            $meta = $this->files->first()->meta;
            return $meta['language'] ?? '';
        }

        return null;
    }

    public function  generateCertNumber()
    {
        $text = time(). rand(100000,999999);
        $number = config("business.deposit.type.$this->type.number_prefix") .
            preg_replace_callback("/\d{4}/",
                function ($match) {
                    return  '-' .$match[0];
                },
                strval($text)
            );

        $this->number = $number;
        $this->save();
    }

}
