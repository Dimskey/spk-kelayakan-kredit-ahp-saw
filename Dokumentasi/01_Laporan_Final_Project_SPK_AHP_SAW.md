# LAPORAN FINAL PROJECT
## Sistem Pendukung Keputusan (SPK) Kelayakan Pemberian Fasilitas Kredit (*Term of Payment*) Pelanggan B2B Menggunakan Metode AHP dan SAW Berbasis Web Dashboard 4-Level Drilldown

---

## BAB I — PENDAHULUAN

### 1.1 Latar Belakang
Dalam operasional bisnis *Business-to-Business* (B2B) dan distribusi kemasan/produk industri, penentuan metode pembayaran bagi pelanggan baru merupakan keputusan strategis yang berdampak langsung pada arus kas (*cash flow*) dan tingkat risiko kredit macet (*bad debt*). Manajemen harus menentukan secara tepat apakah suatu entitas pelanggan diwajibkan bertransaksi secara tunai di muka (**Cash Before Delivery / CBD**) atau layak memperoleh fasilitas kredit bertempo (**Net 14 Hari** maupun **Net 30 Hari**).

Selama ini, evaluasi kelayakan pelanggan sering dilakukan secara manual dengan memeriksa dokumen legalitas satu per satu dari arsip atau berkas CSV (*raw data*). Pendekatan manual ini rentan menimbulkan bias subjektif, memperlambat proses *onboarding* pelanggan potensial, serta membuka celah ketidakcocokan antara profil risiko pelanggan dan metode pembayaran yang diberikan.

### 1.2 Urgensi Pengembangan Sistem
Pengembangan Sistem Pendukung Keputusan (SPK) terintegrasi memberikan empat nilai strategis bagi perusahaan:
1. **Mitigasi Risiko Kredit Macet (*Bad Debt*)**: Mendeteksi entitas dengan legalitas tidak lengkap atau indikasi anomali alamat/kontak sebelum fasilitas kredit diberikan.
2. **Efisiensi Waktu Validasi (Otomatisasi)**: Memproses dan menilai ribuan profil pelanggan dari dataset CSV dalam hitungan detik.
3. **Keputusan Berbasis Data (*Data-Driven*)**: Menghilangkan subjektivitas tim *Sales/Finance* melalui pembobotan terstruktur **Analytical Hierarchy Process (AHP)** dan pemeringkatan matematis **Simple Additive Weighting (SAW)**.
4. **Transparansi Audit Keputusan**: Melalui arsitektur **4-Level Interactive Drilldown**, setiap skor akhir pelanggan dapat ditelusuri mulai dari ringkasan portofolio hingga ke isi kolom CSV mentah dan substitusi angka rumus.

### 1.3 Tujuan Proyek
Merancang, mengimplementasikan, dan memverifikasi aplikasi Web Dashboard SPK berbasis **Laravel** yang mengombinasikan:
- **Rule Engine Data-to-Score** untuk mengonversi data mentah pelanggan (CSV perusahaan) menjadi skor skala $1..5$ pada 5 kriteria (`C1`–`C5`).
- **Metode AHP** untuk menentukan bobot prioritas kriteria ($w_1..w_5$) beserta uji konsistensi ($CR \le 0.10$).
- **Metode SAW** untuk normalisasi matriks keputusan (*Benefit* dan *Cost*), perhitungan nilai preferensi ($V_i$), pemeringkatan, dan klasifikasi rekomendasi *Term of Payment*.
- **Dashboard 4-Level Drilldown** dengan standar desain *Anti-AI-Slop* (*Swiss Grid / Linear / Stripe B2B Aesthetic*).

---

## BAB II — LANDASAN TEORI & PEMETAAN KRITERIA

### 2.1 Konsep *Multiple Attribute Decision Making* (MADM)
MADM digunakan untuk memilih atau memeringkat alternatif terbaik ($A_1..A_m$, dalam proyek ini sebanyak $2.153$ pelanggan) berdasarkan sejumlah atribut atau kriteria ($C_1..C_n$, sebanyak $5$ kriteria) yang memiliki satuan serta arah optimasi berbeda (*Benefit* vs *Cost*).

