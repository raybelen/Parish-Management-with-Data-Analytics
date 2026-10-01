<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/admin',
    'views' => true,
    'limiters' => ['login' => 'admin-login', 'two-factor' => 'admin-mfa'],
    'features' => [Features::twoFactorAuthentication(['confirm' => true, 'window' => 1, 'secret-length' => 32])],
];
