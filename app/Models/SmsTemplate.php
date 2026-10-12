<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An SMS the society sends: its wording (matching the DLT-approved text), the purpose it is used for and the Fast2SMS
 * Message ID. A template with a Message ID is sent on the DLT route; one without goes out as Quick SMS.
 */
class SmsTemplate extends Model
{
    public const PURPOSES = [
        'login_otp' => 'Login OTP',
        'gate_pass' => 'Gate pass',
        'visitor'   => 'Visitor',
        'new_bill'  => 'New bill',
        'notice'    => 'Notice',
        'event'     => 'Event',
        'other'     => 'Other',
    ];

    public const AUDIENCES = ['resident' => 'Resident', 'guard' => 'Guard', 'admin' => 'Admin'];

    /** Characters allowed in one template body (the DLT limit the page shows). */
    public const MAX_BODY = 320;

    // The older, unused sms_templates table has a different shape; this one holds the editable catalogue.
    protected $table = 'sms_message_templates';

    protected $fillable = ['society_id', 'name', 'purpose', 'audience', 'body', 'dlt_message_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function purposeLabel(): string
    {
        return self::PURPOSES[$this->purpose] ?? ucfirst(str_replace('_', ' ', (string) $this->purpose));
    }

    /** Number of {{variable}} placeholders in the body. */
    public function variableCount(): int
    {
        return (int) preg_match_all('/\{\{\s*[A-Za-z0-9_]+\s*\}\}/', (string) $this->body);
    }

    /** The template a purpose uses in a society: the newest active one. */
    public static function forPurpose(?int $societyId, string $purpose): ?self
    {
        return static::where('society_id', $societyId)->where('purpose', $purpose)->where('is_active', true)->orderByDesc('id')->first();
    }
}
