<?php

namespace App\Services\Spk;

use InvalidArgumentException;

class AhpCalculatorService
{
    public const CONSISTENCY_THRESHOLD = 0.10;

    public const RANDOM_INDEX = [
        1 => 0.00,
        2 => 0.00,
        3 => 0.58,
        4 => 0.90,
        5 => 1.12,
        6 => 1.24,
        7 => 1.32,
        8 => 1.41,
        9 => 1.45,
        10 => 1.49,
    ];

    /**
     * Calculate AHP normalized matrix, priority weights, eigenvalue, CI, RI, CR, and inconsistency diagnostics.
     *
     * @param  array<int, array<int, float|int>>  $pairwiseMatrix
     */
    public function calculate(array $pairwiseMatrix): array
    {
        $matrix = array_values(array_map('array_values', $pairwiseMatrix));
        $n = count($matrix);

        if ($n < 2 || $n > 10) {
            throw new InvalidArgumentException("Ukuran matriks AHP harus antara 2x2 hingga 10x10 (diterima: {$n}).");
        }

        foreach ($matrix as $i => $row) {
            if (count($row) !== $n) {
                throw new InvalidArgumentException("Matriks AHP harus berbentuk persegi {$n}x{$n}.");
            }
            foreach ($row as $j => $val) {
                if (! is_numeric($val) || (float) $val <= 0.0) {
                    throw new InvalidArgumentException("Nilai sel matriks [{$i}][{$j}] harus bilangan positif.");
                }
                $matrix[$i][$j] = (float) $val;
            }
        }

        // Validate diagonal = 1 and reciprocal symmetry
        for ($i = 0; $i < $n; $i++) {
            if (abs($matrix[$i][$i] - 1.0) > 0.001) {
                throw new InvalidArgumentException("Elemen diagonal [{$i}][{$i}] harus bernilai 1.");
            }
            for ($j = $i + 1; $j < $n; $j++) {
                $product = $matrix[$i][$j] * $matrix[$j][$i];
                if (abs($product - 1.0) > 0.02) {
                    throw new InvalidArgumentException(
                        "Matriks harus resiprokal: [{$i}][{$j}] * [{$j}][{$i}] = {$product} (seharusnya 1.0)."
                    );
                }
            }
        }

        // 1. Column sums
        $columnSums = array_fill(0, $n, 0.0);
        for ($j = 0; $j < $n; $j++) {
            for ($i = 0; $i < $n; $i++) {
                $columnSums[$j] += $matrix[$i][$j];
            }
        }

        // 2. Normalized matrix
        $normalizedMatrix = [];
        for ($i = 0; $i < $n; $i++) {
            $normalizedMatrix[$i] = [];
            for ($j = 0; $j < $n; $j++) {
                $normalizedMatrix[$i][$j] = $matrix[$i][$j] / $columnSums[$j];
            }
        }

        // 3. Priority weights (row average of normalized matrix)
        $weights = [];
        for ($i = 0; $i < $n; $i++) {
            $weights[$i] = array_sum($normalizedMatrix[$i]) / $n;
        }

        // 4. Weighted sum vector (A * w) and Consistency vector ((A * w) / w)
        $weightedSums = [];
        $consistencyVector = [];
        for ($i = 0; $i < $n; $i++) {
            $sum = 0.0;
            for ($j = 0; $j < $n; $j++) {
                $sum += $matrix[$i][$j] * $weights[$j];
            }
            $weightedSums[$i] = $sum;
            $consistencyVector[$i] = $sum / $weights[$i];
        }

        // 5. Lambda max, CI, RI, CR
        $lambdaMax = array_sum($consistencyVector) / $n;
        $ci = $n > 1 ? ($lambdaMax - $n) / ($n - 1) : 0.0;
        // Clamp tiny negative floating-point artifact (e.g. -1e-16) to 0
        if ($ci < 0.0 && abs($ci) < 1e-9) {
            $ci = 0.0;
        }
        $ri = self::RANDOM_INDEX[$n];
        $cr = $ri > 0.0 ? ($ci / $ri) : 0.0;
        $isConsistent = $cr <= self::CONSISTENCY_THRESHOLD;

        // 6. Pairwise deviation diagnostics for inconsistent matrices
        $deviations = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $expectedRatio = $weights[$i] / $weights[$j];
                $actual = $matrix[$i][$j];
                $ratioError = max($actual / $expectedRatio, $expectedRatio / $actual);
                $deviations[] = [
                    'row_index' => $i,
                    'col_index' => $j,
                    'actual_value' => round($actual, 6),
                    'implied_ratio' => round($expectedRatio, 6),
                    'relative_error' => round($ratioError, 6),
                ];
            }
        }
        usort($deviations, fn ($a, $b) => $b['relative_error'] <=> $a['relative_error']);

        return [
            'matrix_size' => $n,
            'raw_matrix' => $this->roundMatrix($matrix, 6),
            'column_sums' => array_map(fn ($v) => round($v, 6), $columnSums),
            'normalized_matrix' => $this->roundMatrix($normalizedMatrix, 6),
            'weights' => array_map(fn ($v) => round($v, 6), $weights),
            'weighted_sums' => array_map(fn ($v) => round($v, 6), $weightedSums),
            'consistency_vector' => array_map(fn ($v) => round($v, 6), $consistencyVector),
            'lambda_max' => round($lambdaMax, 6),
            'ci' => round($ci, 6),
            'ri' => $ri,
            'cr' => round($cr, 6),
            'is_consistent' => $isConsistent,
            'deviations' => $deviations,
        ];
    }

    /**
     * Build a complete reciprocal NxN matrix from upper-triangle pairs.
     *
     * @param  array<int, array<int, float|int>>  $upperPairs  Map of [$i][$j] => value for i < j
     */
    public function buildReciprocalMatrix(int $n, array $upperPairs): array
    {
        $matrix = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    $matrix[$i][$j] = 1.0;
                } elseif ($i < $j) {
                    $val = (float) ($upperPairs[$i][$j] ?? 1.0);
                    $matrix[$i][$j] = $val;
                    $matrix[$j][$i] = 1.0 / $val;
                }
            }
        }

        return $matrix;
    }

    private function roundMatrix(array $matrix, int $precision): array
    {
        return array_map(
            fn (array $row) => array_map(fn ($v) => round((float) $v, $precision), $row),
            $matrix
        );
    }
}
