<?php

namespace App\Services\Spk;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CsvCustomerImporterService
{
    private const EXTRA_PAYLOAD_COLUMNS = [
        'countryId',
        'deliveryPostalCode',
        'billingPostalCode',
        'mobile1',
        'web',
        'taxType',
        'seller',
        'salesId',
        'paymentTermId',
    ];

    public function importFromFile(string $filePath, ?string $batchId = null): array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new InvalidArgumentException("File CSV tidak ditemukan atau tidak dapat dibaca: {$filePath}");
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Gagal membuka file CSV: {$filePath}");
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new InvalidArgumentException('File CSV kosong.');
            }
            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);

            $rawHeaders = fgetcsv($handle, 0, $delimiter, '"', '');
            if ($rawHeaders === false || $rawHeaders === []) {
                throw new InvalidArgumentException('Header CSV tidak valid.');
            }

            $rawHeaders[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeaders[0]);
            $headers = array_map(fn ($h) => trim((string) $h), $rawHeaders);
            $headerCount = count($headers);

            $resolvedBatchId = $batchId ?: 'batch_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(6));
            $now = now()->toDateTimeString();
            $rowsByCode = [];

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                if ($row === [null] || count($row) < 2) {
                    continue;
                }

                $padded = array_pad(array_slice($row, 0, $headerCount), $headerCount, null);
                $assoc = array_combine($headers, $padded);
                if ($assoc === false) {
                    continue;
                }

                $code = $this->cleanScalar($assoc['CODE'] ?? $assoc['customer_code'] ?? null);
                $name = $this->cleanScalar($assoc['NAME'] ?? $assoc['customer_name'] ?? null);
                if ($code === null || $name === null) {
                    continue;
                }

                $extraPayload = [];
                foreach (self::EXTRA_PAYLOAD_COLUMNS as $extraCol) {
                    if (array_key_exists($extraCol, $assoc)) {
                        $extraPayload[$extraCol] = $this->cleanScalar($assoc[$extraCol]);
                    }
                }

                $rowsByCode[$code] = [
                    'customer_code' => $code,
                    'customer_name' => $name,
                    'type' => $this->cleanScalar($assoc['TYPE'] ?? $assoc['type'] ?? null),
                    'company' => $this->cleanScalar($assoc['COMPANY'] ?? $assoc['company'] ?? null),
                    'business_entity' => $this->cleanScalar($assoc['businessEntity'] ?? $assoc['business_entity'] ?? null),
                    'business_entity_name' => $this->cleanScalar($assoc['businessEntityName'] ?? $assoc['business_entity_name'] ?? null),
                    'npwp' => $this->cleanScalar($assoc['npwp'] ?? null),
                    'nib' => $this->cleanScalar($assoc['nib'] ?? null),
                    'sppkp' => $this->cleanScalar($assoc['sppkp'] ?? null),
                    'sppkp_date' => $this->cleanScalar($assoc['sppkpDate'] ?? $assoc['sppkp_date'] ?? null),
                    'img_sppkp' => $this->cleanScalar($assoc['imgSppkp'] ?? $assoc['img_sppkp'] ?? null),
                    'img_npwp' => $this->cleanScalar($assoc['imgNpwp'] ?? $assoc['img_npwp'] ?? null),
                    'pic' => $this->cleanScalar($assoc['pic'] ?? null),
                    'nik' => $this->cleanScalar($assoc['nik'] ?? null),
                    'img_ktp' => $this->cleanScalar($assoc['imgKtp'] ?? $assoc['img_ktp'] ?? null),
                    'customer_type' => $this->cleanScalar($assoc['customerType'] ?? $assoc['customer_type'] ?? null),
                    'delivery_address' => $this->cleanScalar($assoc['DELIVERY ADDRESS'] ?? $assoc['delivery_address'] ?? null),
                    'billing_address' => $this->cleanScalar($assoc['billingAddress'] ?? $assoc['billing_address'] ?? null),
                    'delivery_street' => $this->cleanScalar($assoc['deliveryStreet'] ?? $assoc['delivery_street'] ?? null),
                    'billing_street' => $this->cleanScalar($assoc['billingStreet'] ?? $assoc['billing_street'] ?? null),
                    'delivery_phone' => $this->cleanScalar($assoc['deliveryPhone'] ?? $assoc['delivery_phone'] ?? null),
                    'billing_phone' => $this->cleanScalar($assoc['billingPhone'] ?? $assoc['billing_phone'] ?? null),
                    'email' => $this->cleanScalar($assoc['email'] ?? null),
                    'delivery_email' => $this->cleanScalar($assoc['deliveryEmail'] ?? $assoc['delivery_email'] ?? null),
                    'billing_email' => $this->cleanScalar($assoc['billingEmail'] ?? $assoc['billing_email'] ?? null),
                    'current_payment_term' => $this->cleanScalar($assoc['paymentTerm'] ?? $assoc['current_payment_term'] ?? null),
                    'status' => $this->cleanScalar($assoc['STATUS'] ?? $assoc['status'] ?? null),
                    'raw_payload' => json_encode($extraPayload, JSON_UNESCAPED_UNICODE),
                    'import_batch_id' => $resolvedBatchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        } finally {
            fclose($handle);
        }

        $records = array_values($rowsByCode);
        if ($records === []) {
            throw new InvalidArgumentException('Tidak ada baris pelanggan valid yang ditemukan di file CSV.');
        }

        $updateColumns = array_values(array_diff(array_keys($records[0]), ['customer_code', 'created_at']));

        DB::transaction(function () use ($records, $updateColumns): void {
            foreach (array_chunk($records, 300) as $chunk) {
                Customer::upsert($chunk, ['customer_code'], $updateColumns);
            }
        });

        return [
            'imported_count' => count($records),
            'batch_id' => $resolvedBatchId,
        ];
    }

    private function detectDelimiter(string $sampleLine): string
    {
        $commaCount = substr_count($sampleLine, ',');
        $semicolonCount = substr_count($sampleLine, ';');

        return $semicolonCount > $commaCount ? ';' : ',';
    }

    private function cleanScalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