### 2.2 Metode *Analytical Hierarchy Process* (AHP)
AHP yang dikembangkan oleh Thomas L. Saaty digunakan untuk menghitung bobot kepentingan relatif antar kriteria melalui matriks perbandingan berpasangan ($A_{5\times5}$):

1. **Matriks Perbandingan Berpasangan ($A$)**:
   $$A = [a_{ij}], \quad a_{ii} = 1, \quad a_{ji} = \frac{1}{a_{ij}}$$
   dengan skala Saaty $1..9$ ($1 =$ sama penting, $3 =$ sedikit lebih penting, $5 =$ kuat lebih penting, $7 =$ sangat kuat, $9 =$ mutlak lebih penting).
2. **Normalisasi Kolom**:
   $$a'_{ij} = \frac{a_{ij}}{\sum_{k=1}^{n} a_{kj}}$$
3. **Bobot Prioritas (Eigenvector Pendekatan Rata-Rata Baris)**:
   $$w_i = \frac{1}{n} \sum_{j=1}^{n} a'_{ij}, \quad \sum_{i=1}^{n} w_i = 1$$
4. **Uji Konsistensi**:
   - Vektor konsistensi: $\lambda_i = \frac{(A \cdot w)_i}{w_i}$
   - Eigenvalue maksimum: $\lambda_{\max} = \frac{1}{n} \sum_{i=1}^{n} \lambda_i$
   - Consistency Index: $CI = \frac{\lambda_{\max} - n}{n - 1}$
   - Consistency Ratio: $CR = \frac{CI}{RI}$, dengan *Random Index* untuk $n = 5$ adalah $RI = 1.12$.
   - Syarat sah: **$CR \le 0.10$** (konsisten). Jika $CR > 0.10$, sistem menolak penyimpanan bobot permanen.

### 2.3 Metode *Simple Additive Weighting* (SAW)
SAW melakukan penjumlahan terbobot dari rating kinerja ternormalisasi pada setiap alternatif:

1. **Normalisasi Matriks Keputusan ($R = [r_{ij}]$)**:
   - Atribut **Benefit** ($C_1, C_2, C_3, C_4$):
     $$r_{ij} = \frac{x_{ij}}{\max_i(x_{ij})}$$
   - Atribut **Cost** ($C_5$ — semakin kecil skor risiko semakin baik):
     $$r_{ij} = \frac{\min_i(x_{ij})}{x_{ij}}$$
2. **Nilai Preferensi Akhir ($V_i$)**:
   $$V_i = \sum_{j=1}^{5} w_j \cdot r_{ij}$$
3. **Klasifikasi Keputusan *Term of Payment* (ToP)**:
   - **Tier A — `NET_30` (Layak Kredit 30 Hari)**: $V_i \ge 0.80$
   - **Tier B — `NET_14` (Layak Bersyarat 14 Hari)**: $0.60 \le V_i < 0.80$
   - **Tier C — `CBD` (*Cash Before Delivery* / Tunai)**: $V_i < 0.60$

### 2.4 Pemetaan Kriteria & Sanitasi Data CSV (*Data Hygiene*)
Berdasarkan proposal dan observasi langsung terhadap `Asets/Customer (3).csv` ($2.153$ baris, $54$ kolom), ditetapkan 5 kriteria berikut:

