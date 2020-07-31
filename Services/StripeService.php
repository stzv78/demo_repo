<?php


namespace App\Services;

use App\Exceptions\DevelopmentException;
use App\Exceptions\StripeException;
use App\Http\Traits\StripeResourcesTrait;
use App\Http\Traits\SyncStripeTrait;
use App\Models\Customer;
use App\User;
use Laravel\Cashier\Subscription;


class StripeService
{
    use StripeResourcesTrait, SyncStripeTrait;

    protected $apiKey;

    private $serviceName = 'stripe';

    public function __construct()
    {
        $this->apiKey = env('STRIPE_SECRET');
        \Stripe\Stripe::setApiKey($this->apiKey);

        $this->getProduct();
    }

    public function getProduct(): \Stripe\Product
    {
        try {
            return \Stripe\Product::retrieve(env('STRIPE_PRODUCT'));
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage(), 404);
        }
    }


    //working with \Stripe\Plan $plan
    public function getAllPlans($active = true)
    {
        $plans = \Stripe\Plan::all(['active' => $active]);
        return $this->preparePlansForResponse($plans); //TODO сложить в редиску
    }

    public function getPlan(string $planId)
    {
        $plan = $this->retrievePlan($planId);

        return $this->preparePlansForResponse($plan);
    }

    public function retrievePlan(string $planId): \Stripe\Plan
    {
        try {
            return \Stripe\Plan::retrieve($planId);
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage(), 404);
        }
    }

    public function deactivatePlan(string $planId): \Stripe\Plan
    {
        try {
            $plan = \Stripe\Plan::update($planId, ['active' => false]);

        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage(), 404);
        }

        return $plan;
    }

    public function activatePlan(string $planId): \Stripe\Plan
    {
        try {
            $plan = \Stripe\Plan::update($planId, ['active' => true]);

        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage(), 404);
        }

        return $plan;
    }

    public function deletePlanWithSubscriptions(string $planId)
    {
        //найти и отменить все подписки на план со след месяца
        $subscriptions = \Stripe\Subscription::all(['plan' => $planId]);
        foreach ($subscriptions as $subscription) {
            $subscription->cancel();
        }

        return $this->deletePlan($planId);
    }

    public function deletePlan(string $planId)
    {
        //удалить план
        $plan = \Stripe\Plan::retrieve($planId);

        return $plan->delete();
    }

    public function createStripeCustomer(User $user): \Stripe\Customer
    {
        try {
            return \Stripe\Customer::create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage(), 404);
        }
    }

    public function createSubscription(string $customerId, string $planId)
    {
        try {
            return \Stripe\Subscription::create([
                'customer' => $customerId,
                'items' => [
                    [
                        'plan' => $planId,
                    ],
                ],
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage(), 404);
        }
    }

    public function getStripeCustomer(string $customerId): \Stripe\Customer
    {
        try {
            return \Stripe\Customer::retrieve($customerId);
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage(), 404);
        }
    }

    public function createSetupIntent(\Stripe\Customer $customer, array $metadata): \Stripe\SetupIntent
    {
        try {
            return \Stripe\SetupIntent::create([
                'payment_method_types' => ['card'],
                'customer' => $customer->id,
                'metadata' => array_merge([], $metadata),
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage(), 404);
        }
    }

    //working with Customer
    public function attachPaymentMethod(string $customerId, string $paymentMethodId): \Stripe\PaymentMethod
    {
        try {
            $method = \Stripe\PaymentMethod::retrieve($paymentMethodId);

            \Stripe\PaymentMethod::attach(
                $paymentMethodId,
                ['customer' => $customerId]
            );

        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage(), 404);
        }
    }

    public function setDefaultPaymentMethod(string $customerId, string $paymentMethodId): \Stripe\Customer
    {
        try {
            \Stripe\Customer::update(
                $customerId,
                [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethodId,
                    ],
                ]
            );
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage(), 404);
            //("The payment method `{$paymentMethod->id}` does not belong to this customer `$customerId`.");
        }
    }

    public function getPaymentMethods(string $customerId): \Stripe\Collection
    {
        try {
            \Stripe\PaymentMethod::all([
                'customer' => $customerId,
                'type' => 'card',
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage(), 404);
        }
    }


    //working with SetupIntent $setupIntent

    public function getCustomerPaymentMethods(Customer $customer)
    {
        $defaultMethod = $customer->defaultPaymentMethod() ?? false;

        $methods = [];

        foreach ($customer->paymentMethods() as $method) {
            array_push($methods, [
                'id' => $method->id,
                'default' => ($defaultMethod && $method->id === $defaultMethod->id),
                'brand' => $method->card->brand,
                'last_four' => $method->card->last4,
                'exp_month' => $method->card->exp_month,
                'exp_year' => $method->card->exp_year,
            ]);
        }
        return $methods;
    }

    //working with PaymentMethod $paymentMethod

    public function deleteCustomerPaymentMethod(Customer $customer, string $paymentMethodId)
    {
        try {
            $data = $customer->removePaymentMethod($paymentMethodId);
            $customer->updateDefaultPaymentMethodFromStripe();
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage(), 404);
        }

        return $data;
    }

    public function reattemptPaymentMethod(string $latestInvoiceId): \Stripe\Invoice
    {
        $invoice = \Stripe\Invoice::retrieve(['id' => $latestInvoiceId]);
        return $invoice->pay(['expand' => ['payment_intent']]);
    }

    public function createTestCardPaymentMethod(string $cardNumber)
    {
        $paymentMethod = \Stripe\PaymentMethod::create([
            'type' => 'card',
            'card' => [
                'number' => $cardNumber,
                'exp_month' => 6,
                'exp_year' => 2021,
                'cvc' => '314',
            ],
        ]);

        return $paymentMethod;
    }

    public function updateSubscription(string $subscriptionId, string $planId): \Stripe\Subscription
    {
        $subscription = \Stripe\Subscription::retrieve($subscriptionId);

        try {
            \Stripe\Subscription::update($subscriptionId, [
                'cancel_at_period_end' => false,
                'proration_behavior' => 'always_invoice', //'create_prorations',
                'items' => [
                    [
                        'id' => $subscription->items->data[0]->id,
                        'plan' => $planId,
                    ],
                ],
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage() . '. Details:' . json_encode($exception->getJsonBody()), 423);
        }

        return $subscription;
    }

    public function resumeSubscription(Customer $customer, string $subscriptionId)
    {
        $subscription = $this->getSubscription($subscriptionId);

        $db = $this->syncSubOptions($subscriptionId, [
            'name' => $subscription->plan->nickname,
            'status' => $subscription->status,
        ]);

        //возобновляем только действующие подписки
        if ($db->ended()) {
            throw new DevelopmentException(trans('messages.api.subscription.ended'), 422);
        }

        //возобновляем только подписки, принадлежащие пользователю
        if ($subscription->customer != $customer->stripe_id) {
            throw new DevelopmentException(trans('messages.api.subscription.not_customers_one'), 422);
        }

        return $this->subscriptionResource($this->resume($db));
    }

    private function getSubscription($subscriptionId)
    {
        try {
            return \Stripe\Subscription::retrieve(
                $subscriptionId, [
                'expand' => ['latest_invoice.payment_intent']
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage(), 404);
        }
    }

    public function syncSubOptions(string $subscriptionId, array $options)
    {
        $dbSub = Subscription::where('stripe_id', $subscriptionId)->first();

        if ($dbSub) {
            $dbSub->update([
                'name' => $options['name'],
                'stripe_status' => $options['status'],
            ]);
            return true;
        }

        return false;
    }

    private function resume(Subscription $subscription)
    {
        try {
            return $subscription->resume();
        } catch (\LogicException $exception) {
            throw new StripeException($exception->getMessage(), 503);
        }
    }

    public function deleteSubscription($subscriptionId)
    {
        try {
            $subscription = \Stripe\Subscription::retrieve($subscriptionId);
            $subscription->delete();

        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage(), 404);
        }
    }

    //working with Invoices
    public function getCustomerInvoices($customerId)
    {
        return \Stripe\Invoice::all(['customer' => $customerId]);
    }

}
