@extends('layouts.app')

@section('title', 'AHP Calculation Inspector — SPK Kredit B2B (AHP & SAW)')

@section('header_status')
    <div class="hidden md:flex items-center gap-2 px-2.5 py-1 rounded-sm border border-hairline bg-canvas text-[11px] font-mono-tabular">
        <span class="inline-block w-1.5 h-1.5 rounded-full {{ $activeSession->is_consistent ? 'bg-emerald-600' : 'bg-rose-600' }}"></span>
        <span class="text-muted">CR AKTIF:</span>
        <span class="font-semibold text-ink">{{ number_format($activeSession->cr, 4) }}</span>
        <span class="text-zinc-300">|</span>
        <span class="text-muted">RI (n=5):</span>
        <span class="font-semibold text-ink">1.1200</span>
    </div>
@endsection

@section('nav_actions')
    <form method="POST" action="{{ route('spk.ahp.reset') }}">
        @csrf
        <button type="submit"
                class="px-2.5 py-1.5 text-xs font-medium rounded-sm border border-hairline bg-surface hover:bg-subtle text-ink transition-colors cursor-pointer">
            Reset Baseline Default
        </button>
    </form>
@endsection

@section('content')
@php
    $criteriaPayload = $criteria->map(fn ($c) => [
        'id' => $c->id,
        'code' => $c->code,
        'name' => $c->name,
        'type' => $c->type,
        'weight' => (float) $c->weight,
    ])->values();
@endphp

