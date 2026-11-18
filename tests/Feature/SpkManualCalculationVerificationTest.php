<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAssessment;
use App\Services\Spk\SpkAssessmentOrchestratorService;
use Database\Seeders\SpkCriteriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpkManualCalculationVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_matches_manual_ahp_saw_calculation_across_six_real_customer_archetypes(): void
    {
        $this->seed(SpkCriteriaSeeder::class);

        $samples = [
            [
                'customer_code' => 'C-01709',
                'customer_name' => 'KEMBANG BULAN',
                'business_entity' => 'pt',
                'npwp' => '0011371259605000',
                'nib' => '9120009491319',
                'sppkp' => 'PEM-01157/WPJ.11/KP.0203/2012',
                'pic' => 'Kembang Bulan Surabaya',
                'nik' => '0011371259605000',
                'img_ktp' => 'https://solusi-pack.com/uploads/DWG/reseller_request/ktp_kembang.jpg',
                'customer_type' => 'enduser',
                'delivery_address' => 'Jl Demak 275, Kel. Dupak, Kec. Krembangan, Kota Surabaya 60179',
                'billing_address' => 'Jl Demak 275, Kel. Dupak, Kec. Krembangan, Kota Surabaya 60179',
                'delivery_phone' => '085648806908',
                'email' => 'febriandp.kbf@yahoo.com',
                'current_payment_term' => 'CBD',
                'expected_scores' => [5, 5, 5, 3, 1],
                'expected_v' => 0.970363,
                'expected_top' => 'NET_30',
            ],
            [
                'customer_code' => 'C-02368',
                'customer_name' => 'HENATO INDAH PERKASA',
                'business_entity' => 'pt',
                'npwp' => '0613002849215000',
                'nib' => '1710220087795',
                'sppkp' => '-',
                'pic' => 'PT. HENATO IDAH PERKASA',
                'nik' => null,
                'img_ktp' => 'https://solusi-pack.com/uploads/DWG/reseller_request/ktp_jovii.pdf',
                'customer_type' => 'reseller',
                'delivery_address' => 'Union industrial park, blok g nomor 17 Batam 29462',
                'billing_address' => 'Union industrial park, blok g nomor 17 Batam 29462',
                'delivery_phone' => '081268891399',
                'email' => 'Purchasing01@henato.co.id',
                'current_payment_term' => 'CBD',
                'expected_scores' => [5, 3, 3, 5, 1],
                'expected_v' => 0.801380,
                'expected_top' => 'NET_30',
            ],
            [
                'customer_code' => 'C-02351',
                'customer_name' => 'FASTRATA BUANA',
                'business_entity' => 'pt',
                'npwp' => '0015480486007000',
                'nib' => '8120005901306',
                'sppkp' => 'PEM-00165/WPJ.20/KP.0703/2012',
                'pic' => 'PT Fastrata Buana (KAG)',
                'nik' => null,
                'img_ktp' => 'https://solusi-pack.com/uploads/DWG/reseller_request/ktp_fastrata.pdf',
                'customer_type' => 'enduser',
                'delivery_address' => 'Jl. Taman jatibaru barat no.1, Gambir Jakarta Pusat 10150',
                'billing_address' => 'Jl. Suci, 75, Ciracas Jakarta Timur 13750',
                'delivery_phone' => '088293804360',
                'email' => 'ryan.hakim@kapalapi.co.id',
                'current_payment_term' => '30 Days',
                'expected_scores' => [5, 5, 3, 3, 3],
                'expected_v' => 0.778649,
                'expected_top' => 'NET_14',
            ],
            [
                'customer_code' => 'C-02300',
                'customer_name' => 'HOMIA KREASI KARYA YAKIN',
                'business_entity' => 'pt',
                'npwp' => '0109744953201000',
                'nib' => 'null',
                'sppkp' => 'null',
                'pic' => 'henny henny',
                'nik' => '1371030711640003',
                'img_ktp' => 'https://solusi-pack.com/uploads/DWG/reseller_request/ktp_charli.JPG',
                'customer_type' => 'enduser',
                'delivery_address' => 'Jln niaga no 273 Padang Barat Kota padang 25211',
                'billing_address' => 'Jln niaga no. 273 rt. 001 rw. 004 kampung pondok Padang Barat Kota padang',
                'delivery_phone' => '081365131998',
                'email' => 'ingwha80@gmail.com',
                'current_payment_term' => 'CBD',
                'expected_scores' => [5, 2, 5, 3, 3],
                'expected_v' => 0.602205,
                'expected_top' => 'NET_14',
            ],
            [
                'customer_code' => 'C-00872',
                'customer_name' => 'BAHAGIA BERKAT SCARLET',
                'business_entity' => 'pt',
                'npwp' => '0926043167028000',
                'nib' => 'null',
                'sppkp' => 'null',
                'pic' => 'Eric',
                'nik' => null,
                'img_ktp' => 'null',
                'customer_type' => 'enduser',
                'delivery_address' => 'Jalan Cideng Barat No. 62 B Gambir Jakarta Pusat DKI Jakarta',
                'billing_address' => 'Jalan Cideng Barat No. 62 B Gambir Jakarta Pusat DKI Jakarta',
                'delivery_phone' => '08119883384',
                'email' => null,
                'current_payment_term' => 'CBD',
                'expected_scores' => [5, 2, 3, 3, 3],
                'expected_v' => 0.553611,
                'expected_top' => 'CBD',
            ],
            [
                'customer_code' => 'C-01587',
                'customer_name' => 'ADIWINATA SATYA RAHARDJA',
                'business_entity' => 'individu',
                'npwp' => 'null',
                'nib' => 'null',
                'sppkp' => 'null',
                'pic' => null,
                'nik' => null,
                'img_ktp' => 'null',
                'customer_type' => 'enduser',
                'delivery_address' => 'Jl. Merdeka No 1 Jakarta',
                'billing_address' => 'Jl. Sudirman No 99 Bandung',
                'delivery_phone' => '08123456789',
                'email' => 'null',
                'current_payment_term' => 'CBD',
                'expected_scores' => [1, 1, 1, 3, 5],
                'expected_v' => 0.229636,
                'expected_top' => 'CBD',
            ],
        ];

        foreach ($samples as $sample) {
            $payload = $sample;
            unset($payload['expected_scores'], $payload['expected_v'], $payload['expected_top']);
            Customer::create($payload);
        }

        app(SpkAssessmentOrchestratorService::class)->recalculateForActiveSession();

        foreach ($samples as $idx => $sample) {
            $customer = Customer::where('customer_code', $sample['customer_code'])->firstOrFail();
            /** @var CustomerAssessment $assessment */
            $assessment = $customer->activeAssessment;

            $this->assertSame($idx + 1, $assessment->rank);
            $this->assertSame(
                $sample['expected_scores'],
                [$assessment->c1_score, $assessment->c2_score, $assessment->c3_score, $assessment->c4_score, $assessment->c5_score]
            );
            $this->assertEqualsWithDelta($sample['expected_v'], $assessment->preference_score, 0.000001);
            $this->assertSame($sample['expected_top'], $assessment->recommended_top);
        }
    }

    public function test_export_csv_endpoint_streams_filtered_and_full_ranked_results(): void
    {
        $this->seed(SpkCriteriaSeeder::class);

        Customer::create([
            'customer_code' => 'C-EXP-1',
            'customer_name' => 'PT EKSPOR UTAMA',
            'business_entity' => 'pt',
            'npwp' => '0011371259605000',
            'nib' => '9120009491319',
            'sppkp' => 'PEM-01157/WPJ.11/KP.0203/2012',
            'pic' => 'Andi',
            'nik' => '3174050804770004',
            'img_ktp' => 'https://solusi-pack.com/uploads/ktp_andi.jpg',
            'customer_type' => 'reseller',
            'delivery_address' => 'Jl Industri 1 Bekasi',
            'billing_address' => 'Jl Industri 1 Bekasi',
            'delivery_phone' => '08123456789',
            'email' => 'andi@ekspor.co.id',
            'current_payment_term' => 'CBD',
        ]);

        Customer::create([
            'customer_code' => 'C-EXP-2',
            'customer_name' => 'INDIVIDU TUNAI',
            'business_entity' => 'individu',
            'customer_type' => 'enduser',
            'current_payment_term' => 'CBD',
        ]);

        app(SpkAssessmentOrchestratorService::class)->recalculateForActiveSession();

        $res = $this->get('/export-csv?top=NET_30');
        $res->assertStatus(200);
        $this->assertStringStartsWith('text/csv', (string) $res->headers->get('content-type'));

        $csvOutput = $res->streamedContent();
        $this->assertStringContainsString('C-EXP-1', $csvOutput);
        $this->assertStringContainsString('NET_30', $csvOutput);
        $this->assertStringNotContainsString('C-EXP-2', $csvOutput);
    }
}
