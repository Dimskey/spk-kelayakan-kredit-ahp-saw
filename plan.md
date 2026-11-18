# Rencana Pengembangan Sistem Pendukung Keputusan (SPK) Kelayakan Pemberian Fasilitas Kredit (*Term of Payment*) Pelanggan

Dokumen ini merupakan cetak biru (*blueprint*) arsitektur, pemodelan matematis, desain antarmuka (*Anti-AI-Slop*), serta tahapan implementasi aplikasi web dashboard berbasis **Laravel** untuk menilai kelayakan pemberian fasilitas kredit (*Term of Payment*) pelanggan B2B menggunakan kombinasi metode **AHP (Analytical Hierarchy Process)** dan **SAW (Simple Additive Weighting)** dengan sistem **4-Level Interactive Drilldown**.

---

## 1. Ringkasan Eksekutif & Tujuan Sistem

Dalam operasional bisnis B2B dan distribusi, keputusan pemberian metode pembayaran kepada pelanggan baru—apakah wajib membayar di muka (**Cash Before Delivery / CBD**) atau berhak memperoleh fasilitas tempo (**Net 14 Hari** / **Net 30 Hari**)—berdampak langsung terhadap keamanan arus kas (*cash flow*) dan mitigasi risiko kredit macet (*bad debt*).

Aplikasi ini dirancang untuk mencapai tujuan berikut:
1. **Objektivitas Penilaian Kredit**: Menghilangkan bias subjektif tim *Sales/Finance* dengan pembobotan terstruktur berbasis **AHP** (termasuk validasi konsistensi matematis $CR \le 0.10$) dan perankingan deterministik berbasis **SAW**.
2. **Transparansi Keputusan melalui Sistem Drilldown**: Setiap angka peringkat dan rekomendasi *Term of Payment* dapat ditelusuri (*traceable*) mulai dari ringkasan portofolio makro hingga ke sel data mentah CSV dan substitusi rumus per pelanggan.
3. **Otomatisasi Data Historis**: Mengolah langsung data pelanggan dari file CSV perusahaan sesuai skema kolom pada proposal tanpa membebani tim administrasi dengan format input baru.

---

## 2. Catatan Status Data CSV di Workspace

Pada pemeriksaan awal direktori proyek (`/Users/mac/Project/sistem pendukung keputusan` beserta subfolder `Dokumentasi/`), **belum ditemukan file `.csv`** di dalam workspace.

### Rencana Penanganan Data:
1. **CSV Importer Terintegrasi (Web UI & CLI)**:
   - Menyediakan halaman/modal unggah CSV (*drag-and-drop*) dengan pratinjau pemetaan kolom di dashboard.
   - Menyediakan perintah Artisan (`php artisan spk:import-csv {path}`) untuk mengimpor dataset CSV berskala besar secara cepat.
2. **Database Seeder Simulasi (Fallback)**:
   - Menyediakan seeder dengan data pelanggan B2B yang realistis menggunakan skema kolom persis seperti proposal (`businessEntity`, `npwp`, `nib`, `sppkp`, `pic`, `nik`, `imgKtp`, `customerType`, `DELIVERY ADDRESS`, `billingAddress`, `deliveryPhone`, `email`) agar aplikasi dapat langsung diuji sebelum file produksi diunggah.
3. **Langkah Sebelum Eksekusi Kode**:
   - Silakan meletakkan file `.csv` dataset pelanggan ke dalam folder `/Users/mac/Project/sistem pendukung keputusan` atau `/Users/mac/Project/sistem pendukung keputusan/Dokumentasi` agar struktur header, format nilai kosong/anomali, serta delimiter CSV dapat diinspeksi secara akurat sebelum proses *coding* dimulai.

---

## 3. Desain Engine Matematis & Aturan Konversi Data (*Data-to-Score Pipeline*)

### Tahap 1: *Rule Engine* Transformasi Data Mentah CSV ke Skor Kriteria ($x_{ij} \in \{1..5\}$)

Setiap baris data pelanggan pada CSV akan dipetakan secara deterministik menjadi skor skala $1..5$ untuk kelima kriteria berikut, disertai pencatatan jejak evaluasi (*evaluation trace*) agar dapat diaudit pada **Drilldown Level 3**:

