<?php

namespace App\Contracts;

use App\Models\QuotaOrder;

interface PaymentGatewayInterface
{
    /**
     * Create a payment charge or transaction for the quota order.
     *
     * @return array{
     *     reference: string,
     *     payment_url: ?string,
     *     qr_code_url: ?string,
     *     instructions: ?string
     * }
     */
    public function createCharge(QuotaOrder $order): array;

    /**
     * Check or verify the payment status of an order.
     *
     * @return array{
     *     status: 'paid'|'pending'|'failed'|'expired',
     *     paid_at: ?string,
     *     transaction_id: ?string
     * }
     */
    public function verifyPayment(QuotaOrder $order): array;
}

