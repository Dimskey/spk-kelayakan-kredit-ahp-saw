<?php

namespace App\Console\Commands;

use App\Models\Criterion;
use App\Services\Spk\CsvCustomerImporterService;
use App\Services\Spk\SpkAssessmentOrchestratorService;
use Database\Seeders\SpkCriteriaSeeder;
use Illuminate\Console\Command;

class ImportSpkCustomersCommand extends Command
{
    protected $signature = 'spk:import-csv {path? : Path ke file CSV pelanggan}';

    protected $description = 'Impor data pelanggan CSV dan kalkulasi ulang peringkat kelayakan kredit AHP-SAW';

    public function handle(
        CsvCustomerImporterService $importer,
        SpkAssessmentOrchestratorService $orchestrator
    ): int {
        $path = $this->argument('path') ?: base_path('Asets/Customer (3).csv');

        if (Criterion::count() === 0) {
            $this->info('Menjalankan SpkCriteriaSeeder...');
            $this->call('db:seed', ['--class' => SpkCriteriaSeeder::class]);
        }

        $this->info("Mengimpor dataset pelanggan dari: {$path}");
        $importResult = $importer->importFromFile($path);
        $this->info("Berhasil mengimpor {$importResult['imported_count']} pelanggan (Batch: {$importResult['batch_id']}).");

        $this->info('Menghitung skor kriteria (C1..C5), normalisasi SAW, dan peringkat ToP...');
        $summary = $orchestrator->recalculateForActiveSession();

        $this->table(
            ['Total Pelanggan', 'NET_30 (Layak)', 'NET_14 (Bersyarat)', 'CBD (Tunai)', 'AHP CR'],
            [[
                $summary['total'],
                $summary['net_30'],
                $summary['net_14'],
                $summary['cbd'],
                number_format((float) $summary['cr'], 6),
            ]]
        );

        return self::SUCCESS;
    }
}
