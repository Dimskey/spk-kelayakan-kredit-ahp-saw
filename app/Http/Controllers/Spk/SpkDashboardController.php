<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use App\Models\AhpSession;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\CustomerAssessment;
use App\Services\Spk\CsvCustomerImporterService;
use App\Services\Spk\SpkAssessmentOrchestratorService;
use Database\Seeders\SpkCriteriaSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpkDashboardController extends Controller
{
    private const ENTITY_LABELS = [
        5 => 'PT (Perseroan Terbatas)',
        4 => 'CV (Persekutuan Komanditer)',
        3 => 'Koperasi / Yayasan',
        2 => 'PD / Toko / UD',
        1 => 'Individu / Perorangan',
    ];

    private const TOP_LABELS = [
        'NET_30' => 'Net 30 Hari',
        'NET_14' => 'Net 14 Hari',
        'CBD' => 'CBD (Tunai)',
    ];

    private const FRAUD_LABELS = [
        1 => 'Risiko Rendah (1)',
        3 => 'Risiko Sedang (3)',
        5 => 'Risiko Tinggi (5)',
    ];

    private const REPEAT_LABELS = [
        5 => 'Reseller (5)',
        3 => 'Enduser / Lainnya (3)',
    ];

    public function __construct(
        private readonly CsvCustomerImporterService $importer,
        private readonly SpkAssessmentOrchestratorService $orchestrator
    ) {}

    public function index(Request $request): View
    {
        [$activeSession, $criteria] = $this->ensureInitialized();

        $macro = $this->buildMacroOverview($activeSession);
        $matrices = $this->buildCrossTabulationMatrices($activeSession);
        $filters = $this->extractFilters($request);

        $query = CustomerAssessment::query()
            ->select('customer_assessments.*')
            ->join('customers', 'customers.id', '=', 'customer_assessments.customer_id')
            ->with('customer')
            ->where('customer_assessments.ahp_session_id', $activeSession->id);

        $this->applyLevel1Filters($query, $filters);

        $sortMap = [
            'rank' => 'customer_assessments.rank',
            'preference_score' => 'customer_assessments.preference_score',
            'customer_code' => 'customers.customer_code',
            'customer_name' => 'customers.customer_name',
            'c1' => 'customer_assessments.c1_score',
            'c2' => 'customer_assessments.c2_score',
            'c3' => 'customer_assessments.c3_score',
            'c4' => 'customer_assessments.c4_score',
            'c5' => 'customer_assessments.c5_score',
        ];

        $sortKey = array_key_exists($filters['sort'], $sortMap) ? $filters['sort'] : 'rank';
        $sortDir = strtolower($filters['dir']) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortMap[$sortKey], $sortDir)
            ->orderBy('customer_assessments.rank', 'asc');

        $perPage = max(10, min(100, (int) $filters['per_page']));
        $assessments = $query->paginate($perPage)->withQueryString();

        $activeFilterBreadcrumbs = $this->buildFilterBreadcrumbs($filters, $request);

        return view('spk.dashboard', [
            'activeSession' => $activeSession,
            'criteria' => $criteria,
            'macro' => $macro,
            'matrices' => $matrices,
            'assessments' => $assessments,
            'filters' => $filters,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
            'activeFilterBreadcrumbs' => $activeFilterBreadcrumbs,
            'entityLabels' => self::ENTITY_LABELS,
            'topLabels' => self::TOP_LABELS,
            'fraudLabels' => self::FRAUD_LABELS,
            'repeatLabels' => self::REPEAT_LABELS,
        ]);
    }

    public function dossier(Customer $customer): JsonResponse
    {
        [$activeSession, $criteria] = $this->ensureInitialized();

        $assessment = CustomerAssessment::where('customer_id', $customer->id)
            ->where('ahp_session_id', $activeSession->id)
            ->first();

        if (! $assessment) {
            return response()->json([
                'message' => 'Data asesmen untuk pelanggan ini belum tersedia pada sesi AHP aktif.',
            ], 404);
        }

        $totalPopulation = CustomerAssessment::where('ahp_session_id', $activeSession->id)->count();
        $trace = $assessment->evaluation_trace ?? [];
        $sawTrace = $trace['saw']['formula_trace'] ?? [];
        $sawExtrema = $trace['saw']['extrema'] ?? [];

        $scoreByCode = [
            'C1' => $assessment->c1_score,
            'C2' => $assessment->c2_score,
            'C3' => $assessment->c3_score,
            'C4' => $assessment->c4_score,
            'C5' => $assessment->c5_score,
        ];
        $normByCode = [
            'C1' => $assessment->r1,
            'C2' => $assessment->r2,
            'C3' => $assessment->r3,
            'C4' => $assessment->r4,
            'C5' => $assessment->r5,
        ];
        $weightedByCode = [
            'C1' => $assessment->w1_r1,
            'C2' => $assessment->w2_r2,
            'C3' => $assessment->w3_r3,
            'C4' => $assessment->w4_r4,
            'C5' => $assessment->w5_r5,
        ];

        $criteriaDetails = [];
        foreach ($criteria as $criterion) {
            $code = $criterion->code;
            $weightedVal = (float) ($weightedByCode[$code] ?? 0.0);
            $contribPct = $assessment->preference_score > 0
                ? round(($weightedVal / $assessment->preference_score) * 100, 2)
                : 0.0;

            $criteriaDetails[] = [
                'code' => $code,
                'name' => $criterion->name,
                'type' => $criterion->type,
                'weight' => (float) $criterion->weight,
                'weight_pct' => round((float) $criterion->weight * 100, 2),
                'score' => (int) ($scoreByCode[$code] ?? 1),
                'normalized' => (float) ($normByCode[$code] ?? 0.0),
                'weighted' => $weightedVal,
                'contribution_pct' => $contribPct,
                'rubric' => $criterion->rubric,
                'source_columns' => $criterion->source_columns,
                'rule_trace' => $trace[$code] ?? [],
                'saw_trace' => $sawTrace[$code] ?? [],
                'population_extremum' => $sawExtrema[$code] ?? [],
            ];
        }

        $narrative = $this->buildUnderwritingNarrative($customer, $assessment, $criteriaDetails);

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'customer_name' => $customer->customer_name,
                'type' => $customer->type,
                'company' => $customer->company,
                'business_entity' => $customer->business_entity,
                'business_entity_name' => $customer->business_entity_name,
                'customer_type' => $customer->customer_type,
                'current_payment_term' => $customer->current_payment_term ?: 'Tidak Tercatat',
                'status' => $customer->status,
                'pic' => $customer->pic,
                'nik' => $customer->nik,
                'img_ktp' => $customer->img_ktp,
                'npwp' => $customer->npwp,
                'nib' => $customer->nib,
                'sppkp' => $customer->sppkp,
                'delivery_address' => $customer->delivery_address,
                'billing_address' => $customer->billing_address,
                'delivery_phone' => $customer->delivery_phone,
                'email' => $customer->email,
            ],
            'assessment' => [
                'rank' => $assessment->rank,
                'total_population' => $totalPopulation,
                'preference_score' => (float) $assessment->preference_score,
                'recommended_top' => $assessment->recommended_top,
                'recommended_top_label' => self::TOP_LABELS[$assessment->recommended_top] ?? $assessment->recommended_top,
                'scores' => $scoreByCode,
                'normalized' => $normByCode,
                'weighted' => $weightedByCode,
                'thresholds' => $trace['saw']['thresholds'] ?? ['net_30' => 0.80, 'net_14' => 0.60],
                'ahp_session' => [
                    'id' => $activeSession->id,
                    'name' => $activeSession->name,
                    'cr' => (float) $activeSession->cr,
                ],
            ],
            'criteria_details' => $criteriaDetails,
            'underwriting_narrative' => $narrative,
        ]);
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:20480'],
            'sync_default' => ['nullable', 'boolean'],
        ]);

        [$activeSession, $criteria] = $this->ensureInitialized();

        if ($request->hasFile('csv_file')) {
            $uploaded = $request->file('csv_file');
            $importResult = $this->importer->importFromFile($uploaded->getRealPath());
        } else {
            $defaultPath = base_path('Asets/Customer (3).csv');
            $importResult = $this->importer->importFromFile($defaultPath);
        }

        $summary = $this->orchestrator->recalculateForActiveSession();

        return redirect()
            ->route('spk.dashboard')
            ->with('status', sprintf(
                'Impor selesai (%d pelanggan pada batch %s). Hasil SAW: %d NET_30, %d NET_14, %d CBD.',
                $importResult['imported_count'],
                $importResult['batch_id'],
                $summary['net_30'],
                $summary['net_14'],
                $summary['cbd']
            ));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        [$activeSession, $criteria] = $this->ensureInitialized();
        $filters = $this->extractFilters($request);

        $query = CustomerAssessment::query()
            ->select('customer_assessments.*')
            ->join('customers', 'customers.id', '=', 'customer_assessments.customer_id')
            ->with('customer')
            ->where('customer_assessments.ahp_session_id', $activeSession->id);

        $this->applyLevel1Filters($query, $filters);
        $query->orderBy('customer_assessments.rank', 'asc');

        $filename = 'spk-peringkat-kredit-ahp-saw.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for clean Excel opening
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Rank',
                'Customer Code',
                'Customer Name',
                'Business Entity',
                'Customer Type',
                'Current Payment Term',
                'C1 (Badan Usaha)',
                'C2 (Legalitas)',
                'C3 (Identitas PIC)',
                'C4 (Repeat Order)',
                'C5 (Risiko Anomali)',
                'r1',
                'r2',
                'r3',
                'r4',
                'r5',
                'w1_r1',
                'w2_r2',
                'w3_r3',
                'w4_r4',
                'w5_r5',
                'Preference Score (V_i)',
                'Recommended ToP',
            ], ',', '"', '');

            foreach ($query->cursor() as $assessment) {
                $cust = $assessment->customer;
                fputcsv($handle, [
                    $assessment->rank,
                    $cust->customer_code,
                    $cust->customer_name,
                    $cust->business_entity,
                    $cust->customer_type,
                    $cust->current_payment_term,
                    $assessment->c1_score,
                    $assessment->c2_score,
                    $assessment->c3_score,
                    $assessment->c4_score,
                    $assessment->c5_score,
                    number_format((float) $assessment->r1, 6, '.', ''),
                    number_format((float) $assessment->r2, 6, '.', ''),
                    number_format((float) $assessment->r3, 6, '.', ''),
                    number_format((float) $assessment->r4, 6, '.', ''),
                    number_format((float) $assessment->r5, 6, '.', ''),
                    number_format((float) $assessment->w1_r1, 6, '.', ''),
                    number_format((float) $assessment->w2_r2, 6, '.', ''),
                    number_format((float) $assessment->w3_r3, 6, '.', ''),
                    number_format((float) $assessment->w4_r4, 6, '.', ''),
                    number_format((float) $assessment->w5_r5, 6, '.', ''),
                    number_format((float) $assessment->preference_score, 6, '.', ''),
                    $assessment->recommended_top,
                ], ',', '"', '');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function ensureInitialized(): array
    {
        if (Criterion::count() === 0 || ! AhpSession::where('is_active', true)->exists()) {
            app(SpkCriteriaSeeder::class)->run();
        }

        $activeSession = AhpSession::where('is_active', true)->firstOrFail();
        $criteria = Criterion::orderBy('sort_order')->get();

        return [$activeSession, $criteria];
    }

    private function buildMacroOverview(AhpSession $session): array
    {
        $base = CustomerAssessment::where('ahp_session_id', $session->id);
        $total = (clone $base)->count();

        $stats = (clone $base)
            ->selectRaw('AVG(preference_score) as avg_score, MIN(preference_score) as min_score, MAX(preference_score) as max_score')
            ->first();

        $topCountsRaw = (clone $base)
            ->selectRaw('recommended_top, COUNT(*) as cnt')
            ->groupBy('recommended_top')
            ->pluck('cnt', 'recommended_top')
            ->toArray();

        $net30 = (int) ($topCountsRaw['NET_30'] ?? 0);
        $net14 = (int) ($topCountsRaw['NET_14'] ?? 0);
        $cbd = (int) ($topCountsRaw['CBD'] ?? 0);

        $joined = CustomerAssessment::query()
            ->join('customers', 'customers.id', '=', 'customer_assessments.customer_id')
            ->where('customer_assessments.ahp_session_id', $session->id);

        $upgradeCandidates = (clone $joined)
            ->whereRaw("UPPER(COALESCE(customers.current_payment_term, '')) = 'CBD'")
            ->whereIn('customer_assessments.recommended_top', ['NET_30', 'NET_14'])
            ->count();

        $riskExposures = (clone $joined)
            ->whereRaw("UPPER(COALESCE(customers.current_payment_term, '')) NOT IN ('', 'CBD', '0 DAYS')")
            ->where('customer_assessments.recommended_top', 'CBD')
            ->count();

        $highFraudCount = (clone $base)->where('c5_score', 5)->count();

        return [
            'total' => $total,
            'avg_score' => $total > 0 ? round((float) $stats->avg_score, 4) : 0.0,
            'min_score' => $total > 0 ? round((float) $stats->min_score, 4) : 0.0,
            'max_score' => $total > 0 ? round((float) $stats->max_score, 4) : 0.0,
            'net_30' => $net30,
            'net_14' => $net14,
            'cbd' => $cbd,
            'net_30_pct' => $total > 0 ? round(($net30 / $total) * 100, 1) : 0.0,
            'net_14_pct' => $total > 0 ? round(($net14 / $total) * 100, 1) : 0.0,
            'cbd_pct' => $total > 0 ? round(($cbd / $total) * 100, 1) : 0.0,
            'upgrade_candidates' => $upgradeCandidates,
            'risk_exposures' => $riskExposures,
            'high_fraud_count' => $highFraudCount,
        ];
    }

    private function buildCrossTabulationMatrices(AhpSession $session): array
    {
        $entityRows = CustomerAssessment::where('ahp_session_id', $session->id)
            ->selectRaw('c1_score, recommended_top, COUNT(*) as cnt')
            ->groupBy('c1_score', 'recommended_top')
            ->get();

        $entityVsTop = [];
        foreach ([5, 4, 3, 2, 1] as $c1) {
            $entityVsTop[$c1] = [
                'label' => self::ENTITY_LABELS[$c1],
                'NET_30' => 0,
                'NET_14' => 0,
                'CBD' => 0,
                'total' => 0,
            ];
        }
        foreach ($entityRows as $row) {
            $c1 = (int) $row->c1_score;
            $top = $row->recommended_top;
            if (isset($entityVsTop[$c1][$top])) {
                $entityVsTop[$c1][$top] = (int) $row->cnt;
                $entityVsTop[$c1]['total'] += (int) $row->cnt;
            }
        }

        $typeRows = CustomerAssessment::where('ahp_session_id', $session->id)
            ->selectRaw('c4_score, c5_score, COUNT(*) as cnt')
            ->groupBy('c4_score', 'c5_score')
            ->get();

        $typeVsFraud = [];
        foreach ([5, 3] as $c4) {
            $typeVsFraud[$c4] = [
                'label' => self::REPEAT_LABELS[$c4],
                1 => 0,
                3 => 0,
                5 => 0,
                'total' => 0,
            ];
        }
        foreach ($typeRows as $row) {
            $c4 = (int) $row->c4_score;
            $c5 = (int) $row->c5_score;
            if (isset($typeVsFraud[$c4][$c5])) {
                $typeVsFraud[$c4][$c5] = (int) $row->cnt;
                $typeVsFraud[$c4]['total'] += (int) $row->cnt;
            }
        }

        return [
            'entity_vs_top' => $entityVsTop,
            'type_vs_fraud' => $typeVsFraud,
        ];
    }

    private function extractFilters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'top' => strtoupper(trim((string) $request->query('top', ''))),
            'c1' => $request->query('c1', ''),
            'c2' => $request->query('c2', ''),
            'c3' => $request->query('c3', ''),
            'c4' => $request->query('c4', ''),
            'c5' => $request->query('c5', ''),
            'policy_gap' => trim((string) $request->query('policy_gap', '')),
            'sort' => trim((string) $request->query('sort', 'rank')),
            'dir' => trim((string) $request->query('dir', 'asc')),
            'per_page' => (int) $request->query('per_page', 25),
        ];
    }

    private function applyLevel1Filters($query, array $filters): void
    {
        if ($filters['search'] !== '') {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($sub) use ($term): void {
                $sub->where('customers.customer_code', 'like', $term)
                    ->orWhere('customers.customer_name', 'like', $term)
                    ->orWhere('customers.business_entity_name', 'like', $term)
                    ->orWhere('customers.pic', 'like', $term)
                    ->orWhere('customers.npwp', 'like', $term)
                    ->orWhere('customers.email', 'like', $term);
            });
        }

        if (in_array($filters['top'], ['NET_30', 'NET_14', 'CBD'], true)) {
            $query->where('customer_assessments.recommended_top', $filters['top']);
        }

        foreach (['c1', 'c2', 'c3', 'c4', 'c5'] as $criterionKey) {
            if ($filters[$criterionKey] !== '' && is_numeric($filters[$criterionKey])) {
                $query->where("customer_assessments.{$criterionKey}_score", (int) $filters[$criterionKey]);
            }
        }

        if ($filters['policy_gap'] === 'upgrade_candidate') {
            $query->whereRaw("UPPER(COALESCE(customers.current_payment_term, '')) = 'CBD'")
                ->whereIn('customer_assessments.recommended_top', ['NET_30', 'NET_14']);
        } elseif ($filters['policy_gap'] === 'risk_exposure') {
            $query->whereRaw("UPPER(COALESCE(customers.current_payment_term, '')) NOT IN ('', 'CBD', '0 DAYS')")
                ->where('customer_assessments.recommended_top', 'CBD');
        }
    }

    private function buildFilterBreadcrumbs(array $filters, Request $request): array
    {
        $breadcrumbs = [];
        $allQuery = $request->query();
        unset($allQuery['page']);

        $labels = [
            'search' => fn ($v) => "Pencarian: \"{$v}\"",
            'top' => fn ($v) => 'Rekomendasi: ' . (self::TOP_LABELS[$v] ?? $v),
            'c1' => fn ($v) => 'C1 Badan Usaha: ' . (self::ENTITY_LABELS[(int) $v] ?? $v),
            'c2' => fn ($v) => "C2 Legalitas Skor: {$v}",
            'c3' => fn ($v) => "C3 Identitas PIC Skor: {$v}",
            'c4' => fn ($v) => 'C4 Tipe: ' . (self::REPEAT_LABELS[(int) $v] ?? $v),
            'c5' => fn ($v) => 'C5 Anomali: ' . (self::FRAUD_LABELS[(int) $v] ?? $v),
            'policy_gap' => fn ($v) => $v === 'upgrade_candidate'
                ? 'Audit Gap: Kandidat Upgrade Kredit (Saat ini CBD)'
                : 'Audit Gap: Eksposur Risiko (Kredit Eksisting -> SPK CBD)',
        ];

        foreach ($labels as $key => $formatter) {
            if (($filters[$key] ?? '') !== '') {
                $withoutThis = $allQuery;
                unset($withoutThis[$key]);
                $breadcrumbs[] = [
                    'key' => $key,
                    'label' => $formatter($filters[$key]),
                    'remove_url' => route('spk.dashboard', $withoutThis),
                ];
            }
        }

        return $breadcrumbs;
    }

    private function buildUnderwritingNarrative(
        Customer $customer,
        CustomerAssessment $assessment,
        array $criteriaDetails
    ): array {
        $strengths = [];
        $riskFactors = [];

        foreach ($criteriaDetails as $detail) {
            $ruleText = $detail['rule_trace']['rule_matched'] ?? '';
            if ($detail['normalized'] >= 0.80) {
                $strengths[] = "{$detail['code']} ({$detail['name']}): {$ruleText}";
            } elseif ($detail['normalized'] < 0.60 || ($detail['code'] === 'C5' && $detail['score'] >= 3)) {
                $riskFactors[] = "{$detail['code']} ({$detail['name']}): {$ruleText}";
            }
        }

        $verdictLabel = self::TOP_LABELS[$assessment->recommended_top] ?? $assessment->recommended_top;
        $verdictSummary = match ($assessment->recommended_top) {
            'NET_30' => sprintf(
                'Pelanggan meraih skor preferensi V = %.4f (≥ 0.8000), memenuhi ambang batas tertinggi untuk fasilitas kredit %s.',
                $assessment->preference_score,
                $verdictLabel
            ),
            'NET_14' => sprintf(
                'Pelanggan meraih skor preferensi V = %.4f (0.6000 – 0.7999), layak memperoleh fasilitas kredit bersyarat %s dengan pemantauan dokumen.',
                $assessment->preference_score,
                $verdictLabel
            ),
            default => sprintf(
                'Pelanggan meraih skor preferensi V = %.4f (< 0.6000), sehingga sistem merekomendasikan pembayaran %s untuk mitigasi risiko gagal bayar.',
                $assessment->preference_score,
                $verdictLabel
            ),
        };

        $currentTerm = strtoupper(trim((string) $customer->current_payment_term));
        if ($currentTerm === 'CBD' && in_array($assessment->recommended_top, ['NET_30', 'NET_14'], true)) {
            $policyComparison = "Kandidat Upgrade: Saat ini tercatat CBD, namun profil matematis layak diberikan {$verdictLabel}.";
        } elseif ($currentTerm !== '' && ! in_array($currentTerm, ['CBD', '0 DAYS'], true) && $assessment->recommended_top === 'CBD') {
            $policyComparison = "Peringatan Eksposur: Saat ini tercatat '{$customer->current_payment_term}', namun skor SPK menuntut peninjauan ulang ke CBD.";
        } else {
            $policyComparison = 'Selaras dengan kebijakan pembayaran historis (' . ($customer->current_payment_term ?: 'Tidak Tercatat') . ').';
        }

        return [
            'verdict_summary' => $verdictSummary,
            'strengths' => $strengths,
            'risk_factors' => $riskFactors,
            'policy_comparison' => $policyComparison,
        ];
    }
}
