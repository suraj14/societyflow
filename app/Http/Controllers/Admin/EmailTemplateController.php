<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use App\Traits\HandlesFormSubmissions;

class EmailTemplateController extends BaseController
{
    use HandlesFormSubmissions;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:Admin|Super Admin');
    }

    /**
     * Display a listing of email templates
     */
    public function index()
    {
        $societyId = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
        
        $templates = EmailTemplate::when($societyId, function($query) use ($societyId) {
            return $query->forSociety($societyId);
        })->orderBy('name')->paginate(15);

        return view('admin.email-templates.index', compact('templates'));
    }

    /**
     * Show the form for creating a new email template
     */
    public function create()
    {
        $defaultTemplates = EmailTemplate::getDefaultTemplates();
        return view('admin.email-templates.create', compact('defaultTemplates'));
    }

    /**
     * Store a newly created email template
     */
    public function store(Request $request)
    {
        // handleFormSubmission() only ever uses the closure's return value
        // as JSON `data` - a bare redirect() built and returned inside the
        // closure was silently discarded in favour of a generic back()
        // with the default "Operation completed successfully" message
        // (confirmed, same bug as UserController's avatar upload fix;
        // present identically in all four methods of this controller).
        // The intended message/route are passed here instead, the
        // convention every other controller using this trait follows.
        return $this->handleFormSubmission(function() use ($request) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'slug' => 'required|string|max:255|unique:email_templates,slug',
                'subject' => 'required|string|max:500',
                'body' => 'required|string',
                'variables' => 'nullable|array',
                'type' => 'required|in:system,custom',
                'status' => 'required|in:active,inactive',
                'trigger_event' => 'nullable|string'
            ]);

            $validated['society_id'] = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;

            return EmailTemplate::create($validated);
        }, 'Email template created successfully.', 'admin.email-templates.index');
    }

    /**
     * Display the specified email template
     */
    public function show(EmailTemplate $emailTemplate)
    {
        $this->authorizeTemplate($emailTemplate);
        return view('admin.email-templates.show', compact('emailTemplate'));
    }

    /**
     * Show the form for editing the specified email template
     */
    public function edit(EmailTemplate $emailTemplate)
    {
        $this->authorizeTemplate($emailTemplate);
        if ($this->isSharedForCaller($emailTemplate)) {
            return redirect()->route('admin.email-templates.show', $emailTemplate)
                ->with('error', 'This is a shared system template, so it cannot be edited here. Use "Customise for my society" to make your own copy, which is then used instead.');
        }
        return view('admin.email-templates.edit', compact('emailTemplate'));
    }

    /**
     * Update the specified email template
     */
    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $this->authorizeTemplate($emailTemplate, true);

        return $this->handleFormSubmission(function() use ($request, $emailTemplate) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'subject' => 'required|string|max:500',
                'body' => 'required|string',
                'variables' => 'nullable|array',
                'status' => 'required|in:active,inactive',
                'trigger_event' => 'nullable|string'
            ]);

            $emailTemplate->update($validated);

            return $emailTemplate;
        }, 'Email template updated successfully.', 'admin.email-templates.index');
    }

    /**
     * Remove the specified email template
     */
    public function destroy(EmailTemplate $emailTemplate)
    {
        $this->authorizeTemplate($emailTemplate, true);

        // Checked before entering handleFormSubmission(), not inside the
        // closure: the closure's returned redirect() was discarded (see
        // store() above), so attempting to delete a system template
        // actually showed "Operation completed successfully" - the exact
        // opposite of what happened - with the template left untouched.
        if ($emailTemplate->type === 'system') {
            return back()->with('error', 'System templates cannot be deleted.');
        }

        return $this->handleFormSubmission(function() use ($emailTemplate) {
            $emailTemplate->delete();

            return true;
        }, 'Email template deleted successfully.', 'admin.email-templates.index');
    }

    /**
     * Initialize default templates for a society
     */
    public function initializeDefaults()
    {
        $societyId = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;

        // A slug is unique across the whole table (the store form validates it that way and the
        // database enforces it), and the defaults are normally already present as global
        // templates every society uses. Copying them per society therefore collided with the
        // unique index and failed the whole action with a generic error, every time. Create only
        // the defaults that really do not exist yet, and say what happened.
        //
        // "Exists" also means "a template already serves this trigger event". The default set
        // uses legacy hyphenated slugs while the seeded templates are keyed by trigger event, so
        // matching on slug alone would add a SECOND active template for an event that already
        // has one and make the choice between them ambiguous.
        $missing = collect(EmailTemplate::getDefaultTemplates())
            ->reject(fn ($template) => EmailTemplate::where('slug', $template['slug'])
                ->when(!empty($template['trigger_event']), fn ($q) => $q->orWhere('trigger_event', $template['trigger_event']))
                ->exists())
            ->values();

        if ($missing->isEmpty()) {
            return redirect()->route('admin.email-templates.index')
                ->with('success', 'Nothing to add: all default email templates already exist.');
        }

        return $this->handleFormSubmission(function () use ($missing, $societyId) {
            foreach ($missing as $template) {
                EmailTemplate::create(['society_id' => $societyId] + $template);
            }

            return true;
        }, "{$missing->count()} default email template(s) created.", 'admin.email-templates.index');
    }

    /**
     * Test email template
     */
    public function test(Request $request, EmailTemplate $emailTemplate)
    {
        $this->authorizeTemplate($emailTemplate);

        $request->validate([
            'test_email' => 'required|email'
        ]);

        // Nothing leaves the server while the kill switch is off, so say so instead of logging a "sent" row for it.
        if (!\App\Support\ExternalMessaging::enabled()) {
            return response()->json(\App\Support\ExternalMessaging::blockedResponse(\App\Support\ExternalMessaging::BLOCKED_DISABLED));
        }
        if (!\App\Support\ExternalMessaging::allows($request->test_email)) {
            return response()->json(\App\Support\ExternalMessaging::blockedResponse(\App\Support\ExternalMessaging::BLOCKED_NOT_ALLOWLISTED, \App\Support\ExternalMessaging::allowlistProblem() ? \App\Support\ExternalMessaging::ALLOWLIST_INVALID_MESSAGE : \App\Support\ExternalMessaging::NOT_ALLOWED_MESSAGE));
        }

        try {
            $testEmail = $request->test_email;
            $societyId = auth()->user()->society_id;

            // Load society-specific email configuration
            \App\Services\EmailConfigurationService::loadAndApplySettings($societyId);

            // Get society information
            $society = \App\Models\Society::find($societyId);

            // Prepare test variables
            $variables = [
                'user_name' => 'Test User',
                'society_name' => $society?->name ?? 'SocietyFlow',
                'society_email' => $society?->email ?? 'support@societyflow.com',
                'society_phone' => $society?->phone ?? '',
                'society_address' => $society?->address ?? '',
                'amount' => '5000',
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'bill_number' => 'TEST-001',
                'payment_date' => now()->format('Y-m-d'),
                'complaint_number' => 'TKT-001',
                'event_name' => 'Test Event',
                'event_date' => now()->addDays(1)->format('Y-m-d'),
                'event_time' => '18:00',
                'notice_title' => 'Test Notice',
                'notice_content' => 'This is a test notice content.',
                'login_url' => route('login'),
                'reset_url' => route('password.request'),
                'view_url' => '#',
                'receipt_url' => '#',
                'report_url' => '#',
            ];

            // Replace variables in subject and body
            $subject = $this->replaceVariables($emailTemplate->subject, $variables);
            $body = $this->replaceVariables($emailTemplate->body, $variables);

            // Send test email
            \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($testEmail, $subject, $body) {
                $message->to($testEmail)
                        ->subject($subject)
                        ->html($body);
            });

            // Log the test email
            \App\Models\EmailLog::create([
                'email_template_id' => $emailTemplate->id,
                'society_id' => $societyId,
                'recipient_email' => $testEmail,
                'recipient_name' => 'Test User',
                'trigger_action' => 'test_email',
                'variables' => $variables,
                'status' => \App\Support\MailDelivery::statusAfterSend($testEmail),
                'sent_at' => \App\Support\MailDelivery::statusAfterSend($testEmail) === 'sent' ? now() : null,
            ]);

            return response()->json([
                'success' => \App\Support\MailDelivery::statusAfterSend($testEmail) === \App\Support\MailDelivery::SENT,
                'message' => (\App\Support\MailDelivery::statusAfterSend() !== \App\Support\MailDelivery::SENT ? 'Not sent: the mailer is the local log file, so the test email was only written to the log.' : 'The mail server accepted the test email for ' . $testEmail . '. Check the inbox to confirm delivery.')
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Test email failed', [
                'template_id' => $emailTemplate->id,
                'test_email' => $request->test_email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Replace variables in text
     */
    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $text = str_replace($placeholder, (string)$value, $text);
        }

        return $text;
    }

    /**
     * Authorize template access
     */
    /**
     * Society isolation for templates. A society's Admin may READ (and test-send) the shared system templates (society_id NULL) that
     * the mapping screen links to - refusing them was a 403 on a template the product itself points at - and may change only
     * their own society's templates. Changing a shared one would change it for every society, so that stays Super Admin only; an Admin
     * customises it by making a society copy, which then takes precedence (EmailTemplate::getTemplateForEvent).
     */
    private function authorizeTemplate(EmailTemplate $emailTemplate, bool $write = false)
    {
        $user = auth()->user();
        if ($user->hasRole('Super Admin')) {
            return;
        }
        $own = $emailTemplate->society_id !== null && (int) $emailTemplate->society_id === (int) $user->society_id;
        $shared = $emailTemplate->society_id === null;
        if ($own || ($shared && !$write)) {
            return;
        }
        abort(403, $shared ? 'A shared system template can only be changed by a Super Admin. Customise a copy for your society instead.' : 'Unauthorized access to email template.');
    }

    private function isSharedForCaller(EmailTemplate $t): bool
    {
        return $t->society_id === null && !auth()->user()->hasRole('Super Admin');
    }

    /** Make this society its own copy of a shared template. The copy wins over the shared one for this society only. */
    public function customise(EmailTemplate $emailTemplate)
    {
        $this->authorizeTemplate($emailTemplate);
        $user = auth()->user();
        if ($emailTemplate->society_id !== null || !$user->society_id) {
            return back()->with('error', 'Only a shared system template can be customised, and only for a society.');
        }
        $existing = $emailTemplate->trigger_event
            ? EmailTemplate::where('society_id', $user->society_id)->where('trigger_event', $emailTemplate->trigger_event)->first() : null;
        if ($existing) {
            return redirect()->route('admin.email-templates.show', $existing)->with('success', 'Your society already has its own template for this event.');
        }
        $copy = EmailTemplate::create([
            'society_id' => $user->society_id, 'name' => $emailTemplate->name, 'slug' => $emailTemplate->slug . '-society-' . $user->society_id,
            'subject' => $emailTemplate->subject, 'body' => $emailTemplate->body, 'variables' => $emailTemplate->variables, 'type' => 'custom',
            'status' => $emailTemplate->status, 'trigger_event' => $emailTemplate->trigger_event,
        ]);

        return redirect()->route('admin.email-templates.edit', $copy)->with('success', 'Your own copy was created. Edit it here; the shared template is unchanged.');
    }
}