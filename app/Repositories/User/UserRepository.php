<?php
namespace App\Repositories\User;

use DB;
use App\Models\User;
use App\Models\Profile;
use App\Models\ProfileExtended;
use App\Enums\RequestStatus;
use App\Enums\AuthType;
use App\Repositories\Profile\ProfileRepository;

use \Exception;

class UserRepository implements UserRepositoryInterface
{
    protected $profileRepo;

    public function __construct(ProfileRepository $profileRepo)
    {
        $this->profileRepo = $profileRepo;
    }

    public function throwStoreResourceFailedException($message='Failed to store your requested resource.', Validator $validator=null)
    {
        if ($validator instanceof Validator) {
            throw new \Dingo\Api\Exception\StoreResourceFailedException($message, $validator->errors());
        } else {
            throw new \Dingo\Api\Exception\StoreResourceFailedException($message);
        }
    }

    public function getAll()
    {
        return 'get all';
    }

    public function find($id)
    {
        $user = User::find($id);
        if ($user) {
            return $user;
        } else {
            $this->throwStoreResourceFailedException();
        }
    }

    public function createUser($data)
    {
        $user = new User;
        if (!empty($data['token_facebook'])) {
            $user = User::firstOrNew(['email' => $data['username']]);
            $user->auth_type = AuthType::FACEBOOK;
            $user->vendor_auth_token = $data['token_facebook'];
        } else {
            $user->email = $data['email'];
            $user->auth_type = AuthType::EMAIL;
            $user->password = bcrypt($data['password']);
        }

        DB::beginTransaction();
        try {
            $user->save();
            if (!empty($data['profile'])) {
                $user = $this->createOrUpdateProfile($user, $data['profile']);
            }
            DB::commit();
            return $user->load('profile');
        } catch (Exception $ex) {
            DB::rollback();
            return false;
        }
    }

    public function createOrUpdateProfile($user, $data)
    {
        $profile = $user->profile;

        if (!$profile) {
            $profile = new Profile;
        }

        DB::beginTransaction();
        try {
            $profile->fill($data);

            if ($user->profile()->save($profile)) {
                DB::commit();
                return $user;
            } else {
                DB::rollback();
                return false;
            }
        } catch (Exception $ex) {
            DB::rollback();
            return false;
        }
    }
}
