<?php

namespace App\Models;

use App\Support\Uploads;
use Illuminate\Database\Eloquent\Model;

class VendorPhoto extends Model
{
    protected $guarded = ['id'];

    public function url(): string
    {
        return Uploads::url($this->path);
    }
}
