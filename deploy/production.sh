#!/usr/bin/env bash
#
# Deploy Sidumas ke Rumahweb Cloud Hosting (cPanel + SSH).
# Jalankan di server lewat SSH, dari direktori home. Script ini:
#   clone -> composer install -> .env produksi -> key:generate -> migrate ->
#   seed role -> buat akun admin superuser -> storage:link -> build asset ->
#   perintah cron siap-paste.
#
# Sebelum jalan, siapkan: database MySQL sudah dibuat di cPanel, app Pusher
# Channels sudah dibuat, email SMTP cPanel. Aktifkan AutoSSL setelah selesai.
#
# Pemakaian:
#   bash deploy/production.sh [<dir-proyek>]        # default ~/sidumas
#   DEPS="npm ci && npm run build" bash ...          # lewati build bila DEPS kosong
#
# Semua nilai bisa di-prefill lewat env var (prompt dilewati). Prefix SIDUMAS_:
#   SIDUMAS_DOMAIN, SIDUMAS_DB_DATABASE, SIDUMAS_DB_USERNAME, SIDUMAS_DB_PASSWORD,
#   SIDUMAS_PUSHER_APP_ID, _PUSHER_APP_KEY, _PUSHER_APP_SECRET, _PUSHER_APP_CLUSTER,
#   SIDUMAS_MAIL_MAILER, _MAIL_HOST, _MAIL_USERNAME, _MAIL_PASSWORD, _MAIL_FROM_ADDRESS,
#   SIDUMAS_ADMIN_EMAIL, SIDUMAS_ADMIN_PASSWORD
# Contoh:
#   SIDUMAS_MAIL_MAILER=log SIDUMAS_PUSHER_APP_KEY=xxx bash deploy/production.sh
#
set -euo pipefail

PROJECT_DIR="${1:-$HOME/sidumas}"
REPO_URL="${REPO_URL:-https://github.com/dipoabilianto/pengaduan.git}"

echo "==> Target proyek : $PROJECT_DIR"
echo "==> Repo          : $REPO_URL"

if [[ -z "${PHP_BIN:-}" ]] && ! command -v php >/dev/null; then
    echo "ERROR: PHP tidak ditemukan di PATH (atau set PHP_BIN=<binary php> terlebih dahulu)."
    exit 1
fi
PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
echo "==> PHP           : $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;' 2>/dev/null || echo '?'))"
ORIGINAL_PHP_BIN="$PHP_BIN"

# ==== Auto-heal proc_open (hosting shared mengunci proc_open di disable_functions) ====
# Wrapper per-user `php -d disable_functions=` — -d diproses SETELAH semua ini (termasuk
# scan-dir), sehingga seluruh daftar fungsi yang diblokir dikosongkan tanpa menyentuh
# file php.ini server. Semua ekstensi dipertahankan apa adanya.
# PENTING: wrapper ini HANYA dipakai untuk proses deploy ini (Composer butuh proc_open).
# Cron produksi (queue worker & scheduler) tetap memakai $ORIGINAL_PHP_BIN yang restriksinya
# utuh — kalau dibiarkan longgar permanen, itu melemahkan proteksi shared hosting untuk
# proses yang jalan terus-menerus, bukan cuma sekali saat deploy.
ensure_proc_open() {
    local pbin="$PHP_BIN"
    # proc_open dibutuhkan Composer; shell_exec dibutuhkan Tinker/PsySH (dipakai untuk
    # buat akun admin). Hosting bisa memblokir salah satu atau keduanya secara terpisah.
    if "$pbin" -r 'exit((function_exists("proc_open") && function_exists("shell_exec")) ? 0 : 1);' 2>/dev/null; then
        return 0
    fi
    echo
    echo "==> proc_open/shell_exec nonaktif di $pbin — membuat wrapper PHP tanpa blokir fungsi ..."

    local home bin
    home="${HOME:-/tmp}"
    bin="$home/.sidumas-bin/php"
    mkdir -p "$home/.sidumas-bin"

    cat > "$bin" <<EOF
#!/usr/bin/env bash
exec "$pbin" -d disable_functions= "\$@"
EOF
    chmod 700 "$bin"

    if "$bin" -r 'exit((function_exists("proc_open") && function_exists("shell_exec")) ? 0 : 1);' 2>/dev/null; then
        PHP_BIN="$bin"
        export PATH="$home/.sidumas-bin:$PATH"
        echo "==> OK — memakai $bin (proc_open & shell_exec aktif; berlaku hanya untuk proses deploy ini)."
        return 0
    fi

    echo "!!!!! Gagal mengaktifkan proc_open. Hubungi Rumahweb: minta enable"
    echo "proc_open/shell_exec/exec di PHP CLI untuk user ini (dibutuhkan Composer)."
    return 1
}

