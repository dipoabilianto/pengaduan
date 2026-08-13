<?php

namespace App\Exports;

use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Built directly on phpoffice/phpspreadsheet rather than the maatwebsite/excel wrapper:
 * that package pins phpspreadsheet to ^1.30.0, and every 1.30.x patch release after
 * 1.30.0 (the only unpatched one) caps `php <8.5.0` — incompatible with this project's
 * PHP 8.5 runtime. Depending on phpspreadsheet ^2.4 directly gets the CVE fixes without
 * that conflict; the wrapper only saved a small amount of boilerplate anyway.
 *
 * Reporter identity (name/phone) is intentionally excluded — bulk export stays as
 * anonymous as the reports list, identity is only revealed one at a time with an
 * audit log (see ReportAdminService::revealReporterIdentity).
 */
class ReportsExport
{
    private const HEADINGS = ['No. Tiket', 'Jenis', 'Kategori', 'Status', 'Urgensi', 'Kanal', 'Tanggal Lapor'];

    public function __construct(private readonly Builder $query) {}

    public function download(string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan');
        $sheet->fromArray(self::HEADINGS, null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        $row = 2;
        foreach ($this->query->get() as $report) {
            $sheet->fromArray($this->mapRow($report), null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * @return array<int,string>
     */
    private function mapRow(Report $report): array
    {
        return [
            $report->ticket_no,
            $report->type === 'whistleblowing' ? 'Whistleblowing' : 'Pengaduan',
            $report->category,
            $report->statusLabel(),
            $report->urgency_flag ? Report::URGENCY_LABELS[$report->urgency_flag] : 'Belum dinilai',
            ucfirst($report->channel),
            $report->created_at->format('d/m/Y H:i'),
        ];
    }
}
