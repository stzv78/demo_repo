<?php


namespace App\Http\Traits;

use App\Exceptions\StripeException;
use App\Models\Customer;
use App\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;

trait SyncStripeTrait
{

    //синхронизация пользователей---------------------------------------------------------------------------------------
    public function syncCustomers()
    {
        $appProduct = $this->getAppProductId();
        $plansIds = $this->getAllProductPlansIDs($appProduct);
        $customersIds = $this->getCustomersIDs($plansIds);

        $result = collect($customersIds)->map(function ($customerId) {
            $customer = \Stripe\Customer::retrieve($customerId);
            return $this->updateCustomerUser($customer);
        });

        return $result->contains(true);
    }

    private function getAppProductId(string $appName = null): string
    {
        $appName = $appName ?? env('APP_NAME');

        $products = \Stripe\Product::all(["active" => true]);

        $appProduct = collect($products['data'])->filter(function ($item) use ($appName) {
            return $item->name == $appName;
        })->first();

        return $appProduct->id;
    }


    private function getAllProductPlansIDs(string $productId): array
    {
        $plans = \Stripe\Plan::all(['product' => $productId]);

        return collect($plans['data'])->map(function ($item) {
            return $item->id;
        })->toArray();
    }

    private function getCustomersIDs(array $plansIds): array
    {
        $customersIds = collect($plansIds)->map(function ($plan_id) {

            $subscriptions = \Stripe\Subscription::all(['plan' => $plan_id]);

            $customers = collect($subscriptions['data'])->map(function ($item) {
                return $item->customer;
            });

            return $customers->unique();

        })->flatten();

        return $customersIds->toArray();
    }

    private function updateCustomerUser(\Stripe\Customer $customer)
    {
        $user = User::whereEmail($customer->email)->first();

        if (!$user)
            return false;

        //пользователь удален в страйп
        if ($customer->isDeleted()) {
            $dbCustomer = $user->customer()->where('stripe_id', $customer->id)->first();
            if ($dbCustomer) {
                $dbSub = $dbCustomer->subscription;
                if ($dbSub) {
                    $dbSub->delete();
                    Log::info("Application stripe customer's user_id=$user->id subscrtption $dbSub->id deleted");
                }
                $dbCustomer->delete();
                Log::info("Application stripe customer's user_id=$user->id customer $dbCustomer->id deleted");
                return true;
            }
        }
        //пользователь не удален в страйп
        if (!$customer->isDeleted()) {

            //на бэке пользователь отстутствует
            if (!$user->customer) {
                $newCustomer = $user->customer()->create([
                    'stripe_id' => $customer->id,
                ]);

                Log::info("Application stripe customer created: user_id=$newCustomer->user_id, stripe_id={$newCustomer->id}");

                $this->syncSubscriptions($newCustomer);
                $newCustomer->updateDefaultPaymentMethodFromStripe();
            } else {
                //на бэке есть пользователь, обновляем его в таблице customers
                if ($user->customer->stripe_id == $customer->id) {
                    Log::info("Application stripe customer user_id=$user->id is up to date");
                } else {
                    $user->customer->update(['stripe_id' => $customer->id]);
                    Log::info("Application stripe customer updated: user_id=$user->id, stripe_id={$customer->id}");
                }
                $this->syncSubscriptions($user->customer);
                $user->customer->updateDefaultPaymentMethodFromStripe();
            };

        }

        return true;
    }

    public function syncSubscriptions(Customer $customer)
    {
        $array = $customer->getSubscriptions('stripe'); //array

        if (empty($array)) {
            return false;
        }

        $sub = Subscription::where('customer_id', $customer->id)->get();
        if (!$sub->isEmpty()) {
            $sub->map(function ($item) {
                return $item->delete();
            });
        }

        foreach ($array as $subscription) {
            $options = [
                'customer_id' => $customer->id,
                'name' => $subscription->plan->nickname,
                'quantity' => $subscription->quantity,
                'stripe_plan' => $subscription->plan->id,
                'stripe_id' => $subscription->id,
                'stripe_status' => $subscription->status,
            ];

            if ($subscription->status == 'trialing') {
                array_merge($options, [
                    'trial_end' => $this->getDateTimeFromTimestamp($subscription->trial_ends_at)
                ]);
            }

            if ($subscription->status == 'canceled') {
                array_merge($options, [
                    'ends_at' => $this->getDateTimeFromTimestamp($subscription->cancel_at)
                ]);
            }

            Subscription::create($options);
        }

        return true;
    }

    //синхронизация планов
    public function uploadPlansFromConfig()
    {
        $plans = [];
        $configPlans = config("business.payment.services.$this->serviceName.plans");

        foreach ($configPlans as $name => $params) {
            $plan = $this->createPlan(
                $params['price'],
                $params['currency'],
                $params['period'],
                Str::lower($params['title']),
                env('APP_NAME'),
                $params['trial_period']
            );

            if ($plan) {
                \Illuminate\Support\Facades\Log::info("$plan->nickname $plan->id created in Stripe.");
            }
            Arr::set($plans, $plan->nickname, $plan);
        }

        return $plans;
    }

    public function createPlan($amount, $currency, $interval, $subscriptionSlug, $productName, $trial_period_days = 0)
    {
        try {
            $productId = $this->getAppProductId();

            $plan = \Stripe\Plan::create([
                'amount' => $amount,
                'currency' => $currency,
                'interval' => $interval,
                'nickname' => $subscriptionSlug,
                'product' => $productId,
                'trial_period_days' => $trial_period_days,
            ]);

        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            throw new StripeException($exception->getMessage() . '. Details:' . json_encode($exception->getJsonBody()), 423);
        }
        return $plan;
    }

}
