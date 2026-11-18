<?php

namespace App\Services\Spk;

use App\Models\AhpComparison;
use App\Models\AhpSession;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\CustomerAssessment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SpkAssessmentOrchestratorService
{
    public function __construct(
        private readonly CriteriaScoringRuleEngine $ruleEngine,
        private readonly AhpCalculatorService $ahpCalculator,
        private readonly SawCalculatorService $sawCalculator
    ) {}

    public function recalculateForActiveSession(array $thresholds = SawCalculatorService::DEFAULT_THRESHOLDS): array
    {
        $session = AhpSession::where('is_active', true)->first();
        if (! $session) {
            throw new RuntimeException('Belum ada sesi AHP aktif. Jalankan SpkCriteriaSeeder terlebih dahulu.');
        }

        $criteria = Criterion::orderBy('sort_order')->get();
        if ($criteria->count() !== 5) {
            throw new RuntimeException('Jumlah kriteria SPK harus tepat 5 (C1..C5).');
        }

        $weights = $criteria->pluck('weight', 'code')->toArray();
        $types = $criteria->pluck('type', 'code')->toArray();

        $customers = Customer::orderBy('id')->get();
        if ($customers->isEmpty()) {
            return [
                'total' => 0,
                'net_30' => 0,
                'net_14' => 0,
                'cbd' => 0,
                'ahp_session_id' => $session->id,
                'cr' => $session->cr,
            ];
        }

        $items = [];
        $tracesByCustomerId = [];

        foreach ($customers as $customer) {
            $evaluated = $this->ruleEngine->evaluate($customer);
            $tracesByCustomerId[$customer->id] = $evaluated['trace'];
            $items[] = [
                'customer_id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'scores' => $evaluated['scores'],
            ];
        }

        $sawOutput = $this->sawCalculator->calculate($items, $weights, $types, $thresholds);
        $now = now()->toDateTimeString();
        $rowsToUpsert = [];
        $counts = ['NET_30' => 0, 'NET_14' => 0, 'CBD' => 0];

        foreach ($sawOutput['results'] as $res) {
            $customerId = (int) $res['customer_id'];
            $counts[$res['recommended_top']] = ($counts[$res['recommended_top']] ?? 0) + 1;

            $fullTrace = $tracesByCustomerId[$customerId] ?? [];
            $fullTrace['saw'] = [
                'extrema' => $sawOutput['extrema'],
                'formula_trace' => $res['formula_trace'],
                'thresholds' => $sawOutput['thresholds'],
            ];

            $rowsToUpsert[] = [
                'customer_id' => $customerId,
                'ahp_session_id' => $session->id,
                'c1_score' => $res['scores']['C1'],
                'c2_score' => $res['scores']['C2'],
                'c3_score' => $res['scores']['C3'],
                'c4_score' => $res['scores']['C4'],
                'c5_score' => $res['scores']['C5'],
                'evaluation_trace' => json_encode($fullTrace, JSON_UNESCAPED_UNICODE),
                'r1' => $res['normalized']['C1'],
                'r2' => $res['normalized']['C2'],
                'r3' => $res['normalized']['C3'],
                'r4' => $res['normalized']['C4'],
                'r5' => $res['normalized']['C5'],
                'w1_r1' => $res['weighted']['C1'],
                'w2_r2' => $res['weighted']['C2'],
                'w3_r3' => $res['weighted']['C3'],
                'w4_r4' => $res['weighted']['C4'],
                'w5_r5' => $res['weighted']['C5'],
                'preference_score' => $res['preference_score'],
                'rank' => $res['rank'],
                'recommended_top' => $res['recommended_top'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $updateColumns = array_values(array_diff(array_keys($rowsToUpsert[0]), ['customer_id', 'ahp_session_id', 'created_at']));

        DB::transaction(function () use ($rowsToUpsert, $updateColumns): void {
            foreach (array_chunk($rowsToUpsert, 300) as $chunk) {
                CustomerAssessment::upsert($chunk, ['customer_id', 'ahp_session_id'], $updateColumns);
            }
        });

        return [
            'total' => count($rowsToUpsert),
            'net_30' => $counts['NET_30'],
            'net_14' => $counts['NET_14'],
            'cbd' => $counts['CBD'],
            'ahp_session_id' => $session->id,
            'cr' => $session->cr,
        ];
    }

    public function applyAhpMatrix(
        array $pairwiseMatrix,
        string $sessionName = 'Simulasi Kebijakan Manajemen',
        array $thresholds = SawCalculatorService::DEFAULT_THRESHOLDS
    ): array {
        $ahpResult = $this->ahpCalculator->calculate($pairwiseMatrix);

        if (! $ahpResult['is_consistent']) {
            throw new InvalidArgumentException(
                sprintf(
                    'Matriks AHP tidak konsisten (CR = %.4f > 0.1000). Perbaiki perbandingan kriteria sebelum menyimpan bobot aktif.',
                    $ahpResult['cr']
                )
            );
        }

        $criteria = Criterion::orderBy('sort_order')->get();
        if ($criteria->count() !== $ahpResult['matrix_size']) {
            throw new InvalidArgumentException('Ukuran matriks tidak sesuai dengan jumlah kriteria aktif.');
        }

        $session = DB::transaction(function () use ($ahpResult, $criteria, $sessionName): AhpSession {
            $weightsSnapshot = [];
            foreach ($criteria as $index => $criterion) {
                $weight = $ahpResult['weights'][$index];
                $criterion->update(['weight' => $weight]);
                $weightsSnapshot[$criterion->code] = $weight;
            }

            AhpSession::where('is_active', true)->update(['is_active' => false]);

            $newSession = AhpSession::create([
                'name' => $sessionName,
                'matrix_size' => $ahpResult['matrix_size'],
                'lambda_max' => $ahpResult['lambda_max'],
                'ci' => $ahpResult['ci'],
                'ri' => $ahpResult['ri'],
                'cr' => $ahpResult['cr'],
                'is_consistent' => true,
                'raw_matrix_snapshot' => $ahpResult['raw_matrix'],
                'normalized_matrix_snapshot' => $ahpResult['normalized_matrix'],
                'weights_snapshot' => $weightsSnapshot,
                'is_active' => true,
            ]);

            $comparisonRows = [];
            $now = now()->toDateTimeString();
            foreach ($criteria as $rowIdx => $rowCriterion) {
                foreach ($criteria as $colIdx => $colCriterion) {
                    $comparisonRows[] = [
                        'ahp_session_id' => $newSession->id,
                        'criterion_row_id' => $rowCriterion->id,
                        'criterion_col_id' => $colCriterion->id,
                        'value' => $ahpResult['raw_matrix'][$rowIdx][$colIdx],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            AhpComparison::insert($comparisonRows);

            return $newSession;
        });

        $sawSummary = $this->recalculateForActiveSession($thresholds);

        return [
            'ahp' => $ahpResult,
            'session' => $session,
            'saw_summary' => $sawSummary,
        ];
    }
}
