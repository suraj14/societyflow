<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmailEvent;
use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsAppPurposes;
use Illuminate\Http\Request;

/**
 * One catalogue of the messages SocietyFlow can send - SMS, WhatsApp and Email - with the provider identifier,
 * approval status, variables, the notification each is used for, and whether it is on.
 *
 * It is a catalogue plus the places to act, not a second editor: choosing an SMS template and mapping a WhatsApp
 * template use the existing Settings endpoints (same validation, same society isolation); email templates are
 * edited on the existing Email Templates pages. Approved wording is never edited here - a provider-approved
 * template's text is fixed and changing it would invalidate the approval.
 *
 * Route sits in the Settings group (Super Admin | Admin, restrict.staff). Everything is scoped to the signed-in
 * society; a Super Admin sees the platform-level (society-less) rows.
 */
class MessageTemplatesController extends Controller
{
    /** purpose group => label, matched on a keyword in the purpose / trigger key */
    private const PURPOSE_GROUPS = [
        'otp'       => ['OTP & login', ['otp', 'login', 'password', 'registered']],
        'notices'   => ['Notices & events', ['notice', 'event', 'document']],
        'visitors'  => ['Visitors & gate', ['visitor', 'access_pass', 'access-pass', 'gate', 'pass']],
        'bookings'  => ['Bookings', ['booking', 'facility']],
        'bills'     => ['Bills & payments', ['bill', 'payment', 'rent', 'receipt']],
        'tickets'   => ['Complaints & services', ['ticket', 'complaint', 'service']],
    ];

    private function societyId(): ?int
    {
        $user = auth()->user();

        return $user->hasRole('Super Admin') ? null : $user->society_id;
    }

    private function group(string $key): string
    {
        $key = strtolower($key);
        foreach (self::PURPOSE_GROUPS as $group => [, $words]) {
            foreach ($words as $w) {
                if (str_contains($key, $w)) {
                    return $group;
                }
            }
        }

        return 'other';
    }

