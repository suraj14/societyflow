<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Enums\EmailEvent;
use App\Services\DynamicEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Email Template Mapping Controller
 * 
 * Allows admins to configure which email templates are sent for specific events.
 */
class EmailTemplateMappingController extends Controller
{
    /**
     * Show email template mapping interface
     */
    public function index(Request $request)
    {
        $this->authorize('manage_settings');

        $societyId = auth()->user()->society_id;
        
        // Get all available events
        $availableEvents = EmailEvent::labels();
        
        // Get current mappings
        $mappings = [];
        foreach ($availableEvents as $eventValue => $eventLabel) {
            $template = EmailTemplate::getTemplateForEvent($eventValue, $societyId);
            $mappings[$eventValue] = [
                'label' => $eventLabel,
                'template' => $template,
                'configured' => $template !== null,
            ];
        }

        // Get all templates for this society
        $templates = EmailTemplate::where(function($q) use ($societyId) {
            $q->where('society_id', $societyId)->orWhereNull('society_id');
        })->active()->get();

        return view('admin.email-template-mappings.index', compact('mappings', 'templates', 'availableEvents'));
    }

    /**
     * Update template mapping for an event
     */
    public function update(Request $request, string $eventValue)
    {
        $this->authorize('manage_settings');

        $validated = $request->validate([
            'template_id' => 'required|exists:email_templates,id',
        ]);

        $societyId = auth()->user()->society_id;

        // Verify event is valid
        if (!in_array($eventValue, EmailEvent::values())) {
            return response()->json(['error' => 'Invalid event'], 400);
        }

        // Get the template
        $template = EmailTemplate::findOrFail($validated['template_id']);

        // Verify template belongs to this society or is system template
        if ($template->society_id !== null && $template->society_id !== $societyId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Update trigger_event for this template
        $template->update(['trigger_event' => $eventValue]);

        Log::info('Email template mapping updated', [
            'template_id' => $template->id,
            'event' => $eventValue,
            'society_id' => $societyId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email template mapping updated successfully',
            'template' => $template,
        ]);
    }

    /**
     * Remove template mapping for an event
     */
    public function destroy(string $eventValue)
    {
        $this->authorize('manage_settings');

        $societyId = auth()->user()->society_id;

        // Verify event is valid
        if (!in_array($eventValue, EmailEvent::values())) {
            return response()->json(['error' => 'Invalid event'], 400);
        }

        // Find and clear the template for this event
        $template = EmailTemplate::getTemplateForEvent($eventValue, $societyId);

        if ($template) {
            $template->update(['trigger_event' => null]);

            Log::info('Email template mapping removed', [
                'template_id' => $template->id,
                'event' => $eventValue,
                'society_id' => $societyId,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email template mapping removed successfully',
        ]);
    }

    /**
     * Get available templates for an event
     */
    public function getTemplatesForEvent(string $eventValue)
    {
        $this->authorize('manage_settings');

        // Verify event is valid
        if (!in_array($eventValue, EmailEvent::values())) {
            return response()->json(['error' => 'Invalid event'], 400);
        }

        $societyId = auth()->user()->society_id;

        // Get templates that can be used for this event
        $templates = EmailTemplate::where(function($q) use ($societyId) {
            $q->where('society_id', $societyId)->orWhereNull('society_id');
        })->active()->get();

        return response()->json([
            'success' => true,
            'templates' => $templates,
            'event' => $eventValue,
            'eventLabel' => EmailEvent::labels()[$eventValue] ?? $eventValue,
        ]);
    }

    /**
     * Test email template mapping
     */
    public function test(Request $request, string $eventValue)
    {
        $this->authorize('manage_settings');

        $validated = $request->validate([
            'recipient_email' => 'required|email',
        ]);

        $societyId = auth()->user()->society_id;

        // Verify event is valid
        if (!in_array($eventValue, EmailEvent::values())) {
            return response()->json(['error' => 'Invalid event'], 400);
        }

        // Get template for this event
        $template = EmailTemplate::getTemplateForEvent($eventValue, $societyId);

        if (!$template) {
            return response()->json(['error' => 'No template configured for this event'], 400);
        }

        // Send test email
        $success = DynamicEmailService::sendByEvent(
            $eventValue,
            [[
                'email' => $validated['recipient_email'],
                'name' => 'Test User',
                'role' => 'Admin',
            ]],
            [
                'user_name' => 'Test User',
                'society_name' => auth()->user()->society->name ?? 'SocietyFlow',
            ],
            $societyId
        );

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to ' . $validated['recipient_email'],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email. Check logs for details.',
            ], 500);
        }
    }
}
