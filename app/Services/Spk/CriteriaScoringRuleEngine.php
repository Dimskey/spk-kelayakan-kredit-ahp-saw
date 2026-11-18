<?php

namespace App\Services\Spk;

use App\Models\Customer;

class CriteriaScoringRuleEngine
{
    public function evaluate(Customer|array $customer): array
    {
        $data = $customer instanceof Customer ? $customer->toArray() : $customer;

        $c1 = $this->evaluateC1($data);
        $c2 = $this->evaluateC2($data);
        $c3 = $this->evaluateC3($data);
        $c4 = $this->evaluateC4($data);
        $c5 = $this->evaluateC5($data);

        return [
            'scores' => [
                'C1' => $c1['score'],
                'C2' => $c2['score'],
                'C3' => $c3['score'],
                'C4' => $c4['score'],
                'C5' => $c5['score'],
            ],
            'trace' => [
                'C1' => $c1,
                'C2' => $c2,
                'C3' => $c3,
                'C4' => $c4,
                'C5' => $c5,
            ],
        ];
    }

    public function isValidDocumentValue(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        $lower = strtolower($trimmed);
        if (in_array($lower, ['null', 'undefined', '-', 'none', 'nan', 'n/a'], true)) {
            return false;
        }

        if (preg_match('/^[0\-\.\s]+$/', $trimmed) === 1) {
            return false;
        }

        return true;
    }

    public function isValidImageUrl(?string $value): bool
    {
        if (! $this->isValidDocumentValue($value)) {
            return false;
        }

        $trimmed = trim((string) $value);
        if (preg_match('#^https?://#i', $trimmed) === 1) {
            $path = parse_url($trimmed, PHP_URL_PATH);
            if ($path === null || $path === '' || $path === '/') {
                return false;
            }
        }

        return true;
    }

    public function isValidTextValue(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        $lower = strtolower($trimmed);
        if (in_array($lower, ['null', 'undefined', '-', 'none', 'nan', 'n/a'], true)) {
            return false;
        }

        if (preg_replace('/[^a-zA-Z0-9]/', '', $trimmed) === '') {
            return false;
        }

        return true;
    }

    public function normalizeAddress(?string $address): string
    {
        if (! $this->isValidTextValue($address)) {
            return '';
        }

        $cleaned = strtolower(trim((string) $address));
        // Strip trailing 5-digit Indonesian postal code so formatting differences don't trigger false mismatch
        $cleaned = (string) preg_replace('/[\s,\.]*\b\d{5}\s*$/', '', $cleaned);

        return (string) preg_replace('/[^a-z0-9]/', '', $cleaned);
    }

    private function evaluateC1(array $data): array
    {
        $rawEntity = $this->extractField($data, ['business_entity', 'businessEntity']);
        $rawEntityName = $this->extractField($data, ['business_entity_name', 'businessEntityName']);
        $rawName = $this->extractField($data, ['customer_name', 'NAME']);

        $normalized = strtolower(trim((string) $rawEntity));
        $sourceUsed = 'businessEntity';

        $score = match ($normalized) {
            'pt', 'perseroan terbatas' => 5,
            'cv' => 4,
            'koperasi', 'yayasan' => 3,
            'pd', 'toko', 'ud' => 2,
            default => null,
        };

        $ruleMatched = match ($score) {
            5 => 'Badan hukum Perseroan Terbatas (PT) pada kolom businessEntity',
            4 => 'Badan usaha Persekutuan Komanditer (CV) pada kolom businessEntity',
            3 => 'Badan usaha Koperasi/Yayasan pada kolom businessEntity',
            2 => 'Usaha Dagang / PD / Toko pada kolom businessEntity',
            default => null,
        };

        if ($score === null) {
            $combinedName = trim(($rawEntityName ?? '') . ' ' . ($rawName ?? ''));
            if (preg_match('/\b(PT|PERSEROAN\s+TERBATAS)\b/i', $combinedName) === 1) {
                $score = 5;
                $sourceUsed = 'businessEntityName/NAME (fallback)';
                $ruleMatched = 'Teridentifikasi prefiks PT / Perseroan Terbatas pada nama entitas';
            } elseif (preg_match('/\bCV\b/i', $combinedName) === 1) {
                $score = 4;
                $sourceUsed = 'businessEntityName/NAME (fallback)';
                $ruleMatched = 'Teridentifikasi prefiks CV pada nama entitas';
            } elseif (preg_match('/\b(KOPERASI|YAYASAN)\b/i', $combinedName) === 1) {
                $score = 3;
                $sourceUsed = 'businessEntityName/NAME (fallback)';
                $ruleMatched = 'Teridentifikasi Koperasi/Yayasan pada nama entitas';
            } elseif (preg_match('/\b(PD|TOKO|UD)\b/i', $combinedName) === 1) {
                $score = 2;
                $sourceUsed = 'businessEntityName/NAME (fallback)';
                $ruleMatched = 'Teridentifikasi PD/Toko/UD pada nama entitas';
            } else {
                $score = 1;
                $ruleMatched = 'Individu / Perorangan / Tidak berbadan hukum';
            }
        }

        return [
            'criterion' => 'C1',
            'label' => 'Bentuk Badan Usaha',
            'type' => 'benefit',
            'score' => $score,
            'source_used' => $sourceUsed,
            'rule_matched' => $ruleMatched,
            'raw_inputs' => [
                'businessEntity' => $rawEntity,
                'businessEntityName' => $rawEntityName,
                'NAME' => $rawName,
            ],
        ];
    }

