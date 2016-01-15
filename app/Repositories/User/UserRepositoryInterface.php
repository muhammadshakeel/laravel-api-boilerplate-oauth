<?php

namespace App\Repositories\User;

interface UserRepositoryInterface
{
    public function getAll();

    public function getRecommendedUsers($userId, $limit, $filterByIds, $lat, $lng, $radius);

    public function searchUser();
}
