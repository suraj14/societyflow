<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use HasFactory;

    /**
     * A message that was deliberately not attempted (messaging off, key/template missing) is stored as
     * status `failed` + this marker + retry_count 3, because the status column cannot take a new value without a
     * table rebuild. Use isSkipped() to tell it apart: it is "Not sent", never "Failed" and never "Sent".
     */
    public const SKIPPED_PREFIX = 'Skipped: ';

    /**
     * Template variables that are secrets: a first password, a reset link or token, a one-time code. They are used to
     * render the message that is sent, and are NEVER written to the delivery log - the log (and the admin screen that
     * prints it) used to keep the temporary password of every new account in plain text.
     */
    public const SENSITIVE_VARIABLES = ['temporary_password', 'password', 'new_password', 'reset_token', 'reset_url', 'token', 'otp', 'code', 'api_key', 'secret'];

    public const HIDDEN = '[hidden]';

    /** @param array<string,mixed>|null $variables */
    public static function scrubbed(?array $variables): ?array
    {
        if ($variables === null) {
            return null;
        }
        foreach ($variables as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_VARIABLES, true) && $value !== null && $value !== '') {
                $variables[$key] = self::HIDDEN;
            }
        }

        return $variables;
    }

    public function setVariablesAttribute($value): void
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        $this->attributes['variables'] = $value === null ? null : json_encode(self::scrubbed((array) $value));
    }

    /** The message carried a secret that was not kept, so it cannot be rebuilt (and re-sent) from this row. */
    public function hasHiddenValues(): bool
    {
        return in_array(self::HIDDEN, (array) $this->variables, true);
    }

    public function isSkipped(): bool
    {
        return $this->status === 'failed' && str_starts_with((string) $this->error_message, self::SKIPPED_PREFIX);
    }

    protected $fillable = [
        'email_template_id',
        'society_id',
        'channel',
        'recipient_email',
        'recipient_phone',
        'recipient_name',
        'trigger_action',
        'notification_key',
        'provider_reference',
        'variables',
        'status',
        'error_message',
        'retry_count',
        'sent_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the email template
     */
    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    /**
     * Get the society
     */
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    /**
     * Scope for pending emails
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for failed emails
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for sent emails
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope for emails by trigger action
     */
    public function scopeByTrigger($query, string $trigger)
    {
        return $query->where('trigger_action', $trigger);
    }

    /**
     * Scope for emails by society
     */
    public function scopeForSociety($query, int $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    /**
     * Mark as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Check if should retry
     */
    public function shouldRetry(): bool
    {
        return $this->status === 'failed' && $this->retry_count < 3;
    }
}
