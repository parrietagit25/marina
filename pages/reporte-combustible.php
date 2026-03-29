<?php
/**
 * Reporte combustible: elegir pedidos o despachos, filtrar por fechas y exportar.
 */
$titulo = 'Reporte — Combustible';
$pdo = getDb();
require_once __DIR__ . '/../includes/combustible_helpers.php';
require_once __DIR__ . '/../includes/export_excel.php';

$vista = trim((string) obtener('vista', ''));
$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));

if ($vista !== 'pedidos' && $vista !== 'despachos') {
    require_once __DIR__ . '/../includes/layout.php';
    ?>
    <h1 class="h4 mb-3">Reporte — Combustible</h1>
    <p class="text-muted mb-4">Seleccione qué movimientos desea consultar.</p>
    <div class="row g-3">
        <div class="col-md-6">
            <a class="card h-100 text-decoration-none text-body p-4 border shadow-sm" href="<?= MARINA_URL ?>/index.php?p=reporte-combustible&vista=pedidos">
                <h2 class="h5">Pedidos</h2>
                <p class="text-muted small mb-0">Compras, GLS pedido/recibido, factura, costo, pagos y estado.</p>
            </a>
        </div>
        <div class="col-md-6">
            <a class="card h-100 text-decoration-none text-body p-4 border shadow-sm" href="<?= MARINA_URL ?>/index.php?p=reporte-combustible&vista=despachos">
                <h2 class="h5">Despachos</h2>
                <p class="text-muted small mb-0">Ventas por fecha, embarcación, GLS, monto y cuenta.</p>
            </a>
        </div>
    </div>
    <?php require_once __DIR__ . '/../includes/footer.php';
    return;
}

$filas = [];
$encabezados = [];
if ($vista === 'pedidos') {
    $st = $pdo->prepare("
        SELECT p.id, p.tipo_combustible, p.fecha_pedido, p.gls_pedido, p.fecha_recibido, p.gls_recibido,
               p.numero_factura, p.costo_total, p.estado_pago,
               (SELECT COALESCE(SUM(monto),0) FROM combustible_pedido_pagos x WHERE x.pedido_id = p.id) AS monto_pagado
        FROM combustible_pedidos p
        WHERE p.fecha_pedido BETWEEN ? AND ?
        ORDER BY p.fecha_pedido DESC, p.id DESC
    ");
    $st->execute([$desde, $hasta]);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);
    $encabezados = ['Id', 'Tipo', 'Fecha pedido', 'GLS pedido', 'Fecha recibido', 'GLS recibido', 'Factura', 'Costo total', 'Monto pagado', 'Estado'];
    if (obtener('export') === 'excel') {
        $rows = [];
        foreach ($filas as $r) {
            $rows[] = [
                $r['id'],
                MARINA_COMB_TIPOS[$r['tipo_combustible']] ?? $r['tipo_combustible'],
                $r['fecha_pedido'],
                (float) $r['gls_pedido'],
                $r['fecha_recibido'] ?? '',
                $r['gls_recibido'] ?? '',
                $r['numero_factura'] ?? '',
                (float) $r['costo_total'],
                (float) $r['monto_pagado'],
                ($r['estado_pago'] ?? '') === 'pagado' ? 'Pagado' : 'Por pagar',
            ];
        }
        exportarExcel('reporte_combustible_pedidos', $encabezados, $rows, []);
    }
} else {
    $st = $pdo->prepare("
        SELECT d.id, d.tipo_combustible, d.fecha, d.embarcacion, d.gls, d.monto_total,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre
        FROM combustible_despachos d
        JOIN cuentas c ON c.id = d.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        WHERE d.fecha BETWEEN ? AND ?
        ORDER BY d.fecha DESC, d.id DESC
    ");
    $st->execute([$desde, $hasta]);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);
    $encabezados = ['Id', 'Tipo', 'Fecha', 'Embarcación', 'GLS', 'Monto', 'Cuenta'];
    if (obtener('export') === 'excel') {
        $rows = [];
        foreach ($filas as $r) {
            $rows[] = [
                $r['id'],
                MARINA_COMB_TIPOS[$r['tipo_combustible']] ?? $r['tipo_combustible'],
                $r['fecha'],
                $r['embarcacion'],
                (float) $r['gls'],
                (float) $r['monto_total'],
                $r['cuenta_nombre'] ?? '',
            ];
        }
        exportarExcel('reporte_combustible_despachos', $encabezados, $rows, []);
    }
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-3">Reporte — Combustible (<?= $vista === 'pedidos' ? 'Pedidos' : 'Despachos' ?>)</h1>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-combustible">
    <input type="hidden" name="vista" value="<?= e($vista) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Desde</label>
            <input type="date" class="form-control" name="desde" value="<?= e($desde) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Hasta</label>
            <input type="date" class="form-control" name="hasta" value="<?= e($hasta) ?>">
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-success" name="export" value="excel">Exportar Excel</button>
        </div>
        <div class="col-12 col-md-auto">
            <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=reporte-combustible">Cambiar vista</a>
        </div>
    </div>
</form>

<div class="card p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <?php foreach ($encabezados as $h): ?>
                        <th><?= e($h) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($vista === 'pedidos'): ?>
                <?php foreach ($filas as $r): ?>
                    <tr>
                        <td><?= (int) $r['id'] ?></td>
                        <td><?= e(MARINA_COMB_TIPOS[$r['tipo_combustible']] ?? $r['tipo_combustible']) ?></td>
                        <td><?= fechaFormato($r['fecha_pedido']) ?></td>
                        <td class="text-end"><?= e((string) $r['gls_pedido']) ?></td>
                        <td><?= $r['fecha_recibido'] ? fechaFormato($r['fecha_recibido']) : '—' ?></td>
                        <td class="text-end"><?= $r['gls_recibido'] !== null ? e((string) $r['gls_recibido']) : '—' ?></td>
                        <td><?= e($r['numero_factura'] ?? '') ?></td>
                        <td class="text-end"><?= dinero((float) $r['costo_total']) ?></td>
                        <td class="text-end"><?= dinero((float) $r['monto_pagado']) ?></td>
                        <td><?= ($r['estado_pago'] ?? '') === 'pagado' ? 'Pagado' : 'Por pagar' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($filas as $r): ?>
                    <tr>
                        <td><?= (int) $r['id'] ?></td>
                        <td><?= e(MARINA_COMB_TIPOS[$r['tipo_combustible']] ?? $r['tipo_combustible']) ?></td>
                        <td><?= fechaFormato($r['fecha']) ?></td>
                        <td><?= e($r['embarcacion']) ?></td>
                        <td class="text-end"><?= e((string) $r['gls']) ?></td>
                        <td class="text-end"><?= dinero((float) $r['monto_total']) ?></td>
                        <td><?= e($r['cuenta_nombre'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($filas === []): ?>
                <tr><td colspan="<?= count($encabezados) ?>" class="text-muted">Sin registros en el período.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
