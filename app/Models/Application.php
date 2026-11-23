<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $casts = [
        'status' => ApplicationStatus::class,
    ];
}