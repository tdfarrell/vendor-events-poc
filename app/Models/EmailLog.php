<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Model;

/** Organizer-scoped: each organizer sees only the email sent in its name. */
class EmailLog extends Model
{
    use BelongsToOrganizer;

    protected $table = 'email_log';

    protected $guarded = ['id', 'organizer_id'];
}
