<?php

use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\BrandingSettingsController;
use App\Http\Controllers\Admin\ChannelInboxController;
use App\Http\Controllers\Admin\ChatFactsSettingsController;
use App\Http\Controllers\Admin\ChatTicketController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InstagramSettingsController;
use App\Http\Controllers\Admin\PushSettingsController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WhatsAppSettingsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\ChatBroadcastAuthController;
use App\Http\Controllers\Public\ChatController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ReportController;
use App\Http\Controllers\Public\StatusCheckController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('public.home');
// Statistik & daftar pengaduan digabung ke halaman utama (Bab 4.4/8.2 PDR).
Route::redirect('/statistik', '/#statistik', 301)->name('public.dashboard');
Route::redirect('/daftar-pengaduan', '/#daftar-pengaduan', 301)->name('public.reports.index');

// GET /pengaduan (tanpa /buat) cuma dipakai POST untuk submit form — kalau diakses
// langsung lewat browser (URL diketik manual/bookmark lama) sebelumnya 405. Arahkan
// ke halaman formnya, sama seperti redirect /statistik & /daftar-pengaduan di atas.
Route::redirect('/pengaduan', '/pengaduan/buat', 301)->name('report.index');

Route::prefix('pengaduan')->name('report.')->group(function () {
    Route::get('/buat', [ReportController::class, 'create'])->name('create');
    Route::post('/', [ReportController::class, 'store'])->middleware('throttle:6,1')->name('store');
    Route::get('/{ticketNo}/sukses', [ReportController::class, 'success'])->name('success');
});

Route::prefix('cek-status')->name('public.status.')->group(function () {
    Route::get('/', [StatusCheckController::class, 'form'])->name('check');
    Route::get('/hasil', [StatusCheckController::class, 'result'])->middleware('throttle:10,1')->name('result');
});

Route::prefix('chat')->name('chat.')->group(function () {
    // Only called when actually starting/resuming via a typed phone number (new
    // ticket, or no active server session) — see chat-widget.js's startChat()/
    // revealHistory(). Gated by CAPTCHA + a per-phone-number rate limit
    // ('chat-start-phone') on top of this IP-based one, since a phone number alone
    // is otherwise sufficient to reach a citizen's chat.
    Route::post('/mulai', [ChatController::class, 'start'])->middleware(['throttle:30,1', 'throttle:chat-start-phone'])->name('start');
    Route::post('/{chatTicket}/pesan', [ChatController::class, 'sendMessage'])->middleware(['throttle:20,1', 'throttle:chat-message'])->name('send');
    Route::post('/{chatTicket}/nilai', [ChatController::class, 'rate'])->middleware('throttle:10,1')->name('rate');
    Route::post('/broadcasting/auth', [ChatBroadcastAuthController::class, 'authenticate'])->middleware('throttle:30,1')->name('broadcasting-auth');
    // Called automatically on every public page load (chat-widget.js: init()) — resumes
    // an already-open conversation purely from the HttpOnly session cookie, no phone
    // number involved, so the widget no longer needs to cache/replay one from localStorage.
    Route::get('/sesi', [ChatController::class, 'session'])->middleware('throttle:30,1')->name('session');
    Route::post('/keluar', [ChatController::class, 'endSession'])->middleware('throttle:30,1')->name('end-session');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

// Tidak lagi digerbang oleh nama role (role/izin sekarang dinamis) — tiap aksi di
// dalamnya sudah dijaga sendiri oleh ReportPolicy lewat $this->authorize(...).
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/laporan', [AdminReportController::class, 'index'])->name('reports.index');
    // Wajib sebelum /laporan/{report} — kalau tidak, "export" akan ditangkap sebagai {report}.
    Route::get('/laporan/export', [AdminReportController::class, 'export'])->name('reports.export');
    Route::get('/laporan/{report}', [AdminReportController::class, 'show'])->name('reports.show');
    Route::get('/laporan/{report}/lampiran/{attachment}', [AdminReportController::class, 'showAttachment'])->scopeBindings()->name('reports.attachments.show');
    Route::patch('/laporan/{report}/status', [AdminReportController::class, 'updateStatus'])->name('reports.status');
    Route::post('/laporan/{report}/setujui-ai', [AdminReportController::class, 'approveAiAssessment'])->name('reports.approve-ai');
    Route::post('/laporan/{report}/nilai-ulang-ai', [AdminReportController::class, 'rescoreWithAi'])->name('reports.rescore-ai');
    Route::get('/laporan/{report}/status-ai', [AdminReportController::class, 'aiStatus'])->name('reports.ai-status');
    Route::post('/laporan/{report}/teruskan', [AdminReportController::class, 'assign'])->name('reports.assign');
    Route::post('/laporan/{report}/buka-pelapor', [AdminReportController::class, 'revealReporter'])->name('reports.reveal-reporter');
    Route::put('/laporan/{report}/balasan', [AdminReportController::class, 'updateReply'])->name('reports.reply.update');
    Route::delete('/laporan/{report}/balasan', [AdminReportController::class, 'deleteReply'])->name('reports.reply.destroy');
    Route::post('/laporan/{report}/balasan/buat-ai', [AdminReportController::class, 'generateReply'])->name('reports.reply.generate');
    Route::get('/ai-health', [AiSettingsController::class, 'health'])->name('ai-health');
});

