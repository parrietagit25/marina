<?php
/**
 * Manual de usuario: visión general y descripción por módulos.
 */
$titulo = 'Manual de usuario';

require_once __DIR__ . '/../includes/layout.php';

$u = function (string $p, string $label): string {
    $href = MARINA_URL . '/index.php?p=' . rawurlencode($p);
    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
};
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h4 mb-0">Manual de usuario</h1>
</div>

<p class="text-muted mb-4">
    Guía orientativa del sistema Marina. Use el menú lateral (o <strong>Menu</strong> en pantallas pequeñas) para ir a cada pantalla.
    Los enlaces siguientes abren la sección correspondiente dentro de la aplicación.
</p>

<div class="accordion manual-accordion mb-4" id="manualAccordion">

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#manualIntro" aria-expanded="true">
                Cómo funciona el sistema
            </button>
        </h2>
        <div id="manualIntro" class="accordion-collapse collapse show" data-bs-parent="#manualAccordion">
            <div class="accordion-body">
                <p>Marina centraliza la operación de la marina: <strong>clientes y contratos</strong> (amarres, inmuebles),
                    <strong>créditos por cuotas y electricidad</strong>, <strong>gastos y proveedores</strong>,
                    <strong>cuentas bancarias</strong> y <strong>reportes</strong>.</p>
                <ul>
                    <li>Debe iniciar sesión con un usuario válido. La sesión se cierra con <strong>Salir</strong>.</li>
                    <li>Muchos listados permiten ordenar y buscar; en tablas con DataTables use el cuadro de búsqueda y el orden por columnas.</li>
                    <li>Los <strong>créditos bancarios</strong> pueden registrarse de tres maneras: pagos de <strong>cuotas</strong> de contratos,
                        pagos de <strong>facturas de electricidad</strong> por contrato, y <strong>movimientos bancarios manuales</strong>
                        (otros <?= e(marina_ui_credito()) ?> o <?= e(marina_ui_debito()) ?> que no vienen de cuotas ni electricidad ni del módulo de gastos).</li>
                    <li>Los <strong>gastos</strong> (proveedores) generan débitos al registrarse pagos; también pueden verse en movimiento bancario y reportes.</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#manualInicio">
                Inicio (dashboard)
            </button>
        </h2>
        <div id="manualInicio" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
            <div class="accordion-body">
                <p>Pantalla <?= $u('dashboard', 'Inicio') ?>: resumen del mes en curso con totales de créditos y débitos,
                    gráficas por día, distribución por cuenta bancaria y, cuando aplica, débitos por partida contable.</p>
                <p>Sirve para una vista rápida del flujo de caja y la actividad reciente sin entrar aún a cada módulo.</p>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#manualMant">
                Mantenimiento
            </button>
        </h2>
        <div id="manualMant" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
            <div class="accordion-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3"><?= $u('usuarios', 'Usuarios') ?></dt>
                    <dd class="col-sm-9">Alta y administración de cuentas que pueden entrar al sistema.</dd>
                    <dt class="col-sm-3"><?= $u('bancos', 'Bancos') ?></dt>
                    <dd class="col-sm-9">Catálogo de instituciones financieras. Las cuentas bancarias se asocian a un banco.</dd>
                    <dt class="col-sm-3"><?= $u('cuentas', 'Cuentas') ?></dt>
                    <dd class="col-sm-9">Cuentas corrientes o de depósito usadas en contratos, pagos, movimientos y gastos.</dd>
                    <dt class="col-sm-3"><?= $u('configuracion', 'Configuración') ?></dt>
                    <dd class="col-sm-9">Ajuste del tamaño de fuente global de la interfaz (accesibilidad).</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#manualBanco">
                Banco
            </button>
        </h2>
        <div id="manualBanco" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
            <div class="accordion-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3"><?= $u('movimiento-bancario', 'Registrar movimientos bancarios') ?></dt>
                    <dd class="col-sm-9">Línea de tiempo de movimientos: cuotas pagadas, electricidad cobrada, gastos pagados y movimientos manuales.
                        Use <strong>Registrar movimiento bancario</strong> para <?= e(marina_ui_credito()) ?> o <?= e(marina_ui_debito()) ?> que no se originan en otro módulo.
                        Solo las filas <strong>manuales</strong> tienen botón <strong>Editar</strong>; el resto se modifica en su módulo de origen.</dd>
                    <dt class="col-sm-3"><?= $u('reporte-estado-cuenta-bancarias', 'Estado de cuenta bancaria') ?></dt>
                    <dd class="col-sm-9">Detalle por cuenta y rango de fechas, alineado con los mismos conceptos que el listado de movimientos.</dd>
                    <dt class="col-sm-3"><?= $u('saldos-cuentas-bancarias', 'Saldos de cuentas bancarias') ?></dt>
                    <dd class="col-sm-9">Saldo acumulado por cuenta según créditos y débitos registrados en el sistema.</dd>
                    <dt class="col-sm-3"><?= $u('formas-pago', 'Tipo de movimientos') ?></dt>
                    <dd class="col-sm-9">Formas de pago clasificadas como <?= e(marina_ui_credito()) ?> o <?= e(marina_ui_debito()) ?>; se usan en movimientos bancarios manuales y en pagos.</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#manualGastos">
                Costo o gastos
            </button>
        </h2>
        <div id="manualGastos" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
            <div class="accordion-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3"><?= $u('proveedores', 'Proveedores') ?></dt>
                    <dd class="col-sm-9">Proveedores de bienes y servicios; base para registrar facturas y pagos.</dd>
                    <dt class="col-sm-3"><?= $u('gastos', 'Factura / Pagar') ?></dt>
                    <dd class="col-sm-9">Registro de facturas de gasto, partida contable, abonos y saldo pendiente o pagado.
                        Los pagos impactan bancos y reportes de débitos.</dd>
                    <dt class="col-sm-3"><?= $u('reporte-proveedores-estado-cuenta', 'Estado de cuenta proveedor') ?></dt>
                    <dd class="col-sm-9">Historial y saldo por proveedor en un período.</dd>
                    <dt class="col-sm-3"><?= $u('partidas', 'Partidas') ?></dt>
                    <dd class="col-sm-9">Categorías contables para agrupar gastos y alimentar reportes por partida.</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#manualMarina">
                Marina Ingresos
            </button>
        </h2>
        <div id="manualMarina" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
            <div class="accordion-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3"><?= $u('clientes', 'Clientes') ?></dt>
                    <dd class="col-sm-9">Personas o empresas titulares de contratos.</dd>
                    <dt class="col-sm-3"><?= $u('muelles', 'Muelles') ?></dt>
                    <dd class="col-sm-9">Estructura física de la marina; organiza slips.</dd>
                    <dt class="col-sm-3"><?= $u('slips', 'Slips') ?></dt>
                    <dd class="col-sm-9">Espacios de amarre asociados a muelle; suelen usarse en contratos de marina.</dd>
                    <dt class="col-sm-3"><?= $u('grupos', 'Grupos') ?></dt>
                    <dd class="col-sm-9">Agrupaciones de inmuebles (por ejemplo edificios o sectores).</dd>
                    <dt class="col-sm-3"><?= $u('inmuebles', 'Inmuebles') ?></dt>
                    <dd class="col-sm-9">Unidades arrendables dentro de grupos.</dd>
                    <dt class="col-sm-3"><?= $u('mapa-marina', 'Mapa Marina') ?></dt>
                    <dd class="col-sm-9">Vista gráfica de muelles y slips y su ocupación.</dd>
                    <dt class="col-sm-3"><?= $u('mapa-grupos', 'Mapa Grupos') ?></dt>
                    <dd class="col-sm-9">Vista de grupos e inmuebles.</dd>
                    <dt class="col-sm-3"><?= $u('contratos', 'Contratos') ?></dt>
                    <dd class="col-sm-9">Contratos de amarre o inmueble: cliente, cuenta de cobro, período, montos y <strong>cuotas</strong>.
                        Los contratos pueden estar <strong>activos</strong> o <strong>terminados</strong>; un terminado limita ciertas acciones (por ejemplo eliminar).
                        Desde la lista puede abrir la gestión de <strong>electricidad por contrato</strong> cuando aplique.</dd>
                    <dt class="col-sm-3"><?= $u('contratos-electricidad', 'Electricidad (contrato)') ?></dt>
                    <dd class="col-sm-9">Facturas y cobros de electricidad ligados a un contrato; los pagos aparecen como créditos bancarios en reportes y movimiento bancario.</dd>
                    <dt class="col-sm-3">Combustible</dt>
                    <dd class="col-sm-9">
                        <?= $u('combustible-pedidos', 'Pedidos') ?> (compras y recepción),
                        <?= $u('combustible-despacho', 'Despacho') ?> (venta a embarcaciones),
                        <?= $u('combustible-ajuste', 'Ajuste') ?> (correcciones de inventario),
                        <?= $u('combustible-precios', 'Precio x galón') ?> (tarifas vigentes).
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#manualRep">
                Reportes
            </button>
        </h2>
        <div id="manualRep" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
            <div class="accordion-body">
                <ul class="mb-0">
                    <li><?= $u('reporte-cuotas', 'Reporte de cuotas') ?> — cuotas por vencimiento, estado (pagada / pendiente / vencida), marina (muelle/slip) o inmueble (grupo/inmueble).</li>
                    <li><?= $u('reporte-ingresos', 'Reporte de ingreso') ?> y <?= $u('reporte-egresos', 'Reporte de egresos') ?> — concentrados por tipo.</li>
                    <li><?= $u('reportes', 'Reporte de ingresos y egresos') ?> — resumen del período (totales por cuenta y por partida).</li>
                    <li><?= $u('reporte-ingresos-egresos', 'Ingresos y egresos (detalle)') ?> — listado conjunto con filtros (cuenta, vista agrupada, etc.).</li>
                    <li><?= $u('reporte-marina-contratos', 'Reporte Marina → contratos') ?> — relación muelle/slip y contratos.</li>
                    <li><?= $u('reporte-inmuebles-contratos', 'Reporte Inmuebles → contratos') ?> — relación grupo/inmueble y contratos.</li>
                    <li><?= $u('reporte-combustible', 'Combustible') ?> — operación e inventario de combustible.</li>
                </ul>
            </div>
        </div>
    </div>

</div>

<div class="card border-0 bg-light">
    <div class="card-body small text-muted">
        Este manual describe la versión actual de la aplicación. Si tras una actualización cambian pantallas o reglas de negocio,
        conviene revisar de nuevo esta sección o solicitar capacitación complementaria.
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
