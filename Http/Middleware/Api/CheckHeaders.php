<?php

namespace App\Http\Middleware\Api;

use Closure;

class CheckHeaders
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
//        if (! $request->expectsJson()) {
//            return response(['errors' => ['error' => 'Incorrect "Accept" header. Must be "application/json".']], 403);
//        }

        if (json_encode($request->input()) === false) {
            return response(['errors' => ['error' => 'Incorrect charset in request body']], 403);
        }

        return $next($request);
    }
}
