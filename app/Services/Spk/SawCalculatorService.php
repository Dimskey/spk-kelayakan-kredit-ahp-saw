<?php

namespace App\Services\Spk;

use InvalidArgumentException;

class SawCalculatorService
{
    public const DEFAULT_THRESHOLDS = [
        'net_30' => 0.80,
        'net_14' => 0.60,
    ];

    /**
     * Calculate SAW normalization, weighted preference score V_i, ranking, and ToP classification.
     *
     * @param  array<int, array{customer_id?: int|string, customer_code?: string, scores: array<string, int|float>}>  $items
     * @param  array<string, float>  $weights  Keyed by criterion code e.g. ['C1' => 0.30, ...]
     * @param  array<string, string>  $criteriaTypes  Keyed by criterion code e.g. ['C1' => 'benefit', 'C5' => 'cost']
     * @param  array{net_30: float, net_14: float}  $thresholds
     */
    public function calculate(
        array $items,
        array $weights,
        array $criteriaTypes,
        array $thresholds = self::DEFAULT_THRESHOLDS
    ): array {
        if ($items === []) {
            return [
                'extrema' => [],
                'thresholds' => $thresholds,
                'results' => [],
            ];
        }

        $criteriaCodes = array_keys($criteriaTypes);
        if ($criteriaCodes === []) {
            throw new InvalidArgumentException('Daftar kriteria SAW tidak boleh kosong.');
        }

        // Validate weights exist for every criterion
        foreach ($criteriaCodes as $code) {
            if (! array_key_exists($code, $weights)) {
                throw new InvalidArgumentException("Bobot untuk kriteria {$code} tidak ditemukan.");
            }
        }

        // 1. Find column extrema (max for benefit, min for cost)
        $extrema = [];
        foreach ($criteriaCodes as $code) {
            $values = array_map(function (array $item) use ($code): float {
                if (! isset($item['scores'][$code])) {
                    throw new InvalidArgumentException("Skor kriteria {$code} hilang pada salah satu item.");
                }
                $val = (float) $item['scores'][$code];
                if ($val <= 0.0) {
                    throw new InvalidArgumentException("Skor kriteria {$code} harus > 0 agar normalisasi SAW valid.");
                }

                return $val;
            }, $items);

            $type = strtolower((string) $criteriaTypes[$code]);
            $extrema[$code] = [
                'type' => $type,
                'operator' => $type === 'cost' ? 'min' : 'max',
                'value' => $type === 'cost' ? min($values) : max($values),
                'min' => min($values),
                'max' => max($values),
            ];
        }

        // 2. Normalize, apply weights, compute preference score V_i, and classify ToP
        $results = [];
        foreach ($items as $item) {
            $normalized = [];
            $weighted = [];
            $formulaTrace = [];
            $preferenceScore = 0.0;

            foreach ($criteriaCodes as $code) {
                $rawScore = (float) $item['scores'][$code];
                $weight = (float) $weights[$code];
                $type = $extrema[$code]['type'];
                $extVal = (float) $extrema[$code]['value'];

                $r = $type === 'cost'
                    ? ($extVal / $rawScore)
                    : ($rawScore / $extVal);

                $wr = $weight * $r;
                $preferenceScore += $wr;

                $normalized[$code] = round($r, 6);
                $weighted[$code] = round($wr, 6);
                $formulaTrace[$code] = [
                    'type' => $type,
                    'raw_score' => $rawScore,
                    'extremum_label' => $type === 'cost' ? 'min(X)' : 'max(X)',
                    'extremum_value' => $extVal,
                    'normalized_r' => round($r, 6),
                    'weight_w' => round($weight, 6),
                    'weighted_wr' => round($wr, 6),
                ];
            }

            $roundedV = round($preferenceScore, 6);
            $recommendedTop = $this->classifyToP($roundedV, $thresholds);

            $results[] = [
                'customer_id' => $item['customer_id'] ?? null,
                'customer_code' => $item['customer_code'] ?? '',
                'scores' => $item['scores'],
                'normalized' => $normalized,
                'weighted' => $weighted,
                'formula_trace' => $formulaTrace,
                'preference_score' => $roundedV,
                'recommended_top' => $recommendedTop,
            ];
        }

        // 3. Deterministic descending rank sort
        usort($results, function (array $a, array $b): int {
            $cmp = $b['preference_score'] <=> $a['preference_score'];
            if ($cmp !== 0) {
                return $cmp;
            }
            // Tie-breaker 1: Lower C5 fraud risk score
            $c5Cmp = ($a['scores']['C5'] ?? 5) <=> ($b['scores']['C5'] ?? 5);
            if ($c5Cmp !== 0) {
                return $c5Cmp;
            }
            // Tie-breaker 2: Stronger C1 business entity score
            $c1Cmp = ($b['scores']['C1'] ?? 1) <=> ($a['scores']['C1'] ?? 1);
            if ($c1Cmp !== 0) {
                return $c1Cmp;
            }

            return strcmp((string) $a['customer_code'], (string) $b['customer_code']);
        });

        foreach ($results as $idx => &$row) {
            $row['rank'] = $idx + 1;
        }
        unset($row);

        return [
            'extrema' => $extrema,
            'thresholds' => $thresholds,
            'results' => $results,
        ];
    }

    public function classifyToP(float $preferenceScore, array $thresholds = self::DEFAULT_THRESHOLDS): string
    {
        if ($preferenceScore >= (float) $thresholds['net_30']) {
            return 'NET_30';
        }
        if ($preferenceScore >= (float) $thresholds['net_14']) {
            return 'NET_14';
        }

        return 'CBD';
    }
}
