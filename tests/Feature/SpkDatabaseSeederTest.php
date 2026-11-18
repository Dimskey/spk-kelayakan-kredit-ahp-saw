<?php

namespace Tests\Feature;

use App\Models\AhpComparison;
use App\Models\AhpSession;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\CustomerAssessment;
use App\Services\Spk\CriteriaScoringRuleEngine;
use App\Services\Spk\SawCalculatorService;
use Database\Seeders\SpkCriteriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpkDatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_spk_criteria_seeder_populates_consistent_active_session_and_weights(): void
    {
        $this->seed(SpkCriteriaSeeder::class);

        $this->assertDatabaseCount('criteria', 5);
        $this->assertDatabaseCount('ahp_sessions', 1);
        $this->assertDatabaseCount('ahp_comparisons', 25);

        $session = AhpSession::where('is_active', true)->first();
        $this->assertNotNull($session);
        $this->assertTrue($session->is_consistent);
        $this->assertLessThanOrEqual(0.10, $session->cr);

        $weightSum = Criterion::sum('weight');
        $this->assertEqualsWithDelta(1.0, $weightSum, 0.0001);
    }

    public function test_customer_assessment_pipeline_integrates_rule_engine_and_saw_with_models(): void
    {
        $this->seed(SpkCriteriaSeeder::class);

        $session = AhpSession::where('is_active', true)->firstOrFail();
        $criteria = Criterion::orderBy('sort_order')->get();
        $weights = $criteria->pluck('weight', 'code')->toArray();
        $types = $criteria->pluck('type', 'code')->toArray();

        $customer = Customer::create([
            'customer_code' => 'C-02464',
            'customer_name' => 'CIPTA RASA FOOD',
            'business_entity' => 'pt',
            'business_entity_name' => 'PT. CIPTA RASA FOOD',
            'npwp' => '0503508210418000',
            'nib' => '1509230149162',
            'sppkp' => 'S-529/PKP/KPP.080603/2023',
            'pic' => 'CIPTA RASA FOOD',
            'nik' => null,
            'img_ktp' => 'https://solusi-pack.com/uploads/DWG/reseller_request/npwp_pt_cipta_rasa_food_9e4a7613f9a36bf33e92196b23c9130e.pdf',
            'customer_type' => 'enduser',
            'delivery_address' => 'Jalan Raya Selembaran 99, Blok Cengklong Kosambi Kabupaten Tangerang Banten 15213',
            'billing_address' => 'Jalan Raya Selembaran 99, Blok Cengklong Kosambi Kabupaten Tangerang Banten ',
            'delivery_phone' => '08111386860',
            'email' => 'ciptarasafoodadm@gmail.com',
            'current_payment_term' => 'CBD',
            'status' => 'Active',
        ]);

        $ruleEngine = app(CriteriaScoringRuleEngine::class);
        $evaluated = $ruleEngine->evaluate($customer);

        $sawService = app(SawCalculatorService::class);
        $sawOutput = $sawService->calculate([
            [
                'customer_id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'scores' => $evaluated['scores'],
            ],
        ], $weights, $types);

        $row = $sawOutput['results'][0];

        $assessment = CustomerAssessment::create([
            'customer_id' => $customer->id,
            'ahp_session_id' => $session->id,
            'c1_score' => $evaluated['scores']['C1'],
            'c2_score' => $evaluated['scores']['C2'],
            'c3_score' => $evaluated['scores']['C3'],
            'c4_score' => $evaluated['scores']['C4'],
            'c5_score' => $evaluated['scores']['C5'],
            'evaluation_trace' => $evaluated['trace'],
            'r1' => $row['normalized']['C1'],
            'r2' => $row['normalized']['C2'],
            'r3' => $row['normalized']['C3'],
            'r4' => $row['normalized']['C4'],
            'r5' => $row['normalized']['C5'],
            'w1_r1' => $row['weighted']['C1'],
            'w2_r2' => $row['weighted']['C2'],
            'w3_r3' => $row['weighted']['C3'],
            'w4_r4' => $row['weighted']['C4'],
            'w5_r5' => $row['weighted']['C5'],
            'preference_score' => $row['preference_score'],
            'rank' => $row['rank'],
            'recommended_top' => $row['recommended_top'],
        ]);

        $this->assertSame(5, $assessment->c1_score);
        $this->assertSame(5, $assessment->c2_score);
        $this->assertSame(3, $assessment->c3_score);
        $this->assertSame(3, $assessment->c4_score);
        $this->assertSame(1, $assessment->c5_score);
        $this->assertEquals(1, $assessment->rank);
        $this->assertSame('NET_30', $assessment->recommended_top);
        $this->assertSame($assessment->id, $customer->activeAssessment->id);
        $this->assertCount(25, AhpComparison::where('ahp_session_id', $session->id)->get());
    }
}
