<?php

namespace Tests\Unit\Spk;

use App\Services\Spk\CriteriaScoringRuleEngine;
use PHPUnit\Framework\TestCase;

class CriteriaScoringRuleEngineTest extends TestCase
{
    private CriteriaScoringRuleEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new CriteriaScoringRuleEngine();
    }

    public function test_c1_business_entity_scoring_and_fallback_detection(): void
    {
        $this->assertSame(5, $this->engine->evaluate(['businessEntity' => 'pt'])['scores']['C1']);
        $this->assertSame(4, $this->engine->evaluate(['businessEntity' => 'CV'])['scores']['C1']);
        $this->assertSame(3, $this->engine->evaluate(['businessEntity' => 'Koperasi'])['scores']['C1']);
        $this->assertSame(2, $this->engine->evaluate(['businessEntity' => 'Toko'])['scores']['C1']);
        $this->assertSame(1, $this->engine->evaluate(['businessEntity' => 'individu', 'NAME' => 'BUDI SANTOSO'])['scores']['C1']);

        // Real CSV edge case: businessEntity='individu' while businessEntityName starts with 'PERSEROAN TERBATAS' or 'PT.'
        $fallbackPt = $this->engine->evaluate([
            'businessEntity' => 'individu',
            'businessEntityName' => 'PERSEROAN TERBATAS - BADAN UNITED FAMILY FOOD',
            'NAME' => 'UNITED FAMILY FOOD',
        ]);
        $this->assertSame(5, $fallbackPt['scores']['C1']);
        $this->assertSame('businessEntityName/NAME (fallback)', $fallbackPt['trace']['C1']['source_used']);
    }

    public function test_c2_legality_document_counting_ignores_dirty_placeholders(): void
    {
        // 3 valid docs -> 5
        $three = $this->engine->evaluate([
            'npwp' => '0503508210418000',
            'nib' => '1509230149162',
            'sppkp' => 'S-529/PKP/KPP.080603/2023',
        ]);
        $this->assertSame(5, $three['scores']['C2']);
        $this->assertSame(3, $three['trace']['C2']['valid_count']);

        // 2 valid docs (with dirty 'null' nib) -> 3
        $two = $this->engine->evaluate([
            'npwp' => '0500113170444000',
            'nib' => 'null',
            'sppkp' => 'S-283/PKP/KPP.091403/2024',
        ]);
        $this->assertSame(3, $two['scores']['C2']);

        // 1 valid doc (all-zero NIB and '0' SPPKP must be ignored) -> 2
        $one = $this->engine->evaluate([
            'npwp' => '0019844885435000',
            'nib' => '0000000000',
            'sppkp' => '0',
        ]);
        $this->assertSame(2, $one['scores']['C2']);

        // 0 valid docs (all-zero 16-digit NPWP, 'null', '-') -> 1
        $zero = $this->engine->evaluate([
            'npwp' => '0000000000000000',
            'nib' => 'null',
            'sppkp' => '-',
        ]);
        $this->assertSame(1, $zero['scores']['C2']);
    }

    public function test_c3_pic_identity_validation_rejects_bare_domain_ktp_url(): void
    {
        // Complete PIC, NIK, and real KTP upload path -> 5
        $complete = $this->engine->evaluate([
            'pic' => 'Ronny Kurniawan',
            'nik' => '3578080310790002',
            'imgKtp' => 'https://solusi-pack.com/uploads/DWG/reseller_request/ktp_ronny.jpg',
        ]);
        $this->assertSame(5, $complete['scores']['C3']);

        // Bare domain placeholder URL must NOT count as valid KTP image -> 4 (PIC + NIK only)
        $bareDomain = $this->engine->evaluate([
            'pic' => 'Meirosita',
            'nik' => '3603346505660004',
            'imgKtp' => 'https://solusi-pack.com/',
        ]);
        $this->assertSame(4, $bareDomain['scores']['C3']);
        $this->assertFalse($bareDomain['trace']['C3']['attributes']['imgKtp']['is_valid']);

        // Only KTP photo -> 3
        $onlyKtp = $this->engine->evaluate([
            'pic' => 'null',
            'nik' => '',
            'imgKtp' => 'https://solusi-pack.com/uploads/DWG/reseller_request/ktp_test.jpg',
        ]);
        $this->assertSame(3, $onlyKtp['scores']['C3']);

        // All empty -> 1
        $empty = $this->engine->evaluate([
            'pic' => '-',
            'nik' => 'null',
            'imgKtp' => 'https://solusi-pack.com/',
        ]);
        $this->assertSame(1, $empty['scores']['C3']);
    }

    public function test_c4_customer_type_repeat_order_scoring(): void
    {
        $this->assertSame(5, $this->engine->evaluate(['customerType' => 'Reseller'])['scores']['C4']);
        $this->assertSame(3, $this->engine->evaluate(['customerType' => 'enduser'])['scores']['C4']);
        $this->assertSame(3, $this->engine->evaluate(['customerType' => 'affiliasi'])['scores']['C4']);
    }

    public function test_c5_fraud_anomaly_risk_scoring_handles_postal_suffix_and_contact_checks(): void
    {
        // Same address (with trailing postal code only on delivery) + complete contact -> 1 (Low risk)
        $lowRisk = $this->engine->evaluate([
            'DELIVERY ADDRESS' => 'Jalan Raya Selembaran 99, Kosambi Kabupaten Tangerang Banten 15213',
            'billingAddress' => 'Jalan Raya Selembaran 99, Kosambi Kabupaten Tangerang Banten ',
            'deliveryPhone' => '08111386860',
            'email' => 'ciptarasafoodadm@gmail.com',
        ]);
        $this->assertSame(1, $lowRisk['scores']['C5']);
        $this->assertTrue($lowRisk['trace']['C5']['checks']['is_same_address']);
        $this->assertTrue($lowRisk['trace']['C5']['checks']['is_contact_complete']);

        // Different address + complete contact -> 3 (Medium risk)
        $diffAddrCompleteContact = $this->engine->evaluate([
            'DELIVERY ADDRESS' => 'Nginden Intan Timur VII D6 No 15 Surabaya 60118',
            'billingAddress' => 'Bratang Binangun 3/45 Surabaya',
            'deliveryPhone' => '081927788817',
            'email' => 'majujayaplastik1@gmail.com',
        ]);
        $this->assertSame(3, $diffAddrCompleteContact['scores']['C5']);

        // Same address + incomplete contact -> 3 (Medium risk)
        $sameAddrIncompleteContact = $this->engine->evaluate([
            'DELIVERY ADDRESS' => 'Jl. Bojongkoneng No. 9 Bandung 40124',
            'billingAddress' => 'Jl. Bojongkoneng No. 9 Bandung 40124',
            'deliveryPhone' => '087826440800',
            'email' => '',
        ]);
        $this->assertSame(3, $sameAddrIncompleteContact['scores']['C5']);

        // Different address + incomplete contact -> 5 (High risk)
        $highRisk = $this->engine->evaluate([
            'DELIVERY ADDRESS' => 'Jl. Merdeka No. 1 Jakarta',
            'billingAddress' => 'Jl. Sudirman No. 99 Bandung',
            'deliveryPhone' => '08123456789',
            'email' => 'null',
        ]);
        $this->assertSame(5, $highRisk['scores']['C5']);
    }
}
