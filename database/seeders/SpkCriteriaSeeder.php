<?php

namespace Database\Seeders;

use App\Models\AhpComparison;
use App\Models\AhpSession;
use App\Models\Criterion;
use App\Services\Spk\AhpCalculatorService;
use Illuminate\Database\Seeder;

class SpkCriteriaSeeder extends Seeder
{
    public function run(): void
    {
        $criteriaDefinitions = [
            [
                'code' => 'C1',
                'name' => 'Bentuk Badan Usaha',
                'type' => 'benefit',
                'description' => 'Mengukur bonafiditas legal entitas perusahaan pelanggan.',
                'source_columns' => ['businessEntity', 'businessEntityName', 'NAME'],
                'rubric' => [
                    5 => 'PT (Perseroan Terbatas)',
                    4 => 'CV (Commanditaire Vennootschap)',
                    3 => 'Koperasi / Yayasan',
                    2 => 'PD / Toko / UD',
                    1 => 'Individu / Perorangan / Kosong',
                ],
                'sort_order' => 1,
            ],
            [
                'code' => 'C2',
                'name' => 'Kelengkapan Legalitas Usaha',
                'type' => 'benefit',
                'description' => 'Mengecek kelengkapan izin operasional dan kepatuhan pajak.',
                'source_columns' => ['npwp', 'nib', 'sppkp'],
                'rubric' => [
                    5 => 'Ada 3 dokumen legal valid',
                    3 => 'Ada 2 dokumen legal valid',
                    2 => 'Ada 1 dokumen legal valid',
                    1 => 'Kosong semua (0 dokumen)',
                ],
                'sort_order' => 2,
            ],
            [
                'code' => 'C3',
                'name' => 'Validitas Identitas PIC',
                'type' => 'benefit',
                'description' => 'Memastikan eksistensi dan jejak kependudukan penanggung jawab perusahaan.',
                'source_columns' => ['pic', 'nik', 'imgKtp'],
                'rubric' => [
                    5 => 'Nama PIC, NIK, dan Foto KTP ada',
                    4 => 'Nama PIC dan NIK ada',
                    3 => 'Hanya Foto KTP atau identitas parsial',
                    1 => 'Kosong semua',
                ],
                'sort_order' => 3,
            ],
            [
                'code' => 'C4',
                'name' => 'Potensi Repeat Order',
                'type' => 'benefit',
                'description' => 'Menilai kapasitas serapan pembelian berdasarkan tipe pelanggan.',
                'source_columns' => ['customerType'],
                'rubric' => [
                    5 => 'Reseller',
                    3 => 'Enduser / Lainnya',
                ],
                'sort_order' => 4,
            ],
            [
                'code' => 'C5',
                'name' => 'Risiko Fraud / Data Anomali',
                'type' => 'cost',
                'description' => 'Mendeteksi indikasi fraud berdasarkan kelengkapan kontak dan kecocokan alamat domisili operasional.',
                'source_columns' => ['DELIVERY ADDRESS', 'billingAddress', 'deliveryPhone', 'email'],
                'rubric' => [
                    1 => 'Risiko Rendah: Alamat sama persis & kontak lengkap',
                    3 => 'Risiko Sedang: Alamat beda tapi kontak lengkap (atau alamat sama & kontak tidak lengkap)',
                    5 => 'Risiko Tinggi: Alamat beda & kontak tidak lengkap',
                ],
                'sort_order' => 5,
            ],
        ];

        $criteriaModels = [];
        foreach ($criteriaDefinitions as $def) {
            $criteriaModels[] = Criterion::updateOrCreate(
                ['code' => $def['code']],
                $def
            );
        }

        // Standard B2B credit underwriting baseline matrix (CR ≈ 0.0074)
        $ahpService = app(AhpCalculatorService::class);
        $matrix = $ahpService->buildReciprocalMatrix(5, [
            0 => [1 => 0.5, 2 => 2.0, 3 => 3.0, 4 => 1.0],
            1 => [2 => 3.0, 3 => 4.0, 4 => 2.0],
            2 => [3 => 2.0, 4 => 0.5],
            3 => [4 => 1.0 / 3.0],
        ]);

        $ahpResult = $ahpService->calculate($matrix);

        $weightsByCode = [];
        foreach ($criteriaModels as $index => $criterion) {
            $weight = $ahpResult['weights'][$index];
            $criterion->update(['weight' => $weight]);
            $weightsByCode[$criterion->code] = $weight;
        }

        AhpSession::where('is_active', true)->update(['is_active' => false]);

        $session = AhpSession::updateOrCreate(
            ['name' => 'Baseline Kebijakan Kredit B2B (Default)'],
            [
                'matrix_size' => $ahpResult['matrix_size'],
                'lambda_max' => $ahpResult['lambda_max'],
                'ci' => $ahpResult['ci'],
                'ri' => $ahpResult['ri'],
                'cr' => $ahpResult['cr'],
                'is_consistent' => $ahpResult['is_consistent'],
                'raw_matrix_snapshot' => $ahpResult['raw_matrix'],
                'normalized_matrix_snapshot' => $ahpResult['normalized_matrix'],
                'weights_snapshot' => $weightsByCode,
                'is_active' => true,
            ]
        );

        foreach ($criteriaModels as $rowIdx => $rowCriterion) {
            foreach ($criteriaModels as $colIdx => $colCriterion) {
                AhpComparison::updateOrCreate(
                    [
                        'ahp_session_id' => $session->id,
                        'criterion_row_id' => $rowCriterion->id,
                        'criterion_col_id' => $colCriterion->id,
                    ],
                    ['value' => $ahpResult['raw_matrix'][$rowIdx][$colIdx]]
                );
            }
        }
    }
}