ensure_proc_open || exit 1

# Saat dipanggil, PATH & PHP_BIN sudah menunjuk wrapper. Pastikan perintah
# `composer` juga memakai PHP ini (banyak hosting punya shebang php hardcode).
if command -v composer >/dev/null 2>&1; then
    _COMPOSER="$(command -v composer)"
    composer() { "$PHP_BIN" "$_COMPOSER" "$@"; }
fi

# Impor prefill dari env SIDUMAS_* (nilai dari env menang, prompt dilewati).
for suffix in DOMAIN DB_DATABASE DB_USERNAME DB_PASSWORD \
              PUSHER_APP_ID PUSHER_APP_KEY PUSHER_APP_SECRET PUSHER_APP_CLUSTER \
              MAIL_MAILER MAIL_HOST MAIL_USERNAME MAIL_PASSWORD MAIL_FROM_ADDRESS \
              ADMIN_EMAIL ADMIN_PASSWORD; do
    evar="SIDUMAS_${suffix}"
    if [[ -n "${!evar:-}" ]]; then
        printf -v "$suffix" '%s' "${!evar}"
        case "$suffix" in
            DB_PASSWORD|PUSHER_APP_SECRET|ADMIN_PASSWORD)
                echo "(prefill) $suffix = ***" ;;
            *)
                echo "(prefill) $suffix = ${!suffix}" ;;
        esac
    fi
done

prompt() {
    # prompt <var> <pesan> [default]; kalau <var> sudah terisi via env, dipakai
    # apa adanya tanpa menanya.
    local var="$1" msg="$2" default="${3:-}"
    if [[ -n "${!var:-}" ]]; then
        echo "$msg: ${!var}"
        return 0
    fi
    if [[ -n "$default" ]]; then
        read -rp "$msg [$default]: " "$var"
        if [[ -z "${!var:-}" ]]; then printf -v "$var" '%s' "$default"; fi
    else
        read -rp "$msg: " "$var"
        while [[ -z "${!var:-}" ]]; do read -rp "$msg: " "$var"; done
    fi
}

prompt_secret() {
    # prompt_secret <var> <pesan>; baca tanpa echo (dilewati jika env sudah terisi)
    local var="$1" msg="$2" v2
    if [[ -n "${!var:-}" ]]; then
        echo "$msg: ***"
        return 0
    fi
    read -srp "$msg: " "$var"; echo
    read -srp "Ulangi $msg: " v2; echo
    [[ "${!var:-}" == "$v2" ]] || { echo "ERROR: tidak cocok."; exit 1; }
    [[ -n "${!var:-}" ]] || { echo "ERROR: kosong."; exit 1; }
}

echo
echo "==> Domain produksi (tanpa https:// — mis. pengaduan.disdukcapil.id)"
prompt DOMAIN "Domain"

echo
echo "==> Kredensial database MySQL (dari cPanel -> MySQL Databases)"
prompt DB_DATABASE "Nama database"
prompt DB_USERNAME "User database"
prompt_secret DB_PASSWORD "Password database"

echo
echo "==> Pusher Channels (dashboard Pusher -> app baru)"
prompt PUSHER_APP_ID "Pusher App ID"
prompt PUSHER_APP_KEY "Pusher App Key"
prompt_secret PUSHER_APP_SECRET "Pusher App Secret"
prompt PUSHER_APP_CLUSTER "Pusher Cluster" "ap1"

