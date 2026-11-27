<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ClientAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientAccessService $clientAccess,
        private readonly ActivityLogger $logger
    ) {}

    public function index(Request $request): View
    {
        $query = $this->clientAccess->visibleClientsQuery($request->user())
            ->with('assignedCa');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('client_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('pan', 'like', "%{$search}%")
                    ->orWhere('gstin', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($assignedCa = $request->query('assigned_ca_id')) {
            $query->where('assigned_ca_id', $assignedCa);
        }

        $clients = $query->orderBy('name')->paginate(15)->withQueryString();
        $staff = User::staff()->active()->orderBy('name')->get();

        return view('clients.index', compact('clients', 'staff'));
    }

    public function create(): View
    {
        $staff = User::staff()->active()->orderBy('name')->get();
        $nextCode = $this->generateClientCode();

        return view('clients.create', compact('staff', 'nextCode'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateClient($request);

        $client = DB::transaction(function () use ($validated, $request) {
            $portalUserId = null;
            if ($request->boolean('create_portal_login') && ! empty($validated['email'])) {
                $portalPassword = $request->input('portal_password') ?: 'Client@123';
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($portalPassword),
                    'role' => User::ROLE_CLIENT,
                    'phone' => $validated['mobile'] ?? null,
                    'is_active' => true,
                    'avatar_color' => '#0d9488',
                ]);
                $portalUserId = $user->id;
            }

            $client = Client::create([
                ...$validated,
                'user_id' => $portalUserId,
                'client_code' => ! empty($validated['client_code']) ? $validated['client_code'] : $this->generateClientCode(),
                'is_gst_registered' => $request->boolean('is_gst_registered'),
                'is_itr_applicable' => $request->boolean('is_itr_applicable'),
                'is_tds_applicable' => $request->boolean('is_tds_applicable'),
                'is_recurring_retainer' => $request->boolean('is_recurring_retainer'),
                'retainer_amount' => $validated['retainer_amount'] ?? 0,
                'billing_cycle' => $validated['billing_cycle'] ?? 'none',
            ]);

            if (! empty($request->input('staff_ids'))) {
                $client->staff()->sync($request->input('staff_ids'));
            }

            return $client;
        });

        $this->logger->log('clients', 'created', "Added client {$client->name} ({$client->client_code})", $client);

        return redirect()->route('clients.show', $client)->with('success', 'Client profile created successfully.');
    }

    public function show(Request $request, Client $client): View
    {
        abort_unless($this->clientAccess->canAccessClient($request->user(), $client), 403);

        $tab = $request->query('tab', 'overview');
        $client->load([
            'assignedCa',
            'staff',
            'user',
            'gstReturns.assignee',
            'itrFilings.assignee',
            'tdsReturns.assignee',
            'deadlines.assignee',
            'documents.uploader',
            'documents.versions',
            'tasks.assignee',
            'invoices.payments',
            'audits.leadAuditor',
            'communications.user',
        ]);

        $staff = User::staff()->active()->orderBy('name')->get();

        return view('clients.show', compact('client', 'tab', 'staff'));
    }

    public function edit(Request $request, Client $client): View
    {
        abort_unless($this->clientAccess->canAccessClient($request->user(), $client), 403);
        $staff = User::staff()->active()->orderBy('name')->get();
        $client->load('staff');

        return view('clients.edit', compact('client', 'staff'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        abort_unless($this->clientAccess->canAccessClient($request->user(), $client), 403);
        $validated = $this->validateClient($request, $client);

        DB::transaction(function () use ($client, $validated, $request) {
            $client->update([
                ...$validated,
                'is_gst_registered' => $request->boolean('is_gst_registered'),
                'is_itr_applicable' => $request->boolean('is_itr_applicable'),
                'is_tds_applicable' => $request->boolean('is_tds_applicable'),
                'is_recurring_retainer' => $request->boolean('is_recurring_retainer'),
                'retainer_amount' => $validated['retainer_amount'] ?? 0,
                'billing_cycle' => $validated['billing_cycle'] ?? 'none',
            ]);

            $client->staff()->sync($request->input('staff_ids', []));

            if ($request->boolean('create_portal_login') && ! $client->user_id && ! empty($validated['email'])) {
                $existingUser = User::where('email', $validated['email'])->first();
                if (! $existingUser) {
                    $user = User::create([
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'password' => Hash::make($request->input('portal_password') ?: 'Client@123'),
                        'role' => User::ROLE_CLIENT,
                        'phone' => $validated['mobile'] ?? null,
                        'is_active' => true,
                        'avatar_color' => '#0d9488',
                    ]);
                    $client->update(['user_id' => $user->id]);
                }
            }
        });

        $this->logger->log('clients', 'updated', "Updated client {$client->name}", $client);

        return redirect()->route('clients.show', $client)->with('success', 'Client profile updated successfully.');
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        abort_unless($request->user()->isManagerOrAbove(), 403);
        $name = $client->name;
        $client->delete();

        $this->logger->log('clients', 'deleted', "Archived client {$name}");

        return redirect()->route('clients.index')->with('success', "Client {$name} archived.");
    }

    public function downloadImportTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Name', 'Code', 'Category', 'Email', 'Mobile', 'PAN', 'GSTIN', 'TAN', 'CIN',
                'Assigned CA', 'GST', 'ITR', 'TDS', 'Is Recurring Retainer', 'Retainer Amount', 'Billing Cycle',
            ]);
            fputcsv($handle, [
                'Apex Precision Components Pvt Ltd', '', 'business', 'accounts@apexpc.in', '+91 9820012345',
                'AABCA1234E', '27AABCA1234E1Z5', 'MUMM12345A', 'U29100MH2020PTC123456',
                'admin@uptoware.com', '1', '1', '1', '1', '15000', 'monthly',
            ]);
            fputcsv($handle, [
                'Rohan Sharma', '', 'individual', 'rohan.sharma@example.com', '+91 9811122334',
                'BGPPS4321K', '', '', '', '', '0', '1', '0', '0', '0', 'none',
            ]);
            fclose($handle);
        }, 'clients_import_template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path = $request->file('csv_file')->getRealPath();
        $handle = fopen($path, 'r');
        if (! $handle) {
            return back()->withErrors(['csv_file' => 'Unable to read uploaded CSV file.']);
        }

        $headerRow = fgetcsv($handle);
        if (! $headerRow) {
            fclose($handle);

            return back()->withErrors(['csv_file' => 'CSV file is empty.']);
        }

        // Strip UTF-8 BOM from first header cell if present
        $headerRow[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headerRow[0]);
        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow);

        $staffList = User::staff()->get();
        $imported = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $idx => $key) {
                $assoc[$key] = trim((string) ($row[$idx] ?? ''));
            }

            $name = $assoc['name'] ?? '';
            if ($name === '') {
                $errors[] = "Row {$rowNumber}: Name is required.";

                continue;
            }

            $category = strtolower($assoc['category'] ?? 'business');
            if (! array_key_exists($category, Client::CATEGORIES)) {
                $category = 'business';
            }

            $code = $assoc['code'] ?? '';
            if ($code !== '' && Client::withTrashed()->where('client_code', $code)->exists()) {
                $errors[] = "Row {$rowNumber} ({$name}): Client code '{$code}' already exists.";

                continue;
            }

            $assignedCaId = null;
            $caRef = $assoc['assigned ca'] ?? ($assoc['assigned_ca'] ?? '');
            if ($caRef !== '') {
                $matched = $staffList->first(fn ($u) => strcasecmp($u->email, $caRef) === 0 || strcasecmp($u->name, $caRef) === 0);
                $assignedCaId = $matched?->id;
            }

            $toBool = fn (string $val, bool $default = false): bool => $val === ''
                ? $default
                : in_array(strtolower($val), ['1', 'true', 'yes', 'y'], true);

            $billingCycle = strtolower($assoc['billing cycle'] ?? ($assoc['billing_cycle'] ?? 'none'));
            if (! array_key_exists($billingCycle, Client::BILLING_CYCLES)) {
                $billingCycle = 'none';
            }

            Client::create([
                'client_code' => $code !== '' ? $code : $this->generateClientCode(),
                'name' => $name,
                'category' => $category,
                'email' => ($assoc['email'] ?? '') !== '' ? $assoc['email'] : null,
                'mobile' => ($assoc['mobile'] ?? '') !== '' ? $assoc['mobile'] : null,
                'pan' => ($assoc['pan'] ?? '') !== '' ? strtoupper($assoc['pan']) : null,
                'gstin' => ($assoc['gstin'] ?? '') !== '' ? strtoupper($assoc['gstin']) : null,
                'tan' => ($assoc['tan'] ?? '') !== '' ? strtoupper($assoc['tan']) : null,
                'cin' => ($assoc['cin'] ?? '') !== '' ? strtoupper($assoc['cin']) : null,
                'assigned_ca_id' => $assignedCaId,
                'status' => 'active',
                'is_gst_registered' => $toBool($assoc['gst'] ?? '', false),
                'is_itr_applicable' => $toBool($assoc['itr'] ?? '', true),
                'is_tds_applicable' => $toBool($assoc['tds'] ?? '', false),
                'is_recurring_retainer' => $toBool($assoc['is recurring retainer'] ?? ($assoc['is_recurring_retainer'] ?? ''), false),
                'retainer_amount' => is_numeric($assoc['retainer amount'] ?? ($assoc['retainer_amount'] ?? '')) ? (float) ($assoc['retainer amount'] ?? $assoc['retainer_amount']) : 0,
                'billing_cycle' => $billingCycle,
            ]);

            $imported++;
        }

        fclose($handle);

        $this->logger->log('clients', 'bulk_import', "Imported {$imported} clients via CSV ({$rowNumber} rows scanned).");

        return back()->with('import_summary', [
            'imported' => $imported,
            'errors' => $errors,
        ])->with('success', "CSV import completed: {$imported} client(s) added.");
    }

    private function validateClient(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'client_code' => ['nullable', 'string', 'max:30', Rule::unique('clients', 'client_code')->ignore($client?->id)],
            'category' => ['required', Rule::in(array_keys(Client::CATEGORIES))],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                $request->boolean('create_portal_login') && ! $client?->user_id
                    ? Rule::unique('users', 'email')
                    : 'nullable',
            ],
            'mobile' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:15'],
            'pan' => ['nullable', 'string', 'max:20'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'tan' => ['nullable', 'string', 'max:20'],
            'cin' => ['nullable', 'string', 'max:30'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'industry' => ['nullable', 'string', 'max:100'],
            'assigned_ca_id' => ['nullable', 'exists:users,id'],
            'registration_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(Client::STATUSES))],
            'is_recurring_retainer' => ['nullable', 'boolean'],
            'retainer_amount' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle' => ['nullable', Rule::in(array_keys(Client::BILLING_CYCLES))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function generateClientCode(): string
    {
        $count = Client::withTrashed()->count() + 1;

        return sprintf('CA-CL-%04d', $count);
    }
}
