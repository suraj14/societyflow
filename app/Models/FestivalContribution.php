<?php

namespace App\Models;

use App\Models\Concerns\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * pledged = an intention, worth nothing until a person with authority has verified the money arrived (received). Only `received`
 * counts, minus what was refunded. status/amount/receipt fields are NOT mass-assignable: they change only through FestivalService.
 */
class FestivalContribution extends Model
{
    use GeneratesDocumentNumber;

    public const METHODS = ['cash', 'cheque', 'upi', 'online', 'card', 'bank_transfer'];

    protected $fillable = ['society_id', 'festival_id', 'user_id', 'flat_id', 'contributor_name', 'is_anonymous', 'amount', 'request_token', 'created_by'];
    protected $casts = ['amount' => 'decimal:2', 'refunded_amount' => 'decimal:2', 'received_on' => 'date', 'refunded_at' => 'datetime', 'is_anonymous' => 'boolean'];

    public function festival() { return $this->belongsTo(Festival::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function flat() { return $this->belongsTo(Flat::class); }

    public static function nextReceiptNumber(int $societyId): string
    {
        return static::nextDocumentNumber($societyId, 'FCR', 'receipt_number', 6, true);
    }
}