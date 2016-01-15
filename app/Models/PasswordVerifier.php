<?php
namespace App\Models;

use App\Models\User;
use App\Repositories\User\UserRepositoryInterface;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PasswordVerifier
{
    /**
     * Request object to be injected
     *
     * @var Request
     */
    public $request;

    protected $userRepo;

    public function __construct(Request $request, UserRepositoryInterface $userRepo)
    {
        $this->request = $request;
        $this->userRepo = $userRepo;
    }

    public function verify($username, $password)
    {
        $credentials = [
            'email'    => $username,
            'password' => $password,
        ];

        // Check for FB login
        if ($this->request->has('token_facebook')) {
            $user = $this->userRepo->createUser($this->request->all());

            return $user->id;
        }

        // For normal users
        if (Auth::once($credentials)) {
            return Auth::user()->id;
        }

        return false;
  }
}
