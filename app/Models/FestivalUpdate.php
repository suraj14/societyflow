<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FestivalUpdate extends Model
{
    protected $fillable = ['society_id', 'festival_id', 'title', 'body', 'scheduled_for', 'created_by'];
    protected $casts = ['scheduled_for' => 'datetime'];
}