| Kode | Kriteria & Sifat | Sumber Kolom CSV | Logika & Skala Penilaian ($x_{ij}$) |
| :--- | :--- | :--- | :--- |
| **C1** | Bentuk Badan Usaha (**Benefit**) | `businessEntity` | **5**: PT (Perseroan Terbatas)<br>**4**: CV (Commanditaire Vennootschap)<br>**3**: Koperasi / Yayasan<br>**2**: PD / Toko / UD<br>**1**: Individu / Perorangan / Kosong |
| **C2** | Kelengkapan Legalitas Usaha (**Benefit**) | `npwp`, `nib`, `sppkp` | Menghitung jumlah kolom dokumen valid (tidak kosong, bukan `null`, bukan `-`):<br>**5**: Lengkap 3 dokumen (`npwp`, `nib`, `sppkp`)<br>**3**: Ada 2 dokumen<br>**2**: Ada 1 dokumen<br>**1**: Kosong semua (0 dokumen) |
| **C3** | Validitas Identitas PIC (**Benefit**) | `pic`, `nik`, `imgKtp` | **5**: Nama PIC (`pic`), NIK (`nik`), dan Foto KTP (`imgKtp`) tersedia lengkap<br>**4**: Nama PIC & NIK tersedia (tanpa Foto KTP)<br>**3**: Hanya Foto KTP tersedia (atau hanya 1 atribut identitas parsial)<br>**1**: Kosong semua |
| **C4** | Potensi Repeat Order (**Benefit**) | `customerType` | Pencocokan *case-insensitive*:<br>**5**: `Reseller`<br>**3**: `Enduser` (atau tipe non-reseller lainnya) |
| **C5** | Risiko Fraud / Data Anomali (**Cost**) | `DELIVERY ADDRESS`, `billingAddress`, `deliveryPhone`, `email` | Normalisasi string alamat (huruf kecil, rapikan spasi & tanda baca) untuk membandingkan `DELIVERY ADDRESS` vs `billingAddress`, serta cek kelengkapan kontak (`deliveryPhone` & `email`):<br>**1 (Risiko Rendah — terbaik pada Cost)**: Alamat sama persis & kontak lengkap (`deliveryPhone` + `email` terisi)<br>**3 (Risiko Sedang)**: Alamat berbeda tetapi kontak lengkap (atau alamat sama tetapi salah satu kontak tidak lengkap)<br>**5 (Risiko Tinggi — terburuk pada Cost)**: Alamat berbeda & kontak tidak lengkap (atau alamat kosong) |

---

### Tahap 2: Metode AHP (Pembobotan Kriteria & Uji Konsistensi)

AHP digunakan oleh pihak manajemen/keuangan untuk menentukan bobot kepentingan relatif ($w_1..w_5$) antar kriteria menggunakan skala perbandingan berpasangan Saaty ($1..9$).

1. **Matriks Perbandingan Berpasangan ($A_{5\times5}$)**:
   $$A = [a_{ij}], \quad a_{ii} = 1, \quad a_{ji} = \frac{1}{a_{ij}}$$
2. **Normalisasi Kolom**:
   $$a'_{ij} = \frac{a_{ij}}{\sum_{k=1}^{5} a_{kj}}$$
3. **Bobot Prioritas Kriteria (Eigenvector pendekatan rata-rata baris)**:
   $$w_i = \frac{1}{5} \sum_{j=1}^{5} a'_{ij}, \quad \text{dengan } \sum_{i=1}^{5} w_i = 1$$
4. **Uji Konsistensi Matriks**:
   - Eigenvalue maksimum:
     $$\lambda_{\max} = \sum_{i=1}^{5} \left( \left(\sum_{j=1}^{5} a_{ij}\right) \cdot w_i \right)$$
   - Consistency Index ($CI$) dengan $n = 5$:
     $$CI = \frac{\lambda_{\max} - n}{n - 1}$$
   - Consistency Ratio ($CR$) dengan Random Index untuk $n = 5$ yaitu $RI = 1.12$:
     $$CR = \frac{CI}{RI}$$
   - **Validasi Sistem**: Matriks dinyatakan **Konsisten** apabila $CR \le 0.10$. Jika $CR > 0.10$, sistem akan menolak penyimpanan bobot sebagai konfigurasi aktif dan menyorot sel perbandingan berpasangan yang paling banyak menyumbang inkonsistensi agar dapat diperbaiki oleh pengguna.