<div x-data="ahpInspector()" class="space-y-5">

    @if (session('status'))
        <div class="border border-emerald-300 bg-emerald-50 text-emerald-900 px-3.5 py-2.5 rounded-sm text-xs font-medium flex items-center justify-between">
            <span>{{ session('status') }}</span>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-950 font-mono-tabular text-[11px] cursor-pointer">TUTUP</button>
        </div>
    @endif

    @if ($errors->any())
        <div class="border border-rose-300 bg-rose-50 text-rose-900 px-3.5 py-2.5 rounded-sm text-xs font-medium">
            {{ $errors->first() }}
        </div>
    @endif

    <div x-show="applyMessage" x-cloak class="border px-3.5 py-2.5 rounded-sm text-xs font-medium flex items-center justify-between"
         :class="applyError ? 'border-rose-300 bg-rose-50 text-rose-900' : 'border-emerald-300 bg-emerald-50 text-emerald-900'">
        <span x-text="applyMessage"></span>
        <button type="button" x-on:click="applyMessage = null" class="font-mono-tabular text-[11px] cursor-pointer">TUTUP</button>
    </div>

    {{-- Top Page Header & Live Consistency Strip --}}
    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-wider text-muted">Transparansi Pembobotan — Analytical Hierarchy Process</p>
            <h1 class="text-lg font-bold tracking-tight text-ink">AHP Calculation &amp; Consistency Inspector</h1>
        </div>
        <div class="text-xs text-muted">
            Sesi Aktif: <strong class="text-ink font-medium">{{ $activeSession->name }}</strong>
        </div>
    </div>

    {{-- Live Consistency Metric Strip --}}
    <div class="bg-surface border border-hairline rounded-md grid grid-cols-2 md:grid-cols-5 divide-y md:divide-y-0 md:divide-x divide-hairline">
        <div class="p-3.5">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Status Konsistensi</div>
            <div class="mt-1.5">
                <span class="inline-flex items-center px-2 py-1 rounded-xs text-xs font-semibold font-mono-tabular"
                      :class="calc.is_consistent ? 'status-net30' : 'status-cbd'"
                      x-text="calc.is_consistent ? 'KONSISTEN (CR <= 0.10)' : 'TIDAK KONSISTEN (CR > 0.10)'"></span>
            </div>
        </div>
        <div class="p-3.5">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Eigenvalue (&lambda;<sub>max</sub>)</div>
            <div class="mt-1 text-xl font-semibold font-mono-tabular text-ink" x-text="Number(calc.lambda_max).toFixed(6)"></div>
            <div class="text-[10px] font-mono-tabular text-muted">Target ideal: 5.000000</div>
        </div>
        <div class="p-3.5">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Consistency Index (CI)</div>
            <div class="mt-1 text-xl font-semibold font-mono-tabular text-ink" x-text="Number(calc.ci).toFixed(6)"></div>
            <div class="text-[10px] font-mono-tabular text-muted">(&lambda;<sub>max</sub> &minus; 5) / 4</div>
        </div>
        <div class="p-3.5">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Random Index (RI)</div>
            <div class="mt-1 text-xl font-semibold font-mono-tabular text-ink" x-text="Number(calc.ri).toFixed(4)"></div>
            <div class="text-[10px] font-mono-tabular text-muted">Tabel Saaty (n = 5)</div>
        </div>
        <div class="p-3.5">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Consistency Ratio (CR)</div>
            <div class="mt-1 text-xl font-semibold font-mono-tabular"
                 :class="calc.is_consistent ? 'text-emerald-800' : 'text-rose-700'"
                 x-text="Number(calc.cr).toFixed(6)"></div>
            <div class="text-[10px] font-mono-tabular text-muted">Ambang batas: &le; 0.100000</div>
        </div>
    </div>

    {{-- Interactive Pairwise Comparison Matrix + Apply Controls --}}
    <section class="bg-surface border border-hairline rounded-md overflow-hidden">
        <div class="px-4 py-3 border-b border-hairline bg-subtle/40 flex flex-wrap items-center justify-between gap-3">
            <div>
                <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">Langkah 1 — Matriks Perbandingan Berpasangan (A<sub>5&times;5</sub>)</span>
                <h2 class="text-xs font-semibold text-ink">Editor Skala Saaty (Segitiga Atas Dapat Diubah &bull; Segitiga Bawah Otomatis Resiprokal 1/a<sub>ij</sub>)</h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button"
                        x-on:click="loadInconsistentDemo()"
                        class="px-2.5 py-1.5 text-xs font-medium rounded-sm border border-rose-200 bg-rose-50 text-rose-800 hover:bg-rose-100 transition-colors cursor-pointer">
                    Simulasi Matriks Tidak Konsisten (Uji CR &gt; 0.10)
                </button>
                <button type="button"
                        x-on:click="loadSavedMatrix()"
                        class="px-2.5 py-1.5 text-xs font-medium rounded-sm border border-hairline bg-surface hover:bg-subtle text-ink transition-colors cursor-pointer">
                    Muat Matriks Tersimpan
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="border-b border-hairline bg-canvas text-[10px] uppercase tracking-wider text-muted">
                        <th class="text-left font-semibold px-3.5 py-2.5 min-w-[200px]">Kriteria (Baris vs Kolom)</th>
                        <template x-for="(col, j) in criteria" :key="'h-' + col.code">
                            <th class="text-right font-semibold px-3 py-2.5 font-mono-tabular" x-text="col.code"></th>
                        </template>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    <template x-for="(row, i) in criteria" :key="'r-' + row.code">
                        <tr class="hover:bg-zinc-50/60">
                            <td class="px-3.5 py-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono-tabular text-[11px] font-semibold px-1.5 py-0.5 bg-subtle border border-hairline rounded-xs" x-text="row.code"></span>
                                    <span class="font-medium text-ink" x-text="row.name"></span>
                                </div>
                            </td>
                            <template x-for="(col, j) in criteria" :key="'c-' + i + '-' + j">
                                <td class="px-3 py-2 text-right border-l border-hairline font-mono-tabular">
                                    <template x-if="i === j">
                                        <span class="inline-block px-2 py-1 text-muted bg-canvas border border-hairline rounded-xs">1.0000</span>
                                    </template>
                                    <template x-if="i < j">
                                        <select :value="closestSaatyValue(matrix[i][j])"
                                                x-on:change="updatePair(i, j, parseFloat($event.target.value))"
                                                class="px-2 py-1 text-xs bg-surface border border-zinc-300 rounded-xs font-mono-tabular text-right focus:outline-none focus:border-zinc-950 cursor-pointer">
                                            <template x-for="opt in saatyOptions" :key="opt.label">
                                                <option :value="opt.value" :selected="Math.abs(matrix[i][j] - opt.value) < 0.001" x-text="opt.label"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="i > j">
                                        <span class="text-muted" x-text="Number(matrix[i][j]).toFixed(4)"></span>
                                    </template>
                                </td>
                            </template>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-hairline bg-canvas font-mono-tabular text-xs font-semibold text-ink">
                        <td class="px-3.5 py-2.5 font-sans text-[11px] uppercase tracking-wider text-muted">Jumlah Kolom (&Sigma; a<sub>ij</sub>)</td>
                        <template x-for="(sumVal, j) in calc.column_sums" :key="'cs-' + j">
                            <td class="px-3 py-2.5 text-right border-l border-hairline" x-text="Number(sumVal).toFixed(4)"></td>
                        </template>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-hairline bg-canvas flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[260px]">
                <label class="text-xs font-medium text-muted">Nama Sesi:</label>
                <input type="text"
                       x-model="sessionName"
                       placeholder="Misal: Kebijakan Kredit Q4 (Prioritas Legalitas)"
                       class="flex-1 min-w-[220px] max-w-md px-2.5 py-1.5 text-xs bg-surface border border-hairline rounded-sm focus:outline-none focus:border-zinc-900">
            </div>

            <div class="flex items-center gap-2">
                <span x-show="loadingPreview" x-cloak class="text-[11px] font-mono-tabular text-muted">Menghitung...</span>
                <button type="button"
                        x-on:click="applyMatrix()"
                        :disabled="!calc.is_consistent || applying"
                        class="px-3.5 py-1.5 rounded-sm text-xs font-semibold transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                        :class="calc.is_consistent ? 'bg-zinc-950 text-white hover:bg-zinc-800' : 'bg-rose-100 text-rose-800 border border-rose-300'">
                    <span x-show="!applying">Terapkan Bobot &amp; Hitung Ulang Pelanggan</span>
                    <span x-show="applying" x-cloak>Menyimpan &amp; Menghitung SAW...</span>
                </button>
            </div>
        </div>
    </section>

    {{-- Step 2 & Step 3: Normalized Matrix + Eigenvector + Consistency Vector --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        {{-- Normalized Matrix A' and Priority Weights w_i --}}
        <section class="lg:col-span-7 bg-surface border border-hairline rounded-md overflow-hidden">
            <div class="px-4 py-2.5 border-b border-hairline bg-subtle/40">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">Langkah 2 — Matriks Normalisasi (a'<sub>ij</sub> = a<sub>ij</sub> / &Sigma; a<sub>kj</sub>)</span>
                <h2 class="text-xs font-semibold text-ink">Matriks Normalisasi &amp; Vektor Bobot Prioritas (w<sub>i</sub>)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs border-collapse font-mono-tabular">
                    <thead>
                        <tr class="border-b border-hairline bg-canvas text-[10px] uppercase tracking-wider text-muted font-sans">
                            <th class="text-left font-semibold px-3.5 py-2">Kriteria</th>
                            <template x-for="col in criteria" :key="'nh-' + col.code">
                                <th class="text-right font-semibold px-2.5 py-2 font-mono-tabular" x-text="col.code"></th>
                            </template>
                            <th class="text-right font-semibold px-3.5 py-2 bg-subtle/60 text-ink">Bobot (w<sub>i</sub>)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        <template x-for="(row, i) in criteria" :key="'nr-' + row.code">
                            <tr class="hover:bg-zinc-50">
                                <td class="px-3.5 py-2 font-sans font-medium text-ink">
                                    <span class="font-mono-tabular font-semibold mr-1.5" x-text="row.code"></span>
                                    <span class="text-[11px] text-muted" x-text="row.name"></span>
                                </td>
                                <template x-for="(val, j) in calc.normalized_matrix[i]" :key="'nv-' + i + '-' + j">
                                    <td class="px-2.5 py-2 text-right border-l border-hairline text-ink" x-text="Number(val).toFixed(4)"></td>
                                </template>
                                <td class="px-3.5 py-2 text-right border-l border-hairline bg-subtle/40 font-semibold text-ink">
                                    <span x-text="Number(calc.weights[i]).toFixed(6)"></span>
                                    <span class="text-[10px] text-muted ml-1" x-text="'(' + (calc.weights[i] * 100).toFixed(1) + '%)'"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Weighted Sum & Consistency Vector + Deviation Diagnostics --}}
        <section class="lg:col-span-5 bg-surface border border-hairline rounded-md overflow-hidden flex flex-col justify-between">
            <div>
                <div class="px-4 py-2.5 border-b border-hairline bg-subtle/40">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">Langkah 3 — Vektor Konsistensi &amp; Diagnostik</span>
                    <h2 class="text-xs font-semibold text-ink">A&middot;w, &lambda;<sub>i</sub> = (A&middot;w)<sub>i</sub> / w<sub>i</sub>, serta Deviasi Pasangan</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse font-mono-tabular">
                        <thead>
                            <tr class="border-b border-hairline bg-canvas text-[10px] uppercase tracking-wider text-muted font-sans">
                                <th class="text-left font-semibold px-3.5 py-2">Kriteria</th>
                                <th class="text-right font-semibold px-3 py-2">Bobot w<sub>i</sub></th>
                                <th class="text-right font-semibold px-3 py-2">A&middot;w</th>
                                <th class="text-right font-semibold px-3.5 py-2">&lambda;<sub>i</sub></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline">
                            <template x-for="(row, i) in criteria" :key="'cv-' + row.code">
                                <tr>
                                    <td class="px-3.5 py-2 font-semibold text-ink" x-text="row.code"></td>
                                    <td class="px-3 py-2 text-right text-muted" x-text="Number(calc.weights[i]).toFixed(4)"></td>
                                    <td class="px-3 py-2 text-right text-ink" x-text="Number(calc.weighted_sums[i]).toFixed(4)"></td>
                                    <td class="px-3.5 py-2 text-right font-semibold text-ink" x-text="Number(calc.consistency_vector[i]).toFixed(4)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Inconsistency Deviation Diagnostics --}}
            <div class="border-t border-hairline p-3.5 bg-canvas space-y-2">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">
                    Pasangan dengan Deviasi Rasio Terbesar (Panduan Perbaikan Konsistensi)
                </div>
                <div class="space-y-1 text-[11px] font-mono-tabular">
                    <template x-for="(dev, idx) in calc.deviations.slice(0, 3)" :key="'dev-' + idx">
                        <div class="flex items-center justify-between px-2.5 py-1 bg-surface border border-hairline rounded-xs"
                             :class="!calc.is_consistent && idx === 0 ? 'border-rose-400 bg-rose-50/70 text-rose-900' : 'text-ink'">
                            <span>
                                <strong x-text="criteria[dev.row_index].code"></strong> vs <strong x-text="criteria[dev.col_index].code"></strong>
                                <span class="text-muted ml-1" x-text="'(input: ' + Number(dev.actual_value).toFixed(2) + ', rasio w: ' + Number(dev.implied_ratio).toFixed(2) + ')'"></span>
                            </span>
                            <span class="font-semibold" x-text="Number(dev.relative_error).toFixed(2) + 'x'"></span>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    </div>

    {{-- Criteria Reference & Rubric + Session History --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <section class="lg:col-span-8 bg-surface border border-hairline rounded-md overflow-hidden">
            <div class="px-4 py-2.5 border-b border-hairline bg-subtle/40">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">Referensi Pemetaan Data-to-Score</span>
                <h2 class="text-xs font-semibold text-ink">Definisi Kriteria, Kolom Sumber CSV &amp; Skala Penilaian (C1–C5)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-hairline bg-canvas text-[10px] uppercase tracking-wider text-muted">
                            <th class="text-left font-semibold px-3.5 py-2">Kode &amp; Sifat</th>
                            <th class="text-left font-semibold px-3 py-2">Kriteria</th>
                            <th class="text-left font-semibold px-3 py-2">Kolom CSV</th>
                            <th class="text-left font-semibold px-3.5 py-2">Rubrik Skala (1–5)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @foreach ($criteria as $criterion)
                            <tr>
                                <td class="px-3.5 py-2.5 align-top whitespace-nowrap">
                                    <span class="font-mono-tabular font-semibold text-ink">{{ $criterion->code }}</span>
                                    <span class="ml-1 text-[10px] font-mono-tabular uppercase px-1.5 py-0.5 rounded-xs border border-hairline text-muted">{{ $criterion->type }}</span>
                                </td>
                                <td class="px-3 py-2.5 align-top">
                                    <div class="font-semibold text-ink">{{ $criterion->name }}</div>
                                    <div class="text-[11px] text-muted mt-0.5">{{ $criterion->description }}</div>
                                </td>
                                <td class="px-3 py-2.5 align-top font-mono-tabular text-[11px] text-muted">
                                    {{ implode(', ', $criterion->source_columns ?? []) }}
                                </td>
                                <td class="px-3.5 py-2.5 align-top text-[11px] space-y-0.5">
                                    @foreach (($criterion->rubric ?? []) as $scoreKey => $desc)
                                        <div>
                                            <span class="font-mono-tabular font-semibold text-ink">{{ $scoreKey }}:</span>
                                            <span class="text-muted">{{ $desc }}</span>
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="lg:col-span-4 bg-surface border border-hairline rounded-md overflow-hidden">
            <div class="px-4 py-2.5 border-b border-hairline bg-subtle/40">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">Audit Log</span>
                <h2 class="text-xs font-semibold text-ink">Riwayat Sesi Bobot AHP</h2>
            </div>
            <div class="divide-y divide-hairline">
                @foreach ($sessionHistory as $hist)
                    <div class="px-3.5 py-2.5 text-xs flex items-center justify-between gap-2 {{ $hist->is_active ? 'bg-canvas' : '' }}">
                        <div class="min-w-0">
                            <div class="font-medium text-ink truncate">{{ $hist->name }}</div>
                            <div class="text-[11px] font-mono-tabular text-muted">
                                CR: {{ number_format($hist->cr, 4) }} &bull; &lambda;<sub>max</sub>: {{ number_format($hist->lambda_max, 4) }}
                            </div>
                        </div>
                        @if ($hist->is_active)
                            <span class="px-1.5 py-0.5 text-[10px] font-mono-tabular status-net30 rounded-xs shrink-0">AKTIF</span>
                        @else
                            <span class="px-1.5 py-0.5 text-[10px] font-mono-tabular border border-hairline text-muted rounded-xs shrink-0">ARSIP</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</div>

<script>
    window.__AHP_INITIAL_CALC__ = @js($ahpCalculation);
    window.__AHP_CRITERIA__ = @js($criteriaPayload);

    function ahpInspector() {
        const initialCalc = window.__AHP_INITIAL_CALC__;
        return {
            criteria: window.__AHP_CRITERIA__ || [],
            savedMatrix: JSON.parse(JSON.stringify(initialCalc.raw_matrix)),
            matrix: JSON.parse(JSON.stringify(initialCalc.raw_matrix)),
            calc: initialCalc,
            sessionName: '',
            loadingPreview: false,
            applying: false,
            applyMessage: null,
            applyError: false,
            saatyOptions: [
                { label: '9 (Mutlak lebih penting)', value: 9 },
                { label: '8', value: 8 },
                { label: '7 (Sangat kuat)', value: 7 },
                { label: '6', value: 6 },
                { label: '5 (Kuat lebih penting)', value: 5 },
                { label: '4', value: 4 },
                { label: '3 (Sedikit lebih penting)', value: 3 },
                { label: '2', value: 2 },
                { label: '1 (Sama penting)', value: 1 },
                { label: '1/2 (0.50)', value: 0.5 },
                { label: '1/3 (0.33)', value: 1 / 3 },
                { label: '1/4 (0.25)', value: 0.25 },
                { label: '1/5 (0.20)', value: 0.2 },
                { label: '1/6 (0.17)', value: 1 / 6 },
                { label: '1/7 (0.14)', value: 1 / 7 },
                { label: '1/8 (0.13)', value: 0.125 },
                { label: '1/9 (0.11)', value: 1 / 9 },
            ],

            closestSaatyValue(val) {
                let best = this.saatyOptions[0].value;
                let minDiff = Infinity;
                for (const opt of this.saatyOptions) {
                    const diff = Math.abs(val - opt.value);
                    if (diff < minDiff) {
                        minDiff = diff;
                        best = opt.value;
                    }
                }
                return best;
            },

            updatePair(i, j, val) {
                this.matrix[i][j] = val;
                this.matrix[j][i] = 1 / val;
                this.previewMatrix();
            },

            loadSavedMatrix() {
                this.matrix = JSON.parse(JSON.stringify(this.savedMatrix));
                this.applyMessage = null;
                this.previewMatrix();
            },

            loadInconsistentDemo() {
                const pairs = {
                    '0-1': 9, '0-2': 1 / 9, '0-3': 5, '0-4': 1,
                    '1-2': 9, '1-3': 1, '1-4': 7,
                    '2-3': 1 / 5, '2-4': 1 / 3,
                    '3-4': 5
                };
                for (let i = 0; i < 5; i++) {
                    for (let j = 0; j < 5; j++) {
                        if (i === j) {
                            this.matrix[i][j] = 1;
                        } else if (i < j) {
                            const v = pairs[`${i}-${j}`] ?? 1;
                            this.matrix[i][j] = v;
                            this.matrix[j][i] = 1 / v;
                        }
                    }
                }
                this.applyMessage = 'Simulasi matriks kontradiktif dimuat: perhatikan CR melebihi 0.10 dan tombol Simpan otomatis terkunci.';
                this.applyError = true;
                this.previewMatrix();
            },

            async previewMatrix() {
                this.loadingPreview = true;
                try {
                    const response = await fetch('{{ route('spk.ahp.preview') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify({ matrix: this.matrix }),
                    });
                    if (response.ok) {
                        this.calc = await response.json();
                    }
                } finally {
                    this.loadingPreview = false;
                }
            },

            async applyMatrix() {
                if (!this.calc.is_consistent || this.applying) return;
                this.applying = true;
                this.applyMessage = null;
                this.applyError = false;

                try {
                    const response = await fetch('{{ route('spk.ahp.apply') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify({
                            matrix: this.matrix,
                            session_name: this.sessionName,
                        }),
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        this.applyError = true;
                        this.applyMessage = data.message || 'Gagal menyimpan bobot AHP.';
                        return;
                    }
                    this.savedMatrix = JSON.parse(JSON.stringify(this.matrix));
                    this.applyMessage = data.message;
                    this.applyError = false;
                } catch (err) {
                    this.applyError = true;
                    this.applyMessage = 'Terjadi kesalahan jaringan saat menyimpan bobot AHP.';
                } finally {
                    this.applying = false;
                }
            }
        };
    }
</script>
@endsection
