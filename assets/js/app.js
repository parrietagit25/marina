/* Marina - modales CRUD por módulo */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-confirm]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!confirm('¿Está seguro?')) e.preventDefault();
        });
    });

    // --- Usuarios ---
    (function() {
        var el = document.getElementById('usuarioModal');
        if (!el) return;
        var title = document.getElementById('usuarioModalTitle');
        var accion = document.getElementById('usuarioFormAccion');
        var fid = document.getElementById('usuarioFormId');
        var nombre = document.getElementById('usuarioNombre');
        var email = document.getElementById('usuarioEmail');
        var password = document.getElementById('usuarioPassword');
        var activo = document.getElementById('usuarioActivo');
        var msg = document.getElementById('usuarioModalMensaje');
        var modal = new bootstrap.Modal(el);
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        function crear() {
            title.textContent = 'Nuevo usuario';
            accion.value = 'crear'; fid.value = '';
            nombre.value = ''; email.value = ''; password.value = ''; activo.checked = true;
            if (password) password.required = true; setErr(''); modal.show();
        }
        function editar(btn) {
            title.textContent = 'Editar usuario';
            accion.value = 'editar';
            fid.value = btn.getAttribute('data-id') || '';
            nombre.value = btn.getAttribute('data-nombre') || '';
            email.value = btn.getAttribute('data-email') || '';
            password.value = ''; if (password) password.required = false;
            activo.checked = (btn.getAttribute('data-activo') === '1');
            setErr(''); modal.show();
        }
        document.getElementById('btnNuevoUsuario') && document.getElementById('btnNuevoUsuario').addEventListener('click', crear);
        document.querySelectorAll('.btn-editar-usuario').forEach(function(b){ b.addEventListener('click', function(){ editar(b); }); });
        var delEl = document.getElementById('confirmEliminarUsuarioModal');
        if (delEl) {
            var d = new bootstrap.Modal(delEl);
            document.querySelectorAll('.btn-eliminar-usuario').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('usuarioDeleteId').value = b.getAttribute('data-id') || '';
                    document.getElementById('usuarioDeleteNombre').textContent = (b.getAttribute('data-nombre') || '') ? '"' + b.getAttribute('data-nombre') + '"' : '';
                    d.show();
                });
            });
        }
        if (window.__usuariosModal && window.__usuariosModal.mostrarModal) {
            var d = window.__usuariosModal.datos || {};
            if (window.__usuariosModal.modo === 'editar') {
                editar({ getAttribute: function(k) { return d[k === 'data-activo' ? 'activo' : k.replace('data-','')] !== undefined ? (k === 'data-activo' ? (d.activo ? '1' : '0') : d[k.replace('data-','')]) : null; } });
            } else { crear(); nombre.value = d.nombre || ''; email.value = d.email || ''; activo.checked = !!d.activo; }
            setErr(window.__usuariosModal.error || '');
        }
    })();

    // Bancos
    (function() {
        var el = document.getElementById('bancoModal');
        if (!el) return;
        var title = document.getElementById('bancoModalTitle');
        var accion = document.getElementById('bancoFormAccion');
        var fid = document.getElementById('bancoFormId');
        var nombre = document.getElementById('bancoNombre');
        var msg = document.getElementById('bancoModalMensaje');
        var modal = new bootstrap.Modal(el);
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        document.getElementById('btnNuevoBanco') && document.getElementById('btnNuevoBanco').addEventListener('click', function() {
            title.textContent = 'Nuevo banco'; accion.value = 'crear'; fid.value = ''; nombre.value = ''; setErr(''); modal.show();
        });
        document.querySelectorAll('.btn-editar-banco').forEach(function(b) {
            b.addEventListener('click', function() {
                title.textContent = 'Editar banco'; accion.value = 'editar'; fid.value = b.getAttribute('data-id') || ''; nombre.value = b.getAttribute('data-nombre') || ''; setErr(''); modal.show();
            });
        });
        var delEl = document.getElementById('confirmEliminarBancoModal');
        if (delEl) {
            var d = new bootstrap.Modal(delEl);
            document.querySelectorAll('.btn-eliminar-banco').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('bancoDeleteId').value = b.getAttribute('data-id') || '';
                    document.getElementById('bancoDeleteNombre').textContent = (b.getAttribute('data-nombre') || '') ? '"' + b.getAttribute('data-nombre') + '"' : '';
                    d.show();
                });
            });
        }
        if (window.__bancoModal && window.__bancoModal.mostrar && window.__bancoModal.datos) {
            var w = window.__bancoModal;
            title.textContent = 'Editar banco'; accion.value = 'editar'; fid.value = w.datos.id || ''; nombre.value = w.datos.nombre || ''; setErr(w.error || ''); modal.show();
        }
    })();

    // Muelles
    (function() {
        var el = document.getElementById('muelleModal');
        if (!el) return;
        var title = document.getElementById('muelleModalTitle');
        var accion = document.getElementById('muelleFormAccion');
        var fid = document.getElementById('muelleFormId');
        var nombre = document.getElementById('muelleNombre');
        var msg = document.getElementById('muelleModalMensaje');
        var modal = new bootstrap.Modal(el);
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        document.getElementById('btnNuevoMuelle') && document.getElementById('btnNuevoMuelle').addEventListener('click', function() {
            title.textContent = 'Nuevo muelle'; accion.value = 'crear'; fid.value = ''; nombre.value = ''; setErr(''); modal.show();
        });
        document.querySelectorAll('.btn-editar-muelle').forEach(function(b) {
            b.addEventListener('click', function() {
                title.textContent = 'Editar muelle'; accion.value = 'editar'; fid.value = b.getAttribute('data-id') || ''; nombre.value = b.getAttribute('data-nombre') || ''; setErr(''); modal.show();
            });
        });
        var delEl = document.getElementById('confirmEliminarMuelleModal');
        if (delEl) {
            var d = new bootstrap.Modal(delEl);
            document.querySelectorAll('.btn-eliminar-muelle').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('muelleDeleteId').value = b.getAttribute('data-id') || '';
                    document.getElementById('muelleDeleteNombre').textContent = (b.getAttribute('data-nombre') || '') ? '"' + b.getAttribute('data-nombre') + '"' : '';
                    d.show();
                });
            });
        }
        if (window.__muelleModal && window.__muelleModal.mostrar && window.__muelleModal.datos) {
            var w = window.__muelleModal;
            title.textContent = 'Editar muelle'; accion.value = 'editar'; fid.value = w.datos.id || ''; nombre.value = w.datos.nombre || ''; setErr(w.error || ''); modal.show();
        }
    })();

    // Formas de pago
    (function() {
        var el = document.getElementById('formaPagoModal');
        if (!el) return;
        var title = document.getElementById('formaPagoModalTitle');
        var accion = document.getElementById('formaPagoFormAccion');
        var fid = document.getElementById('formaPagoFormId');
        var nombre = document.getElementById('formaPagoNombre');
        var tipoMovimiento = document.getElementById('formaPagoTipoMovimiento');
        var msg = document.getElementById('formaPagoModalMensaje');
        var modal = new bootstrap.Modal(el);
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        document.getElementById('btnNuevoFormaPago') && document.getElementById('btnNuevoFormaPago').addEventListener('click', function() {
            title.textContent = 'Nuevo tipo de movimiento'; accion.value = 'crear'; fid.value = ''; nombre.value = ''; if (tipoMovimiento) tipoMovimiento.value = 'ingreso'; setErr(''); modal.show();
        });
        document.querySelectorAll('.btn-editar-formapago').forEach(function(b) {
            b.addEventListener('click', function() {
                title.textContent = 'Editar tipo de movimiento'; accion.value = 'editar'; fid.value = b.getAttribute('data-id') || ''; nombre.value = b.getAttribute('data-nombre') || ''; if (tipoMovimiento) tipoMovimiento.value = b.getAttribute('data-tipo-movimiento') || 'ingreso'; setErr(''); modal.show();
            });
        });
        var delEl = document.getElementById('confirmEliminarFormaPagoModal');
        if (delEl) {
            var d = new bootstrap.Modal(delEl);
            document.querySelectorAll('.btn-eliminar-formapago').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('formaPagoDeleteId').value = b.getAttribute('data-id') || '';
                    document.getElementById('formaPagoDeleteNombre').textContent = (b.getAttribute('data-nombre') || '') ? '"' + b.getAttribute('data-nombre') + '"' : '';
                    d.show();
                });
            });
        }
        if (window.__formaPagoModal && window.__formaPagoModal.mostrar && window.__formaPagoModal.datos) {
            var w = window.__formaPagoModal;
            title.textContent = 'Editar tipo de movimiento'; accion.value = 'editar'; fid.value = w.datos.id || ''; nombre.value = w.datos.nombre || ''; if (tipoMovimiento) tipoMovimiento.value = w.datos.tipo_movimiento || 'ingreso'; setErr(w.error || ''); modal.show();
        }
    })();

    // Cuentas (banco_id + nombre)
    (function() {
        var el = document.getElementById('cuentaModal');
        if (!el) return;
        var title = document.getElementById('cuentaModalTitle');
        var accion = document.getElementById('cuentaFormAccion');
        var fid = document.getElementById('cuentaFormId');
        var bancoId = document.getElementById('cuentaBancoId');
        var nombre = document.getElementById('cuentaNombre');
        var msg = document.getElementById('cuentaModalMensaje');
        var modal = new bootstrap.Modal(el);
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        document.getElementById('btnNuevoCuenta') && document.getElementById('btnNuevoCuenta').addEventListener('click', function() {
            title.textContent = 'Nueva cuenta'; accion.value = 'crear'; fid.value = ''; if (bancoId) bancoId.value = ''; nombre.value = ''; setErr(''); modal.show();
        });
        document.querySelectorAll('.btn-editar-cuenta').forEach(function(b) {
            b.addEventListener('click', function() {
                title.textContent = 'Editar cuenta'; accion.value = 'editar';
                fid.value = b.getAttribute('data-id') || '';
                if (bancoId) bancoId.value = b.getAttribute('data-banco-id') || '';
                nombre.value = b.getAttribute('data-nombre') || ''; setErr(''); modal.show();
            });
        });
        var delEl = document.getElementById('confirmEliminarCuentaModal');
        if (delEl) {
            var d = new bootstrap.Modal(delEl);
            document.querySelectorAll('.btn-eliminar-cuenta').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('cuentaDeleteId').value = b.getAttribute('data-id') || '';
                    document.getElementById('cuentaDeleteNombre').textContent = (b.getAttribute('data-nombre') || '') ? '"' + b.getAttribute('data-nombre') + '"' : '';
                    d.show();
                });
            });
        }
        if (window.__cuentaModal && window.__cuentaModal.mostrar && window.__cuentaModal.datos) {
            var w = window.__cuentaModal;
            title.textContent = 'Editar cuenta'; accion.value = 'editar'; fid.value = w.datos.id || ''; if (bancoId) bancoId.value = w.datos.banco_id || ''; nombre.value = w.datos.nombre || ''; setErr(w.error || ''); modal.show();
        }
    })();

    // Slips (muelle_id + nombre)
    (function() {
        var el = document.getElementById('slipModal');
        if (!el) return;
        var title = document.getElementById('slipModalTitle');
        var accion = document.getElementById('slipFormAccion');
        var fid = document.getElementById('slipFormId');
        var muelleId = document.getElementById('slipMuelleId');
        var nombre = document.getElementById('slipNombre');
        var msg = document.getElementById('slipModalMensaje');
        var modal = new bootstrap.Modal(el);
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        document.getElementById('btnNuevoSlip') && document.getElementById('btnNuevoSlip').addEventListener('click', function() {
            title.textContent = 'Nuevo slip'; accion.value = 'crear'; fid.value = ''; if (muelleId) muelleId.value = ''; nombre.value = ''; setErr(''); modal.show();
        });
        document.querySelectorAll('.btn-editar-slip').forEach(function(b) {
            b.addEventListener('click', function() {
                title.textContent = 'Editar slip'; accion.value = 'editar';
                fid.value = b.getAttribute('data-id') || '';
                if (muelleId) muelleId.value = b.getAttribute('data-muelle-id') || '';
                nombre.value = b.getAttribute('data-nombre') || ''; setErr(''); modal.show();
            });
        });
        var delEl = document.getElementById('confirmEliminarSlipModal');
        if (delEl) {
            var d = new bootstrap.Modal(delEl);
            document.querySelectorAll('.btn-eliminar-slip').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('slipDeleteId').value = b.getAttribute('data-id') || '';
                    document.getElementById('slipDeleteNombre').textContent = (b.getAttribute('data-nombre') || '') ? '"' + b.getAttribute('data-nombre') + '"' : '';
                    d.show();
                });
            });
        }
        if (window.__slipModal && window.__slipModal.mostrar && window.__slipModal.datos) {
            var w = window.__slipModal;
            title.textContent = 'Editar slip'; accion.value = 'editar'; fid.value = w.datos.id || ''; if (muelleId) muelleId.value = w.datos.muelle_id || ''; nombre.value = w.datos.nombre || ''; setErr(w.error || ''); modal.show();
        }
    })();

    // Grupos
    (function() {
        var el = document.getElementById('grupoModal');
        if (!el) return;
        var title = document.getElementById('grupoModalTitle');
        var accion = document.getElementById('grupoFormAccion');
        var fid = document.getElementById('grupoFormId');
        var nombre = document.getElementById('grupoNombre');
        var msg = document.getElementById('grupoModalMensaje');
        var modal = new bootstrap.Modal(el);
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        document.getElementById('btnNuevoGrupo') && document.getElementById('btnNuevoGrupo').addEventListener('click', function() {
            title.textContent = 'Nuevo grupo'; accion.value = 'crear'; fid.value = ''; nombre.value = ''; setErr(''); modal.show();
        });
        document.querySelectorAll('.btn-editar-grupo').forEach(function(b) {
            b.addEventListener('click', function() {
                title.textContent = 'Editar grupo'; accion.value = 'editar'; fid.value = b.getAttribute('data-id') || ''; nombre.value = b.getAttribute('data-nombre') || ''; setErr(''); modal.show();
            });
        });
        var delEl = document.getElementById('confirmEliminarGrupoModal');
        if (delEl) {
            var d = new bootstrap.Modal(delEl);
            document.querySelectorAll('.btn-eliminar-grupo').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('grupoDeleteId').value = b.getAttribute('data-id') || '';
                    document.getElementById('grupoDeleteNombre').textContent = (b.getAttribute('data-nombre') || '') ? '"' + b.getAttribute('data-nombre') + '"' : '';
                    d.show();
                });
            });
        }
        if (window.__grupoModal && window.__grupoModal.mostrar && window.__grupoModal.datos) {
            var w = window.__grupoModal;
            title.textContent = 'Editar grupo'; accion.value = 'editar'; fid.value = w.datos.id || ''; nombre.value = w.datos.nombre || ''; setErr(w.error || ''); modal.show();
        }
    })();

    // Inmuebles (grupo_id + nombre)
    (function() {
        var el = document.getElementById('inmuebleModal');
        if (!el) return;
        var title = document.getElementById('inmuebleModalTitle');
        var accion = document.getElementById('inmuebleFormAccion');
        var fid = document.getElementById('inmuebleFormId');
        var grupoId = document.getElementById('inmuebleGrupoId');
        var nombre = document.getElementById('inmuebleNombre');
        var msg = document.getElementById('inmuebleModalMensaje');
        var modal = new bootstrap.Modal(el);
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        document.getElementById('btnNuevoInmueble') && document.getElementById('btnNuevoInmueble').addEventListener('click', function() {
            title.textContent = 'Nuevo inmueble'; accion.value = 'crear'; fid.value = ''; if (grupoId) grupoId.value = ''; nombre.value = ''; setErr(''); modal.show();
        });
        document.querySelectorAll('.btn-editar-inmueble').forEach(function(b) {
            b.addEventListener('click', function() {
                title.textContent = 'Editar inmueble'; accion.value = 'editar';
                fid.value = b.getAttribute('data-id') || '';
                if (grupoId) grupoId.value = b.getAttribute('data-grupo-id') || '';
                nombre.value = b.getAttribute('data-nombre') || ''; setErr(''); modal.show();
            });
        });
        var delEl = document.getElementById('confirmEliminarInmuebleModal');
        if (delEl) {
            var d = new bootstrap.Modal(delEl);
            document.querySelectorAll('.btn-eliminar-inmueble').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('inmuebleDeleteId').value = b.getAttribute('data-id') || '';
                    document.getElementById('inmuebleDeleteNombre').textContent = (b.getAttribute('data-nombre') || '') ? '"' + b.getAttribute('data-nombre') + '"' : '';
                    d.show();
                });
            });
        }
        if (window.__inmuebleModal && window.__inmuebleModal.mostrar && window.__inmuebleModal.datos) {
            var w = window.__inmuebleModal;
            title.textContent = 'Editar inmueble'; accion.value = 'editar'; fid.value = w.datos.id || ''; if (grupoId) grupoId.value = w.datos.grupo_id || ''; nombre.value = w.datos.nombre || ''; setErr(w.error || ''); modal.show();
        }
    })();

    // Clientes (nombre, documento, telefono, email, direccion)
    (function() {
        var el = document.getElementById('clienteModal');
        if (!el) return;
        var title = document.getElementById('clienteModalTitle');
        var accion = document.getElementById('clienteFormAccion');
        var fid = document.getElementById('clienteFormId');
        var modal = new bootstrap.Modal(el);
        var msg = document.getElementById('clienteModalMensaje');
        var fields = ['nombre','documento','telefono','email','direccion'];
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        function clearForm() { fields.forEach(function(f) { var e = document.getElementById('cliente' + f.charAt(0).toUpperCase() + f.slice(1)); if (e) e.value = ''; }); }
        function fillForm(data) { fields.forEach(function(f) { var e = document.getElementById('cliente' + f.charAt(0).toUpperCase() + f.slice(1)); if (e && data[f] !== undefined) e.value = data[f] || ''; }); }
        document.getElementById('btnNuevoCliente') && document.getElementById('btnNuevoCliente').addEventListener('click', function() {
            title.textContent = 'Nuevo cliente'; accion.value = 'crear'; fid.value = ''; clearForm(); setErr(''); modal.show();
        });
        document.querySelectorAll('.btn-editar-cliente').forEach(function(b) {
            b.addEventListener('click', function() {
                title.textContent = 'Editar cliente'; accion.value = 'editar';
                fid.value = b.getAttribute('data-id') || '';
                fillForm({ nombre: b.getAttribute('data-nombre'), documento: b.getAttribute('data-documento'), telefono: b.getAttribute('data-telefono'), email: b.getAttribute('data-email'), direccion: b.getAttribute('data-direccion') });
                setErr(''); modal.show();
            });
        });
        var delEl = document.getElementById('confirmEliminarClienteModal');
        if (delEl) {
            var d = new bootstrap.Modal(delEl);
            document.querySelectorAll('.btn-eliminar-cliente').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('clienteDeleteId').value = b.getAttribute('data-id') || '';
                    document.getElementById('clienteDeleteNombre').textContent = (b.getAttribute('data-nombre') || '') ? '"' + b.getAttribute('data-nombre') + '"' : '';
                    d.show();
                });
            });
        }
        if (window.__clienteModal && window.__clienteModal.mostrar && window.__clienteModal.datos) {
            var w = window.__clienteModal;
            title.textContent = 'Editar cliente'; accion.value = 'editar'; fid.value = w.datos.id || ''; fillForm(w.datos); setErr(w.error || ''); modal.show();
        }
    })();

    // Proveedores
    (function() {
        var el = document.getElementById('proveedorModal');
        if (!el) return;
        var title = document.getElementById('proveedorModalTitle');
        var accion = document.getElementById('proveedorFormAccion');
        var fid = document.getElementById('proveedorFormId');
        var modal = new bootstrap.Modal(el);
        var msg = document.getElementById('proveedorModalMensaje');
        var fields = ['nombre','documento','telefono','email','direccion'];
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        function clearForm() { fields.forEach(function(f) { var e = document.getElementById('proveedor' + f.charAt(0).toUpperCase() + f.slice(1)); if (e) e.value = ''; }); }
        function fillForm(data) { fields.forEach(function(f) { var e = document.getElementById('proveedor' + f.charAt(0).toUpperCase() + f.slice(1)); if (e && data[f] !== undefined) e.value = data[f] || ''; }); }
        document.getElementById('btnNuevoProveedor') && document.getElementById('btnNuevoProveedor').addEventListener('click', function() {
            title.textContent = 'Nuevo proveedor'; accion.value = 'crear'; fid.value = ''; clearForm(); setErr(''); modal.show();
        });
        document.querySelectorAll('.btn-editar-proveedor').forEach(function(b) {
            b.addEventListener('click', function() {
                title.textContent = 'Editar proveedor'; accion.value = 'editar';
                fid.value = b.getAttribute('data-id') || '';
                fillForm({ nombre: b.getAttribute('data-nombre'), documento: b.getAttribute('data-documento'), telefono: b.getAttribute('data-telefono'), email: b.getAttribute('data-email'), direccion: b.getAttribute('data-direccion') });
                setErr(''); modal.show();
            });
        });
        var delEl = document.getElementById('confirmEliminarProveedorModal');
        if (delEl) {
            var d = new bootstrap.Modal(delEl);
            document.querySelectorAll('.btn-eliminar-proveedor').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('proveedorDeleteId').value = b.getAttribute('data-id') || '';
                    document.getElementById('proveedorDeleteNombre').textContent = (b.getAttribute('data-nombre') || '') ? '"' + b.getAttribute('data-nombre') + '"' : '';
                    d.show();
                });
            });
        }
        if (window.__proveedorModal && window.__proveedorModal.mostrar && window.__proveedorModal.datos) {
            var w = window.__proveedorModal;
            title.textContent = 'Editar proveedor'; accion.value = 'editar'; fid.value = w.datos.id || ''; fillForm(w.datos); setErr(w.error || ''); modal.show();
        }
    })();

    // Partidas (parent_id + nombre)
    (function() {
        var el = document.getElementById('partidaModal');
        if (!el) return;
        var title = document.getElementById('partidaModalTitle');
        var accion = document.getElementById('partidaFormAccion');
        var fid = document.getElementById('partidaFormId');
        var parentId = document.getElementById('partidaParentId');
        var nombre = document.getElementById('partidaNombre');
        var msg = document.getElementById('partidaModalMensaje');
        var modal = new bootstrap.Modal(el);
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        document.getElementById('btnNuevaPartidaRaiz') && document.getElementById('btnNuevaPartidaRaiz').addEventListener('click', function() {
            title.textContent = 'Nueva partida (raíz)'; accion.value = 'crear'; fid.value = ''; if (parentId) parentId.value = '0'; if (nombre) nombre.value = ''; setErr(''); modal.show();
        });
        document.querySelectorAll('.btn-subpartida-partida').forEach(function(b) {
            b.addEventListener('click', function() {
                var pid = b.getAttribute('data-parent-id') || '0';
                title.textContent = 'Nueva subpartida'; accion.value = 'crear'; fid.value = ''; if (parentId) parentId.value = pid; if (nombre) nombre.value = ''; setErr(''); modal.show();
            });
        });
        document.querySelectorAll('.btn-editar-partida').forEach(function(b) {
            b.addEventListener('click', function() {
                title.textContent = 'Editar partida'; accion.value = 'editar';
                fid.value = b.getAttribute('data-id') || '';
                if (parentId) parentId.value = b.getAttribute('data-parent-id') || '0';
                if (nombre) nombre.value = b.getAttribute('data-nombre') || ''; setErr(''); modal.show();
            });
        });
        var delEl = document.getElementById('confirmEliminarPartidaModal');
        if (delEl) {
            var d = new bootstrap.Modal(delEl);
            document.querySelectorAll('.btn-eliminar-partida').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('partidaDeleteId').value = b.getAttribute('data-id') || '';
                    var nomEl = document.getElementById('partidaDeleteNombre');
                    if (nomEl) nomEl.textContent = (b.getAttribute('data-nombre') || '') ? '"' + b.getAttribute('data-nombre') + '"' : '';
                    d.show();
                });
            });
        }
        if (window.__partidaModal && window.__partidaModal.mostrar) {
            var w = window.__partidaModal;
            if (w.datos && w.datos.id) {
                title.textContent = 'Editar partida'; accion.value = 'editar'; fid.value = w.datos.id || ''; if (parentId) parentId.value = w.datos.parent_id || '0'; if (nombre) nombre.value = w.datos.nombre || ''; setErr(w.error || ''); modal.show();
            } else {
                title.textContent = 'Nueva partida'; accion.value = 'crear'; fid.value = ''; if (parentId) parentId.value = (w.datos && w.datos.parent_id) ? w.datos.parent_id : '0'; if (nombre) nombre.value = (w.datos && w.datos.nombre) ? w.datos.nombre : ''; setErr(w.error || ''); modal.show();
            }
        }
    })();

    // Contratos
    (function() {
        var el = document.getElementById('contratoModal');
        if (!el) return;
        var title = document.getElementById('contratoModalTitle');
        var accion = document.getElementById('contratoFormAccion');
        var fid = document.getElementById('contratoFormId');
        var modal = new bootstrap.Modal(el);
        var msg = document.getElementById('contratoModalMensaje');
        var ids = ['ClienteId','CuentaId','MuelleId','SlipId','GrupoId','InmuebleId'];
        var fields = ['FechaInicio','FechaFin','MontoTotal','Observaciones'];
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        function get(id) { var e = document.getElementById('contrato' + id); return e ? e.value : ''; }
        function set(id, v) { var e = document.getElementById('contrato' + id); if (e) e.value = v !== undefined && v !== null ? v : ''; }
        function setActivo(v) { var e = document.getElementById('contratoActivo'); if (e) e.checked = !!v; }
        document.getElementById('btnNuevoContrato') && document.getElementById('btnNuevoContrato').addEventListener('click', function() {
            title.textContent = 'Nuevo contrato'; accion.value = 'crear'; fid.value = '';
            ids.forEach(function(i){ set(i, ''); }); set('FechaInicio', ''); set('FechaFin', ''); set('MontoTotal', ''); set('Observaciones', ''); setActivo(1); setErr(''); modal.show();
        });
        document.querySelectorAll('.btn-editar-contrato').forEach(function(b) {
            b.addEventListener('click', function() {
                title.textContent = 'Editar contrato'; accion.value = 'editar';
                fid.value = b.getAttribute('data-id') || '';
                set('ClienteId', b.getAttribute('data-cliente-id')); set('CuentaId', b.getAttribute('data-cuenta-id')); set('MuelleId', b.getAttribute('data-muelle-id')); set('SlipId', b.getAttribute('data-slip-id')); set('GrupoId', b.getAttribute('data-grupo-id')); set('InmuebleId', b.getAttribute('data-inmueble-id'));
                set('FechaInicio', b.getAttribute('data-fecha-inicio')); set('FechaFin', b.getAttribute('data-fecha-fin')); set('MontoTotal', b.getAttribute('data-monto-total')); set('Observaciones', b.getAttribute('data-observaciones')); setActivo(b.getAttribute('data-activo') === '1'); setErr(''); modal.show();
            });
        });
        var delEl = document.getElementById('confirmEliminarContratoModal');
        if (delEl) {
            var d = new bootstrap.Modal(delEl);
            document.querySelectorAll('.btn-eliminar-contrato').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('contratoDeleteId').value = b.getAttribute('data-id') || '';
                    d.show();
                });
            });
        }
        if (window.__contratoModal && window.__contratoModal.mostrar && window.__contratoModal.datos) {
            var w = window.__contratoModal;
            var d = w.datos;
            title.textContent = (d.id && d.id !== '') ? 'Editar contrato' : 'Nuevo contrato'; accion.value = (d.id && d.id !== '') ? 'editar' : 'crear'; fid.value = d.id || '';
            set('ClienteId', d.cliente_id); set('CuentaId', d.cuenta_id); set('MuelleId', d.muelle_id); set('SlipId', d.slip_id); set('GrupoId', d.grupo_id); set('InmuebleId', d.inmueble_id);
            set('FechaInicio', d.fecha_inicio); set('FechaFin', d.fecha_fin); set('MontoTotal', d.monto_total); set('Observaciones', d.observaciones); setActivo(d.activo); setErr(w.error || ''); modal.show();
        }
    })();

    // Gastos
    (function() {
        var el = document.getElementById('gastoModal');
        if (!el) return;
        var title = document.getElementById('gastoModalTitle');
        var accion = document.getElementById('gastoFormAccion');
        var fid = document.getElementById('gastoFormId');
        var modal = new bootstrap.Modal(el);
        var msg = document.getElementById('gastoModalMensaje');
        var ids = ['PartidaId','ProveedorId','CuentaId','FormaPagoId'];
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        function set(id, v) { var e = document.getElementById('gasto' + id); if (e) e.value = v !== undefined && v !== null ? v : ''; }
        document.getElementById('btnNuevoGasto') && document.getElementById('btnNuevoGasto').addEventListener('click', function() {
            title.textContent = 'Nuevo gasto'; accion.value = 'crear'; fid.value = '';
            set('PartidaId',''); set('ProveedorId',''); set('CuentaId',''); set('FormaPagoId',''); set('Monto',''); set('FechaGasto', new Date().toISOString().slice(0,10)); set('Referencia',''); set('Observaciones',''); setErr(''); modal.show();
        });
        document.querySelectorAll('.btn-editar-gasto').forEach(function(b) {
            b.addEventListener('click', function() {
                title.textContent = 'Editar gasto'; accion.value = 'editar';
                fid.value = b.getAttribute('data-id') || '';
                set('PartidaId', b.getAttribute('data-partida-id')); set('ProveedorId', b.getAttribute('data-proveedor-id')); set('CuentaId', b.getAttribute('data-cuenta-id')); set('FormaPagoId', b.getAttribute('data-forma-pago-id'));
                set('Monto', b.getAttribute('data-monto')); set('FechaGasto', b.getAttribute('data-fecha-gasto')); set('Referencia', b.getAttribute('data-referencia')); set('Observaciones', b.getAttribute('data-observaciones')); setErr(''); modal.show();
            });
        });
        var delEl = document.getElementById('confirmEliminarGastoModal');
        if (delEl) {
            var d = new bootstrap.Modal(delEl);
            document.querySelectorAll('.btn-eliminar-gasto').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('gastoDeleteId').value = b.getAttribute('data-id') || '';
                    d.show();
                });
            });
        }
        if (window.__gastoModal && window.__gastoModal.mostrar && window.__gastoModal.datos) {
            var w = window.__gastoModal;
            var d = w.datos;
            title.textContent = (d.id && d.id !== '') ? 'Editar gasto' : 'Nuevo gasto'; accion.value = (d.id && d.id !== '') ? 'editar' : 'crear'; fid.value = d.id || '';
            set('PartidaId', d.partida_id); set('ProveedorId', d.proveedor_id); set('CuentaId', d.cuenta_id); set('FormaPagoId', d.forma_pago_id);
            set('Monto', d.monto); set('FechaGasto', d.fecha_gasto); set('Referencia', d.referencia); set('Observaciones', d.observaciones); setErr(w.error || ''); modal.show();
        }
    })();

    // Cuotas (contrato): agregar, pagar, abonar y ver movimientos
    (function() {
        var modalAgregar = document.getElementById('modalAgregarCuota');
        var modalPagar = document.getElementById('modalPagarCuota');
        var modalAbonar = document.getElementById('modalAbonarCuota');
        var modalVer = document.getElementById('modalVerCuotaMovs');
        if (!modalAgregar && !modalPagar && !modalAbonar && !modalVer) return;

        function formatMoney(v) {
            var n = parseFloat((v + '').replace(',', '.'));
            if (isNaN(n)) return v;
            return n.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // --- Agregar cuota
        if (modalAgregar) {
            var mAgregar = new bootstrap.Modal(modalAgregar);
            var msgAgregar = document.getElementById('modalAgregarCuotaMensaje');
            var numEl = document.getElementById('agregarNumeroCuota');
            var montoEl = document.getElementById('agregarMontoCuota');
            var vencEl = document.getElementById('agregarFechaVencimiento');
            function setErrAgregar(m) { if (msgAgregar) { msgAgregar.textContent = m || ''; msgAgregar.classList.toggle('d-none', !m); } }
            var btnAgregar = document.getElementById('btnAgregarCuota');
            var proxima = document.querySelector('[data-proxima-cuota]') ? document.querySelector('[data-proxima-cuota]').getAttribute('data-proxima-cuota') : '';
            if (btnAgregar) btnAgregar.addEventListener('click', function() {
                if (numEl) numEl.value = proxima || '';
                if (montoEl) montoEl.value = '';
                if (vencEl) vencEl.value = '';
                setErrAgregar('');
                mAgregar.show();
            });
            if (window.__cuotaAgregarModal && window.__cuotaAgregarModal.mostrar) {
                var w = window.__cuotaAgregarModal;
                if (numEl && w.datos) numEl.value = w.datos.numero_cuota || '';
                if (montoEl && w.datos) montoEl.value = w.datos.monto_cuota || '';
                if (vencEl && w.datos) vencEl.value = w.datos.fecha_vencimiento || '';
                setErrAgregar(w.error || '');
                mAgregar.show();
            }
        }

        // --- Pagar cuota (saldo completo)
        if (modalPagar) {
            var mPagar = new bootstrap.Modal(modalPagar);
            var msgPagar = document.getElementById('modalPagarCuotaMensaje');
            var pagarCuotaId = document.getElementById('pagarCuotaId');
            var pagarMontoMov = document.getElementById('pagarMontoMovimiento');
            var pagarMontoPreview = document.getElementById('pagarMontoPreview');
            var pagarCuotaInfo = document.getElementById('pagarCuotaInfo');
            var pagarFecha = document.getElementById('pagarFechaPago');
            var pagarForma = document.getElementById('pagarFormaPagoId');
            var pagarRef = document.getElementById('pagarReferencia');
            function setErrPagar(m) { if (msgPagar) { msgPagar.textContent = m || ''; msgPagar.classList.toggle('d-none', !m); } }
            document.querySelectorAll('.btn-pagar-cuota').forEach(function(b) {
                b.addEventListener('click', function() {
                    var cid = b.getAttribute('data-cuota-id') || '';
                    var num = b.getAttribute('data-numero') || '';
                    var saldo = b.getAttribute('data-saldo') || '';
                    if (pagarCuotaId) pagarCuotaId.value = cid;
                    if (pagarMontoMov) pagarMontoMov.value = saldo;
                    if (pagarMontoPreview) pagarMontoPreview.value = saldo ? formatMoney(saldo) : '';
                    if (pagarCuotaInfo) pagarCuotaInfo.textContent = 'Cuota Nº ' + num + ' (saldo completo: ' + (saldo ? formatMoney(saldo) : '') + ')';
                    if (pagarFecha) pagarFecha.value = new Date().toISOString().slice(0, 10);
                    if (pagarForma) pagarForma.value = '';
                    if (pagarRef) pagarRef.value = '';
                    setErrPagar('');
                    mPagar.show();
                });
            });
            if (window.__cuotaPagarModal && window.__cuotaPagarModal.mostrar) {
                var w = window.__cuotaPagarModal;
                if (pagarCuotaId && w.datos) pagarCuotaId.value = w.datos.cuota_mov_id || '';
                if (pagarMontoMov && w.datos) pagarMontoMov.value = w.datos.monto_movimiento || '';
                if (pagarMontoPreview && w.datos) pagarMontoPreview.value = w.datos.monto_movimiento ? formatMoney(w.datos.monto_movimiento) : '';
                if (pagarFecha && w.datos) pagarFecha.value = w.datos.fecha_pago || new Date().toISOString().slice(0, 10);
                if (pagarForma && w.datos) pagarForma.value = w.datos.forma_pago_id || '';
                if (pagarRef && w.datos) pagarRef.value = w.datos.referencia_pago || '';
                if (pagarCuotaInfo && w.datos) {
                    var s = w.datos.saldo_disponible || '';
                    pagarCuotaInfo.textContent = 'Cuota Nº ' + (w.datos.numero_cuota || '') + ' (saldo: ' + (s ? formatMoney(s) : '') + ')';
                }
                setErrPagar(w.error || '');
                mPagar.show();
            }
        }

        // --- Abonar cuota (monto parcial)
        if (modalAbonar) {
            var mAbonar = new bootstrap.Modal(modalAbonar);
            var msgAbonar = document.getElementById('modalAbonarCuotaMensaje');
            var abonarCuotaId = document.getElementById('abonarCuotaId');
            var abonarMonto = document.getElementById('abonarMonto');
            var abonarCuotaInfo = document.getElementById('abonarCuotaInfo');
            var abonarFecha = document.getElementById('abonarFechaPago');
            var abonarForma = document.getElementById('abonarFormaPagoId');
            var abonarRef = document.getElementById('abonarReferencia');
            function setErrAbonar(m) { if (msgAbonar) { msgAbonar.textContent = m || ''; msgAbonar.classList.toggle('d-none', !m); } }
            document.querySelectorAll('.btn-abonar-cuota').forEach(function(b) {
                b.addEventListener('click', function() {
                    var cid = b.getAttribute('data-cuota-id') || '';
                    var num = b.getAttribute('data-numero') || '';
                    var saldo = b.getAttribute('data-saldo') || '';
                    if (abonarCuotaId) abonarCuotaId.value = cid;
                    if (abonarMonto) abonarMonto.value = saldo ? saldo : '';
                    if (abonarCuotaInfo) abonarCuotaInfo.textContent = 'Cuota Nº ' + num + ' (saldo disponible: ' + (saldo ? formatMoney(saldo) : '') + ')';
                    if (abonarFecha) abonarFecha.value = new Date().toISOString().slice(0, 10);
                    if (abonarForma) abonarForma.value = '';
                    if (abonarRef) abonarRef.value = '';
                    setErrAbonar('');
                    mAbonar.show();
                });
            });
            if (window.__cuotaAbonarModal && window.__cuotaAbonarModal.mostrar) {
                var w = window.__cuotaAbonarModal;
                if (abonarCuotaId && w.datos) abonarCuotaId.value = w.datos.cuota_mov_id || '';
                if (abonarMonto && w.datos) abonarMonto.value = w.datos.monto_movimiento || '';
                if (abonarFecha && w.datos) abonarFecha.value = w.datos.fecha_pago || new Date().toISOString().slice(0, 10);
                if (abonarForma && w.datos) abonarForma.value = w.datos.forma_pago_id || '';
                if (abonarRef && w.datos) abonarRef.value = w.datos.referencia_pago || '';
                if (abonarCuotaInfo && w.datos) {
                    var s = w.datos.saldo_disponible || '';
                    abonarCuotaInfo.textContent = 'Cuota Nº ' + (w.datos.numero_cuota || '') + ' (saldo disponible: ' + (s ? formatMoney(s) : '') + ')';
                }
                setErrAbonar(w.error || '');
                mAbonar.show();
            }
        }

        // --- Ver movimientos de cuota (pagos y abonos)
        if (modalVer) {
            var mVer = new bootstrap.Modal(modalVer);
            var tipoFiltro = document.getElementById('movsTipoFiltro');
            var saldoInfo = document.getElementById('movsSaldoInfo');
            var tablaBody = document.getElementById('movsTablaBody');
            var verCuotaActual = null;

            function render() {
                if (!tablaBody) return;
                if (!window.__cuotasMovimientos || !verCuotaActual) return;
                var data = window.__cuotasMovimientos[verCuotaActual];
                if (!data) {
                    tablaBody.innerHTML = '<tr><td colspan="4">No hay datos.</td></tr>';
                    return;
                }
                var tipo = (tipoFiltro && tipoFiltro.value) ? tipoFiltro.value : 'todos';
                var movimientos = data.movimientos || [];
                if (tipo !== 'todos') movimientos = movimientos.filter(function(m) { return (m.tipo || '') === tipo; });

                if (!movimientos.length) {
                    tablaBody.innerHTML = '<tr><td colspan="4">No hay movimientos.</td></tr>';
                    return;
                }

                var rows = movimientos.map(function(m) {
                    var fecha = m.fecha_pago ? m.fecha_pago : '—';
                    var monto = (m.monto !== undefined && m.monto !== null) ? formatMoney(m.monto) : '—';
                    var forma = m.forma_pago_nombre ? m.forma_pago_nombre : '—';
                    var ref = m.referencia ? m.referencia : '—';
                    return '<tr>' +
                        '<td>' + fecha + '</td>' +
                        '<td>' + monto + '</td>' +
                        '<td>' + forma + '</td>' +
                        '<td>' + ref + '</td>' +
                        '</tr>';
                }).join('');

                tablaBody.innerHTML = rows;
                if (saldoInfo && data.saldo !== undefined && data.saldo !== null) {
                    saldoInfo.textContent = 'Saldo actual: ' + formatMoney(data.saldo);
                }
            }

            document.querySelectorAll('.btn-ver-cuota-movs').forEach(function(b) {
                b.addEventListener('click', function() {
                    verCuotaActual = b.getAttribute('data-cuota-id') || '';
                    var t = b.getAttribute('data-tipo') || 'todos';
                    if (tipoFiltro) tipoFiltro.value = t;
                    render();
                    mVer.show();
                });
            });

            if (tipoFiltro) {
                tipoFiltro.addEventListener('change', function() {
                    render();
                });
            }
        }
    })();

});
