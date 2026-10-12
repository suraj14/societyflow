<?php

namespace App\Models;

use App\Support\TemplateVariables;
use Illuminate\Database\Eloquent\Model;

/**
 * An SMS the society sends: its wording (matching the DLT-approved text), the notification it is used for, who it goes
 * to, and the Fast2SMS Message ID. A template with a Message ID (or the default Message ID from SMS settings) and a
 * Sender ID goes on the DLT route; one with neither goes out as Quick SMS. At most one active template per purpose and
 * society: that is "the template marked for it".
 */
class SmsTemplate extends Model
{
    public const AUDIENCES = ['resident' => 'Resident', 'guard' => 'Guard', 'admin' => 'Admin'];

    /** Characters allowed in one template body (the limit the editor's counter shows). */
    public const MAX_BODY = 320;

    // The older, unused sms_templates table has a different shape; this one holds the editable catalogue.
    protected $table = 'sms_message_templates';

    protected $fillable = ['society_id', 'name', 'purpose', 'audience', 'body', 'dlt_message_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function purposeLabel(): string
    {
        return TemplateVariables::smsLabels()[$this->purpose] ?? ucfirst(str_replace('_', ' ', (string) $this->purpose));
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience] ?? ucfirst((string) $this->audience);
    }

    /** Number of {{variable}} placeholders in the body (each is one DLT slot). */
    public function variableCount(): int
    {
        return count(TemplateVariables::placeholders((string) $this->body));
    }

    /**
     * How this template goes out, given the SMS settings (SmsService::routing()): its own Message ID first, the default
     * Message ID as the fallback, then Quick SMS. A Message ID without a Sender ID is reported, never sent as Quick SMS.
     *
     * @param array{sender_id: string, default_message_id: string} $routing
     * @return array{kind: string, label: string}  kind: dlt | dlt_default | dlt_blocked | quick
     */
    public function routeSummary(array $routing): array
    {
        $own = (string) $this->dlt_message_id;
        $messageId = $own !== '' ? $own : $routing['default_message_id'];

        if ($messageId === '') {
            return ['kind' => 'quick', 'label' => 'Quick SMS'];
        }
        if ($routing['sender_id'] === '') {
            return ['kind' => 'dlt_blocked', 'label' => 'DLT: ' . $messageId . ' - add a Sender ID'];
        }

        return $own !== ''
            ? ['kind' => 'dlt', 'label' => 'DLT: ' . $own]
            : ['kind' => 'dlt_default', 'label' => 'DLT: ' . $messageId . ' (default)'];
    }

    /** The template a purpose uses in a society: the newest active one. */
    public static function forPurpose(?int $societyId, string $purpose): ?self
    {
        return static::where('society_id', $societyId)->where('purpose', $purpose)->where('is_active', true)->orderByDesc('id')->first();
    }

    /** Make this the only active template for its purpose in its society. */
    public function makeTheActiveOne(): void
    {
        static::where('society_id', $this->society_id)->where('purpose', $this->purpose)->where('id', '!=', $this->id)->where('is_active', true)->update(['is_active' => false]);
        $this->forceFill(['is_active' => true])->save();
    }
}
