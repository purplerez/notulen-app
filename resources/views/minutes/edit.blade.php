<x-layouts.app :title="'Sunting Draf Notulen - ' . $meeting->title">
    @php
        $content = $minute->content_json ?? [];
        $ringkasan = old('ringkasan', $content['ringkasan'] ?? '');
        $agenda = old('agenda', $content['agenda'] ?? []);
        $pembahasan = old('pembahasan', $content['pembahasan'] ?? '');
        $keputusan = old('keputusan', $content['keputusan'] ?? []);
        $actionItemsData = old('action_items', $minute->actionItems->map(fn($item) => [
            'description' => $item->description,
            'assignee_name' => $item->assignee_name,
            'due_date' => $item->due_date ? \Carbon\Carbon::parse($item->due_date)->format('Y-m-d') : '',
            'status' => $item->status,
        ])->all());
    @endphp

    <div
        x-data="{
            finalizeModalOpen: false,
            agendaList: @js(!empty($agenda) ? $agenda : ['']),
            keputusanList: @js(!empty($keputusan) ? $keputusan : ['']),
            actionItems: @js(!empty($actionItemsData) ? $actionItemsData : []),
            markersOpen: false,

            addAgenda() {
                this.agendaList.push('');
            },
            removeAgenda(index) {
                if (this.agendaList.length > 1) {
                    this.agendaList.splice(index, 1);
                } else {
                    this.agendaList = [''];
                }
            },
            addKeputusan() {
                this.keputusanList.push('');
            },
            removeKeputusan(index) {
                if (this.keputusanList.length > 1) {
                    this.keputusanList.splice(index, 1);
                } else {
                    this.keputusanList = [''];
                }
            },
            addActionItem() {
                this.actionItems.push({ description: '', assignee_name: '', due_date: '', status: 'pending' });
            },
            removeActionItem(index) {
                this.actionItems.splice(index, 1);
            }
        }"
        class="space-y-6"
    >
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 pb-4">
            <div>
                <a href="{{ route('meetings.minutes.show', ['meeting' => $meeting, 'v' => $minute->version]) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                    ← Kembali ke Tampilan Notulen
                </a>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-1">
                    Penyuntingan Draf Notulen (Versi {{ $minute->version }})
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Rapat: <strong>{{ $meeting->title }}</strong> · Gaya {{ ucfirst($meeting->style) }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="markersOpen = !markersOpen"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 cursor-pointer"
                >
                    <span>📌</span>
                    <span>Petunjuk Penanda ({{ $markers->count() }})</span>
                </button>

                <button
                    type="button"
                    @click="finalizeModalOpen = true"
                    class="inline-flex min-h-[40px] items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-2xs hover:bg-emerald-700 active:scale-[0.98] transition cursor-pointer"
                >
                    <span>🔒</span>
                    <span>Finalisasi Notulen</span>
                </button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-xs text-rose-800 space-y-1">
                <p class="font-bold">⚠️ Mohon periksa kembali formulir:</p>
                @foreach ($errors->all() as $error)
                    <p>• {{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <!-- Manual Markers Collapsible Drawer / Panel -->
        <div
            x-show="markersOpen"
            x-transition
            class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-4 text-xs space-y-2"
            style="display: none;"
        >
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-indigo-900 flex items-center gap-1.5">
                    <span>📌</span> Penanda Manual Saat Rapat
                </h3>
                <button type="button" @click="markersOpen = false" class="text-indigo-600 font-bold hover:text-indigo-800">Tutup</button>
            </div>
            @if($markers->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                    @foreach($markers as $marker)
                        <div class="rounded-lg bg-white p-2.5 border border-indigo-100 shadow-2xs space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase
                                    @if($marker->type === 'keputusan') bg-emerald-50 text-emerald-700
                                    @elseif($marker->type === 'tugas') bg-blue-50 text-blue-700
                                    @else bg-amber-50 text-amber-700 @endif">
                                    {{ $marker->type }}
                                </span>
                                <span class="text-[10px] text-slate-400 font-mono">{{ sprintf('%02d:%02d', floor($marker->at_sec / 60), floor(fmod($marker->at_sec, 60))) }}</span>
                            </div>
                            <p class="text-xs text-slate-800 font-medium">{{ $marker->note }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-slate-500 italic">Tidak ada penanda manual yang ditambahkan selama rekaman rapat.</p>
            @endif
        </div>

        <!-- Main Editor Form -->
        <form method="POST" action="{{ route('meetings.minutes.update', $meeting) }}" class="space-y-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="minute_id" value="{{ $minute->id }}" />

            <!-- 1. Ringkasan Eksekutif -->
            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-2">
                <label for="ringkasan" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                    📌 Ringkasan Eksekutif
                </label>
                <textarea
                    id="ringkasan"
                    name="ringkasan"
                    rows="4"
                    class="block w-full rounded-xl border border-slate-300 p-3.5 text-sm text-slate-900 shadow-2xs focus:ring-2 focus:ring-indigo-600 leading-relaxed"
                    placeholder="Tulis ringkasan hasil rapat..."
                >{{ $ringkasan }}</textarea>
            </div>

            <!-- 2. Agenda Rapat (Hanya jika gaya lengkap atau memiliki isi) -->
            @if($meeting->style === 'lengkap' || !empty($agenda))
                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            📋 Agenda Rapat
                        </label>
                        <button
                            type="button"
                            @click="addAgenda()"
                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                        >
                            + Tambah Agenda
                        </button>
                    </div>

                    <div class="space-y-2">
                        <template x-for="(item, index) in agendaList" :key="index">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-400 w-5 text-right" x-text="index + 1 + '.'"></span>
                                <input
                                    type="text"
                                    name="agenda[]"
                                    x-model="agendaList[index]"
                                    placeholder="Agenda pembahasan..."
                                    class="block flex-1 rounded-xl border border-slate-300 px-3 py-2 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                                />
                                <button
                                    type="button"
                                    @click="removeAgenda(index)"
                                    class="text-slate-400 hover:text-rose-600 px-2 py-1 text-sm"
                                >
                                    ✕
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            @endif

            <!-- 3. Pembahasan Rinci (Hanya jika gaya lengkap) -->
            @if($meeting->style === 'lengkap')
                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-2">
                    <label for="pembahasan" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        💬 Pembahasan Rinci
                    </label>
                    <textarea
                        id="pembahasan"
                        name="pembahasan"
                        rows="6"
                        class="block w-full rounded-xl border border-slate-300 p-3.5 text-sm text-slate-900 shadow-2xs focus:ring-2 focus:ring-indigo-600 leading-relaxed whitespace-pre-line"
                        placeholder="Uraian pembahasan materi rapat..."
                    >{{ $pembahasan }}</textarea>
                </div>
            @endif

            <!-- 4. Keputusan Rapat -->
            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        ⚖️ Butir Keputusan Rapat
                    </label>
                    <button
                        type="button"
                        @click="addKeputusan()"
                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                    >
                        + Tambah Keputusan
                    </button>
                </div>

                <div class="space-y-2">
                    <template x-for="(item, index) in keputusanList" :key="index">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-400 w-5 text-right" x-text="index + 1 + '.'"></span>
                            <input
                                type="text"
                                name="keputusan[]"
                                x-model="keputusanList[index]"
                                placeholder="Uraian keputusan rapat..."
                                class="block flex-1 rounded-xl border border-slate-300 px-3.5 py-2.5 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                            />
                            <button
                                type="button"
                                @click="removeKeputusan(index)"
                                class="text-slate-400 hover:text-rose-600 px-2 py-1 text-sm"
                            >
                                ✕
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- 5. Tindak Lanjut / Action Items (Jika use_followup) -->
            @if($meeting->use_followup)
                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                🎯 Tindak Lanjut (Action Items)
                            </label>
                            <p class="text-[11px] text-slate-400">Tentukan penanggung jawab dan batas waktu penyelesaian</p>
                        </div>
                        <button
                            type="button"
                            @click="addActionItem()"
                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                        >
                            + Tambah Tugas
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(item, index) in actionItems" :key="index">
                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-600" x-text="'Tugas #' + (index + 1)"></span>
                                    <button
                                        type="button"
                                        @click="removeActionItem(index)"
                                        class="text-xs font-bold text-rose-600 hover:text-rose-800"
                                    >
                                        Hapus
                                    </button>
                                </div>

                                <div class="space-y-1">
                                    <input
                                        type="text"
                                        :name="'action_items[' + index + '][description]'"
                                        x-model="item.description"
                                        placeholder="Deskripsi tugas atau tindak lanjut..."
                                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                                    />
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-medium text-slate-500">Penanggung Jawab</label>
                                        <input
                                            type="text"
                                            :name="'action_items[' + index + '][assignee_name]'"
                                            x-model="item.assignee_name"
                                            placeholder="Nama PIC..."
                                            class="block w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-slate-500">Tenggat Waktu</label>
                                        <input
                                            type="date"
                                            :name="'action_items[' + index + '][due_date]'"
                                            x-model="item.due_date"
                                            class="block w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-slate-500">Status</label>
                                        <select
                                            :name="'action_items[' + index + '][status]'"
                                            x-model="item.status"
                                            class="block w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                                        >
                                            <option value="pending">Pending</option>
                                            <option value="in_progress">Dalam Proses</option>
                                            <option value="completed">Selesai</option>
                                            <option value="cancelled">Dibatalkan</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div x-show="actionItems.length === 0" class="text-center py-4 text-xs text-slate-400 italic">
                            Belum ada butir tindak lanjut. Klik '+ Tambah Tugas' untuk menambahkan.
                        </div>
                    </div>
                </div>
            @endif

            <!-- Save Draft Button -->
            <div class="flex items-center justify-between pt-2">
                <a
                    href="{{ route('meetings.minutes.show', ['meeting' => $meeting, 'v' => $minute->version]) }}"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                >
                    Batalkan / Kembali
                </a>

                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-800 shadow-2xs hover:bg-slate-50 active:scale-[0.98] transition cursor-pointer"
                    >
                        💾 Simpan Perubahan Draf
                    </button>

                    <button
                        type="button"
                        @click="finalizeModalOpen = true"
                        class="rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-2xs hover:bg-emerald-700 active:scale-[0.98] transition cursor-pointer"
                    >
                        🔒 Finalisasi Notulen
                    </button>
                </div>
            </div>
        </form>

        <!-- Finalize Modal with Mandatory share_expires_at -->
        <div
            x-show="finalizeModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
            style="display: none;"
        >
            <div
                @click.away="finalizeModalOpen = false"
                class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl space-y-4"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Finalisasi Notulen Rapat</h3>
                    <button type="button" @click="finalizeModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Setelah difinalkan, notulen versi {{ $minute->version }} akan dapat dibaca oleh pimpinan lembaga dan dibagikan kepada peserta rapat.
                </p>

                <form method="POST" action="{{ route('meetings.minutes.finalize', $meeting) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="minute_id" value="{{ $minute->id }}" />

                    <div class="space-y-1.5 rounded-xl bg-amber-50/70 p-4 border border-amber-200/80">
                        <label for="share_expires_at" class="block text-xs font-bold text-amber-900">
                            Batas Waktu Baca Peserta (Wajib) <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="datetime-local"
                            id="share_expires_at"
                            name="share_expires_at"
                            required
                            min="{{ now()->addHour()->format('Y-m-d\TH:i') }}"
                            value="{{ old('share_expires_at', $meeting->share_expires_at ? \Carbon\Carbon::parse($meeting->share_expires_at)->format('Y-m-d\TH:i') : now()->addDays(7)->format('Y-m-d\TH:i')) }}"
                            class="block w-full rounded-lg border border-amber-300 bg-white p-2.5 text-xs text-slate-900 shadow-2xs focus:ring-2 focus:ring-amber-600"
                        />
                        <p class="text-[11px] text-amber-700 leading-normal">
                            Tentukan batas akhir masa berlaku tautan publik bagi peserta untuk mengakses notulen ini.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="space-y-1">
                            <label for="access_mode" class="block font-bold text-slate-700">Mode Gerbang Akses</label>
                            <select
                                id="access_mode"
                                name="access_mode"
                                class="block w-full rounded-xl border border-slate-300 bg-white p-2 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                            >
                                <option value="phone" selected>Nomor WhatsApp</option>
                                <option value="code">Kode PIN 6 Digit</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label for="recipients_scope" class="block font-bold text-slate-700">Cakupan Penerima</label>
                            <select
                                id="recipients_scope"
                                name="recipients_scope"
                                class="block w-full rounded-xl border border-slate-300 bg-white p-2 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                            >
                                <option value="semua_undangan" selected>Semua Undangan</option>
                                <option value="hadir">Peserta Hadir Saja</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="finalizeModalOpen = false"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-2xs hover:bg-emerald-700"
                        >
                            Ya, Finalkan Notulen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
