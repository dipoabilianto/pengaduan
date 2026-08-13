---
name: penilaian-urgensi-ai
description: Kriteria dan kebijakan resmi penilaian urgensi laporan oleh AI di Sidumas (5 flag, prinsip isi vs kelengkapan data, definisi tidak_valid). WAJIB dibaca sebelum mengubah app/Services/Ai/BuildsUrgencyPrompt.php atau logika terkait urgency_flag/ai_suggested_flag, dan jadi rujukan saat menjelaskan/mendiskusikan cara AI mengambil keputusan penilaian.
---

## Prinsip utama (ditetapkan pemilik produk, 2026-08-12)

**Isi/substansi pengaduan diutamakan di atas kelengkapan data.** Laporan yang datanya
minim (cuma 1-2 field What/Who/Where/When/How/Why terisi) TETAP dinilai berdasarkan
isinya, BUKAN otomatis diarahkan ke `tidak_valid` atau skor rendah hanya karena kurang
lengkap. Alasannya: laporan singkat sekalipun bisa jadi cikal bakal laporan yang lebih
lengkap di kemudian hari setelah ditindaklanjuti Admin — kelengkapan boleh disebut di
`reasoning` sebagai catatan, tapi tidak boleh jadi alasan menurunkan flag ke `tidak_valid`.

## Definisi 5 flag urgensi (`Report::URGENCY_LABELS`)

| Flag | Kriteria |
|---|---|
| `red_code` | Dugaan pidana, korupsi/gratifikasi, kekerasan, keterlibatan pejabat tinggi, risiko keselamatan |
| `tinggi` | Dampak signifikan pada layanan/masyarakat luas, berulang |
| `sedang` | Keluhan layanan standar, berdampak individual/kelompok kecil |
| `rendah` | Saran, masukan, keluhan ringan/administratif |
| `tidak_valid` | **HANYA** dua kasus: (1) isi benar-benar asal mengetik/acak/spam/uji coba sistem, atau (2) isi berupa pertanyaan/hal yang sama sekali tidak sesuai substansi layanan pengaduan instansi (di luar konteks layanan) |

**`tidak_valid` BUKAN untuk**: laporan yang datanya minim/singkat tapi tetap berisi
keluhan, saran, masukan, atau mengindikasikan dampak pada individu/layanan — itu harus
dinilai lewat kriteria `red_code`/`tinggi`/`sedang`/`rendah` sesuai isi yang ada, walau
ringkas. Kalau ragu antara `tidak_valid` dan `rendah`, pilih `rendah`.

## Skor (0-100)

- Field `score` independen dari `flag` — tidak ada mapping/threshold otomatis antara
  keduanya di kode (mis. tidak ada aturan "score < 20 → tidak_valid").
  `app/Services/Ai/BuildsUrgencyPrompt.php` (`parseAssessment()`) hanya meng-clamp
  skor ke rentang 0-100 (`max(0, min(100, ...))`), tidak memvalidasi konsistensinya
  terhadap flag.
- Kalau produk butuh korelasi skor↔flag yang lebih ketat di masa depan, itu perubahan
  yang harus eksplisit — jangan diasumsikan sudah ada.

## Implementasi & alur (referensi cepat)

- Prompt aktual (system + user): `app/Services/Ai/BuildsUrgencyPrompt.php`
- Dipicu otomatis (async) saat laporan masuk: `app/Services/ReportService::submit()` →
  `app/Jobs/ScoreReportUrgencyJob`
- Hasil AI cuma REKOMENDASI, tersimpan di `report_ai_assessments.ai_suggested_flag` —
  BUKAN keputusan final. `reports.urgency_flag` baru terisi setelah Admin approve lewat
  `ReportAdminService::approveAiAssessment()` (human-in-the-loop, Admin bisa override).
- `tidak_valid` wajib berpasangan dengan status `ditolak` — ditegakkan di form request
  dan `ReportAdminService::updateStatus()`.
- `red_code` tidak pernah muncul di listing laporan publik
  (`Report::scopePubliclyVisible`).
- Kriteria ini masih ditandai draf di komentar kode — mencerminkan PDR Bab 5.3, pending
  verifikasi legal terhadap Permenpan-RB No. 5/2025 (Bab 1.4 PDR).

## Kapan skill ini relevan

- Mengubah/meninjau prompt urgensi AI atau logika parsing hasilnya.
- Menjawab pertanyaan pemilik produk/admin soal "kenapa AI menandai laporan ini X".
- Menulis/mengubah test yang menguji skoring urgensi
  (`tests/Unit/Services/Ai/BuildsUrgencyPromptTest.php`, dll).
- Mendiskusikan perubahan kebijakan urgensi baru — catat perubahan berikutnya juga di
  sini, bukan cuma di riwayat percakapan, supaya jadi acuan permanen untuk sesi berikutnya.