    private function evaluateC2(array $data): array
    {
        $rawNpwp = $this->extractField($data, ['npwp']);
        $rawNib = $this->extractField($data, ['nib']);
        $rawSppkp = $this->extractField($data, ['sppkp']);

        $hasNpwp = $this->isValidDocumentValue($rawNpwp);
        $hasNib = $this->isValidDocumentValue($rawNib);
        $hasSppkp = $this->isValidDocumentValue($rawSppkp);

        $validCount = ($hasNpwp ? 1 : 0) + ($hasNib ? 1 : 0) + ($hasSppkp ? 1 : 0);

        $score = match ($validCount) {
            3 => 5,
            2 => 3,
            1 => 2,
            default => 1,
        };

        $ruleMatched = match ($validCount) {
            3 => 'Lengkap 3 dokumen legalitas (NPWP, NIB, SPPKP)',
            2 => 'Tersedia 2 dari 3 dokumen legalitas',
            1 => 'Hanya tersedia 1 dokumen legalitas',
            default => 'Tidak ada dokumen legalitas valid (0 dokumen)',
        };

        return [
            'criterion' => 'C2',
            'label' => 'Kelengkapan Legalitas Usaha',
            'type' => 'benefit',
            'score' => $score,
            'valid_count' => $validCount,
            'rule_matched' => $ruleMatched,
            'documents' => [
                'npwp' => ['raw' => $rawNpwp, 'is_valid' => $hasNpwp],
                'nib' => ['raw' => $rawNib, 'is_valid' => $hasNib],
                'sppkp' => ['raw' => $rawSppkp, 'is_valid' => $hasSppkp],
            ],
        ];
    }

    private function evaluateC3(array $data): array
    {
        $rawPic = $this->extractField($data, ['pic']);
        $rawNik = $this->extractField($data, ['nik']);
        $rawImgKtp = $this->extractField($data, ['img_ktp', 'imgKtp']);

        $hasPic = $this->isValidTextValue($rawPic);
        $hasNik = $this->isValidDocumentValue($rawNik);
        $hasImgKtp = $this->isValidImageUrl($rawImgKtp);

        if ($hasPic && $hasNik && $hasImgKtp) {
            $score = 5;
            $ruleMatched = 'Nama PIC, NIK, dan Foto/Dokumen KTP lengkap';
        } elseif ($hasPic && $hasNik) {
            $score = 4;
            $ruleMatched = 'Nama PIC dan NIK valid tersedia (tanpa Foto KTP valid)';
        } elseif ($hasImgKtp && ! $hasPic && ! $hasNik) {
            $score = 3;
            $ruleMatched = 'Hanya Foto/Dokumen KTP tersedia';
        } elseif ($hasPic || $hasNik || $hasImgKtp) {
            $score = 3;
            $ruleMatched = 'Identitas PIC parsial (hanya sebagian atribut identitas valid)';
        } else {
            $score = 1;
            $ruleMatched = 'Seluruh atribut identitas PIC kosong / tidak valid';
        }

        return [
            'criterion' => 'C3',
            'label' => 'Validitas Identitas PIC',
            'type' => 'benefit',
            'score' => $score,
            'rule_matched' => $ruleMatched,
            'attributes' => [
                'pic' => ['raw' => $rawPic, 'is_valid' => $hasPic],
                'nik' => ['raw' => $rawNik, 'is_valid' => $hasNik],
                'imgKtp' => ['raw' => $rawImgKtp, 'is_valid' => $hasImgKtp],
            ],
        ];
    }

