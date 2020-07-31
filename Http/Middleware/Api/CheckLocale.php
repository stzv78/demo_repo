<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Support\Arr;

class CheckLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $locale = mb_substr($request->header('Accept-Language'), 0, 2);
        $locale = mb_strtolower($locale);

        if (in_array($locale, config('business.localization.available_locales'))) {
            \App::setLocale($locale);
        } else if ($locale) {
            return response(['errors' => ['error' => 'Incorrect "Accept-Language" header.']], 403);
        }

        return $next($request);
    }
}
