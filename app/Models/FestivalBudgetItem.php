<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FestivalBudgetItem extends Model
{
    protected $fillable = ['society_id', 'festival_id', 'category', 'description', 'estimated_amount', 'vendor', 'quotation_amount', 'approved_limit', 'assigned_to'];
    protected $casts = ['estimated_amount' => 'decimal:2', 'quotation_amount' => 'decimal:2', 'approved_limit' => 'decimal:2'];

    public function festival() { return $this->belongsTo(Festival::class); }
}