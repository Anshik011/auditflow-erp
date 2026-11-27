<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uptoware Audit — CA Practice &amp; Compliance Management ERP</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top py-3">
    <div class="container">
        <a class="navbar-brand text-decoration-none" href="{{ route('home') }}">
            <x-ca-logo :size="36" />
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#landingNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="landingNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
                <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="#portal">Client Portal</a></li>
                <li class="nav-item"><a class="nav-link" href="#pricing">Pricing</a></li>
                <li class="nav-item"><a class="btn btn-outline-primary btn-sm px-3" href="{{ route('login') }}">Login</a></li>
                <li class="nav-item"><a class="btn btn-primary btn-sm px-3" href="{{ route('login') }}">Try Live Demo</a></li>
            </ul>
        </div>
    </div>
</nav>

<section class="py-5 bg-white border-bottom">
    <div class="container py-lg-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill mb-3">Built for Indian Chartered Accountants &amp; Tax Firms</span>
                <h1 class="display-5 fw-bold mb-3" style="color: #1e3a5f;">
                    Run Your Entire CA Practice from One Clean Workspace
                </h1>
                <p class="lead text-muted mb-4">
                    GST, ITR, TDS, Audit Working Papers, Automated Compliance Reminders, Retainer Billing with Razorpay UPI, 194J TDS Tracking, and an isolated Client Portal — engineered to run smoothly on standard shared hosting.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login to Workspace
                    </a>
                    <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-lg px-4">
                        <i class="bi bi-play-circle me-2"></i>Try Live Demo
                    </a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="ca-card p-4 bg-light border">
                    <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Shared-Hosting Ready Architecture</h2>
                    <ul class="list-unstyled mb-0 d-grid gap-2 small">
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Zero VPS, Redis, or Supervisor required (cPanel / Hostinger ready)</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>In-App Encrypted Settings for SMTP, WhatsApp &amp; Razorpay</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>1-Click Pure-PHP Database + Document Vault ZIP Backups</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Email-OTP Two-Factor Authentication &amp; Staff Privacy Controls</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Tally-Ready CSV Exports &amp; Client Profitability Analytics</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="features" class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="h3 fw-bold" style="color: #1e3a5f;">Everything Your Practice Needs</h2>
            <p class="text-muted">Purpose-built modules for compliance, billing, and team accountability.</p>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="ca-card p-4 h-100">
                    <i class="bi bi-receipt-cutoff fs-3 text-primary mb-2 d-block"></i>
                    <h3 class="h6 fw-bold">GST, ITR &amp; TDS Compliance</h3>
                    <p class="small text-muted mb-0">Track GSTR-1/3B, ITR-1 to ITR-7, and Form 24Q/26Q/27Q with automated recurring deadlines and WhatsApp/Email reminders.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="ca-card p-4 h-100">
                    <i class="bi bi-cash-coin fs-3 text-success mb-2 d-block"></i>
                    <h3 class="h6 fw-bold">Retainer Billing, 194J &amp; Razorpay</h3>
                    <p class="small text-muted mb-0">Auto-generate monthly/quarterly retainer invoices, collect via Razorpay UPI links, record 194J TDS deductions, and export for Tally.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="ca-card p-4 h-100">
                    <i class="bi bi-stopwatch fs-3 text-info mb-2 d-block"></i>
                    <h3 class="h6 fw-bold">Kanban Tasks &amp; Profitability</h3>
                    <p class="small text-muted mb-0">Assign work, log billable staff hours directly on tasks, and compare fees billed against hours spent per client.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="portal" class="py-5 bg-white border-top border-bottom">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <h2 class="h3 fw-bold mb-3" style="color: #1e3a5f;">Dedicated, Data-Isolated Client Portal</h2>
                <p class="text-muted mb-3">Give every business and individual client a clean self-service portal to upload requested documents, track filing statuses, download GST invoices, pay online via UPI, and message your firm.</p>
                <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm">Explore Client Portal Demo</a>
            </div>
            <div class="col-lg-6">
                <div class="row g-2 small">
                    <div class="col-6"><div class="ca-card p-3"><i class="bi bi-folder2-open text-primary me-2"></i>Document Vault</div></div>
                    <div class="col-6"><div class="ca-card p-3"><i class="bi bi-file-earmark-check text-success me-2"></i>Live Filing Status</div></div>
                    <div class="col-6"><div class="ca-card p-3"><i class="bi bi-lightning-charge text-warning me-2"></i>Instant UPI Pay Now</div></div>
                    <div class="col-6"><div class="ca-card p-3"><i class="bi bi-chat-dots text-info me-2"></i>Direct Firm Messaging</div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="pricing" class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="ca-card p-4 p-md-5 text-center border-primary">
                    <span class="badge bg-primary mb-2">All-Inclusive Firm License</span>
                    <h2 class="h3 fw-bold mb-1" style="color: #1e3a5f;">Uptoware Audit Complete</h2>
                    <div class="display-5 fw-bold text-primary my-3">₹25,000 <span class="fs-6 fw-normal text-muted">/ one-time setup</span></div>
                    <p class="small text-muted mb-4">Unlimited clients, staff logins, Client Portal, WhatsApp &amp; SMTP automation, Razorpay UPI links, Tally exports, and pure-PHP backups.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="{{ route('login') }}" class="btn btn-primary px-4">Try Live Demo</a>
                        <a href="{{ route('login') }}" class="btn btn-outline-secondary px-4">Sign In</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="py-4 bg-white border-top text-center small text-muted">
    <div class="container">
        &copy; {{ date('Y') }} Uptoware Audit. Built for Chartered Accountants &amp; Tax Practices.
    </div>
</footer>
</body>
</html>
