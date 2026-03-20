<?php
/**
 * Layout: Bootstrap 5 + sidebar izquierda responsive
 */
$usuario = usuarioActual();
$p = trim($_GET['p'] ?? 'dashboard');
$p = preg_replace('/[^a-z0-9_-]/', '', $p) ?: 'dashboard';
$nombre_usuario = e($usuario['nombre'] ?? '');

$seccionMantenimiento = in_array($p, ['usuarios', 'bancos', 'cuentas'], true);
$seccionTransacciones = in_array($p, ['formas-pago', 'movimiento-bancario', 'partidas', 'proveedores', 'gastos', 'reportes'], true);
$seccionMarina = in_array($p, ['clientes', 'muelles', 'slips', 'grupos', 'inmuebles', 'mapa-marina', 'mapa-grupos', 'contratos'], true);
$seccionReportes = in_array($p, ['reporte-cuotas', 'reporte-proveedores-estado-cuenta', 'reporte-estado-cuenta-bancarias', 'reporte-ingresos', 'reporte-egresos', 'reporte-ingresos-egresos', 'reporte-marina-contratos', 'reporte-inmuebles-contratos'], true);
?>
<!DOCTYPE html>
<html lang='es'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title><?= isset($titulo) ? e($titulo) . ' - ' : '' ?>Marina</title>

  <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet' crossorigin='anonymous'>
  <link href='https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css' rel='stylesheet'>
  <link rel='stylesheet' href='<?= MARINA_URL ?>/assets/css/estilos.css'>
</head>
<body>

