/**
 * Exportar tablas visibles del sistema a Excel (.xlsx) con SheetJS.
 */
(function() {
    'use strict';

    function slugNombre(s) {
        return String(s || 'tabla')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-zA-Z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .slice(0, 60) || 'tabla';
    }

    function tituloPagina() {
        var h = document.querySelector('main h1, .main-area h1, h1');
        if (h && h.textContent) return h.textContent.trim();
        var p = new URLSearchParams(window.location.search).get('p') || 'marina';
        return p.replace(/-/g, ' ');
    }

    function esColumnaOmitir(cell, index, headerCells) {
        if (!cell) return true;
        if (cell.classList.contains('acciones')) return true;
        if (cell.querySelector && cell.querySelector('button, .btn, form')) return true;
        var th = headerCells[index];
        if (th) {
            var ht = (th.textContent || '').trim().toLowerCase();
            if (ht === '' || ht === 'acciones' || ht === 'acción' || ht === 'accion') return true;
        }
        return false;
    }

    function indicesVisibles(table) {
        var headerRow = table.querySelector('thead tr:last-child');
        if (!headerRow) return [];
        var ths = headerRow.querySelectorAll('th, td');
        var out = [];
        for (var i = 0; i < ths.length; i++) {
            if (!esColumnaOmitir(ths[i], i, ths)) out.push(i);
        }
        return out;
    }

    function textoCelda(cell) {
        if (!cell) return '';
        return (cell.innerText || cell.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function extraerEncabezados(table, cols) {
        var headerRow = table.querySelector('thead tr:last-child');
        if (!headerRow) return [];
        var ths = headerRow.querySelectorAll('th, td');
        return cols.map(function(i) { return textoCelda(ths[i]); });
    }

    function numColumnasTabla(table) {
        var headerRow = table.querySelector('thead tr:last-child');
        if (!headerRow) return 0;
        return headerRow.querySelectorAll('th, td').length;
    }

    /** Expande colspan/rowspan en una fila al ancho del thead (p. ej. totales en tfoot). */
    function expandirCeldasFila(tr, numCols) {
        var expanded = [];
        tr.querySelectorAll('td, th').forEach(function(cell) {
            var text = textoCelda(cell);
            var span = parseInt(cell.getAttribute('colspan'), 10) || 1;
            for (var s = 0; s < span; s++) {
                expanded.push(s === 0 ? text : '');
            }
        });
        if (numCols > 0) {
            while (expanded.length < numCols) expanded.push('');
            if (expanded.length > numCols) expanded = expanded.slice(0, numCols);
        }
        return expanded;
    }

    function filaDesdeTr(tr, cols, numCols) {
        if (!tr.querySelectorAll('td, th').length) return null;
        var expanded = expandirCeldasFila(tr, numCols);
        if (!expanded.length) return null;
        return cols.map(function(i) {
            return expanded[i] !== undefined ? expanded[i] : '';
        });
    }

    function filasPieTabla(table, cols, numCols) {
        var pie = [];
        table.querySelectorAll('tfoot tr').forEach(function(tr) {
            var row = filaDesdeTr(tr, cols, numCols);
            if (row && row.some(function(v) { return v !== ''; })) pie.push(row);
        });
        return pie;
    }

    function extraerFilasTabla(table) {
        var cols = indicesVisibles(table);
        if (!cols.length) return null;
        var numCols = numColumnasTabla(table);
        var headers = extraerEncabezados(table, cols);
        var rows = [];
        var bodyRows = table.querySelectorAll('tbody tr');
        bodyRows.forEach(function(tr) {
            var row = filaDesdeTr(tr, cols, numCols);
            if (row && row.some(function(v) { return v !== ''; })) rows.push(row);
        });
        filasPieTabla(table, cols, numCols).forEach(function(row) { rows.push(row); });
        if (!headers.length && !rows.length) return null;
        return { headers: headers, rows: rows };
    }

    function extraerConDataTables(table) {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.dataTable) return null;
        if (!window.jQuery.fn.dataTable.isDataTable(table)) return null;
        var api = window.jQuery(table).DataTable();
        var cols = indicesVisibles(table);
        if (!cols.length) return null;
        var numCols = numColumnasTabla(table);
        var headers = extraerEncabezados(table, cols);
        var rows = [];
        api.rows({ search: 'applied' }).every(function() {
            var row = filaDesdeTr(this.node(), cols, numCols);
            if (row) rows.push(row);
        });
        filasPieTabla(table, cols, numCols).forEach(function(row) { rows.push(row); });
        return { headers: headers, rows: rows };
    }

    function parseNumeroCelda(text) {
        if (text == null) return null;
        var raw = String(text).replace(/\s+/g, ' ').trim();
        if (raw === '' || raw === '—' || raw === '-' || raw === '–') return null;
        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return null;
        if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(raw)) return null;
        var t = raw.replace(/[^\d.,\-+]/g, '');
        if (t === '' || t === '-' || t === '+') return null;
        if (/,\d{1,2}$/.test(t)) {
            t = t.replace(/\./g, '').replace(',', '.');
        } else {
            t = t.replace(/,/g, '');
        }
        var n = parseFloat(t);
        return isNaN(n) ? null : n;
    }

    function esColumnaNumericaExport(headerText, table, colIndexReal) {
        var ht = (headerText || '').toLowerCase();
        if (/monto|pagado|saldo|total|cr[eé]dito|d[eé]bito|acumulado|costo|vencido|pend|∑|suma|abonado|facturado|neto|unid\.|ocup\.|libres/.test(ht)) {
            return true;
        }
        var headerRow = table.querySelector('thead tr:last-child');
        if (headerRow) {
            var ths = headerRow.querySelectorAll('th, td');
            var th = ths[colIndexReal];
            if (th && th.classList.contains('text-end')) return true;
        }
        return false;
    }

    function aplicarNumerosEnHoja(ws, table, cols, headers) {
        if (!ws || !ws['!ref']) return;
        var range = XLSX.utils.decode_range(ws['!ref']);
        var colsNum = headers.map(function(h, i) {
            return esColumnaNumericaExport(h, table, cols[i]);
        });
        for (var R = 1; R <= range.e.r; R++) {
            for (var C = 0; C <= range.e.c; C++) {
                if (!colsNum[C]) continue;
                var ref = XLSX.utils.encode_cell({ r: R, c: C });
                var cell = ws[ref];
                if (!cell) continue;
                var num = parseNumeroCelda(cell.v);
                if (num !== null) {
                    cell.t = 'n';
                    cell.v = num;
                    cell.z = '#,##0.00';
                } else if (cell.v === '' || cell.v === '—' || cell.v === '-') {
                    delete ws[ref];
                }
            }
        }
    }

    function exportarTabla(table) {
        if (!window.XLSX) {
            alert('No se cargó la librería de Excel. Recargue la página.');
            return;
        }
        var datos = extraerConDataTables(table) || extraerFilasTabla(table);
        if (!datos || (!datos.headers.length && !datos.rows.length)) {
            alert('No hay datos para exportar en esta tabla.');
            return;
        }
        var aoa = [];
        if (datos.headers.length) aoa.push(datos.headers);
        datos.rows.forEach(function(r) { aoa.push(r); });
        var ws = XLSX.utils.aoa_to_sheet(aoa);
        var cols = indicesVisibles(table);
        aplicarNumerosEnHoja(ws, table, cols, datos.headers);
        var wb = XLSX.utils.book_new();
        var hoja = (table.getAttribute('data-export-sheet') || 'Datos').slice(0, 31);
        XLSX.utils.book_append_sheet(wb, ws, hoja);
        var base = table.getAttribute('data-export-filename')
            || slugNombre(tituloPagina());
        var stamp = new Date();
        var suf = stamp.getFullYear()
            + String(stamp.getMonth() + 1).padStart(2, '0')
            + String(stamp.getDate()).padStart(2, '0')
            + '_' + String(stamp.getHours()).padStart(2, '0')
            + String(stamp.getMinutes()).padStart(2, '0');
        XLSX.writeFile(wb, base + '_' + suf + '.xlsx');
    }

    function colocarBoton(table) {
        if (table.getAttribute('data-marina-excel-ready') === '1') return;
        if (table.classList.contains('no-excel-export')) return;
        if (table.closest('.modal')) return;
        var tbody = table.querySelector('tbody');
        if (!tbody || !tbody.querySelector('tr')) return;

        table.setAttribute('data-marina-excel-ready', '1');
        var bar = document.createElement('div');
        bar.className = 'marina-excel-export-bar d-flex justify-content-end mb-2';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-success btn-sm marina-btn-export-xlsx';
        btn.textContent = 'Exportar Excel';
        btn.addEventListener('click', function() { exportarTabla(table); });
        bar.appendChild(btn);

        var card = table.closest('.card');
        if (card) {
            var body = card.querySelector('.card-body');
            if (body && body.contains(table)) {
                body.insertBefore(bar, body.firstChild);
                return;
            }
        }
        var resp = table.closest('.table-responsive');
        if (resp && resp.parentNode) {
            resp.parentNode.insertBefore(bar, resp);
            return;
        }
        table.parentNode.insertBefore(bar, table);
    }

    function initMarinaExcelExport() {
        if (!window.XLSX) return;
        document.querySelectorAll('table').forEach(colocarBoton);
    }

    window.marinaInitExcelExport = initMarinaExcelExport;
    window.marinaExportarTablaXlsx = exportarTabla;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMarinaExcelExport);
    } else {
        initMarinaExcelExport();
    }
    window.addEventListener('load', initMarinaExcelExport);
    setTimeout(initMarinaExcelExport, 400);
    setTimeout(initMarinaExcelExport, 1200);
})();
