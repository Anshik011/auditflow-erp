<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ComplianceDeadline;
use App\Models\ComplianceRule;
use App\Models\FirmSetting;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use App\Services\BackupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class UptowareAutomationAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_bulk_client_csv_import_and_template_download(): void
    {
        $admin = User::where('email', 'admin@uptoware.com')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('clients.import.template'))
            ->assertOk();

        $csvContent = implode("\n", [
            'Name,Code,Category,Email,Mobile,PAN,GSTIN,TAN,CIN,Assigned CA,GST,ITR,TDS,Is Recurring Retainer,Retainer Amount,Billing Cycle',
            'CSV Demo Corp,CSV-001,business,csv@democorp.in,+91 9820099887,AABCC1234D,27AABCC1234D1Z5,,,admin@uptoware.com,1,1,1,1,12000,monthly',
            ',CSV-002,business,bad@row.in,,,,,,,,,,,,',
        ]);

        $file = UploadedFile::fake()->createWithContent('clients.csv', $csvContent);

        $this->actingAs($admin)
            ->post(route('clients.import'), ['csv_file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'client_code' => 'CSV-001',
            'name' => 'CSV Demo Corp',
            'is_recurring_retainer' => true,
            'retainer_amount' => 12000.00,
            'billing_cycle' => 'monthly',
        ]);
    }

    public function test_recurring_work_and_compliance_reminders_commands(): void
    {
        Mail::fake();

        $client = Client::create([
            'client_code' => 'REC-001',
            'name' => 'Recurring Retainer Client',
            'category' => 'business',
            'email' => 'retainer@example.com',
            'mobile' => '+919820012345',
            'status' => 'active',
            'is_gst_registered' => true,
            'is_itr_applicable' => true,
            'is_tds_applicable' => true,
            'is_recurring_retainer' => true,
            'retainer_amount' => 10000,
            'billing_cycle' => 'monthly',
        ]);

        Artisan::call('app:generate-recurring-work');

        $this->assertDatabaseHas('tasks', [
            'client_id' => $client->id,
            'category' => 'gst',
        ]);
        $this->assertDatabaseHas('tasks', [
            'client_id' => $client->id,
            'category' => 'tds',
        ]);
        $this->assertDatabaseHas('invoices', [
            'client_id' => $client->id,
        ]);

        $rule = ComplianceRule::first();
        $deadline = ComplianceDeadline::create([
            'title' => 'GSTR-3B Due Test',
            'compliance_type' => 'gst',
            'client_id' => $client->id,
            'compliance_rule_id' => $rule?->id,
            'due_date' => now()->addDays(2),
            'reminder_date' => now()->subDay(),
            'status' => 'upcoming',
            'priority' => 'high',
        ]);

        Artisan::call('app:send-compliance-reminders --limit=25');

        $deadline->refresh();
        $this->assertNotNull($deadline->last_reminded_at);
    }

    public function test_employee_cannot_access_billing_and_financial_kpis_are_hidden(): void
    {
        $employee = User::where('email', 'employee@uptoware.com')->firstOrFail();
        $manager = User::where('email', 'manager@uptoware.com')->firstOrFail();

        $this->actingAs($employee)->get(route('billing.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('billing.create'))->assertForbidden();
        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Outstanding Fees')
            ->assertDontSee('Monthly Revenue');

        $this->actingAs($manager)->get(route('billing.index'))->assertOk();
        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Outstanding Fees');
    }

    public function test_pure_php_backup_creates_zip_with_sql_dump(): void
    {
        $backupService = app(BackupService::class);
        $zipPath = $backupService->createFullBackup(7);

        $this->assertFileExists($zipPath);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertNotFalse($zip->locateName('database.sql'));
        $zip->close();

        @unlink($zipPath);
    }

    public function test_razorpay_link_webhook_verification_and_194j_tds_tally_export(): void
    {
        FirmSetting::setValue('razorpay_enabled', '1', 'razorpay');
        FirmSetting::setValue('razorpay_key_id', 'rzp_test_key', 'razorpay');
        FirmSetting::setValue('razorpay_key_secret', 'rzp_test_secret', 'razorpay');
        FirmSetting::setValue('razorpay_webhook_secret', 'whsec_test_123', 'razorpay');

        Http::fake([
            'https://api.razorpay.com/v1/payment_links' => Http::response([
                'id' => 'plink_test_123',
                'short_url' => 'https://rzp.io/i/test123',
            ], 200),
        ]);

        $admin = User::where('email', 'admin@uptoware.com')->firstOrFail();
        $invoice = Invoice::firstOrFail();
        $invoice->update(['balance_amount' => 11800, 'total_amount' => 11800, 'paid_amount' => 0, 'status' => 'unpaid']);

        $this->actingAs($admin)
            ->post(route('billing.razorpay.link', $invoice))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertEquals('plink_test_123', $invoice->razorpay_payment_link_id);
        $this->assertEquals('https://rzp.io/i/test123', $invoice->razorpay_payment_link_url);

        $payload = json_encode([
            'event' => 'payment_link.paid',
            'payload' => [
                'payment_link' => ['entity' => ['id' => 'plink_test_123', 'reference_id' => $invoice->invoice_number]],
                'payment' => ['entity' => ['id' => 'pay_rzp_999', 'amount' => 1180000]],
            ],
        ]);
        $signature = hash_hmac('sha256', $payload, 'whsec_test_123');

        $this->postJson(route('webhooks.razorpay'), json_decode($payload, true), [
            'X-Razorpay-Signature' => $signature,
        ])->assertOk()->assertJson(['ok' => true]);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0.0, (float) $invoice->balance_amount);

        // Test 194J TDS payment on a fresh invoice + Tally Export
        $invoice2 = Invoice::where('id', '!=', $invoice->id)->firstOrFail();
        $invoice2->update(['total_amount' => 11800, 'paid_amount' => 0, 'balance_amount' => 11800, 'status' => 'unpaid']);

        $this->actingAs($admin)->post(route('billing.payments.store', $invoice2), [
            'payment_date' => now()->toDateString(),
            'amount' => 10800,
            'tds_deducted' => 1000,
            'tds_section' => '194J',
            'payment_mode' => 'bank_transfer',
            'reference_number' => 'UTR-194J-01',
        ])->assertRedirect();

        $invoice2->refresh();
        $this->assertEquals('paid', $invoice2->status);

        $this->actingAs($admin)
            ->get(route('reports.export_csv', ['type' => 'tally_export']))
            ->assertOk();
    }

    public function test_timesheet_logging_profitability_report_web_cron_and_2fa(): void
    {
        $admin = User::where('email', 'admin@uptoware.com')->firstOrFail();
        $task = Task::firstOrFail();

        $this->actingAs($admin)->post(route('tasks.time_logs.store', $task), [
            'minutes' => 90,
            'logged_date' => now()->toDateString(),
            'is_billable' => '1',
            'notes' => 'GSTR reconciliation',
        ])->assertRedirect();

        $this->assertDatabaseHas('task_time_logs', [
            'task_id' => $task->id,
            'minutes' => 90,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.index', ['type' => 'client_profitability']))
            ->assertOk();

        FirmSetting::setValue('cron_secret_token', 'secret-cron-token-xyz', 'security');
        $this->get(route('cron.run', ['token' => 'wrong']))->assertForbidden();
        $this->get(route('cron.run', ['token' => 'secret-cron-token-xyz']))->assertOk()->assertJson(['ok' => true]);

        // Test Email-OTP 2FA flow
        Mail::fake();
        FirmSetting::setValue('enforce_2fa', '1', 'security');

        $this->post(route('login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('login.2fa'));

        $this->assertGuest();
    }
}
