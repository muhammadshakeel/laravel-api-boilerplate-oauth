<?php

namespace App\Providers;

use Dingo\Api\Auth\Auth;
use Dingo\Api\Auth\Provider\OAuth2;
use Illuminate\Support\ServiceProvider;
use App\Models\User;

class OAuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app[Auth::class]->extend('oauth', function ($app) {
            $provider = new OAuth2($app['oauth2-server.authorizer']->getChecker());

            $provider->setUserResolver(function ($id) {
                // Logic to return a user by their ID.
                //dd(User::find($id));
                return User::find($id);
            });

            $provider->setClientResolver(function ($id) {
                // Logic to return a client by their ID.
                //return 'client@fake.com';
                //dd($id);
            });

            return $provider;
        });
    }

    public function register()
    {
        //
    }
}
