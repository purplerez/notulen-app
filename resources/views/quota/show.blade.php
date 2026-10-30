<x-layouts.app :title="'Tagihan Pesanan #' . $order->order_number . ' - ' . config('app.name', 'Notulensi Rapat')">
    <x-slot:headerActions>
        <a
            href="{{ route('quota.index') }}"
            class="inline-flex min-h-[38px] items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
        >
            <span>← Kembali ke Daftar Kuota</span>
        </a>
    </x-slot:headerActions>

    <div class="space-y-6">
        <!-- Order Header Card -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs sm:p-6 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span class="text-xs font-mono uppercase tracking-wider text-slate-400">Invoice Tagihan</span>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-0.5">
                        Pesanan #{{ $order->order_number }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Dibuat pada {{ $order->created_at->format('d F Y, H:i') }} WIB oleh {{ $order->creator?->name ?? 'Admin' }}
                    </p>
                </div>

                <div>
                    @if($order->isPaid())
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20">
                            ✓ LUNAS / SELESAI
                        </span>
                    @elseif($order->isWaitingVerification())
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 ring-1 ring-amber-600/20">
                            ⏳ MENUNGGU VERIFIKASI
                        </span>
                    @elseif($order->isPending())
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 ring-1 ring-blue-600/20">
                            MENUNGGU PEMBAYARAN
                        </span>
                    @elseif($order->isRejected())
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 ring-1 ring-rose-600/20">
                            DITOLAK
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
                            DIBATALKAN
                        </span>
                    @endif
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        @if($order->isRejected() && $order->notes)
            <div class="rounded-2xl border border-rose-300 bg-rose-50 p-4 text-xs text-rose-900 space-y-1">
                <strong class="font-bold">Alasan Penolakan dari Administrator Platform:</strong>
                <p>{{ $order->notes }}</p>
            </div>
        @endif

        <!-- Order Summary Card -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs sm:p-6 space-y-4">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-slate-400">Rincian Pembelian</h2>
            <div class="divide-y divide-slate-100 text-xs">
                <div class="py-2.5 flex justify-between text-slate-600">
                    <span>Durasi Kuota yang Dibeli</span>
                    <strong class="text-slate-900">{{ $order->hours }} Jam ({{ $order->minutes }} Menit)</strong>
                </div>
                <div class="py-2.5 flex justify-between text-slate-600">
                    <span>Harga Efektif per Jam</span>
                    <strong class="text-slate-900">Rp {{ number_format($order->price_per_hour, 0, ',', '.') }}</strong>
                </div>
                <div class="py-3 flex justify-between items-center text-sm">
                    <span class="font-bold text-slate-900">Total Tagihan</span>
                    <strong class="text-lg font-extrabold text-indigo-700">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong>
                </div>
            </div>
        </div>

        @if($order->isPaid())
            <!-- Paid Success State -->
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 sm:p-6 text-xs text-emerald-900 space-y-2">
                <div class="font-bold text-emerald-900 text-sm flex items-center gap-1.5">
                    <span>🎉</span> Kuota Anda Telah Aktif!
                </div>
                <p class="leading-relaxed">
                    Pembayaran sebesar <strong>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong> telah diterima pada {{ $order->paid_at?->format('d F Y, H:i') }} WIB. Kuota rekaman sebanyak <strong>{{ $order->hours }} Jam</strong> telah otomatis ditambahkan ke saldo organisasi Anda.
                </p>
                <div class="pt-2">
                    <a href="{{ route('meetings.create') }}" class="inline-flex items-center gap-1 font-bold text-emerald-800 underline">
                        Mulai Rekam Rapat Baru Sekarang &rarr;
                    </a>
                </div>
            </div>
        @elseif($order->isPending() || $order->isWaitingVerification() || $order->isRejected())
            <!-- Payment Instructions Card -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs sm:p-6 space-y-4">
                <h2 class="text-base font-bold text-slate-900">Instruksi Pembayaran Transfer Bank</h2>
                <p class="text-xs text-slate-500">
                    Silakan transfer nominal persis <strong>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong> ke salah satu rekening resmi platform di bawah ini:
                </p>

                <div class="space-y-3">
                    @foreach($bankAccounts as $acc)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-slate-900">{{ $acc['bank_name'] }}</span>
                                <span class="text-[11px] text-slate-500">{{ $acc['account_holder'] }}</span>
                            </div>
                            <div class="flex items-center justify-between pt-1">
                                <span class="font-mono font-extrabold text-base text-indigo-700 tracking-wider">
                                    {{ $acc['account_number'] }}
                                </span>
                                <button
                                    type="button"
                                    onclick="navigator.clipboard.writeText('{{ $acc['account_number'] }}'); alert('Nomor rekening disalin ke clipboard!')"
                                    class="text-xs font-semibold text-slate-600 hover:text-indigo-600 bg-white border border-slate-200 rounded-lg px-2 py-1 shadow-2xs"
                                >
                                    Salin
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                @can('uploadProof', $order)
                    <!-- Upload Proof Form -->
                    <div class="pt-4 border-t border-slate-100 space-y-3">
                        <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">
                            {{ $order->isWaitingVerification() ? 'Unggah Ulang Bukti Transfer' : 'Konfirmasi Bukti Transfer' }}
                        </h3>
                        @if($order->isWaitingVerification())
                            <p class="text-xs text-amber-700">
                                Bukti pembayaran telah tersimpan dan sedang menunggu peninjauan Super Admin. Anda dapat mengunggah bukti baru jika diperlukan.
                            </p>
                        @endif
                        <form method="POST" action="{{ route('quota.orders.proof', $order) }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <div>
                                <label for="proof" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Pilih Berkas Foto Struk / Tangkapan Layar (JPG, PNG, PDF maks. 5MB):
                                </label>
                                <input
                                    type="file"
                                    id="proof"
                                    name="proof"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    required
                                    class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                                />
                            </div>
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-2xs hover:bg-indigo-700 transition cursor-pointer"
                            >
                                <span>📤</span>
                                <span>Kirim Bukti Pembayaran</span>
                            </button>
                        </form>
                    </div>
                @endcan

                @can('simulate', $order)
                    <!-- Developer Sandbox / Simulation Section -->
                    <div class="pt-4 border-t border-slate-100 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Mode Simulasi Pengujian</span>
                            <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-mono">Sandbox</span>
                        </div>
                        <p class="text-xs text-slate-500">
                            Untuk mempercepat pengujian tanpa transfer bank nyata, Anda dapat melakukan simulasi pelunasan instan:
                        </p>
                        <form method="POST" action="{{ route('quota.orders.simulate', $order) }}">
                            @csrf
                            <button
                                type="submit"
                                onclick="return confirm('Simulasikan pembayaran lunas untuk pesanan ini?')"
                                class="w-full flex items-center justify-center gap-2 rounded-xl bg-emerald-600 py-2.5 px-4 text-xs font-bold text-white shadow-2xs hover:bg-emerald-700 transition cursor-pointer"
                            >
                                <span>⚡ Simulasi Bayar Instan (Sandbox Testing)</span>
                            </button>
                        </form>
                    </div>
                @endcan

                @can('cancel', $order)
                    <!-- Cancel Order Button -->
                    <div class="pt-3 border-t border-slate-100 text-right">
                        <form method="POST" action="{{ route('quota.orders.cancel', $order) }}" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini?');">
                            @csrf
                            <button
                                type="submit"
                                class="text-xs font-semibold text-rose-600 hover:text-rose-800 transition cursor-pointer"
                            >
                                Batalkan Pesanan Ini
                            </button>
                        </form>
                    </div>
                @endcan
            </div>
        @endif
    </div>
</x-layouts.app>

