<?php
namespace App\Repositories\Profile;

use DB;
use App\Models\User;
use App\Models\Profile;
use App\Models\ProfileExtended;
use App\Enums\RequestStatus;
use App\Enums\AuthType;

use \Exception;

class ProfileRepository implements ProfileRepositoryInterface
{
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
        return '';
    }

    public function findByUserId($userId)
    {
        $profile = Profile::where('user_id', $userId)->first();
        if ($profile) {
            return $profile;
        } else {
            $this->throwStoreResourceFailedException();
        }
    }

    public function updateLocation($userId, $lat, $lng)
    {
        $profile = $this->findByUserId($userId);

        $profile->cur_lat = $lat;
        $profile->cur_lng = $lng;

        return $profile->save();
    }
}
