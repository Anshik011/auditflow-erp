@extends('layouts.app')

@section('title', 'Firm Settings & Audit Logs')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1" style="color: #1e3a5f;">Firm Settings, Integrations &amp; System Control</h1>
        <p class="text-muted small mb-0">Configure CA firm profile, WhatsApp Cloud API, SMTP email, Razorpay payments, 2FA, web cron, and pure-PHP backups without editing <code>.env</code>.</p>
    </div>
    <div class="d-flex gap-2">
        <form action="{{ route('settings.cron_run') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-play-circle me-1"></i>Run Scheduled Jobs Now
            </button>
        </form>
        <form action="{{ route('settings.backup') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-sm btn-success">
                <i class="bi bi-cloud-arrow-down me-1"></i>Download Full Backup
            </button>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <form action="{{ route('settings.update') }}" method="POST" class="ca-card p-3 mb-3">
            @csrf
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-building me-1 text-primary"></i>CA Firm &amp; Billing Profile</h2>
            <div class="mb-2"><label class="form-label small fw-semibold">Firm Name *</label><input type="text" name="firm_name" value="{{ $settings['firm_name'] ?? 'Uptoware Audit & Advisory' }}" class="form-control form-control-sm" required></div>
            <div class="mb-2"><label class="form-label small fw-semibold">Tagline</label><input type="text" name="firm_tagline" value="{{ $settings['firm_tagline'] ?? 'Chartered Accountants, Tax & Practice Management' }}" class="form-control form-control-sm"></div>
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label small fw-semibold">Email</label><input type="email" name="firm_email" value="{{ $settings['firm_email'] ?? 'office@uptoware.com' }}" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label small fw-semibold">Phone</label><input type="text" name="firm_phone" value="{{ $settings['firm_phone'] ?? '+91 22 4001 9900' }}" class="form-control form-control-sm"></div>
            </div>
            <div class="mb-2"><label class="form-label small fw-semibold">Office Address</label><textarea name="firm_address" rows="2" class="form-control form-control-sm">{{ $settings['firm_address'] ?? 'Nariman Point, Mumbai, Maharashtra 400021' }}</textarea></div>
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label small fw-semibold">Firm PAN</label><input type="text" name="firm_pan" value="{{ $settings['firm_pan'] ?? 'AABFC1234D' }}" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label small fw-semibold">Firm GSTIN</label><input type="text" name="firm_gstin" value="{{ $settings['firm_gstin'] ?? '27AABFC1234D1Z5' }}" class="form-control form-control-sm"></div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-3"><label class="form-label small fw-semibold">Inv Prefix</label><input type="text" name="invoice_prefix" value="{{ $settings['invoice_prefix'] ?? 'CA-INV' }}" class="form-control form-control-sm" required></div>
                <div class="col-3"><label class="form-label small fw-semibold">CGST %</label><input type="number" step="0.01" name="default_cgst_rate" value="{{ $settings['default_cgst_rate'] ?? '9' }}" class="form-control form-control-sm" required></div>
                <div class="col-3"><label class="form-label small fw-semibold">SGST %</label><input type="number" step="0.01" name="default_sgst_rate" value="{{ $settings['default_sgst_rate'] ?? '9' }}" class="form-control form-control-sm" required></div>
                <div class="col-3"><label class="form-label small fw-semibold">IGST %</label><input type="number" step="0.01" name="default_igst_rate" value="{{ $settings['default_igst_rate'] ?? '18' }}" class="form-control form-control-sm" required></div>
            </div>

            <hr class="my-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0"><i class="bi bi-whatsapp me-1 text-success"></i>Meta WhatsApp Cloud API</h2>
                @if($whatsAppConfigured)
                    <span class="badge badge-soft-success">Connected</span>
                @else
                    <span class="badge badge-soft-warning">Not Configured</span>
                @endif
            </div>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="whatsapp_enabled" value="1" id="waEnabled" @checked(($settings['whatsapp_enabled'] ?? '0') === '1')>
                <label class="form-check-label small" for="waEnabled">Enable WhatsApp Cloud API Dispatch</label>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Phone Number ID</label>
                    <input type="text" name="whatsapp_phone_number_id" value="{{ $settings['whatsapp_phone_number_id'] ?? '' }}" class="form-control form-control-sm" placeholder="e.g. 123456789012345">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Access Token (Encrypted)</label>
                    <input type="password" name="whatsapp_access_token" class="form-control form-control-sm" placeholder="{{ !empty($settings['whatsapp_access_token']) ? '•••••••• (Saved)' : 'Paste permanent token' }}">
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-4">
                    <label class="form-label small fw-semibold">Compliance Template</label>
                    <input type="text" name="whatsapp_template_compliance" value="{{ $settings['whatsapp_template_compliance'] ?? 'ca_compliance_reminder' }}" class="form-control form-control-sm" placeholder="ca_compliance_reminder">
                </div>
                <div class="col-4">
                    <label class="form-label small fw-semibold">Document Template</label>
                    <input type="text" name="whatsapp_template_document" value="{{ $settings['whatsapp_template_document'] ?? 'ca_document_reminder' }}" class="form-control form-control-sm" placeholder="ca_document_reminder">
                </div>
                <div class="col-3">
                    <label class="form-label small fw-semibold">Invoice Template</label>
                    <input type="text" name="whatsapp_template_invoice" value="{{ $settings['whatsapp_template_invoice'] ?? 'ca_invoice_reminder' }}" class="form-control form-control-sm" placeholder="ca_invoice_reminder">
                </div>
                <div class="col-1">
                    <label class="form-label small fw-semibold">Lang</label>
                    <input type="text" name="whatsapp_template_language" value="{{ $settings['whatsapp_template_language'] ?? 'en' }}" class="form-control form-control-sm" placeholder="en">
                </div>
            </div>

            <hr class="my-3">
            <h2 class="h6 fw-bold mb-2"><i class="bi bi-envelope-at me-1 text-info"></i>SMTP Mail Settings</h2>
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label small fw-semibold">SMTP Host</label><input type="text" name="mail_host" value="{{ $settings['mail_host'] ?? '' }}" class="form-control form-control-sm" placeholder="smtp.hostinger.com"></div>
                <div class="col-3"><label class="form-label small fw-semibold">Port</label><input type="number" name="mail_port" value="{{ $settings['mail_port'] ?? '587' }}" class="form-control form-control-sm"></div>
                <div class="col-3">
                    <label class="form-label small fw-semibold">Encryption</label>
                    <select name="mail_encryption" class="form-select form-select-sm">
                        @foreach(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $encVal => $encLabel)
                            <option value="{{ $encVal }}" @selected(($settings['mail_encryption'] ?? 'tls') === $encVal)>{{ $encLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label small fw-semibold">SMTP Username</label><input type="text" name="mail_username" value="{{ $settings['mail_username'] ?? '' }}" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label small fw-semibold">SMTP Password (Encrypted)</label><input type="password" name="mail_password" class="form-control form-control-sm" placeholder="{{ !empty($settings['mail_password']) ? '•••••••• (Saved)' : 'SMTP password' }}"></div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6"><label class="form-label small fw-semibold">From Address</label><input type="email" name="mail_from_address" value="{{ $settings['mail_from_address'] ?? '' }}" class="form-control form-control-sm" placeholder="noreply@firm.in"></div>
                <div class="col-6"><label class="form-label small fw-semibold">From Name</label><input type="text" name="mail_from_name" value="{{ $settings['mail_from_name'] ?? '' }}" class="form-control form-control-sm" placeholder="Uptoware Audit"></div>
            </div>

            <hr class="my-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0"><i class="bi bi-credit-card me-1 text-primary"></i>Razorpay Payment Links</h2>
                @if($razorpayConfigured)
                    <span class="badge badge-soft-success">Active</span>
                @else
                    <span class="badge badge-soft-secondary">Inactive</span>
                @endif
            </div>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="razorpay_enabled" value="1" id="rzpEnabled" @checked(($settings['razorpay_enabled'] ?? '0') === '1')>
                <label class="form-check-label small" for="rzpEnabled">Enable Online UPI / Razorpay Payment Links</label>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-4"><label class="form-label small fw-semibold">Key ID</label><input type="text" name="razorpay_key_id" value="{{ $settings['razorpay_key_id'] ?? '' }}" class="form-control form-control-sm" placeholder="rzp_live_..."></div>
                <div class="col-4"><label class="form-label small fw-semibold">Key Secret</label><input type="password" name="razorpay_key_secret" class="form-control form-control-sm" placeholder="{{ !empty($settings['razorpay_key_secret']) ? '••••••••' : 'Key Secret' }}"></div>
                <div class="col-4"><label class="form-label small fw-semibold">Webhook Secret</label><input type="password" name="razorpay_webhook_secret" class="form-control form-control-sm" placeholder="{{ !empty($settings['razorpay_webhook_secret']) ? '••••••••' : 'Webhook Secret' }}"></div>
            </div>

            <hr class="my-3">
            <h2 class="h6 fw-bold mb-2"><i class="bi bi-shield-lock me-1 text-danger"></i>Security &amp; Shared-Hosting Cron</h2>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="enforce_2fa" value="1" id="enforce2fa" @checked(($settings['enforce_2fa'] ?? '0') === '1')>
                <label class="form-check-label small" for="enforce2fa">Enforce Email-OTP 2FA for All Staff &amp; Portal Logins</label>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Web Cron Secret Token</label>
                <input type="text" name="cron_secret_token" value="{{ $settings['cron_secret_token'] ?? '' }}" class="form-control form-control-sm font-monospace">
                <div class="form-text small">
                    cPanel / Hostinger URL Cron: <code>{{ url('/cron/run') }}?token={{ $settings['cron_secret_token'] ?? '' }}</code>
                </div>
            </div>

            <button type="submit" class="btn btn-sm btn-primary w-100">Save All Settings</button>
        </form>
    </div>

    <div class="col-lg-6">
        <!-- Integration Diagnostics -->
        <div class="ca-card p-3 mb-3">
            <h2 class="h6 fw-bold mb-3">Integration Diagnostics &amp; Live Tests</h2>
            <div class="row g-2">
                <div class="col-md-12">
                    <form action="{{ route('settings.test_whatsapp') }}" method="POST">
                        @csrf
                        <div class="d-flex gap-2 mb-1">
                            <input type="text" name="test_phone" class="form-control form-control-sm" placeholder="+91 98200 11001" required>
                            <button type="submit" class="btn btn-sm btn-outline-success text-nowrap"><i class="bi bi-whatsapp me-1"></i>Test WhatsApp</button>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="use_hello_world" value="1" id="useHelloWorld" checked>
                            <label class="form-check-label small text-muted" for="useHelloWorld">Use <code>hello_world</code> (<code>en_US</code>) template (recommended outside 24h window)</label>
                        </div>
                    </form>
                </div>
                <div class="col-md-12">
                    <form action="{{ route('settings.test_email') }}" method="POST" class="d-flex gap-2">
                        @csrf
                        <input type="email" name="test_email" value="{{ auth()->user()->email }}" class="form-control form-control-sm" placeholder="test@example.com" required>
                        <button type="submit" class="btn btn-sm btn-outline-info text-nowrap"><i class="bi bi-envelope me-1"></i>Test Email</button>
                    </form>
                </div>
                <div class="col-md-12">
                    <form action="{{ route('settings.test_razorpay') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-credit-card me-1"></i>Test Razorpay API Connection</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Local Backups -->
        <div class="ca-card p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0">Pure-PHP Local Backups (Last 7 Retained)</h2>
                <span class="badge badge-soft-info">{{ count($backups) }} Archives</span>
            </div>
            @if(empty($backups))
                <p class="small text-muted mb-0">No local backups generated yet. Click "Download Full Backup" above to create one.</p>
            @else
                <ul class="list-group list-group-flush small">
                    @foreach($backups as $bk)
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="font-monospace">{{ $bk['name'] }}</span>
                            <span class="text-muted">{{ number_format($bk['size'] / 1024, 1) }} KB · {{ $bk['created_at'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <!-- System Audit Trail -->
        <div class="ca-card overflow-hidden">
            <div class="p-3 border-bottom"><h2 class="h6 fw-bold mb-0">System Audit Trail</h2></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Timestamp</th><th>User</th><th>Module</th><th>Description</th></tr></thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td class="small text-nowrap">{{ $log->created_at->format('d M Y, h:i A') }}</td>
                                <td class="small fw-medium">{{ $log->user?->name ?? 'System' }}</td>
                                <td><span class="badge badge-soft-info">{{ ucfirst($log->module) }}</span></td>
                                <td class="small">{{ $log->description }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No audit logs recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($logs->hasPages())<div class="p-3 border-top">{{ $logs->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
