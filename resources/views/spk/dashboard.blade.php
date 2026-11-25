@extends('layouts.app')

@section('title', 'Portfolio & Drilldown Matrix — SPK Kredit B2B (AHP & SAW)')

@section('header_status')
    <div class="hidden md:flex items-center gap-2 px-2.5 py-1 rounded-sm border border-hairline bg-canvas text-[11px] font-mono-tabular">
        <span class="inline-block w-1.5 h-1.5 rounded-full {{ $activeSession->is_consistent ? 'bg-emerald-600' : 'bg-rose-600' }}"></span>
        <span class="text-muted">AHP CR:</span>
        <span class="font-semibold text-ink">{{ number_format($activeSession->cr, 4) }}</span>
        <span class="text-zinc-300">|</span>
        <span class="font-semibold text-ink">{{ number_format($macro['total'], 0, ',', '.') }}</span>
        <span class="text-muted">ENTITAS</span>
    </div>
@endsection

@section('nav_actions')
    <a href="{{ route('spk.export.csv', request()->query()) }}"
       class="px-2.5 py-1.5 text-xs font-medium rounded-sm border border-hairline bg-surface hover:bg-subtle text-ink transition-colors">
        Ekspor Hasil CSV
    </a>
    <button type="button"
            onclick="window.dispatchEvent(new CustomEvent('open-csv-modal'))"
            class="px-2.5 py-1.5 text-xs font-medium rounded-sm border border-hairline bg-surface hover:bg-subtle text-ink transition-colors cursor-pointer">
        Impor / Sinkronkan CSV
    </button>
@endsection

@section('content')
@php
    $pageCustomersPayload = $assessments->getCollection()->map(function ($a) {
        return [
            'id' => $a->customer->id,
            'code' => $a->customer->customer_code,
            'name' => $a->customer->customer_name,
            'entity' => strtoupper($a->customer->business_entity ?: '-'),
            'entity_name' => $a->customer->business_entity_name,
            'customer_type' => strtoupper($a->customer->customer_type ?: '-'),
            'current_term' => $a->customer->current_payment_term ?: '-',
            'rank' => $a->rank,
            'preference_score' => (float) $a->preference_score,
            'recommended_top' => $a->recommended_top,
            'scores' => ['C1' => $a->c1_score, 'C2' => $a->c2_score, 'C3' => $a->c3_score, 'C4' => $a->c4_score, 'C5' => $a->c5_score],
            'normalized' => ['C1' => (float) $a->r1, 'C2' => (float) $a->r2, 'C3' => (float) $a->r3, 'C4' => (float) $a->r4, 'C5' => (float) $a->r5],
            'weighted' => ['C1' => (float) $a->w1_r1, 'C2' => (float) $a->w2_r2, 'C3' => (float) $a->w3_r3, 'C4' => (float) $a->w4_r4, 'C5' => (float) $a->w5_r5],
        ];
    })->values();
@endphp

