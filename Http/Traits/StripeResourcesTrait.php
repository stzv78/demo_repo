<?php


namespace App\Http\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait StripeResourcesTrait
{
    public function preparePlansForResponse($data)
    {
        if ($data instanceof \Stripe\Collection) {
            $data = array_values(Arr::sort($data['data'], 'amount'));
            $result['data'] = collect($data)->map(function ($item) {
                return ($item->nickname === 'free') ? $this->getFreePlan() : $this->planResource($item);
            });
        }

        if ($data instanceof \Stripe\Plan) {
            $result['data'] = $this->planResource($data);
        }

        return $result['data'];
    }

    public function getFreePlan() : array
    {
        $currency = config('business.payment.services.stripe.plans.free.currency');

        return [
            'id' => null,
            'name' => config('business.payment.services.stripe.plans.free.title'),
            'active' => 'true',
            'amount' => config('business.payment.services.stripe.plans.free.price'),
            'currency' => $currency,
            'currency_symbol' => config('business.payment.currency')[$currency]['symbol'],
            'description' => config('business.payment.services.stripe.plans.free.description'),
            'target_audience' => config('business.payment.services.stripe.plans.free.target_audience'),
            'interval' => config('business.payment.services.stripe.plans.free.period'),
        ];
    }

    public function planResource(\Stripe\Plan $plan): array
    {
        $currency = Str::upper($plan->currency);
        $enterpriseName = Str::lower(config('business.payment.services.stripe.plans.enterprise.title'));

        return [
            'id' => $plan->id,
            'name' => $plan->nickname,
            'active' => $plan->active,
            'amount' => $plan->nickname === $enterpriseName ? '' : $plan->amount,
            'currency' => $currency,
            'currency_symbol' => config('business.payment.currency')[$currency]['symbol'],
            'description' => config('business.payment.services.stripe.plans')[$plan->nickname]['description'],
            'target_audience' => config('business.payment.services.stripe.plans')[$plan->nickname]['target_audience'],
            'interval' => $plan->interval,
        ];
    }

    public function prepareSubscriptionsForResponse($data) : array
    {
        if ($data instanceof \Stripe\Collection || is_array($data)) {
            $result['data'] = collect($data['data'])->map(function ($item) {
                return $this->subscriptionResource($item);
            });
        }

        if ($data instanceof \Stripe\Subscription) {
            $result['data'] = $this->subscriptionResource($data);
        }

        return $result['data'];
    }

    public function subscriptionResource(\Stripe\Subscription $subscription) : array
    {
        return [
            'id' => $subscription->id,
            'created_at' => $this->getDateTimeFromTimestamp($subscription->created),
            'status' => $subscription->status,
            'current_period_end' => $this->getDateTimeFromTimestamp($subscription->current_period_end),
            'current_period_start' => $this->getDateTimeFromTimestamp($subscription->current_period_start),
            'canceled_at' => $this->getDateTimeFromTimestamp($subscription->canceled_at),
            'ended_at' => $this->getDateTimeFromTimestamp($subscription->ended_at),
            'days_until_due' => $subscription->days_until_due,
            'default_payment_method' => $subscription->default_payment_method,
            'latest_invoice' => $subscription->latest_invoice,
            'plan' => $this->planResource($subscription->plan),
        ];
    }

    public function prepareInvoicesForResponse($data) : array
    {
        if ($data instanceof \Stripe\Collection || is_array($data)) {
            $result['data'] = collect($data['data'])->map(function ($item) {
                return $this->invoiceResource($item);
            });
        }

        if ($data instanceof \Stripe\Plan) {
            $result['data'] = $this->invoiceResource($data);
        }

        return $result['data'];
    }

    public function invoiceResource(\Stripe\Invoice $invoice) : array
    {
        $plan = $invoice->lines->data[0]->plan;

        return [
            'id' => $invoice->id,
            'created_at' => $this->getDateTimeFromTimestamp($invoice->created),
            'invoice_item_id' => $invoice->lines->data[0]->id,
            'invoice_item_amount' => $invoice->lines->data[0]->amount,
            'invoice_item_currency' => $invoice->lines->data[0]->currency,
            'invoice_item_period' => [
                'start' => $this->getDateTimeFromTimestamp($invoice->lines->data[0]->period->start),
                'end' => $this->getDateTimeFromTimestamp($invoice->lines->data[0]->period->end),
            ],
            'hosted_invoice_url' => $invoice->hosted_invoice_url,
            'invoice_pdf' => $invoice->invoice_pdf,
            'plan' => $this->planResource($plan),
        ];
    }
}
