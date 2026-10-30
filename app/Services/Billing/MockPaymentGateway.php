<?php

namespace App\Services\Billing;

use App\Contracts\PaymentGatewayInterface;
use App\Models\QuotaOrder;

class MockPaymentGateway implements PaymentGatewayInterface
{
    /**
     * Create a payment charge or transaction for the quota order.
     */
    public function createCharge(QuotaOrder $order): array
    {
        return [
            'reference' => 'MOCK-'.$order->order_number,
            'payment_url' => url("/quota/orders/{$order->id}?mock_pay=1"),
            'qr_code_url' => null,
            'instructions' => 'Gunakan tombol simulasi pembayaran untuk menguji transaksi seketika.',
        ];
    }

    /**
     * Check or verify the payment status of an order.
     */
    public function verifyPayment(QuotaOrder $order): array
    {
        return [
            'status' => $order->isPaid() ? 'paid' : 'pending',
            'paid_at' => $order->paid_at?->toIso8601String(),
            'transaction_id' => 'TX-'.$order->order_number,
        ];
    }
}
