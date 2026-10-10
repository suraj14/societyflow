<?php

namespace Tests\Feature;

use App\Events\SocietyDocumentPublished;
use App\Jobs\DispatchEventNotification;
use App\Models\Society;
use App\Models\SocietyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Society Rules & Documents: upload -> draft -> publish -> versions, read
 * by each role, and the isolation guarantees (other society, drafts,
 * archived versions, guard-only visibility) through web and API alike.
 */
class SocietyDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Society $societyA;
    private Society $societyB;
    private User $adminA;
    private User $tenantA;
    private User $ownerA;
    private User $guardA;
    private User $staffA;
    private User $adminB;
    private User $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->societyA = $this->makeSociety('Royal Eden');
        $this->societyB = $this->makeSociety('Other Society');

        $this->adminA  = $this->makeUserWithRole($this->societyA, 'Admin');
        $this->tenantA = $this->makeUserWithRole($this->societyA, 'Tenant');
        $this->ownerA  = $this->makeUserWithRole($this->societyA, 'Villa Owner');
        $this->guardA  = $this->makeUserWithRole($this->societyA, 'Guard');
        $this->staffA  = $this->makeUserWithRole($this->societyA, 'Staff');
        $this->adminB  = $this->makeUserWithRole($this->societyB, 'Admin');
        $this->tenantB = $this->makeUserWithRole($this->societyB, 'Tenant');
    }

    private function pdf(string $name = 'parking-rules.pdf', ?string $content = null): UploadedFile
    {
        $content ??= "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function upload(User $admin, array $overrides = []): SocietyDocument
    {
        $this->actingAs($admin)->post(route('admin.society-documents.store'), array_merge([
            'title'    => 'Parking Rules',
            'category' => 'parking',
            'version'  => 'v1',
            'file'     => $this->pdf(),
        ], $overrides))->assertSessionHasNoErrors()->assertRedirect();

        return SocietyDocument::latest('id')->firstOrFail();
    }

    private function published(User $admin, array $overrides = [], array $publish = []): SocietyDocument
    {
        $doc = $this->upload($admin, $overrides);
        $this->actingAs($admin)->post(route('admin.society-documents.publish', $doc), $publish)->assertRedirect();

        return $doc->fresh();
    }

    // ── Admin upload ────────────────────────────────────────────────────────

    public function test_admin_upload_creates_a_private_draft_in_their_society(): void
    {
        $doc = $this->upload($this->adminA);

        $this->assertSame('draft', $doc->status);
        $this->assertSame($this->societyA->id, $doc->society_id);
        $this->assertSame($this->adminA->id, $doc->uploaded_by);
        $this->assertSame(1, $doc->version_number);
        Storage::disk('local')->assertExists($doc->file_path);
        $this->assertStringStartsWith("society-documents/{$this->societyA->id}/", $doc->file_path);
        $this->assertFileDoesNotExist(public_path($doc->file_path));
    }

    public function test_draft_is_invisible_to_residents_web_and_api(): void
    {
        $doc = $this->upload($this->adminA);

        $this->actingAs($this->tenantA)->get(route('society-rules.index'))->assertOk()->assertDontSee('Parking Rules');
        $this->actingAs($this->tenantA)->get(route('society-rules.show', $doc->id))->assertNotFound();
        $this->actingAs($this->tenantA)->get(route('society-rules.file', $doc->id))->assertNotFound();

        Sanctum::actingAs($this->tenantA);
        $this->getJson("/api/v1/society-documents/{$doc->id}")->assertNotFound();
        $this->getJson('/api/v1/society-documents')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_published_document_is_readable_by_tenant_and_owner(): void
    {
        $doc = $this->published($this->adminA);

        foreach ([$this->tenantA, $this->ownerA] as $reader) {
            $this->actingAs($reader)->get(route('society-rules.index'))->assertOk()->assertSee('Parking Rules');
            $this->actingAs($reader)->get(route('society-rules.show', $doc->id))->assertOk()->assertSee('Parking Rules');

            $file = $this->actingAs($reader)->get(route('society-rules.file', $doc->id));
            $file->assertOk();
            $this->assertSame('application/pdf', $file->headers->get('Content-Type'));
            $this->assertStringStartsWith('inline', $file->headers->get('Content-Disposition'));

            $download = $this->actingAs($reader)->get(route('society-rules.file', ['document' => $doc->id, 'download' => 1]));
            $download->assertOk();
            $this->assertStringStartsWith('attachment', $download->headers->get('Content-Disposition'));
        }
    }

    public function test_guard_sees_only_guard_visible_documents(): void
    {
        $residentOnly = $this->published($this->adminA, ['title' => 'Pet Policy', 'category' => 'society_rules']);
        $gateRules    = $this->published($this->adminA, ['title' => 'Gate SOP', 'category' => 'security', 'visible_to_guards' => 1]);

        $this->actingAs($this->guardA)->get(route('guard.rules.index'))
            ->assertOk()->assertSee('Gate SOP')->assertDontSee('Pet Policy');
        $this->actingAs($this->guardA)->get(route('guard.rules.show', $gateRules->id))->assertOk();
        $this->actingAs($this->guardA)->get(route('guard.rules.file', $gateRules->id))->assertOk();
        $this->actingAs($this->guardA)->get(route('guard.rules.show', $residentOnly->id))->assertNotFound();
        $this->actingAs($this->guardA)->get(route('guard.rules.file', $residentOnly->id))->assertNotFound();

        // The guard is kept to /guard: the resident URL is not reachable at all.
        $this->actingAs($this->guardA)->get(route('society-rules.index'))->assertForbidden();

        Sanctum::actingAs($this->guardA);
        $ids = collect($this->getJson('/api/v1/society-documents')->assertOk()->json('data'))->pluck('id');
        $this->assertEquals([$gateRules->id], $ids->all());
        $this->getJson("/api/v1/society-documents/{$residentOnly->id}/file")->assertNotFound();
    }

    public function test_staff_and_accountant_cannot_read_documents(): void
    {
        $doc = $this->published($this->adminA);
        $accountant = $this->makeUserWithRole($this->societyA, 'Accountant');

        foreach ([$this->staffA, $accountant] as $user) {
            $this->actingAs($user)->get(route('society-rules.index'))->assertForbidden();
            $this->actingAs($user)->get(route('society-rules.file', $doc->id))->assertForbidden();
            Sanctum::actingAs($user);
            $this->getJson("/api/v1/society-documents/{$doc->id}/file")->assertForbidden();
        }
    }

    // ── Society isolation ───────────────────────────────────────────────────

    public function test_other_society_cannot_list_view_or_download_by_id(): void
    {
        $doc = $this->published($this->adminA);

        $this->actingAs($this->tenantB)->get(route('society-rules.index'))->assertOk()->assertDontSee('Parking Rules');
        $this->actingAs($this->tenantB)->get(route('society-rules.show', $doc->id))->assertNotFound();
        $this->actingAs($this->tenantB)->get(route('society-rules.file', $doc->id))->assertNotFound();
        $this->actingAs($this->tenantB)->post(route('society-rules.acknowledge', $doc->id))->assertNotFound();

        Sanctum::actingAs($this->tenantB);
        $this->getJson('/api/v1/society-documents')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/society-documents/{$doc->id}")->assertNotFound();
        $this->getJson("/api/v1/society-documents/{$doc->id}/file")->assertNotFound();
        $this->postJson("/api/v1/society-documents/{$doc->id}/acknowledge")->assertNotFound();
    }

    public function test_other_society_admin_cannot_manage_the_document(): void
    {
        $doc = $this->published($this->adminA);

        $this->actingAs($this->adminB)->get(route('admin.society-documents.index'))->assertOk()->assertDontSee('Parking Rules');
        $this->actingAs($this->adminB)->get(route('admin.society-documents.show', $doc))->assertForbidden();
        $this->actingAs($this->adminB)->get(route('admin.society-documents.file', $doc))->assertForbidden();
        $this->actingAs($this->adminB)->put(route('admin.society-documents.update', $doc), [
            'title' => 'Hijacked', 'category' => 'parking', 'version' => 'v9',
        ])->assertForbidden();
        $this->actingAs($this->adminB)->post(route('admin.society-documents.unpublish', $doc))->assertForbidden();
        $this->actingAs($this->adminB)->post(route('admin.society-documents.archive', $doc))->assertForbidden();
        $this->actingAs($this->adminB)->delete(route('admin.society-documents.destroy', $doc))->assertForbidden();
        $this->actingAs($this->adminB)->post(route('admin.society-documents.versions.store', $doc), [
            'title' => 'x', 'category' => 'parking', 'version' => 'v2', 'file' => $this->pdf(),
        ])->assertForbidden();

        $this->assertSame('Parking Rules', $doc->fresh()->title);
        $this->assertSame('published', $doc->fresh()->status);
    }

    public function test_hidden_society_id_field_cannot_plant_a_document_in_another_society(): void
    {
        $doc = $this->upload($this->adminA, ['society_id' => $this->societyB->id]);

        $this->assertSame($this->societyA->id, $doc->society_id);
    }

    public function test_residents_cannot_reach_management_routes(): void
    {
        $doc = $this->published($this->adminA);

        $this->actingAs($this->tenantA)->get(route('admin.society-documents.index'))->assertForbidden();
        $this->actingAs($this->tenantA)->post(route('admin.society-documents.store'), [
            'title' => 'x', 'category' => 'parking', 'version' => 'v1', 'file' => $this->pdf(),
        ])->assertForbidden();
        $this->actingAs($this->tenantA)->post(route('admin.society-documents.unpublish', $doc))->assertForbidden();
        $this->actingAs($this->tenantA)->delete(route('admin.society-documents.destroy', $doc))->assertForbidden();
        $this->assertSame('published', $doc->fresh()->status);
    }

    public function test_super_admin_must_choose_a_society_and_can_manage_any(): void
    {
        $super = $this->makeUserWithRole($this->societyA, 'Super Admin', ['society_id' => null]);

        $this->actingAs($super)->post(route('admin.society-documents.store'), [
            'title' => 'Bye-Laws', 'category' => 'bye_laws', 'version' => '2026', 'file' => $this->pdf(),
        ])->assertSessionHasErrors('society_id');

        $doc = $this->upload($super, ['society_id' => $this->societyB->id, 'title' => 'Bye-Laws']);
        $this->assertSame($this->societyB->id, $doc->society_id);
        $this->actingAs($super)->get(route('admin.society-documents.show', $doc))->assertOk();
    }

    // ── Lifecycle ───────────────────────────────────────────────────────────

    public function test_unpublish_removes_visibility(): void
    {
        $doc = $this->published($this->adminA);
        $this->actingAs($this->adminA)->post(route('admin.society-documents.unpublish', $doc))->assertRedirect();

        $this->assertSame('unpublished', $doc->fresh()->status);
        $this->actingAs($this->tenantA)->get(route('society-rules.show', $doc->id))->assertNotFound();
        $this->actingAs($this->tenantA)->get(route('society-rules.file', $doc->id))->assertNotFound();
    }

    public function test_new_version_preserves_history_and_becomes_current_only_when_published(): void
    {
        $v1 = $this->published($this->adminA);

        $this->actingAs($this->adminA)->post(route('admin.society-documents.versions.store', $v1), [
            'title' => 'Parking Rules', 'category' => 'parking', 'version' => 'v2', 'file' => $this->pdf('parking-v2.pdf'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $v2 = SocietyDocument::latest('id')->first();

        $this->assertSame($v1->series_key, $v2->series_key);
        $this->assertSame(2, $v2->version_number);
        $this->assertSame('draft', $v2->status);
        // v1 is still what residents read until v2 is published.
        $this->actingAs($this->tenantA)->get(route('society-rules.show', $v1->id))->assertOk();
        $this->actingAs($this->tenantA)->get(route('society-rules.show', $v2->id))->assertNotFound();

        $this->actingAs($this->adminA)->post(route('admin.society-documents.publish', $v2))->assertRedirect();

        $this->assertSame('archived', $v1->fresh()->status);
        $this->assertSame('published', $v2->fresh()->status);
        $this->assertSame(1, SocietyDocument::where('series_key', $v1->series_key)->where('status', 'published')->count());

        // Residents: only v2. Admin: both versions, v1 file still on disk.
        $this->actingAs($this->tenantA)->get(route('society-rules.index'))->assertSee('v2');
        $this->actingAs($this->tenantA)->get(route('society-rules.file', $v1->id))->assertNotFound();
        $this->actingAs($this->tenantA)->get(route('society-rules.file', $v2->id))->assertOk();
        $this->actingAs($this->adminA)->get(route('admin.society-documents.file', $v1))->assertOk();
        Storage::disk('local')->assertExists($v1->file_path);
        $this->actingAs($this->adminA)->get(route('admin.society-documents.show', $v2))->assertOk()->assertSee('v1')->assertSee('v2');
    }

    public function test_only_a_never_published_draft_can_be_deleted(): void
    {
        $draft = $this->upload($this->adminA);
        $this->actingAs($this->adminA)->delete(route('admin.society-documents.destroy', $draft))->assertRedirect();
        $this->assertDatabaseMissing('society_documents', ['id' => $draft->id]);
        Storage::disk('local')->assertMissing($draft->file_path);

        $live = $this->published($this->adminA);
        $this->actingAs($this->adminA)->delete(route('admin.society-documents.destroy', $live))->assertSessionHas('error');
        $this->assertDatabaseHas('society_documents', ['id' => $live->id]);

        $this->actingAs($this->adminA)->post(route('admin.society-documents.archive', $live))->assertRedirect();
        $this->assertSame('archived', $live->fresh()->status);
        $this->actingAs($this->tenantA)->get(route('society-rules.show', $live->id))->assertNotFound();
    }

    public function test_missing_file_on_disk_is_a_clean_404(): void
    {
        $doc = $this->published($this->adminA);
        Storage::disk('local')->delete($doc->file_path);

        $this->actingAs($this->tenantA)->get(route('society-rules.file', $doc->id))->assertNotFound();
        $this->actingAs($this->adminA)->get(route('admin.society-documents.file', $doc))->assertNotFound();
    }

    // ── File validation ─────────────────────────────────────────────────────

    public function test_non_pdf_and_disguised_files_are_rejected(): void
    {
        $cases = [
            'text renamed to pdf'   => UploadedFile::fake()->createWithContent('rules.pdf', 'just some text'),
            'executable as pdf'     => UploadedFile::fake()->createWithContent('rules.pdf', "MZ\x90\x00\x03\x00\x00\x00" . str_repeat("\x00", 64)),
            'php script'            => UploadedFile::fake()->createWithContent('rules.php', '<?php echo 1;'),
            'png image'             => UploadedFile::fake()->createWithContent('rules.png', "\x89PNG\r\n\x1a\n" . str_repeat("\x00", 32)),
            'truncated/corrupt pdf' => UploadedFile::fake()->createWithContent('rules.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog"),
        ];

        foreach ($cases as $label => $file) {
            $this->actingAs($this->adminA)->post(route('admin.society-documents.store'), [
                'title' => 'Bad', 'category' => 'other', 'version' => 'v1', 'file' => $file,
            ])->assertSessionHasErrors('file');
            $this->assertSame(0, SocietyDocument::count(), "Accepted: {$label}");
        }

        // Scoped to this society's folder: the fake disk's directory is shared by every PHPUnit
        // process, so listing the whole disk also sees files other tests (sharded runs) are writing.
        $this->assertEmpty(Storage::disk('local')->allFiles('society-documents/' . $this->societyA->id));
    }

    public function test_oversized_pdf_is_rejected(): void
    {
        $big = UploadedFile::fake()->create('huge.pdf', SocietyDocument::MAX_UPLOAD_KB + 1, 'application/pdf');

        $this->actingAs($this->adminA)->post(route('admin.society-documents.store'), [
            'title' => 'Huge', 'category' => 'other', 'version' => 'v1', 'file' => $big,
        ])->assertSessionHasErrors('file');
        $this->assertSame(0, SocietyDocument::count());
    }

    public function test_stored_filename_is_never_the_client_supplied_path(): void
    {
        $doc = $this->upload($this->adminA, ['file' => $this->pdf('../../evil name;rm -rf.pdf')]);

        $this->assertMatchesRegularExpression('/^society-documents\/\d+\/[0-9a-f-]{36}\.pdf$/', $doc->file_path);
        $this->assertSame('evil-namerm-rf.pdf', $doc->original_filename);
    }

    // ── Notifications ───────────────────────────────────────────────────────

    public function test_publishing_without_announce_sends_nothing(): void
    {
        Event::fake([SocietyDocumentPublished::class]);
        $this->published($this->adminA);
        Event::assertNotDispatched(SocietyDocumentPublished::class);
    }

    public function test_announce_dispatches_once_with_the_chosen_channels(): void
    {
        Event::fake([SocietyDocumentPublished::class]);

        $doc = $this->published($this->adminA, [], ['announce' => 1, 'channels' => ['email', 'sms']]);

        Event::assertDispatched(SocietyDocumentPublished::class, fn ($e) => $e->document->is($doc) && $e->channels === ['email', 'sms']);
        $this->assertNotNull($doc->announced_at);

        // Unpublish + publish again with announce ticked: not re-sent.
        $this->actingAs($this->adminA)->post(route('admin.society-documents.unpublish', $doc));
        $this->actingAs($this->adminA)->post(route('admin.society-documents.publish', $doc), ['announce' => 1, 'channels' => ['email']]);
        Event::assertDispatchedTimes(SocietyDocumentPublished::class, 1);
    }

    public function test_announce_rejects_push_or_unknown_channels(): void
    {
        $doc = $this->upload($this->adminA);
        $this->actingAs($this->adminA)->post(route('admin.society-documents.publish', $doc), ['announce' => 1, 'channels' => ['push']])
            ->assertSessionHasErrors('channels.0');
        $this->assertSame('draft', $doc->fresh()->status);
    }

    public function test_email_announcement_reaches_this_societys_residents_only(): void
    {
        Bus::fake([DispatchEventNotification::class]);

        $this->published($this->adminA, [], ['announce' => 1, 'channels' => ['email']]);

        $recipients = [];
        Bus::assertDispatched(DispatchEventNotification::class, function ($job) use (&$recipients) {
            $recipients[] = $job->recipient['email'];
            return true;
        });

        sort($recipients);
        $expected = [$this->tenantA->email, $this->ownerA->email];
        sort($expected);
        $this->assertSame($expected, $recipients, 'Only society A residents (not guard/staff/admin, not society B) are emailed for a resident-only document.');
    }

    public function test_guard_visible_announcement_includes_guards(): void
    {
        Bus::fake([DispatchEventNotification::class]);

        $this->published($this->adminA, ['category' => 'security', 'visible_to_guards' => 1], ['announce' => 1, 'channels' => ['email']]);

        Bus::assertDispatched(DispatchEventNotification::class, fn ($job) => $job->recipient['email'] === $this->guardA->email);
        Bus::assertNotDispatched(DispatchEventNotification::class, fn ($job) => $job->recipient['email'] === $this->staffA->email);
    }

    // ── Acknowledgement ─────────────────────────────────────────────────────

    public function test_resident_acknowledgement_is_recorded_once_with_version(): void
    {
        $doc = $this->published($this->adminA, ['requires_acknowledgement' => 1]);

        $this->actingAs($this->tenantA)->post(route('society-rules.acknowledge', $doc->id))->assertRedirect();
        $this->actingAs($this->tenantA)->post(route('society-rules.acknowledge', $doc->id))->assertRedirect();

        $this->assertDatabaseCount('society_document_acknowledgements', 1);
        $this->assertDatabaseHas('society_document_acknowledgements', [
            'society_document_id' => $doc->id, 'user_id' => $this->tenantA->id, 'version' => 'v1',
        ]);

        $this->actingAs($this->adminA)->get(route('admin.society-documents.show', $doc))
            ->assertOk()->assertSee('1 of 2 residents')->assertSee($this->tenantA->name);
    }

    public function test_acknowledgement_is_refused_when_not_requested(): void
    {
        $doc = $this->published($this->adminA);
        $this->actingAs($this->tenantA)->post(route('society-rules.acknowledge', $doc->id))->assertSessionHas('error');
        $this->assertDatabaseCount('society_document_acknowledgements', 0);
    }

    // ── API ─────────────────────────────────────────────────────────────────

    public function test_api_lists_shows_streams_and_acknowledges(): void
    {
        $doc = $this->published($this->adminA, ['requires_acknowledgement' => 1, 'effective_from' => '2026-11-01', 'description' => 'Two cars per villa']);
        Sanctum::actingAs($this->ownerA);

        $this->getJson('/api/v1/society-documents')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $doc->id)
            ->assertJsonPath('data.0.title', 'Parking Rules')
            ->assertJsonPath('data.0.category_label', 'Parking')
            ->assertJsonPath('data.0.version', 'v1')
            ->assertJsonPath('data.0.effective_from', '2026-11-01')
            ->assertJsonPath('data.0.requires_acknowledgement', true)
            ->assertJsonPath('data.0.acknowledged', false)
            ->assertJsonPath('data.0.file_url', "/society-documents/{$doc->id}/file");

        $file = $this->get("/api/v1/society-documents/{$doc->id}/file");
        $file->assertOk();
        $this->assertSame('application/pdf', $file->headers->get('Content-Type'));

        $this->postJson("/api/v1/society-documents/{$doc->id}/acknowledge")->assertOk()->assertJsonPath('data.acknowledged', true);
        $this->getJson("/api/v1/society-documents/{$doc->id}")->assertOk()->assertJsonPath('data.acknowledged', true);
    }

    public function test_api_requires_authentication(): void
    {
        $doc = $this->published($this->adminA);
        auth()->forgetGuards();
        $this->getJson('/api/v1/society-documents')->assertUnauthorized();
        $this->getJson("/api/v1/society-documents/{$doc->id}/file")->assertUnauthorized();
    }

    // ── Pages render with data ──────────────────────────────────────────────

    public function test_every_screen_renders_with_rows_present(): void
    {
        $doc = $this->published($this->adminA, ['visible_to_guards' => 1, 'requires_acknowledgement' => 1, 'description' => 'Desc']);
        $this->upload($this->adminA, ['title' => 'Draft Bye-Laws', 'category' => 'bye_laws']);

        $this->actingAs($this->adminA)->get(route('admin.society-documents.index'))->assertOk()->assertSee('Draft Bye-Laws');
        $this->actingAs($this->adminA)->get(route('admin.society-documents.index', ['category' => 'parking', 'status' => 'published', 'q' => 'Park']))
            ->assertOk()->assertSee('Parking Rules')->assertDontSee('Draft Bye-Laws');
        $this->actingAs($this->adminA)->get(route('admin.society-documents.create'))->assertOk();
        $this->actingAs($this->adminA)->get(route('admin.society-documents.show', $doc))->assertOk();
        $this->actingAs($this->adminA)->get(route('admin.society-documents.edit', $doc))->assertOk();
        $this->actingAs($this->adminA)->get(route('admin.society-documents.new-version', $doc))->assertOk()->assertSee('v2');
        // The Settings entry was renamed from "Rules & Documents" to "Society Info"
        // (Society Info = details + committee + these documents, one label on web
        // and mobile); it still leads to the documents management screen.
        $this->actingAs($this->adminA)->get(route('settings.index'))->assertOk()
            ->assertSee('Society Info')->assertSee(route('admin.society-documents.index'), false);
        $this->actingAs($this->tenantA)->get(route('society-rules.index'))->assertOk()->assertSee('Please read');
        $this->actingAs($this->tenantA)->get(route('society-rules.index', ['q' => 'nomatch']))->assertOk()->assertSee('Nothing matches');
        $this->actingAs($this->tenantA)->get(route('society-rules.show', $doc->id))->assertOk()->assertSee('I have read this');
        $this->actingAs($this->guardA)->get(route('guard.rules.index'))->assertOk();
        $this->actingAs($this->guardA)->get(route('guard.rules.show', $doc->id))->assertOk();
    }

    public function test_admin_can_edit_metadata(): void
    {
        $doc = $this->upload($this->adminA);

        $this->actingAs($this->adminA)->put(route('admin.society-documents.update', $doc), [
            'title' => 'Parking & Vehicle Rules', 'category' => 'parking', 'version' => 'v1.1',
            'effective_from' => '2026-12-01', 'visible_to_guards' => 1,
        ])->assertRedirect();

        $doc->refresh();
        $this->assertSame('Parking & Vehicle Rules', $doc->title);
        $this->assertSame('v1.1', $doc->version);
        $this->assertTrue($doc->visible_to_guards);
        $this->assertSame('2026-12-01', $doc->effective_from->toDateString());

        $this->actingAs($this->adminA)->put(route('admin.society-documents.update', $doc), [
            'title' => 'x', 'category' => 'not-a-category', 'version' => 'v1',
        ])->assertSessionHasErrors('category');
    }
}