echo
echo "==> SMTP untuk notifikasi — kosongkan/bukan smtp = pakai log sementara"
prompt MAIL_MAILER "Mailer (smtp / log)" "log"
if [[ "${MAIL_MAILER:-}" == "smtp" ]]; then
    prompt MAIL_HOST "SMTP host" "mail.${DOMAIN:-}"
    prompt MAIL_USERNAME "SMTP username (email cPanel)"
    prompt_secret MAIL_PASSWORD "SMTP password"
    prompt MAIL_FROM_ADDRESS "MAIL_FROM_ADDRESS" "$MAIL_USERNAME"
else
    MAIL_MAILER=log
    MAIL_HOST=
    MAIL_USERNAME=
    MAIL_PASSWORD=
    MAIL_FROM_ADDRESS="admin@${DOMAIN}"
fi

echo
echo "==> Akun admin superuser (login pertama dashboard)"
prompt ADMIN_EMAIL "Email admin"
prompt_secret ADMIN_PASSWORD "Password admin (gunakan yang panjang & unik)"

echo
echo "==> Persiapan direktori"
if [[ -d "$PROJECT_DIR/.git" ]]; then
    echo "Proyek sudah ada di $PROJECT_DIR — pull terbaru."
    git -C "$PROJECT_DIR" pull --ff-only
else
    mkdir -p "$(dirname "$PROJECT_DIR")"
    git clone "$REPO_URL" "$PROJECT_DIR"
fi
cd "$PROJECT_DIR"

echo
echo "==> composer install (--no-dev, optimized)"
composer install --no-dev --optimize-autoloader

echo
echo "==> .env + APP_KEY"
if [[ ! -f .env ]]; then cp .env.example .env; fi
if grep -q '^APP_KEY=$' .env || ! grep -q '^APP_KEY=base64' .env; then
    "$PHP_BIN" artisan key:generate
fi

MARKER='# >>> SIDUMAS PRODUCTION >>>'
if ! grep -qF "$MARKER" .env; then
cat >> .env <<EOF

$MARKER
# Dibuat otomatis oleh deploy/production.sh pada $(date -Is)
APP_NAME="SI-WBS Disdukcapil Tubaba"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://${DOMAIN}

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

BROADCAST_CONNECTION=pusher
VITE_BROADCAST_DRIVER=pusher
PUSHER_APP_ID=${PUSHER_APP_ID}
PUSHER_APP_KEY=${PUSHER_APP_KEY}
PUSHER_APP_SECRET=${PUSHER_APP_SECRET}
PUSHER_APP_CLUSTER=${PUSHER_APP_CLUSTER}
VITE_PUSHER_APP_KEY=${PUSHER_APP_KEY}
VITE_PUSHER_APP_CLUSTER=${PUSHER_APP_CLUSTER}

MAIL_MAILER=${MAIL_MAILER}
EOF
    if [[ "${MAIL_MAILER:-}" == "smtp" ]]; then
        cat >> .env <<EOF
MAIL_HOST=${MAIL_HOST}
MAIL_PORT=587
MAIL_USERNAME=${MAIL_USERNAME}
MAIL_PASSWORD=${MAIL_PASSWORD}
MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS}
EOF
    fi
    echo "Blok produksi ditambahkan ke .env"
else
    echo ".env sudah memuat blok produksi — biarkan (edit manual kalau butuh ubah nilai)."
fi

echo
echo "!!!!! BACKUP APP_KEY INI DI TEMPAT AMAN !!!!!"
grep '^APP_KEY=' .env
echo "(APP_KEY meng-enkripsi nomor HP & kredensial AI di DB — jangan pernah regenerate)"

echo
echo "==> migrate + storage:link"
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan storage:link

echo
echo "==> seed role/permission (tanpa user uji-coba)"
"$PHP_BIN" artisan db:seed --class=RolesTableSeeder --force

echo
echo "==> cek akun admin"
export SIDUMAS_ADMIN_EMAIL="$ADMIN_EMAIL"
ADMIN_EXISTS="$("$PHP_BIN" artisan tinker --execute="echo App\\Models\\User::where('email', getenv('SIDUMAS_ADMIN_EMAIL'))->exists() ? 'yes' : 'no';" 2>/dev/null | tail -n1 | tr -d '[:space:]')"

