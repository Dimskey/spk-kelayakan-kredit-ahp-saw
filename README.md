# SPK Kelayakan Fasilitas Kredit (*Term of Payment*) Pelanggan B2B — AHP & SAW

Aplikasi Web Dashboard Sistem Pendukung Keputusan (SPK) berbasis **Laravel 13**, **Tailwind CSS v4**, dan **Alpine.js** untuk mengevaluasi kelayakan pemberian fasilitas kredit pelanggan B2B (**Net 30 Hari**, **Net 14 Hari**, atau **Cash Before Delivery / CBD**) menggunakan kombinasi metode **Analytical Hierarchy Process (AHP)** dan **Simple Additive Weighting (SAW)** dengan arsitektur **4-Level Interactive Drilldown**.

## Fitur Utama
- **Rule Engine Data-to-Score (`C1`–`C5`)**: Mengonversi data mentah CSV (`businessEntity`, `npwp`, `nib`, `sppkp`, `pic`, `nik`, `imgKtp`, `customerType`, `DELIVERY ADDRESS`, `billingAddress`, `deliveryPhone`, `email`) ke skala $1..5$ dengan sanitasi nilai placeholder.
- **AHP Calculation & Consistency Inspector (`/ahp-inspector`)**: Editor matriks perbandingan berpasangan skala Saaty dengan pratinjau real-time matriks normalisasi, eigenvector ($w_i$), $\lambda_{\max}, CI, RI, CR \le 0.10$, diagnostik pasangan deviasi terbesar, dan kalkulasi ulang peringkat pelanggan.
- **4-Level Interactive Drilldown Dashboard (`/`)**:
  - **Level 0**: Ringkasan portofolio makro, audit gap kebijakan (*Upgrade Candidates* vs *Risk Exposures*), dan dua *Cross-Tabulation Heatmap* klik-untuk-menyaring.
  - **Level 1**: Matriks perankingan SAW berdensitas tinggi dengan *Matrix Mode Switcher* ($x_{ij}$ / $r_{ij}$ / $w_j \cdot r_{ij}$), pencarian instan (`/`), dan perbandingan *Side-by-Side*.
  - **Level 2**: *Slide-Over Customer Credit Underwriting Dossier* dengan *Stacked Contribution Bar* dan narasi otomatis.
  - **Level 3**: *Atomic Data & Formula Trace* yang menampilkan isi sel CSV mentah dan substitusi rumus SAW secara transparan.
- **Impor & Ekspor CSV**: Mendukung CLI (`php artisan spk:import-csv`) serta unggah/ekspor CSV langsung melalui dashboard.

## Menjalankan Proyek Secara Lokal
```bash
php artisan migrate --seed
php artisan spk:import-csv
npm install && npm run build
php artisan serve
```

## Pengujian Otomatis
```bash
./vendor/bin/phpunit --testdox
```

## Dokumen Terkait
- [Rencana Proyek (`plan.md`)](plan.md)
- [Kontrak Desain Anti-AI-Slop (`DESIGN.md`)](DESIGN.md)
- [Laporan Final Project (`Dokumentasi/01_Laporan_Final_Project_SPK_AHP_SAW.md`)](Dokumentasi/01_Laporan_Final_Project_SPK_AHP_SAW.md)
- [Dokumentasi Teknis & Panduan Demo (`Dokumentasi/02_Dokumentasi_Teknis_dan_Panduan_Penggunaan.md`)](Dokumentasi/02_Dokumentasi_Teknis_dan_Panduan_Penggunaan.md)