<div x-data="spkDashboard()"
     x-on:keydown.window="handleGlobalKeydown($event)"
     x-on:open-csv-modal.window="csvModalOpen = true"
     class="space-y-5">

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

    {{-- LEVEL 0: EXECUTIVE PORTFOLIO OVERVIEW (MACRO LEVEL) --}}
    <section aria-label="Level 0 Executive Portfolio Overview" class="space-y-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-wider text-muted">Level 0 — Portfolio Macro Verdict</p>
                <h1 class="text-lg font-bold tracking-tight text-ink">Ikhtisar Kelayakan Kredit Pelanggan B2B</h1>
            </div>
            <div class="text-xs text-muted flex items-center gap-3">
                <span>Sesi AHP Aktif: <strong class="text-ink font-medium">{{ $activeSession->name }}</strong></span>
                <a href="{{ route('spk.ahp.inspector') }}" class="underline decoration-zinc-300 hover:decoration-zinc-900 text-ink font-medium">
                    Inspeksi Bobot AHP &rarr;
                </a>
            </div>
        </div>

        {{-- Compact 5-Column Structural Ribbon --}}
        <div class="bg-surface border border-hairline rounded-md grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 divide-y sm:divide-y-0 sm:divide-x divide-hairline">
            <div class="p-3.5 flex flex-col justify-between">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Populasi Teranalisis</div>
                <div class="mt-1.5 flex items-baseline justify-between gap-2">
                    <span class="text-2xl font-semibold font-mono-tabular text-ink">{{ number_format($macro['total'], 0, ',', '.') }}</span>
                    <span class="text-[11px] font-mono-tabular text-muted">Avg V: {{ number_format($macro['avg_score'], 4) }}</span>
                </div>
                <div class="mt-2 pt-2 border-t border-hairline flex items-center justify-between text-[11px] font-mono-tabular text-muted">
                    <span>Min {{ number_format($macro['min_score'], 4) }}</span>
                    <span>Max {{ number_format($macro['max_score'], 4) }}</span>
                </div>
            </div>

            <a href="{{ route('spk.dashboard', ['top' => 'NET_30']) }}"
               class="p-3.5 flex flex-col justify-between hover:bg-emerald-50/40 transition-colors {{ $filters['top'] === 'NET_30' ? 'bg-emerald-50/60 ring-1 ring-inset ring-emerald-500' : '' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-emerald-800">Layak Kredit · Net 30</span>
                    <span class="text-[10px] font-mono-tabular text-emerald-700">V &ge; 0.80</span>
                </div>
                <div class="mt-1.5 flex items-baseline justify-between gap-2">
                    <span class="text-2xl font-semibold font-mono-tabular text-emerald-800">{{ number_format($macro['net_30'], 0, ',', '.') }}</span>
                    <span class="text-xs font-mono-tabular font-medium text-emerald-700">{{ number_format($macro['net_30_pct'], 1) }}%</span>
                </div>
                <div class="mt-2 h-1.5 w-full bg-zinc-100 overflow-hidden">
                    <div class="h-full bg-emerald-600" style="width: {{ min(100, $macro['net_30_pct']) }}%"></div>
                </div>
            </a>

            <a href="{{ route('spk.dashboard', ['top' => 'NET_14']) }}"
               class="p-3.5 flex flex-col justify-between hover:bg-amber-50/40 transition-colors {{ $filters['top'] === 'NET_14' ? 'bg-amber-50/60 ring-1 ring-inset ring-amber-500' : '' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-amber-800">Bersyarat · Net 14</span>
                    <span class="text-[10px] font-mono-tabular text-amber-700">0.60–0.79</span>
                </div>
                <div class="mt-1.5 flex items-baseline justify-between gap-2">
                    <span class="text-2xl font-semibold font-mono-tabular text-amber-800">{{ number_format($macro['net_14'], 0, ',', '.') }}</span>
                    <span class="text-xs font-mono-tabular font-medium text-amber-700">{{ number_format($macro['net_14_pct'], 1) }}%</span>
                </div>
                <div class="mt-2 h-1.5 w-full bg-zinc-100 overflow-hidden">
                    <div class="h-full bg-amber-600" style="width: {{ min(100, $macro['net_14_pct']) }}%"></div>
                </div>
            </a>

            <a href="{{ route('spk.dashboard', ['top' => 'CBD']) }}"
               class="p-3.5 flex flex-col justify-between hover:bg-rose-50/40 transition-colors {{ $filters['top'] === 'CBD' ? 'bg-rose-50/60 ring-1 ring-inset ring-rose-500' : '' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-rose-800">Wajib Tunai · CBD</span>
                    <span class="text-[10px] font-mono-tabular text-rose-700">V &lt; 0.60</span>
                </div>
                <div class="mt-1.5 flex items-baseline justify-between gap-2">
                    <span class="text-2xl font-semibold font-mono-tabular text-rose-800">{{ number_format($macro['cbd'], 0, ',', '.') }}</span>
                    <span class="text-xs font-mono-tabular font-medium text-rose-700">{{ number_format($macro['cbd_pct'], 1) }}%</span>
                </div>
                <div class="mt-2 h-1.5 w-full bg-zinc-100 overflow-hidden">
                    <div class="h-full bg-rose-600" style="width: {{ min(100, $macro['cbd_pct']) }}%"></div>
                </div>
            </a>

            <div class="p-3.5 flex flex-col justify-between">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Audit Gap Kebijakan</div>
                <div class="mt-1.5 space-y-1.5">
                    <a href="{{ route('spk.dashboard', ['policy_gap' => 'upgrade_candidate']) }}"
                       class="flex items-center justify-between px-2 py-1 rounded-xs border text-[11px] transition-colors {{ $filters['policy_gap'] === 'upgrade_candidate' ? 'border-emerald-600 bg-emerald-50 text-emerald-900 font-semibold' : 'border-hairline hover:bg-subtle text-ink' }}">
                        <span>CBD &rarr; Layak Kredit</span>
                        <span class="font-mono-tabular font-semibold text-emerald-700">{{ number_format($macro['upgrade_candidates'], 0, ',', '.') }}</span>
                    </a>
                    <a href="{{ route('spk.dashboard', ['policy_gap' => 'risk_exposure']) }}"
                       class="flex items-center justify-between px-2 py-1 rounded-xs border text-[11px] transition-colors {{ $filters['policy_gap'] === 'risk_exposure' ? 'border-rose-600 bg-rose-50 text-rose-900 font-semibold' : 'border-hairline hover:bg-subtle text-ink' }}">
                        <span>Tempo &rarr; SPK CBD</span>
                        <span class="font-mono-tabular font-semibold text-rose-700">{{ number_format($macro['risk_exposures'], 0, ',', '.') }}</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Interactive Cross-Tabulation Drilldown Matrices --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            {{-- Matrix A: C1 Business Entity x Recommended ToP --}}
            <div class="lg:col-span-7 bg-surface border border-hairline rounded-md overflow-hidden">
                <div class="px-3.5 py-2.5 border-b border-hairline flex items-center justify-between bg-subtle/40">
                    <div>
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">Drilldown Kohort A</span>
                        <h2 class="text-xs font-semibold text-ink">Bentuk Badan Usaha (C1) &times; Rekomendasi Term of Payment</h2>
                    </div>
                    <span class="text-[11px] text-muted">Klik sel angka untuk menyaring tabel</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-hairline bg-canvas text-[10px] uppercase tracking-wider text-muted">
                                <th class="text-left font-semibold px-3.5 py-2">Entitas (Skor C1)</th>
                                <th class="text-right font-semibold px-3 py-2 text-emerald-800">Net 30</th>
                                <th class="text-right font-semibold px-3 py-2 text-amber-800">Net 14</th>
                                <th class="text-right font-semibold px-3 py-2 text-rose-800">CBD</th>
                                <th class="text-right font-semibold px-3.5 py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline">
                            @foreach ($matrices['entity_vs_top'] as $c1Score => $row)
                                <tr class="hover:bg-zinc-50/70">
                                    <td class="px-3.5 py-2 font-medium text-ink">
                                        <a href="{{ route('spk.dashboard', ['c1' => $c1Score]) }}" class="hover:underline flex items-center gap-2">
                                            <span class="font-mono-tabular text-[11px] px-1.5 py-0.5 bg-subtle border border-hairline rounded-xs text-muted">{{ $c1Score }}</span>
                                            <span>{{ $row['label'] }}</span>
                                        </a>
                                    </td>
                                    @foreach (['NET_30' => 'emerald', 'NET_14' => 'amber', 'CBD' => 'rose'] as $topKey => $tone)
                                        @php
                                            $cnt = $row[$topKey];
                                            $isActiveCell = (string) $filters['c1'] === (string) $c1Score && $filters['top'] === $topKey;
                                            $cellBg = $cnt > 0
                                                ? ($tone === 'emerald' ? 'bg-emerald-50/70 text-emerald-900 hover:bg-emerald-100/80' : ($tone === 'amber' ? 'bg-amber-50/70 text-amber-900 hover:bg-amber-100/80' : 'bg-rose-50/60 text-rose-900 hover:bg-rose-100/70'))
                                                : 'text-zinc-300';
                                        @endphp
                                        <td class="p-0 text-right border-l border-hairline">
                                            @if ($cnt > 0)
                                                <a href="{{ route('spk.dashboard', ['c1' => $c1Score, 'top' => $topKey]) }}"
                                                   class="block px-3 py-2 font-mono-tabular transition-colors {{ $cellBg }} {{ $isActiveCell ? 'ring-2 ring-inset ring-zinc-950 font-semibold' : '' }}">
                                                    {{ number_format($cnt, 0, ',', '.') }}
                                                </a>
                                            @else
                                                <span class="block px-3 py-2 font-mono-tabular {{ $cellBg }}">0</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-3.5 py-2 text-right font-mono-tabular font-semibold text-ink border-l border-hairline bg-canvas/50">
                                        {{ number_format($row['total'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Matrix B: C4 Customer Type x C5 Fraud Anomaly Risk --}}
            <div class="lg:col-span-5 bg-surface border border-hairline rounded-md overflow-hidden flex flex-col justify-between">
                <div>
                    <div class="px-3.5 py-2.5 border-b border-hairline flex items-center justify-between bg-subtle/40">
                        <div>
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">Drilldown Kohort B</span>
                            <h2 class="text-xs font-semibold text-ink">Tipe Pelanggan (C4) &times; Risiko Anomali (C5)</h2>
                        </div>
                        <span class="text-[11px] font-mono-tabular text-muted">Cost Attribute</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-hairline bg-canvas text-[10px] uppercase tracking-wider text-muted">
                                    <th class="text-left font-semibold px-3.5 py-2">Tipe (C4)</th>
                                    <th class="text-right font-semibold px-3 py-2 text-emerald-800">Rendah (1)</th>
                                    <th class="text-right font-semibold px-3 py-2 text-amber-800">Sedang (3)</th>
                                    <th class="text-right font-semibold px-3 py-2 text-rose-800">Tinggi (5)</th>
                                    <th class="text-right font-semibold px-3.5 py-2">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-hairline">
                                @foreach ($matrices['type_vs_fraud'] as $c4Score => $row)
                                    <tr class="hover:bg-zinc-50/70">
                                        <td class="px-3.5 py-2.5 font-medium text-ink">
                                            <a href="{{ route('spk.dashboard', ['c4' => $c4Score]) }}" class="hover:underline">
                                                {{ $row['label'] }}
                                            </a>
                                        </td>
                                        @foreach ([1 => 'emerald', 3 => 'amber', 5 => 'rose'] as $c5Score => $tone)
                                            @php
                                                $cnt = $row[$c5Score];
                                                $isActiveCell = (string) $filters['c4'] === (string) $c4Score && (string) $filters['c5'] === (string) $c5Score;
                                                $cellBg = $cnt > 0
                                                    ? ($tone === 'emerald' ? 'bg-emerald-50/70 text-emerald-900 hover:bg-emerald-100/80' : ($tone === 'amber' ? 'bg-amber-50/70 text-amber-900 hover:bg-amber-100/80' : 'bg-rose-50/60 text-rose-900 hover:bg-rose-100/70'))
                                                    : 'text-zinc-300';
                                            @endphp
                                            <td class="p-0 text-right border-l border-hairline">
                                                <a href="{{ route('spk.dashboard', ['c4' => $c4Score, 'c5' => $c5Score]) }}"
                                                   class="block px-3 py-2.5 font-mono-tabular transition-colors {{ $cellBg }} {{ $isActiveCell ? 'ring-2 ring-inset ring-zinc-950 font-semibold' : '' }}">
                                                    {{ number_format($cnt, 0, ',', '.') }}
                                                </a>
                                            </td>
                                        @endforeach
                                        <td class="px-3.5 py-2.5 text-right font-mono-tabular font-semibold text-ink border-l border-hairline bg-canvas/50">
                                            {{ number_format($row['total'], 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- AHP Weight Distribution Strip --}}
                <div class="px-3.5 py-2.5 border-t border-hairline bg-canvas/70">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-muted mb-1.5">Bobot Prioritas AHP Aktif (Total = 1.0000)</div>
                    <div class="grid grid-cols-5 gap-1.5 text-[11px] font-mono-tabular">
                        @foreach ($criteria as $criterion)
                            <div class="px-2 py-1 bg-surface border border-hairline rounded-xs">
                                <div class="text-[10px] text-muted">{{ $criterion->code }} ({{ $criterion->type === 'cost' ? 'Cost' : 'Ben' }})</div>
                                <div class="font-semibold text-ink">{{ number_format($criterion->weight * 100, 1) }}%</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- LEVEL 1: MASTER RANKING & COHORT MATRIX (MESO LEVEL) --}}
    <section aria-label="Level 1 Master Ranking Matrix" class="bg-surface border border-hairline rounded-md overflow-hidden">
        {{-- Breadcrumb & Active Filters Bar --}}
        <div class="px-4 py-2.5 border-b border-hairline bg-subtle/40 flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-muted mr-1">Level 1 — Matriks Perankingan SAW:</span>
                @if (count($activeFilterBreadcrumbs) === 0)
                    <span class="px-2 py-0.5 rounded-xs bg-surface border border-hairline text-ink font-medium">Semua Pelanggan ({{ number_format($assessments->total(), 0, ',', '.') }})</span>
                @else
                    @foreach ($activeFilterBreadcrumbs as $crumb)
                        <a href="{{ $crumb['remove_url'] }}"
                           class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-xs bg-zinc-900 text-white hover:bg-zinc-700 transition-colors">
                            <span>{{ $crumb['label'] }}</span>
                            <span class="font-mono-tabular text-[10px] opacity-80">&times;</span>
                        </a>
                    @endforeach
                    <a href="{{ route('spk.dashboard') }}" class="ml-1 text-[11px] text-muted hover:text-ink underline">
                        Reset Filter
                    </a>
                @endif
            </div>

            {{-- Matrix Cell Mode Switcher & Compare Trigger --}}
            <div class="flex items-center gap-2">
                <button type="button"
                        x-show="compareList.length >= 2"
                        x-cloak
                        x-on:click="compareModalOpen = true"
                        class="px-2.5 py-1 rounded-xs bg-zinc-950 text-white text-xs font-medium hover:bg-zinc-800 transition-colors cursor-pointer">
                    Bandingkan (<span class="font-mono-tabular" x-text="compareList.length"></span>)
                </button>

                <div class="inline-flex items-center bg-subtle p-0.5 rounded-sm border border-hairline text-[11px]" role="group" aria-label="Mode tampilan sel kriteria">
                    <button type="button"
                            x-on:click="matrixMode = 'raw'"
                            :class="matrixMode === 'raw' ? 'bg-surface text-ink font-semibold border border-hairline' : 'text-muted hover:text-ink border border-transparent'"
                            class="px-2 py-1 rounded-xs transition-colors cursor-pointer">
                        Skor x<sub>ij</sub>
                    </button>
                    <button type="button"
                            x-on:click="matrixMode = 'normalized'"
                            :class="matrixMode === 'normalized' ? 'bg-surface text-ink font-semibold border border-hairline' : 'text-muted hover:text-ink border border-transparent'"
                            class="px-2 py-1 rounded-xs transition-colors cursor-pointer">
                        Norm r<sub>ij</sub>
                    </button>
                    <button type="button"
                            x-on:click="matrixMode = 'weighted'"
                            :class="matrixMode === 'weighted' ? 'bg-surface text-ink font-semibold border border-hairline' : 'text-muted hover:text-ink border border-transparent'"
                            class="px-2 py-1 rounded-xs transition-colors cursor-pointer">
                        Terbobot w<sub>j</sub>&middot;r<sub>ij</sub>
                    </button>
                </div>
            </div>
        </div>

        {{-- Unified Page-Level Filter Toolbar --}}
        <form method="GET" action="{{ route('spk.dashboard') }}" class="px-4 py-2.5 border-b border-hairline bg-surface flex flex-wrap items-center gap-2 text-xs">
            <div class="relative flex-1 min-w-[220px]">
                <input type="text"
                       name="search"
                       x-ref="searchInput"
                       value="{{ $filters['search'] }}"
                       placeholder="Cari kode, nama perusahaan, PIC, NPWP, email... (tekan /)"
                       class="w-full pl-2.5 pr-7 py-1.5 text-xs bg-canvas border border-hairline rounded-sm focus:outline-none focus:border-zinc-900">
                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-mono-tabular text-muted border border-hairline px-1 rounded-xs bg-surface">/</span>
            </div>

            <select name="top" class="py-1.5 px-2 text-xs bg-canvas border border-hairline rounded-sm focus:outline-none focus:border-zinc-900">
                <option value="">Semua ToP</option>
                <option value="NET_30" @selected($filters['top'] === 'NET_30')>NET_30 (Layak)</option>
                <option value="NET_14" @selected($filters['top'] === 'NET_14')>NET_14 (Bersyarat)</option>
                <option value="CBD" @selected($filters['top'] === 'CBD')>CBD (Tunai)</option>
            </select>

            <select name="c1" class="py-1.5 px-2 text-xs bg-canvas border border-hairline rounded-sm focus:outline-none focus:border-zinc-900">
                <option value="">C1: Badan Usaha</option>
                @foreach ($entityLabels as $score => $label)
                    <option value="{{ $score }}" @selected((string) $filters['c1'] === (string) $score)>{{ $score }}: {{ $label }}</option>
                @endforeach
            </select>

            <select name="c4" class="py-1.5 px-2 text-xs bg-canvas border border-hairline rounded-sm focus:outline-none focus:border-zinc-900">
                <option value="">C4: Tipe</option>
                <option value="5" @selected((string) $filters['c4'] === '5')>5: Reseller</option>
                <option value="3" @selected((string) $filters['c4'] === '3')>3: Enduser/Lainnya</option>
            </select>

            <select name="c5" class="py-1.5 px-2 text-xs bg-canvas border border-hairline rounded-sm focus:outline-none focus:border-zinc-900">
                <option value="">C5: Risiko Anomali</option>
                @foreach ($fraudLabels as $score => $label)
                    <option value="{{ $score }}" @selected((string) $filters['c5'] === (string) $score)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="policy_gap" class="py-1.5 px-2 text-xs bg-canvas border border-hairline rounded-sm focus:outline-none focus:border-zinc-900">
                <option value="">Semua Status Audit</option>
                <option value="upgrade_candidate" @selected($filters['policy_gap'] === 'upgrade_candidate')>Kandidat Upgrade (CBD &rarr; Kredit)</option>
                <option value="risk_exposure" @selected($filters['policy_gap'] === 'risk_exposure')>Eksposur Risiko (Tempo &rarr; CBD)</option>
            </select>

            <select name="per_page" class="py-1.5 px-2 text-xs bg-canvas border border-hairline rounded-sm font-mono-tabular focus:outline-none focus:border-zinc-900">
                <option value="25" @selected((int) $filters['per_page'] === 25)>25 / hal</option>
                <option value="50" @selected((int) $filters['per_page'] === 50)>50 / hal</option>
                <option value="100" @selected((int) $filters['per_page'] === 100)>100 / hal</option>
            </select>

            <button type="submit" class="px-3 py-1.5 bg-zinc-950 text-white rounded-sm text-xs font-medium hover:bg-zinc-800 transition-colors cursor-pointer">
                Terapkan
            </button>
        </form>

        {{-- Master Data Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="border-b border-hairline bg-canvas text-[10px] uppercase tracking-wider text-muted">
                        <th class="w-9 px-3 py-2.5 text-center">Cmp</th>
                        <th class="px-3 py-2.5 text-right">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'rank', 'dir' => ($sortKey === 'rank' && $sortDir === 'asc') ? 'desc' : 'asc']) }}" class="hover:text-ink">
                                Rank {!! $sortKey === 'rank' ? ($sortDir === 'asc' ? '&uarr;' : '&darr;') : '' !!}
                            </a>
                        </th>
                        <th class="px-3 py-2.5 text-left">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'customer_name', 'dir' => ($sortKey === 'customer_name' && $sortDir === 'asc') ? 'desc' : 'asc']) }}" class="hover:text-ink">
                                Pelanggan / Entitas {!! $sortKey === 'customer_name' ? ($sortDir === 'asc' ? '&uarr;' : '&darr;') : '' !!}
                            </a>
                        </th>
                        <th class="px-3 py-2.5 text-left">Term Saat Ini</th>
                        @foreach ($criteria as $criterion)
                            @php $cKey = strtolower($criterion->code); @endphp
                            <th class="px-2.5 py-2.5 text-right whitespace-nowrap">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => $cKey, 'dir' => ($sortKey === $cKey && $sortDir === 'desc') ? 'asc' : 'desc']) }}" class="hover:text-ink">
                                    {{ $criterion->code }} <span class="font-mono-tabular opacity-70">{{ number_format($criterion->weight * 100, 1) }}%</span>
                                </a>
                            </th>
                        @endforeach
                        <th class="px-3 py-2.5 text-right">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'preference_score', 'dir' => ($sortKey === 'preference_score' && $sortDir === 'desc') ? 'asc' : 'desc']) }}" class="hover:text-ink">
                                Skor V<sub>i</sub> {!! $sortKey === 'preference_score' ? ($sortDir === 'asc' ? '&uarr;' : '&darr;') : '' !!}
                            </a>
                        </th>
                        <th class="px-3 py-2.5 text-left">Rekomendasi ToP</th>
                        <th class="px-3 py-2.5 text-right">Drilldown</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse ($assessments as $assessment)
                        @php
                            $cust = $assessment->customer;
                            $badgeClass = match ($assessment->recommended_top) {
                                'NET_30' => 'status-net30',
                                'NET_14' => 'status-net14',
                                default => 'status-cbd',
                            };
                            $badgeLabel = $topLabels[$assessment->recommended_top] ?? $assessment->recommended_top;
                            $isGapUpgrade = strtoupper(trim((string) $cust->current_payment_term)) === 'CBD' && in_array($assessment->recommended_top, ['NET_30', 'NET_14'], true);
                            $isGapExposure = $cust->current_payment_term && !in_array(strtoupper(trim((string) $cust->current_payment_term)), ['CBD', '0 DAYS'], true) && $assessment->recommended_top === 'CBD';
                        @endphp
                        <tr class="hover:bg-zinc-50 transition-colors cursor-pointer"
                            x-on:click="openDossier({{ $cust->id }})"
                            :class="activeCustomerId === {{ $cust->id }} ? 'bg-zinc-100/80' : ''">
                            <td class="px-3 py-2 text-center" x-on:click.stop>
                                <input type="checkbox"
                                       :checked="isCompared({{ $cust->id }})"
                                       x-on:change="toggleCompare({{ $cust->id }})"
                                       aria-label="Pilih {{ $cust->customer_name }} untuk dibandingkan"
                                       class="rounded-xs border-zinc-300 text-zinc-950 focus:ring-0 cursor-pointer">
                            </td>
                            <td class="px-3 py-2 text-right font-mono-tabular font-semibold text-ink whitespace-nowrap">
                                #{{ $assessment->rank }}
                            </td>
                            <td class="px-3 py-2 max-w-[300px]">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono-tabular text-[11px] text-muted">{{ $cust->customer_code }}</span>
                                    <span class="font-semibold text-ink truncate">{{ $cust->customer_name }}</span>
                                </div>
                                <div class="text-[11px] text-muted truncate">
                                    {{ strtoupper($cust->business_entity ?: '-') }} &middot; {{ strtoupper($cust->customer_type ?: '-') }}
                                    @if ($cust->pic)
                                        &middot; PIC: {{ $cust->pic }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="font-mono-tabular text-[11px] text-muted">{{ $cust->current_payment_term ?: '-' }}</span>
                                @if ($isGapUpgrade)
                                    <span class="ml-1 inline-block px-1 py-0.5 text-[9px] font-mono-tabular uppercase bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xs">UPGRADE</span>
                                @elseif ($isGapExposure)
                                    <span class="ml-1 inline-block px-1 py-0.5 text-[9px] font-mono-tabular uppercase bg-rose-50 text-rose-700 border border-rose-200 rounded-xs">EKSPOSUR</span>
                                @endif
                            </td>

                            @foreach (['C1' => ['c1_score', 'r1', 'w1_r1'], 'C2' => ['c2_score', 'r2', 'w2_r2'], 'C3' => ['c3_score', 'r3', 'w3_r3'], 'C4' => ['c4_score', 'r4', 'w4_r4'], 'C5' => ['c5_score', 'r5', 'w5_r5']] as $code => [$rawCol, $normCol, $weightCol])
                                <td class="px-2.5 py-2 text-right font-mono-tabular whitespace-nowrap {{ $code === 'C5' && $assessment->$rawCol === 5 ? 'text-rose-700 font-semibold' : 'text-ink' }}">
                                    <span x-show="matrixMode === 'raw'">{{ $assessment->$rawCol }}</span>
                                    <span x-show="matrixMode === 'normalized'" x-cloak>{{ number_format($assessment->$normCol, 4) }}</span>
                                    <span x-show="matrixMode === 'weighted'" x-cloak>{{ number_format($assessment->$weightCol, 4) }}</span>
                                </td>
                            @endforeach

                            <td class="px-3 py-2 text-right font-mono-tabular whitespace-nowrap">
                                <div class="font-semibold text-ink">{{ number_format($assessment->preference_score, 4) }}</div>
                                <div class="mt-0.5 ml-auto w-14 h-1 bg-zinc-100 overflow-hidden">
                                    <div class="h-full {{ $assessment->recommended_top === 'NET_30' ? 'bg-emerald-600' : ($assessment->recommended_top === 'NET_14' ? 'bg-amber-600' : 'bg-rose-600') }}"
                                         style="width: {{ min(100, round($assessment->preference_score * 100)) }}%"></div>
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-xs text-[11px] font-semibold font-mono-tabular {{ $badgeClass }}">
                                    {{ $badgeLabel }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <button type="button"
                                        x-on:click.stop="openDossier({{ $cust->id }})"
                                        class="text-[11px] font-medium text-ink hover:underline cursor-pointer">
                                    Dossier &rarr;
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-4 py-10 text-center text-xs text-muted">
                                Tidak ada pelanggan yang cocok dengan kriteria filter drilldown saat ini.
                                <a href="{{ route('spk.dashboard') }}" class="underline text-ink ml-1">Reset semua filter</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        <div class="px-4 py-2.5 border-t border-hairline bg-canvas flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
            <div class="font-mono-tabular">
                Menampilkan {{ $assessments->firstItem() ?? 0 }}–{{ $assessments->lastItem() ?? 0 }} dari {{ number_format($assessments->total(), 0, ',', '.') }} entitas
            </div>
            <div>
                {{ $assessments->links() }}
            </div>
        </div>
    </section>

    {{-- LEVEL 2 & LEVEL 3: SLIDE-OVER UNDERWRITING DOSSIER & ATOMIC AUDIT DRAWER --}}
    <div x-show="dossierOpen"
         x-cloak
         class="fixed inset-0 z-50 flex justify-end"
         aria-modal="true"
         role="dialog">
        <div class="fixed inset-0 bg-zinc-950/25 transition-opacity" x-on:click="closeDossier()"></div>

        <aside class="relative z-10 w-full max-w-2xl bg-surface border-l border-hairline h-full flex flex-col overflow-hidden">
            {{-- Drawer Header --}}
            <div class="px-5 py-3.5 border-b border-hairline bg-surface flex items-center justify-between gap-3 shrink-0">
                <div class="min-w-0">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">
                        Level 2 — Customer Credit Underwriting Dossier
                    </div>
                    <h2 class="text-base font-bold text-ink truncate" x-text="dossierData ? dossierData.customer.customer_name : 'Memuat dossier...'"></h2>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <button type="button"
                            x-on:click="navigateDossier(-1)"
                            :disabled="!canNavigate(-1)"
                            class="px-2 py-1 text-xs border border-hairline rounded-xs hover:bg-subtle disabled:opacity-40 cursor-pointer"
                            title="Pelanggan sebelumnya">&larr;</button>
                    <button type="button"
                            x-on:click="navigateDossier(1)"
                            :disabled="!canNavigate(1)"
                            class="px-2 py-1 text-xs border border-hairline rounded-xs hover:bg-subtle disabled:opacity-40 cursor-pointer"
                            title="Pelanggan berikutnya">&rarr;</button>
                    <button type="button"
                            x-on:click="closeDossier()"
                            class="ml-1 px-2.5 py-1 text-xs font-mono-tabular border border-hairline rounded-xs hover:bg-subtle cursor-pointer">
                        ESC
                    </button>
                </div>
            </div>

            {{-- Drawer Body --}}
            <div class="flex-1 overflow-y-auto p-5 space-y-5">
                <template x-if="dossierLoading">
                    <div class="py-12 text-center text-xs font-mono-tabular text-muted">
                        Memuat evaluasi rule engine dan jejak rumus SAW...
                    </div>
                </template>

                <template x-if="dossierError">
                    <div class="p-3 border border-rose-300 bg-rose-50 text-rose-900 text-xs rounded-sm" x-text="dossierError"></div>
                </template>

                <template x-if="dossierData && !dossierLoading">
                    <div class="space-y-5">
                        {{-- Identity & Verdict Strip --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 border border-hairline rounded-sm divide-y sm:divide-y-0 sm:divide-x divide-hairline bg-canvas">
                            <div class="p-3">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Peringkat Populasi</div>
                                <div class="mt-1 text-xl font-semibold font-mono-tabular text-ink">
                                    #<span x-text="dossierData.assessment.rank"></span>
                                    <span class="text-xs font-normal text-muted">/ <span x-text="dossierData.assessment.total_population"></span></span>
                                </div>
                                <div class="mt-0.5 text-[11px] font-mono-tabular text-muted" x-text="dossierData.customer.customer_code"></div>
                            </div>

                            <div class="p-3">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Nilai Preferensi V<sub>i</sub></div>
                                <div class="mt-1 text-xl font-semibold font-mono-tabular text-ink" x-text="Number(dossierData.assessment.preference_score).toFixed(4)"></div>
                                <div class="mt-0.5 text-[11px] text-muted">
                                    Term Lama: <span class="font-mono-tabular text-ink" x-text="dossierData.customer.current_payment_term"></span>
                                </div>
                            </div>

                            <div class="p-3 flex flex-col justify-between">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-muted">Keputusan SPK</div>
                                <div class="mt-1">
                                    <span class="inline-flex items-center px-2 py-1 rounded-xs text-xs font-semibold font-mono-tabular"
                                          :class="topBadgeClass(dossierData.assessment.recommended_top)"
                                          x-text="dossierData.assessment.recommended_top_label"></span>
                                </div>
                                <div class="mt-1 text-[10px] font-mono-tabular text-muted">
                                    CR Sesi: <span x-text="Number(dossierData.assessment.ahp_session.cr).toFixed(4)"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Stacked Contribution Bar --}}
                        <div class="border border-hairline rounded-sm p-3.5 bg-surface space-y-2.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-ink">Komposisi Kontribusi Terbobot (&Sigma; w<sub>j</sub>&middot;r<sub>ij</sub> = V<sub>i</sub>)</span>
                                <span class="font-mono-tabular text-muted" x-text="Number(dossierData.assessment.preference_score).toFixed(4) + ' / 1.0000'"></span>
                            </div>
                            <div class="h-3.5 w-full flex overflow-hidden rounded-xs border border-hairline bg-zinc-100">
                                <template x-for="(item, idx) in dossierData.criteria_details" :key="item.code">
                                    <div :style="'width:' + (item.weighted * 100).toFixed(2) + '%'"
                                         :class="segmentColor(idx)"
                                         class="h-full border-r border-white last:border-r-0"
                                         :title="item.code + ': ' + Number(item.weighted).toFixed(4)"></div>
                                </template>
                            </div>
                            <div class="grid grid-cols-5 gap-1.5 text-[10px] font-mono-tabular">
                                <template x-for="(item, idx) in dossierData.criteria_details" :key="item.code + '-legend'">
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-block w-2 h-2 shrink-0 rounded-xs" :class="segmentColor(idx)"></span>
                                        <span class="text-muted" x-text="item.code"></span>
                                        <span class="font-semibold text-ink" x-text="Number(item.weighted).toFixed(3)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Automated Narrative --}}
                        <div class="border border-hairline rounded-sm p-3.5 bg-canvas space-y-2 text-xs">
                            <p class="font-medium text-ink" x-text="dossierData.underwriting_narrative.verdict_summary"></p>
                            <p class="text-[11px] text-muted" x-text="dossierData.underwriting_narrative.policy_comparison"></p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                <div>
                                    <div class="text-[10px] font-semibold uppercase tracking-wider text-emerald-800 mb-1">Faktor Penguat</div>
                                    <ul class="space-y-1 text-[11px] text-ink list-disc list-inside">
                                        <template x-for="(str, i) in dossierData.underwriting_narrative.strengths" :key="'str-' + i">
                                            <li x-text="str"></li>
                                        </template>
                                        <li x-show="dossierData.underwriting_narrative.strengths.length === 0" class="text-muted list-none">Tidak ada kriteria dominan (&ge; 0.80)</li>
                                    </ul>
                                </div>
                                <div>
                                    <div class="text-[10px] font-semibold uppercase tracking-wider text-rose-800 mb-1">Faktor Risiko / Lemah</div>
                                    <ul class="space-y-1 text-[11px] text-ink list-disc list-inside">
                                        <template x-for="(risk, i) in dossierData.underwriting_narrative.risk_factors" :key="'risk-' + i">
                                            <li x-text="risk"></li>
                                        </template>
                                        <li x-show="dossierData.underwriting_narrative.risk_factors.length === 0" class="text-muted list-none">Tidak ada penalti risiko signifikan</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {{-- LEVEL 3: ATOMIC DATA & FORMULA TRACE ACCORDION --}}
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">
                                    Level 3 — Audit Sel CSV &amp; Substitusi Rumus (Klik Kriteria)
                                </span>
                                <button type="button"
                                        x-on:click="expandAllCriteria = !expandAllCriteria"
                                        class="text-[11px] font-medium text-ink underline cursor-pointer"
                                        x-text="expandAllCriteria ? 'Ringkas Semua' : 'Buka Semua Trace'"></button>
                            </div>

                            <template x-for="detail in dossierData.criteria_details" :key="detail.code">
                                <div class="border border-hairline rounded-sm bg-surface overflow-hidden">
                                    <button type="button"
                                            x-on:click="toggleCriterionTrace(detail.code)"
                                            class="w-full px-3.5 py-2.5 flex items-center justify-between gap-3 text-left hover:bg-zinc-50 transition-colors cursor-pointer">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono-tabular text-[11px] font-semibold px-1.5 py-0.5 bg-subtle border border-hairline rounded-xs" x-text="detail.code"></span>
                                                <span class="text-xs font-semibold text-ink" x-text="detail.name"></span>
                                                <span class="text-[10px] font-mono-tabular uppercase px-1 py-0.5 rounded-xs border border-hairline text-muted" x-text="detail.type"></span>
                                            </div>
                                            <div class="mt-0.5 text-[11px] text-muted truncate" x-text="detail.rule_trace.rule_matched || ''"></div>
                                        </div>
                                        <div class="flex items-center gap-4 shrink-0 font-mono-tabular text-xs text-right">
                                            <div>
                                                <div class="text-[10px] text-muted">Skor x</div>
                                                <div class="font-semibold text-ink" x-text="detail.score"></div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] text-muted">Norm r</div>
                                                <div class="text-ink" x-text="Number(detail.normalized).toFixed(4)"></div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] text-muted">w&middot;r</div>
                                                <div class="font-semibold text-ink" x-text="Number(detail.weighted).toFixed(4)"></div>
                                            </div>
                                            <span class="text-muted text-[11px]" x-text="isCriterionExpanded(detail.code) ? '−' : '+'"></span>
                                        </div>
                                    </button>

                                    {{-- Level 3 Expanded Body --}}
                                    <div x-show="isCriterionExpanded(detail.code)"
                                         x-cloak
                                         class="border-t border-hairline bg-canvas p-3.5 space-y-3 text-xs">
                                        {{-- Math Substitution Block --}}
                                        <div class="bg-surface border border-hairline rounded-xs p-2.5 font-mono-tabular text-[11px] space-y-1">
                                            <div class="text-[10px] uppercase tracking-wider text-muted font-sans font-semibold">Substitusi Rumus SAW &amp; Bobot AHP</div>
                                            <template x-if="detail.type === 'benefit'">
                                                <div class="text-ink">
                                                    r = x<sub>ij</sub> / max(X) = <span x-text="detail.score"></span> / <span x-text="detail.saw_trace.extremum_value"></span> = <strong x-text="Number(detail.normalized).toFixed(6)"></strong>
                                                </div>
                                            </template>
                                            <template x-if="detail.type === 'cost'">
                                                <div class="text-ink">
                                                    r = min(X) / x<sub>ij</sub> = <span x-text="detail.saw_trace.extremum_value"></span> / <span x-text="detail.score"></span> = <strong x-text="Number(detail.normalized).toFixed(6)"></strong>
                                                </div>
                                            </template>
                                            <div class="text-ink">
                                                Kontribusi = w<sub>j</sub> &times; r = <span x-text="Number(detail.weight).toFixed(6)"></span> &times; <span x-text="Number(detail.normalized).toFixed(6)"></span> = <strong x-text="Number(detail.weighted).toFixed(6)"></strong>
                                            </div>
                                        </div>

                                        {{-- Raw CSV Column Inspection --}}
                                        <div class="space-y-1.5">
                                            <div class="text-[10px] uppercase tracking-wider text-muted font-semibold">Inspeksi Kolom CSV Asli</div>

                                            {{-- C1 Raw --}}
                                            <template x-if="detail.code === 'C1'">
                                                <div class="border border-hairline bg-surface rounded-xs divide-y divide-hairline font-mono-tabular text-[11px]">
                                                    <div class="px-2.5 py-1.5 flex justify-between gap-2"><span class="text-muted">businessEntity</span><span class="text-ink" x-text="detail.rule_trace.raw_inputs?.businessEntity || 'null'"></span></div>
                                                    <div class="px-2.5 py-1.5 flex justify-between gap-2"><span class="text-muted">businessEntityName</span><span class="text-ink text-right" x-text="detail.rule_trace.raw_inputs?.businessEntityName || 'null'"></span></div>
                                                    <div class="px-2.5 py-1.5 flex justify-between gap-2"><span class="text-muted">Sumber Deteksi</span><span class="font-semibold text-ink" x-text="detail.rule_trace.source_used"></span></div>
                                                </div>
                                            </template>

                                            {{-- C2 Raw --}}
                                            <template x-if="detail.code === 'C2'">
                                                <div class="border border-hairline bg-surface rounded-xs divide-y divide-hairline font-mono-tabular text-[11px]">
                                                    <template x-for="(docInfo, docKey) in detail.rule_trace.documents" :key="docKey">
                                                        <div class="px-2.5 py-1.5 flex items-center justify-between gap-2">
                                                            <span class="text-muted uppercase" x-text="docKey"></span>
                                                            <div class="flex items-center gap-2 min-w-0">
                                                                <span class="truncate text-ink" x-text="docInfo.raw || 'kosong'"></span>
                                                                <span class="px-1.5 py-0.5 text-[9px] rounded-xs shrink-0"
                                                                      :class="docInfo.is_valid ? 'status-net30' : 'status-cbd'"
                                                                      x-text="docInfo.is_valid ? 'VALID' : 'KOSONG'"></span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>

                                            {{-- C3 Raw --}}
                                            <template x-if="detail.code === 'C3'">
                                                <div class="border border-hairline bg-surface rounded-xs divide-y divide-hairline font-mono-tabular text-[11px]">
                                                    <template x-for="(attrInfo, attrKey) in detail.rule_trace.attributes" :key="attrKey">
                                                        <div class="px-2.5 py-1.5 flex items-center justify-between gap-2">
                                                            <span class="text-muted" x-text="attrKey"></span>
                                                            <div class="flex items-center gap-2 min-w-0 max-w-[72%]">
                                                                <span class="truncate text-ink" x-text="attrInfo.raw || 'kosong'"></span>
                                                                <span class="px-1.5 py-0.5 text-[9px] rounded-xs shrink-0"
                                                                      :class="attrInfo.is_valid ? 'status-net30' : 'status-cbd'"
                                                                      x-text="attrInfo.is_valid ? 'VALID' : 'KOSONG'"></span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>

                                            {{-- C4 Raw --}}
                                            <template x-if="detail.code === 'C4'">
                                                <div class="border border-hairline bg-surface rounded-xs divide-y divide-hairline font-mono-tabular text-[11px]">
                                                    <div class="px-2.5 py-1.5 flex justify-between gap-2"><span class="text-muted">customerType</span><span class="text-ink" x-text="detail.rule_trace.raw_inputs?.customerType || 'null'"></span></div>
                                                    <div class="px-2.5 py-1.5 flex justify-between gap-2"><span class="text-muted">TYPE</span><span class="text-ink" x-text="detail.rule_trace.raw_inputs?.TYPE || 'null'"></span></div>
                                                </div>
                                            </template>

                                            {{-- C5 Raw --}}
                                            <template x-if="detail.code === 'C5'">
                                                <div class="border border-hairline bg-surface rounded-xs divide-y divide-hairline text-[11px]">
                                                    <div class="p-2.5 grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                                        <div>
                                                            <div class="text-[10px] font-semibold uppercase text-muted mb-0.5">DELIVERY ADDRESS</div>
                                                            <div class="text-ink leading-relaxed" x-text="detail.rule_trace.raw_inputs?.['DELIVERY ADDRESS'] || 'kosong'"></div>
                                                        </div>
                                                        <div>
                                                            <div class="text-[10px] font-semibold uppercase text-muted mb-0.5">billingAddress</div>
                                                            <div class="text-ink leading-relaxed" x-text="detail.rule_trace.raw_inputs?.billingAddress || 'kosong'"></div>
                                                        </div>
                                                    </div>
                                                    <div class="px-2.5 py-1.5 flex flex-wrap items-center justify-between gap-2 font-mono-tabular">
                                                        <span class="px-1.5 py-0.5 text-[10px] rounded-xs"
                                                              :class="detail.rule_trace.checks?.is_same_address ? 'status-net30' : 'status-cbd'"
                                                              x-text="detail.rule_trace.checks?.is_same_address ? 'ALAMAT COCOK' : 'ALAMAT BERBEDA'"></span>
                                                        <span class="text-muted">Telp: <strong class="text-ink" x-text="detail.rule_trace.raw_inputs?.deliveryPhone || 'kosong'"></strong></span>
                                                        <span class="text-muted">Email: <strong class="text-ink" x-text="detail.rule_trace.raw_inputs?.email || 'kosong'"></strong></span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </aside>
    </div>

    {{-- SIDE-BY-SIDE COMPARISON MODAL --}}
    <div x-show="compareModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-zinc-950/30" x-on:click="compareModalOpen = false"></div>
        <div class="relative z-10 w-full max-w-4xl bg-surface border border-hairline rounded-md overflow-hidden">
            <div class="px-4 py-3 border-b border-hairline flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">Komparasi Side-by-Side</span>
                    <h3 class="text-sm font-bold text-ink">Perbandingan Matematis Antar Pelanggan</h3>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" x-on:click="compareList = []; compareModalOpen = false" class="text-xs text-muted hover:text-ink underline cursor-pointer">Kosongkan</button>
                    <button type="button" x-on:click="compareModalOpen = false" class="px-2 py-1 text-xs font-mono-tabular border border-hairline rounded-xs hover:bg-subtle cursor-pointer">ESC</button>
                </div>
            </div>
            <div class="overflow-x-auto p-4">
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-hairline text-[10px] uppercase tracking-wider text-muted">
                            <th class="text-left py-2 pr-3">Parameter</th>
                            <template x-for="cust in comparedCustomers()" :key="'cmp-h-' + cust.id">
                                <th class="text-right py-2 px-3">
                                    <div class="font-mono-tabular text-[10px]" x-text="cust.code"></div>
                                    <div class="text-xs font-bold text-ink normal-case" x-text="cust.name"></div>
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline font-mono-tabular">
                        <tr>
                            <td class="py-2 pr-3 font-sans text-muted">Peringkat &amp; ToP</td>
                            <template x-for="cust in comparedCustomers()" :key="'cmp-r-' + cust.id">
                                <td class="py-2 px-3 text-right">
                                    #<span x-text="cust.rank"></span> &middot;
                                    <span class="px-1.5 py-0.5 rounded-xs text-[10px] font-semibold" :class="topBadgeClass(cust.recommended_top)" x-text="cust.recommended_top"></span>
                                </td>
                            </template>
                        </tr>
                        <tr>
                            <td class="py-2 pr-3 font-sans font-semibold text-ink">Nilai Akhir (V<sub>i</sub>)</td>
                            <template x-for="cust in comparedCustomers()" :key="'cmp-v-' + cust.id">
                                <td class="py-2 px-3 text-right font-semibold text-ink" x-text="Number(cust.preference_score).toFixed(4)"></td>
                            </template>
                        </tr>
                        <template x-for="code in ['C1', 'C2', 'C3', 'C4', 'C5']" :key="'cmp-c-' + code">
                            <tr>
                                <td class="py-2 pr-3 font-sans text-muted" x-text="code + ' (Skor / Terbobot)'"></td>
                                <template x-for="cust in comparedCustomers()" :key="'cmp-' + code + '-' + cust.id">
                                    <td class="py-2 px-3 text-right">
                                        <span class="font-semibold text-ink" x-text="cust.scores[code]"></span>
                                        <span class="text-muted text-[11px]" x-text="' (' + Number(cust.weighted[code]).toFixed(4) + ')'"></span>
                                    </td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- CSV IMPORT / RE-SYNC MODAL --}}
    <div x-show="csvModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-zinc-950/30" x-on:click="csvModalOpen = false"></div>
        <div class="relative z-10 w-full max-w-lg bg-surface border border-hairline rounded-md overflow-hidden">
            <div class="px-4 py-3 border-b border-hairline flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-muted">Data Pipeline</span>
                    <h3 class="text-sm font-bold text-ink">Impor CSV Pelanggan &amp; Kalkulasi Ulang SAW</h3>
                </div>
                <button type="button" x-on:click="csvModalOpen = false" class="px-2 py-1 text-xs font-mono-tabular border border-hairline rounded-xs hover:bg-subtle cursor-pointer">ESC</button>
            </div>
            <form method="POST" action="{{ route('spk.import.csv') }}" enctype="multipart/form-data" class="p-4 space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-medium text-ink mb-1.5">Unggah File CSV Baru (Opsional)</label>
                    <input type="file"
                           name="csv_file"
                           accept=".csv,text/csv"
                           class="w-full p-2 border border-hairline rounded-sm bg-canvas text-xs file:mr-3 file:py-1 file:px-2.5 file:border-0 file:bg-zinc-900 file:text-white file:rounded-xs file:text-xs">
                    <p class="mt-1.5 text-[11px] text-muted">
                        Kosongkan pilihan file jika ingin melakukan sinkronisasi ulang dari dataset bawaan <span class="font-mono-tabular">Asets/Customer (3).csv</span>.
                    </p>
                </div>

                <div>
                    <span class="block font-medium text-ink mb-1.5">Mode Impor Dataset</span>
                    <div class="space-y-2">
                        <label class="flex items-start gap-2.5 p-2.5 border border-hairline rounded-sm bg-canvas hover:bg-subtle/60 cursor-pointer">
                            <input type="radio"
                                   name="import_mode"
                                   value="replace"
                                   checked
                                   class="mt-0.5 border-zinc-300 text-zinc-950 focus:ring-0">
                            <div>
                                <div class="font-medium text-ink">Ganti Seluruh Dataset (Replace)</div>
                                <p class="text-[11px] text-muted">Menghapus data pelanggan lama dan hanya menghitung ulang data dari file CSV yang diimpor.</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-2.5 p-2.5 border border-hairline rounded-sm bg-canvas hover:bg-subtle/60 cursor-pointer">
                            <input type="radio"
                                   name="import_mode"
                                   value="upsert"
                                   class="mt-0.5 border-zinc-300 text-zinc-950 focus:ring-0">
                            <div>
                                <div class="font-medium text-ink">Gabung / Perbarui (Upsert)</div>
                                <p class="text-[11px] text-muted">Mempertahankan data lama dan memperbarui pelanggan dengan <span class="font-mono-tabular">customer_code</span> yang sama.</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-hairline">
                    <button type="button" x-on:click="csvModalOpen = false" class="px-3 py-1.5 border border-hairline rounded-sm text-muted hover:text-ink cursor-pointer">Batal</button>
                    <button type="submit" class="px-3 py-1.5 bg-zinc-950 text-white rounded-sm font-medium hover:bg-zinc-800 cursor-pointer">Jalankan Impor &amp; Evaluasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.__SPK_PAGE_CUSTOMERS__ = @js($pageCustomersPayload);

    function spkDashboard() {
        return {
            matrixMode: 'raw',
            pageCustomers: window.__SPK_PAGE_CUSTOMERS__ || [],
            dossierOpen: false,
            dossierLoading: false,
            dossierError: null,
            dossierData: null,
            activeCustomerId: null,
            expandedCriteria: {},
            expandAllCriteria: false,
            compareList: [],
            compareModalOpen: false,
            csvModalOpen: false,

            handleGlobalKeydown(e) {
                if (e.key === 'Escape') {
                    this.dossierOpen = false;
                    this.compareModalOpen = false;
                    this.csvModalOpen = false;
                    return;
                }
                if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
                    e.preventDefault();
                    this.$refs.searchInput?.focus();
                }
            },

            async openDossier(customerId) {
                this.activeCustomerId = customerId;
                this.dossierOpen = true;
                this.dossierLoading = true;
                this.dossierError = null;
                this.expandedCriteria = {};
                this.expandAllCriteria = false;

                try {
                    const response = await fetch(`/customers/${customerId}/dossier`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!response.ok) {
                        throw new Error('Gagal memuat detail dossier pelanggan.');
                    }
                    this.dossierData = await response.json();
                } catch (err) {
                    this.dossierError = err.message || 'Terjadi kesalahan saat mengambil dossier.';
                } finally {
                    this.dossierLoading = false;
                }
            },

            closeDossier() {
                this.dossierOpen = false;
            },

            canNavigate(delta) {
                if (!this.activeCustomerId || this.pageCustomers.length === 0) return false;
                const idx = this.pageCustomers.findIndex(c => c.id === this.activeCustomerId);
                return idx >= 0 && (idx + delta) >= 0 && (idx + delta) < this.pageCustomers.length;
            },

            navigateDossier(delta) {
                const idx = this.pageCustomers.findIndex(c => c.id === this.activeCustomerId);
                if (idx >= 0 && this.pageCustomers[idx + delta]) {
                    this.openDossier(this.pageCustomers[idx + delta].id);
                }
            },

            toggleCriterionTrace(code) {
                if (this.expandAllCriteria) {
                    this.expandAllCriteria = false;
                    this.expandedCriteria = { [code]: false };
                    return;
                }
                this.expandedCriteria[code] = !this.expandedCriteria[code];
            },

            isCriterionExpanded(code) {
                return this.expandAllCriteria || Boolean(this.expandedCriteria[code]);
            },

            toggleCompare(customerId) {
                const idx = this.compareList.indexOf(customerId);
                if (idx >= 0) {
                    this.compareList.splice(idx, 1);
                } else if (this.compareList.length < 3) {
                    this.compareList.push(customerId);
                }
            },

            isCompared(customerId) {
                return this.compareList.includes(customerId);
            },

            comparedCustomers() {
                return this.pageCustomers.filter(c => this.compareList.includes(c.id));
            },

            topBadgeClass(top) {
                if (top === 'NET_30') return 'status-net30';
                if (top === 'NET_14') return 'status-net14';
                return 'status-cbd';
            },

            segmentColor(idx) {
                return ['bg-zinc-950', 'bg-zinc-700', 'bg-zinc-500', 'bg-zinc-400', 'bg-emerald-700'][idx % 5];
            }
        };
    }
</script>
@endsection
