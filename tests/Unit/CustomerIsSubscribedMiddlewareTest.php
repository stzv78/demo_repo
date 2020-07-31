<?php

namespace Tests\Unit;

use App\Http\Middleware\CustomerIsSubscribed;
use App\Models\Customer;
use App\User;
use Illuminate\Foundation\Testing\Concerns\InteractsWithExceptionHandling;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class CustomerIsSubscribedMiddlewareTest extends TestCase
{
    use DatabaseTransactions, InteractsWithExceptionHandling;

    /**
     * Test
     *
     * @return void
     */
    /** @test */
    public function customer_is_not_subscribed()
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->expectExceptionCode(402);
        $this->expectExceptionMessage(trans('messages.api.user.unsubscribed'));


        $user = factory(\App\User::class)->create();

        $this->actingAs($user, 'api');

        $request = Request::create(route('deposits.plugin.add'), 'POST');

        $middleware = new CustomerIsSubscribed();

        $middleware->handle($request, function () {});
    }

    public function customer_is_subscribed()
    {
        $user = User::find(2);

        $this->actingAs($user, 'api');

        $request = Request::create(route('deposits.plugin.add'), 'POST');

        $middleware = new CustomerIsSubscribed();

        $response = $middleware->handle($request, function () {});

        $this->assertEquals($response, null);
    }
}
