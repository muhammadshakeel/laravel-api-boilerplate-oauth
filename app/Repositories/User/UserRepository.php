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

    public function getRecommendedUsers($userId, $limit, $filterByIds, $lat, $lng, $radius)
    {
        $user = $this->find($userId);

        if (!empty($lat) && !empty($lng)) {
            $usersQuery = $user->recommended($filterByIds)->byLocation($lat, $lng, $radius);
        } else {
            $usersQuery = $user->recommended($filterByIds);
        }

        $users = $usersQuery->paginate($limit);
        return $users;
    }

    public function getUsersAroundMe($userId, $lat, $lng, $radius)
    {
        $longitude = (float) $lng;
        $latitude = (float) $lat;
        $radius = (int) $radius; // in miles

        $lng_min = $longitude - $radius / abs(cos(deg2rad($latitude)) * 69);
        $lng_max = $longitude + $radius / abs(cos(deg2rad($latitude)) * 69);
        $lat_min = $latitude - ($radius / 69);
        $lat_max = $latitude + ($radius / 69);

        // Update current user's location
        // $this->profileRepo->updateLocation($userId, $lat, $lng);

        $profiles = Profile::whereBetween('cur_lat', [$lat_min, $lat_max])
                    ->whereBetween('cur_lng', [$lng_min, $lng_max])
                    ->get();

        return $profiles;
    }

    public function searchUser()
    {

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
                if (!empty($data['preferences']) && is_array($data['preferences'])) {
                    foreach ($data['preferences'] as $field => $fieldOptions) {
                        $this->setMetadata($profile, $field, $fieldOptions);
                    }
                }
                if (!empty($data['skill_level'])) {
                    $skillMetaDataId = DB::table('metadata_field_options')->where('value', $data['skill_level'])->value('id');
                    if (!is_null($skillMetaDataId)) {
                        $data['recommendations'] = [
                            'skill_level' => [(int) $skillMetaDataId]
                        ];
                        foreach ($data['recommendations'] as $field => $fieldOptions) {
                            $this->setMetadata($profile, $field, $fieldOptions);
                        }
                    }
                }
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

    /**
     * Saves child records for user's profile preferences
     *
     * @param Array $preferences preferences_metadata ids selected from User
     * @return boolean
     */
    public function setMetadata($profile, $metadataField, Array $selectedOptions=[])
    {
        // Delete all previous preference before adding new
        ProfileExtended::where('profile_id', $profile->id)
            ->join('metadata_field_options', 'profile_extended.metadata_field_option_id', '=', 'metadata_field_options.id')
            ->join('metadata_fields', function ($join) use ($metadataField) {
                $join->on('metadata_fields.id', '=', 'metadata_field_options.metadata_field_id')
                    ->where('metadata_fields.field', '=', $metadataField);
            })
            ->delete();
        $metadataOptions = DB::table('metadata_field_options')
            ->select('metadata_field_options.id', 'metadata_field_options.weight')
            ->whereIn('metadata_field_options.id', $selectedOptions)
            ->join('metadata_fields', function ($join) use ($metadataField) {
                $join->on('metadata_fields.id', '=', 'metadata_field_options.metadata_field_id')
                    ->where('metadata_fields.field', '=', $metadataField);
            })
            ->get();

        foreach ($metadataOptions as $metadataOption) {
            $profileExtended = new ProfileExtended(['metadata_field_option_id' => $metadataOption->id, 'metadata_field_option_weight' => $metadataOption->weight]);

            $profile->profile_extended()->save($profileExtended);
        }
        return true;
    }
}