<div class='app-shell'>

  <aside class='sidebar d-none d-md-flex'>
    <div class='sidebar-brand'>
      <a class='sidebar-logo' href='<?= MARINA_URL ?>/index.php'>Marina</a>
    </div>

    <div class='sidebar-menu list-group list-group-flush'>
      <a class='list-group-item list-group-item-action <?= ($p === 'dashboard') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php'>Inicio</a>

      <div class="sidebar-accordion mt-2" id="sidebarAccordionDesktop">
        <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuMantDesktop" aria-expanded="<?= $seccionMantenimiento ? 'true' : 'false' ?>">
          Mantenimiento
        </button>
        <div id="menuMantDesktop" class="collapse <?= $seccionMantenimiento ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionDesktop">
          <a class='list-group-item list-group-item-action <?= ($p === 'usuarios') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=usuarios'>Usuarios</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'bancos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=bancos'>Bancos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'cuentas') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=cuentas'>Cuentas</a>
        </div>

        <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuTransDesktop" aria-expanded="<?= $seccionTransacciones ? 'true' : 'false' ?>">
          Transacciones
        </button>
        <div id="menuTransDesktop" class="collapse <?= $seccionTransacciones ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionDesktop">
          <a class='list-group-item list-group-item-action <?= ($p === 'formas-pago') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=formas-pago'>Tipo de movimientos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'movimiento-bancario') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=movimiento-bancario'>Movimiento bancario</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'partidas') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=partidas'>Partidas</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'proveedores') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=proveedores'>Proveedores</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'gastos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=gastos'>Gastos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reportes') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reportes'>Ingresos / Costos</a>
        </div>

        <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuMarinaDesktop" aria-expanded="<?= $seccionMarina ? 'true' : 'false' ?>">
          Marina
        </button>
        <div id="menuMarinaDesktop" class="collapse <?= $seccionMarina ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionDesktop">
          <a class='list-group-item list-group-item-action <?= ($p === 'clientes') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=clientes'>Clientes</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'muelles') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=muelles'>Muelles</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'slips') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=slips'>Slips</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'grupos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=grupos'>Grupos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'inmuebles') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=inmuebles'>Inmuebles</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'mapa-marina') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=mapa-marina'>Mapa Marina</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'mapa-grupos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=mapa-grupos'>Mapa Grupos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'contratos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=contratos'>Contratos</a>
        </div>

        <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuRepDesktop" aria-expanded="<?= $seccionReportes ? 'true' : 'false' ?>">
          Reportes
        </button>
        <div id="menuRepDesktop" class="collapse <?= $seccionReportes ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionDesktop">
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-cuotas') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-cuotas'>Cuotas</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-proveedores-estado-cuenta') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-proveedores-estado-cuenta'>Proveedores, estado de cuenta</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-estado-cuenta-bancarias') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-estado-cuenta-bancarias'>Estado de cuenta Bancarias</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-ingresos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-ingresos'>Ingreso</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-egresos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-egresos'>Egresos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-ingresos-egresos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-ingresos-egresos'>Ingresos / Egresos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-marina-contratos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-marina-contratos'>Reporte Marina -> contratos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-inmuebles-contratos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-inmuebles-contratos'>Reporte Inmuebles -> contratos</a>
        </div>
      </div>

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
            <a class='list-group-item list-group-item-action <?= ($p === 'dashboard') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php'>Inicio</a>
            <div class="sidebar-accordion mt-2" id="sidebarAccordionMobile">
              <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuMantMobile" aria-expanded="<?= $seccionMantenimiento ? 'true' : 'false' ?>">
                Mantenimiento
              </button>
              <div id="menuMantMobile" class="collapse <?= $seccionMantenimiento ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionMobile">
                <a class='list-group-item list-group-item-action <?= ($p === 'usuarios') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=usuarios'>Usuarios</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'bancos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=bancos'>Bancos</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'cuentas') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=cuentas'>Cuentas</a>
              </div>

              <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuTransMobile" aria-expanded="<?= $seccionTransacciones ? 'true' : 'false' ?>">
                Transacciones
              </button>
              <div id="menuTransMobile" class="collapse <?= $seccionTransacciones ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionMobile">
                <a class='list-group-item list-group-item-action <?= ($p === 'formas-pago') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=formas-pago'>Tipo de movimientos</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'movimiento-bancario') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=movimiento-bancario'>Movimiento bancario</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'partidas') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=partidas'>Partidas</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'proveedores') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=proveedores'>Proveedores</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'gastos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=gastos'>Gastos</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reportes') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reportes'>Ingresos / Costos</a>
              </div>

              <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuMarinaMobile" aria-expanded="<?= $seccionMarina ? 'true' : 'false' ?>">
                Marina
              </button>
              <div id="menuMarinaMobile" class="collapse <?= $seccionMarina ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionMobile">
                <a class='list-group-item list-group-item-action <?= ($p === 'clientes') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=clientes'>Clientes</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'muelles') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=muelles'>Muelles</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'slips') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=slips'>Slips</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'grupos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=grupos'>Grupos</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'inmuebles') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=inmuebles'>Inmuebles</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'mapa-marina') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=mapa-marina'>Mapa Marina</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'mapa-grupos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=mapa-grupos'>Mapa Grupos</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'contratos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=contratos'>Contratos</a>
              </div>

              <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuRepMobile" aria-expanded="<?= $seccionReportes ? 'true' : 'false' ?>">
                Reportes
              </button>
              <div id="menuRepMobile" class="collapse <?= $seccionReportes ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionMobile">
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-cuotas') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-cuotas'>Cuotas</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-proveedores-estado-cuenta') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-proveedores-estado-cuenta'>Proveedores, estado de cuenta</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-estado-cuenta-bancarias') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-estado-cuenta-bancarias'>Estado de cuenta Bancarias</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-ingresos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-ingresos'>Ingreso</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-egresos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-egresos'>Egresos</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-ingresos-egresos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-ingresos-egresos'>Ingresos / Egresos</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-marina-contratos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-marina-contratos'>Reporte Marina -> contratos</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-inmuebles-contratos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-inmuebles-contratos'>Reporte Inmuebles -> contratos</a>
              </div>
            </div>

          </div>
          <div class='p-3'>
            <a class='btn btn-danger w-100' href='<?= MARINA_URL ?>/index.php?p=logout'>Salir</a>
          </div>
        </aside>
      </div>
    </div>

    <main class='content-main container-fluid py-3 py-md-4'>

