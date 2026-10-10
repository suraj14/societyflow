<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Contract for the shared property-type field: the callback a page hands it
 * (`sfOnChange`) is invoked from the GLOBAL scope - by an inline onchange and
 * by the partial's own load-time trigger. Found by the browser audit: on
 * Add Tenant the function lived only inside a DOMContentLoaded closure, so in
 * a single-type society (e.g. Royal Eden, villas only) the villa picker never
 * appeared and an admin could not assign a villa to a new tenant.
 *
 * PHP cannot execute the page's JavaScript, so this pins the two source-level
 * facts that make it work; the behaviour itself was verified in real Chrome.
 */
class PropertyTypeFieldContractTest extends TestCase
{
    private function source(string $relative): string
    {
        return file_get_contents(resource_path('views/' . $relative));
    }

    public function test_the_partial_triggers_after_the_pages_own_scripts_have_run(): void
    {
        $partial = $this->source('partials/property-type-field.blade.php');

        $this->assertStringContainsString("window.addEventListener('load'", $partial);
        $this->assertStringNotContainsString("document.addEventListener('DOMContentLoaded'", $partial,
            'a DOMContentLoaded handler in this earlier inline script runs before the page defines its callback');
    }

    public function test_add_tenant_exposes_its_property_type_callback_globally(): void
    {
        $page = $this->source('admin/tenants/create.blade.php');

        // The function is declared inside this page's DOMContentLoaded handler, so without the
        // explicit assignment the partial's inline onchange / load trigger cannot find it.
        $this->assertMatchesRegularExpression('/window\.updatePropertyTypeSelection\s*=\s*updatePropertyTypeSelection/', $page);
    }

    // Deliberately no generic "every page using the partial" scan: from source alone a function that is
    // trapped in a closure cannot be told from a global one (the booking form's is genuinely global and a
    // heuristic flagged it). Other callers were checked in the browser: typeof window.<fn> === 'function'.
}
