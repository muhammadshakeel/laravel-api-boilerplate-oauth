<?php

namespace App\Api\V1\Controllers;

use Authorizer;
use Validator;
use Config;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use App\Api\V1\Controllers\BaseController as Controller;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    /**
     * To get common validation rules for login and fblogin
     *
     * @return array
     */
    public function getLoginValidationRules()
    {
        return [
            'grant_type'    => 'required',
            'client_id'     => 'required',
            'client_secret' => 'required',
            'username'      => 'required|email',
        ];
    }

    /**
     * Verify user credentials and generates authentication token
     *
     * @Get("/login")
     * @Versions({"v1"})
     *
     * @Request({"grant_type":"password", "client_id":"{{client_id}}", "client_secret":"{{client_secret}}", "username":"fake@fake.com", "password":"secret"})
     *
     * @Response(200, body={"access_token":"{{generated_token}}","token_type":"Bearer","expires_in":86400})
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $credentials = $request->only(['grant_type', 'client_id', 'client_secret', 'username', 'password']);

        $validationRules = $this->getLoginValidationRules();
        $validationRules['password'] = 'required';
        $this->validateOrFail($credentials, $validationRules);

        try {
            if (! $accessToken = Authorizer::issueAccessToken()) {
                return $this->response->errorUnauthorized();
            }
        } catch (\League\OAuth2\Server\Exception\OAuthException $e) {
            throw $e;
            return $this->response->error('could_not_create_token', 500);
        }

        return response()->json(compact('accessToken'));
    }

    public function signup(Request $request)
    {
        $signupFields = Config::get('boilerplate.signup_fields');
        $hasToReleaseToken = Config::get('boilerplate.signup_token_release');

        $userData = $request->only($signupFields);

        $validator = Validator::make($userData, Config::get('boilerplate.signup_fields_rules'));

        if($validator->fails()) {
            throw new ValidationHttpException($validator->errors()->all());
        }

        User::unguard();
        $user = User::create($userData);
        User::reguard();

        if(!$user->id) {
            return $this->response->error('could_not_create_user', 500);
        }

        if($hasToReleaseToken) {
            return $this->login($request);
        }

        return $this->response->created();
    }

    public function recovery(Request $request)
    {
        $validator = Validator::make($request->only('email'), [
            'email' => 'required'
        ]);

        if($validator->fails()) {
            throw new ValidationHttpException($validator->errors()->all());
        }

        $response = Password::sendResetLink($request->only('email'), function (Message $message) {
            $message->subject(Config::get('boilerplate.recovery_email_subject'));
        });

        switch ($response) {
            case Password::RESET_LINK_SENT:
                return $this->response->noContent();
            case Password::INVALID_USER:
                return $this->response->errorNotFound();
        }
    }

    public function reset(Request $request)
    {
        $credentials = $request->only(
            'email', 'password', 'password_confirmation', 'token'
        );

        $validator = Validator::make($credentials, [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:6',
        ]);

        if($validator->fails()) {
            throw new ValidationHttpException($validator->errors()->all());
        }

        $response = Password::reset($credentials, function ($user, $password) {
            $user->password = $password;
            $user->save();
        });

        switch ($response) {
            case Password::PASSWORD_RESET:
                if(Config::get('boilerplate.reset_token_release')) {
                    return $this->login($request);
                }
                return $this->response->noContent();

            default:
                return $this->response->error('could_not_reset_password', 500);
        }
    }
}
