<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\BillingService;
use App\Services\FirmConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RazorpayWebhookController extends Controller
{
    public function handle(Request $request, BillingService $billingService): JsonResponse
    {
        $cfg = FirmConfigService::razorpay();
        $webhookSecret = (string) ($cfg['webhook_secret'] ?? '');
        $signature = (string) $request->header('X-Razorpay-Signature', '');
        $payload = $request->getContent();

        if ($webhookSecret === '' || $signature === '') {
            return response()->json(['ok' => false, 'message' => 'Webhook secret or signature missing.'], 400);
        }

        $expected = hash_hmac('sha256', $payload, $webhookSecret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['ok' => false, 'message' => 'Invalid signature.'], 403);
        }

        $data = $request->json()->all();
        $event = $data['event'] ?? '';

        if (in_array($event, ['payment_link.paid', 'payment.captured'], true)) {
            $paymentLinkEntity = $data['payload']['payment_link']['entity'] ?? null;
            $paymentEntity = $data['payload']['payment']['entity'] ?? null;

            $invoice = null;
            if (! empty($paymentLinkEntity['id'])) {
                $invoice = Invoice::where('razorpay_payment_link_id', $paymentLinkEntity['id'])->first();
            }
            if (! $invoice && ! empty($paymentLinkEntity['reference_id'])) {
                $invoice = Invoice::where('invoice_number', $paymentLinkEntity['reference_id'])->first();
            }
            if (! $invoice && ! empty($paymentEntity['notes']['invoice_id'])) {
                $invoice = Invoice::find($paymentEntity['notes']['invoice_id']);
            }

            if ($invoice && (float) $invoice->balance_amount > 0) {
                $paymentId = (string) ($paymentEntity['id'] ?? ($paymentLinkEntity['id'] ?? 'rzp_'.time()));
                $amountPaise = (float) ($paymentEntity['amount'] ?? ($paymentLinkEntity['amount_paid'] ?? 0));
                $amountInr = $amountPaise > 0 ? ($amountPaise / 100) : (float) $invoice->balance_amount;

                $billingService->recordRazorpayPayment($invoice, $paymentId, $amountInr);
            }
        }

        return response()->json(['ok' => true]);
    }
}
