<?php
namespace App\Enums;

use MyCLabs\Enum\Enum;

/**
 * Action enum
 */
class AuthType extends Enum
{
    const EMAIL = 'email';
    const GOOGLE = 'google';
    const FACEBOOK = 'facebook';
}