    private function evaluateC4(array $data): array
    {
        $rawCustomerType = $this->extractField($data, ['customer_type', 'customerType']);
        $rawType = $this->extractField($data, ['type', 'TYPE']);
        $effectiveRaw = $this->isValidTextValue($rawCustomerType) ? $rawCustomerType : $rawType;
        $normalized = strtolower(trim((string) $effectiveRaw));

        if ($normalized === 'reseller') {
            $score = 5;
            $ruleMatched = 'Tipe pelanggan Reseller (potensi repeat order tinggi)';
        } else {
            $score = 3;
            $ruleMatched = $normalized === 'enduser'
                ? 'Tipe pelanggan Enduser (potensi repeat order standar)'
                : 'Tipe pelanggan non-reseller (' . ($effectiveRaw ?: 'kosong') . ')';
        }

        return [
            'criterion' => 'C4',
            'label' => 'Potensi Repeat Order',
            'type' => 'benefit',
            'score' => $score,
            'rule_matched' => $ruleMatched,
            'raw_inputs' => [
                'customerType' => $rawCustomerType,
                'TYPE' => $rawType,
            ],
        ];
    }

    private function evaluateC5(array $data): array
    {
        $rawDeliveryAddress = $this->extractField($data, ['delivery_address', 'DELIVERY ADDRESS']);
        $rawBillingAddress = $this->extractField($data, ['billing_address', 'billingAddress']);
        $rawDeliveryPhone = $this->extractField($data, ['delivery_phone', 'deliveryPhone']);
        $rawEmail = $this->extractField($data, ['email']);

        $normDelivery = $this->normalizeAddress($rawDeliveryAddress);
        $normBilling = $this->normalizeAddress($rawBillingAddress);

        $hasBothAddresses = $normDelivery !== '' && $normBilling !== '';
        $isSameAddress = $hasBothAddresses && ($normDelivery === $normBilling);

        $hasPhone = $this->isValidTextValue($rawDeliveryPhone);
        $hasEmail = $this->isValidTextValue($rawEmail);
        $isContactComplete = $hasPhone && $hasEmail;

        if (! $hasBothAddresses) {
            $score = 5;
            $ruleMatched = 'Risiko Tinggi: Salah satu atau kedua alamat domisili kosong';
        } elseif ($isSameAddress && $isContactComplete) {
            $score = 1;
            $ruleMatched = 'Risiko Rendah: Alamat pengiriman & penagihan cocok serta kontak lengkap';
        } elseif (! $isSameAddress && $isContactComplete) {
            $score = 3;
            $ruleMatched = 'Risiko Sedang: Alamat pengiriman & penagihan berbeda tetapi kontak lengkap';
        } elseif ($isSameAddress && ! $isContactComplete) {
            $score = 3;
            $ruleMatched = 'Risiko Sedang: Alamat sama tetapi kontak (telepon/email) belum lengkap';
        } else {
            $score = 5;
            $ruleMatched = 'Risiko Tinggi: Alamat berbeda dan kontak tidak lengkap';
        }

        return [
            'criterion' => 'C5',
            'label' => 'Risiko Fraud / Data Anomali',
            'type' => 'cost',
            'score' => $score,
            'rule_matched' => $ruleMatched,
            'checks' => [
                'is_same_address' => $isSameAddress,
                'has_delivery_phone' => $hasPhone,
                'has_email' => $hasEmail,
                'is_contact_complete' => $isContactComplete,
            ],
            'raw_inputs' => [
                'DELIVERY ADDRESS' => $rawDeliveryAddress,
                'billingAddress' => $rawBillingAddress,
                'deliveryPhone' => $rawDeliveryPhone,
                'email' => $rawEmail,
            ],
            'normalized_addresses' => [
                'delivery' => $normDelivery,
                'billing' => $normBilling,
            ],
        ];
    }

    private function extractField(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                return (string) $data[$key];
            }
        }

        return null;
    }
}
