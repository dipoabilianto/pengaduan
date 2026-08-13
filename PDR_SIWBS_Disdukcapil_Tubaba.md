# PROJECT DESIGN REPORT (PDR)
## Sistem Pengaduan dan Whistleblowing
### Dinas Kependudukan dan Pencatatan Sipil Kabupaten Tulang Bawang Barat (SI-WBS DISDUKCAPIL TUBABA)

| Atribut | Keterangan |
|---|---|
| Jenis Dokumen | Project Design Report (Dokumen Perencanaan & Rancangan Sistem) — versi kerja untuk repo |
| Platform | Aplikasi Berbasis Website (Web-based Application) |
| Teknologi Utama | PHP 8.x + Laravel Framework (versi LTS terbaru) |
| Pedoman Regulasi | Permenpan-RB Nomor 5 Tahun 2025 tentang Pengelolaan Pengaduan Pelayanan Publik |
| Versi Dokumen | 1.0 |
| Tanggal | 10 Agustus 2026 |

> **Cara pakai file ini:** simpan di root repo sebagai `PDR.md` atau `docs/PDR.md`. Checkbox `[ ]` pada Bab 11 (Rencana Pengembangan Per Fase) dipakai sebagai checklist progres pengembangan — centang `[x]` setiap item selesai, dan commit perubahan file ini bersamaan dengan kode terkait agar dokumentasi selalu sinkron dengan progres.

---

## Daftar Isi

