<?php

namespace Tests\Feature\api;

use App\Http\Resources\DepositResource;
use App\Services\NRISApiService;
use Tests\TestCase;

class NRISTest extends TestCase
{
    protected $NRISservice;

    public function testCanDepositCreate()
    {
        $this->sendDeposit();

        $code = $this->NRISservice->getResponseCode();
        $this->assertEquals(200, $code);
    }

    public function sendDeposit()
    {
        $ois = $this->createDeposit();

        $this->NRISservice = \App::make(NRISApiService::class)->createOis($ois);
    }

    public function createDeposit()
    {
        $deposit = factory(\App\Models\Deposit::class)->states('draft', 'implementation')->create([
            'user_id' => function () {
                return \App\Models\UserPds::where('expired_at', '<=', Carbon::now())->first()->user_id;
            }
        ]);

        $deposit->actors()->createMany(
            factory(\App\Models\Actor::class, 2)->states('floatContributionWeight')->make()->toArray()
        );

        DepositResource::withoutWrapping();

        return new DepositResource($deposit);
    }

    public function testCanDepositGet()
    {
        $this->sendDeposit();

        $uuid = $this->NRISservice->isResponseSuccess() ? $this->NRISservice->getId() : null;

        $responseUuid = $this->NRISservice->getOis(strval($uuid))->isResponseSuccess()
            ? $this->NRISservice->getId()
            : null;

        $this->assertNotNull($uuid);
        $this->assertNotNull($responseUuid);
        $this->assertEquals($uuid, $responseUuid);
    }
}
