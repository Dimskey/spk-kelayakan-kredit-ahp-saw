# Dokumentasi Teknis & Panduan Penggunaan Aplikasi SPK Kredit B2B (AHP & SAW)

Dokumen ini menjelaskan arsitektur kode, perintah operasional, alur demonstrasi aplikasi (sidang/presentasi), dan cara menjalankan pengujian otomatis (*automated testing*).

---

## 1. Prasyarat Lingkungan
- **PHP**: `8.2+` (terpasang `PHP 8.5.12` via Homebrew)
- **Composer**: `2.x` (`2.11.2`)
- **Node.js & npm**: `Node v24+` & `npm`
- **Database**: `SQLite` (`database/database.sqlite`)

---

## 2. Panduan Menjalankan Aplikasi Secara Lokal

1. **Migrasi & Seeding Baseline Kriteria + Bobot AHP**:
   ```bash
   php artisan migrate:fresh --seed
   ```
2. **Impor Dataset Pelanggan CSV & Hitung Peringkat SAW**:
   ```bash
   php artisan spk:import-csv
   ```
   *(Secara default membaca `Asets/Customer (3).csv` berisi 2.153 baris pelanggan. Anda dapat menyertakan argumen path khusus: `php artisan spk:import-csv /path/to/file.csv`.)*
3. **Kompilasi Asset Frontend (Vite + Tailwind CSS v4 + Alpine.js)**:
   ```bash
   npm install && npm run build
   ```
4. **Jalankan Server Pengembangan**:
   ```bash
   php artisan serve
   ```
   Buka browser dan akses: **`http://127.0.0.1:8000`**

---

## 3. Struktur Direktori & Arsitektur Kode Utama

| Komponen | Lokasi File | Tanggung Jawab |
| :--- | :--- | :--- |
| **Rule Engine (`C1`–`C5`)** | `app/Services/Spk/CriteriaScoringRuleEngine.php` | Sanitasi nilai kosong CSV (`null`, nol berulang, bare domain URL) dan pemetaan skor $1..5$ beserta `evaluation_trace`. |
| **Engine AHP** | `app/Services/Spk/AhpCalculatorService.php` | Normalisasi matriks berpasangan, vektor bobot $w_i$, $\lambda_{\max}, CI, RI, CR$, serta deteksi sel penyumbang inkonsistensi. |
| **Engine SAW** | `app/Services/Spk/SawCalculatorService.php` | Normalisasi *Benefit* & *Cost*, skor preferensi $V_i$, peringkat deterministik, dan klasifikasi ToP (`NET_30`, `NET_14`, `CBD`). |
| **CSV Importer** | `app/Services/Spk/CsvCustomerImporterService.php` | Pembacaan CSV (BOM-safe, auto-delimiter) & *chunked upsert* ke tabel `customers`. |
| **Assessment Orchestrator** | `app/Services/Spk/SpkAssessmentOrchestratorService.php` | Orkestrasi evaluasi massal populasi pelanggan serta penyimpanan sesi AHP baru. |
| **Artisan Command** | `app/Console/Commands/ImportSpkCustomersCommand.php` | CLI `php artisan spk:import-csv`. |
| **Controller Dashboard** | `app/Http/Controllers/Spk/SpkDashboardController.php` | Endpoint Level 0–3 (`/`), Dossier JSON (`/customers/{customer}/dossier`), Impor (`/import-csv`), dan Ekspor (`/export-csv`). |
| **Controller AHP Inspector** | `app/Http/Controllers/Spk/AhpInspectorController.php` | Endpoint halaman inspeksi AHP (`/ahp-inspector`), pratinjau real-time (`/ahp-inspector/preview`), simpan (`/apply`), dan reset (`/reset`). |
| **Views (Anti-AI-Slop)** | `resources/views/layouts/app.blade.php`, `resources/views/spk/dashboard.blade.php`, `resources/views/spk/ahp-inspector.blade.php` | Antarmuka *Swiss Grid / Linear / Stripe* tanpa ornamen gradien neon maupun emoji dekoratif. |
| **Design Contract** | `DESIGN.md` | Spesifikasi token warna, tipografi (`Plus Jakarta Sans` + `JetBrains Mono` `.font-mono-tabular`), dan aturan *Anti-AI-Slop*. |

---

## 4. Alur Demonstrasi Web Dashboard (4-Level Drilldown)

