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
        return view('admin.email-templates.edit', compact('emailTemplate'));
    }

    /**
     * Update the specified email template
     */
    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $this->authorizeTemplate($emailTemplate);

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
        $this->authorizeTemplate($emailTemplate);

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
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to ' . $testEmail
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
    private function authorizeTemplate(EmailTemplate $emailTemplate)
    {
        if (!auth()->user()->hasRole('Super Admin') && $emailTemplate->society_id !== auth()->user()->society_id) {
            abort(403, 'Unauthorized access to email template.');
        }
    }
}