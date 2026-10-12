<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Model;

class DoorSale extends Model
{
    use BelongsToOrganizer;

    protected $guarded = ['id', 'organizer_id'];
}
