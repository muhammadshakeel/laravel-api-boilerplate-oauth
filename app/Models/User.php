<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

use DB;
use App\Enums\Gender;
use App\Enums\RequestStatus;
use App\Enums\MeetingInterest;
use \Exception;

class User extends Model implements AuthenticatableContract,
                                    AuthorizableContract,
                                    CanResetPasswordContract
{
    use Authenticatable, Authorizable, CanResetPassword;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['email'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'activated_at', 'deleted_at'];

    /**
     * Get User's profile
     *
     * @return App/Models/Profile Profile object for this User
     */
    public function profile()
    {
        return $this->hasOne('App\Models\Profile');
    }

    public function albums()
    {
        return $this->hasMany('App\Models\Album');
    }

    public function contents()
    {
        return $this->hasManyThrough('App\Models\Content', 'App\Models\Album');
    }

    public function recent_contents()
    {
        return $this->contents()
            ->select(['contents.id', 'album_id', 'content_path', 'content_thumb_path', 'content_type'])
            ->orderBy('contents.created_at', 'desc')
            ->limit(6);
    }

    public function allBuddies()
    {
        return $this->belongsToMany('App\Models\User', 'user_buddies', 'user_id', 'buddy_id');
    }

    public function buddies($request_status=RequestStatus::ACCEPTED)
    {
        if (RequestStatus::ALL == $request_status) {
            $buddies = $this->allBuddies();
        } else {
            $buddies = $this->allBuddies()->where('user_buddies.request_status', $request_status);
        }
        return $buddies;
    }

    public function mutual_buddies($buddyId)
    {
        $mutual_buddies = $this->buddies()
            ->join('user_buddies as bb', function($join) use ($buddyId) {
                $join->on('bb.buddy_id', '=', 'ub.buddy_id')
                    ->where('bb.user_id', '=', $buddyId)
                    ->where('bb.request_status', '=', RequestStatus::ACCEPTED);
            })
            ->where('ub.user_id', '=', $this->id)
            ->where('ub.request_status', '=', RequestStatus::ACCEPTED);
        return $mutual_buddies;
    }

    public function requests()
    {
        return $requests = $this->buddies(RequestStatus::PENDING)->where('is_requester', 0);
    }

    public static function isUserExists($email)
    {
        return DB::table('users')->where('email', $email)->value('id') ? 0:1;
    }

    /************************ Scopes *************************/

    /**
     * Scope a query to only include popular users.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecommended($query, $filterByIds=[])
    {
        $optionFilter = $filterByIds;
        // Get All skill level metadata ids Query
        $skillMetaDataQuery = DB::table('metadata_field_options')
            ->select('id')
            ->where('metadata_field_id', '=', function ($query) {
                $query->select('id')
                    ->from('metadata_fields')
                    ->where('field', '=', 'skill_level');
            });
        $skillMetaDataIds = $skillMetaDataQuery->lists('id');
        $mySkillWeight = DB::table('metadata_field_options')->where('value', $this->profile->skill_level)->value('weight');

        if (empty($filterByIds)) {
            $optionFilter = $skillMetaDataIds;
            // $optionFilter = $filterCallback = function ($query) use ($skillMetaDataQuery) {
            //     $query->select('metadata_field_option_id')
            //         ->from('profile_extended')
            //         ->where('profile_id', $this->profile->id)
            //         ->union($skillMetaDataQuery);
            // };
        } else {
            $optionFilter = array_merge($filterByIds, $skillMetaDataIds);
        }

        $rankQuery = 'SUM(CASE
        	WHEN pe.metadata_field_option_id in ('.implode($skillMetaDataIds, ',').')
        		THEN -(ABS('.$mySkillWeight.'-CAST(pe.metadata_field_option_weight as SIGNED)))+4
        	ELSE pe.metadata_field_option_weight
        END) as rank';

        $query = $query->where('users.id', '<>', $this->id)
            ->with('profile')
            ->select('users.id', 'users.email', 'users.auth_type', DB::raw($rankQuery))
            ->whereIn('pe.metadata_field_option_id', $optionFilter)
            ->whereNotIn('users.id', function($query) {
                $query->select('buddy_id')
                    ->from('user_buddies')
                    ->where('user_id', $this->id);
                })
            ->join('profiles as p', 'users.id', '=', 'p.user_id')
            ->join('profile_extended as pe', 'pe.profile_id', '=', 'p.id')
            ->groupBy('pe.profile_id')
            ->orderBy('rank', 'desc')
            ->orderBy('p.first_name', 'asc')
            ->orderBy('p.last_name', 'asc');

        // Add Interested in factor for recommended users
        switch ($this->profile->settings_interested_in) {
            case MeetingInterest::MEN:
                $query = $query->where('p.gender', Gender::MALE);
                break;

            case MeetingInterest::WOMEN:
                $query = $query->where('p.gender', Gender::FEMALE);
                break;

            default:
                break;
        }
        // Add age range factors for recommended users
        if (!empty($this->profile->settings_min_age)) {
            $query = $query->where('p.date_of_birth', '<=', \Carbon\Carbon::now()->subYears($this->profile->settings_min_age)->toDateString());
        }
        if (!empty($this->profile->settings_max_age)) {
            $query = $query->where('p.date_of_birth', '>', \Carbon\Carbon::now()->subYears($this->profile->settings_max_age+1)->addDay(1)->toDateString());
        }

        return $query;
    }

    public function scopeByLocation($query, $lat, $lng, $radius)
    {
        $longitude = (float) $lng;
        $latitude = (float) $lat;
        $radius = (int) $radius; // in miles

        $lng_min = $longitude - $radius / abs(cos(deg2rad($latitude)) * 69);
        $lng_max = $longitude + $radius / abs(cos(deg2rad($latitude)) * 69);
        $lat_min = $latitude - ($radius / 69);
        $lat_max = $latitude + ($radius / 69);

        $query = $query->whereBetween('cur_lat', [$lat_min, $lat_max])
                    ->whereBetween('cur_lng', [$lng_min, $lng_max]);

        return $query;
    }

    // Accessors & Mutators
    public function getSocialNetworkAttribute($value)
    {
        return ($this->auth_type == 'email' ? 'shredd' : $this->auth_type);
    }
}
