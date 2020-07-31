<?php

namespace Tests\Feature\api;

use App\Http\Middleware\AuthUserFromPlugin;
use App\Http\Middleware\CheckUserPds;
use App\Http\Middleware\CustomerIsSubscribed;
use App\Models\UserPds;
use App\Notifications\DepositCreated;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DepositFromPluginTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * test if user is not ipchain user
     *
     * @return void
     */
    public function testUserFailPds()
    {
        $this->withMiddleware([CheckUserPds::class, AuthUserFromPlugin::class]);
        $this->withoutMiddleware(CustomerIsSubscribed::class);

        $user = User::firstOrCreate([
            'email' => config('business.admin.users.default.superadmin.email')
        ]);

        $project = factory(\App\Models\Project::class)->create([
            'user_id' => $user->id,
        ]);

        $deposit = factory(\App\Models\Deposit::class)->states('draft', 'implementation')->make([
            'user_id' => $user->id,
            "project" => [
                "id" => $project->id,
            ],
        ]);

        $headers = ['Content-Type' => "multipart/form-data"];

        $payload = array_merge(
            $this->getDepositPayload($deposit, $project), [
            'email' => $user->email,
            'password' => $user->password,
        ]);

        $response = $this->post(route('deposits.plugin.add'), $payload, $headers);
        $response->assertStatus(403)
            ->assertJson([
                "status" => 403,
                "result" => false,
            ]);
    }

    private function getDepositPayload($deposit, $project)
    {
        return [
            'type' => $deposit->type,
            'projectName' => $project->name,
            'content' => \Faker\Factory::create()->text(50),
            'name' => $deposit->name,
            'language' => 'php',
        ];
    }

    /**
     * test if user create deposit with fail credencials
     *
     * @return void
     */
    public function testUserFailCredentials()
    {
        $this->withMiddleware([CheckUserPds::class, AuthUserFromPlugin::class]);
        $this->withoutMiddleware(CustomerIsSubscribed::class);

        $user = UserPds::where('expired_at', '>=', Carbon::now())->first()->user;

        $headers = ['Content-Type' => "multipart/form-data"];

        $project = factory(\App\Models\Project::class)->create([
            'user_id' => $user->id,
        ]);

        $deposit = factory(\App\Models\Deposit::class)->states('draft', 'implementation')->make([
            'user_id' => $user->id,
            "project" => [
                "id" => $project->id,
            ],
        ]);

        $payload = array_merge(
            $this->getDepositPayload($deposit, $project),
            $this->getUserCredencials(false)
        );

        $response = $this->post(route('deposits.plugin.add'), $payload, $headers);

        $response->assertStatus(403)
            ->assertJson([
                "status" => 403,
                "result" => false,
            ]);

    }

    private function getUserCredencials($success = true)
    {
        $password = config('business.admin.users.default.participant.password');
        $email = config('business.admin.users.default.participant.email');

        return [
            'email' => $success ? $email : \Faker\Factory::create()->safeEmail(),
            'password' => $success ? encrypt($password) : \Faker\Factory::create()->password(10),
        ];
    }

    /**
     * test user create deposit with one actor itself
     *
     * @return void
     */
    public function testDepositWithDefaultActor()
    {
        $this->withoutMiddleware(CustomerIsSubscribed::class);
        $this->withMiddleware([CheckUserPds::class, AuthUserFromPlugin::class]);

        $user = UserPds::where('expired_at', '>=', Carbon::now())->first()->user;

        $headers = ['Content-Type' => "multipart/form-data"];

        $project = factory(\App\Models\Project::class)->create([
            'user_id' => $user->id,
        ]);

        $deposit = factory(\App\Models\Deposit::class)->states('draft', 'implementation')->make([
            'user_id' => $user->id,
            "project" => [
                "id" => $project->id,
            ],
        ]);

        $payload = array_merge(
            $this->getDepositPayload($deposit, $project),
            $this->getUserCredencials(true)
        );

        $response = $this->post(route('deposits.plugin.add'), $payload, $headers);
        $response->assertStatus(200)
            ->assertJson([
                "status" => 201,
                "result" => true,
            ]);
    }

    /**
     * test if notification sent when deposit created from plugin
     *
     * @return void
     */
    public function testDepositNotificationSent()
    {
        Notification::fake();

        // Assert that no notifications were sent...
        Notification::assertNothingSent();

        $this->withoutMiddleware(CustomerIsSubscribed::class);

        $user = UserPds::where('expired_at', '>=', Carbon::now())->first()->user;

        $headers = ['Content-Type' => "multipart/form-data"];

        $project = factory(\App\Models\Project::class)->create([
            'user_id' => $user->id,
        ]);

        $deposit = factory(\App\Models\Deposit::class)->states('draft', 'implementation')->make([
            'user_id' => $user->id,
            "project" => [
                "id" => $project->id,
            ],
        ]);

        $payload = array_merge(
            $this->getDepositPayload($deposit, $project),
            $this->getUserCredencials($user, true)
        );

        $response = $this->post(route('deposits.plugin.add'), $payload, $headers);
        $response->assertStatus(200);

        Notification::assertSentTo(
            $user,
            DepositCreated::class
        );
    }

    public function testMailSent()
    {
        $response = $this->get(route('test.mail'));

        $response->assertStatus(200)
            ->assertSee('Sent');
    }

    /**
     * test user create deposit with several actors
     *
     * @return void
     */
    public function testDepositWithUploadActors()
    {
        $this->withMiddleware([CheckUserPds::class, AuthUserFromPlugin::class]);
        $this->withoutMiddleware(CustomerIsSubscribed::class);

        $user = UserPds::where('expired_at', '>=', Carbon::now())->first()->user;
        Auth::guest();

        $headers = ['Content-Type' => "multipart/form-data"];

        $project = factory(\App\Models\Project::class)->create([
            'user_id' => $user->id,
        ]);

        $deposit = factory(\App\Models\Deposit::class)->states('draft', 'implementation')->make([
            'user_id' => $user->id,
            "project" => [
                "id" => $project->id,
            ],
        ]);

        $payload = array_merge(
            $this->getDepositPayload($deposit, $project),
            $this->getUserCredencials(true),
            $this->getActors()
        );

        $response = $this->post(route('deposits.plugin.add'), $payload, $headers);

        $response->assertStatus(200)
            ->assertJson([
                "status" => 201,
                "result" => true,
            ]);

    }

    private function getActors()
    {
        $authors = factory(\App\Models\Actor::class, 2)->states('author', 'floatContributionWeight')->make();

        $rightholders = factory(\App\Models\Actor::class, 2)
            ->states('rightholder', 'sole_proprietor', 'floatContributionWeight')
            ->make();

        return [
            'actors' => array_merge($authors->toArray(), $rightholders->toArray())
        ];
    }

}
