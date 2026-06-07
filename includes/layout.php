<?php
/**
 * Layout: Bootstrap 5 + sidebar izquierda responsive
 */
$usuario = usuarioActual();
$p = trim($_GET['p'] ?? 'dashboard');
$p = preg_replace('/[^a-z0-9_-]/', '', $p) ?: 'dashboard';
$nombre_usuario = e($usuario['nombre'] ?? '');

$marinaFontSizePct = 100;
if (function_exists('getDb')) {
    try {
        $marinaFontSizePct = marina_config_font_size_percent(getDb());
    } catch (Throwable $e) {
        $marinaFontSizePct = 100;
    }
}

require_once __DIR__ . '/menu_sidebar.php';
?>
<!DOCTYPE html>
<html lang='es' style='font-size: <?= (int) $marinaFontSizePct ?>%;'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title><?= isset($titulo) ? e($titulo) . ' - ' : '' ?>Marina</title>

  <link rel='preconnect' href='https://fonts.googleapis.com'>
  <link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
  <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap' rel='stylesheet'>
  <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet' crossorigin='anonymous'>
  <link href='https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css' rel='stylesheet'>
  <link rel='stylesheet' href='<?= MARINA_URL ?>/assets/css/estilos.css'>
  <?php if (!empty($marinaHeadExtra)) { echo $marinaHeadExtra; } ?>
</head>
<body>

<div class='app-shell'>

  <aside class='sidebar d-none d-md-flex'>
    <div class='sidebar-brand'>
      <a class='sidebar-logo' href='<?= MARINA_URL ?>/index.php'>Marina</a>
    </div>

    <div class='sidebar-menu list-group list-group-flush'>
      <?php marina_render_sidebar_menu($p, '', 'Desktop'); ?>
    </div>

    <div class='sidebar-footer'>
      <div class='small text-white-50'>Usuario</div>
      <div class='sidebar-user'><?= $nombre_usuario ?></div>
      <a class='sidebar-exit' href='<?= MARINA_URL ?>/index.php?p=logout'>Salir</a>
    </div>
  </aside>

  <div class='main-area'>

    <header class='topbar d-flex d-md-none align-items-center px-2'>
      <div class='fw-semibold text-white ms-2'>Marina</div>
      <div class='ms-auto me-2 d-flex align-items-center gap-2'>
        <button class='btn btn-sm btn-light' type='button' data-bs-toggle='offcanvas' data-bs-target='#sidebarOffcanvas'>Menu</button>
        <a class='btn btn-sm btn-outline-light' href='<?= MARINA_URL ?>/index.php?p=logout'>Salir</a>
      </div>
    </header>

    <div class='offcanvas offcanvas-start' tabindex='-1' id='sidebarOffcanvas' aria-labelledby='sidebarOffcanvasLabel'>
      <div class='offcanvas-header'>
        <h5 class='offcanvas-title' id='sidebarOffcanvasLabel'>Marina</h5>
        <button type='button' class='btn-close' data-bs-dismiss='offcanvas' aria-label='Cerrar'></button>
      </div>
      <div class='offcanvas-body p-0'>
        <aside class='sidebar mobile-sidebar'>
          <div class='sidebar-brand'>
            <div class='small text-white-50'>Usuario</div>
            <div class='sidebar-user'><?= $nombre_usuario ?></div>
          </div>
          <div class='sidebar-menu list-group list-group-flush'>
            <?php marina_render_sidebar_menu($p, '', 'Mobile'); ?>
          </div>
          <div class='p-3'>
            <a class='btn btn-danger w-100' href='<?= MARINA_URL ?>/index.php?p=logout'>Salir</a>
          </div>
        </aside>
      </div>
    </div>

    <main class='content-main container-fluid py-3 py-md-4'>

