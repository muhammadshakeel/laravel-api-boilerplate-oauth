<?php

$api = app('Dingo\Api\Routing\Router');

// All Free Routes
$api->version('v1', ['prefix' => 'api/v1'], function ($api) {

	// Authentication Module routes
	$api->post('login', 'App\Api\V1\Controllers\AuthController@login');
	$api->post('signup', 'App\Api\V1\Controllers\AuthController@signup');
	$api->post('auth/recovery', 'App\Api\V1\Controllers\AuthController@recovery');
	$api->post('auth/reset', 'App\Api\V1\Controllers\AuthController@reset');

});


// All protected routes
$api->version('v1', ['prefix' => 'api/v1', 'middleware' => 'api.auth', 'providers' => ['oauth']], function ($api) {


});