---

### Tahap 3: Metode SAW (Normalisasi Matriks Keputusan & Perankingan)

Setelah bobot valid ($w_1..w_5$) diperoleh dari AHP, metode SAW menghitung skor kelayakan setiap pelanggan ($i = 1..m$):

1. **Normalisasi Matriks Keputusan ($R = [r_{ij}]$)**:
   - Untuk kriteria **Benefit** ($C_1, C_2, C_3, C_4$):
     $$r_{ij} = \frac{x_{ij}}{\max_i(x_{ij})}$$
   - Untuk kriteria **Cost** ($C_5$ — makin rendah skor risiko makin baik):
     $$r_{ij} = \frac{\min_i(x_{ij})}{x_{ij}}$$
2. **Perhitungan Nilai Preferensi Akhir ($V_i$)**:
   $$V_i = \sum_{j=1}^{5} w_j \cdot r_{ij}$$
3. **Klasifikasi Keputusan *Term of Payment* (Ambang Batas Dapat Dikonfigurasi di UI)**:
   - **Tier A — Layak Kredit (`NET_30` / Tempo 30 Hari)**: $V_i \ge 0.80$
   - **Tier B — Layak Bersyarat (`NET_14` / Tempo 14 Hari)**: $0.60 \le V_i < 0.80$
   - **Tier C — Tidak Layak Kredit (`CBD` / Cash Before Delivery)**: $V_i < 0.60$

---

## 4. Arsitektur Sistem Drilldown (4-Level Interactive Drilldown)

Dashboard dirancang untuk memungkinkan manajemen maupun analis kredit menyelami keputusan dari level portofolio hingga ke bukti data mentah dalam maksimal 3 kali klik tanpa kehilangan konteks halaman.

```text
[Level 0: Executive Portfolio Overview]
   │  (Klik segmen heatmap/matriks cohort, mis. "PT -> CBD" atau "Risiko Tinggi")
   ▼
[Level 1: Master Ranking & Cohort Matrix]
   │  (Klik salah satu baris pelanggan di data grid)
   ▼
[Level 2: Customer Credit Underwriting Dossier (Slide-Over Drawer)]
   │  (Klik salah satu kriteria C1..C5 pada dossier pelanggan)
   ▼
[Level 3: Atomic Data & Formula Trace (Audit Trail)]
```

### Detail Setiap Level Drilldown:
1. **Level 0 — Executive Portfolio Overview (Macro Level)**:
   - **Global Status Strip**: Ringkasan padat berisi Total Pelanggan Teranalisis, Persentase Distribusi (`Net 30` vs `Net 14` vs `CBD`), Status Konsistensi AHP ($CR$ aktif), dan Jumlah Entitas dengan Indikasi Fraud Tinggi ($C_5 = 5$).
   - **Cross-Tabulation Cohort Matrix (Interactive Heatmap Grid)**:
     - Matriks *Bentuk Badan Usaha ($C_1$) $\times$ Rekomendasi ToP* (`Net 30`, `Net 14`, `CBD`).
     - Matriks *Tipe Pelanggan ($C_4$) $\times$ Tingkat Risiko Anomali ($C_5$)*.
   - **Interaksi Drilldown**: Klik pada sel mana saja (contoh: *"Reseller dengan Risiko Tinggi"* atau *"PT dengan status CBD"*) langsung menyaring tabel utama di Level 1 secara instan.

