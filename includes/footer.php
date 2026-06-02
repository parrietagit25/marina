</main>
<footer class='footer text-center text-muted py-3'>
  Marina &copy; <?= date('Y') ?>
</footer>
  </div><!-- .main-area -->
</div><!-- .app-shell -->
<?php
require_once __DIR__ . '/permisos.php';
$permJs = ['puedeEditar' => true, 'puedeEliminar' => true, 'accesoTotal' => true];
if (function_exists('marina_permisos_sesion')) {
    $ps = marina_permisos_sesion();
    $permJs = [
        'puedeEditar' => marina_permiso_puede_editar(),
        'puedeEliminar' => marina_permiso_puede_eliminar(),
        'accesoTotal' => !empty($ps['acceso_total']),
    ];
}
?>
<script>
window.__marinaPerm = <?= json_encode($permJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.marinaFmtFecha = function(fecha) {
  if (!fecha) return '';
  var s = String(fecha).slice(0, 10);
  var p = s.split('-');
  if (p.length !== 3) return s;
  var y = p[0].length >= 4 ? p[0].slice(-2) : p[0];
  return p[2] + '/' + p[1] + '/' + y;
};
</script>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js' crossorigin='anonymous'></script>
<script src='https://code.jquery.com/jquery-3.7.1.min.js'></script>
<script src='https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js'></script>
<script src='https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js'></script>
<script src='https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js'></script>
<script src='https://unpkg.com/lucide@latest'></script>
<script src='<?= MARINA_URL ?>/assets/js/marina-excel-export.js'></script>
<script src='<?= MARINA_URL ?>/assets/js/app.js'></script>
<script src='<?= MARINA_URL ?>/assets/js/contrato-estado-cuenta.js'></script>
<script>
(function() {
  function initDataTablesGlobal() {
    try {
      if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.DataTable) return;
      var $ = window.jQuery;
      $('table').each(function() {
        var $table = $(this);
        if ($table.hasClass('no-datatable')) return;
        if ($table.attr('data-dt-ready') === '1') return;
        /* TN/18: DataTables no admite filas con colspan en tbody (p. ej. “sin datos”). */
        if ($table.find('tbody td[colspan], tbody th[colspan]').filter(function() {
          return (parseInt($(this).attr('colspan'), 10) || 1) > 1;
        }).length) {
          return;
        }
        if ($.fn.dataTable.isDataTable(this)) {
          $table.attr('data-dt-ready', '1');
          return;
        }
        if (!$table.parent().hasClass('table-responsive')) {
          $table.wrap('<div class="table-responsive"></div>');
        }
        $table.addClass('table table-hover align-middle w-100');
        $table.DataTable({
          pageLength: 10,
          lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
          order: [],
          pagingType: 'simple_numbers',
          autoWidth: false,
          dom: "<'row g-2 align-items-center mb-2'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
               "rt" +
               "<'row g-2 align-items-center mt-2'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
          language: {
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Mostrando 0 a 0 de 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros)',
            zeroRecords: 'No se encontraron registros',
            paginate: {
              first: 'Primero',
              last: 'Último',
              next: 'Siguiente',
              previous: 'Anterior'
            }
          }
        });
        if ($table.attr('data-dt-restore-page') === '1') {
          var dtPageParam = new URLSearchParams(window.location.search).get('dt_page');
          if (dtPageParam !== null && dtPageParam !== '') {
            var pg = parseInt(dtPageParam, 10);
            if (!isNaN(pg) && pg >= 0) {
              $table.DataTable().page(pg).draw('page');
            }
          }
        }
        $table.attr('data-dt-ready', '1');
      });
    } catch (e) {
      // Silencio para no romper la UI en producción.
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDataTablesGlobal);
  } else {
    initDataTablesGlobal();
  }
  window.addEventListener('load', initDataTablesGlobal);
  setTimeout(initDataTablesGlobal, 250);
  setTimeout(function() {
    if (typeof window.marinaInitExcelExport === 'function') {
      window.marinaInitExcelExport();
    }
  }, 300);
  setTimeout(function() {
    if (typeof window.marinaInitExcelExport === 'function') {
      window.marinaInitExcelExport();
    }
  }, 1300);
})();
if (window.lucide) {
  window.lucide.createIcons();
}
(function() {
  var perm = window.__marinaPerm || {};
  if (perm.accesoTotal) return;
  function ocultar(sel) {
    document.querySelectorAll(sel).forEach(function(el) {
      el.style.display = 'none';
      el.setAttribute('aria-hidden', 'true');
    });
  }
  if (!perm.puedeEliminar) {
    ocultar('[class*="btn-eliminar"], button[name="eliminar"], a.btn-eliminar, .btn-borrar');
    document.querySelectorAll('form').forEach(function(f) {
      var acc = f.querySelector('input[name="accion"]');
      if (acc && acc.value === 'eliminar') f.style.display = 'none';
    });
  }
  if (!perm.puedeEditar) {
    ocultar('[class*="btn-editar"], [id^="btnNuevo"], [id^="btnEditar"], .btn-nuevo-registro');
    document.querySelectorAll('button[type="submit"]').forEach(function(btn) {
      var t = (btn.textContent || '').toLowerCase();
      if (t.indexOf('guardar') >= 0 && btn.closest('form') && btn.closest('form').id !== 'usuarioRolForm') {
        var fid = btn.closest('form').id || '';
        if (fid.indexOf('Delete') < 0 && fid.indexOf('Eliminar') < 0) btn.style.display = 'none';
      }
    });
  }
})();
</script>
</body>
</html>

