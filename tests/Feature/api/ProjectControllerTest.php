<?php

namespace Tests\Feature\api;

use App\Models\UserPds;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProjectControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function testProjectCreate()
    {
        $user = UserPds::where('expired_at', '>=', Carbon::now())->first()->user;
        $accessToken = JWTAuth::fromUser($user);

        $headers = ['Authorization' => "Bearer $accessToken"];

        $payload = factory(\App\Models\Project::class)->make([
            'user_id' => $user->id,
        ]);

        $this->post(route('project.store'), $payload->toArray(), $headers)
            ->assertStatus(200)
            ->assertJson([
                "status" => 201,
                "result" => true,
            ]);
    }

    public function testProjectIndex()
    {
        $user = UserPds::where('expired_at', '>=', Carbon::now())->first()->user;
        $accessToken = JWTAuth::fromUser($user);

        $headers = ['Authorization' => "Bearer $accessToken"];

        factory(\App\Models\Project::class, 3)->create([
            'user_id' => $user->id,
        ]);

        $this->get(route('project.index'), $headers)
            ->assertStatus(200)
            ->assertJson([
                "status" => 200,
                "result" => true,
            ]);
    }

    public function testUserAccessHisProject()
    {
        $authUser = factory(\App\User::class)->state('testUser')->create();
        $accessToken = JWTAuth::fromUser($authUser);

        $headers = ['Authorization' => "Bearer $accessToken"];

        $otherUser = factory(\App\User::class)->state('testUser')->create();

        $project = factory(\App\Models\Project::class)->create([
            'user_id' => $otherUser->id,
        ]);

        $this->get(route('project.details', ['id' => $project->id]), $headers)
            ->assertStatus(403)
            ->assertJson([
                "status" => 403,
                "result" => false,
            ]);
    }
}
