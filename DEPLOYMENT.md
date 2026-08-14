# Panduan Deploy — Rumahweb Cloud Hosting (cPanel + SSH)

Panduan ini untuk deploy Sidumas ke hosting berbasis cPanel dengan akses SSH (mis. Rumahweb
Cloud Hosting), yang **tidak** punya Supervisor/systemd dan **tidak** bisa membuka port
kustom ke publik. Dua konsekuensi arsitektur dari batasan itu:

1. **WebSocket (live chat) pakai Pusher, bukan Reverb self-hosted.** Reverb butuh proses
   yang jalan terus-menerus di port sendiri — tidak reliable di cPanel. Pusher berbicara
   protokol yang sama persis, jadi tidak ada perubahan kode di luar konfigurasi env (lihat
   `resources/js/echo-config.js` dan `ChatBroadcastAuthController`).
2. **Queue worker jalan lewat cron, bukan proses permanen.** `php artisan queue:work` yang
   dibiarkan jalan terus akan mati kena limit proses/CPU hosting. Cron tiap menit yang
   menjalankan `queue:work` (TANPA `--stop-when-empty` — lihat catatan di langkah 7) adalah
   pola standar untuk shared/cloud hosting.

## 1. Persiapan

- [ ] Domain sudah diarahkan (DNS A record) ke hosting.
- [ ] Database MySQL sudah dibuat di cPanel (MySQL Databases), catat nama DB/user/password.
- [ ] Akun [Pusher](https://pusher.com) (Channels) — buat app baru, catat `app_id`, `key`,
      `secret`, `cluster`. Free tier cukup untuk mulai (100 koneksi bersamaan, 200rb pesan/hari).
- [ ] PHP versi 8.3–8.5 aktif di cPanel (MultiPHP Manager) untuk domain ini.
- [ ] Ekstensi PHP: `pdo_mysql`, `mbstring`, `bcmath`, `intl`, `gd` atau `imagick`, `zip` —
      cek di MultiPHP INI Editor / `php -m` lewat SSH.
- [ ] Composer tersedia lewat SSH (`composer -V`) — kalau belum, cPanel biasanya sudah sedia
      lewat "Setup PHP App" atau bisa unduh `composer.phar` manual.
- [ ] Node.js **opsional** — cek `node -v`/`npm -v` lewat SSH. Kalau tidak ada, build asset
      (`npm run build`) dilakukan di komputer lokal lalu folder `public/build` diunggah manual
      (lihat langkah 4).

## 2. Struktur Direktori di cPanel

Document root domain **harus** mengarah ke folder `public/` proyek, bukan root proyek —
kalau tidak, `.env` dan kode aplikasi jadi bisa diakses langsung lewat browser. Tiga cara:

- **Paling mudah — symlink `public_html` ke folder `public/` proyek** (SSH):
  ```bash
  cd ~
  mv public_html public_html.bak        # cadangkan dulu isi bawaan cPanel
  ln -s /home/USER/sidumas/public public_html
  ```
  Tidak perlu edit `index.php` apa pun — `__DIR__.'/..'` di `public/index.php` menembus
  symlink dan tetap menunjuk ke `~/sidumas`. Kalau panel protes, balikkan dengan
  `rm public_html && mv public_html.bak public_html`, lalu pakai cara kedua/ketiga.
- **Kalau cPanel mengizinkan ubah document root** (Domains → Manage → Document Root): arahkan
  langsung ke `namadomain.com/public`.
- **Kalau tidak bisa** (banyak paket shared/cloud hosting): taruh proyek di luar
  `public_html` (mis. `~/sidumas`), lalu isi `public_html/` cuma dengan **isi** folder
  `public/` proyek (`index.php`, `.htaccess`, dst — bukan foldernya) dan edit
  `public_html/index.php`: ganti dua baris `require __DIR__.'/../vendor/autoload.php'` dan
  `require_once __DIR__.'/../bootstrap/app.php'` supaya menunjuk ke `~/sidumas/vendor/...`
  dan `~/sidumas/bootstrap/...` (path absolut sesuai lokasi upload).

## 3. Clone & Install

Repo: https://github.com/dipoabilianto/pengaduan.git

```bash
ssh user@namadomain.com
cd ~
git clone https://github.com/dipoabilianto/pengaduan.git sidumas
cd sidumas
composer install --no-dev --optimize-autoloader
```

Kalau repo-nya *private*, clone HTTPS harus pakai Personal Access Token
(`https://<token>@github.com/...`) atau pasang SSH key di server dan clone lewat
`git@github.com:dipoabilianto/pengaduan.git`.

## 4. Build Asset Frontend

Kalau Node.js tersedia lewat SSH:

```bash
npm ci
npm run build
```

Kalau tidak tersedia — build di laptop/komputer lokal (`npm run build` di clone proyek yang
sama), lalu unggah folder `public/build/` hasilnya lewat File Manager/SFTP ke lokasi `public/`
di server.

## 5. Konfigurasi `.env`

```bash
cp .env.example .env
php artisan key:generate
```

**PENTING**: `APP_KEY` dipakai untuk enkripsi data sensitif (nomor HP pelapor, token API AI/
WhatsApp/Instagram yang disimpan lewat halaman Pengaturan). Jangan pernah generate ulang
`APP_KEY` setelah data mulai masuk — semua yang terenkripsi jadi tidak bisa dibaca lagi.
Backup nilai `APP_KEY` di tempat aman begitu sudah di-generate sekali.

Edit `.env`, minimal ubah:

```env
APP_NAME="SI-WBS Disdukcapil Tubaba"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://namadomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database_cpanel
DB_USERNAME=user_database_cpanel
DB_PASSWORD=password_database_cpanel

SESSION_DRIVER=database
QUEUE_CONNECTION=database

BROADCAST_CONNECTION=pusher
VITE_BROADCAST_DRIVER=pusher
PUSHER_APP_ID=isi_dari_dashboard_pusher
PUSHER_APP_KEY=isi_dari_dashboard_pusher
PUSHER_APP_SECRET=isi_dari_dashboard_pusher
PUSHER_APP_CLUSTER=isi_dari_dashboard_pusher

# Wajib sama dengan variabel pusher di atas — dipakai runtime oleh JS asset:
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

MAIL_MAILER=smtp
MAIL_HOST=mail.namadomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@namadomain.com
MAIL_PASSWORD=password_email_cpanel
MAIL_FROM_ADDRESS=noreply@namadomain.com
```

Kalau assetnya di-build ulang setelah mengisi `VITE_BROADCAST_DRIVER`/`VITE_PUSHER_*`
(langkah 4 di atas dilakukan SEBELUM `.env` diisi), jalankan `npm run build` sekali lagi
setelah `.env` lengkap — nilai `VITE_*` di-bake ke dalam file JS saat build, bukan dibaca
runtime.

## 6. Migrasi, Storage, Permission

```bash
php artisan migrate --force
php artisan storage:link
php artisan db:seed --class=RolesTableSeeder   # role + permission saja (belum ada user)

chmod -R 775 storage bootstrap/cache
```

Lalu buat akun admin. `RolesTableSeeder` TIDAK membuat user (berbeda dengan
`DatabaseSeeder` yang membuat 4 akun uji-coba berpassword `password`). Untuk produksi
lebih aman buat 1 akun superuser langsung dengan password kuat lewat tinker
(ganti email dan password):

```bash
php artisan tinker --execute="\$u = App\Models\User::updateOrCreate(['email' => 'admin@disdukcapiltubaba.go.id'], ['name' => 'Admin Utama', 'password' => 'PASSWORD_AMAN_PANJANG_UNIK', 'email_verified_at' => now()]); \$u->assignRole('superuser');"
```

Kalau lebih suka jalur cepat `php artisan db:seed --force` (DatabaseSeeder) untuk
membuat 4 akun awal (`superuser@sidumas.test`, `admin@sidumas.test`,
`pejabat@sidumas.test`, `pengawas@sidumas.test` — semua berpassword **`password`**),
maka **segera login dan ganti password semuanya** sebelum aplikasi diumumkan ke publik.

## 7. Cron Job

Di cPanel → Cron Jobs, tambahkan (sesuaikan path ke lokasi proyek dan versi PHP):

```
* * * * * /usr/local/bin/php83 /home/user/sidumas/artisan schedule:run >> /dev/null 2>&1
* * * * * /usr/local/bin/php83 /home/user/sidumas/artisan queue:work --max-time=55 --tries=3 >> /home/user/sidumas/storage/logs/queue-cron.log 2>&1
```

- Baris pertama menjalankan scheduler (ringkasan harian jam 07:00, heartbeat AI tiap 5
  menit, nudge/auto-close chat tiap 15 menit — semua sudah didefinisikan di
  `routes/console.php`, tidak perlu cron terpisah per job). Kalau hosting mematikan
  `proc_open`/`shell_exec` di `disable_functions` (umum di shared hosting — lihat
  `deploy/production.sh`), Laravel scheduler butuh itu untuk menjalankan
  `Schedule::command(...)` — pakai PHP binary yang sudah di-heal (lihat catatan akhir
  skrip deploy), bukan PHP bawaan, kalau baris ini gagal dengan error proc_open.
- Baris kedua adalah "pengganti" queue worker permanen: **JANGAN tambahkan
  `--stop-when-empty`** — flag itu membuat worker langsung keluar begitu antrean kosong,
  padahal balasan AI di chat sengaja diberi jeda simulasi "mengetik" (1,2–6 detik, lihat
  `AnswerChatMessageWithAiJob::typingDelayFor()`) sebelum job kedua (`PostChatAiReplyJob`)
  benar-benar tersedia untuk diproses. Kalau worker sudah keluar duluan, job kedua itu baru
  tertangkap di **cron menit berikutnya** — menambah ~1 menit jeda ekstra di atas jeda
  mengetik yang seharusnya cuma beberapa detik (pernah terjadi persis di produksi). Tanpa
  `--stop-when-empty`, `queue:work` terus memeriksa antrean tiap beberapa detik selama
  hampir 55 detik penuh, menangkap job yang baru tersedia di tengah siklus yang sama. Ini
  memproses penilaian AI, balasan chat AI, dan notifikasi WhatsApp/push/Instagram.
  Konsekuensinya: ada jeda maksimal ~1 menit sebelum pesan citizen PERTAMA mulai diproses
  (dibanding worker permanen yang langsung memproses) — cukup untuk skala aplikasi ini,
  dan balasan-balasan berikutnya dalam siklus yang sama jauh lebih cepat.

## 8. Setelah Deploy — Wajib Dicek

- [ ] **Akun admin.** Kalau memakai `db:seed --force`, ganti password 4 akun default
      (`superuser@sidumas.test`, dst — semua `password`) sebelum aplikasi diumumkan ke
      publik. Kalau memakai tinker (langkah 6), pastikan email & password sudah diisi
      dengan nilai yang kuat.
- [ ] **`APP_ENV=production` dan `APP_DEBUG=false`** — cek di `.env`. Kalau `APP_DEBUG=true`,
      error stack trace dan kredensial bisa bocor ke publik.
- [ ] Isi kredensial AI (Groq/Anthropic/OpenAI/Gemini) lewat menu Pengaturan → AI di admin
      (bukan lewat `.env` — aplikasi ini menyimpannya terenkripsi di database, bukan env var).
- [ ] Isi kredensial WhatsApp/Instagram (kalau dipakai) lewat menu Pengaturan masing-masing,
      lalu daftarkan URL webhook (`https://namadomain.com/api/webhooks/whatsapp` dan
      `/api/webhooks/instagram`) di Meta App Dashboard.
- [ ] SSL: aktifkan AutoSSL (Let's Encrypt) di cPanel untuk domain ini — `APP_URL` harus
      `https://` dan cookie sesi butuh koneksi aman untuk login admin.
- [ ] Cek `storage/logs/laravel.log` dan `storage/logs/queue-cron.log` beberapa saat setelah
      deploy untuk memastikan tidak ada error tersembunyi (queue gagal jalan, migrasi
      bermasalah, dst).

## 9. Verifikasi Akhir

1. Buka `https://namadomain.com` — halaman publik & form pengaduan bisa diakses.
2. Buka `https://namadomain.com/up` — harus menampilkan `{"status":"ok"}` (health check
   bawaan Laravel, bukti aplikasi bangkit tanpa error).
3. Login admin, cek dashboard tampil (artinya DB & migrasi benar).
4. Submit satu laporan uji coba lewat form publik — cek beberapa menit kemudian apakah
   penilaian AI muncul di halaman detail laporan (kalau kredensial AI sudah diisi) —
   ini membuktikan cron queue worker benar-benar jalan.
5. Buka widget chat di halaman publik, kirim pesan — balasan AI/petugas harus muncul
   real-time tanpa refresh (ini membuktikan Pusher + `ChatBroadcastAuthController`
   terkonfigurasi benar). Kalau tidak real-time tapi lainnya normal, cek console browser
   untuk error koneksi WebSocket dan cocokkan `PUSHER_APP_CLUSTER` di `.env` server dengan
   `VITE_PUSHER_APP_CLUSTER` yang ter-build ke asset (lihat catatan di langkah 5).
6. Pastikan `https://` semua asset (Mixpanel/DevTools → tab Network) — kalau ada asset
   http, indikasi `APP_URL` atau document root salah.