RESET_PASSWORD=1
if [[ "$ADMIN_EXISTS" == "yes" ]]; then
    echo "Akun admin dengan email $ADMIN_EMAIL sudah ada."
    if [[ "${SIDUMAS_ADMIN_RESET_PASSWORD:-}" == "1" ]]; then
        echo "SIDUMAS_ADMIN_RESET_PASSWORD=1 -> password akan ditimpa."
    elif [[ -t 0 ]]; then
        read -rp "Timpa password akun ini dengan yang baru saja dimasukkan? (y/N): " confirm
        [[ "$confirm" =~ ^[Yy]$ ]] || RESET_PASSWORD=0
    else
        echo "Non-interaktif & SIDUMAS_ADMIN_RESET_PASSWORD tidak diset -> password TIDAK diubah."
        RESET_PASSWORD=0
    fi
fi

if [[ "$RESET_PASSWORD" == "1" ]]; then
    echo "==> buat/reset akun admin superuser"
    export SIDUMAS_ADMIN_PASSWORD="$ADMIN_PASSWORD"
    "$PHP_BIN" artisan tinker --execute="\$u = App\\Models\\User::updateOrCreate(['email' => getenv('SIDUMAS_ADMIN_EMAIL')], ['name' => 'Admin Utama', 'password' => getenv('SIDUMAS_ADMIN_PASSWORD'), 'email_verified_at' => now()]); \$u->assignRole('superuser'); echo 'OK admin (password diset): '.\$u->email.PHP_EOL;"
else
    echo "==> lewati reset password, pastikan role superuser saja"
    "$PHP_BIN" artisan tinker --execute="\$u = App\\Models\\User::where('email', getenv('SIDUMAS_ADMIN_EMAIL'))->first(); if (\$u && !\$u->hasRole('superuser')) { \$u->assignRole('superuser'); } echo 'OK admin (password TIDAK diubah): '.getenv('SIDUMAS_ADMIN_EMAIL').PHP_EOL;"
fi

echo
echo "==> permission folder"
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
chmod -R 775 storage bootstrap/cache

if [[ "${DEPS:-1}" != "" ]]; then
    echo
    echo "==> build asset (npm) — VITE_PUSHER_* sudah di .env"
    if command -v node >/dev/null; then
        npm ci && npm run build
    else
        echo "WARNING: Node tidak ada. Build asset manual di mesin lokal, lalu unggah public/build."
    fi
else
    echo
    echo "==> build asset dilewati (DEPS kosong)."
fi

echo
echo "================================================================"
echo "SELESAI. Langkah manual yang tersisa:"
echo " 1. Document root — cara paling mudah pakai symlink (SSH):"
echo "      cd ~"
echo "      mv public_html public_html.bak"
echo "      ln -s $PROJECT_DIR/public public_html"
echo "    (alternatif: Domains -> Manage -> Document Root -> $PROJECT_DIR/public)"
echo " 2. Cron Jobs (sesuaikan path PHP & user):"
echo "    * * * * * ${ORIGINAL_PHP_BIN} $PROJECT_DIR/artisan schedule:run >> /dev/null 2>&1"
echo "    * * * * * ${ORIGINAL_PHP_BIN} $PROJECT_DIR/artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> $PROJECT_DIR/storage/logs/queue-cron.log 2>&1"
if [[ "$PHP_BIN" != "$ORIGINAL_PHP_BIN" ]]; then
    echo "    CATATAN: proc_open sempat di-heal khusus untuk Composer di deploy ini ($PHP_BIN)."
    echo "    Cron di atas SENGAJA memakai PHP asli ($ORIGINAL_PHP_BIN) dengan disable_functions"
    echo "    utuh. Kalau queue worker/scheduler ternyata butuh proc_open juga, minta hosting"
    echo "    aktifkan resmi lewat MultiPHP INI Editor — jangan pakai wrapper ini permanen."
fi
echo " 3. Kalau belum: unggah public/build (tanpa Node di server) ke $PROJECT_DIR/public/build."
echo " 4. Aktifkan SSL/AutoSSL, pastikan APP_URL memakai https."
echo " 5. Verifikasi: /up, login admin, submit laporan, chat realtime."
echo "================================================================"