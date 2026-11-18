<?php

namespace Tests\Unit\Spk;

use App\Services\Spk\SawCalculatorService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SawCalculatorServiceTest extends TestCase
{
    private SawCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SawCalculatorService();
    }

    public function test_saw_normalizes_benefit_and_cost_criteria_and_ranks_correctly(): void
    {
        $weights = [
            'C1' => 0.25,
            'C2' => 0.35,
            'C3' => 0.15,
            'C4' => 0.10,
            'C5' => 0.15,
        ];

        $types = [
            'C1' => 'benefit',
            'C2' => 'benefit',
            'C3' => 'benefit',
            'C4' => 'benefit',
            'C5' => 'cost',
        ];

        $items = [
            [
                'customer_id' => 1,
                'customer_code' => 'C-001',
                'scores' => ['C1' => 5, 'C2' => 5, 'C3' => 5, 'C4' => 5, 'C5' => 1], // Ideal candidate -> V = 1.0
            ],
            [
                'customer_id' => 2,
                'customer_code' => 'C-002',
                'scores' => ['C1' => 4, 'C2' => 3, 'C3' => 4, 'C4' => 5, 'C5' => 3],
            ],
            [
                'customer_id' => 3,
                'customer_code' => 'C-003',
                'scores' => ['C1' => 1, 'C2' => 1, 'C3' => 1, 'C4' => 3, 'C5' => 5], // Worst candidate
            ],
        ];

        $output = $this->service->calculate($items, $weights, $types);
        $results = $output['results'];

        $this->assertCount(3, $results);

        // Extrema check
        $this->assertSame(5.0, $output['extrema']['C1']['value']);
        $this->assertSame(1.0, $output['extrema']['C5']['value']);

        // Rank 1: C-001 (V = 1.0, NET_30)
        $this->assertSame('C-001', $results[0]['customer_code']);
        $this->assertSame(1, $results[0]['rank']);
        $this->assertEqualsWithDelta(1.0, $results[0]['preference_score'], 0.000001);
        $this->assertSame('NET_30', $results[0]['recommended_top']);

        // Rank 2: C-002 manual check:
        // r1 = 4/5 = 0.8 -> 0.25*0.8 = 0.20
        // r2 = 3/5 = 0.6 -> 0.35*0.6 = 0.21
        // r3 = 4/5 = 0.8 -> 0.15*0.8 = 0.12
        // r4 = 5/5 = 1.0 -> 0.10*1.0 = 0.10
        // r5 = 1/3 = 0.333333 -> 0.15*(1/3) = 0.05
        // Total V = 0.20 + 0.21 + 0.12 + 0.10 + 0.05 = 0.68 -> NET_14
        $this->assertSame('C-002', $results[1]['customer_code']);
        $this->assertSame(2, $results[1]['rank']);
        $this->assertEqualsWithDelta(0.68, $results[1]['preference_score'], 0.000001);
        $this->assertSame('NET_14', $results[1]['recommended_top']);

        // Rank 3: C-003 manual check:
        // r1=0.2(0.05), r2=0.2(0.07), r3=0.2(0.03), r4=0.6(0.06), r5=1/5=0.2(0.03) => V = 0.24 -> CBD
        $this->assertSame('C-003', $results[2]['customer_code']);
        $this->assertSame(3, $results[2]['rank']);
        $this->assertEqualsWithDelta(0.24, $results[2]['preference_score'], 0.000001);
        $this->assertSame('CBD', $results[2]['recommended_top']);
    }

    public function test_custom_thresholds_change_top_classification(): void
    {
        $weights = ['C1' => 1.0];
        $types = ['C1' => 'benefit'];
        $items = [
            ['customer_code' => 'A', 'scores' => ['C1' => 5]], // r=1.0 -> V=1.0
            ['customer_code' => 'B', 'scores' => ['C1' => 4]], // r=0.8 -> V=0.8
        ];

        $strict = $this->service->calculate($items, $weights, $types, ['net_30' => 0.85, 'net_14' => 0.70]);
        $this->assertSame('NET_30', $strict['results'][0]['recommended_top']);
        $this->assertSame('NET_14', $strict['results'][1]['recommended_top']);
    }

    public function test_empty_items_returns_empty_results(): void
    {
        $output = $this->service->calculate([], ['C1' => 1.0], ['C1' => 'benefit']);
        $this->assertSame([], $output['results']);
    }

    public function test_zero_score_throws_exception_to_prevent_division_by_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->calculate(
            [['customer_code' => 'X', 'scores' => ['C5' => 0]]],
            ['C5' => 1.0],
            ['C5' => 'cost']
        );
    }
}
