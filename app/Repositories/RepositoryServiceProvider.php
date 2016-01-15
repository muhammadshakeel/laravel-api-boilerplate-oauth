<?php
namespace App\Repositories;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
    * @var bool
    */
    //protected $defer = true;

    /**
    * @var array
    */
    protected $bindings = [
        User\UserRepositoryInterface::class  => User\UserRepository::class,
        Buddy\BuddyRepositoryInterface::class => Buddy\BuddyRepository::class,
        Profile\ProfileRepositoryInterface::class => Profile\ProfileRepository::class,
    ];

    /**
    * @return void
    */
    public function register()
    {
        //dd($this->bindings);
        foreach ($this->bindings as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
