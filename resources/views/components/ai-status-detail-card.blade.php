@props(['status'])

<div
    x-data="{
        state: @js($status['state']),
        reason: @js($status['reason']),
        lastSuccessAt: @js($status['last_success_at']),
        lastSuccessFeature: @js($status['last_success_feature']),
        lastFailureAt: @js($status['last_failure_at']),
        lastFailureReason: @js($status['last_failure_reason']),
        successRate: @js($status['recent_success_rate']),
        recentCalls: @js($status['recent_calls']),
        label: { active: 'Aktif', off: 'Tidak Aktif', error: 'Bermasalah' },
        poll() {
            axios.get('{{ route('admin.ai-health') }}').then((res) => {
                this.state = res.data.state;
                this.reason = res.data.reason;
                this.lastSuccessAt = res.data.last_success_at;
                this.lastSuccessFeature = res.data.last_success_feature;
                this.lastFailureAt = res.data.last_failure_at;
                this.lastFailureReason = res.data.last_failure_reason;
                this.successRate = res.data.recent_success_rate;
                this.recentCalls = res.data.recent_calls;
            }).catch(() => {});
        },
        timeAgo(iso) {
            if (! iso) return null;
            const seconds = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
            if (seconds < 60) return 'baru saja';
            if (seconds < 3600) return Math.floor(seconds / 60) + ' menit lalu';
            if (seconds < 86400) return Math.floor(seconds / 3600) + ' jam lalu';
            return Math.floor(seconds / 86400) + ' hari lalu';
        },
        init() {
            setInterval(() => this.poll(), 15000);
        },
    }"
    class="rounded-lg bg-white p-5 shadow"
>
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700">Status AI Real-time</h3>
        <span
            :class="{
                'bg-status-good/10 text-status-good': state === 'active',
                'bg-gray-100 text-gray-600': state === 'off',
                'bg-status-critical/10 text-status-critical': state === 'error',
            }"
            class="rounded-full px-2.5 py-1 text-xs font-medium"
            x-text="'AI Otomatis ' + label[state]"
        ></span>
    </div>
    <p class="mt-1 text-xs text-gray-500" x-text="reason"></p>

    <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
        <div>
            <dt class="text-gray-400">Terakhir berhasil</dt>
            <dd class="mt-0.5 font-medium text-gray-700">
                <template x-if="lastSuccessAt">
                    <span x-text="timeAgo(lastSuccessAt) + ' (' + lastSuccessFeature + ')'"></span>
                </template>
                <template x-if="! lastSuccessAt">
                    <span class="text-gray-400">Belum pernah</span>
                </template>
            </dd>
        </div>
        <div>
            <dt class="text-gray-400">Terakhir gagal</dt>
            <dd class="mt-0.5 font-medium text-gray-700">
                <template x-if="lastFailureAt">
                    <span x-text="timeAgo(lastFailureAt)" :title="lastFailureReason"></span>
                </template>
                <template x-if="! lastFailureAt">
                    <span class="text-gray-400">Tidak ada</span>
                </template>
            </dd>
        </div>
        <div class="col-span-2">
            <dt class="text-gray-400">Tingkat keberhasilan (20 panggilan terakhir)</dt>
            <dd class="mt-1">
                <template x-if="successRate !== null">
                    <div class="flex items-center gap-2">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-status-good" :style="`width: ${successRate}%`"></div>
                        </div>
                        <span class="font-medium text-gray-700" x-text="successRate + '%'"></span>
                    </div>
                </template>
                <template x-if="successRate === null">
                    <span class="text-gray-400">Belum ada data panggilan</span>
                </template>
            </dd>
        </div>
    </dl>

    <div class="mt-4 border-t border-gray-100 pt-3">
        <p class="text-xs font-medium text-gray-500">Aktivitas terbaru</p>
        <ul class="mt-2 max-h-40 space-y-1 overflow-y-auto text-xs">
            <template x-for="call in recentCalls" :key="call.created_at + call.feature">
                <li class="flex items-center justify-between gap-2 py-0.5">
                    <span class="flex items-center gap-1.5 truncate">
                        <span :class="call.outcome === 'success' ? 'text-status-good' : 'text-status-critical'" x-text="call.outcome === 'success' ? '✓' : '✗'"></span>
                        <span class="truncate text-gray-600" x-text="call.feature_label" :title="call.reason ?? ''"></span>
                    </span>
                    <span class="shrink-0 text-gray-400" x-text="timeAgo(call.created_at)"></span>
                </li>
            </template>
            <template x-if="recentCalls.length === 0">
                <li class="py-1 text-gray-400">Belum ada aktivitas tercatat.</li>
            </template>
        </ul>
    </div>
</div>
