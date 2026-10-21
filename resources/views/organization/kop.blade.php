<x-layouts.app :title="'Kop Surat - ' . $organization->name">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between gap-3">
            <div>
                <a href="{{ route('dashboard') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                    ← Kembali ke Dashboard
                </a>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-0.5">
                    Kop Surat Organisasi
                </h1>
                <p class="text-xs text-slate-500">
                    Kop surat resmi yang akan dicetak pada ekspor dokumen PDF Notulen dan Berita Acara.
                </p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-xs font-medium text-emerald-800">
                ✅ {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-xs text-rose-800 space-y-1">
                @foreach ($errors->all() as $error)
                    <p>⚠️ {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <!-- Current Kop Preview Card -->
        <div class="rounded-2xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700">
                Kop Surat Saat Ini
            </h2>

            @if($organization->kop_path)
                <div class="space-y-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 text-center">
                        <img
                            src="{{ route('organization.kop.preview') }}"
                            alt="Pratinjau Kop Surat"
                            class="mx-auto max-h-32 object-contain rounded-lg border border-slate-200 bg-white p-2 shadow-2xs"
                        />
                    </div>

                    @can('update', $organization)
                        <div class="flex justify-end">
                            <form method="POST" action="{{ route('organization.kop.destroy') }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus gambar kop surat ini?');">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100 transition cursor-pointer"
                                >
                                    <span>🗑️</span>
                                    <span>Hapus Gambar Kop</span>
                                </button>
                            </form>
                        </div>
                    @endcan
                </div>
            @else
                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center space-y-2">
                    <span class="text-2xl">📄</span>
                    <h3 class="text-xs font-bold text-slate-700">Belum Ada Gambar Kop Diunggah</h3>
                    <p class="text-[11px] text-slate-500 max-w-sm mx-auto">
                        Dokumen PDF saat ini menggunakan kop teks standar berupa Nama Organisasi dan Alamat resmi.
                    </p>
                </div>
            @endif
        </div>

        <!-- Upload Form (Admin only) -->
        @can('update', $organization)
            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700">
                    {{ $organization->kop_path ? 'Ganti Kop Surat' : 'Unggah Kop Surat' }}
                </h2>

                <form method="POST" action="{{ route('organization.kop.update') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <!-- File input -->
                    <div class="space-y-1.5">
                        <label for="kop_file" class="block text-xs font-bold text-slate-700">
                            Berkas Gambar Kop Surat <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="file"
                            id="kop_file"
                            name="kop"
                            accept="image/png,image/jpeg,image/webp"
                            required
                            class="block w-full text-xs text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                        />
                        <p class="text-[11px] text-slate-400">
                            Format JPG, PNG, atau WebP. Maksimal 2 MB. Disarankan proporsi horizontal memanjang (seperti kop surat standar).
                        </p>
                    </div>

                    <!-- Address input -->
                    <div class="space-y-1.5">
                        <label for="address_text" class="block text-xs font-bold text-slate-700">
                            Alamat & Keterangan Lembaga (Opsional)
                        </label>
                        <textarea
                            id="address_text"
                            name="address_text"
                            rows="2"
                            class="block w-full rounded-xl border border-slate-300 p-2.5 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                            placeholder="Contoh: Jl. Pendidikan No. 45, Jakarta Selatan • Telp: (021) 1234567 • email: info@sekolah.sch.id"
                        >{{ old('address_text', $organization->address_text) }}</textarea>
                    </div>

                    <div class="flex items-center justify-end pt-2">
                        <button
                            type="submit"
                            class="inline-flex min-h-[40px] items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-2xs hover:bg-indigo-700 active:scale-[0.98] transition cursor-pointer"
                        >
                            <span>💾</span>
                            <span>Simpan Kop Surat</span>
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
                ℹ️ Pengaturan gambar kop surat hanya dapat diubah oleh <strong>Admin</strong> organisasi.
            </div>
        @endcan
    </div>
</x-layouts.app>
