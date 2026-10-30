<x-layouts.app :title="'Pembelian Kuota Rekam - ' . config('app.name', 'Notulensi Rapat')">
    <x-slot:headerActions>
        <a
            href="{{ route('dashboard') }}"
            class="inline-flex min-h-[38px] items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
        >
            <span>← Kembali ke Dashboard</span>
        </a>
    </x-slot:headerActions>

    <div class="space-y-6" x-data="{
        baseRate: {{ $baseRate }},
        hours: 3,
        minHours: {{ $minHours }},
        maxHours: {{ $maxHours }},
        get discountPct() {
            if (this.hours >= 11) return 30;
            if (this.hours >= 6) return 20;
            if (this.hours >= 3) return 10;
            return 0;
        },
        get discountAmountPerHr() {
            return Math.round(this.baseRate * (this.discountPct / 100));
        },
        get effectiveRate() {
            return this.baseRate - this.discountAmountPerHr;
        },
        get subtotal() {
            return this.hours * this.baseRate;
        },
        get totalDiscount() {
            return this.hours * this.discountAmountPerHr;
        },
        get totalAmount() {
            return this.hours * this.effectiveRate;
        },
        formatRupiah(num) {
            return 'Rp ' + Number(num).toLocaleString('id-ID');
        }
    }">
        <!-- Page Title & Header -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs sm:p-6 space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span class="text-xs font-medium uppercase tracking-wider text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full font-semibold">
                        Pay-as-You-Go / Prabayar
                    </span>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-2">
                        Fasilitas Kuota Rekam Rapat
                    </h1>
                    <p class="text-xs font-semibold text-slate-700 mt-1">
                        Organisasi: <span class="text-indigo-600">{{ $organization?->name ?? 'Organisasi' }}</span>
                    </p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Hanya bayar ketika organisasi Anda ingin menggunakan fasilitas rekaman & notulensi. Kuota tidak memiliki batas kedaluwarsa.
                    </p>
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

        <!-- Quota Overview Stats -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs space-y-1">
                <span class="text-xs text-slate-500 font-medium">Sisa Kuota</span>
                <p class="text-2xl font-bold text-indigo-600">{{ $availableHours }} Jam</p>
                <p class="text-[11px] text-slate-400">Setara {{ $availableMinutes }} menit</p>
            </div>

            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs space-y-1">
                <span class="text-xs text-slate-500 font-medium">Kuota Terpakai</span>
                <p class="text-2xl font-bold text-slate-800">{{ round($usedMinutes / 60, 1) }} Jam</p>
                <p class="text-[11px] text-slate-400">Total {{ $usedMinutes }} menit audio</p>
            </div>

            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs space-y-1">
                <span class="text-xs text-slate-500 font-medium">Total Akumulasi</span>
                <p class="text-2xl font-bold text-slate-800">{{ round($totalMinutes / 60, 1) }} Jam</p>
                <p class="text-[11px] text-slate-400">Riwayat alokasi + beli</p>
            </div>

            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs space-y-1">
                <span class="text-xs text-slate-500 font-medium">Masa Toleransi</span>
                <p class="text-2xl font-bold text-emerald-600">{{ $gracePeriodMinutes }} Menit</p>
                <p class="text-[11px] text-slate-400">Buffer jika kuota 0</p>
            </div>
        </div>

        @can('create', App\Models\QuotaOrder::class)
        <!-- Calculator & Purchase Form -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs sm:p-6 space-y-5">
            <div>
                <h2 class="text-base font-bold text-slate-900">Pilih Jumlah Jam Yang Diinginkan</h2>
                <p class="text-xs text-slate-500">Harga dasar: {{ 'Rp ' . number_format($baseRate, 0, ',', '.') }}/jam. Nikmati potongan harga bertingkat untuk paket jam lebih besar.</p>
            </div>

            <!-- Quick Pick Buttons -->
            <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-700">Pilihan Paket Cepat:</label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach($quickOptions as $opt)
                        <button
                            type="button"
                            @click="hours = {{ $opt['hours'] }}"
                            class="p-3 rounded-xl border text-left transition"
                            :class="hours === {{ $opt['hours'] }} ? 'border-indigo-600 bg-indigo-50/70 ring-2 ring-indigo-600/20' : 'border-slate-200 bg-white hover:bg-slate-50'"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-sm text-slate-900">{{ $opt['hours'] }} Jam</span>
                                @if($opt['discount_pct'] > 0)
                                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">
                                        Hemat {{ $opt['discount_pct'] }}%
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-500 mt-1 font-medium">
                                Rp {{ number_format($opt['total_amount'], 0, ',', '.') }}
                            </p>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Custom Hours Slider & Input -->
            <div class="space-y-2 pt-2 border-t border-slate-100">
                <div class="flex items-center justify-between">
                    <label for="hours_input" class="text-xs font-semibold text-slate-700">Atau Tentukan Jumlah Jam Sendiri:</label>
                    <div class="flex items-center gap-1.5">
                        <input
                            type="number"
                            id="hours_input"
                            x-model.number="hours"
                            :min="minHours"
                            :max="maxHours"
                            class="w-20 text-center font-bold text-sm rounded-lg border border-slate-300 py-1 px-2 focus:ring-2 focus:ring-indigo-600"
                        />
                        <span class="text-xs font-semibold text-slate-600">Jam</span>
                    </div>
                </div>

                <input
                    type="range"
                    x-model.number="hours"
                    :min="minHours"
                    :max="maxHours"
                    step="1"
                    class="w-full accent-indigo-600 cursor-pointer"
                />

                <div class="flex justify-between text-[11px] text-slate-400">
                    <span>{{ $minHours }} Jam</span>
                    <span>50 Jam</span>
                    <span>{{ $maxHours }} Jam</span>
                </div>
            </div>

            <!-- Realtime Price Breakdown Box -->
            <div class="rounded-xl bg-slate-50 p-4 border border-slate-200/90 space-y-2 text-xs">
                <div class="flex justify-between text-slate-600">
                    <span>Durasi Kuota:</span>
                    <span class="font-bold text-slate-900">
                        <span x-text="hours"></span> Jam (<span x-text="hours * 60"></span> Menit)
                    </span>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>Harga Dasar per Jam:</span>
                    <span class="font-medium text-slate-700" x-text="formatRupiah(baseRate)"></span>
                </div>
                <div x-show="discountPct > 0" class="flex justify-between text-emerald-700 font-medium">
                    <span>Diskon Paket (<span x-text="discountPct"></span>%):</span>
                    <span>- <span x-text="formatRupiah(totalDiscount)"></span></span>
                </div>
                <div class="pt-2 border-t border-slate-200 flex justify-between items-center text-sm">
                    <span class="font-bold text-slate-900">Total Pembayaran:</span>
                    <span class="font-extrabold text-lg text-indigo-700" x-text="formatRupiah(totalAmount)"></span>
                </div>
            </div>

            <!-- Order Submission Form -->
            <form method="POST" action="{{ route('quota.orders.store') }}">
                @csrf
                <input type="hidden" name="hours" :value="hours">
                <button
                    type="submit"
                    class="w-full flex items-center justify-center gap-2 rounded-xl bg-indigo-600 py-3.5 px-4 font-bold text-white shadow-md hover:bg-indigo-700 transition cursor-pointer"
                >
                    <span>Lanjutkan ke Pembayaran</span>
                    <span>&rarr;</span>
                </button>
            </form>
        </div>
        @endcan

        <!-- Order History Table -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs sm:p-6 space-y-4">
            <h2 class="text-base font-bold text-slate-900">Riwayat Pembelian Kuota</h2>

            @if($orders->isEmpty())
                <p class="text-xs text-slate-400 py-4 text-center">Belum ada riwayat pesanan kuota untuk organisasi Anda.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 font-semibold uppercase">
                                <th class="py-2.5 px-2">No. Pesanan</th>
                                <th class="py-2.5 px-2">Tanggal</th>
                                <th class="py-2.5 px-2">Jumlah Kuota</th>
                                <th class="py-2.5 px-2">Total Biaya</th>
                                <th class="py-2.5 px-2">Status</th>
                                <th class="py-2.5 px-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($orders as $order)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3 px-2 font-mono font-bold text-slate-800">
                                        {{ $order->order_number }}
                                    </td>
                                    <td class="py-3 px-2 text-slate-600">
                                        {{ $order->created_at->format('d M Y, H:i') }}
                                    </td>
                                    <td class="py-3 px-2 font-medium text-slate-800">
                                        {{ $order->hours }} Jam ({{ $order->minutes }} Menit)
                                    </td>
                                    <td class="py-3 px-2 font-bold text-slate-900">
                                        Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 px-2">
                                        @if($order->isPaid())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20">
                                                Lunas / Selesai
                                            </span>
                                        @elseif($order->isWaitingVerification())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 ring-1 ring-amber-600/20">
                                                Menunggu Verifikasi
                                            </span>
                                        @elseif($order->isPending())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 ring-1 ring-blue-600/20">
                                                Menunggu Pembayaran
                                            </span>
                                        @elseif($order->isRejected())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 ring-1 ring-rose-600/20">
                                                Ditolak
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                                                Dibatalkan
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-2 text-right">
                                        <a
                                            href="{{ route('quota.orders.show', $order) }}"
                                            class="inline-flex items-center font-semibold text-indigo-600 hover:text-indigo-800"
                                        >
                                            Rincian &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pt-3 border-t border-slate-100">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
