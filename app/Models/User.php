<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganizer;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Organizer staff. Scoped to the organizer, so a user can only be found (and
 * so only sign in) on their own organizer's subdomain.
 */
class User extends Authenticatable
{
    use BelongsToOrganizer, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'organizer_id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }
}
