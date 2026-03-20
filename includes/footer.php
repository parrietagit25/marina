</main>
<footer class='footer text-center text-muted py-3'>
  Marina &copy; <?= date('Y') ?>
</footer>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js' crossorigin='anonymous'></script>
<script src='https://code.jquery.com/jquery-3.7.1.min.js'></script>
<script src='https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js'></script>
<script src='https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js'></script>
<script src='<?= MARINA_URL ?>/assets/js/app.js'></script>
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
})();
</script>
</body>
</html>

