<?php

namespace Tests\Feature\Admin;

use App\Models\Report;
use App\Models\ReportAiAssessment;
use App\Models\ReportAssignment;
use App\Models\ReportAttachment;
use App\Models\User;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportShowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesTableSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');

        return $user;
    }

    private function superuser(): User
    {
        $this->seed(RolesTableSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('superuser');

        return $user;
    }

    /**
     * Once a report is "Dalam Penanganan", only whoever it's actually assigned to (or
     * Superuser) may act on it — a generic Administrator no longer can. Tests exercising
     * the action UI/endpoints at that status need this instead of admin().
     */
    private function pejabatAssignedTo(Report $report): User
    {
        $this->seed(RolesTableSeeder::class);

        $pejabat = User::factory()->create();
        $pejabat->assignRole('Pelaksana');

        ReportAssignment::create([
            'report_id' => $report->id,
            'assigned_to' => $pejabat->id,
            'assigned_by' => User::factory()->create()->id,
            'assigned_at' => now(),
        ]);

        return $pejabat;
    }

    private function report(array $attributes = []): Report
    {
        return Report::create(array_merge([
            'ticket_no' => 'TCK-'.fake()->unique()->numerify('####'),
            'type' => 'pengaduan',
            'category' => 'Pelayanan Administrasi Kependudukan',
            'channel' => 'web',
            'status' => 'baru_masuk',
            'what' => 'Contoh kronologi kejadian untuk pengujian.',
        ], $attributes));
    }

    public function test_shows_merged_card_with_approve_form_when_ai_recommendation_is_pending(): void
    {
        $report = $this->report();

        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 45,
            'ai_suggested_flag' => 'sedang',
            'ai_raw_response' => [],
            'ai_reasoning' => 'Alasan pengujian.',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Rekomendasi Urgensi AI &amp; Ubah Status', false);
        $response->assertSee('Alasan pengujian.');
        $response->assertSee('Setujui / Sesuaikan');
        $response->assertDontSee('Simpan Perubahan');
    }

    public function test_shows_generic_status_form_when_no_ai_recommendation_is_pending(): void
    {
        $report = $this->report(['status' => 'baru_masuk']);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Rekomendasi Urgensi AI &amp; Ubah Status', false);
        $response->assertSee('Verifikasi &amp; Lanjutkan', false);
        $response->assertSee('Tolak Laporan');
        $response->assertDontSee('Setujui / Sesuaikan');
        // Urgency not decided yet — the manual picker must be offered.
        $response->assertSee('name="urgency_flag"', false);
        $response->assertSee('— Belum dinilai —', false);
    }

    public function test_hides_manual_urgency_picker_once_urgency_is_already_set(): void
    {
        $report = $this->report(['status' => 'dalam_penanganan', 'urgency_flag' => 'sedang']);

        $response = $this->actingAs($this->pejabatAssignedTo($report))->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Tandai Selesai');
        // No editable <select> for urgency anymore, just a read-only badge...
        $response->assertDontSee('id="urgency_flag"', false);
        $response->assertSee('<span class="font-medium">Sedang</span>', false);
        // ...but the value still round-trips via a hidden field so it isn't wiped on submit.
        $response->assertSee('<input type="hidden" name="urgency_flag" value="sedang">', false);

        // Row order: urgency field must render before the status field in the markup.
        $content = $response->getContent();
        $urgencyPos = strpos($content, 'Urgensi</label>');
        $statusPos = strpos($content, 'Status</label>');
        $this->assertNotFalse($urgencyPos);
        $this->assertNotFalse($statusPos);
        $this->assertLessThan($statusPos, $urgencyPos, 'Urgensi row must come before Status row.');
    }

    public function test_forward_action_button_is_labeled_for_manual_verification_at_baru_masuk(): void
    {
        // No AI assessment recorded — this is the "AI is off, admin verifies manually" path,
        // which should behave like approving an AI recommendation: one explicit action button
        // moves the report forward (Terverifikasi Admin), no dropdown involved.
        $report = $this->report(['status' => 'baru_masuk']);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Verifikasi &amp; Lanjutkan', false);

        // And actually clicking that action (submitting status=terverifikasi_admin) must move
        // the report forward, end to end — backend is unchanged by the button redesign.
        $update = $this->actingAs($this->admin())->patch(route('admin.reports.status', $report), [
            'status' => 'terverifikasi_admin',
            'urgency_flag' => 'sedang',
        ]);

        $update->assertRedirect();
        $this->assertSame('terverifikasi_admin', $report->fresh()->status);
        $this->assertSame('sedang', $report->fresh()->urgency_flag);
    }

    public function test_forward_action_button_is_labeled_per_current_status(): void
    {
        $report = $this->report(['status' => 'dalam_penanganan', 'urgency_flag' => 'sedang']);

        $response = $this->actingAs($this->pejabatAssignedTo($report))->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Tandai Selesai');
        $response->assertDontSee('Verifikasi &amp; Lanjutkan', false);
    }

    public function test_tandai_selesai_button_is_disabled_until_a_reply_is_written(): void
    {
        $report = $this->report(['status' => 'dalam_penanganan', 'urgency_flag' => 'sedang']);

        $response = $this->actingAs($this->pejabatAssignedTo($report))->get(route('admin.reports.show', $report));

        $response->assertOk();
        // A report must never look "Selesai" to the reporter without an actual reply — the
        // button stays disabled until the reply textarea has content.
        $response->assertSee(':disabled="submitting || !urgencyFlag || (true && !reply.trim())"', false);
        $response->assertSee('Isi balasan untuk pelapor dulu sebelum menandai selesai.');
    }

    public function test_forward_button_is_not_gated_by_reply_at_earlier_stages(): void
    {
        $report = $this->report(['status' => 'terverifikasi_admin', 'urgency_flag' => 'sedang']);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        // "Teruskan ke Pejabat" isn't the finishing action, so it must not require a reply.
        $response->assertSee(':disabled="submitting || !urgencyFlag || (false && !reply.trim())"', false);
        $response->assertDontSee('Isi balasan untuk pelapor dulu sebelum menandai selesai.');
    }

    /**
     * "Alihkan ke Pejabat Lain" is a peer handoff (Pejabat currently assigned, or
     * Superuser) — not an Admin ability. Superuser is used for the pure status-gating
     * checks below so role permission never masks what's actually being tested.
     */
    public function test_hides_assign_to_pejabat_card_before_report_is_verified(): void
    {
        $report = $this->report(['status' => 'baru_masuk']);

        $response = $this->actingAs($this->superuser())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertDontSee('Alihkan ke Pejabat Lain', false);
    }

    public function test_hides_assign_to_pejabat_card_once_report_is_closed(): void
    {
        foreach (['selesai', 'ditolak'] as $status) {
            $report = $this->report(['status' => $status]);

            $response = $this->actingAs($this->superuser())->get(route('admin.reports.show', $report));

            $response->assertOk();
            $response->assertDontSee('Alihkan ke Pejabat Lain', false);
        }
    }

    public function test_hides_assign_to_pejabat_card_at_terverifikasi_admin_since_it_is_now_inline(): void
    {
        $report = $this->report(['status' => 'terverifikasi_admin', 'urgency_flag' => 'sedang']);

        $response = $this->actingAs($this->superuser())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertDontSee('Alihkan ke Pejabat Lain', false);
        // The inline picker itself must be present (even if hidden via x-show until selected).
        $response->assertSee('id="pejabat_id"', false);
    }

    private function assignedPejabat(Report $report, User $admin, array $attributes = []): User
    {
        $pejabat = User::factory()->create($attributes);
        $pejabat->assignRole('Pelaksana');

        ReportAssignment::create([
            'report_id' => $report->id,
            'assigned_to' => $pejabat->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        return $pejabat;
    }

    public function test_shows_assign_to_pejabat_card_to_the_assigned_pejabat_once_report_is_already_routed(): void
    {
        $admin = $this->admin();

        $report = $this->report(['status' => 'dalam_penanganan', 'urgency_flag' => 'sedang']);
        $pejabat = $this->assignedPejabat($report, $admin);

        $response = $this->actingAs($pejabat)->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Alihkan ke Pejabat Lain', false);
    }

    public function test_shows_assign_to_pejabat_card_to_superuser_even_without_being_assigned(): void
    {
        $report = $this->report(['status' => 'dalam_penanganan', 'urgency_flag' => 'sedang']);
        $this->assignedPejabat($report, $this->admin());

        $response = $this->actingAs($this->superuser())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Alihkan ke Pejabat Lain', false);
    }

    public function test_admin_can_no_longer_see_the_alihkan_card(): void
    {
        $admin = $this->admin();
        $report = $this->report(['status' => 'dalam_penanganan', 'urgency_flag' => 'sedang']);
        $this->assignedPejabat($report, $admin);

        $response = $this->actingAs($admin)->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertDontSee('Alihkan ke Pejabat Lain', false);
    }

    public function test_alihkan_card_shows_who_the_report_is_currently_assigned_to(): void
    {
        $admin = $this->admin();
        $report = $this->report(['status' => 'dalam_penanganan', 'urgency_flag' => 'sedang']);
        $pejabat = $this->assignedPejabat($report, $admin, ['name' => 'Kepala Bidang Pelayanan']);

        $response = $this->actingAs($pejabat)->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Alihkan ke Pejabat Lain', false);
        $response->assertSee('sedang ditangani oleh');
        $response->assertSee('Kepala Bidang Pelayanan');
    }

    public function test_assigned_pejabat_can_alihkan_report_to_another_pejabat(): void
    {
        $admin = $this->admin();
        $report = $this->report(['status' => 'dalam_penanganan', 'urgency_flag' => 'sedang']);
        $currentPejabat = $this->assignedPejabat($report, $admin);
        $otherPejabat = User::factory()->create();
        $otherPejabat->assignRole('Pelaksana');

        $response = $this->actingAs($currentPejabat)->post(route('admin.reports.assign', $report), [
            'pejabat_id' => $otherPejabat->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertTrue($report->assignments()->where('assigned_to', $otherPejabat->id)->exists());
    }

    public function test_admin_cannot_alihkan_report_to_another_pejabat(): void
    {
        $admin = $this->admin();
        $report = $this->report(['status' => 'dalam_penanganan', 'urgency_flag' => 'sedang']);
        $this->assignedPejabat($report, $admin);
        $otherPejabat = User::factory()->create();
        $otherPejabat->assignRole('Pelaksana');

        $response = $this->actingAs($admin)->post(route('admin.reports.assign', $report), [
            'pejabat_id' => $otherPejabat->id,
        ]);

        $response->assertForbidden();
    }

    public function test_unassigned_pejabat_cannot_alihkan_a_report_they_do_not_hold(): void
    {
        $admin = $this->admin();
        $report = $this->report(['status' => 'dalam_penanganan', 'urgency_flag' => 'sedang']);
        $this->assignedPejabat($report, $admin);
        $outsidePejabat = User::factory()->create();
        $outsidePejabat->assignRole('Pelaksana');
        $otherPejabat = User::factory()->create();
        $otherPejabat->assignRole('Pelaksana');

        $response = $this->actingAs($outsidePejabat)->post(route('admin.reports.assign', $report), [
            'pejabat_id' => $otherPejabat->id,
        ]);

        // An unassigned Pejabat can't even view this report (Bab 2 PDR), so the
        // authorization failure surfaces as a 403 before reaching the assign check.
        $response->assertForbidden();
    }

    public function test_saving_status_with_no_real_change_flashes_a_no_change_notice(): void
    {
        $report = $this->report(['status' => 'terverifikasi_admin', 'urgency_flag' => 'sedang']);

        $response = $this->actingAs($this->admin())->patch(route('admin.reports.status', $report), [
            'status' => 'terverifikasi_admin',
            'urgency_flag' => 'sedang',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Tidak ada perubahan — status laporan tetap sama.');
    }

    public function test_saving_a_real_status_change_flashes_the_success_notice(): void
    {
        $report = $this->report(['status' => 'dalam_penanganan', 'urgency_flag' => 'sedang']);

        $response = $this->actingAs($this->pejabatAssignedTo($report))->patch(route('admin.reports.status', $report), [
            'status' => 'selesai',
            'urgency_flag' => 'sedang',
            'reply' => 'Laporan sudah selesai kami tindak lanjuti.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Status laporan berhasil diperbarui.');
    }

    public function test_server_rejects_marking_selesai_without_ever_writing_a_reply(): void
    {
        $report = $this->report(['status' => 'dalam_penanganan', 'urgency_flag' => 'sedang']);

        $response = $this->actingAs($this->pejabatAssignedTo($report))->patch(route('admin.reports.status', $report), [
            'status' => 'selesai',
            'urgency_flag' => 'sedang',
        ]);

        $response->assertSessionHasErrors('reply');
        $this->assertSame('dalam_penanganan', $report->fresh()->status);
    }

    public function test_server_accepts_marking_selesai_when_a_reply_was_already_saved_earlier(): void
    {
        $report = $this->report([
            'status' => 'dalam_penanganan',
            'urgency_flag' => 'sedang',
            'public_reply' => 'Kabar progres yang sudah ditulis sebelumnya.',
            'public_reply_at' => now(),
        ]);

        $response = $this->actingAs($this->pejabatAssignedTo($report))->patch(route('admin.reports.status', $report), [
            'status' => 'selesai',
            'urgency_flag' => 'sedang',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('selesai', $report->fresh()->status);
    }

    public function test_routing_to_pejabat_via_status_dropdown_requires_a_pejabat(): void
    {
        $report = $this->report(['status' => 'terverifikasi_admin', 'urgency_flag' => 'sedang']);

        $response = $this->actingAs($this->admin())->patch(route('admin.reports.status', $report), [
            'status' => 'dalam_penanganan',
            'urgency_flag' => 'sedang',
        ]);

        $response->assertSessionHasErrors('pejabat_id');
        $this->assertSame('terverifikasi_admin', $report->fresh()->status);
    }

    public function test_routing_to_pejabat_via_status_dropdown_creates_the_assignment(): void
    {
        $this->seed(RolesTableSeeder::class);
        $pejabat = User::factory()->create();
        $pejabat->assignRole('Pelaksana');
        $report = $this->report(['status' => 'terverifikasi_admin', 'urgency_flag' => 'sedang']);

        $response = $this->actingAs($this->admin())->patch(route('admin.reports.status', $report), [
            'status' => 'dalam_penanganan',
            'urgency_flag' => 'sedang',
            'pejabat_id' => $pejabat->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', "Laporan diteruskan ke {$pejabat->name}.");
        $this->assertSame('dalam_penanganan', $report->fresh()->status);
        $this->assertDatabaseHas('report_assignments', [
            'report_id' => $report->id,
            'assigned_to' => $pejabat->id,
        ]);
    }

    public function test_shows_attachment_lightbox_with_all_attachments_when_evidence_uploaded(): void
    {
        $report = $this->report(['status' => 'baru_masuk']);

        ReportAttachment::create([
            'report_id' => $report->id,
            'file_path' => 'reports/evidence-1.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_at' => now(),
        ]);
        ReportAttachment::create([
            'report_id' => $report->id,
            'file_path' => 'reports/evidence-2.pdf',
            'file_type' => 'application/pdf',
            'uploaded_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Lampiran Bukti');
        $response->assertSee('Pratinjau tidak tersedia');

        // The lightbox's Alpine state is seeded via @js(), which double JSON-encodes the
        // payload (once for the data, once to produce a JS single-quoted string literal).
        // Reversing both passes confirms both attachments actually reached the browser,
        // rather than pattern-matching Laravel's internal escaping format by hand.
        preg_match("/items: JSON\.parse\('(.*?)'\)\s*}\"/s", $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'Lightbox items payload not found in response.');
        $items = json_decode(json_decode('"'.$matches[1].'"'), true);

        $this->assertCount(2, $items);
        $this->assertSame(
            route('admin.reports.attachments.show', [$report, $report->attachments[0]]),
            $items[0]['url'],
        );
        $this->assertTrue($items[0]['isImage']);
        $this->assertSame(
            route('admin.reports.attachments.show', [$report, $report->attachments[1]]),
            $items[1]['url'],
        );
        $this->assertFalse($items[1]['isImage']);
    }

    public function test_data_pelapor_card_is_compact_and_has_no_button_for_admin(): void
    {
        $report = $this->report();

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Hanya Superuser yang dapat membukanya.');
        $response->assertDontSee('Buka Data Pelapor');
    }

    public function test_data_pelapor_modal_is_present_but_closed_by_default_for_superuser(): void
    {
        $report = $this->report();

        $response = $this->actingAs($this->superuser())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Buka Data Pelapor');
        $response->assertSee('Alasan membuka data pelapor');
        // Modal markup is present in the HTML but must not be forced open — no revealed
        // identity and no validation error, so the Alpine state should default closed.
        $response->assertDontSee('open: true', false);
    }

    public function test_data_pelapor_modal_opens_automatically_once_identity_is_revealed(): void
    {
        $report = $this->report();

        $response = $this->actingAs($this->superuser())
            ->withSession(['reporterIdentity' => ['name' => 'Budi', 'phone' => '08123456789']])
            ->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee("x-data=\"{ open: true }\"", false);
        $response->assertSee('Budi');
        $response->assertSee('08123456789');
    }

    public function test_riwayat_status_labels_a_note_only_entry_distinctly_from_a_real_status_change(): void
    {
        $report = $this->report(['status' => 'terverifikasi_admin', 'urgency_flag' => 'sedang']);
        $admin = $this->admin();

        \App\Models\ReportStatusLog::create([
            'report_id' => $report->id,
            'old_status' => 'terverifikasi_admin',
            'new_status' => 'terverifikasi_admin',
            'actor_id' => $admin->id,
            'note' => 'Sudah dikonfirmasi via telepon.',
            'created_at' => now(),
        ]);
        \App\Models\ReportStatusLog::create([
            'report_id' => $report->id,
            'old_status' => 'baru_masuk',
            'new_status' => 'terverifikasi_admin',
            'actor_id' => $admin->id,
            'note' => null,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('menambahkan catatan');
        $response->assertSee('(status tetap Terverifikasi Admin)');
        $response->assertSee('mengubah status ke');
        $response->assertSee('Sudah dikonfirmasi via telepon.');
    }

    public function test_status_field_is_locked_for_admin_once_report_is_selesai_or_ditolak(): void
    {
        foreach (['selesai', 'ditolak'] as $status) {
            $report = $this->report(['status' => $status, 'urgency_flag' => 'sedang']);

            $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

            $response->assertOk();
            $response->assertSee('Terkunci — hanya Superuser yang dapat mengubah');
            $response->assertDontSee('id="status"', false);
            $response->assertSee("<input type=\"hidden\" name=\"status\" value=\"{$status}\">", false);
        }
    }

    public function test_status_field_stays_editable_for_superuser_even_when_selesai_or_ditolak(): void
    {
        $report = $this->report(['status' => 'selesai', 'urgency_flag' => 'sedang']);

        $response = $this->actingAs($this->superuser())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertDontSee('Terkunci — hanya Superuser yang dapat mengubah');
        $response->assertSee('id="status"', false);
        // Unrestricted for Superuser here — every status is offered, not just the normal next step.
        $response->assertSee('<option value="baru_masuk"', false);
        $response->assertSee('<option value="dalam_penanganan"', false);
    }

    public function test_admin_cannot_bypass_the_lock_by_forging_the_status_field(): void
    {
        $report = $this->report(['status' => 'selesai', 'urgency_flag' => 'sedang']);

        $response = $this->actingAs($this->admin())->patch(route('admin.reports.status', $report), [
            'status' => 'dalam_penanganan',
            'urgency_flag' => 'sedang',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame('selesai', $report->fresh()->status);
    }

    public function test_superuser_can_reopen_a_selesai_report(): void
    {
        $report = $this->report(['status' => 'selesai', 'urgency_flag' => 'sedang']);

        $response = $this->actingAs($this->superuser())->patch(route('admin.reports.status', $report), [
            'status' => 'dalam_penanganan',
            'urgency_flag' => 'sedang',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('dalam_penanganan', $report->fresh()->status);
    }

    public function test_reply_field_markup_is_present_but_no_saved_reply_yet(): void
    {
        $report = $this->report(['status' => 'baru_masuk']);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        // The field itself is always rendered (x-show hides it client-side, see the next
        // test) — assertSee only proves the markup exists in the response.
        $response->assertSee('id="status_reply"', false);
        // ...but there is no saved reply yet, so the "Hapus Balasan" mini-card must not show.
        $response->assertDontSee('Hapus Balasan');
    }

    public function test_reply_field_is_reactively_hidden_outside_ditolak_dalam_penanganan_selesai(): void
    {
        $report = $this->report(['status' => 'terverifikasi_admin', 'urgency_flag' => 'sedang']);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        // Baru Masuk/Terverifikasi Admin are internal administrative stages — nothing
        // worth telling the reporter yet. The field stays reactive to
        // effectiveStatus (not the report's static status) so it appears immediately if
        // urgency is set to Tidak Valid, without needing a page reload.
        $response->assertSee('x-show="[\'ditolak\', \'dalam_penanganan\', \'selesai\'].includes(effectiveStatus)"', false);
    }

    public function test_admin_can_save_a_reply(): void
    {
        $report = $this->report();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->put(route('admin.reports.reply.update', $report), [
            'reply' => 'Laporan Anda sedang kami tindak lanjuti.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Balasan untuk pelapor tersimpan.');
        $report->refresh();
        $this->assertSame('Laporan Anda sedang kami tindak lanjuti.', $report->public_reply);
        $this->assertSame($admin->id, $report->public_reply_by);
        $this->assertNotNull($report->public_reply_at);
    }

    public function test_reply_cannot_be_saved_empty(): void
    {
        $report = $this->report();

        $response = $this->actingAs($this->admin())->put(route('admin.reports.reply.update', $report), [
            'reply' => '',
        ]);

        $response->assertSessionHasErrors('reply');
        $this->assertNull($report->fresh()->public_reply);
    }

    public function test_admin_can_delete_an_existing_reply(): void
    {
        $admin = $this->admin();
        $report = $this->report([
            'public_reply' => 'Balasan lama.',
            'public_reply_by' => $admin->id,
            'public_reply_at' => now(),
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.reports.reply.destroy', $report));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Balasan untuk pelapor dihapus.');
        $report->refresh();
        $this->assertNull($report->public_reply);
        $this->assertNull($report->public_reply_by);
        $this->assertNull($report->public_reply_at);
    }

    public function test_reply_field_shows_the_existing_reply_with_hapus_button(): void
    {
        // Non-locked status — the assigned Pejabat can still edit/delete the reply normally.
        $report = $this->report([
            'status' => 'dalam_penanganan',
            'urgency_flag' => 'sedang',
            'public_reply' => 'Sudah kami konfirmasi ke pihak terkait.',
            'public_reply_at' => now(),
        ]);
        $pejabat = $this->pejabatAssignedTo($report);

        $response = $this->actingAs($pejabat)->get(route('admin.reports.show', $report));

        $response->assertOk();
        // Pre-filled into the merged form's reply field, not blank.
        $response->assertSee('Sudah kami konfirmasi ke pihak terkait.');
        $response->assertSee('Hapus Balasan');
    }

    public function test_locked_report_reply_is_read_only_for_non_superuser(): void
    {
        // Selesai/Ditolak adalah status akhir — Admin/Pejabat cuma boleh melihat balasan yang
        // sudah tersimpan, tidak bisa lagi mengubah atau menghapusnya.
        $admin = $this->admin();
        $report = $this->report([
            'status' => 'ditolak',
            'urgency_flag' => 'sedang',
            'public_reply' => 'Sudah kami konfirmasi ke pihak terkait.',
            'public_reply_by' => $admin->id,
            'public_reply_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Sudah kami konfirmasi ke pihak terkait.');
        $response->assertSee('balasan terkunci', false);
        $response->assertDontSee('Hapus Balasan');
    }

    public function test_superuser_can_still_delete_reply_on_a_locked_report(): void
    {
        $report = $this->report([
            'status' => 'ditolak',
            'urgency_flag' => 'sedang',
            'public_reply' => 'Sudah kami konfirmasi ke pihak terkait.',
        ]);

        $response = $this->actingAs($this->superuser())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Hapus Balasan');
    }

    public function test_unassigned_pejabat_cannot_save_a_reply(): void
    {
        $this->seed(RolesTableSeeder::class);
        $pejabat = User::factory()->create();
        $pejabat->assignRole('Pelaksana');
        $report = $this->report();

        $response = $this->actingAs($pejabat)->put(route('admin.reports.reply.update', $report), [
            'reply' => 'Mencoba membalas tanpa ditugaskan.',
        ]);

        $response->assertForbidden();
        $this->assertNull($report->fresh()->public_reply);
    }

    public function test_shows_tidak_valid_warning_badge_when_ai_recommends_it(): void
    {
        $report = $this->report();
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 5,
            'ai_suggested_flag' => 'tidak_valid',
            'ai_raw_response' => [],
            'ai_reasoning' => 'Isi laporan tidak masuk akal dan tidak ada kronologi.',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('AI merekomendasikan flag');
        $response->assertSee('Tidak Valid');
    }

    public function test_does_not_show_tidak_valid_warning_for_a_normal_flag(): void
    {
        $report = $this->report();
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 50,
            'ai_suggested_flag' => 'sedang',
            'ai_raw_response' => [],
            'ai_reasoning' => 'Keluhan layanan standar.',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertDontSee('AI merekomendasikan flag');
    }

    public function test_approve_form_reactively_shows_reply_field_when_tidak_valid_is_selected(): void
    {
        $report = $this->report();
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 5,
            'ai_suggested_flag' => 'tidak_valid',
            'ai_raw_response' => [],
            'ai_reasoning' => 'Isi laporan tidak masuk akal.',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('id="approve_reply"', false);
        $response->assertSee("finalFlag: 'tidak_valid'", false);
    }

    public function test_prefills_balasan_when_tidak_valid_and_no_reply_yet(): void
    {
        // Laporan ini sudah "Ditolak" (terkunci) — cuma Superuser yang masih bisa membuka
        // form-nya untuk menulis balasan pertama kali, jadi draf-nya dites dari sudut pandang itu.
        $report = $this->report(['status' => 'ditolak', 'urgency_flag' => 'tidak_valid']);
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 5,
            'ai_suggested_flag' => 'tidak_valid',
            'ai_raw_response' => [],
            'ai_reasoning' => 'Isi laporan tidak masuk akal.',
            'approved_flag' => 'tidak_valid',
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($this->superuser())->get(route('admin.reports.show', $report));

        $response->assertOk();
        // Balasan (public-facing) pre-filled with a polite template, NOT the raw reasoning.
        $response->assertSee('Draf balasan otomatis');
        $response->assertSee('belum dapat kami tindak lanjuti karena informasi yang diberikan belum lengkap');
    }

    public function test_non_superuser_cannot_see_reply_draft_on_an_already_locked_report(): void
    {
        // Sama seperti di atas, tapi dari sudut pandang Admin biasa — laporan sudah final,
        // jadi tidak ada apa pun untuk ditulis lagi (bukan cuma draf yang disembunyikan, tapi
        // seluruh field balasan), sampai Superuser yang membuka kembali.
        $report = $this->report(['status' => 'ditolak', 'urgency_flag' => 'tidak_valid']);
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 5,
            'ai_suggested_flag' => 'tidak_valid',
            'ai_raw_response' => [],
            'ai_reasoning' => 'Isi laporan tidak masuk akal.',
            'approved_flag' => 'tidak_valid',
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertDontSee('Draf balasan otomatis');
        $response->assertDontSee('id="status_reply"', false);
    }

    public function test_does_not_prefill_balasan_when_a_reply_already_exists(): void
    {
        $admin = $this->admin();
        $report = $this->report([
            'status' => 'ditolak',
            'urgency_flag' => 'tidak_valid',
            'public_reply' => 'Balasan yang sudah ditulis sebelumnya.',
            'public_reply_by' => $admin->id,
            'public_reply_at' => now(),
        ]);
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 5,
            'ai_suggested_flag' => 'tidak_valid',
            'ai_raw_response' => [],
            'ai_reasoning' => 'Isi laporan tidak masuk akal.',
            'approved_flag' => 'tidak_valid',
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Balasan yang sudah ditulis sebelumnya.');
        $response->assertDontSee('Draf balasan otomatis');
    }

    /**
     * Regression: AI's ORIGINAL suggestion must stop influencing the reply draft the
     * moment a decision has actually been recorded (approved_flag set) — even if that
     * decision overrode the suggestion. Report stayed "Rendah"/"Terverifikasi Admin"
     * here despite AI having suggested "Tidak Valid"; the rejection draft must not
     * resurface every time the page loads.
     */
    public function test_does_not_prefill_balasan_when_ai_suggested_tidak_valid_but_admin_approved_something_else(): void
    {
        $report = $this->report(['status' => 'terverifikasi_admin', 'urgency_flag' => 'rendah']);
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 40,
            'ai_suggested_flag' => 'tidak_valid',
            'ai_raw_response' => [],
            'ai_reasoning' => 'Kemungkinan tidak valid, tapi ambigu.',
            'approved_flag' => 'rendah',
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        // "Draf balasan otomatis" only renders when $rejectReplyDraft is set — the reliable
        // signal that the textarea was pre-filled (the template text itself legitimately
        // still appears elsewhere on the page, embedded as Alpine's `rejectTemplate` JS
        // value, so it can auto-fill later if urgency is ever switched to Tidak Valid).
        $response->assertDontSee('Draf balasan otomatis');
    }

    public function test_approving_tidak_valid_rejects_the_report_and_saves_the_reply(): void
    {
        $admin = $this->admin();
        $report = $this->report();
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 5,
            'ai_suggested_flag' => 'tidak_valid',
            'ai_raw_response' => [],
            'ai_reasoning' => 'Isi laporan tidak masuk akal.',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.reports.approve-ai', $report), [
            'final_flag' => 'tidak_valid',
            'reply' => 'Mohon maaf, laporan ini tidak dapat kami proses.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Rekomendasi AI diverifikasi. Laporan berstatus Ditolak.');
        $report->refresh();
        $this->assertSame('ditolak', $report->status);
        $this->assertSame('tidak_valid', $report->urgency_flag);
        $this->assertSame('Mohon maaf, laporan ini tidak dapat kami proses.', $report->public_reply);
    }

    public function test_approving_a_normal_flag_still_verifies_as_before(): void
    {
        $report = $this->report();
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 40,
            'ai_suggested_flag' => 'sedang',
            'ai_raw_response' => [],
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.reports.approve-ai', $report), [
            'final_flag' => 'sedang',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Rekomendasi AI diverifikasi. Laporan berstatus Terverifikasi Admin.');
        $this->assertSame('terverifikasi_admin', $report->fresh()->status);
    }

    public function test_prefills_balasan_when_urgency_is_manually_tidak_valid_with_no_ai_involved(): void
    {
        // No AiAssessment at all — Admin picked "Tidak Valid" manually (AI off/unused) — the
        // polite draft must still appear, not just when AI itself suggested it. Laporan sudah
        // terkunci ("Ditolak"), jadi dilihat dari sudut pandang Superuser (satu-satunya yang
        // masih bisa membuka form untuk balasan pertama kali).
        $report = $this->report(['status' => 'ditolak', 'urgency_flag' => 'tidak_valid']);

        $response = $this->actingAs($this->superuser())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Draf balasan otomatis karena urgensi Tidak Valid');
        $response->assertSee('belum dapat kami tindak lanjuti karena informasi yang diberikan belum lengkap');
    }

    public function test_status_form_reactively_fills_reply_when_urgency_dropdown_is_set_to_tidak_valid(): void
    {
        // Urgency not yet decided (editable select, no AI) — the Alpine watcher that fills the
        // draft the moment "Tidak Valid" is picked must be wired up in the markup.
        $report = $this->report(['status' => 'baru_masuk']);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee("\$watch('urgencyFlag'", false);
        $response->assertSee('rejectTemplate:', false);
    }

    public function test_approve_form_reactively_fills_reply_when_admin_manually_switches_to_tidak_valid(): void
    {
        // AI suggested something else ("sedang") — Admin can still manually switch the dropdown
        // to Tidak Valid, which must trigger the same draft via the Alpine watcher.
        $report = $this->report();
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 40,
            'ai_suggested_flag' => 'sedang',
            'ai_raw_response' => [],
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee("\$watch('finalFlag'", false);
        $response->assertSee('rejectTemplate:', false);
    }

    /**
     * Regression test for a real bug found in production: AI suggested Tidak Valid (auto-filling
     * a rejection draft into the reply field), Admin overrode the flag to a normal urgency and
     * approved — but the leftover rejection draft still got saved as the public reply on a report
     * that was never actually rejected. The watcher must clear the draft back out the moment the
     * flag switches away from Tidak Valid, as long as it's still untouched.
     */
    public function test_approve_form_clears_the_reject_draft_when_flag_is_switched_away_from_tidak_valid(): void
    {
        $report = $this->report();
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 15,
            'ai_suggested_flag' => 'tidak_valid',
            'ai_raw_response' => [],
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee("this.reply = '';", false);
    }

    public function test_status_form_reverts_the_reject_draft_to_original_reply_when_urgency_is_switched_away_from_tidak_valid(): void
    {
        $report = $this->report(['status' => 'baru_masuk']);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee('this.reply = this.originalReply;', false);
    }

    public function test_approving_a_normal_flag_after_switching_away_from_tidak_valid_does_not_save_a_rejection_reply(): void
    {
        // End-to-end version of the same regression: simulates the corrected client behavior by
        // submitting final_flag=rendah (Admin's override) with no reply — exactly what happens
        // once the watcher above clears the stale draft before submit.
        $report = $this->report();
        ReportAiAssessment::create([
            'report_id' => $report->id,
            'ai_score' => 15,
            'ai_suggested_flag' => 'tidak_valid',
            'ai_raw_response' => [],
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.reports.approve-ai', $report), [
            'final_flag' => 'rendah',
        ]);

        $response->assertRedirect();
        $this->assertSame('terverifikasi_admin', $report->fresh()->status);
        $this->assertSame('rendah', $report->fresh()->aiAssessment->approved_flag);
        $this->assertNull($report->fresh()->public_reply);
    }

    public function test_status_dropdown_is_replaced_by_a_locked_ditolak_display_when_urgency_is_already_tidak_valid(): void
    {
        // Urgency already fixed to Tidak Valid while status hasn't caught up yet (legacy data,
        // or urgency set through some other path) — the free "Status" select, which would
        // otherwise offer statuses like "Dalam Penanganan", must not be rendered at all.
        $report = $this->report(['status' => 'baru_masuk', 'urgency_flag' => 'tidak_valid']);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        // Both templates ship in the markup (Alpine's <template x-if> toggles at runtime, not
        // server-side), so assert the reactive lock is wired to the right condition instead of
        // asserting raw absence of the select.
        $response->assertSee("x-if=\"urgencyFlag === 'tidak_valid'\"", false);
        $response->assertSee('Otomatis — urgensi Tidak Valid');
    }

    public function test_status_dropdown_markup_reactively_locks_to_ditolak_when_urgency_dropdown_is_switched(): void
    {
        // Urgency not yet decided — the reactive template x-if that swaps the free status select
        // for the locked "Ditolak" display the moment urgencyFlag becomes tidak_valid must be wired up.
        $report = $this->report(['status' => 'baru_masuk']);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.show', $report));

        $response->assertOk();
        $response->assertSee("x-if=\"urgencyFlag === 'tidak_valid'\"", false);
        $response->assertSee('Otomatis — urgensi Tidak Valid');
    }

    public function test_server_rejects_tidak_valid_urgency_paired_with_a_non_ditolak_status(): void
    {
        // Defense in depth — even if the UI is bypassed (forged request), the combination
        // urgency_flag=tidak_valid with any status other than ditolak must be rejected.
        $report = $this->report(['status' => 'baru_masuk']);

        $response = $this->actingAs($this->admin())->patch(route('admin.reports.status', $report), [
            'status' => 'terverifikasi_admin',
            'urgency_flag' => 'tidak_valid',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame('baru_masuk', $report->fresh()->status);
    }

    public function test_server_accepts_tidak_valid_urgency_paired_with_ditolak_status(): void
    {
        $report = $this->report(['status' => 'baru_masuk']);

        $response = $this->actingAs($this->admin())->patch(route('admin.reports.status', $report), [
            'status' => 'ditolak',
            'urgency_flag' => 'tidak_valid',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('ditolak', $report->fresh()->status);
        $this->assertSame('tidak_valid', $report->fresh()->urgency_flag);
    }
}
