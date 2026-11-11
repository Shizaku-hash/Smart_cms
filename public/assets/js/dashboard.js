document.addEventListener('DOMContentLoaded', () => {
  const data = window.DASHBOARD_CHARTS || {};
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const gridColor = isDark ? 'rgba(148,163,184,0.12)' : 'rgba(15,23,42,0.06)';
  const tickColor = isDark ? '#94a3b8' : '#64748b';

  Chart.defaults.color = tickColor;
  Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';

  // 1. Income vs Expenses
  const monthly = data.monthly_series || [];
  const ctx1 = document.getElementById('chartIncomeExpense');
  if (ctx1) {
    new Chart(ctx1, {
      type: 'line',
      data: {
        labels: monthly.map(m => m.month),
        datasets: [
          {
            label: 'Income',
            data: monthly.map(m => m.income),
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.14)',
            tension: 0.35,
            fill: true,
            borderWidth: 2.5
          },
          {
            label: 'Expenses',
            data: monthly.map(m => m.expense),
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239, 68, 68, 0.10)',
            tension: 0.35,
            fill: true,
            borderWidth: 2.5
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: { y: { grid: { color: gridColor } }, x: { grid: { display: false } } }
      }
    });
  }

  // 2. Monthly Profit Statistics
  const ctx2 = document.getElementById('chartMonthlyProfit');
  if (ctx2) {
    new Chart(ctx2, {
      type: 'bar',
      data: {
        labels: monthly.map(m => m.month),
        datasets: [{
          label: 'Net Profit',
          data: monthly.map(m => m.profit),
          backgroundColor: monthly.map(m => m.profit >= 0 ? '#2563eb' : '#ef4444'),
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { grid: { color: gridColor } }, x: { grid: { display: false } } }
      }
    });
  }

  // 3. Expense Categories
  const expCats = data.expense_categories || [];
  const ctx3 = document.getElementById('chartExpenseCategories');
  if (ctx3) {
    new Chart(ctx3, {
      type: 'doughnut',
      data: {
        labels: expCats.map(c => c.category),
        datasets: [{
          data: expCats.map(c => Number(c.amount)),
          backgroundColor: expCats.map(c => c.color || '#6366f1'),
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '64%',
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
      }
    });
  }

  // 4. Salary Distribution
  const salDist = data.salary_distribution || [];
  const ctx4 = document.getElementById('chartSalaryDistribution');
  if (ctx4) {
    new Chart(ctx4, {
      type: 'bar',
      data: {
        labels: salDist.map(s => s.department),
        datasets: [{
          label: 'Department Payroll',
          data: salDist.map(s => Number(s.total)),
          backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#06b6d4'],
          borderRadius: 6
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { grid: { color: gridColor } }, y: { grid: { display: false } } }
      }
    });
  }

  // 5. Employee Performance Statistics
  const perfStats = data.performance_stats || [];
  const ctx5 = document.getElementById('chartPerformance');
  if (ctx5) {
    new Chart(ctx5, {
      type: 'bar',
      data: {
        labels: perfStats.map(p => p.full_name.split(' ')[0]),
        datasets: [{
          label: 'Performance Score',
          data: perfStats.map(p => Number(p.computed_score)),
          backgroundColor: '#10b981',
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { min: 0, max: 100, grid: { color: gridColor } }, x: { grid: { display: false } } }
      }
    });
  }

  // Dashboard Widget Customizer (Order + Visibility)
  const listEl = document.getElementById('widgetCustomizerList');
  if (listEl) {
    listEl.addEventListener('click', (e) => {
      const target = e.target.closest('button');
      if (!target) return;
      const item = target.closest('.list-group-item');
      if (!item) return;
      if (target.classList.contains('move-up-btn') && item.previousElementSibling) {
        listEl.insertBefore(item, item.previousElementSibling);
      } else if (target.classList.contains('move-down-btn') && item.nextElementSibling) {
        listEl.insertBefore(item.nextElementSibling, item);
      }
    });
  }

  const saveBtn = document.getElementById('saveDashboardWidgetsBtn');
  if (saveBtn && listEl) {
    saveBtn.addEventListener('click', async () => {
      const items = [...listEl.querySelectorAll('.list-group-item')];
      const widgets = items.map((el, idx) => ({
        id: el.dataset.wid,
        visible: el.querySelector('.widget-vis-toggle').checked,
        order: idx + 1
      }));
      await window.CMS.postJson('/dashboard/widgets', { widgets });
      window.location.reload();
    });
  }
});