1. [Pendahuluan](#1-pendahuluan)
2. [Aktor dan Hak Akses](#2-aktor-dan-hak-akses)
3. [Arsitektur Sistem dan Technology Stack](#3-arsitektur-sistem-dan-technology-stack)
4. [Modul Frontend Publik](#4-modul-frontend-publik-tanpa-login)
5. [Modul Backend](#5-modul-backend-admin-pejabat-superuser)
6. [Notifikasi Multi-Kanal](#6-notifikasi-multi-kanal)
7. [Keamanan dan Perlindungan Data](#7-keamanan-dan-perlindungan-data)
8. [Desain UI/UX — Glassmorphism](#8-desain-uiux--glassmorphism)
9. [Rancangan Skema Database](#9-rancangan-skema-database-ringkas)
10. [Prinsip DRY & Clean Code](#10-prinsip-pengembangan-dry--clean-code)
11. [Rencana Pengembangan Per Fase (Checklist)](#11-rencana-pengembangan-per-fase-checklist)
12. [Penutup](#12-penutup)

---

## 1. PENDAHULUAN

### 1.1 Latar Belakang

Dinas Kependudukan dan Pencatatan Sipil (Disdukcapil) Kabupaten Tulang Bawang Barat memerlukan kanal pengaduan dan whistleblowing yang kredibel, aman, dan akuntabel bagi masyarakat maupun aparatur internal untuk melaporkan dugaan pelanggaran, maladministrasi, gratifikasi, atau keluhan pelayanan publik. Sistem manual yang selama ini berjalan (kotak saran, surat, datang langsung) memiliki keterbatasan dalam hal kerahasiaan identitas pelapor, kecepatan tindak lanjut, transparansi status penanganan, serta pengukuran urgensi laporan secara objektif.

Dokumen ini disusun sebagai rancangan arsitektur, alur bisnis, dan rencana pengembangan sistem pengaduan dan whistleblowing berbasis website (SI-WBS) yang dibangun menggunakan Laravel (PHP), dengan mekanisme perlindungan data pelapor, penilaian urgensi berbasis kecerdasan buatan (AI), integrasi notifikasi multi-kanal (aplikasi Android, WhatsApp), serta dashboard publik dan dashboard pemantauan terintegrasi.

### 1.2 Tujuan

- Menyediakan kanal pengaduan dan whistleblowing yang mudah diakses tanpa mewajibkan pelapor melakukan registrasi/login.
- Menjamin kerahasiaan dan perlindungan data pribadi pelapor sesuai prinsip perlindungan data dan Permenpan-RB No. 5 Tahun 2025.
- Mempercepat proses triase, verifikasi urgensi, dan pendistribusian laporan kepada pejabat berwenang.
- Meningkatkan transparansi publik melalui dashboard statistik pengaduan tanpa membuka data sensitif/rahasia.
- Mengintegrasikan seluruh kanal pengaduan (formulir web, WhatsApp, media sosial) dalam satu dashboard pemantauan admin.
- Membangun sistem dengan kode yang bersih, terstruktur, mudah dipelihara (prinsip DRY & Clean Code), dan dikembangkan secara bertahap per fase.

### 1.3 Ruang Lingkup

Sistem terdiri dari dua sisi utama:

- **Frontend Publik**: formulir pelaporan, daftar pengaduan publik & status penanganan, dashboard statistik publik.
- **Backend Admin/Superuser**: manajemen laporan, penilaian urgensi (dibantu AI), eskalasi ke pejabat berwenang, manajemen pengguna, integrasi notifikasi, dan dashboard monitoring multi-kanal.

Di luar ruang lingkup dokumen ini: pengadaan infrastruktur server/hosting, proses hukum lanjutan atas laporan, dan integrasi langsung ke sistem kepegawaian (SIMPEG) — dapat menjadi fase pengembangan lanjutan.

### 1.4 Dasar Regulasi & Acuan

- Peraturan Menteri Pendayagunaan Aparatur Negara dan Reformasi Birokrasi (Permenpan-RB) Nomor 5 Tahun 2025 tentang Pengelolaan Pengaduan Pelayanan Publik — acuan utama kategori pengaduan, SLA penanganan, klasifikasi/tingkat urgensi, mekanisme eskalasi, dan kewajiban transparansi status ke pelapor.
- Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi — acuan perlindungan data pelapor dan pihak terlapor.
- Undang-Undang Nomor 25 Tahun 2009 tentang Pelayanan Publik — acuan hak masyarakat menyampaikan pengaduan.

> ⚠️ **Penting**: Tim pengembang wajib melakukan kajian pasal-per-pasal Permenpan-RB No. 5/2025 bersama bagian hukum/reformasi birokrasi Disdukcapil pada Fase 1 (Analisis Kebutuhan) untuk memetakan istilah, tingkatan urgensi resmi, dan SLA ke dalam desain sistem, karena isi lengkap regulasi perlu diverifikasi langsung dari salinan resmi terbaru.

---

## 2. AKTOR DAN HAK AKSES

| Aktor | Deskripsi | Hak Akses Utama |
|---|---|---|
| **Pelapor (Publik)** | Masyarakat/pegawai yang menyampaikan pengaduan atau whistleblowing, tanpa akun/login. | Mengisi form laporan, melihat status via nomor tiket/HP, melihat daftar & dashboard publik (non-sensitif). |
| **Admin** | Petugas pengelola pengaduan Disdukcapil (tim pengelola WBS). | Melihat seluruh laporan masuk, menilai/menyetujui skor urgensi AI, mengubah status, meneruskan ke pejabat berwenang, mengelola notifikasi. |
| **Pejabat Berwenang** | Pejabat struktural/eselon yang menerima eskalasi laporan sesuai bidang & urgensi. | Menerima notifikasi laporan yang diteruskan, memberi disposisi/tindak lanjut, mengubah status penanganan pada laporannya. |
| **Superuser** | Administrator tertinggi sistem. | Akses penuh termasuk data pribadi pelapor (terenkripsi), manajemen akun & role, konfigurasi AI, audit log, konfigurasi integrasi (WA/APK/sosmed). |

> 🔒 **Kontrol Kerahasiaan**: Data identitas pelapor (nama, no. HP) HANYA dapat dibuka oleh Superuser melalui mekanisme akses khusus yang tercatat di audit log (siapa membuka, kapan, laporan mana). Admin dan Pejabat Berwenang hanya melihat data laporan dalam bentuk tersamar/masked.

---

## 3. ARSITEKTUR SISTEM DAN TECHNOLOGY STACK

### 3.1 Gambaran Arsitektur

Sistem dibangun dengan pola arsitektur monolitik modular berbasis Laravel (MVC + Service + Repository Pattern) agar kode tetap DRY dan mudah dipelihara, dengan pemisahan yang jelas antara modul Publik, Modul Admin, Modul AI-Scoring, dan Modul Integrasi (Notifikasi & Sosial Media).

| Layer | Teknologi/Pendekatan |
|---|---|
| Backend Framework | Laravel 11.x (PHP 8.2+), Service Layer + Repository Pattern + Form Request Validation |
| Frontend Web | Blade Templating + Alpine.js/Livewire, TailwindCSS untuk styling glassmorphism |
| Database | MySQL/MariaDB (utama), opsi Redis untuk cache & queue |
| Autentikasi Admin | Laravel Breeze/Fortify + Spatie Laravel-Permission (role: Admin, Pejabat, Superuser) |
| Antrian & Job | Laravel Queue (Redis/Database driver) — AI scoring, notifikasi WA & Push, generate laporan |
| Penyimpanan File | Laravel Filesystem (local/S3-compatible), terenkripsi at-rest |
| API | Laravel Sanctum untuk RESTful API (dikonsumsi aplikasi Android/APK) |
| AI Urgency Scoring | Integrasi API LLM via HTTP client Laravel untuk analisis teks 5W1H & klasifikasi urgensi |
| Notifikasi WhatsApp | Integrasi WhatsApp Business API / Gateway resmi |
| Aplikasi Android | Flutter atau WebView + Firebase Cloud Messaging |
| CAPTCHA | Custom CAPTCHA alfabet 5 huruf (Intervention Image) / Google reCAPTCHA sebagai alternatif |
| Keamanan | Enkripsi AES-256, HTTPS/TLS wajib, hashing, rate limiting, audit log (Spatie Activitylog) |

### 3.2 Diagram Alur Data (Tekstual)

1. Pelapor mengakses Frontend Publik → mengisi formulir pengaduan → submit.
2. Sistem menyimpan data: (a) data laporan (publik/tersamar), (b) data pelapor (terenkripsi, akses terbatas Superuser).
3. Job Queue memicu proses AI Urgency Scoring berdasarkan isi 5W1H.
4. Hasil skor AI masuk sebagai rekomendasi ke Admin Dashboard (status: Menunggu Verifikasi Urgensi).
5. Admin meninjau & menyetujui/menyesuaikan skor urgensi → sistem memberi flag (Hijau/Kuning/Merah).
6. Jika "Red Code"/sensitif → otomatis disembunyikan dari daftar publik & diteruskan prioritas ke Pejabat Berwenang terkait.
7. Sistem mengirim notifikasi ke Pejabat Berwenang via Push APK & opsional WhatsApp.
8. Pejabat memberi disposisi/update status → status tersinkron ke daftar publik (tanpa data sensitif) & dashboard.
9. Seluruh laporan dari kanal WhatsApp/Sosial Media ditarik (via webhook/API) ke Dashboard Monitoring Terpadu untuk ditindaklanjuti Admin.

---

## 4. MODUL FRONTEND PUBLIK (TANPA LOGIN)

### 4.1 Alur Pengisian Laporan

1. **Pilih Jenis Pelaporan** — Pengaduan Pelayanan Publik / Whistleblowing (dugaan pelanggaran, gratifikasi, pungli, dll), sesuai kategori Permenpan-RB No. 5/2025.
2. **Input Data Pelapor** — Nomor HP/WhatsApp aktif sebagai Personal Key (PK)/nomor identifikasi pelapor; nama (opsional, dapat anonim); data langsung dienkripsi saat disimpan.
3. **Input Detail 5W1H** — What, Who, Where, When, Why, How — field terpisah agar terstruktur dan siap diproses AI.
4. **Upload Bukti** — multiple file upload (foto/dokumen), validasi tipe & ukuran, watermark otomatis opsional, disimpan terenkripsi.
5. **Isi CAPTCHA** — kode alfabet acak 5 huruf sebagai anti-bot/anti-spam sebelum submit.
6. **Submit** — sistem menampilkan Nomor Tiket Pengaduan + pengingat No. HP adalah PK untuk cek status.

### 4.2 Halaman Cek Status (Berbasis PK/No. HP)

Pelapor dapat memasukkan Nomor Tiket atau Nomor HP (PK) untuk melihat status penanganan laporan yang pernah diajukan, tanpa perlu login akun.

### 4.3 Daftar Pengaduan Publik

- Menampilkan laporan dengan status: Diterima, Diverifikasi, Diteruskan, Dalam Proses, Selesai/Ditutup.
- Identitas pelapor dan detail sensitif **tidak pernah** ditampilkan — hanya ringkasan kategori, wilayah umum, dan status.
- Laporan berflag **"Red Code"** atau ditandai sensitif otomatis **TIDAK tampil** di daftar publik (lihat Bab 7).
- Jika pelapor menuliskan data pribadi (nama, NIK, alamat, no HP orang lain) di kolom uraian bebas area publik, sistem otomatis mendeteksi & menyamarkan (auto-hide/redaction) sebelum ditampilkan publik.

### 4.4 Dashboard Publik (Statistik)

- Grafik jumlah pengaduan per periode (harian/bulanan/tahunan).
- Grafik kategori pengaduan terbanyak (top issues).
- Grafik status penyelesaian (rasio selesai vs proses).
- Peta sebaran wilayah pengaduan (opsional, level kecamatan, tanpa alamat detail).
- Seluruh data agregat — tidak ada data individual/sensitif yang ditampilkan.

---

## 5. MODUL BACKEND (ADMIN, PEJABAT, SUPERUSER)

### 5.1 Login & Manajemen Peran

Login khusus untuk Admin, Pejabat Berwenang, dan Superuser menggunakan Laravel Breeze/Fortify dengan Two-Factor Authentication (2FA) wajib untuk Superuser. Manajemen role & permission menggunakan Spatie Laravel-Permission.

### 5.2 Manajemen Laporan Masuk

- List seluruh laporan (real-time) dengan filter: jenis, kategori, tanggal, status, tingkat urgensi, kanal asal (Web/WhatsApp/Sosmed).
- Detail laporan menampilkan 5W1H, bukti foto, hasil rekomendasi skor AI, dan riwayat status (log/timeline).
- Admin dapat menyetujui, menurunkan, atau menaikkan skor urgensi hasil AI berdasarkan penilaian manusia (human-in-the-loop).
- Fitur "Teruskan ke Pejabat" — memilih pejabat/bidang tujuan sesuai kategori laporan, otomatis memicu notifikasi.

### 5.3 Penilaian Urgensi Berbasis AI

Setiap laporan baru diproses melalui job asynchronous yang mengirim ringkasan 5W1H ke layanan AI untuk mendapatkan rekomendasi tingkat urgensi awal, mempertimbangkan kata kunci sensitif, potensi kerugian, keterlibatan pejabat, dan indikasi pelanggaran hukum.

| Tingkat Urgensi (Flag) | Kriteria Indikatif | SLA Tindak Lanjut (acuan awal) |
|---|---|---|
| 🔴 **Red Code** (Kritis/Sensitif) | Dugaan pidana, korupsi/gratifikasi, kekerasan, keterlibatan pejabat tinggi, risiko keselamatan. | Maks. 1×24 jam, tidak tampil publik |
| 🟠 **Tinggi** | Dampak signifikan pada layanan/masyarakat luas, berulang. | Maks. 3×24 jam |
| 🟡 **Sedang** | Keluhan layanan standar, berdampak individual/kelompok kecil. | Maks. 7 hari kerja |
| 🟢 **Rendah** | Saran, masukan, keluhan ringan/administratif. | Maks. 14 hari kerja |

Skor & tingkatan AI bersifat rekomendasi — keputusan final tetap berada pada Admin (persetujuan manual) sebelum status "terverifikasi urgensi" ditetapkan dan diteruskan, sesuai prinsip *human-in-the-loop* untuk akuntabilitas.

> ⚠️ **Perlu Verifikasi**: Mapping tingkatan urgensi di atas adalah usulan awal berbasis praktik umum whistleblowing system. Definisi final beserta SLA wajib disesuaikan/diverifikasi dengan ketentuan resmi dalam Permenpan-RB No. 5 Tahun 2025 pada tahap Analisis Kebutuhan bersama pihak Disdukcapil.

### 5.4 Flag & Status Laporan

- **Flag Urgensi**: Red Code, Tinggi, Sedang, Rendah — ditampilkan sebagai badge warna di list & detail.
- **Status Proses**: Baru Masuk → Menunggu Verifikasi AI → Terverifikasi Admin → Diteruskan ke Pejabat → Dalam Penanganan → Selesai/Ditutup → (opsional) Ditolak/Tidak Valid.
- Setiap perubahan status tercatat di audit trail (waktu, aktor, catatan) demi transparansi dan akuntabilitas.

### 5.5 Dashboard Monitoring Terpadu (Web, WhatsApp, Sosial Media)

Dashboard tunggal bagi Admin untuk memantau pengaduan yang masuk dari berbagai kanal:

- Formulir Web (utama).
- WhatsApp (via WhatsApp Business API/Gateway — pesan masuk otomatis dikonversi menjadi tiket laporan draft untuk dilengkapi Admin).
- Media Sosial (integrasi API resmi platform seperti Instagram/Facebook/X untuk mention/DM yang mengandung kata kunci pengaduan — masuk sebagai antrian untuk ditriase manual oleh Admin).
- Setiap sumber diberi label kanal asal agar Admin dapat melacak dan menyatukan laporan duplikat dari kanal berbeda.

### 5.6 Manajemen Superuser

- Akses buka data pribadi pelapor per-laporan (dengan alasan wajib diisi & tercatat log).
- Manajemen akun Admin & Pejabat Berwenang beserta hak aksesnya per bidang.
- Konfigurasi ambang batas (threshold) AI, kata kunci sensitif, dan pengaturan integrasi eksternal (WA Gateway, Firebase, API sosmed).
- Melihat audit log menyeluruh seluruh aktivitas sistem.

---

## 6. NOTIFIKASI MULTI-KANAL

### 6.1 Push Notification — Aplikasi Android (.APK)

- Aplikasi companion Android untuk Admin & Pejabat Berwenang (bukan untuk publik/pelapor).
- Dibangun dengan Flutter atau WebView terintegrasi Firebase Cloud Messaging (FCM).
- Berisi: notifikasi laporan baru, notifikasi eskalasi/laporan diteruskan, notifikasi laporan Red Code (prioritas tinggi), ringkasan status harian.
- Autentikasi aplikasi menggunakan token API (Laravel Sanctum) yang terhubung ke akun backend masing-masing pengguna.

### 6.2 Notifikasi WhatsApp

- Notifikasi otomatis ke Admin/Pejabat saat ada laporan baru sesuai bidang & tingkat urgensi (khususnya Red Code).
- Notifikasi status ke Pelapor (opsional, jika berkenan menerima update) melalui nomor HP yang didaftarkan sebagai PK, tanpa membocorkan isi sensitif pihak lain.
- Menggunakan WhatsApp Business API resmi/mitra gateway berizin — bukan solusi tidak resmi, demi keamanan & keberlanjutan layanan.

---

## 7. KEAMANAN DAN PERLINDUNGAN DATA

| Aspek | Penerapan |
|---|---|
| Enkripsi Data Pribadi | Nama & No. HP pelapor dienkripsi AES-256 di database (bukan hanya hashing satu arah, karena Superuser perlu membuka data bila diperlukan). |
| Kontrol Akses | Role-based access control: Admin & Pejabat hanya melihat data tersamar (masked); hanya Superuser dapat mendekripsi, dengan log wajib. |
| Auto-Redaction Publik | Deteksi pola data pribadi (NIK 16 digit, no. HP, nama+alamat) pada teks uraian yang tampil di halaman publik, otomatis disensor menjadi `***`. |
| CAPTCHA Anti-Bot | CAPTCHA gambar acak 5 huruf pada form publik untuk mencegah spam/robot submission. |
| Rate Limiting | Pembatasan jumlah submit per IP/No. HP dalam rentang waktu tertentu untuk mencegah penyalahgunaan. |
| Audit Log | Seluruh aksi (lihat data sensitif, ubah status, teruskan laporan) tercatat lengkap: aktor, waktu, aksi, IP. |
| Transmisi Data | Wajib HTTPS/TLS di seluruh endpoint, termasuk API untuk aplikasi Android. |
| Kepatuhan Regulasi | Struktur alur & retensi data disesuaikan prinsip UU PDP dan pedoman Permenpan-RB No. 5/2025. |

> 🔒 **Desain Keamanan**: Data pada tabel `report_reporters` (`name_enc`, `phone_enc`) disimpan terenkripsi menggunakan Laravel Encrypted Casts, terpisah secara fisik/tabel dari data laporan (`reports`) agar query & tampilan publik/Admin tidak pernah menyentuh tabel ini secara langsung kecuali melalui service khusus yang hanya bisa dipanggil role Superuser.

---

## 8. DESAIN UI/UX — GLASSMORPHISM

### 8.1 Prinsip Desain

- **Glassmorphism**: elemen kartu/panel semi-transparan dengan efek blur latar (`backdrop-filter: blur`), border tipis semi-transparan, dan bayangan lembut (soft shadow) untuk kesan modern & elegan.
- Palet warna institusional (biru navy & putih) dipadukan aksen gradasi lembut agar tetap formal namun kontemporer.
- Animasi ringan berbasis library gratis: AOS (Animate on Scroll), Alpine.js Transition, atau Tailwind CSS transition/animation utilities — untuk transisi antar section, hover state, dan loading skeleton.
- **Fully Responsive**: mobile-first, mendukung breakpoint mobile, tablet, dan desktop; komponen form pengaduan dioptimalkan untuk pengisian via ponsel.
- **Aksesibilitas**: kontras warna memadai, label form jelas, dukungan navigasi keyboard dasar.

### 8.2 Komponen Utama

- Landing page publik dengan hero section, ringkasan statistik, dan tombol "Buat Pengaduan".
- Form multi-step (wizard) dengan progress indicator untuk alur pengisian 1→2→3→4.
- Kartu status laporan dengan badge warna urgensi & status.
- Dashboard admin dengan sidebar modern, grafik interaktif (Chart.js/ApexCharts), dan tabel data responsif (DataTables).

---

## 9. RANCANGAN SKEMA DATABASE (RINGKAS)

| Tabel | Kolom Kunci | Keterangan |
|---|---|---|
| `reports` | id, ticket_no, category, type (pengaduan/whistleblowing), what, who, where, when, how, why, status, urgency_flag, channel, created_at | Data inti laporan (dapat diakses tersamar oleh Admin/Pejabat) |
| `report_reporters` | id, report_id (FK), name_enc, phone_enc (PK/no HP), created_at | Data pelapor terenkripsi — hanya Superuser dapat mendekripsi |
| `report_attachments` | id, report_id (FK), file_path, file_type, uploaded_at | Bukti foto/dokumen |
| `report_ai_assessments` | id, report_id (FK), ai_score, ai_suggested_flag, ai_raw_response, reviewed_by, approved_flag, reviewed_at | Hasil & riwayat penilaian AI + persetujuan Admin |
| `report_status_logs` | id, report_id (FK), old_status, new_status, actor_id, note, created_at | Riwayat/timeline status laporan (audit trail) |
| `report_assignments` | id, report_id (FK), assigned_to (pejabat), assigned_by (admin), assigned_at | Riwayat eskalasi/penerusan ke pejabat berwenang |
| `users` | id, name, email, phone, role, is_2fa_enabled | Akun Admin, Pejabat Berwenang, Superuser (Spatie roles) |
| `channel_inbox` | id, source (whatsapp/sosmed), external_ref, raw_message, linked_report_id, status | Antrian pesan masuk dari WhatsApp/sosmed sebelum dikonversi jadi laporan |
| `audit_logs` | id, user_id, action, subject_type, subject_id, ip_address, created_at | Log seluruh aktivitas sensitif sistem |

---

## 10. PRINSIP PENGEMBANGAN: DRY & CLEAN CODE

- **Service Layer Pattern**: seluruh logika bisnis (submit laporan, scoring AI, eskalasi) ditempatkan di Service Class, bukan di Controller, agar reusable antara Web Controller & API Controller.
- **Repository Pattern** (opsional untuk query kompleks): memisahkan logika akses data dari logika bisnis.
- **Form Request Validation**: seluruh validasi input menggunakan Laravel Form Request class terpisah per aksi (mis. `StoreReportRequest`), bukan validasi inline di Controller.
- **Resource/DTO**: response API menggunakan Laravel API Resource agar format output konsisten dan terpisah dari struktur database.
- **Event & Listener**: proses seperti "laporan disubmit" memicu Event yang didengarkan Listener terpisah (kirim ke AI queue, kirim notifikasi) — decoupling antar proses.
- **Policy & Gate**: otorisasi akses data (khususnya data pelapor) diatur melalui Laravel Policy, bukan pengecekan role manual berulang di banyak tempat.
- **Reusable Blade Components**: komponen UI (card status, badge urgensi, form step) dibuat sebagai Blade Component agar tidak duplikasi markup.
- **Konfigurasi terpusat** (`.env` & `config/*.php`) untuk seluruh kredensial integrasi (AI API key, WA Gateway, Firebase) — tidak ada hardcode.
- **Automated Testing**: unit test untuk Service kritikal (scoring, enkripsi, redaction) dan feature test untuk alur submit laporan.

---

## 11. RENCANA PENGEMBANGAN PER FASE (CHECKLIST)

Pengembangan dilakukan bertahap (iteratif) agar setiap fase menghasilkan modul yang dapat diuji fungsinya sebelum lanjut ke fase berikutnya. Centang setiap item saat selesai dikerjakan.

### FASE 1 — Analisis, Perancangan & Fondasi Sistem
- [ ] Kajian mendalam Permenpan-RB No. 5/2025 & pemetaan kategori/urgensi/SLA resmi bersama pihak Disdukcapil
- [ ] Finalisasi ERD, wireframe UI (glassmorphism), dan arsitektur teknis
- [x] Setup project Laravel, struktur folder Service/Repository, konfigurasi environment (dev/staging)
- [x] Setup autentikasi Admin (Breeze/Fortify) + role & permission (Spatie)

**Output:** Dokumen desain final, database migration awal, project skeleton siap dikembangkan.

### FASE 2 — Modul Frontend Publik: Formulir Pengaduan
- [x] Halaman landing publik (desain glassmorphism + animasi)
- [x] Form multi-step: pilih jenis → data pelapor → 5W1H → upload bukti → CAPTCHA
- [x] Enkripsi data pelapor saat penyimpanan; generate nomor tiket
- [x] Halaman cek status via No. HP (PK) / nomor tiket

**Output:** Masyarakat dapat mengajukan laporan dan mengecek status secara mandiri.

### FASE 3 — Modul Backend Admin: Manajemen Laporan
- [x] Dashboard Admin: list laporan, filter, detail laporan (data tersamar)
- [x] Manajemen status & flag manual (sebelum AI diaktifkan)
- [x] Fitur teruskan laporan ke Pejabat Berwenang + akun & akses Pejabat
- [x] Modul Superuser: akses buka data pelapor + audit log dasar

**Output:** Alur triase & eskalasi laporan berjalan penuh secara manual.

### FASE 4 — Integrasi AI Urgency Scoring
- [x] Integrasi API AI untuk analisis teks 5W1H → rekomendasi skor & flag urgensi (Anthropic/OpenAI/Gemini, dapat dikonfigurasi Superuser)
- [x] Job Queue asynchronous agar proses AI tidak menghambat submit pelapor
- [x] UI persetujuan (approve/adjust) skor AI oleh Admin (human-in-the-loop)
- [x] Logika otomatis: flag Red Code → sembunyikan dari daftar publik (query scope, siap dipakai Fase 5)

**Output:** Triase laporan lebih cepat & konsisten dengan bantuan AI, tetap dengan validasi manusia.

### FASE 5 — Dashboard Publik & Auto-Redaction Data Pribadi
- [x] Dashboard publik: grafik statistik, top issues, status distribution
- [x] Daftar pengaduan publik dengan filter status & kategori
- [x] Modul deteksi & penyamaran otomatis data pribadi pada teks publik

**Output:** Transparansi publik aktif tanpa risiko kebocoran data pribadi.

### FASE 6 — Notifikasi Multi-Kanal (APK & WhatsApp)
- [ ] Pengembangan aplikasi Android (APK) untuk Admin/Pejabat + integrasi Firebase Cloud Messaging
- [ ] Integrasi WhatsApp Business API untuk notifikasi Admin/Pejabat & update status ke pelapor
- [ ] API backend (Laravel Sanctum) untuk mendukung aplikasi Android

**Output:** Admin & Pejabat menerima notifikasi real-time di luar dashboard web.

### FASE 7 — Dashboard Monitoring Terpadu (Sosmed & WhatsApp Inbox)
- [ ] Integrasi webhook WhatsApp untuk menangkap pesan masuk sebagai draft laporan
- [ ] Integrasi API resmi media sosial (sesuai platform yang disepakati) untuk memantau mention/DM terkait pengaduan
- [ ] Dashboard "Inbox Terpadu" bagi Admin untuk mengonversi pesan menjadi tiket laporan resmi

**Output:** Seluruh kanal pengaduan terpantau dalam satu dashboard admin.

### FASE 8 — Keamanan, Pengujian, & Go-Live
- [ ] Hardening keamanan: penetration testing dasar, review enkripsi & access control
- [ ] User Acceptance Test (UAT) bersama Disdukcapil (Admin, Pejabat, Superuser)
- [ ] Optimasi performa, dokumentasi teknis & manual pengguna, pelatihan operator
- [ ] Deployment ke server produksi & monitoring pasca go-live

**Output:** Sistem siap dan aman digunakan secara resmi (production-ready).

---

## 12. PENUTUP

Dokumen Project Design Report ini menjadi acuan awal perancangan Sistem Pengaduan dan Whistleblowing (SI-WBS) Dinas Kependudukan dan Pencatatan Sipil Kabupaten Tulang Bawang Barat. Detail teknis (skema database lengkap, API contract, mockup UI definitif, dan pemetaan pasal Permenpan-RB No. 5/2025) akan disempurnakan pada dokumen turunan (Technical Design Document/SRS) di Fase 1 pengembangan bersama tim internal Disdukcapil.

Dengan pendekatan pengembangan bertahap per fase, prinsip clean code, serta perlindungan data pelapor yang ketat, sistem ini diharapkan dapat meningkatkan kepercayaan publik dan efektivitas penanganan pengaduan sekaligus whistleblowing di lingkungan Disdukcapil Kabupaten Tulang Bawang Barat.