2. **Level 1 — Master Ranking & Cohort Matrix (Meso Level)**:
   - *High-Density Data Grid* bergaya Stripe/Linear menampilkan: Peringkat (`#Rank`), Nama Pelanggan/Perusahaan, Badan Usaha, Skor $C_1..C_5$, Nilai Preferensi Akhir ($V_i$), dan Lencana Keputusan ToP.
   - **Kontrol Analis**:
     - Filter multi-dimensi instan + pencarian cepat pintasan papan ketik (`/` atau `Cmd+K`).
     - **Matrix Mode Switcher**: Tombol pengubah tampilan angka sel $C_1..C_5$ secara langsung antara:
       1. **Nilai Mentah ($x_{ij}$)**
       2. **Nilai Normalisasi ($r_{ij}$)**
       3. **Kontribusi Terbobot ($w_j \cdot r_{ij}$)**
     - **Side-by-Side Comparison**: Memilih 2–3 pelanggan untuk membandingkan skor dan selisih antar kriteria secara berdampingan.

3. **Level 2 — Customer Credit Underwriting Dossier (Micro Level / Slide-Over Drawer)**:
   - Mengklik baris pelanggan membuka panel *Slide-Over Dossier* di sisi kanan layar tanpa memutus konteks tabel peringkat:
     - **Verdict Header**: Skor akhir $V_i$ (4 angka di belakang koma), Peringkat `#k dari N`, rekomendasi ToP, serta ringkasan naratif otomatis mengenai faktor terkuat dan faktor risiko utama.
     - **Stacked Contribution Bar**: Bilah proporsional horizontal yang menunjukkan kontribusi masing-masing kriteria ($w_1 r_{i1}$ s/d $w_5 r_{i5}$) membentuk skor total $V_i$.
     - **5 Kartu Audit Kriteria**: Daftar $C_1..C_5$ yang dapat diklik untuk membuka Level 3.

4. **Level 3 — Atomic Data & Formula Trace (Audit Trail Level)**:
   - Mengklik kriteria tertentu di dalam *Dossier* akan mengekspansi jejak audit lengkap:
     1. **Inspeksi Kolom CSV Asli**: Menampilkan nilai mentah persis seperti pada CSV (misal: nilai `npwp`, `nib`, `sppkp`, atau perbandingan teks `DELIVERY ADDRESS` vs `billingAddress` dengan penanda kesamaan/perbedaan alamat dan kelengkapan `deliveryPhone`/`email`).
     2. **Rule Evaluation Trace**: Menampilkan kondisi logika yang terpenuhi pada `CriteriaScoringRuleEngine` sehingga menghasilkan skor $x_{ij} \in \{1..5\}$.
     3. **Step-by-Step Math Trace**: Menampilkan substitusi angka nyata ke dalam rumus normalisasi SAW ($r_{ij} = x_{ij}/\max$ atau $\min/x_{ij}$) hingga hasil perkalian bobot AHP ($w_j \cdot r_{ij}$).
   - **Halaman Khusus AHP Calculation Inspector**: Menyajikan tabel interaktif Matriks Perbandingan Berpasangan $\rightarrow$ Matriks Normalisasi $\rightarrow$ Eigenvector ($w_j$) $\rightarrow$ Perhitungan $\lambda_{\max}, CI, RI, CR$ secara transparan untuk kebutuhan presentasi/pengujian akademis.

---

## 5. Spesifikasi Desain Frontend "Anti-AI-Slop" (*Swiss Grid / Linear / Stripe B2B Aesthetic*)

Antarmuka dibangun dengan filosofi **matematis, editorial, berdensitas informasi tinggi, dan bebas ornamen artifisial** agar setara dengan perangkat lunak finansial B2B modern.

### Referensi Desain Utama:
1. **Stripe Radar & Financial Dashboard**: Presisi tabel data finansial, angka rata kanan (*right-aligned tabular*), sinyal risiko semantik yang tenang dan profesional.
2. **Linear App**: Kepadatan informasi yang efisien, pemisah garis tipis `1px` (*hairline border*), *slide-over inspector drawer*, navigasi papan ketik responsif.
3. **Swiss International Typographic Style / Bloomberg Web**: Grid 12-kolom tegas, kontras jelas antara tipografi label editorial dengan angka teknis *monospace*.