### A. Demonstrasi Drilldown Level 0 $\to$ Level 3
1. **Level 0 (Executive Portfolio Overview)**:
   - Tunjukkan ringkasan populasi (`2.153` entitas), distribusi `NET_30` (`44`), `NET_14` (`184`), `CBD` (`1.925`), serta kotak **Audit Gap Kebijakan** (`200` kandidat upgrade, `243` eksposur risiko).
   - Klik salah satu sel pada **Drilldown Kohort A** (misal `PT × Net 30` bernilai `36`) atau **Drilldown Kohort B** (misal `Reseller × Risiko Tinggi` bernilai `157`) untuk memperlihatkan filter otomatis pada tabel Level 1 beserta *breadcrumb* aktifnya.
2. **Level 1 (Master Ranking Matrix)**:
   - Ubah pengalih mode sel di kanan atas tabel: **Skor $x_{ij}$** $\to$ **Norm $r_{ij}$** $\to$ **Terbobot $w_j \cdot r_{ij}$**.
   - Centang kotak `Cmp` pada 2–3 pelanggan lalu klik tombol **Bandingkan** untuk membandingkan profil matematis pelanggan secara berdampingan.
   - Tekan tombol `/` untuk memfokuskan kotak pencarian secara instan.
3. **Level 2 (Slide-Over Customer Credit Underwriting Dossier)**:
   - Klik baris pelanggan (misal `#1 KEMBANG BULAN` atau `#50 FASTRATA BUANA`) untuk membuka *Slide-Over Dossier* di sisi kanan layar.
   - Tunjukkan *Stacked Contribution Bar*, narasi otomatis *Faktor Penguat* dan *Faktor Risiko*, serta navigasi antar baris menggunakan tombol `←` / `→` dan `ESC`.
4. **Level 3 (Atomic CSV & Formula Trace)**:
   - Klik salah satu kriteria (misal `C2 Kelengkapan Legalitas` atau `C5 Risiko Anomali`) di dalam *Dossier* untuk menampilkan bukti kolom CSV asli (`npwp`, `nib`, `sppkp`, perbandingan alamat `DELIVERY ADDRESS` vs `billingAddress`) sekaligus substitusi angka rumus SAW ($r_{ij}$ dan $w_j \cdot r_{ij}$).

### B. Demonstrasi AHP Calculation Inspector (`/ahp-inspector`)
1. Buka tab **02 AHP Calculation Inspector** di header atas.
2. Ubah nilai perbandingan pada segitiga atas untuk memperlihatkan pembaruan otomatis matriks resiprokal $1/a_{ij}$, matriks normalisasi, vektor bobot $w_i$, $\lambda_{\max}$, $CI$, dan $CR$.
3. Klik tombol **Simulasi Matriks Tidak Konsisten (Uji CR > 0.10)** untuk menunjukkan sistem mendeteksi $CR > 0.10$, menyorot pasangan kriteria penyebab inkonsistensi terbesar, dan mengunci tombol simpan agar bobot tidak konsisten tidak dapat merusak perankingan.
4. Klik **Reset Baseline Default** untuk mengembalikan bobot awal.

### C. Ekspor & Impor Data CSV
- Klik **Ekspor Hasil CSV** pada header dashboard untuk mengunduh peringkat lengkap (atau hasil kohort yang sedang difilter) dalam format CSV siap buka di Excel.
- Klik **Impor / Sinkronkan CSV** untuk mengunggah file CSV baru atau melakukan sinkronisasi ulang dari `Asets/Customer (3).csv`.

---

## 5. Pengujian Otomatis (*Automated Testing*)
Jalankan seluruh *unit test* dan *feature test* menggunakan perintah:
```bash
./vendor/bin/phpunit --testdox
```
Cakupan pengujian meliputi:
- `tests/Unit/Spk/CriteriaScoringRuleEngineTest.php` — validasi aturan konversi `C1`–`C5` dan sanitasi data kotor CSV.
- `tests/Unit/Spk/AhpCalculatorServiceTest.php` — verifikasi eigenvector, $\lambda_{\max}, CI, CR$, deteksi ketidakkonsistenan, dan validasi matriks resiprokal.
- `tests/Unit/Spk/SawCalculatorServiceTest.php` — verifikasi normalisasi *Benefit* & *Cost*, skor preferensi $V_i$, perankingan, serta ambang batas ToP.
- `tests/Feature/SpkManualCalculationVerificationTest.php` — uji kecocokan perhitungan manual vs sistem pada 6 arketipe pelanggan nyata dari `Customer (3).csv` dan endpoint ekspor CSV.
- `tests/Feature/SpkDashboardAndAhpWebTest.php` — uji endpoint Web Dashboard, filter drilldown, JSON Dossier, pratinjau/simpan AHP, dan unggah CSV.
- `tests/Feature/SpkDatabaseSeederTest.php` — uji integrasi seeder kriteria, sesi AHP, dan relasi model Eloquent.
