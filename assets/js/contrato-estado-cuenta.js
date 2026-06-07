/**
 * Modal E. Cuentas: estado de cuenta del contrato (cuotas + electricidad).
 */
function marinaInitEstadoCuentaContrato() {
    var D = 'd' + 'iv';
    var tag = function (cls, inner, close) {
        return '<' + D + (cls ? ' class="' + cls + '"' : '') + '>' + inner + (close !== false ? '</' + D + '>' : '');
    };

    var modalEl = document.getElementById('modalEstadoCuentaContrato');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var titleEl = document.getElementById('ecContratoTitle');
    var bodyEl = document.getElementById('ecContratoBody');
    var linkCuotas = document.getElementById('ecLinkCuotas');
    var linkEle = document.getElementById('ecLinkElectricidad');
    var btnEnviarEmail = document.getElementById('ecBtnEnviarEmail');
    var baseUrl = (typeof window.MARINA_EC_BASE === 'string' && window.MARINA_EC_BASE) ? window.MARINA_EC_BASE : 'index.php';
    var contratoEcActual = 0;

    function escEc(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtEc(n) {
        var x = parseFloat(String(n).replace(',', '.'));
        if (isNaN(x)) return '—';
        return x.toLocaleString('es-PA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function badgeEstado(est) {
        var e = String(est || '');
        var cls = 'bg-secondary';
        if (e === 'Pagada') cls = 'bg-success';
        else if (e === 'Pendiente') cls = 'bg-warning text-dark';
        else if (e === 'Parcial') cls = 'bg-info text-dark';
        return '<span class="badge ' + cls + '">' + escEc(e) + '</span>';
    }

    function fmtFecha(x) {
        return typeof marinaFmtFecha === 'function' ? marinaFmtFecha(x || '') : escEc(x);
    }

    function renderMovimientosCuota(movs) {
        if (!movs || !movs.length) {
            return tag('small text-muted mb-0', 'Sin pagos ni abonos registrados.');
        }
        var rows = movs.map(function (m) {
            var tipo = (m.tipo === 'abono') ? 'Abono' : 'Pago';
            return '<tr><td>' + escEc(tipo) + '</td><td>' + fmtFecha(m.fecha_pago) + '</td>' +
                '<td class="text-end">' + fmtEc(m.monto) + '</td><td>' + escEc(m.concepto || '—') + '</td>' +
                '<td>' + escEc(m.forma_pago || '—') + '</td><td>' + escEc(m.referencia || '—') + '</td></tr>';
        }).join('');
        return '<table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr>' +
            '<th>Tipo</th><th>Fecha</th><th class="text-end">Monto</th><th>Concepto</th><th>Forma pago</th><th>Referencia</th>' +
            '</tr></thead><tbody>' + rows + '</tbody></table>';
    }

    function renderEstadoCuenta(data) {
        var c = data.contrato || {};
        var rc = data.resumen_cuotas || {};
        var re = data.resumen_electricidad || {};
        var cuotas = data.cuotas || [];
        var ele = data.electricidad || [];

        var estadoContr = (c.estado === 'activo')
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Liberado</span>';

        var html = tag('mb-3 p-3 rounded border bg-light',
            tag('row g-2 small',
                tag('col-md-6', '<strong>Cliente / embarcación:</strong> ' + escEc(c.cliente)) +
                tag('col-md-6', '<strong>Dueño / capitán:</strong> ' + escEc(c.dueno_capitan || '—')) +
                tag('col-md-6', '<strong>Unidad:</strong> ' + escEc(c.unidad)) +
                tag('col-md-6', '<strong>Cuenta:</strong> ' + escEc(c.cuenta)) +
                tag('col-md-4', '<strong>Período:</strong> ' + fmtFecha(c.fecha_inicio) + ' – ' + fmtFecha(c.fecha_fin)) +
                tag('col-md-4', '<strong>Monto contrato:</strong> ' + fmtEc(c.monto_total)) +
                tag('col-md-4', '<strong>Estado:</strong> ' + estadoContr) +
                (c.numero_recibo ? tag('col-12', '<strong>Nº recibo:</strong> ' + escEc(c.numero_recibo)) : '')
            )
        );

        html += '<h6 class="mt-2 mb-2">Cuotas del contrato</h6><p class="small text-muted mb-2">Total cuotas: <strong>' +
            fmtEc(rc.total_cuotas) + '</strong> · Pagado: <strong>' + fmtEc(rc.total_pagado) + '</strong> · Saldo: <strong>' +
            fmtEc(rc.saldo) + '</strong> · Monto contrato: ' + fmtEc(rc.monto_contrato) + '</p>';

        if (!cuotas.length) {
            html += '<p class="text-muted">No hay cuotas registradas.</p>';
        } else {
            cuotas.forEach(function (cu) {
                html += tag('card mb-2 border',
                    tag('card-body py-2',
                        tag('d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2',
                            '<strong>Cuota #' + escEc(cu.numero) + '</strong> ' + badgeEstado(cu.estado) +
                            '<span class="small">Vence: ' + fmtFecha(cu.vencimiento) + '</span>' +
                            '<span class="small">Monto: ' + fmtEc(cu.monto) + ' · Pagado: ' + fmtEc(cu.pagado) + ' · Saldo: ' + fmtEc(cu.saldo) + '</span>'
                        ) + renderMovimientosCuota(cu.movimientos)
                    )
                );
            });
        }

        html += '<h6 class="mt-4 mb-2">Electricidad</h6><p class="small text-muted mb-2">Facturado: <strong>' +
            fmtEc(re.total_facturado) + '</strong> · Pagado: <strong>' + fmtEc(re.total_pagado) +
            '</strong> · Saldo: <strong>' + fmtEc(re.saldo) + '</strong></p>';

        if (!ele.length) {
            html += '<p class="text-muted">No hay facturas de electricidad.</p>';
        } else {
            ele.forEach(function (f) {
                var label = 'Factura';
                if (f.numero_factura) label += ' ' + escEc(f.numero_factura);
                if (f.fecha_factura) label += ' · ' + fmtFecha(f.fecha_factura);
                var inner = tag('d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2',
                    '<strong>' + label + '</strong> ' + badgeEstado(f.estado) +
                    '<span class="small">Total: ' + fmtEc(f.monto_total) + ' · Pagado: ' + fmtEc(f.pagado) + ' · Saldo: ' + fmtEc(f.saldo) + '</span>'
                );
                if (f.periodo) inner += '<p class="small mb-2 text-muted">Período: ' + escEc(f.periodo) + '</p>';
                if (f.observaciones) inner += '<p class="small mb-2">' + escEc(f.observaciones) + '</p>';
                if (!f.pagos || !f.pagos.length) {
                    inner += '<p class="small text-muted mb-0">Sin pagos registrados.</p>';
                } else {
                    var pr = f.pagos.map(function (p) {
                        return '<tr><td>' + fmtFecha(p.fecha_pago) + '</td><td class="text-end">' + fmtEc(p.monto) +
                            '</td><td>' + escEc(p.cuenta) + '</td><td>' + escEc(p.forma_pago) + '</td><td>' +
                            escEc(p.referencia) + '</td><td>' + escEc(p.observaciones) + '</td></tr>';
                    }).join('');
                    inner += '<table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr>' +
                        '<th>Fecha</th><th class="text-end">Monto</th><th>Cuenta</th><th>Forma pago</th><th>Referencia</th><th>Obs.</th>' +
                        '</tr></thead><tbody>' + pr + '</tbody></table>';
                }
                html += tag('card mb-2 border', tag('card-body py-2', inner));
            });
        }

        return html;
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('.btn-estado-cuenta-contrato') : null;
        if (!btn) return;
        var id = btn.getAttribute('data-contrato-id') || '';
        if (!id) return;
        contratoEcActual = parseInt(id, 10) || 0;

        if (titleEl) titleEl.textContent = 'Estado de cuenta — contrato #' + id;
        if (bodyEl) bodyEl.innerHTML = '<p class="text-muted mb-0">Cargando…</p>';
        if (linkCuotas) {
            linkCuotas.href = baseUrl + '?p=contratos&accion=cuotas&id=' + encodeURIComponent(id);
            linkCuotas.classList.remove('d-none');
        }
        if (linkEle) {
            linkEle.href = baseUrl + '?p=contratos-electricidad&id=' + encodeURIComponent(id);
            linkEle.classList.remove('d-none');
        }
        if (btnEnviarEmail) {
            btnEnviarEmail.classList.remove('d-none');
            btnEnviarEmail.disabled = false;
            btnEnviarEmail.textContent = 'Enviar por correo';
        }
        bsModal.show();

        fetch(baseUrl + '?p=contrato-estado-cuenta&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.ok || !json.data) {
                    if (bodyEl) {
                        bodyEl.innerHTML = tag('alert alert-danger mb-0',
                            escEc((json && json.error) || 'No se pudo cargar el estado de cuenta.'));
                    }
                    return;
                }
                if (bodyEl) bodyEl.innerHTML = renderEstadoCuenta(json.data);
            })
            .catch(function () {
                if (bodyEl) {
                    bodyEl.innerHTML = tag('alert alert-danger mb-0', 'Error de conexión al cargar el estado de cuenta.');
                }
            });
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        if (bodyEl) bodyEl.innerHTML = '<p class="text-muted mb-0">Cargando…</p>';
        if (linkCuotas) linkCuotas.classList.add('d-none');
        if (linkEle) linkEle.classList.add('d-none');
        if (btnEnviarEmail) btnEnviarEmail.classList.add('d-none');
        contratoEcActual = 0;
    });

    if (btnEnviarEmail) {
        btnEnviarEmail.addEventListener('click', function () {
            if (!contratoEcActual) return;
            if (!confirm('¿Enviar el estado de cuenta por correo al cliente?')) return;
            btnEnviarEmail.disabled = true;
            btnEnviarEmail.textContent = 'Enviando…';
            var fd = new FormData();
            fd.append('accion', 'enviar_email');
            fd.append('contrato_id', String(contratoEcActual));
            fetch(baseUrl + '?p=contrato-estado-cuenta&id=' + encodeURIComponent(contratoEcActual), {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (json) {
                alert((json && json.ok) ? (json.mensaje || 'Enviado.') : ((json && json.error) || 'No se pudo enviar.'));
            }).catch(function () {
                alert('Error de conexión.');
            }).finally(function () {
                btnEnviarEmail.disabled = false;
                btnEnviarEmail.textContent = 'Enviar por correo';
            });
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', marinaInitEstadoCuentaContrato);
} else {
    marinaInitEstadoCuentaContrato();
}
