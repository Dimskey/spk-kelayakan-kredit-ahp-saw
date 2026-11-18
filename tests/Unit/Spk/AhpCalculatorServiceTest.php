<?php

namespace Tests\Unit\Spk;

use App\Services\Spk\AhpCalculatorService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AhpCalculatorServiceTest extends TestCase
{
    private AhpCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AhpCalculatorService();
    }

    public function test_identity_matrix_yields_equal_weights_and_zero_cr(): void
    {
        $matrix = $this->service->buildReciprocalMatrix(5, []);
        $result = $this->service->calculate($matrix);

        $this->assertSame([0.2, 0.2, 0.2, 0.2, 0.2], $result['weights']);
        $this->assertEqualsWithDelta(5.0, $result['lambda_max'], 0.000001);
        $this->assertEqualsWithDelta(0.0, $result['ci'], 0.000001);
        $this->assertEqualsWithDelta(1.12, $result['ri'], 0.0001);
        $this->assertEqualsWithDelta(0.0, $result['cr'], 0.000001);
        $this->assertTrue($result['is_consistent']);
    }

    public function test_baseline_matrix_matches_expected_weights_and_consistency_ratio(): void
    {
        $matrix = $this->service->buildReciprocalMatrix(5, [
            0 => [1 => 0.5, 2 => 2.0, 3 => 3.0, 4 => 1.0],
            1 => [2 => 3.0, 3 => 4.0, 4 => 2.0],
            2 => [3 => 2.0, 4 => 0.5],
            3 => [4 => 1.0 / 3.0],
        ]);

        $result = $this->service->calculate($matrix);

        $this->assertEqualsWithDelta(0.214681, $result['weights'][0], 0.000002);
        $this->assertEqualsWithDelta(0.375063, $result['weights'][1], 0.000002);
        $this->assertEqualsWithDelta(0.121484, $result['weights'][2], 0.000002);
        $this->assertEqualsWithDelta(0.074090, $result['weights'][3], 0.000002);
        $this->assertEqualsWithDelta(0.214681, $result['weights'][4], 0.000002);
        $this->assertEqualsWithDelta(1.0, array_sum($result['weights']), 0.00001);
        $this->assertEqualsWithDelta(5.033170, $result['lambda_max'], 0.00001);
        $this->assertEqualsWithDelta(0.008293, $result['ci'], 0.00001);
        $this->assertEqualsWithDelta(0.007404, $result['cr'], 0.00001);
        $this->assertTrue($result['is_consistent']);
    }

    public function test_inconsistent_matrix_flags_consistency_failure_and_diagnoses_deviations(): void
    {
        // Deliberately circular / contradictory preferences: C1 >> C2 >> C3 >> C1
        $matrix = $this->service->buildReciprocalMatrix(5, [
            0 => [1 => 9.0, 2 => 1.0 / 9.0, 3 => 5.0, 4 => 1.0],
            1 => [2 => 9.0, 3 => 1.0, 4 => 7.0],
            2 => [3 => 1.0 / 5.0, 4 => 1.0 / 3.0],
            3 => [4 => 5.0],
        ]);

        $result = $this->service->calculate($matrix);

        $this->assertGreaterThan(0.10, $result['cr']);
        $this->assertFalse($result['is_consistent']);
        $this->assertNotEmpty($result['deviations']);
        $this->assertGreaterThan(1.0, $result['deviations'][0]['relative_error']);
    }

    public function test_non_reciprocal_matrix_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $badMatrix = [
            [1, 3, 1, 1, 1],
            [3, 1, 1, 1, 1], // should be 1/3
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
        ];

        $this->service->calculate($badMatrix);
    }
}
