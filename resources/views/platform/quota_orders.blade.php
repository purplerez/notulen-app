@extends('platform.layouts.app')

@section('title', 'Manajemen Pesanan Kuota Tenant')

@section('content')
<div class="space-y-6" x-data="{
    rejectModalOpen: false,
    selectedOrderId: null,
    selectedOrderNumber: '',
    rejectActionUrl: '',
    openRejectModal(id, number, url) {
        this.selectedOrderId = id;
        this.selectedOrderNumber = number;
        this.rejectActionUrl = url;
        this.rejectModalOpen = true;
    }
}">
    <!-- Header Section -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-white">Manajemen Pembelian Kuota Tenant</h1>
            <p class="text-xs text-slate-400 mt-1">
                Kelola pesanan kuota rekaman rapat prabayar, verifikasi transfer bank, dan pantau pendapatan platform.
            </p>
        </div>
    </div>

    <!-- Revenue & Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xs space-y-1">
            <span class="text-xs font-medium text-slate-400">Total Pendapatan Lunas</span>
            <p class="text-2xl font-bold text-emerald-400">
                Rp {{ number_format($stats['total_revenue'], 0, ',', '.') }}
            </p>
            <p class="text-[11px] text-slate-500">Semua transaksi sukses</p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xs space-y-1">
            <span class="text-xs font-medium text-slate-400">Total Jam Kuota Terjual</span>
            <p class="text-2xl font-bold text-indigo-400">
                {{ $stats['total_hours_sold'] }} Jam
            </p>
            <p class="text-[11px] text-slate-500">Setara {{ $stats['total_hours_sold'] * 60 }} menit rekaman</p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xs space-y-1">
            <span class="text-xs font-medium text-amber-400">Menunggu Verifikasi Bukti</span>
            <p class="text-2xl font-bold text-amber-300">
                {{ $stats['pending_verifications'] }} Pesanan
            </p>
            <p class="text-[11px] text-slate-500">Perlu tindakan Super Admin</p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xs space-y-1">
            <span class="text-xs font-medium text-slate-400">Menunggu Pembayaran</span>
            <p class="text-2xl font-bold text-slate-300">
                {{ $stats['pending_orders'] }} Pesanan
            </p>
            <p class="text-[11px] text-slate-500">Belum transfer / belum upload</p>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-4 shadow-xs">
        <form method="GET" action="{{ route('platform.quota_orders.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label for="q" class="block text-xs font-semibold text-slate-400 mb-1">Cari No. Pesanan / Nama Lembaga</label>
                <input
                    type="text"
                    id="q"
                    name="q"
                    value="{{ $filters['q'] }}"
                    placeholder="Ketik kata kunci..."
                    class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-white placeholder-slate-500 shadow-xs focus:ring-1 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label for="status" class="block text-xs font-semibold text-slate-400 mb-1">Status Pesanan</label>
                <select
                    id="status"
                    name="status"
                    class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-white shadow-xs focus:ring-1 focus:ring-indigo-500"
                >
                    <option value="all">Semua Status</option>
                    <option value="waiting_verification" @selected($filters['status'] === 'waiting_verification')>Menunggu Verifikasi Bukti</option>
                    <option value="pending" @selected($filters['status'] === 'pending')>Menunggu Pembayaran</option>
                    <option value="paid" @selected($filters['status'] === 'paid')>Lunas / Selesai</option>
                    <option value="rejected" @selected($filters['status'] === 'rejected')>Ditolak</option>
                    <option value="cancelled" @selected($filters['status'] === 'cancelled')>Dibatalkan</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button
                    type="submit"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-indigo-500 transition"
                >
                    Terapkan Filter
                </button>
                @if(array_filter($filters))
                    <a
                        href="{{ route('platform.quota_orders.index') }}"
                        class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-300 hover:text-white transition"
                    >
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xs space-y-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-300">Daftar Seluruh Pesanan Kuota</h2>

        @if($orders->isEmpty())
            <p class="text-xs text-slate-500 py-8 text-center">Tidak ada pesanan kuota yang sesuai dengan filter.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 font-semibold uppercase">
                            <th class="py-3 px-3">No. Pesanan</th>
                            <th class="py-3 px-3">Lembaga / Organisasi</th>
                            <th class="py-3 px-3">Durasi</th>
                            <th class="py-3 px-3">Total Bayar</th>
                            <th class="py-3 px-3">Bukti Bayar</th>
                            <th class="py-3 px-3">Status</th>
                            <th class="py-3 px-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @foreach($orders as $order)
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-3.5 px-3 font-mono font-bold text-indigo-400">
                                    {{ $order->order_number }}
                                    <div class="text-[10px] text-slate-500 font-normal">
                                        {{ $order->created_at->format('d/m/Y H:i') }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-3">
                                    <span class="font-bold text-white block">{{ $order->organization->name }}</span>
                                    <span class="text-[11px] text-slate-400">Oleh: {{ $order->creator?->name ?? '-' }}</span>
                                </td>
                                <td class="py-3.5 px-3 text-slate-300">
                                    <strong class="text-white">{{ $order->hours }} Jam</strong> ({{ $order->minutes }} m)
                                </td>
                                <td class="py-3.5 px-3 font-bold text-emerald-400">
                                    Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                                </td>
                                <td class="py-3.5 px-3">
                                    @if($order->payment_proof_path)
                                        <a
                                            href="{{ route('platform.quota_orders.proof', $order) }}"
                                            target="_blank"
                                            class="inline-flex items-center gap-1 font-semibold text-indigo-400 hover:text-indigo-300 underline text-xs"
                                        >
                                            <span>📎 Lihat Bukti</span>
                                        </a>
                                    @else
                                        <span class="text-slate-500 text-[11px]">Belum ada bukti</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-3">
                                    @if($order->isPaid())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-800/60">
                                            LUNAS
                                        </span>
                                    @elseif($order->isWaitingVerification())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-950/80 text-amber-300 border border-amber-800/60 animate-pulse">
                                            PERLU VERIFIKASI
                                        </span>
                                    @elseif($order->isPending())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-950/80 text-blue-300 border border-blue-800/60">
                                            MENUNGGU BAYAR
                                        </span>
                                    @elseif($order->isRejected())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-950/80 text-rose-300 border border-rose-800/60">
                                            DITOLAK
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400">
                                            DIBATALKAN
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-3 text-right">
                                    @if(! $order->isPaid() && ! $order->isCancelled())
                                        <div class="inline-flex items-center gap-1.5 justify-end">
                                            <form method="POST" action="{{ route('platform.quota_orders.approve', $order) }}" onsubmit="return confirm('Verifikasi pembayaran dan tambah kuota {{ $order->hours }} jam ke {{ $order->organization->name }}?');">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs transition cursor-pointer"
                                                >
                                                    ✓ Setujui
                                                </button>
                                            </form>

                                            <button
                                                type="button"
                                                @click="openRejectModal({{ $order->id }}, '{{ $order->order_number }}', '{{ route('platform.quota_orders.reject', $order) }}')"
                                                class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-semibold bg-rose-900/60 hover:bg-rose-900 text-rose-300 border border-rose-800/60 transition cursor-pointer"
                                            >
                                                ✕ Tolak
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-slate-500 text-[11px]">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pt-4 border-t border-slate-800">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

    <!-- Reject Reason Modal -->
    <div
        x-show="rejectModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs"
        @keydown.escape.window="rejectModalOpen = false"
    >
        <div
            @click.away="rejectModalOpen = false"
            class="w-full max-w-md bg-slate-900 rounded-2xl p-6 shadow-2xl border border-slate-800 space-y-4"
        >
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-base font-bold text-white">Tolak Bukti Pembayaran</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Pesanan: <span class="font-mono text-indigo-400 font-bold" x-text="selectedOrderNumber"></span></p>
                </div>
                <button @click="rejectModalOpen = false" type="button" class="text-slate-400 hover:text-white">✕</button>
            </div>

            <form method="POST" :action="rejectActionUrl" class="space-y-4">
                @csrf
                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-300 mb-1">
                        Alasan Penolakan <span class="text-rose-400">*</span>
                    </label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        required
                        placeholder="Contoh: Bukti transfer buram / nominal tidak sesuai / mutasi belum masuk."
                        class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-white placeholder-slate-500 shadow-xs focus:ring-1 focus:ring-indigo-500"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                    <button
                        @click="rejectModalOpen = false"
                        type="button"
                        class="px-3.5 py-2 rounded-xl border border-slate-700 bg-slate-800 text-xs font-semibold text-slate-300 hover:text-white transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-xs font-bold text-white shadow-xs transition"
                    >
                        Kirim Penolakan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

