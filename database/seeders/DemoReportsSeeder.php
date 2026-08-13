<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Database\Seeder;

/**
 * 15 laporan demo berstatus "Baru Masuk", dibuat lewat ReportService::submit() (bukan
 * Report::create() langsung) supaya persis meniru jalur publik yang sebenarnya: dispatch
 * ScoreReportUrgencyJob (penilaian AI otomatis) + NotifyNewReportJob, sama seperti laporan
 * yang masuk dari halaman "Buat Pengaduan". Isi kontennya sengaja bervariasi (mendesak,
 * biasa, samar/kurang informatif, dugaan pidana) supaya rekomendasi urgensi dari AI juga
 * bervariasi — bukan untuk seed produksi, cuma untuk latihan alur verifikasi Admin.
 *
 * Dipanggil manual (tidak ikut DatabaseSeeder::run() default): php artisan db:seed --class=DemoReportsSeeder
 */
class DemoReportsSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ReportService::class);

        foreach ($this->reports() as $data) {
            $service->submit($data);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reports(): array
    {
        return [
            [
                'type' => 'pengaduan',
                'category' => 'Pelayanan Administrasi Kependudukan',
                'name' => 'Sri Wahyuni',
                'phone' => '081234500001',
                'what' => 'Pengajuan KTP elektronik saya sudah lebih dari 3 bulan belum juga selesai. Setiap saya tanya ke loket, petugas hanya bilang "tunggu saja" tanpa kepastian kapan selesainya.',
                'where' => 'Kantor Disdukcapil Kabupaten Tulang Bawang Barat',
                'when' => now()->subDays(95)->toDateString(),
                'how' => 'Sudah datang langsung 4 kali ke loket dan menelepon 2 kali, jawaban selalu sama.',
            ],
            [
                'type' => 'pengaduan',
                'category' => 'Keterlambatan Proses Layanan',
                'name' => 'Budi Santoso',
                'phone' => '081234500002',
                'what' => 'Akta kelahiran anak saya diajukan 2 bulan lalu dan belum terbit, padahal anak saya harus mendaftar sekolah dasar minggu depan dan akta ini jadi syarat wajib pendaftaran.',
                'where' => 'Kantor Disdukcapil Kecamatan Tumijajar',
                'when' => now()->subDays(60)->toDateString(),
                'why' => 'Butuh segera untuk pendaftaran sekolah anak.',
            ],
            [
                'type' => 'pengaduan',
                'category' => 'Perilaku/Sikap Petugas',
                'reported_party' => 'Petugas loket pelayanan KTP',
                'name' => 'Ahmad Fauzi',
                'phone' => '081234500003',
                'what' => 'Petugas di loket membentak ibu saya yang sudah lanjut usia hanya karena bertanya ulang prosedur. Bicaranya kasar dan tidak sabar, membuat ibu saya menangis di tempat.',
                'where' => 'Loket 2, Kantor Disdukcapil',
                'when' => now()->subDays(5)->toDateString(),
            ],
            [
                'type' => 'pengaduan',
                'category' => 'Sarana & Prasarana Pelayanan',
                'name' => 'Dewi Lestari',
                'phone' => '081234500004',
                'what' => 'AC di ruang tunggu mati total sudah beberapa minggu. Warga menunggu berjam-jam kepanasan dan kursi yang tersedia jauh lebih sedikit dari jumlah pengunjung setiap harinya.',
                'where' => 'Ruang tunggu utama Kantor Disdukcapil',
            ],
            [
                'type' => 'pengaduan',
                'category' => 'Lainnya',
                'name' => 'Rudi Hartono',
                'phone' => '081234500005',
                'what' => 'Situs untuk cek status pengajuan Kartu Keluarga terus menampilkan error setiap saya coba login, sudah dicoba dari HP dan laptop berbeda tapi hasilnya sama.',
            ],
            [
                'type' => 'pengaduan',
                'category' => 'Pelayanan Administrasi Kependudukan',
                'name' => 'Siti Aminah',
                'phone' => '081234500006',
                'what' => 'Pengajuan pindah domisili untuk Kartu Keluarga saya ditolak tiga kali berturut-turut tanpa penjelasan yang jelas dari petugas mengenai berkas apa yang sebenarnya masih kurang.',
                'where' => 'Kantor Disdukcapil Kecamatan Tulang Bawang Tengah',
            ],
            [
                'type' => 'pengaduan',
                'category' => 'Keterlambatan Proses Layanan',
                'name' => 'Hendra Gunawan',
                'phone' => '081234500007',
                'what' => 'Pengurusan akta kematian orang tua saya sudah diajukan sebulan lalu dan belum selesai, padahal saya sangat membutuhkannya segera untuk keperluan klaim asuransi dan pembagian warisan keluarga.',
                'when' => now()->subDays(30)->toDateString(),
            ],
            [
                'type' => 'pengaduan',
                'category' => 'Perilaku/Sikap Petugas',
                'reported_party' => 'Oknum petugas loket percepatan dokumen',
                'name' => null,
                'phone' => '081234500008',
                'what' => 'Saat mengurus dokumen, saya diminta memberi "uang rokok" secara halus oleh petugas supaya prosesnya dipercepat. Ini bukan biaya resmi dan tidak ada kuitansinya sama sekali.',
                'where' => 'Kantor Disdukcapil',
            ],
            [
                'type' => 'pengaduan',
                'category' => 'Sarana & Prasarana Pelayanan',
                'name' => 'Nur Kholis',
                'phone' => '081234500009',
                'what' => 'Mesin nomor antrian sudah rusak sejak sebulan lalu dan belum diperbaiki. Warga jadi harus berebut posisi antrean secara manual setiap pagi, sering menimbulkan cekcok kecil.',
            ],
            [
                'type' => 'pengaduan',
                'category' => 'Lainnya',
                'name' => 'Yanto',
                'phone' => '081234500010',
                'what' => 'mau tanya jam buka hari sabtu, udah telepon ga diangkat',
            ],
            [
                'type' => 'whistleblowing',
                'category' => 'Pungutan Liar / Pemerasan',
                'reported_party' => 'Oknum petugas berinisial "R" di loket percetakan KTP',
                'name' => null,
                'phone' => '081234500011',
                'what' => 'Saya diminta membayar tunai Rp200.000 secara langsung di luar loket resmi, tanpa kuitansi apa pun, oleh oknum petugas berinisial "R" supaya KTP saya bisa selesai lebih cepat dari jadwal normal. Kejadian ini disaksikan juga oleh dua warga lain yang antre di hari yang sama.',
                'where' => 'Area parkir belakang Kantor Disdukcapil',
                'when' => now()->subDays(10)->toDateString(),
                'how' => 'Diminta secara langsung dan lisan, tidak ada bukti tertulis resmi.',
            ],
            [
                'type' => 'whistleblowing',
                'category' => 'Penyalahgunaan Wewenang/Jabatan',
                'reported_party' => 'Kepala Seksi Pelayanan Dokumen',
                'name' => 'Pelapor tidak ingin disebutkan namanya',
                'phone' => '081234500012',
                'what' => 'Saya melihat langsung bahwa Kepala Seksi memprioritaskan pengurusan dokumen kerabat dan kenalan pribadinya sehingga langsung selesai dalam hitungan jam, sementara warga biasa yang antre resmi harus menunggu berminggu-minggu untuk dokumen yang sama.',
                'where' => 'Kantor Disdukcapil, ruang pelayanan dokumen',
            ],
            [
                'type' => 'pengaduan',
                'category' => 'Pelayanan Administrasi Kependudukan',
                'name' => 'Fitriani',
                'phone' => '081234500013',
                'what' => 'Nama saya salah cetak di KTP (ada typo satu huruf). Saat saya ajukan perbaikan, saya malah diminta mengajukan permohonan dari awal lagi seperti pemohon baru, padahal ini murni kesalahan pencetakan dari pihak kantor.',
            ],
            [
                'type' => 'pengaduan',
                'category' => 'Perilaku/Sikap Petugas',
                'name' => 'Joko Prasetyo',
                'phone' => '081234500014',
                'what' => 'Petugas di loket terlihat sibuk bermain HP selama jam pelayanan berlangsung, sementara antrean warga menumpuk dan tidak ada yang dipanggil selama lebih dari 30 menit.',
                'where' => 'Loket 3',
            ],
            [
                'type' => 'pengaduan',
                'category' => 'Lainnya',
                'name' => null,
                'phone' => '081234500015',
                'what' => 'test doang, gapenting',
            ],
        ];
    }
}