### Daftar Larangan Keras (*AI Slop Blacklist*):
- **DILARANG** menggunakan gradien *glowing purple/indigo/blue mesh* atau efek *glassmorphism* (`backdrop-blur` bercahaya) pada panel maupun latar belakang.
- **DILARANG** membungkus setiap elemen dalam kartu berlapis (*card-inside-card*) dengan sudut membulat besar dan bayangan tebal (`shadow-xl`, `rounded-3xl`).
- **DILARANG** menggunakan emoji dekoratif pada judul, navigasi, tabel, atau lencana status.
- **DILARANG** menggunakan *whitespace bloat* (kartu KPI raksasa berisi satu angka besar) atau grafik *donut/pie 3D* warna-warni yang tidak menambah presisi analisis.
- **DILARANG** memakai statistik palsu atau *badge* kosong seperti `"AI Powered"` atau `"v2.0"`.

### Design Contract (Tipografi, Grid & Palet Warna):
- **Grid & Struktur Permukaan**:
  - Layout berbasis garis pemisah struktural `1px solid #E4E4E7` (`zinc-200`) dengan radius sudut tajam-terukur (`rounded-md` / `4px–6px`) dan tanpa *drop shadow* (`shadow-none`).
- **Tipografi Berkarakter**:
  - **UI & Heading**: `Plus Jakarta Sans` atau `Geist` (tracking rapat `-0.02em` pada heading, label kategori `10px–11px` uppercase dengan `tracking-wider`).
  - **Angka, Skor, Matriks & Dokumen Legal**: `JetBrains Mono` / `Geist Mono` dengan aturan CSS wajib `font-variant-numeric: tabular-nums` pada seluruh nilai matriks AHP, skor $x_{ij}, r_{ij}, V_i$, peringkat, NIK, NPWP, dan NIB agar kolom angka sejajar presisi secara vertikal.
- **Palet Warna (Swiss Paper Light Mode sebagai default + opsi Dark Terminal)**:
  - **Canvas**: `#FAFAFA` (`zinc-50`)
  - **Surface / Panel**: `#FFFFFF` dengan border `1px solid #E4E4E7` (`zinc-200`)
  - **Teks Utama**: `#09090B` (`zinc-950`)
  - **Teks Sekunder / Metadata**: `#52525B` (`zinc-600`)
  - **Warna Semantik Status Kredit** *(hanya digunakan sebagai sinyal status, tidak pernah sebagai dekorasi latar)*:
    - **Net 30 (Layak Kredit / Low Risk)**: Emerald (`#047857`, latar `#ECFDF5`, border `#A7F3D0`)
    - **Net 14 (Layak Bersyarat / Medium Watch)**: Industrial Amber (`#B45309`, latar `#FFFBEB`, border `#FDE68A`)
    - **CBD (Tidak Layak / High Fraud Risk)**: Crimson (`#BE123C`, latar `#FFF1F2`, border `#FECDD3`)

---

## 6. Arsitektur Teknis Laravel & Struktur Modul

### Technology Stack:
- **Backend**: Laravel 11 / 12 (PHP 8.2+), SQLite (untuk kemudahan portabilitas prototipe) atau MySQL/PostgreSQL.
- **Frontend**: Laravel Blade + Alpine.js + Tailwind CSS (via Vite) — memberikan latensi interaksi sub-50ms untuk *matrix mode switcher*, filter instan, *slide-over dossier*, dan simulasi matriks AHP *real-time* (Livewire dapat ditambahkan jika diperlukan reaktivitas berbasis server untuk dataset sangat besar).

### Skema Database:
1. `criteria`:
   - `id`, `code` (`C1`..`C5`), `name`, `type` (`benefit` / `cost`), `source_columns` (JSON), `weight` (`decimal(8,6)`), `rubric` (JSON).
2. `ahp_comparisons`:
   - `id`, `ahp_session_id`, `criterion_row_id`, `criterion_col_id`, `value` (`decimal(10,4)`).
3. `ahp_sessions`:
   - `id`, `name`, `lambda_max` (`decimal(10,6)`), `ci` (`decimal(10,6)`), `ri` (`decimal(6,4)`), `cr` (`decimal(10,6)`), `is_consistent` (boolean), `weights_snapshot` (JSON), `is_active` (boolean).
