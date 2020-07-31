<?php

namespace Tests\Feature\api;

use App\Models\UserPds;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;
use \Illuminate\Foundation\Testing\DatabaseTransactions;

class ActorsUploadTest extends TestCase
{
    use DatabaseTransactions;

    public function testDepositDraftwithActorsCreate()
    {
        $user = UserPds::where('expired_at', '>=', Carbon::now())->first()->user;
        $accessToken = JWTAuth::fromUser($user);

        $headers = ['Authorization' => "Bearer $accessToken"];

        $project = factory(\App\Models\Project::class)->create([
            'user_id' => $user->id,
        ]);

        $deposit = factory(\App\Models\Deposit::class)->states('draft', 'implementation')->make([
            'user_id' => $user->id,
        ]);

        $authors = factory(\App\Models\Actor::class, 2)->states('author', 'floatContributionWeight')->make();

        $rightholders = factory(\App\Models\Actor::class, 2)
            ->states('rightholder', 'sole_proprietor', 'floatContributionWeight')
            ->make();

        $payload = [
            'type' => $deposit->type,
            'name' => $deposit->name,
            'actors' => array_merge($authors->toArray(), $rightholders->toArray()),
            'project' => [
                'id' => $project->id,
            ]
        ];

        $this->post(route('deposit.store'), $payload, $headers)
            ->assertStatus(200)
            ->assertJson([
                "status" => 201,
                "result" => true,
            ]);
    }

    public function testDepositDraftwithActorsAuthorsCreate()
    {
        $user = UserPds::where('expired_at', '>=', Carbon::now())->first()->user;
        $accessToken = JWTAuth::fromUser($user);

        $headers = ['Authorization' => "Bearer $accessToken"];

        $project = factory(\App\Models\Project::class)->create([
            'user_id' => $user->id,
        ]);

        $deposit = factory(\App\Models\Deposit::class)->states('draft', 'implementation')->make([
            'user_id' => $user->id,
        ]);

        $actors = factory(\App\Models\Actor::class, 3)->states('author', 'floatContributionWeight')->make();

        $payload = [
            'type' => $deposit->type,
            'name' => $deposit->name,
            'actors' => $actors->toArray(),
            'project' => [
                'id' => $project->id,
            ]
        ];

        $this->post(route('deposit.store'), $payload, $headers)
            ->assertStatus(422)
            ->assertJson([
                "status" => 422,
                "result" => false,
            ]);
    }

    public function testDepositDraftwithActorsRightholdersCreate()
    {
        $user = UserPds::where('expired_at', '>=', Carbon::now())->first()->user;
        $accessToken = JWTAuth::fromUser($user);

        $headers = ['Authorization' => "Bearer $accessToken"];

        $project = factory(\App\Models\Project::class)->create([
            'user_id' => $user->id,
        ]);

        $deposit = factory(\App\Models\Deposit::class)->states('draft', 'implementation')->make([
            'user_id' => $user->id,
        ]);

        $rightholders = factory(\App\Models\Actor::class, 3)
            ->states('rightholder', 'sole_proprietor', 'floatContributionWeight')
            ->make();

        $payload = [
            'type' => $deposit->type,
            'name' => $deposit->name,
            'actors' => $rightholders->toArray(),
            'project' => [
                'id' => $project->id,
            ]
        ];

        $this->post(route('deposit.store'), $payload, $headers)
            ->assertStatus(422)
            ->assertJsonPath('message.actors.0', "total rightholder's contribution weight 1.5 is not equal to 1.00")
            ->assertJson([
                "status" => 422,
                "result" => false,
            ]);
    }
}
