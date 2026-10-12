<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** Runs the platform itself: organizer sign-ups, listings and claims. */
class PlatformAdmin extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
