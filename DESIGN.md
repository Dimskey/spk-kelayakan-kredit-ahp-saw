# DESIGN.md — Kontrak Sistem Desain Anti-AI-Slop (*Swiss Grid / Linear / Stripe B2B*)

Dokumen ini adalah kontrak desain wajib (*Design System Contract*) untuk **Dashboard SPK Kelayakan Fasilitas Kredit (Term of Payment) — AHP & SAW**. Seluruh tampilan antarmuka pada Fase 4 dan seterusnya harus mematuhi aturan di bawah ini tanpa pengecualian.

---

## 1. Filosofi Desain & Referensi Utama
- **Stripe Radar & Financial Dashboard**: Presisi tabel data finansial, angka rata kanan (*right-aligned tabular*), sinyal risiko semantik yang tenang dan profesional.
- **Linear App**: Kepadatan informasi tinggi (*high information density*), pembatas struktural *1px hairline border*, *slide-over inspector drawer* tanpa memutus konteks halaman, serta navigasi cepat via papan ketik (`/` atau `Cmd+K`).
- **Swiss International Typographic Style**: Grid 12-kolom tegas, struktur datar tanpa ornamen bayangan berlebih, dan kontras tipografi yang tajam antara label editorial dan angka teknis.

---

## 2. Daftar Larangan Keras (*AI Slop Blacklist*)
Komponen atau halaman **DITOLAK** apabila mengandung elemen berikut:
1. **Gradien Neon / Mesh Gelap**: Dilarang menggunakan *glowing purple/indigo/blue mesh gradients* atau efek *glassmorphism* (`backdrop-blur` bercahaya) pada latar belakang, hero, maupun panel.
2. **Card-Inside-Card Madness**: Dilarang membungkus setiap metrik, grafik, atau blok data dalam kartu bersudut terlalu bulat (`rounded-2xl` / `rounded-3xl`) dan bayangan tebal (`shadow-lg` / `shadow-xl`). Gunakan pemisah garis tipis `1px` dan `rounded-md` (`4px`–`6px`) atau `shadow-none`.
3. **Emoji Dekoratif**: Dilarang menaruh emoji pada judul, item navigasi, header tabel, atau lencana status.
4. **Whitespace Bloat & Metrik Kosong**: Dilarang membuat kartu KPI raksasa yang boros ruang atau grafik donat/pie 3D warna-warni yang tidak menambah ketajaman analisis.
5. **Dekorasi & Statistik Palsu**: Dilarang menambahkan badge seperti `"AI Powered"`, `"v2.0"`, atau angka statis yang tidak berasal dari database/kalkulasi nyata.

---

## 3. Token Warna (*Design Tokens*)

### Permukaan & Struktur (*Swiss Paper Light Mode*)
| Token | Nilai Hex | Referensi Tailwind | Penggunaan |
| :--- | :--- | :--- | :--- |
| `--bg-canvas` | `#FAFAFA` | `zinc-50` | Latar belakang utama halaman |
| `--bg-surface` | `#FFFFFF` | `white` | Permukaan panel data, tabel, dan *slide-over drawer* |
| `--bg-subtle` | `#F4F4F5` | `zinc-100` | Header kolom tabel, hover baris halus, *segmented switcher* |
| `--border-hairline` | `#E4E4E7` | `zinc-200` | Garis pemisah struktural `1px` antar modul dan sel matriks |
| `--text-ink` | `#09090B` | `zinc-950` | Teks utama, angka skor, judul bagian |
| `--text-muted` | `#52525B` | `zinc-600` | Metadata, label kategori, keterangan rumus |

### Warna Semantik Status Kredit
*(Warna hanya boleh digunakan untuk mengomunikasikan ambang batas keputusan/risiko, bukan dekorasi)*

| Keputusan / State | Teks (`text`) | Latar (`bg`) | Border (`border`) | Makna |
| :--- | :--- | :--- | :--- | :--- |
| **`NET_30` (Layak Kredit)** | `#047857` | `#ECFDF5` | `#A7F3D0` | Skor $V_i \ge 0.80$ / Risiko Rendah |
| **`NET_14` (Layak Bersyarat)** | `#B45309` | `#FFFBEB` | `#FDE68A` | Skor $0.60 \le V_i < 0.80$ / Risiko Sedang |
| **`CBD` (Tidak Layak Kredit)** | `#BE123C` | `#FFF1F2` | `#FECDD3` | Skor $V_i < 0.60$ / Anomali Tinggi |

---

## 4. Tipografi & Aturan Numerik
1. **UI & Editorial Font**: `Plus Jakarta Sans` (weights: `400`, `500`, `600`, `700`).
   - Heading utama menggunakan *tight tracking* (`-0.02em`).
   - Micro-label header menggunakan huruf kapital kecil (`10px`–`11px`, `uppercase`, `tracking-wider`, `font-semibold`).
2. **Data & Mathematical Font**: `JetBrains Mono` (weights: `400`, `500`, `600`).
   - Kelas wajib `.font-mono-tabular` (`font-variant-numeric: tabular-nums;`) diterapkan pada **seluruh** angka skor kriteria ($x_{ij}$), nilai normalisasi ($r_{ij}$), kontribusi terbobot ($w_j \cdot r_{ij}$), nilai akhir ($V_i$), matriks perbandingan berpasangan AHP, konsistensi ($CR$), peringkat, NIK, NPWP, dan NIB agar kolom angka sejajar presisi secara vertikal.
   - Angka di dalam tabel wajib **rata kanan** (*right-aligned*).

---

## 5. Aturan Visual Sistem Drilldown 4-Level
- **Level 0 (Executive Strip & Cross-Tab Heatmap)**: Strip horizontal padat yang menyatukan metrik portofolio dengan garis pembatas `1px`, diikuti matriks silang interaktif yang dapat diklik untuk memfilter Level 1.
- **Level 1 (Master Ranking Data Grid)**: Tabel datar berdensitas tinggi dengan header lengket (*sticky header*), tombol pengalih mode sel (*Raw Score* / *Normalized* / *Weighted*), dan seleksi bandingkan (*Compare*).
- **Level 2 (Slide-Over Underwriting Dossier)**: Panel samping kanan lebar `540px`–`640px` dengan `border-l border-zinc-200`, menampilkan *Stacked Contribution Bar* dan 5 blok audit kriteria yang dapat diekspansi.
- **Level 3 (Atomic Data & Formula Trace)**: Blok kode/audit monospaced menampilkan isi kolom CSV mentah, aturan yang cocok, dan substitusi rumus matematis secara eksplisit.
