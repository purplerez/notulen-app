<?php

namespace App\Policies;

use App\Models\QuotaOrder;
use App\Models\User;

class QuotaOrderPolicy
{
    /**
     * Determine whether the user can view the quota orders list.
     * Admin and Pimpinan can view.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isPimpinan();
    }

    /**
     * Determine whether the user can view the specific quota order.
     */
    public function view(User $user, QuotaOrder $order): bool
    {
        if ((int) $user->organization_id !== (int) $order->organization_id) {
            return false;
        }

        return $user->isAdmin() || $user->isPimpinan();
    }

    /**
     * Determine whether the user can create a quota purchase order.
     * Only Admin can purchase quota.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can upload payment proof.
     */
    public function uploadProof(User $user, QuotaOrder $order): bool
    {
        if ((int) $user->organization_id !== (int) $order->organization_id) {
            return false;
        }

        return $user->isAdmin() && in_array($order->status, ['pending', 'waiting_verification'], true);
    }

    /**
     * Determine whether the user can cancel the quota order.
     */
    public function cancel(User $user, QuotaOrder $order): bool
    {
        if ((int) $user->organization_id !== (int) $order->organization_id) {
            return false;
        }

        return $user->isAdmin() && $order->isPending();
    }

    /**
     * Determine whether the user can simulate payment (sandbox).
     */
    public function simulate(User $user, QuotaOrder $order): bool
    {
        if ((int) $user->organization_id !== (int) $order->organization_id) {
            return false;
        }

        return $user->isAdmin() && in_array($order->status, ['pending', 'waiting_verification'], true);
    }
}

