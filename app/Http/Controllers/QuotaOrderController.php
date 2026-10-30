<?php

namespace App\Http\Controllers;

use App\Models\QuotaOrder;
use App\Services\Billing\QuotaPricingCalculator;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class QuotaOrderController extends Controller
{
    public function __construct(
        protected QuotaPricingCalculator $calculator
    ) {}

    /**
     * Display the quota purchase dashboard & order history.
     */
    public function index(): View
    {
        Gate::authorize('viewAny', QuotaOrder::class);

        $org = auth()->user()->organization;

        $orders = QuotaOrder::with(['creator', 'approver'])
            ->latest()
            ->paginate(10);

        $quickOptions = [
            $this->calculator->calculate(1),
            $this->calculator->calculate(3),
            $this->calculator->calculate(5),
            $this->calculator->calculate(10),
        ];

        return view('quota.index', [
            'organization' => $org,
            'availableMinutes' => $org?->availableQuotaMinutes() ?? 0,
            'availableHours' => $org?->availableQuotaHours() ?? 0.0,
            'usedMinutes' => $org?->usedQuotaMinutes() ?? 0,
            'totalMinutes' => (int) ($org?->quota_minutes ?? 0),
            'quickOptions' => $quickOptions,
            'orders' => $orders,
            'baseRate' => (float) config('quota.rate_per_hour_idr', 50000),
            'minHours' => (int) config('quota.min_hours', 1),
            'maxHours' => (int) config('quota.max_hours', 100),
            'gracePeriodMinutes' => (int) config('quota.grace_period_minutes', 15),
        ]);
    }

    /**
     * Store a new quota purchase order.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', QuotaOrder::class);

        $min = (int) config('quota.min_hours', 1);
        $max = (int) config('quota.max_hours', 100);

        $validated = $request->validate([
            'hours' => ['required', 'integer', "min:{$min}", "max:{$max}"],
            'payment_method' => ['nullable', 'string', 'in:manual_transfer,simulator'],
        ], [
            'hours.required' => 'Jumlah jam wajib dipilih.',
            'hours.min' => "Minimal pembelian adalah {$min} jam.",
            'hours.max' => "Maksimal pembelian adalah {$max} jam.",
        ]);

        $hours = (int) $validated['hours'];
        $quote = $this->calculator->calculate($hours);
        $org = auth()->user()->organization;

        $order = QuotaOrder::create([
            'organization_id' => $org->id,
            'order_number' => QuotaOrder::generateOrderNumber(),
            'hours' => $quote['hours'],
            'minutes' => $quote['minutes'],
            'price_per_hour' => $quote['effective_rate_per_hour'],
            'total_amount' => $quote['total_amount'],
            'status' => 'pending',
            'payment_method' => $validated['payment_method'] ?? 'manual_transfer',
            'created_by' => auth()->id(),
        ]);

        Audit::record('quota_order_create', null, [
            'order_number' => $order->order_number,
            'hours' => $order->hours,
            'minutes' => $order->minutes,
            'total_amount' => $order->total_amount,
        ]);

        return redirect()->route('quota.orders.show', $order)
            ->with('success', "Pesanan kuota #{$order->order_number} berhasil dibuat. Silakan lakukan pembayaran.");
    }

    /**
     * Show quota order details and payment instructions.
     */
    public function show(QuotaOrder $quotaOrder): View
    {
        Gate::authorize('view', $quotaOrder);

        $quotaOrder->load(['creator', 'approver', 'organization']);

        return view('quota.show', [
            'order' => $quotaOrder,
            'bankAccounts' => config('quota.bank_accounts', []),
        ]);
    }

    /**
     * Upload proof of payment for manual bank transfer.
     */
    public function uploadProof(Request $request, QuotaOrder $quotaOrder): RedirectResponse
    {
        Gate::authorize('uploadProof', $quotaOrder);

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [
            'proof.required' => 'Berkas bukti transfer wajib dipilih.',
            'proof.mimes' => 'Format bukti transfer harus berupa JPG, PNG, atau PDF.',
            'proof.max' => 'Ukuran berkas bukti transfer maksimal 5 MB.',
        ]);

        $file = $request->file('proof');
        $orgId = $quotaOrder->organization_id;
        $path = $file->store("orgs/{$orgId}/quota_proofs", 'local');

        $quotaOrder->update([
            'payment_proof_path' => $path,
            'status' => 'waiting_verification',
        ]);

        Audit::record('quota_order_proof_upload', null, [
            'order_number' => $quotaOrder->order_number,
            'path' => $path,
        ]);

        return back()->with('success', 'Bukti pembayaran berhasil diunggah. Menunggu verifikasi dari administrator platform.');
    }

    /**
     * Instant payment simulation (for testing & development sandboxes).
     */
    public function simulatePayment(QuotaOrder $quotaOrder): RedirectResponse
    {
        Gate::authorize('simulate', $quotaOrder);

        if (! in_array($quotaOrder->status, ['pending', 'waiting_verification'], true)) {
            return back()->with('error', 'Pesanan ini tidak dapat disimulasikan pembayarannya.');
        }

        DB::transaction(function () use ($quotaOrder) {
            $quotaOrder->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => 'simulator',
            ]);

            $quotaOrder->organization->increment('quota_minutes', $quotaOrder->minutes);
        });

        Audit::record('quota_order_paid', null, [
            'order_number' => $quotaOrder->order_number,
            'minutes_added' => $quotaOrder->minutes,
            'method' => 'simulator',
        ]);

        return back()->with('success', "Pembayaran simulasi berhasil! Kuota sebanyak {$quotaOrder->hours} Jam ({$quotaOrder->minutes} Menit) telah ditambahkan ke organisasi Anda.");
    }

    /**
     * Cancel a pending quota order.
     */
    public function cancel(QuotaOrder $quotaOrder): RedirectResponse
    {
        Gate::authorize('cancel', $quotaOrder);

        $quotaOrder->update(['status' => 'cancelled']);

        Audit::record('quota_order_cancel', null, [
            'order_number' => $quotaOrder->order_number,
        ]);

        return redirect()->route('quota.index')
            ->with('success', "Pesanan #{$quotaOrder->order_number} telah dibatalkan.");
    }

    /**
     * Return live JSON quota status for in-meeting recorder polling.
     */
    public function quickStatus(): JsonResponse
    {
        $org = auth()->user()?->organization;

        if (! $org) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'available_minutes' => $org->availableQuotaMinutes(),
            'available_hours' => $org->availableQuotaHours(),
            'used_minutes' => $org->usedQuotaMinutes(),
            'total_minutes' => (int) $org->quota_minutes,
            'grace_period_minutes' => (int) config('quota.grace_period_minutes', 15),
        ]);
    }
}