    public function index(Request $request)
    {
        $societyId = $this->societyId();
        $channel = in_array($request->query('channel'), ['sms', 'whatsapp', 'email'], true) ? $request->query('channel') : 'all';
        $purpose = $request->query('purpose');
        $purposeLabels = ['all' => 'All purposes'] + array_map(fn ($g) => $g[0], self::PURPOSE_GROUPS) + ['other' => 'Other'];
        $purpose = array_key_exists($purpose, $purposeLabels) ? $purpose : 'all';

        $sms = SystemSetting::getGroup('sms', $societyId);
        $wa = SystemSetting::getGroup('whatsapp', $societyId);

        $rows = [];

        // ── SMS: two real purposes, each with its OWN Message ID (the OTP one is never reused for notifications)
        $otpMeta = is_array($sms['fast2sms_otp_template'] ?? null) ? $sms['fast2sms_otp_template'] : null;
        $notifMeta = is_array($sms['fast2sms_notification_template'] ?? null) ? $sms['fast2sms_notification_template'] : null;
        $viaOtpService = ($sms['fast2sms_route'] ?? 'otp') === 'otp';
        $rows[] = [
            'channel' => 'sms', 'purpose' => 'otp', 'name' => 'Login OTP', 'usage' => 'Login and password-reset codes',
            'identifier' => $viaOtpService ? ($sms['fast2sms_otp_id'] ?? '') : ($sms['fast2sms_template_id'] ?? ''),
            'identifier_label' => $viaOtpService ? 'OTP ID' : 'Message ID',
            'text' => $viaOtpService ? 'Fast2SMS OTP service (wording set in Fast2SMS)' : ($otpMeta['text'] ?? ''),
            'status' => $this->smsOtpStatus($sms, $viaOtpService), 'variables' => $viaOtpService ? 'filled by Fast2SMS' : '1 ({#var#} = the code)',
            'enabled' => (bool) ($sms['sms_enabled'] ?? false), 'action' => ['SMS settings', route('settings.index', ['tab' => 'sms'])],
        ];
        $notifId = (string) ($sms['fast2sms_notification_template_id'] ?? '');
        $rows[] = [
            'channel' => 'sms', 'purpose' => 'notices', 'name' => 'Notifications', 'usage' => 'Notices, society documents, gate-pass changes',
            'identifier' => $notifId, 'identifier_label' => 'Message ID', 'text' => $notifMeta['text'] ?? '',
            'status' => $notifId === '' ? 'missing' : 'configured', 'variables' => '1 ({#var#} = the message text)',
            'enabled' => (bool) ($sms['notification_sms_enabled'] ?? false), 'action' => ['SMS settings', route('settings.index', ['tab' => 'sms'])],
        ];

        // ── WhatsApp: every synced template, plus which notifications use it
        $templates = WhatsAppTemplate::bySociety($societyId)->orderByRaw("CASE WHEN upper(status) = 'APPROVED' THEN 0 ELSE 1 END")->orderBy('name')->get();
        $purposes = WhatsAppPurposes::all();
        $usedBy = [];
        foreach ($purposes as $key => $p) {
            $name = (string) ($wa["whatsapp_{$key}_template_name"] ?? '');
            if ($name !== '') {
                $usedBy[$name][] = ['key' => $key, 'label' => $p['label']];
            }
        }
        foreach ($templates as $t) {
            $uses = $usedBy[$t->name] ?? [];
            $rows[] = [
                'channel' => 'whatsapp', 'purpose' => $uses ? $this->group($uses[0]['key']) : 'other', 'name' => $t->displayName(),
                'usage' => $uses ? implode(', ', array_column($uses, 'label')) : 'Not used yet',
                'identifier' => (string) ($t->message_id ?: $t->external_id), 'identifier_label' => $t->message_id ? 'Message ID' : 'Template ID',
                'text' => (string) $t->body_text, 'status' => $t->isApproved() ? 'approved' : 'not_approved',
                'status_text' => ucfirst(strtolower((string) $t->status)), 'language' => $t->language, 'variables' => (string) $t->var_count,
                'enabled' => $uses !== [] && (bool) ($wa['whatsapp_enabled'] ?? false), 'action' => null,
            ];
        }
        $unmappedWhatsApp = collect($purposes)->filter(fn ($p, $key) => ($wa["whatsapp_{$key}_template_name"] ?? '') === '')->map(fn ($p) => $p['label'])->values()->all();

        // ── Email: the society's own templates and the shared system ones
        $events = EmailEvent::labels();
        $emails = EmailTemplate::where(fn ($q) => $q->where('society_id', $societyId)->orWhereNull('society_id'))->orderBy('name')->get();
        foreach ($emails as $e) {
            $rows[] = [
                'channel' => 'email', 'purpose' => $this->group((string) ($e->trigger_event ?: $e->slug)), 'name' => $e->name,
                'usage' => $e->trigger_event ? ($events[$e->trigger_event] ?? $e->trigger_event) : 'Not mapped to a notification',
                'identifier' => (string) $e->slug, 'identifier_label' => 'Slug', 'text' => (string) $e->subject,
                'status' => 'local', 'status_text' => $e->society_id ? 'Your template' : 'System template', 'variables' => (string) count((array) $e->variables),
                'enabled' => $e->status === 'active' && (bool) $e->trigger_event, 'action' => $e->society_id ? ['Edit', route('admin.email-templates.edit', $e)] : ['View', route('admin.email-templates.show', $e)],
            ];
        }

        $counts = ['all' => count($rows)] + collect($rows)->countBy('channel')->all();
        $visible = collect($rows)
            ->when($channel !== 'all', fn ($c) => $c->where('channel', $channel))
            ->when($purpose !== 'all', fn ($c) => $c->where('purpose', $purpose))
            ->values();

        return view('admin.message-templates.index', [
            'rows' => $visible, 'channel' => $channel, 'purpose' => $purpose, 'purposeLabels' => $purposeLabels, 'counts' => $counts,
            // masked: the view never receives a decrypted key
            'settings' => ['sms' => \App\Support\SettingSecrets::mask('sms', $sms), 'whatsapp' => \App\Support\SettingSecrets::mask('whatsapp', $wa)], 'unmappedWhatsApp' => $unmappedWhatsApp,
        ]);
    }

    private function smsOtpStatus(array $sms, bool $viaOtpService): string
    {
        if (empty($sms['fast2sms_api_key'])) {
            return 'missing_key';
        }
        if ($viaOtpService) {
            return empty($sms['fast2sms_otp_id']) ? 'missing' : 'configured';
        }
        $id = (string) ($sms['fast2sms_template_id'] ?? '');

        return $id === '' ? 'missing' : (strlen(preg_replace('/\D+/', '', $id)) >= 15 ? 'wrong_id' : 'configured');
    }
}
