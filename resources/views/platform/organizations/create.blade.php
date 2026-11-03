@extends('platform.layouts.app')

@section('title', 'Daftarkan Lembaga Baru')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{
    name: '{{ old('name', '') }}',
    slug: '{{ old('slug', '') }}',
    customSlug: false,
    plan: '{{ old('plan', 'free') }}',
    quotaMinutes: {{ old('quota_minutes', 60) }},
    withLeader: {{ old('leader_email') ? 'true' : 'false' }},
    generateSlug() {
        if (!this.customSlug) {
            this.slug = this.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, '');
        }
    },
    updatePlanDefaults() {
        if (this.plan === 'free') {
            this.quotaMinutes = 60;
        } else if (this.plan === 'paid') {
            this.quotaMinutes = 300;
        } else if (this.plan === 'internal') {
            this.quotaMinutes = 6000;
        }
    }
}">
    <!-- Top Header -->
    <div class="flex items-center justify-between gap-4">
        <div>
            <a href="{{ route('platform.dashboard') }}" class="text-xs font-medium text-indigo-400 hover:text-indigo-300 flex items-center gap-1">
                ← Kembali ke Dashboard Lembaga
            </a>
            <h1 class="text-2xl font-black tracking-tight text-white mt-1">Daftarkan Lembaga / Organisasi Baru</h1>
            <p class="text-xs text-slate-400 mt-1">
                Buat organisasi mitra B2B baru beserta akun administrator awal dan kuota uji coba.
            </p>
        </div>
    </div>

    <!-- Error Summary (if any) -->
    @if ($errors->any())
        <div class="rounded-xl border border-rose-500/30 bg-rose-950/40 p-4 text-xs text-rose-300 space-y-1.5">
            <div class="font-bold flex items-center gap-1.5">
                <span>⚠️</span> Mohon periksa kembali formulir berikut:
            </div>
            <ul class="list-disc list-inside space-y-1 text-[11px] text-rose-400">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('platform.organizations.store') }}" class="space-y-6">
        @csrf

        <!-- CARD 1: Data Lembaga -->
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-sm space-y-5">
            <div class="border-b border-slate-800 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span>🏢</span> 1. Profil Lembaga / Instansi
                    </h2>
                    <p class="text-[11px] text-slate-400 mt-0.5">Identitas sekolah, yayasan, atau instansi yang akan menggunakan aplikasi.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Nama Lembaga -->
                <div class="md:col-span-2 space-y-1.5">
                    <label for="name" class="block text-xs font-semibold text-slate-300">
                        Nama Lembaga / Sekolah <span class="text-rose-400">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        x-model="name"
                        @input="generateSlug()"
                        required
                        placeholder="Contoh: SMA Negeri 2 Bandung"
                        value="{{ old('name') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                </div>

                <!-- Slug URL -->
                <div class="space-y-1.5">
                    <label for="slug" class="block text-xs font-semibold text-slate-300">
                        Kode Unik / Slug Sistem
                    </label>
                    <input
                        type="text"
                        name="slug"
                        id="slug"
                        x-model="slug"
                        @input="customSlug = true"
                        placeholder="sman2-bandung"
                        value="{{ old('slug') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-xs font-mono text-indigo-300 placeholder-slate-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                    <p class="text-[10px] text-slate-500">Pengidentifikasi unik direktori berkas dan audit log.</p>
                </div>

                <!-- Label Pimpinan -->
                <div class="space-y-1.5">
                    <label for="leader_label" class="block text-xs font-semibold text-slate-300">
                        Label Jabatan Pimpinan
                    </label>
                    <input
                        type="text"
                        name="leader_label"
                        id="leader_label"
                        placeholder="Kepala Sekolah, Direktur, Ketua Yayasan"
                        value="{{ old('leader_label', 'Kepala Sekolah') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                    <p class="text-[10px] text-slate-500">Label resmi yang muncul pada dokumen PDF notulen.</p>
                </div>

                <!-- Alamat Lembaga -->
                <div class="md:col-span-2 space-y-1.5">
                    <label for="address_text" class="block text-xs font-semibold text-slate-300">
                        Alamat Singkat Lembaga (Opsional)
                    </label>
                    <input
                        type="text"
                        name="address_text"
                        id="address_text"
                        placeholder="Jl. Merdeka No. 45, Kota Bandung"
                        value="{{ old('address_text') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                </div>
            </div>

            <!-- Paket & Kuota -->
            <div class="pt-4 border-t border-slate-800 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="plan" class="block text-xs font-semibold text-slate-300">
                        Paket Layanan <span class="text-rose-400">*</span>
                    </label>
                    <select
                        name="plan"
                        id="plan"
                        x-model="plan"
                        @change="updatePlanDefaults()"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-xs text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                        <option value="free">Free / Trial (Uji Coba Mitra B2B - Default 1 Jam)</option>
                        <option value="paid">Paid (Berbayar / Kontrak Kustom)</option>
                        <option value="internal">Internal / Pilot Organisasi Sendiri</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label for="quota_minutes" class="block text-xs font-semibold text-slate-300">
                        Alokasi Kuota Awal (Menit Rekaman Audio) <span class="text-rose-400">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="number"
                            name="quota_minutes"
                            id="quota_minutes"
                            x-model.number="quotaMinutes"
                            min="0"
                            step="15"
                            required
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-xs text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 pr-16"
                        >
                        <span class="absolute right-3.5 top-2.5 text-xs font-semibold text-slate-400">
                            Menit (<span x-text="(quotaMinutes / 60).toFixed(1)"></span> Jam)
                        </span>
                    </div>
                    <p class="text-[10px] text-slate-500">60 menit setara dengan 1 jam durasi rekaman rapat audio.</p>
                </div>
            </div>
        </div>

        <!-- CARD 2: Akun Administrator Utama -->
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-sm space-y-5">
            <div class="border-b border-slate-800 pb-3">
                <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                    <span>👤</span> 2. Akun Administrator Lembaga (Notulis Utama)
                </h2>
                <p class="text-[11px] text-slate-400 mt-0.5">Akun ini memiliki hak akses penuh mengatur pengguna, membuat rapat, dan mengunggah rekaman.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="admin_name" class="block text-xs font-semibold text-slate-300">
                        Nama Lengkap Administrator <span class="text-rose-400">*</span>
                    </label>
                    <input
                        type="text"
                        name="admin_name"
                        id="admin_name"
                        required
                        placeholder="Contoh: Budi Santoso, S.Kom."
                        value="{{ old('admin_name') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                </div>

                <div class="space-y-1.5">
                    <label for="admin_email" class="block text-xs font-semibold text-slate-300">
                        Email Login Administrator <span class="text-rose-400">*</span>
                    </label>
                    <input
                        type="email"
                        name="admin_email"
                        id="admin_email"
                        required
                        placeholder="admin@sekolah.sch.id"
                        value="{{ old('admin_email') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                </div>

                <div class="md:col-span-2 space-y-1.5">
                    <label for="admin_password" class="block text-xs font-semibold text-slate-300">
                        Kata Sandi Awal (Opsional)
                    </label>
                    <input
                        type="text"
                        name="admin_password"
                        id="admin_password"
                        placeholder="Kosongkan jika ingin dibuatkan kata sandi acak otomatis oleh sistem"
                        value="{{ old('admin_password') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-xs font-mono text-emerald-400 placeholder-slate-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                    <p class="text-[10px] text-slate-500">Kata sandi dapat disalin setelah proses pembuatan selesai.</p>
                </div>
            </div>
        </div>

        <!-- CARD 3: Akun Pimpinan Lembaga (Opsional) -->
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-sm space-y-5">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span>👔</span> 3. Akun Pimpinan Lembaga (Opsional)
                    </h2>
                    <p class="text-[11px] text-slate-400 mt-0.5">Pimpinan memiliki hak melihat seluruh notulen final dan menandatangani/mengetahui rapat.</p>
                </div>
                <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-slate-300">
                    <input
                        type="checkbox"
                        x-model="withLeader"
                        class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                    >
                    <span>Buat Akun Pimpinan Sekaligus</span>
                </label>
            </div>

            <div x-show="withLeader" style="display: none;" class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                <div class="space-y-1.5">
                    <label for="leader_name" class="block text-xs font-semibold text-slate-300">
                        Nama Lengkap Pimpinan
                    </label>
                    <input
                        type="text"
                        name="leader_name"
                        id="leader_name"
                        placeholder="Contoh: Drs. H. Ahmad Fauzi, M.Pd."
                        value="{{ old('leader_name') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                </div>

                <div class="space-y-1.5">
                    <label for="leader_email" class="block text-xs font-semibold text-slate-300">
                        Email Login Pimpinan
                    </label>
                    <input
                        type="email"
                        name="leader_email"
                        id="leader_email"
                        placeholder="kepsek@sekolah.sch.id"
                        value="{{ old('leader_email') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                </div>

                <div class="md:col-span-2 space-y-1.5">
                    <label for="leader_password" class="block text-xs font-semibold text-slate-300">
                        Kata Sandi Pimpinan (Opsional)
                    </label>
                    <input
                        type="text"
                        name="leader_password"
                        id="leader_password"
                        placeholder="Kosongkan untuk otomatis membuat kata sandi acak"
                        value="{{ old('leader_password') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-xs font-mono text-emerald-400 placeholder-slate-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                </div>
            </div>

            <div x-show="!withLeader" class="text-xs text-slate-500 italic py-1">
                Admin lembaga dapat menambahkan akun pimpinan nanti secara mandiri melalui menu Pengguna di dalam aplikasi.
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-3 pt-4">
            <a
                href="{{ route('platform.dashboard') }}"
                class="rounded-xl border border-slate-700 bg-slate-800 px-5 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-700 hover:text-white transition"
            >
                Batal
            </a>
            <button
                type="submit"
                class="rounded-xl bg-indigo-600 px-6 py-2.5 text-xs font-bold text-white shadow-md hover:bg-indigo-500 active:scale-95 transition flex items-center gap-2"
            >
                <span>🏢</span> Simpan & Daftarkan Lembaga
            </button>
        </div>
    </form>
</div>
@endsection

