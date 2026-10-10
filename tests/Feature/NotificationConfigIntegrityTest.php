<?php

namespace Tests\Feature;

use App\Events\UserCreated;
use App\Models\EmailTemplate;
use App\Providers\EventServiceProvider;
use App\Support\NotificationEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * The structural fix the architecture review asked for: one canonical
 * registry (NotificationEvents) instead of discovering a missing template
 * or a missing permission entry one bug report at a time (Notice,
 * bill-due-reminder, service-request-* all went missing silently before
 * being found this way). This test iterates that registry and the two
 * real delivery mechanisms it describes, and fails if either drifts out
 * of sync with what the registry claims.
 *
 * What makes a NEW event "fail loudly" instead of silently sending
 * nothing, concretely:
 *   - register it in NotificationEvents::all() with no matching
 *     EmailTemplate -> test_every_registered_event_has_an_active_template fails.
 *   - register it with a slug lookup but forget the role permission entry
 *     in EmailNotificationService::shouldReceiveEmail() -> test_every_slug_event_is_reachable_by_at_least_one_resident_role fails.
 *   - leave an EmailTemplate (society_id=null, active) that nothing in the
 *     registry points at -> test_no_unexplained_orphan_templates_exist fails.
 *   - register an event listener for a class nothing ever dispatches ->
 *     test_no_undocumented_dead_event_listeners_exist fails.
 */
class NotificationConfigIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_has_no_duplicate_event_keys(): void
    {
        $events = NotificationEvents::all();
        $this->assertSame(array_keys($events), array_unique(array_keys($events)));
        $this->assertNotEmpty($events);
    }

    public function test_every_registered_event_declares_a_supported_lookup_and_at_least_one_channel(): void
    {
        foreach (NotificationEvents::all() as $key => $event) {
            $this->assertContains(
                $event['lookup'],
                [NotificationEvents::LOOKUP_TRIGGER_EVENT, NotificationEvents::LOOKUP_SLUG],
                "Event '{$key}' declares an unknown lookup type."
            );
            $this->assertNotEmpty($event['templates'], "Event '{$key}' declares no templates.");
            $this->assertNotEmpty($event['channels'], "Event '{$key}' declares no channels.");
            foreach ($event['channels'] as $channel) {
                // Push was removed from SocietyFlow entirely - not part of
                // the approved communication scope. Deliberately absent
                // here too, so a future event accidentally declaring it
                // fails this test instead of silently round-tripping
                // through a registry that no longer has anything to send it.
                $this->assertContains(
                    $channel,
                    ['email', 'sms', 'whatsapp'],
                    "Event '{$key}' declares unsupported channel '{$channel}'."
                );
            }
        }
    }

    /**
     * For every event whose 'channels' includes 'email', at least one
     * active, globally-available (society_id=null) EmailTemplate must
     * exist for every template key it names, matched on the column its
     * own 'lookup' says it is read through. This is the exact check that
     * would have failed before this round's fixes for
     * service-request-{assigned,in_progress,completed,cancelled} and
     * bill-due-reminder - both were registered dispatch points with zero
     * matching template rows.
     */
    public function test_every_registered_event_has_an_active_template(): void
    {
        foreach (NotificationEvents::all() as $key => $event) {
            if (!in_array('email', $event['channels'], true)) {
                continue;
            }

            $column = $event['lookup'] === NotificationEvents::LOOKUP_SLUG ? 'slug' : 'trigger_event';

            foreach ($event['templates'] as $templateKey) {
                $exists = EmailTemplate::where($column, $templateKey)
                    ->whereNull('society_id')
                    ->where('status', 'active')
                    ->exists();

                $this->assertTrue(
                    $exists,
                    "Event '{$key}' has no active, global EmailTemplate with {$column} = '{$templateKey}'. ".
                    "Add a migration seeding it (see 2026_10_07_000006 for the pattern) - do not patch this one template by hand."
                );
            }
        }
    }

    /**
     * Every template an event names must declare at least one variable -
     * an empty variables array means the template editor UI has nothing
     * to offer an admin customizing it, and usually means the template was
     * seeded as a placeholder, not for real use.
     */
    public function test_every_registered_template_declares_its_variables(): void
    {
        foreach (NotificationEvents::all() as $key => $event) {
            $column = $event['lookup'] === NotificationEvents::LOOKUP_SLUG ? 'slug' : 'trigger_event';

            foreach ($event['templates'] as $templateKey) {
                $template = EmailTemplate::where($column, $templateKey)->whereNull('society_id')->first();
                if (!$template) {
                    continue; // already reported by test_every_registered_event_has_an_active_template
                }

                $this->assertNotEmpty(
                    $template->variables,
                    "Event '{$key}'s template ('{$templateKey}') declares no variables."
                );
            }
        }
    }

    /**
     * EmailNotificationService::sendByTrigger() only sends to a recipient
     * if shouldReceiveEmail() allows their role for that slug - a second,
     * independent place a slug-based event can go silent even though its
     * template exists (exactly what happened to service-request-* and
     * bill-due-reminder: template missing AND permission missing, two
     * separate bugs on the same feature). This asserts every slug-lookup
     * event is reachable by at least one of the four resident roles via
     * shouldReceiveEmail()'s exact matching rules (private method, so
     * reflection - there is no public API for "would this role get this
     * email", which is itself why this bug class kept recurring).
     */
    public function test_every_slug_event_is_reachable_by_at_least_one_resident_role(): void
    {
        $method = new \ReflectionMethod(\App\Services\EmailNotificationService::class, 'shouldReceiveEmail');
        $method->setAccessible(true);

        $residentRoles = ['Apartment Owner', 'Villa Owner', 'Owner', 'Tenant'];

        foreach (NotificationEvents::all() as $key => $event) {
            if ($event['lookup'] !== NotificationEvents::LOOKUP_SLUG) {
                continue;
            }

            foreach ($event['templates'] as $slug) {
                $reachable = false;
                foreach ($residentRoles as $role) {
                    if ($method->invoke(null, ['email' => 'probe@test.local', 'role' => $role], $slug)) {
                        $reachable = true;
                        break;
                    }
                }

                $this->assertTrue(
                    $reachable,
                    "Event '{$key}' (slug '{$slug}') has a template but no resident role is permitted to receive it ".
                    "in EmailNotificationService::shouldReceiveEmail() - add the slug, or a 'prefix-*' wildcard, to the relevant role(s)."
                );
            }
        }
    }

    /**
     * Every active, globally-available EmailTemplate must be reachable by
     * at least one registered event (matched on either its slug or its
     * trigger_event - a single row can legitimately serve both lookup
     * systems, e.g. 'new-event' is reached via trigger_event='event_created'
     * AND via slug='new-event' for event.updated) or be in the documented
     * exception list. A new, unexplained orphan means either a template
     * was added without wiring anything to send it, or an event was wired
     * to the wrong template key.
     */
    public function test_no_unexplained_orphan_templates_exist(): void
    {
        $registered = NotificationEvents::allTemplateKeys();
        $known = NotificationEvents::knownOrphanTemplateKeys();

        $templates = EmailTemplate::whereNull('society_id')->where('status', 'active')->get(['slug', 'trigger_event']);

        foreach ($templates as $template) {
            $reachable = in_array($template->slug, $registered, true)
                || in_array($template->trigger_event, $registered, true);
            $documented = in_array($template->slug, $known, true)
                || in_array($template->trigger_event, $known, true);

            $this->assertTrue(
                $reachable || $documented,
                "Template slug='{$template->slug}' trigger_event='{$template->trigger_event}' is active but no registered ".
                "event in NotificationEvents points at it, and it is not in knownOrphanTemplateKeys(). Either wire a real ".
                "event to it, deactivate it, or document why it is an intentional orphan."
            );
        }
    }

    /**
     * Every Event class registered in EventServiceProvider::$listen must
     * either have a real dispatch call site somewhere in app/ (outside
     * Events/Listeners/Providers themselves) or be in the documented dead
     * list. Confirms BillGenerated/PaymentOverdue/ReportGenerated are
     * exactly the dead set, and fails the moment a new listener is wired
     * for an event nobody fires without being added here deliberately.
     */
    public function test_no_undocumented_dead_event_listeners_exist(): void
    {
        $provider = new EventServiceProvider($this->app);
        $listen = $provider->listens();

        $known = NotificationEvents::knownDeadEventClasses();

        $appRoot = base_path('app');
        $searchDirs = ['Http', 'Models', 'Console', 'Jobs', 'Services'];

        foreach (array_keys($listen) as $eventClass) {
            // Laravel's own auth events (Registered, etc.) aren't part of
            // this project's notification registry at all.
            if (!str_starts_with($eventClass, 'App\\Events\\')) {
                continue;
            }

            $shortName = class_basename($eventClass);
            $dispatched = false;

            foreach ($searchDirs as $dir) {
                $path = $appRoot . DIRECTORY_SEPARATOR . $dir;
                if (!is_dir($path)) {
                    continue;
                }

                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
                foreach ($iterator as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }
                    $contents = file_get_contents($file->getPathname());
                    if (str_contains($contents, "{$shortName}::dispatch(") || str_contains($contents, "new {$shortName}(")) {
                        $dispatched = true;
                        break 2;
                    }
                }
            }

            if (!$dispatched) {
                $this->assertContains(
                    $eventClass,
                    $known,
                    "Event class '{$eventClass}' has a registered listener but no dispatch call site was found in app/{Http,Models,Console,Jobs,Services}. ".
                    "Either dispatch it from a real business action, or add it to NotificationEvents::knownDeadEventClasses() with a reason."
                );
            }
        }
    }

    /**
     * The list of known-dead event classes is itself only valid while each
     * one still genuinely has no dispatch site - this is the inverse of
     * the previous test and catches the opposite drift: someone wires a
     * real dispatch for e.g. BillGenerated and forgets to remove it from
     * the documented-dead list, leaving the registry claiming an event is
     * dead when it is now live (and therefore unaudited by
     * test_every_registered_event_has_an_active_template).
     */
    public function test_known_dead_event_classes_are_still_actually_undispatched(): void
    {
        $appRoot = base_path('app');
        $searchDirs = ['Http', 'Models', 'Console', 'Jobs', 'Services'];

        foreach (NotificationEvents::knownDeadEventClasses() as $eventClass) {
            $shortName = class_basename($eventClass);
            $dispatched = false;

            foreach ($searchDirs as $dir) {
                $path = $appRoot . DIRECTORY_SEPARATOR . $dir;
                if (!is_dir($path)) {
                    continue;
                }
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
                foreach ($iterator as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }
                    $contents = file_get_contents($file->getPathname());
                    if (str_contains($contents, "{$shortName}::dispatch(") || str_contains($contents, "new {$shortName}(")) {
                        $dispatched = true;
                        break 2;
                    }
                }
            }

            $this->assertFalse(
                $dispatched,
                "'{$eventClass}' is listed in knownDeadEventClasses() but a dispatch call site now exists - remove it from ".
                "that list and register it as a real event in NotificationEvents::all() instead."
            );
        }
    }
}