Route::prefix('admin/inbox')->name('admin.inbox.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [ChannelInboxController::class, 'index'])->name('index');
    Route::post('/{channelInbox}/convert', [ChannelInboxController::class, 'convert'])->name('convert');
    Route::post('/{channelInbox}/ignore', [ChannelInboxController::class, 'ignore'])->name('ignore');
    Route::post('/{channelInbox}/reply', [ChannelInboxController::class, 'reply'])->name('reply');
});

Route::prefix('admin/pengaturan')->name('admin.settings.')->middleware(['auth', 'verified', 'role:superuser'])->group(function () {
    Route::get('/ai', [AiSettingsController::class, 'edit'])->name('ai');
    Route::put('/ai', [AiSettingsController::class, 'update'])->name('ai.update');
    Route::get('/branding', [BrandingSettingsController::class, 'edit'])->name('branding');
    Route::put('/branding', [BrandingSettingsController::class, 'update'])->name('branding.update');
    Route::get('/whatsapp', [WhatsAppSettingsController::class, 'edit'])->name('whatsapp');
    Route::put('/whatsapp', [WhatsAppSettingsController::class, 'update'])->name('whatsapp.update');
    Route::get('/instagram', [InstagramSettingsController::class, 'edit'])->name('instagram');
    Route::put('/instagram', [InstagramSettingsController::class, 'update'])->name('instagram.update');
    Route::get('/push', [PushSettingsController::class, 'edit'])->name('push');
    Route::put('/push', [PushSettingsController::class, 'update'])->name('push.update');
    Route::get('/asisten-chat', [ChatFactsSettingsController::class, 'edit'])->name('chat-facts');
    Route::put('/asisten-chat', [ChatFactsSettingsController::class, 'update'])->name('chat-facts.update');
});

Route::prefix('admin/pengguna')->name('admin.users.')->middleware(['auth', 'verified', 'role:superuser'])->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/tambah', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}/ubah', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
});

Route::prefix('admin/peran')->name('admin.roles.')->middleware(['auth', 'verified', 'role:superuser'])->group(function () {
    Route::get('/', [RoleController::class, 'index'])->name('index');
    Route::get('/tambah', [RoleController::class, 'create'])->name('create');
    Route::post('/', [RoleController::class, 'store'])->name('store');
    Route::get('/{role}/ubah', [RoleController::class, 'edit'])->name('edit');
    Route::put('/{role}', [RoleController::class, 'update'])->name('update');
    Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
});

// Sama seperti admin/laporan — tidak digerbang oleh nama role, tiap aksi menjaga
// dirinya sendiri lewat ChatTicketPolicy.
Route::prefix('admin/chat')->name('admin.chat.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [ChatTicketController::class, 'index'])->name('index');
    Route::get('/{chatTicket}', [ChatTicketController::class, 'show'])->name('show');
    Route::post('/{chatTicket}/ambil', [ChatTicketController::class, 'claim'])->name('claim');
    Route::post('/{chatTicket}/pesan', [ChatTicketController::class, 'sendMessage'])->name('send');
    Route::post('/{chatTicket}/haluskan', [ChatTicketController::class, 'polishDraft'])->middleware('throttle:20,1')->name('polish');
    Route::patch('/{chatTicket}/status', [ChatTicketController::class, 'updateStatus'])->name('status');
    Route::post('/{chatTicket}/toggle-ai', [ChatTicketController::class, 'toggleAi'])->name('toggle-ai');
    Route::post('/{chatTicket}/buka-nomor', [ChatTicketController::class, 'revealPhone'])->name('reveal-phone');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