| Kode | Kriteria & Sifat | Kolom Sumber CSV | Skala Skor ($x_{ij}$) & Logika Evaluasi |
| :--- | :--- | :--- | :--- |
| **C1** | Bentuk Badan Usaha (**Benefit**) | `businessEntity` *(fallback: `businessEntityName`, `NAME`)* | **5**: `PT` / `Perseroan Terbatas`<br>**4**: `CV`<br>**3**: `Koperasi` / `Yayasan`<br>**2**: `PD` / `Toko` / `UD`<br>**1**: `Individu` / Perorangan / Kosong |
| **C2** | Kelengkapan Legalitas Usaha (**Benefit**) | `npwp`, `nib`, `sppkp` | **5**: 3 dokumen valid<br>**3**: 2 dokumen valid<br>**2**: 1 dokumen valid<br>**1**: 0 dokumen valid |
| **C3** | Validitas Identitas PIC (**Benefit**) | `pic`, `nik`, `imgKtp` | **5**: Nama PIC, NIK, & Foto KTP valid<br>**4**: Nama PIC & NIK valid<br>**3**: Hanya Foto KTP atau identitas parsial<br>**1**: Kosong semua |
| **C4** | Potensi Repeat Order (**Benefit**) | `customerType` *(fallback: `TYPE`)* | **5**: `Reseller`<br>**3**: `Enduser` / `Affiliasi` / Lainnya |
| **C5** | Risiko Fraud / Data Anomali (**Cost**) | `DELIVERY ADDRESS`, `billingAddress`, `deliveryPhone`, `email` | **1 (Risiko Rendah)**: Alamat pengiriman & penagihan cocok + kontak (`deliveryPhone` & `email`) lengkap<br>**3 (Risiko Sedang)**: Alamat berbeda tetapi kontak lengkap, *atau* alamat sama tetapi kontak tidak lengkap<br>**5 (Risiko Tinggi)**: Alamat berbeda & kontak tidak lengkap (atau alamat kosong) |

**Aturan Sanitasi Data Kotor (*Data Hygiene*) pada `CriteriaScoringRuleEngine`**:
- Nilai placeholder seperti `"null"`, `"undefined"`, `"-"`, `"0"`, `"0000000000"`, dan `"0000000000000000"` otomatis dianggap **tidak valid / kosong**.
- Pada kolom `imgKtp`, URL domain dasar tanpa path berkas (`"https://solusi-pack.com/"`) dianggap **kosong/tidak valid**.
- Pada perbandingan `DELIVERY ADDRESS` vs `billingAddress`, spasi, tanda baca, serta perbedaan format kode pos 5 digit di akhir kalimat dinormalisasi sebelum pencocokan agar perbedaan penulisan kode pos tidak menghasilkan *false positive* penalti fraud.

---

## BAB III — PERANCANGAN SISTEM & ARSITEKTUR DRILLDOWN

### 3.1 Stack Teknologi & Modul Backend
Aplikasi dibangun menggunakan **Laravel 13** (PHP 8.5), database **SQLite**, serta **Blade + Alpine.js + Tailwind CSS v4 (Vite)**.
- **Service Layer (`app/Services/Spk/`)**:
  - `CsvCustomerImporterService`: Pembaca CSV (*UTF-8 BOM-safe*, auto-deteksi delimiter, *chunked upsert*).
  - `CriteriaScoringRuleEngine`: Evaluasi deterministik `C1`–`C5` beserta pencatatan `evaluation_trace`.
  - `AhpCalculatorService`: Kalkulasi matriks normalisasi, bobot $w_i$, $\lambda_{\max}, CI, RI, CR$, serta diagnostik deviasi pasangan.
  - `SawCalculatorService`: Kalkulasi ekstremum $\max/\min$, normalisasi $r_{ij}$, skor $V_i$, peringkat deterministik, dan klasifikasi ToP.
  - `SpkAssessmentOrchestratorService`: Orkestrasi penghitungan ulang seluruh populasi pelanggan ketika data CSV atau bobot AHP diperbarui.

### 3.2 Arsitektur 4-Level Interactive Drilldown
1. **Level 0 — Executive Portfolio Overview (Macro)**:
   - Strip 5 kolom ringkasan portofolio (*Populasi*, *Net 30*, *Net 14*, *CBD*, dan *Audit Gap Kebijakan*).
   - Dua tabel *Cross-Tabulation Heatmap* interaktif (`C1 Badan Usaha × Rekomendasi ToP` dan `C4 Tipe Pelanggan × C5 Risiko Anomali`) di mana setiap sel angka dapat diklik untuk langsung menyaring Level 1.
