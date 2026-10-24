<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FestivalMember extends Model
{
    protected $fillable = ['society_id', 'festival_id', 'user_id', 'role', 'responsibility'];

    public function user() { return $this->belongsTo(User::class); }
}