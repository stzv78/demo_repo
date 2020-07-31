<?php

namespace App\Http\Middleware\Stripe;

use App\Exceptions\DevelopmentException;
use Closure;

class IsStripeCustomer
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        $user = $request->user() ?? Auth('api')->user();

        if (!($user && $user->isStripeCustomer())) {
            throw new DevelopmentException(trans('messages.api.user.not_a_stripe_customer'), 402);
        }

        return $next($request);
    }
}
