<?php

namespace Tests\Feature;

use App\Models\AhpSession;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\CustomerAssessment;
use App\Services\Spk\AhpCalculatorService;
use App\Services\Spk\CsvCustomerImporterService;
use App\Services\Spk\SpkAssessmentOrchestratorService;
use Database\Seeders\SpkCriteriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SpkDashboardAndAhpWebTest extends TestCase
{
    use RefreshDatabase;

    private function seedSampleCustomersAndAssessments(): array
    {
        $this->seed(SpkCriteriaSeeder::class);

        $c1 = Customer::create([
            'customer_code' => 'C-1001',
            'customer_name' => 'PT PRIMA MITRA SEJAHTERA',
            'business_entity' => 'pt',
            'business_entity_name' => 'PT. PRIMA MITRA SEJAHTERA',
            'npwp' => '0503508210418000',
            'nib' => '1509230149162',
            'sppkp' => 'S-529/PKP/KPP.080603/2023',
            'pic' => 'Budi Santoso',
            'nik' => '3174050804770004',
            'img_ktp' => 'https://solusi-pack.com/uploads/DWG/reseller_request/ktp_budi.jpg',
            'customer_type' => 'reseller',
            'delivery_address' => 'Jl. Raya Industri No. 10 Bekasi 17530',
            'billing_address' => 'Jl. Raya Industri No. 10 Bekasi',
            'delivery_phone' => '08111386860',
            'email' => 'finance@primamitra.co.id',
            'current_payment_term' => 'CBD',
            'status' => 'Active',
        ]);

        $c2 = Customer::create([
            'customer_code' => 'C-1002',
            'customer_name' => 'TOKO MAJU MANDIRI',
            'business_entity' => 'individu',
            'business_entity_name' => 'TOKO MAJU MANDIRI',
            'npwp' => '0000000000000000',
            'nib' => 'null',
            'sppkp' => 'null',
            'pic' => 'null',
            'nik' => null,
            'img_ktp' => 'https://solusi-pack.com/',
            'customer_type' => 'enduser',
            'delivery_address' => 'Jl. Kenanga No. 1 Jakarta',
            'billing_address' => 'Jl. Melati No. 99 Surabaya',
            'delivery_phone' => '08123456789',
            'email' => 'null',
            'current_payment_term' => '30 Days',
            'status' => 'Active',
        ]);

        app(SpkAssessmentOrchestratorService::class)->recalculateForActiveSession();

        return [$c1->fresh(), $c2->fresh()];
    }

    public function test_dashboard_renders_level_0_overview_and_level_1_matrix(): void
    {
        [$c1, $c2] = $this->seedSampleCustomersAndAssessments();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Ikhtisar Kelayakan Kredit Pelanggan B2B');
        $response->assertSee('PT PRIMA MITRA SEJAHTERA');
        $response->assertSee('TOKO MAJU MANDIRI');
        $response->assertSee('C-1001');
        $response->assertSee('C-1002');
    }

    public function test_level_0_drilldown_and_policy_gap_filters_narrow_level_1_rows(): void
    {
        [$c1, $c2] = $this->seedSampleCustomersAndAssessments();

        // Filter by C1=5 & NET_30 should show PT PRIMA MITRA SEJAHTERA and exclude TOKO MAJU MANDIRI
        $net30Res = $this->get('/?c1=5&top=NET_30');
        $net30Res->assertStatus(200);
        $net30Res->assertSee('PT PRIMA MITRA SEJAHTERA');
        $net30Res->assertDontSee('TOKO MAJU MANDIRI');

        // Filter by policy_gap=upgrade_candidate (current CBD -> NET_30/NET_14)
        $upgradeRes = $this->get('/?policy_gap=upgrade_candidate');
        $upgradeRes->assertStatus(200);
        $upgradeRes->assertSee('PT PRIMA MITRA SEJAHTERA');
        $upgradeRes->assertDontSee('TOKO MAJU MANDIRI');

        // Filter by policy_gap=risk_exposure (current 30 Days -> SPK CBD)
        $riskRes = $this->get('/?policy_gap=risk_exposure');
        $riskRes->assertStatus(200);
        $riskRes->assertSee('TOKO MAJU MANDIRI');
        $riskRes->assertDontSee('PT PRIMA MITRA SEJAHTERA');
    }

    public function test_level_2_and_level_3_customer_dossier_returns_complete_audit_trace_json(): void
    {
        [$c1, $c2] = $this->seedSampleCustomersAndAssessments();

        $response = $this->getJson(route('spk.customers.dossier', $c1));

        $response->assertStatus(200)
            ->assertJsonPath('customer.customer_code', 'C-1001')
            ->assertJsonPath('assessment.rank', 1)
            ->assertJsonPath('assessment.recommended_top', 'NET_30')
            ->assertJsonPath('assessment.scores.C1', 5)
            ->assertJsonPath('assessment.scores.C2', 5)
            ->assertJsonPath('assessment.scores.C3', 5)
            ->assertJsonPath('assessment.scores.C4', 5)
            ->assertJsonPath('assessment.scores.C5', 1)
            ->assertJsonCount(5, 'criteria_details')
            ->assertJsonStructure([
                'customer',
                'assessment' => ['rank', 'total_population', 'preference_score', 'recommended_top', 'scores', 'normalized', 'weighted'],
                'criteria_details' => [
                    '*' => ['code', 'name', 'type', 'weight', 'score', 'normalized', 'weighted', 'rule_trace', 'saw_trace'],
                ],
                'underwriting_narrative' => ['verdict_summary', 'strengths', 'risk_factors', 'policy_comparison'],
            ]);
    }

    public function test_ahp_inspector_preview_and_apply_enforces_consistency_threshold(): void
    {
        $this->seedSampleCustomersAndAssessments();

        $inspectorPage = $this->get(route('spk.ahp.inspector'));
        $inspectorPage->assertStatus(200);
        $inspectorPage->assertSee('AHP Calculation');

        $ahpService = app(AhpCalculatorService::class);

        // 1. Preview & reject inconsistent matrix (CR > 0.10)
        $inconsistentMatrix = $ahpService->buildReciprocalMatrix(5, [
            0 => [1 => 9.0, 2 => 1.0 / 9.0, 3 => 5.0, 4 => 1.0],
            1 => [2 => 9.0, 3 => 1.0, 4 => 7.0],
            2 => [3 => 1.0 / 5.0, 4 => 1.0 / 3.0],
            3 => [4 => 5.0],
        ]);

        $previewFail = $this->postJson(route('spk.ahp.preview'), ['matrix' => $inconsistentMatrix]);
        $previewFail->assertStatus(200)->assertJsonPath('is_consistent', false);

        $applyFail = $this->postJson(route('spk.ahp.apply'), ['matrix' => $inconsistentMatrix]);
        $applyFail->assertStatus(422);

        // 2. Apply consistent matrix (CR <= 0.10) -> persists new session and recalculates SAW
        $consistentMatrix = $ahpService->buildReciprocalMatrix(5, [
            0 => [1 => 1.0, 2 => 3.0, 3 => 4.0, 4 => 1.0],
            1 => [2 => 3.0, 3 => 4.0, 4 => 1.0],
            2 => [3 => 2.0, 4 => 1.0 / 3.0],
            3 => [4 => 1.0 / 4.0],
        ]);

        $applyOk = $this->postJson(route('spk.ahp.apply'), [
            'matrix' => $consistentMatrix,
            'session_name' => 'Kebijakan Test Konsisten',
        ]);

        $applyOk->assertStatus(200)
            ->assertJsonPath('ahp.is_consistent', true)
            ->assertJsonPath('saw_summary.total', 2);

        $this->assertDatabaseHas('ahp_sessions', [
            'name' => 'Kebijakan Test Konsisten',
            'is_active' => true,
            'is_consistent' => true,
        ]);
        $this->assertEquals(2, AhpSession::count());
        $this->assertEquals(2, CustomerAssessment::where('ahp_session_id', AhpSession::where('is_active', true)->value('id'))->count());
    }

    public function test_csv_upload_endpoint_imports_and_evaluates_customers(): void
    {
        $this->seed(SpkCriteriaSeeder::class);

        $csvContent = implode("\n", [
            'CODE,NAME,TYPE,COMPANY,DELIVERY ADDRESS,deliveryPhone,billingAddress,pic,email,customerType,nik,npwp,sppkp,imgKtp,nib,businessEntity,businessEntityName,paymentTerm,STATUS',
            '"C-9001","BERKAH JAYA ABADI","reseller","SOPRA","Jl. Sudirman No 1 Jakarta 10220","081211112222","Jl. Sudirman No 1 Jakarta","Andi Wijaya","andi@berkah.co.id","reseller","3174010101850001","0503508210418001","S-100/PKP/2024","https://solusi-pack.com/uploads/ktp_andi.jpg","1509230149162","pt","PT. BERKAH JAYA ABADI","CBD","Active"',
        ]);

        $file = UploadedFile::fake()->createWithContent('customers_test.csv', $csvContent);

        $response = $this->post(route('spk.import.csv'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('spk.dashboard'));
        $this->assertDatabaseHas('customers', ['customer_code' => 'C-9001']);
        $this->assertDatabaseHas('customer_assessments', [
            'recommended_top' => 'NET_30',
            'c1_score' => 5,
            'c2_score' => 5,
            'c3_score' => 5,
            'c4_score' => 5,
            'c5_score' => 1,
        ]);
    }
}
