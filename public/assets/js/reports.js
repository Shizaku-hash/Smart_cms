document.addEventListener('DOMContentLoaded', () => {
  const init = window.REPORT_BUILDER_INIT || {};
  const method = init.method || 'automatic';

  let manualCols = [...(init.manualColumns || ['Item', 'Amount'])];
  let manualRows = (init.manualRows || [['Sample', '100']]).map(r => [...r]);

  function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  }

  function renderPreview(report) {
    document.getElementById('prevTitle').textContent = report.title || 'Report';
    document.getElementById('prevSubtitle').textContent = report.subtitle || '';
    const tbl = document.getElementById('prevTable');
    let html = '<thead><tr>' + (report.columns || []).map(c => `<th>${escapeHtml(c)}</th>`).join('') + '</tr></thead><tbody>';
    (report.rows || []).forEach(r => {
      html += '<tr>' + (report.columns || []).map((_, idx) => `<td>${escapeHtml(r[idx] ?? '')}</td>`).join('') + '</tr>';
    });
    html += '</tbody>';
    if (report.totals_row && report.totals_row.length) {
      html += '<tfoot><tr class="table-secondary fw-bold">' + report.totals_row.map(c => `<td>${escapeHtml(c)}</td>`).join('') + '</tr></tfoot>';
    }
    tbl.innerHTML = html;

    const notesEl = document.getElementById('prevNotes');
    if (report.notes) {
      notesEl.classList.remove('d-none');
      notesEl.innerHTML = '<strong>Notes:</strong> ' + escapeHtml(report.notes);
    } else {
      notesEl.classList.add('d-none');
    }
  }

  // Method A: Automatic report type switch reloads available column checkboxes
  const typeSelect = document.getElementById('autoReportType');
  if (typeSelect) {
    typeSelect.addEventListener('change', () => {
      const colMap = (init.allColumnsByType || {})[typeSelect.value] || {};
      const box = document.getElementById('autoColumnsBox');
      box.innerHTML = Object.entries(colMap).map(([k, lbl]) => `
        <label class="form-check form-check-inline small m-0">
          <input class="form-check-input auto-col-chk" type="checkbox" name="columns[]" value="${escapeHtml(k)}" checked>
          <span class="form-check-label">${escapeHtml(lbl)}</span>
        </label>
      `).join('');
      const titleInput = document.getElementById('autoTitle');
      if (titleInput) titleInput.value = typeSelect.options[typeSelect.selectedIndex].text;
    });
  }

  function collectAutomaticPayload() {
    const form = document.getElementById('autoReportForm');
    const fd = new FormData(form);
    const payload = { creation_method: 'automatic', columns: [] };
    for (const [k, v] of fd.entries()) {
      if (k === 'columns[]') payload.columns.push(v);
      else payload[k] = v;
    }
    return payload;
  }

  function collectManualPayload() {
    return {
      creation_method: 'manual',
      report_type: 'custom',
      title: document.getElementById('manTitle')?.value || 'Custom Report',
      subtitle: document.getElementById('manSubtitle')?.value || '',
      include_logo: document.getElementById('manIncludeLogo')?.checked ? 1 : 0,
      show_totals_row: document.getElementById('manTotalsRow')?.checked ? 1 : 0,
      notes: document.getElementById('manNotes')?.value || '',
      columns: manualCols,
      rows: manualRows
    };
  }

  // Method B: Editable Grid Renderer
  function renderManualGrid() {
    const grid = document.getElementById('manualGridTable');
    if (!grid) return;
    let html = '<thead><tr>';
    manualCols.forEach((c, cIdx) => {
      html += `<th><input type="text" class="form-control form-control-sm fw-bold" value="${escapeHtml(c)}" data-col-header="${cIdx}"></th>`;
    });
    html += '<th style="width:36px;"></th></tr></thead><tbody>';
    manualRows.forEach((row, rIdx) => {
      html += '<tr>';
      manualCols.forEach((_, cIdx) => {
        html += `<td><input type="text" class="form-control form-control-sm" value="${escapeHtml(row[cIdx] ?? '')}" data-cell-r="${rIdx}" data-cell-c="${cIdx}"></td>`;
      });
      html += `<td><button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" data-del-row="${rIdx}">&times;</button></td></tr>`;
    });
    html += '</tbody>';
    grid.innerHTML = html;

    grid.querySelectorAll('[data-col-header]').forEach(inp => {
      inp.addEventListener('input', e => { manualCols[Number(e.target.dataset.colHeader)] = e.target.value; });
    });
    grid.querySelectorAll('[data-cell-r]').forEach(inp => {
      inp.addEventListener('input', e => {
        const r = Number(e.target.dataset.cellR);
        const c = Number(e.target.dataset.cellC);
        manualRows[r][c] = e.target.value;
      });
    });
    grid.querySelectorAll('[data-del-row]').forEach(btn => {
      btn.addEventListener('click', e => {
        manualRows.splice(Number(e.target.dataset.delRow), 1);
        renderManualGrid();
      });
    });
  }

  window.addManualColumn = () => {
    manualCols.push('Column ' + (manualCols.length + 1));
    manualRows.forEach(r => r.push(''));
    renderManualGrid();
  };
  window.removeManualColumn = () => {
    if (manualCols.length <= 1) return;
    manualCols.pop();
    manualRows.forEach(r => r.pop());
    renderManualGrid();
  };
  window.addManualRow = () => {
    manualRows.push(manualCols.map(() => ''));
    renderManualGrid();
  };

  if (method === 'manual') {
    renderManualGrid();
  }

  const btnAuto = document.getElementById('btnPreviewAuto');
  if (btnAuto) {
    btnAuto.addEventListener('click', async () => {
      const res = await window.CMS.postJson('/reports/preview', collectAutomaticPayload());
      if (res.ok) renderPreview(res.report);
    });
  }

  const btnManual = document.getElementById('btnPreviewManual');
  if (btnManual) {
    btnManual.addEventListener('click', async () => {
      const res = await window.CMS.postJson('/reports/preview', collectManualPayload());
      if (res.ok) renderPreview(res.report);
    });
  }

  const btnSaveTpl = document.getElementById('btnSaveTemplate');
  if (btnSaveTpl) {
    btnSaveTpl.addEventListener('click', async () => {
      const payload = method === 'manual' ? collectManualPayload() : collectAutomaticPayload();
      const name = document.getElementById('saveTemplateName')?.value || payload.title || 'Saved Report';
      const res = await window.CMS.postJson('/reports/templates', {
        template_name: name,
        creation_method: method,
        report_type: payload.report_type || 'custom',
        is_shared: 1,
        config: payload
      });
      if (res.ok) {
        alert('Report template saved!');
      }
    });
  }

  window.submitReportExport = (format) => {
    const form = document.getElementById('hiddenExportForm');
    const csrfInput = form.querySelector('input[name="_csrf_token"]').outerHTML;
    form.innerHTML = csrfInput;

    const payload = method === 'manual' ? collectManualPayload() : collectAutomaticPayload();
    payload.export_format = format;
    payload.engine = document.getElementById('exportEngine')?.value || 'php';

    Object.entries(payload).forEach(([k, v]) => {
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = k;
      inp.value = (typeof v === 'object') ? JSON.stringify(v) : String(v ?? '');
      form.appendChild(inp);
    });
    form.submit();
  };
});
