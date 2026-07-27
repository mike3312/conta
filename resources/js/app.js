import './bootstrap';
import * as bootstrap from 'bootstrap';
import Chart from 'chart.js/auto';
import Alpine from 'alpinejs';

window.bootstrap = bootstrap;
window.Alpine = Alpine;
Alpine.start();

let dashboardChart = null;

const renderDashboardChart = () => {
    const canvas = document.getElementById('financialOverviewChart');
    const dataElement = document.getElementById('financialOverviewData');

    if (!canvas || !dataElement) return;
    const chartData = JSON.parse(dataElement.textContent);
    if (dashboardChart) dashboardChart.destroy();

    dashboardChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [
                { label: 'Ingresos', data: chartData.income, backgroundColor: '#2563eb', borderRadius: 6, maxBarThickness: 32 },
                { label: 'Gastos', data: chartData.expenses, backgroundColor: '#dce6fb', borderRadius: 6, maxBarThickness: 32 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } } },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: { beginAtZero: true, border: { display: false }, ticks: { callback: value => `GTQ ${Number(value).toLocaleString('es-GT', { minimumFractionDigits: 2 })}` } },
            },
        },
    });
};

document.addEventListener('DOMContentLoaded', renderDashboardChart);
document.addEventListener('livewire:navigated', renderDashboardChart);

document.addEventListener('submit', event => {
    const form = event.target.closest('[data-loading-form]');
    if (!form) return;

    const button = form.querySelector('[data-loading-button]');
    if (!button) return;

    button.disabled = true;
    button.querySelector('[data-loading-spinner]')?.classList.remove('d-none');
    const label = button.querySelector('[data-loading-label]');
    if (label && button.dataset.loadingText) label.textContent = button.dataset.loadingText;
});
