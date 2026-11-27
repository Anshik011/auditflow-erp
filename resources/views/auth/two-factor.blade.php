<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Verification — Uptoware Audit</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">
<div class="ca-card p-4 shadow-sm" style="max-width: 420px; width: 100%;">
    <div class="text-center mb-4">
        <x-ca-logo :size="40" />
        <h1 class="h5 fw-bold mt-3 mb-1" style="color: #1e3a5f;">Email-OTP Verification</h1>
        <p class="small text-muted mb-0">Enter the 6-digit verification code sent to your email address.</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success small py-2">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger small py-2">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('login.2fa.verify') }}" method="POST" class="mb-3">
        @csrf
        <div class="mb-3">
            <label class="form-label small fw-semibold">6-Digit OTP Code</label>
            <input type="text" name="otp" maxlength="6" inputmode="numeric" pattern="\d{6}" class="form-control text-center fs-5 letter-spacing-2 font-monospace" placeholder="000000" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary w-100">Verify &amp; Sign In</button>
    </form>

    <div class="d-flex justify-content-between align-items-center small">
        <form action="{{ route('login.2fa.resend') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">Resend OTP</button>
        </form>
        <a href="{{ route('login') }}" class="text-muted text-decoration-none">Back to Login</a>
    </div>
</div>
</body>
</html>
