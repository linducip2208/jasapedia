<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        // Sequential in-process requests share singletons; the RequestGuard
        // memoizes the resolved user across requests, so revoked tokens would
        // still authenticate. Forget guard users after every test request.
        if ($this->app->bound('auth')) {
            foreach (array_keys(config('auth.guards', [])) as $guard) {
                $this->app['auth']->guard($guard)->forgetUser();
            }
        }

        return $response;
    }
}