2. **Level 1 — Master Ranking & Cohort Matrix (Meso)**:
   - Data grid berdensitas tinggi dengan filter multi-dimensi, pintasan `/` untuk pencarian, **Matrix Mode Switcher** (*Skor Mentah $x_{ij}$* / *Normalisasi $r_{ij}$* / *Terbobot $w_j \cdot r_{ij}$*), perbandingan *Side-by-Side* (2–3 pelanggan), dan ekspor hasil CSV sesuai filter aktif.
3. **Level 2 — Customer Credit Underwriting Dossier (Micro / Slide-Over Drawer)**:
   - Panel samping kanan menampilkan peringkat `#k`, skor $V_i$, *Stacked Contribution Bar*, narasi otomatis faktor penguat & risiko, serta navigasi antar baris (`←` / `→` / `ESC`).
4. **Level 3 — Atomic Data & Formula Trace + AHP Calculation Inspector**:
   - Ekspansi kartu kriteria di dalam Dossier menampilkan isi sel CSV asli (`VALID`/`KOSONG`, perbandingan alamat pengiriman vs penagihan) dan substitusi angka rumus SAW secara eksplisit.
   - Halaman khusus **AHP Calculation Inspector** menyediakan editor matriks Saaty interaktif dengan validasi $CR \le 0.10$ secara *real-time*.

---

## BAB IV — IMPLEMENTASI, PERHITUNGAN MANUAL VS SISTEM & ANALISIS DATA NYATA

### 4.1 Perhitungan Manual AHP Baseline vs Output Sistem
Pada konfigurasi *Baseline Kebijakan Kredit B2B*, manajemen memprioritaskan kelengkapan legalitas usaha (`C2`), diikuti bentuk badan usaha (`C1`) dan mitigasi risiko anomali (`C5`), lalu validitas PIC (`C3`), dan potensi repeat order (`C4`).

**1. Matriks Perbandingan Berpasangan ($A$) dan Jumlah Kolom ($\sum a_{ij}$):**

| Kriteria | C1 | C2 | C3 | C4 | C5 |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **C1** (Badan Usaha) | $1.0000$ | $0.5000$ | $2.0000$ | $3.0000$ | $1.0000$ |
| **C2** (Legalitas) | $2.0000$ | $1.0000$ | $3.0000$ | $4.0000$ | $2.0000$ |
| **C3** (Identitas PIC) | $0.5000$ | $0.3333$ | $1.0000$ | $2.0000$ | $0.5000$ |
| **C4** (Repeat Order) | $0.3333$ | $0.2500$ | $0.5000$ | $1.0000$ | $0.3333$ |
| **C5** (Risiko Anomali) | $1.0000$ | $0.5000$ | $2.0000$ | $3.0000$ | $1.0000$ |
| **Jumlah Kolom** | **$4.833333$** | **$2.583333$** | **$8.500000$** | **$13.000000$** | **$4.833333$** |

