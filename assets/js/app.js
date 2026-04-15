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

    // Clientes (nombre, documento, telefono, email, direccion, duenoCapitan)
    (function() {
        var el = document.getElementById('clienteModal');
        if (!el) return;
        var title = document.getElementById('clienteModalTitle');
        var accion = document.getElementById('clienteFormAccion');
        var fid = document.getElementById('clienteFormId');
        var modal = new bootstrap.Modal(el);
        var msg = document.getElementById('clienteModalMensaje');
        var fields = ['nombre','documento','telefono','email','direccion','duenoCapitan'];
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
                fillForm({ nombre: b.getAttribute('data-nombre'), documento: b.getAttribute('data-documento'), telefono: b.getAttribute('data-telefono'), email: b.getAttribute('data-email'), direccion: b.getAttribute('data-direccion'), duenoCapitan: b.getAttribute('data-dueno-capitan') });
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
            var fa = (w.datos.formAccion || 'editar');
            if (fa === 'crear') {
                title.textContent = 'Nuevo cliente'; accion.value = 'crear'; fid.value = '';
            } else {
                title.textContent = 'Editar cliente'; accion.value = 'editar'; fid.value = w.datos.id || '';
            }
            fillForm(w.datos); setErr(w.error || ''); modal.show();
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
            var fa = (w.datos.formAccion || 'editar');
            if (fa === 'crear') {
                title.textContent = 'Nuevo proveedor'; accion.value = 'crear'; fid.value = '';
            } else {
                title.textContent = 'Editar proveedor'; accion.value = 'editar'; fid.value = w.datos.id || '';
            }
            fillForm(w.datos); setErr(w.error || ''); modal.show();
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
        var fields = ['FechaInicio','FechaFin','MontoTotal','Observaciones','NumeroRecibo'];
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        function get(id) { var e = document.getElementById('contrato' + id); return e ? e.value : ''; }
        function set(id, v) { var e = document.getElementById('contrato' + id); if (e) e.value = v !== undefined && v !== null ? v : ''; }
        document.getElementById('btnNuevoContrato') && document.getElementById('btnNuevoContrato').addEventListener('click', function() {
            title.textContent = 'Nuevo contrato'; accion.value = 'crear'; fid.value = '';
            ids.forEach(function(i){ set(i, ''); }); set('FechaInicio', ''); set('FechaFin', ''); set('MontoTotal', ''); set('Observaciones', ''); set('NumeroRecibo', ''); setErr(''); modal.show();
        });
        document.addEventListener('click', function(ev) {
            var bEdit = ev.target && ev.target.closest ? ev.target.closest('.btn-editar-contrato') : null;
            if (bEdit) {
                title.textContent = 'Editar contrato'; accion.value = 'editar';
                fid.value = bEdit.getAttribute('data-id') || '';
                set('ClienteId', bEdit.getAttribute('data-cliente-id')); set('CuentaId', bEdit.getAttribute('data-cuenta-id')); set('MuelleId', bEdit.getAttribute('data-muelle-id')); set('SlipId', bEdit.getAttribute('data-slip-id')); set('GrupoId', bEdit.getAttribute('data-grupo-id')); set('InmuebleId', bEdit.getAttribute('data-inmueble-id'));
                set('FechaInicio', bEdit.getAttribute('data-fecha-inicio')); set('FechaFin', bEdit.getAttribute('data-fecha-fin')); set('MontoTotal', bEdit.getAttribute('data-monto-total')); set('Observaciones', bEdit.getAttribute('data-observaciones')); set('NumeroRecibo', bEdit.getAttribute('data-numero-recibo')); setErr(''); modal.show();
                return;
            }
            var bLib = ev.target && ev.target.closest ? ev.target.closest('.btn-liberar-contrato') : null;
            if (bLib) {
                var libModalEl = document.getElementById('confirmLiberarContratoModal');
                if (!libModalEl) return;
                var hidL = document.getElementById('contratoLiberarId');
                var nomL = document.getElementById('contratoLiberarNombre');
                if (hidL) hidL.value = bLib.getAttribute('data-id') || '';
                if (nomL) nomL.textContent = bLib.getAttribute('data-nombre') || '';
                new bootstrap.Modal(libModalEl).show();
                return;
            }
            var bDel = ev.target && ev.target.closest ? ev.target.closest('.btn-eliminar-contrato') : null;
            if (bDel) {
                var delEl2 = document.getElementById('confirmEliminarContratoModal');
                if (!delEl2) return;
                var hidD = document.getElementById('contratoDeleteId');
                if (hidD) hidD.value = bDel.getAttribute('data-id') || '';
                new bootstrap.Modal(delEl2).show();
            }
        });
        if (window.__contratoModal && window.__contratoModal.mostrar && window.__contratoModal.datos) {
            var w = window.__contratoModal;
            var d = w.datos;
            title.textContent = (d.id && d.id !== '') ? 'Editar contrato' : 'Nuevo contrato'; accion.value = (d.id && d.id !== '') ? 'editar' : 'crear'; fid.value = d.id || '';
            set('ClienteId', d.cliente_id); set('CuentaId', d.cuenta_id); set('MuelleId', d.muelle_id); set('SlipId', d.slip_id); set('GrupoId', d.grupo_id); set('InmuebleId', d.inmueble_id);
            set('FechaInicio', d.fecha_inicio); set('FechaFin', d.fecha_fin); set('MontoTotal', d.monto_total); set('Observaciones', d.observaciones); set('NumeroRecibo', d.numero_recibo); setErr(w.error || ''); modal.show();
        }
    })();

    // Gastos / facturas + abonos
    (function() {
        var el = document.getElementById('gastoModal');
        var elAbono = document.getElementById('gastoAbonoModal');
        if (!el) return;
        var title = document.getElementById('gastoModalTitle');
        var accion = document.getElementById('gastoFormAccion');
        var fid = document.getElementById('gastoFormId');
        var modal = new bootstrap.Modal(el);
        var msg = document.getElementById('gastoModalMensaje');
        function setErr(m) { if (msg) { msg.textContent = m || ''; msg.classList.toggle('d-none', !m); } }
        function set(id, v) { var e = document.getElementById('gasto' + id); if (e) e.value = v !== undefined && v !== null ? v : ''; }
        document.getElementById('btnNuevoGasto') && document.getElementById('btnNuevoGasto').addEventListener('click', function() {
            title.textContent = 'Nueva factura'; accion.value = 'crear'; fid.value = '';
            set('PartidaId',''); set('ProveedorId',''); set('Monto',''); set('FechaGasto', new Date().toISOString().slice(0,10)); set('Referencia',''); set('Observaciones',''); setErr(''); modal.show();
        });
        /* Delegación: DataTables recrea filas y rompe listeners directos en los botones */
        document.addEventListener('click', function(ev) {
            var bEdit = ev.target && ev.target.closest ? ev.target.closest('.btn-editar-gasto') : null;
            if (bEdit) {
                title.textContent = 'Editar factura'; accion.value = 'editar';
                fid.value = bEdit.getAttribute('data-id') || '';
                set('PartidaId', bEdit.getAttribute('data-partida-id')); set('ProveedorId', bEdit.getAttribute('data-proveedor-id'));
                set('Monto', bEdit.getAttribute('data-monto')); set('FechaGasto', bEdit.getAttribute('data-fecha-gasto')); set('Referencia', bEdit.getAttribute('data-referencia')); set('Observaciones', bEdit.getAttribute('data-observaciones')); setErr(''); modal.show();
                return;
            }
        });
        var delEl = document.getElementById('confirmEliminarGastoModal');
        var modalDelGasto = delEl ? new bootstrap.Modal(delEl) : null;
        document.addEventListener('click', function(ev) {
            var bDel = ev.target && ev.target.closest ? ev.target.closest('.btn-eliminar-gasto') : null;
            if (!bDel || !modalDelGasto) return;
            var hid = document.getElementById('gastoDeleteId');
            if (hid) hid.value = bDel.getAttribute('data-id') || '';
            modalDelGasto.show();
        });
        if (window.__gastoModal && window.__gastoModal.mostrar && window.__gastoModal.datos) {
            var w = window.__gastoModal;
            var d = w.datos;
            title.textContent = (d.id && d.id !== '') ? 'Editar factura' : 'Nueva factura'; accion.value = (d.id && d.id !== '') ? 'editar' : 'crear'; fid.value = d.id || '';
            set('PartidaId', d.partida_id); set('ProveedorId', d.proveedor_id);
            set('Monto', d.monto); set('FechaGasto', d.fecha_gasto); set('Referencia', d.referencia); set('Observaciones', d.observaciones); setErr(w.error || ''); modal.show();
        }

        var elVerAbonos = document.getElementById('gastoVerAbonosModal');
        var mVerAbonos = elVerAbonos ? new bootstrap.Modal(elVerAbonos) : null;
        var tbodyVerAbonos = document.getElementById('gastoVerAbonosTbody');
        var tituloVerAbonos = document.getElementById('gastoVerAbonosModalLabel');
        var resumenVerAbonos = document.getElementById('gastoVerAbonosResumen');
        function gastoFmtFechaAbono(ymd) {
            if (!ymd) return '—';
            var p = String(ymd).split('-');
            return p.length === 3 ? (p[2] + '/' + p[1] + '/' + p[0]) : ymd;
        }
        function gastoFmtMontoAbono(n) {
            var x = parseFloat(n);
            if (isNaN(x)) return String(n);
            return x.toLocaleString('es-PA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        document.addEventListener('click', function(ev) {
            var btnV = ev.target && ev.target.closest ? ev.target.closest('.btn-ver-abonos-gasto') : null;
            if (!btnV || !mVerAbonos || !tbodyVerAbonos) return;
            var raw = btnV.getAttribute('data-abonos') || '[]';
            var items;
            try { items = JSON.parse(raw); } catch (e2) { items = []; }
            var fid = btnV.getAttribute('data-factura-id') || '';
            var part = btnV.getAttribute('data-partida') || '';
            var prov = btnV.getAttribute('data-proveedor') || '';
            if (tituloVerAbonos) tituloVerAbonos.textContent = 'Abonos — Factura #' + fid;
            if (resumenVerAbonos) resumenVerAbonos.textContent = part + ' · ' + prov;
            tbodyVerAbonos.textContent = '';
            if (!items.length) {
                var tr0 = document.createElement('tr');
                var td0 = document.createElement('td');
                td0.colSpan = 7;
                td0.className = 'text-muted text-center py-3';
                td0.textContent = 'No hay abonos registrados para esta factura.';
                tr0.appendChild(td0);
                tbodyVerAbonos.appendChild(tr0);
            } else {
                items.forEach(function(row) {
                    var tr = document.createElement('tr');
                    var tdf = document.createElement('td');
                    tdf.textContent = gastoFmtFechaAbono(row.fecha_pago);
                    tr.appendChild(tdf);
                    var tdm = document.createElement('td');
                    tdm.className = 'text-end';
                    tdm.textContent = gastoFmtMontoAbono(row.monto);
                    tr.appendChild(tdm);
                    var tdc = document.createElement('td');
                    tdc.textContent = row.cuenta_nombre || '—';
                    tr.appendChild(tdc);
                    var tdfp = document.createElement('td');
                    tdfp.textContent = row.forma_pago_nombre || '—';
                    tr.appendChild(tdfp);
                    var tdr = document.createElement('td');
                    tdr.textContent = row.referencia || '';
                    tr.appendChild(tdr);
                    var tdo = document.createElement('td');
                    tdo.textContent = row.observaciones || '';
                    tr.appendChild(tdo);
                    var tdAct = document.createElement('td');
                    tdAct.className = 'text-end';
                    if (row.id) {
                        var btnDelAb = document.createElement('button');
                        btnDelAb.type = 'button';
                        btnDelAb.className = 'btn btn-outline-danger btn-sm btn-eliminar-abono-gasto';
                        btnDelAb.setAttribute('data-pago-id', String(row.id));
                        btnDelAb.setAttribute('data-gasto-id', String(fid));
                        btnDelAb.setAttribute('data-fecha-fmt', gastoFmtFechaAbono(row.fecha_pago));
                        btnDelAb.setAttribute('data-monto-fmt', gastoFmtMontoAbono(row.monto));
                        btnDelAb.textContent = 'Eliminar';
                        tdAct.appendChild(btnDelAb);
                    }
                    tr.appendChild(tdAct);
                    tbodyVerAbonos.appendChild(tr);
                });
            }
            mVerAbonos.show();
        });

        var elDelAbono = document.getElementById('confirmEliminarAbonoGastoModal');
        var mDelAbono = elDelAbono ? new bootstrap.Modal(elDelAbono) : null;
        document.addEventListener('click', function(ev) {
            var bDelAb = ev.target && ev.target.closest ? ev.target.closest('.btn-eliminar-abono-gasto') : null;
            if (!bDelAb || !mDelAbono) return;
            var hidP = document.getElementById('gastoAbonoDeletePagoId');
            var hidG = document.getElementById('gastoAbonoDeleteGastoId');
            var txtDel = document.getElementById('gastoAbonoDeleteTexto');
            if (hidP) hidP.value = bDelAb.getAttribute('data-pago-id') || '';
            if (hidG) hidG.value = bDelAb.getAttribute('data-gasto-id') || '';
            if (txtDel) {
                txtDel.textContent = '¿Eliminar el abono del ' + (bDelAb.getAttribute('data-fecha-fmt') || '') + ' por ' + (bDelAb.getAttribute('data-monto-fmt') || '') + '?';
            }
            if (elVerAbonos) {
                var instVer = bootstrap.Modal.getInstance(elVerAbonos);
                if (instVer) instVer.hide();
            }
            mDelAbono.show();
        });

        if (elAbono) {
            var mAbono = new bootstrap.Modal(elAbono);
            var msgAb = document.getElementById('gastoAbonoModalMensaje');
            function setErrAb(m) { if (msgAb) { msgAb.textContent = m || ''; msgAb.classList.toggle('d-none', !m); } }
            function setv(id, v) { var e = document.getElementById(id); if (e) e.value = v !== undefined && v !== null ? v : ''; }
            document.addEventListener('click', function(ev) {
                var bAb = ev.target && ev.target.closest ? ev.target.closest('.btn-abonar-gasto') : null;
                if (!bAb) return;
                setErrAb('');
                setv('gastoAbonoGastoId', bAb.getAttribute('data-id') || '');
                var pend = parseFloat((bAb.getAttribute('data-pendiente') || '0').replace(',', '.')) || 0;
                var part = bAb.getAttribute('data-partida') || '';
                var prov = bAb.getAttribute('data-proveedor') || '';
                var rs = document.getElementById('gastoAbonoResumen');
                var pt = document.getElementById('gastoAbonoPendienteTexto');
                if (rs) rs.textContent = part + ' — ' + prov;
                if (pt) pt.textContent = 'Saldo pendiente máximo: ' + pend.toFixed(2).replace('.', ',');
                setv('gastoAbonoMonto', '');
                setv('gastoAbonoFechaPago', new Date().toISOString().slice(0, 10));
                setv('gastoAbonoCuentaId', '');
                setv('gastoAbonoFormaPagoId', '');
                setv('gastoAbonoReferencia', '');
                setv('gastoAbonoObservaciones', '');
                mAbono.show();
            });
            if (window.__gastoAbonoModal && window.__gastoAbonoModal.mostrar && window.__gastoAbonoModal.datos) {
                var wa = window.__gastoAbonoModal;
                var da = wa.datos;
                setv('gastoAbonoGastoId', da.gasto_id || '');
                setv('gastoAbonoMonto', da.monto_abono || '');
                setv('gastoAbonoFechaPago', da.fecha_pago || '');
                setv('gastoAbonoCuentaId', da.cuenta_id || '');
                setv('gastoAbonoFormaPagoId', da.forma_pago_id || '');
                setv('gastoAbonoReferencia', da.referencia_abono || '');
                setv('gastoAbonoObservaciones', da.observaciones_abono || '');
                setErrAb(wa.error || '');
                mAbono.show();
            }
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
        function escHtmlMovs(s) {
            if (s == null || String(s).trim() === '') return '—';
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
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
            var pagarConcepto = document.getElementById('pagarConceptoMovimiento');
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
                    if (pagarConcepto) pagarConcepto.value = '';
                    if (pagarRef) {
                        var nr = (typeof window.__contratoNumeroRecibo === 'string') ? window.__contratoNumeroRecibo : '';
                        pagarRef.value = nr || '';
                    }
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
                if (pagarConcepto && w.datos) pagarConcepto.value = w.datos.concepto_movimiento || '';
                if (pagarRef && w.datos) pagarRef.value = (w.datos.referencia_pago !== undefined && w.datos.referencia_pago !== null) ? w.datos.referencia_pago : '';
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
            var abonarConcepto = document.getElementById('abonarConceptoMovimiento');
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
                    if (abonarConcepto) abonarConcepto.value = '';
                    if (abonarRef) {
                        var nr2 = (typeof window.__contratoNumeroRecibo === 'string') ? window.__contratoNumeroRecibo : '';
                        abonarRef.value = nr2 || '';
                    }
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
                if (abonarConcepto && w.datos) abonarConcepto.value = w.datos.concepto_movimiento || '';
                if (abonarRef && w.datos) abonarRef.value = (w.datos.referencia_pago !== undefined && w.datos.referencia_pago !== null) ? w.datos.referencia_pago : '';
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
                    tablaBody.innerHTML = '<tr><td class="text-muted" colspan="5">No hay datos.</td></tr>';
                    return;
                }
                var tipo = (tipoFiltro && tipoFiltro.value) ? tipoFiltro.value : 'todos';
                var movimientos = data.movimientos || [];
                if (tipo !== 'todos') movimientos = movimientos.filter(function(m) { return (m.tipo || '') === tipo; });

                if (!movimientos.length) {
                    tablaBody.innerHTML = '<tr><td class="text-muted" colspan="5">No hay movimientos.</td></tr>';
                    return;
                }

                var rows = movimientos.map(function(m) {
                    var fecha = m.fecha_pago ? escHtmlMovs(m.fecha_pago) : '—';
                    var monto = (m.monto !== undefined && m.monto !== null) ? formatMoney(m.monto) : '—';
                    var concepto = escHtmlMovs(m.concepto);
                    var forma = escHtmlMovs(m.forma_pago_nombre);
                    var ref = escHtmlMovs(m.referencia);
                    return '<tr>' +
                        '<td>' + fecha + '</td>' +
                        '<td class="text-end">' + monto + '</td>' +
                        '<td>' + concepto + '</td>' +
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

    // --- Electricidad por contrato (p=contratos-electricidad): modales tras Bootstrap; delegación por si DataTables recrea filas
    (function() {
        var mAbono = document.getElementById('modalAbonoEle');
        var mVer = document.getElementById('modalVerAbonosEle');
        if (!mAbono || typeof bootstrap === 'undefined') return;
        var bsAbono = bootstrap.Modal.getOrCreateInstance(mAbono);
        var bsVer = mVer ? bootstrap.Modal.getOrCreateInstance(mVer) : null;
        function fmtEle(n) {
            var x = parseFloat(String(n).replace(',', '.'));
            if (isNaN(x)) return String(n);
            return x.toLocaleString('es-PA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        document.addEventListener('click', function(ev) {
            var bVer = ev.target && ev.target.closest ? ev.target.closest('.btn-ver-abonos-ele') : null;
            if (bVer) {
                var raw = bVer.getAttribute('data-abonos') || '[]';
                var list = [];
                try { list = JSON.parse(raw); } catch (e2) { list = []; }
                var res = document.getElementById('eleVerResumen');
                if (res) res.textContent = bVer.getAttribute('data-resumen') || '';
                var tb = document.getElementById('eleVerAbonosTbody');
                if (tb) {
                    if (!list.length) {
                        tb.innerHTML = '<tr><td colspan="6" class="text-muted">Sin pagos registrados.</td></tr>';
                    } else {
                        tb.innerHTML = list.map(function(a) {
                            return '<tr><td>' + (a.fecha_pago || '') + '</td><td class="text-end">' + fmtEle(a.monto) + '</td><td>' +
                                (a.cuenta_nombre || '') + '</td><td>' + (a.forma_pago_nombre || '') + '</td><td>' +
                                (a.referencia || '') + '</td><td>' + (a.observaciones || '') + '</td></tr>';
                        }).join('');
                    }
                }
                if (bsVer) bsVer.show();
                return;
            }
            var bAbo = ev.target && ev.target.closest ? ev.target.closest('.btn-abonar-ele') : null;
            if (bAbo) {
                var fidA = document.getElementById('eleFacturaId');
                if (fidA) fidA.value = bAbo.getAttribute('data-factura-id') || '';
                var tA = document.getElementById('eleAbonoTitle');
                if (tA) tA.textContent = 'Registrar abono';
                var rA = document.getElementById('eleAbonoResumen');
                if (rA) rA.textContent = bAbo.getAttribute('data-label') || '';
                var pA = document.getElementById('eleAbonoPendiente');
                var pendA = bAbo.getAttribute('data-pendiente') || '0';
                if (pA) pA.textContent = 'Saldo pendiente: ' + fmtEle(pendA);
                var mA = document.getElementById('eleMontoPago');
                if (mA) { mA.value = ''; mA.readOnly = false; }
                bsAbono.show();
                return;
            }
            var bTot = ev.target && ev.target.closest ? ev.target.closest('.btn-pago-total-ele') : null;
            if (bTot) {
                var fidT = document.getElementById('eleFacturaId');
                if (fidT) fidT.value = bTot.getAttribute('data-factura-id') || '';
                var tT = document.getElementById('eleAbonoTitle');
                if (tT) tT.textContent = 'Pago total';
                var rT = document.getElementById('eleAbonoResumen');
                if (rT) rT.textContent = bTot.getAttribute('data-label') || '';
                var pT = document.getElementById('eleAbonoPendiente');
                var pendT = bTot.getAttribute('data-pendiente') || '0';
                if (pT) pT.textContent = 'Se registrará el saldo completo: ' + fmtEle(pendT);
                var mT = document.getElementById('eleMontoPago');
                if (mT) { mT.value = pendT; mT.readOnly = true; }
                bsAbono.show();
            }
        });
        mAbono.addEventListener('hidden.bs.modal', function() {
            var el = document.getElementById('eleMontoPago');
            if (el) el.readOnly = false;
        });
    })();

});
