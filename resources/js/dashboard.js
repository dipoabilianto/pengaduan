import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('dashboard-data');
    if (!dataEl) return;

    const data = JSON.parse(dataEl.textContent);
    const gridColor = 'rgba(148, 163, 184, 0.15)';
    const textColor = '#cbd5e1';

    Chart.defaults.color = textColor;
    Chart.defaults.borderColor = gridColor;

    new Chart(document.getElementById('chart-monthly'), {
        type: 'line',
        data: {
            labels: data.monthly.labels,
            datasets: [{
                label: 'Jumlah Laporan',
                data: data.monthly.counts,
                borderColor: '#38bdf8',
                backgroundColor: 'rgba(56, 189, 248, 0.15)',
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

    new Chart(document.getElementById('chart-categories'), {
        type: 'bar',
        data: {
            labels: data.categories.labels,
            datasets: [{
                label: 'Jumlah Laporan',
                data: data.categories.counts,
                backgroundColor: '#818cf8',
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
                        // Long category names can get clipped against the canvas edge on
                        // narrow screens — truncate the axis label (tooltips still show the
                        // full text) instead of letting it overflow illegibly.
                        callback(value) {
                            const label = this.getLabelForValue(value);

                            return label.length > 18 ? label.slice(0, 18) + '…' : label;
                        },
                    },
                },
            },
        },
    });

    new Chart(document.getElementById('chart-status'), {
        type: 'doughnut',
        data: {
            labels: data.status.labels,
            datasets: [{
                data: data.status.counts,
                // Colorblind-safe categorical order (validated for CVD separation) — never a
                // plain red/amber/green scheme, since a status distribution has no severity
                // ranking and shouldn't imply one. Legend below keeps text as the real
                // identity signal; color is reinforcement only.
                backgroundColor: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9'],
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
        },
    });
});