4. `customers`:
   - `id`, `customer_code`, `customer_name`, `business_entity`, `npwp`, `nib`, `sppkp`, `pic`, `nik`, `img_ktp`, `customer_type`, `delivery_address`, `billing_address`, `delivery_phone`, `email`, `raw_payload` (JSON untuk menyimpan kolom ekstra jika ada pada CSV asli), `import_batch_id`.
5. `customer_assessments`:
   - `id`, `customer_id`, `ahp_session_id`, `c1_score`..`c5_score` (tinyInteger 1..5), `evaluation_trace` (JSON), `r1`..`r5` (`decimal(10,6)`), `w1_r1`..`w5_r5` (`decimal(10,6)`), `preference_score` ($V_i$, `decimal(10,6)`), `rank` (integer), `recommended_top` (`NET_30`, `NET_14`, `CBD`).

### Service Layer (`app/Services/Spk/`):
- `CsvCustomerImporterService`: Membaca file CSV, mendeteksi delimiter (`,` / `;`) dan variasi penulisan nama header secara fleksibel, melakukan sanitasi dasar, lalu menyimpan data mentah ke `customers`.
- `CriteriaScoringRuleEngine`: Mengonversi atribut mentah pelanggan ke skor $1..5$ untuk $C_1..C_5$ serta mengembalikan array `evaluation_trace` terstruktur (kondisi yang cocok, bukti nilai kolom, dan penjelasan aturan) untuk Drilldown Level 3.
- `AhpCalculatorService`: Menghitung normalisasi matriks berpasangan, vektor bobot $w_i$, $\lambda_{\max}$, $CI$, $RI$, $CR$, serta mendeteksi pasangan kriteria dengan deviasi konsistensi terbesar bila $CR > 0.10$.
- `SawCalculatorService`: Menentukan nilai $\max/\min$ populasi aktif, menghitung matriks normalisasi $R$, menghitung $V_i$, menentukan peringkat (`rank`), dan menetapkan rekomendasi ToP (`NET_30`, `NET_14`, `CBD`) berdasarkan ambang batas aktif.

---

## 7. Tahapan Implementasi (Roadmap Eksekusi Setelah Plan Disetujui)

1. **Fase 1 — Inisialisasi Proyek & Design System**:
   - Setup proyek Laravel baru di workspace, konfigurasi database, Vite, Tailwind CSS, Alpine.js, dan muat font `Plus Jakarta Sans` + `JetBrains Mono` dengan token warna *Anti-AI-Slop*.
2. **Fase 2 — Skema Database & Core SPK Engines**:
   - Pembuatan migrasi database, model Eloquent, serta implementasi `CriteriaScoringRuleEngine`, `AhpCalculatorService`, dan `SawCalculatorService`.
   - Pembuatan **Unit Test (PHPUnit/Pest)** untuk memverifikasi keakuratan matematis AHP ($\lambda_{\max}, CI, CR, w_i$) dan SAW (normalisasi Benefit/Cost, $V_i$, perankingan) terhadap studi kasus manual.
3. **Fase 3 — CSV Importer & Seeder**:
   - Implementasi `CsvCustomerImporterService` (UI upload + perintah Artisan) yang disesuaikan dengan file CSV perusahaan (atau seeder simulasi jika CSV belum tersedia).
4. **Fase 4 — Pembangunan Web Dashboard & 4-Level Drilldown UI**:
   - Level 0 (Executive Portfolio Overview + Interactive Cross-Tab Heatmap).
   - Level 1 (Master Ranking Data Grid + Matrix Mode Switcher + Side-by-Side Comparison).
   - Level 2 (Slide-Over Customer Credit Underwriting Dossier + Stacked Contribution Bar).
   - Level 3 (Atomic Data & Formula Trace Inspector) + Halaman **AHP Calculation Inspector** untuk simulasi dan validasi bobot secara interaktif.
5. **Fase 5 — Verifikasi Akhir & Pengujian**:
   - Menjalankan seluruh test suite (`php artisan test`), memverifikasi kesesuaian hasil hitung aplikasi dengan perhitungan manual, serta menguji seluruh alur drilldown pada browser.
