@extends('layouts.portal')

@section('title', 'Invoices & Payments')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold mb-0" style="color: #1e3a5f;">Invoices &amp; Outstanding Payments</h1>
    <span class="badge badge-soft-danger p-2">Outstanding Balance: ₹{{ number_format($outstandingBalance, 2) }}</span>
</div>

<div class="ca-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Invoice #</th><th>Date</th><th>Due Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                @forelse($invoices as $inv)
                    <tr>
                        <td class="fw-semibold">{{ $inv->invoice_number }}</td>
                        <td>{{ $inv->invoice_date->format('d M Y') }}</td>
                        <td>{{ $inv->due_date->format('d M Y') }}</td>
                        <td>₹{{ number_format((float) $inv->total_amount, 2) }}</td>
                        <td class="text-success">₹{{ number_format((float) $inv->paid_amount, 2) }}</td>
                        <td class="fw-semibold text-danger">₹{{ number_format((float) $inv->balance_amount, 2) }}</td>
                        <td><x-status-badge :status="$inv->status" :label="$inv->statusLabel()" /></td>
                        <td class="text-end text-nowrap">
                            @if((float) $inv->balance_amount > 0)
                                @if($inv->razorpay_payment_link_url)
                                    <a href="{{ $inv->razorpay_payment_link_url }}" target="_blank" class="btn btn-sm btn-success me-1">
                                        <i class="bi bi-lightning-charge me-1"></i>Pay Now
                                    </a>
                                    <form action="{{ route('portal.invoices.verify', $inv) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary me-1" title="Verify Payment Status">
                                            <i class="bi bi-arrow-repeat"></i> Verify
                                        </button>
                                    </form>
                                @elseif(!empty($razorpayEnabled))
                                    <form action="{{ route('portal.invoices.pay', $inv) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success me-1">
                                            <i class="bi bi-lightning-charge me-1"></i>Pay Now
                                        </button>
                                    </form>
                                @endif
                            @endif
                            <a href="{{ route('portal.invoices.pdf', $inv) }}" class="btn btn-sm btn-light border"><i class="bi bi-file-pdf text-danger me-1"></i>PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted text-center py-4">No invoices available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($invoices->hasPages())<div class="p-3 border-top">{{ $invoices->links() }}</div>@endif
</div>
@endsection

