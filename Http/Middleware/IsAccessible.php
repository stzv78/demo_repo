<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use App\Models\Deposit;
use Illuminate\Support\Str;

class IsAccessible
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
        $name = explode('.', \Illuminate\Support\Facades\Route::currentRouteName());
        $dataSource = Arr::first($name);
        $model = $this->getModelByDataSource($dataSource);

        if ($model) {
            if($instance = $model->find(intval($request->id))) {
                if (! Gate::forUser($request->user())->check("$dataSource-accessible", $instance)) {
                    throw new \Illuminate\Auth\Access\AuthorizationException(trans('messages.api.forbidden'), 403);
                }
            }
        }

        return $next($request);
    }


    protected function getModelByDataSource($dataSource)
    {
        $className = 'App\Models\\' . Str::studly($dataSource);
        $model = class_exists($className) ? new $className : null;
        return $model;
    }
}
