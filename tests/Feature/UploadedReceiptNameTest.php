<?php

namespace Tests\Feature;

use App\Models\MaintenanceBill;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\SafeUploadName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A rent payment proof is stored where the web server can serve it. Its extension used to be the one the sender typed, while the
 * validation rule judged the content - so a real image carrying code, sent as "proof.php", was saved as a .php file.
 */
class UploadedReceiptNameTest extends TestCase
{
    use RefreshDatabase;

    /** A real 1x1 GIF-free JPEG header + padding: enough for the content sniffer, followed by text that is not an image. */
    private function jpegNamed(string $clientName, string $trailer = '<?php echo "ran"; ?>'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, hex2bin('ffd8ffe000104a46494600010100000100010000ffdb004300') . str_repeat("\x08", 64) . hex2bin('ffd9') . $trailer);

        return new UploadedFile($path, $clientName, 'image/jpeg', null, true);
    }

    private function pdfNamed(string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");

        return new UploadedFile($path, $clientName, 'application/pdf', null, true);
    }

    public function test_the_extension_comes_from_the_content_not_from_the_name_the_sender_gave(): void
    {
        $this->assertSame('jpg', SafeUploadName::extension($this->jpegNamed('proof.php')));
        $this->assertSame('jpg', SafeUploadName::extension($this->jpegNamed('proof.phtml')));
        $this->assertSame('jpg', SafeUploadName::extension($this->jpegNamed('proof.PHP.jpg')));
        $this->assertSame('pdf', SafeUploadName::extension($this->pdfNamed('proof.php')));
    }

    public function test_content_that_is_not_an_allowed_type_is_refused(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, '<?php echo 1; ?>');
        $script = new UploadedFile($path, 'proof.jpg', 'image/jpeg', null, true);

        $this->expectException(ValidationException::class);
        SafeUploadName::extension($script, ['jpg', 'png', 'pdf'], 'payment_proof');
    }

    public function test_the_stored_name_is_random_and_ends_in_the_content_type(): void
    {
        $a = SafeUploadName::make($this->jpegNamed('proof.php'), 'rent_payment', 7);
        $b = SafeUploadName::make($this->jpegNamed('proof.php'), 'rent_payment', 7);

        $this->assertMatchesRegularExpression('/^rent_payment_7_\d+_[0-9a-f]{16}\.jpg$/', $a);
        $this->assertNotSame($a, $b, 'a receipt cannot be found by counting');
    }

    public function test_a_tenants_payment_proof_with_an_odd_script_extension_is_stored_as_an_image(): void
    {
        // Laravel's own `mimes` rule already refuses .php / .phtml / .phar; names like .pht still got through to the stored file name
        Storage::fake('public');
        $society = $this->makeSociety('Alpha');
        $building = $this->makeBuilding($society);
        $flat = $this->makeFlat($society, $building, '101');
        $user = $this->makeUserWithRole($society, 'Tenant');
        Tenant::create(['society_id' => $society->id, 'flat_id' => $flat->id, 'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => '9000000101', 'move_in_date' => now(), 'status' => 'active']);
        $bill = MaintenanceBill::create(['society_id' => $society->id, 'flat_id' => $flat->id, 'month' => 'September', 'year' => 2026, 'bill_date' => '2026-09-01', 'due_date' => '2026-09-10', 'maintenance_amount' => 10000, 'paid_amount' => 0, 'bill_type' => 'rent']);

        $send = fn (string $name) => $this->actingAs($user)->post(route('tenant.rent.payment'), [
            'rent_id' => $bill->id, 'payment_date' => '2026-09-05', 'payment_amount' => 10000, 'payment_method' => 'upi',
            'payment_proof' => $this->jpegNamed($name),
        ]);

        $send('proof.pht')->assertOk()->assertJsonPath('success', true);

        $stored = Payment::where('maintenance_bill_id', $bill->id)->value('receipt_file_path');
        $this->assertNotNull($stored);
        $this->assertStringEndsWith('.jpg', $stored);
        $this->assertStringNotContainsString('.pht', $stored);
        Storage::disk('public')->assertExists($stored);

        // and the framework's own refusal of a plain .php name still holds
        $send('proof.php')->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame(1, Payment::where('maintenance_bill_id', $bill->id)->count());
    }
}
