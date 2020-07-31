<?php

namespace Tests\Feature\stripe;

use App\Models\UserPds;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SubscriptionApiTest extends TestCase
{
    public function testCreateNewBaseSubscription()
    {
        $user = UserPds::where('expired_at', '>=', Carbon::now())->first()->user;
        $accessToken = JWTAuth::fromUser($user);

        $headers = [
            'Authorization' => "Bearer $accessToken",
        ];

        $testPlanParams = config("business.payment.services.stripe.plans.test");

        //create testPlan
        $testPlan = \App::make(StripeService::class)->createPlan(
            $testPlanParams['price'], //amount
            $testPlanParams['currency'], //currency
            $testPlanParams['period'], //interval
            Str::lower($testPlanParams['title']), //nickname
            env('APP_NAME'),//productName
            $testPlanParams['trial_period'] //trial_period_days
        );

        //create testPaymentMetod
        $paymentMetodId = \App::make(StripeService::class)->createTestCardPaymentMethod('4000000000003055')->id;
        \App::make(StripeService::class)->attachPaymentMethod($user->customer->stripe_id, $paymentMetodId);

        $payload = [
            'plan_id' => $testPlan->id,
            'payment_method_id' => $paymentMetodId,
        ];

        //create testSubscription
        $response = $this->post('/api/subscriptions/stripe', $payload, $headers)->dump();

        $response->assertStatus(200)
            ->assertJson([
                'status' => 201,
                'result' => true,
            ])
            ->assertJsonPath('data.plan.id', $testPlan->id)
            ->assertJsonPath('data.object', 'subscription')
            ->assertJsonPath('data.user.user_id', $user->id);

        \App::make(StripeService::class)->deleteCustomerPaymentMethod($user->customer, $paymentMetodId);
        \App::make(StripeService::class)->deletePlanWithSubscriptions($testPlan->id);
    }
}
