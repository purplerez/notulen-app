<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\QuotaOrder;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformQuotaController extends Controller
{
    /**
     * Display all tenant quota orders for the platform super admin.
     */
    public function index(Request $request): View
    {
        $query = QuotaOrder::withoutGlobalScopes()
            ->with(['organization', 'creator', 'approver'])
            ->latest();

        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhereHas('organization', function ($orgQ) use ($q) {
                        $orgQ->where('name', 'like', "%{$q}%");
                    });
            });
        }

        $orders = $query->paginate(15)->withQueryString();

        $stats = [
            'total_revenue' => (float) QuotaOrder::withoutGlobalScopes()->where('status', 'paid')->sum('total_amount'),
            'total_hours_sold' => (int) QuotaOrder::withoutGlobalScopes()->where('status', 'paid')->sum('hours'),
            'pending_verifications' => QuotaOrder::withoutGlobalScopes()->where('status', 'waiting_verification')->count(),
            'pending_orders' => QuotaOrder::withoutGlobalScopes()->where('status', 'pending')->count(),
        ];

        return view('platform.quota_orders', [
            'orders' => $orders,
            'stats' => $stats,
            'filters' => [
                'status' => $request->input('status', ''),
                'q' => $request->input('q', ''),
            ],
        ]);
    }

    /**
     * View or download payment proof safely.
     */
    public function showProof(QuotaOrder $quotaOrder): StreamedResponse|RedirectResponse
    {
        $path = $quotaOrder->payment_proof_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return back()->with('error', 'Berkas bukti pembayaran tidak ditemukan.');
        }

        return Storage::disk('local')->response($path);
    }

    /**
     * Approve payment and grant quota to the organization.
     */
    public function approve(Request $request, QuotaOrder $quotaOrder): RedirectResponse
    {
        if ($quotaOrder->isPaid()) {
            return back()->with('error', 'Pesanan ini sudah berstatus lunas sebelumnya.');
        }

        $superAdmin = auth('platform')->user();

        DB::transaction(function () use ($quotaOrder, $superAdmin) {
            $quotaOrder->update([
                'status' => 'paid',
                'paid_at' => now(),
                'approved_by' => $superAdmin?->id,
            ]);

            // Add purchased minutes to organization
            $quotaOrder->organization->increment('quota_minutes', $quotaOrder->minutes);
        });

        Audit::record('quota_order_approved', null, [
            'order_number' => $quotaOrder->order_number,
            'organization_id' => $quotaOrder->organization_id,
            'minutes_added' => $quotaOrder->minutes,
            'hours_added' => $quotaOrder->hours,
            'super_admin_id' => $superAdmin?->id,
        ]);

        return back()->with('success', "Pesanan #{$quotaOrder->order_number} berhasil diverifikasi. Kuota {$quotaOrder->hours} Jam telah ditambahkan ke {$quotaOrder->organization->name}.");
    }

    /**
     * Reject payment proof.
     */
    public function reject(Request $request, QuotaOrder $quotaOrder): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ], [
            'notes.required' => 'Alasan penolakan bukti pembayaran wajib diisi.',
        ]);

        $superAdmin = auth('platform')->user();

        $quotaOrder->update([
            'status' => 'rejected',
            'notes' => trim($validated['notes']),
            'approved_by' => $superAdmin?->id,
        ]);

        Audit::record('quota_order_rejected', null, [
            'order_number' => $quotaOrder->order_number,
            'organization_id' => $quotaOrder->organization_id,
            'reason' => $validated['notes'],
            'super_admin_id' => $superAdmin?->id,
        ]);

        return back()->with('success', "Pesanan #{$quotaOrder->order_number} telah ditolak dengan catatan.");
    }
}

