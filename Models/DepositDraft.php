<?php


namespace App\Models;


use App\Helpers\ConfigHelper;
use Illuminate\Support\Facades\App;

class DepositDraft extends Deposit
{
    protected $table = 'deposits';

    protected $fillable = [
        'name',
        'description',
        'type',
        'locale',
        'project_id',
    ];

    protected $guarded = [
        'registered_at',
        'status',
        'user_id',
    ];

    public function setDefaultAttributes()
    {
        $attributes = [
            'status' => App::make(ConfigHelper::class)->getKey('business.deposit.status', ['initial' => true]),
            'user_id' => auth('api')->user()->id,
            'locale' => \App::getLocale(),
        ];

        return $this->setRawAttributes($attributes);
    }

}
