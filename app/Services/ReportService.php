<?php

namespace App\Services;

use App\Jobs\NotifyNewReportJob;
use App\Jobs\ScoreReportUrgencyJob;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportReporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportService
{
    /**
     * @param  array{type:string,category:string,reported_party?:?string,name:?string,phone:string,what:string,who:?string,where:?string,when:?string,how:?string,why:?string,channel?:string}  $data
     * @param  UploadedFile[]  $attachments
     */
    public function submit(array $data, array $attachments = []): Report
    {
        $report = Report::create([
            'ticket_no' => $this->generateTicketNo(),
            'category' => $data['category'],
            'reported_party' => $data['reported_party'] ?? null,
            'type' => $data['type'],
            'what' => $data['what'],
            'who' => $data['who'] ?? null,
            'where' => $data['where'] ?? null,
            'when' => $data['when'] ?? null,
            'how' => $data['how'] ?? null,
            'why' => $data['why'] ?? null,
            'status' => 'baru_masuk',
            'channel' => $data['channel'] ?? 'web',
        ]);

        ReportReporter::create([
            'report_id' => $report->id,
            'name_enc' => $data['name'] ?? null,
            'phone_enc' => $data['phone'],
            'created_at' => now(),
        ]);

        foreach ($attachments as $file) {
            $this->storeAttachment($report, $file);
        }

        ScoreReportUrgencyJob::dispatch($report);
        NotifyNewReportJob::dispatch($report);

        return $report;
    }

    private function storeAttachment(Report $report, UploadedFile $file): ReportAttachment
    {
        $encryptedPath = 'report-attachments/'.$report->id.'/'.Str::uuid()->toString();

        Storage::disk('local')->put($encryptedPath, Crypt::encrypt(file_get_contents($file->getRealPath())));

        return ReportAttachment::create([
            'report_id' => $report->id,
            'file_path' => $encryptedPath,
            'file_type' => $file->getClientMimeType(),
            'uploaded_at' => now(),
        ]);
    }

    /**
     * Look up reports by ticket number or by the reporter's phone (PK),
     * matching phone via its blind index (phone_hash) rather than decrypting rows.
     *
     * @return \Illuminate\Support\Collection<int, Report>
     */
    public function findByTicketOrPhone(string $query): \Illuminate\Support\Collection
    {
        $byTicket = Report::with('statusLogs')->where('ticket_no', $query)->get();
        if ($byTicket->isNotEmpty()) {
            return $byTicket;
        }

        return ReportReporter::with('report.statusLogs')
            ->where('phone_hash', ReportReporter::hashPhone($query))
            ->get()
            ->map(fn (ReportReporter $reporter) => $reporter->report)
            ->filter()
            ->values();
    }

    private function generateTicketNo(): string
    {
        do {
            $candidate = 'WBS-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Report::where('ticket_no', $candidate)->exists());

        return $candidate;
    }
}
