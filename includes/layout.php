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

$seccionMantenimiento = in_array($p, ['usuarios', 'bancos', 'cuentas', 'configuracion'], true);
$seccionBanco = in_array($p, ['movimiento-bancario', 'reporte-estado-cuenta-bancarias', 'saldos-cuentas-bancarias'], true);
$seccionCostoGastos = in_array($p, ['proveedores', 'gastos', 'reporte-proveedores-estado-cuenta'], true);
$seccionTransacciones = in_array($p, ['formas-pago', 'partidas', 'reportes'], true);
$paginasCombustible = ['combustible-pedidos', 'combustible-despacho', 'combustible-ajuste', 'combustible-precios'];
$seccionCombustibleSub = in_array($p, $paginasCombustible, true);
$seccionMarina = in_array($p, array_merge(['clientes', 'muelles', 'slips', 'grupos', 'inmuebles', 'mapa-marina', 'mapa-grupos', 'contratos'], $paginasCombustible), true);
$seccionReportes = in_array($p, ['reporte-cuotas', 'reporte-ingresos', 'reporte-egresos', 'reporte-ingresos-egresos', 'reporte-marina-contratos', 'reporte-inmuebles-contratos', 'reporte-combustible'], true);
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
</head>
<body>

<div class='app-shell'>

  <aside class='sidebar d-none d-md-flex'>
    <div class='sidebar-brand'>
      <a class='sidebar-logo' href='<?= MARINA_URL ?>/index.php'>Marina</a>
    </div>

    <div class='sidebar-menu list-group list-group-flush'>
      <a class='list-group-item list-group-item-action <?= ($p === 'dashboard') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php'><i data-lucide='layout-dashboard' class='menu-ico'></i>Inicio</a>

      <div class="sidebar-accordion mt-2" id="sidebarAccordionDesktop">
        <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuMantDesktop" aria-expanded="<?= $seccionMantenimiento ? 'true' : 'false' ?>">
          Mantenimiento
        </button>
        <div id="menuMantDesktop" class="collapse <?= $seccionMantenimiento ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionDesktop">
          <a class='list-group-item list-group-item-action <?= ($p === 'usuarios') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=usuarios'><i data-lucide='users' class='menu-ico'></i>Usuarios</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'bancos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=bancos'><i data-lucide='landmark' class='menu-ico'></i>Bancos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'cuentas') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=cuentas'><i data-lucide='wallet-cards' class='menu-ico'></i>Cuentas</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'configuracion') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=configuracion'><i data-lucide='type' class='menu-ico'></i>Configuración</a>
        </div>

        <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuBancoDesktop" aria-expanded="<?= $seccionBanco ? 'true' : 'false' ?>">
          Banco
        </button>
        <div id="menuBancoDesktop" class="collapse <?= $seccionBanco ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionDesktop">
          <a class='list-group-item list-group-item-action <?= ($p === 'movimiento-bancario') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=movimiento-bancario'><i data-lucide='banknote' class='menu-ico'></i>Registrar movimientos bancarios</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-estado-cuenta-bancarias') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-estado-cuenta-bancarias'><i data-lucide='file-text' class='menu-ico'></i>Estado de cuenta bancaria</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'saldos-cuentas-bancarias') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=saldos-cuentas-bancarias'><i data-lucide='landmark' class='menu-ico'></i>Saldos de cuentas bancarias</a>
        </div>

        <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuCostoDesktop" aria-expanded="<?= $seccionCostoGastos ? 'true' : 'false' ?>">
          Costo o Gastos
        </button>
        <div id="menuCostoDesktop" class="collapse <?= $seccionCostoGastos ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionDesktop">
          <a class='list-group-item list-group-item-action <?= ($p === 'proveedores') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=proveedores'><i data-lucide='truck' class='menu-ico'></i>Proveedores</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'gastos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=gastos'><i data-lucide='receipt' class='menu-ico'></i>Factura / Pagar</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-proveedores-estado-cuenta') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-proveedores-estado-cuenta'><i data-lucide='file-text' class='menu-ico'></i>Estado de cuenta proveedor</a>
        </div>

        <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuTransDesktop" aria-expanded="<?= $seccionTransacciones ? 'true' : 'false' ?>">
          Transacciones
        </button>
        <div id="menuTransDesktop" class="collapse <?= $seccionTransacciones ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionDesktop">
          <a class='list-group-item list-group-item-action <?= ($p === 'formas-pago') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=formas-pago'><i data-lucide='arrow-right-left' class='menu-ico'></i>Tipo de movimientos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'partidas') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=partidas'><i data-lucide='network' class='menu-ico'></i>Partidas</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reportes') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reportes'><i data-lucide='bar-chart-3' class='menu-ico'></i>Ingresos / Costos</a>
        </div>

        <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuMarinaDesktop" aria-expanded="<?= $seccionMarina ? 'true' : 'false' ?>">
          Marina
        </button>
        <div id="menuMarinaDesktop" class="collapse <?= $seccionMarina ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionDesktop">
          <a class='list-group-item list-group-item-action <?= ($p === 'clientes') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=clientes'><i data-lucide='user-round' class='menu-ico'></i>Clientes</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'muelles') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=muelles'>Muelles</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'slips') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=slips'>Slips</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'grupos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=grupos'>Grupos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'inmuebles') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=inmuebles'>Inmuebles</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'mapa-marina') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=mapa-marina'><i data-lucide='anchor' class='menu-ico'></i>Mapa Marina</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'mapa-grupos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=mapa-grupos'><i data-lucide='building-2' class='menu-ico'></i>Mapa Grupos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'contratos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=contratos'>Contratos</a>
          <button class="menu-section-toggle menu-sub-toggle ps-3 py-2" type="button" data-bs-toggle="collapse" data-bs-target="#menuCombustibleSubDesktop" aria-expanded="<?= $seccionCombustibleSub ? 'true' : 'false' ?>">
            Combustible
          </button>
          <div id="menuCombustibleSubDesktop" class="collapse <?= $seccionCombustibleSub ? 'show' : '' ?>">
            <a class='list-group-item list-group-item-action ps-4 <?= ($p === 'combustible-pedidos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=combustible-pedidos'>Pedidos</a>
            <a class='list-group-item list-group-item-action ps-4 <?= ($p === 'combustible-despacho') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=combustible-despacho'>Despacho</a>
            <a class='list-group-item list-group-item-action ps-4 <?= ($p === 'combustible-ajuste') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=combustible-ajuste'>Ajuste</a>
            <a class='list-group-item list-group-item-action ps-4 <?= ($p === 'combustible-precios') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=combustible-precios'>Precio x galón</a>
          </div>
        </div>

        <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuRepDesktop" aria-expanded="<?= $seccionReportes ? 'true' : 'false' ?>">
          Reportes
        </button>
        <div id="menuRepDesktop" class="collapse <?= $seccionReportes ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionDesktop">
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-cuotas') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-cuotas'>Cuotas</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-ingresos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-ingresos'>Ingreso</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-egresos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-egresos'>Egresos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-ingresos-egresos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-ingresos-egresos'>Ingresos / Egresos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-marina-contratos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-marina-contratos'>Reporte Marina -> contratos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-inmuebles-contratos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-inmuebles-contratos'>Reporte Inmuebles -> contratos</a>
          <a class='list-group-item list-group-item-action <?= ($p === 'reporte-combustible') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-combustible'>Combustible</a>
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
            <a class='list-group-item list-group-item-action <?= ($p === 'dashboard') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php'><i data-lucide='layout-dashboard' class='menu-ico'></i>Inicio</a>
            <div class="sidebar-accordion mt-2" id="sidebarAccordionMobile">
              <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuMantMobile" aria-expanded="<?= $seccionMantenimiento ? 'true' : 'false' ?>">
                Mantenimiento
              </button>
              <div id="menuMantMobile" class="collapse <?= $seccionMantenimiento ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionMobile">
                <a class='list-group-item list-group-item-action <?= ($p === 'usuarios') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=usuarios'>Usuarios</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'bancos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=bancos'>Bancos</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'cuentas') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=cuentas'>Cuentas</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'configuracion') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=configuracion'>Configuración</a>
              </div>

              <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuBancoMobile" aria-expanded="<?= $seccionBanco ? 'true' : 'false' ?>">
                Banco
              </button>
              <div id="menuBancoMobile" class="collapse <?= $seccionBanco ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionMobile">
                <a class='list-group-item list-group-item-action <?= ($p === 'movimiento-bancario') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=movimiento-bancario'>Registrar movimientos bancarios</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-estado-cuenta-bancarias') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-estado-cuenta-bancarias'>Estado de cuenta bancaria</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'saldos-cuentas-bancarias') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=saldos-cuentas-bancarias'>Saldos de cuentas bancarias</a>
              </div>

              <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuCostoMobile" aria-expanded="<?= $seccionCostoGastos ? 'true' : 'false' ?>">
                Costo o Gastos
              </button>
              <div id="menuCostoMobile" class="collapse <?= $seccionCostoGastos ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionMobile">
                <a class='list-group-item list-group-item-action <?= ($p === 'proveedores') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=proveedores'>Proveedores</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'gastos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=gastos'>Factura / Pagar</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-proveedores-estado-cuenta') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-proveedores-estado-cuenta'>Estado de cuenta proveedor</a>
              </div>

              <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuTransMobile" aria-expanded="<?= $seccionTransacciones ? 'true' : 'false' ?>">
                Transacciones
              </button>
              <div id="menuTransMobile" class="collapse <?= $seccionTransacciones ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionMobile">
                <a class='list-group-item list-group-item-action <?= ($p === 'formas-pago') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=formas-pago'>Tipo de movimientos</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'partidas') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=partidas'>Partidas</a>
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
                <button class="menu-section-toggle menu-sub-toggle ps-3 py-2" type="button" data-bs-toggle="collapse" data-bs-target="#menuCombustibleSubMobile" aria-expanded="<?= $seccionCombustibleSub ? 'true' : 'false' ?>">
                  Combustible
                </button>
                <div id="menuCombustibleSubMobile" class="collapse <?= $seccionCombustibleSub ? 'show' : '' ?>">
                  <a class='list-group-item list-group-item-action ps-4 <?= ($p === 'combustible-pedidos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=combustible-pedidos'>Pedidos</a>
                  <a class='list-group-item list-group-item-action ps-4 <?= ($p === 'combustible-despacho') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=combustible-despacho'>Despacho</a>
                  <a class='list-group-item list-group-item-action ps-4 <?= ($p === 'combustible-ajuste') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=combustible-ajuste'>Ajuste</a>
                  <a class='list-group-item list-group-item-action ps-4 <?= ($p === 'combustible-precios') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=combustible-precios'>Precio x galón</a>
                </div>
              </div>

              <button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuRepMobile" aria-expanded="<?= $seccionReportes ? 'true' : 'false' ?>">
                Reportes
              </button>
              <div id="menuRepMobile" class="collapse <?= $seccionReportes ? 'show' : '' ?>" data-bs-parent="#sidebarAccordionMobile">
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-cuotas') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-cuotas'>Cuotas</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-ingresos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-ingresos'>Ingreso</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-egresos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-egresos'>Egresos</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-ingresos-egresos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-ingresos-egresos'>Ingresos / Egresos</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-marina-contratos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-marina-contratos'>Reporte Marina -> contratos</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-inmuebles-contratos') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-inmuebles-contratos'>Reporte Inmuebles -> contratos</a>
                <a class='list-group-item list-group-item-action <?= ($p === 'reporte-combustible') ? 'active' : '' ?>' href='<?= MARINA_URL ?>/index.php?p=reporte-combustible'>Combustible</a>
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

