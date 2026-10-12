<?php

namespace App\Models;

use App\Support\Uploads;
use App\Tenancy\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Model;

class EventPhoto extends Model
{
    use BelongsToOrganizer;

    protected $guarded = ['id', 'organizer_id'];

    public function url(): string
    {
        return Uploads::url($this->path);
    }
}
