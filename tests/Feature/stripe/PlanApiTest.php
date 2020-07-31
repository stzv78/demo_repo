<?php

namespace Tests\Feature\stripe;

use App\Models\UserPds;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PlanApiTest extends TestCase
{
    public function testGetActivePlans()
    {
        $user = UserPds::where('expired_at', '>=', Carbon::now())->first()->user;
        $accessToken = JWTAuth::fromUser($user);

        $headers = [
            'Authorization' => "Bearer $accessToken",
        ];

        $response = $this->get('/api/plans/stripe?active=true', $headers);
        $response->assertStatus(200)
            ->assertJson([
                'status' => 200,
                'result' => true,
            ]);

        $res_array = (array)json_decode($response->content(), true);

        $this->assertNotEmpty($res_array['data']);

        collect($res_array['data'])->pluck('active')->map(function ($item) {
            return $this->assertTrue($item);
        });
    }

    public function testRetrievePlan()
    {
        $user = UserPds::where('expired_at', '>=', Carbon::now())->first()->user;
        $accessToken = JWTAuth::fromUser($user);

        $headers = [
            'Authorization' => "Bearer $accessToken",
        ];

        $response = $this->get('/api/plans/stripe/retrieve?plan_id=plan_H9jufVo0FbAjcN', $headers);
        $response->assertStatus(200)
            ->assertJson([
                'status' => 200,
                'result' => true,
            ])->assertJsonPath('data.id', 'plan_H9jufVo0FbAjcN');

    }

}
