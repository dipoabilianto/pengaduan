import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('admin-dashboard-data');
    if (!dataEl) return;

    const data = JSON.parse(dataEl.textContent);
    const gridColor = 'rgba(100, 116, 139, 0.12)';
    const textColor = '#475569';

    Chart.defaults.color = textColor;
    Chart.defaults.borderColor = gridColor;

    // Legenda ringkas & konsisten untuk kedua donut chart (Status & Urgensi) — box lebih
    // kecil, padding lebih rapat, biar tidak "menumpuk" di kolom sempit begitu banyak item.
    const donutLegend = { position: 'bottom', labels: { boxWidth: 10, padding: 8, font: { size: 11 } } };

    // Colorblind-safe categorical palette (validated for CVD separation), sama seperti
    // dashboard publik (resources/js/dashboard.js) — tapi dipetakan lewat KUNCI status
    // (bukan posisi array), supaya satu status selalu dapat warna yang sama biarpun
    // status lain sedang tersaring (0 laporan) dari legenda/grafik.
    const statusColors = {
        baru_masuk: '#3987e5',
        terverifikasi_admin: '#d95926',
        dalam_penanganan: '#c98500',
        selesai: '#d55181',
        ditolak: '#008300',
    };

    new Chart(document.getElementById('chart-monthly'), {
        type: 'line',
        data: {
            labels: data.monthly.labels,
            datasets: [{
                label: 'Jumlah Laporan',
                data: data.monthly.counts,
                borderColor: '#3987e5',
                backgroundColor: 'rgba(57, 135, 229, 0.12)',
                tension: 0.3,
                fill: true,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });

    if (data.status.labels.length) {
        new Chart(document.getElementById('chart-status'), {
            type: 'doughnut',
            data: {
                labels: data.status.labels,
                datasets: [{
                    data: data.status.counts,
                    backgroundColor: data.status.keys.map((key) => statusColors[key]),
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: donutLegend },
            },
        });
    }

    new Chart(document.getElementById('chart-categories'), {
        type: 'bar',
        data: {
            labels: data.categories.labels,
            datasets: [{
                label: 'Jumlah Laporan',
                data: data.categories.counts,
                backgroundColor: '#3987e5',
                borderRadius: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 } },
                y: {
                    ticks: {
                        callback(value) {
                            const label = this.getLabelForValue(value);

                            return label.length > 18 ? label.slice(0, 18) + '…' : label;
                        },
                    },
                },
            },
        },
    });

    if (data.urgency.labels.length) {
        // Urgensi = severity, jadi warna dipetakan per flag (bukan posisi array) memakai
        // status palette (good→critical) — konsisten dengan urgency-badge.blade.php.
        const urgencyColors = {
            red_code: '#d03b3b',
            tinggi: '#ec835a',
            sedang: '#fab219',
            rendah: '#0ca30c',
            tidak_valid: '#94a3b8',
        };

        new Chart(document.getElementById('chart-urgency'), {
            type: 'doughnut',
            data: {
                labels: data.urgency.labels,
                datasets: [{
                    data: data.urgency.counts,
                    backgroundColor: data.urgency.flags.map((flag) => urgencyColors[flag]),
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: donutLegend },
            },
        });
    }
});