**2. Matriks Normalisasi ($a'_{ij} = a_{ij} / \sum a_{kj}$), Bobot Prioritas ($w_i$), dan Vektor Konsistensi ($\lambda_i$):**

| Kriteria | C1' | C2' | C3' | C4' | C5' | Bobot ($w_i$) | $(A \cdot w)_i$ | $\lambda_i$ |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **C1** | $0.206897$ | $0.193548$ | $0.235294$ | $0.230769$ | $0.206897$ | **$0.214681$** | $1.082133$ | $5.040658$ |
| **C2** | $0.413793$ | $0.387097$ | $0.352941$ | $0.307692$ | $0.413793$ | **$0.375063$** | $1.894602$ | $5.051418$ |
| **C3** | $0.103448$ | $0.129032$ | $0.117647$ | $0.153846$ | $0.103448$ | **$0.121484$** | $0.609367$ | $5.016011$ |
| **C4** | $0.068965$ | $0.096774$ | $0.058824$ | $0.076923$ | $0.068965$ | **$0.074090$** | $0.371719$ | $5.017103$ |
| **C5** | $0.206897$ | $0.193548$ | $0.235294$ | $0.230769$ | $0.206897$ | **$0.214681$** | $1.082133$ | $5.040658$ |

**3. Hasil Uji Konsistensi:**
- $\lambda_{\max} = \frac{5.040658 + 5.051418 + 5.016011 + 5.017103 + 5.040658}{5} = \mathbf{5.033170}$
- $CI = \frac{5.033170 - 5}{5 - 1} = \mathbf{0.008293}$ (tercatat `0.008292` pada pembulatan float)
- $CR = \frac{CI}{RI} = \frac{0.008293}{1.12} = \mathbf{0.007404} \le 0.10$ (**KONSISTEN**)

### 4.2 Verifikasi Perhitungan Manual SAW vs Sistem pada 6 Sampel Pelanggan Nyata
Pada populasi $2.153$ pelanggan, nilai ekstremum kolom adalah:
- $\max(C_1) = 5$, $\max(C_2) = 5$, $\max(C_3) = 5$, $\max(C_4) = 5$ (Benefit)
- $\min(C_5) = 1$ (Cost)

Berikut verifikasi perhitungan manual terhadap 6 profil pelanggan nyata dari dataset:

| Rank | Kode & Nama Pelanggan | Skor Mentah ($x_{1..5}$) | Normalisasi ($r_{1..5}$) | Kontribusi Terbobot ($w_j \cdot r_{ij}$) | Skor Akhir ($V_i$) Manual & Sistem | Keputusan ToP |
| :---: | :--- | :---: | :---: | :---: | :---: | :---: |
| **#1** | `C-01709` — KEMBANG BULAN | `[5, 5, 5, 3, 1]` | `[1.0, 1.0, 1.0, 0.6, 1.0]` | `0.214681 + 0.375063 + 0.121484 + 0.044454 + 0.214681` | **`0.970363`** | **`NET_30`** |
| **#44** | `C-02368` — HENATO INDAH PERKASA | `[5, 3, 3, 5, 1]` | `[1.0, 0.6, 0.6, 1.0, 1.0]` | `0.214681 + 0.225038 + 0.072890 + 0.074090 + 0.214681` | **`0.801380`** | **`NET_30`** |
| **#50** | `C-02351` — FASTRATA BUANA | `[5, 5, 3, 3, 3]` | `[1.0, 1.0, 0.6, 0.6, 0.333333]` | `0.214681 + 0.375063 + 0.072890 + 0.044454 + 0.071560` | **`0.778649`** | **`NET_14`** |
| **#228** | `C-02300` — HOMIA KREASI KARYA YAKIN | `[5, 2, 5, 3, 3]` | `[1.0, 0.4, 1.0, 0.6, 0.333333]` | `0.214681 + 0.150025 + 0.121484 + 0.044454 + 0.071560` | **`0.602205`** | **`NET_14`** |
| **#500** | `C-00872` — BAHAGIA BERKAT SCARLET | `[5, 2, 3, 3, 3]` | `[1.0, 0.4, 0.6, 0.6, 0.333333]` | `0.214681 + 0.150025 + 0.072890 + 0.044454 + 0.071560` | **`0.553611`** | **`CBD`** |
| **#2153** | `C-01587` — ADIWINATA SATYA RAHARDJA | `[1, 1, 1, 3, 5]` | `[0.2, 0.2, 0.2, 0.6, 0.2]` | `0.042936 + 0.075013 + 0.024297 + 0.044454 + 0.042936` | **`0.229636`** | **`CBD`** |

Seluruh hasil pada tabel di atas telah diuji otomatis dalam *integration test* (`SpkManualCalculationVerificationTest`) dengan selisih akurasi $\Delta \le 10^{-6}$.

### 4.3 Analisis Hasil Evaluasi Portofolio Nyata (2.153 Pelanggan)
Dari pemrosesan penuh file `Asets/Customer (3).csv` ($2.153$ pelanggan aktif), diperoleh statistik portofolio berikut:
- **Skor Preferensi ($V_i$)**: Minimum **`0.229636`**, Maksimum **`0.970363`**, Rata-rata **`0.4814`**.
- **Distribusi Rekomendasi ToP**:
  - **`NET_30` (Layak Kredit 30 Hari)**: **44 pelanggan** (**2,0%**)
  - **`NET_14` (Layak Bersyarat 14 Hari)**: **184 pelanggan** (**8,5%**)
  - **`CBD` (Wajib Tunai di Muka)**: **1.925 pelanggan** (**89,4%**)

#### Matriks Silang Bentuk Badan Usaha (`C1`) $\times$ Rekomendasi ToP
| Bentuk Badan Usaha (`C1`) | `NET_30` | `NET_14` | `CBD` | Total |
| :--- | :---: | :---: | :---: | :---: |
| **5 — PT (Perseroan Terbatas)** | 36 | 88 | 675 | **799** |
| **4 — CV (Commanditaire Vennootschap)** | 5 | 51 | 175 | **231** |
| **3 — Koperasi / Yayasan** | 0 | 0 | 2 | **2** |
| **2 — PD / Toko / UD** | 0 | 2 | 17 | **19** |
| **1 — Individu / Perorangan** | 3 | 43 | 1.056 | **1.102** |
| **Total Populasi** | **44** | **184** | **1.925** | **2.153** |

#### Matriks Silang Tipe Pelanggan (`C4`) $\times$ Risiko Anomali (`C5`)
| Tipe Pelanggan (`C4`) | Rendah (`1`) | Sedang (`3`) | Tinggi (`5`) | Total |
| :--- | :---: | :---: | :---: | :---: |
| **5 — Reseller** | 185 | 662 | 157 | **1.004** |
| **3 — Enduser / Lainnya** | 105 | 669 | 375 | **1.149** |
| **Total Populasi** | **290** | **1.331** | **532** | **2.153** |

#### Temuan Strategis Audit Gap Kebijakan
1. **200 Kandidat Upgrade Kredit**: Terdapat **200 pelanggan** yang pada data historis masih ditetapkan sebagai `CBD` (seperti `C-01709` *PT. Kembang Bulan* dan `C-02368` *PT. Henato Indah Perkasa*), padahal kelengkapan legalitas serta konsistensi datanya memenuhi syarat `NET_30` atau `NET_14`.
2. **243 Eksposur Risiko Kredit**: Terdapat **243 pelanggan** yang pada data historis telah menikmati fasilitas tempo pembayaran (misal `14 Days` atau `30 Days`), namun menurut profil risiko dan kelengkapan dokumen SPK berada pada kategori `CBD` sehingga perlu dilakukan peninjauan ulang atau pembaruan dokumen NPWP/NIB/SPPKP/KTP.

---

## BAB V — KESIMPULAN & SARAN PENGEMBANGAN

### 5.1 Kesimpulan
1. Sistem Pendukung Keputusan kelayakan fasilitas kredit (*Term of Payment*) berbasis **AHP dan SAW** telah berhasil dibangun menggunakan **Laravel 13** dan mampu memproses **2.153 pelanggan** secara deterministik.
2. Seluruh rumus matematis AHP ($\lambda_{\max}, CI, CR$) dan SAW ($r_{ij}, w_j \cdot r_{ij}, V_i$) terbukti identik dengan perhitungan manual dan lolos **24 pengujian otomatis** (*unit & feature tests*).
3. Desain **4-Level Interactive Drilldown** dan antarmuka *Anti-AI-Slop* memberikan visibilitas penuh kepada manajemen mulai dari pola kohort portofolio hingga inspeksi sel CSV asli per pelanggan.

### 5.2 Saran Pengembangan Lanjutan
1. Integrasi langsung dengan sistem ERP/akuntansi untuk menambahkan kriteria historis riwayat keterlambatan pembayaran (*Days Sales Outstanding* / DSO) bagi pelanggan *repeat*.
2. Penambahan mekanisme *Role-Based Access Control* (RBAC) sehingga perubahan bobot AHP dan persetujuan kenaikan *Term of Payment* memiliki otorisasi berjenjang (*Sales* $\to$ *Credit Control* $\to$ *Finance Director*).
