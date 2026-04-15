<?php
/**
 * Resumen de saldo por cuenta (ingresos por cuotas + legacy, gastos, movimientos manuales).
 * Sin listado de movimientos; coherente con el estado de cuenta bancarias.
 */
$titulo = 'Saldos de cuentas bancarias';
$pdo = getDb();

$fechaRef = date('Y-m-d');

$sqlBase = "
SELECT c.id,
       CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
       COALESCE(im.ing, 0) + COALESCE(il.ing, 0) + COALESCE(imel.ing, 0) - COALESCE(ge.egr, 0) %s AS saldo
FROM cuentas c
JOIN bancos b ON b.id = c.banco_id
LEFT JOIN (
    SELECT co.cuenta_id, SUM(mo.monto) AS ing
    FROM cuotas_movimientos mo
    JOIN cuotas cu ON mo.cuota_id = cu.id
    JOIN contratos co ON cu.contrato_id = co.id
    WHERE mo.tipo IN ('pago','abono')
    GROUP BY co.cuenta_id
) im ON im.cuenta_id = c.id
LEFT JOIN (
    SELECT co.cuenta_id, SUM(cu.monto) AS ing
    FROM cuotas cu
    JOIN contratos co ON cu.contrato_id = co.id
    WHERE cu.fecha_pago IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM cuotas_movimientos x WHERE x.cuota_id = cu.id)
    GROUP BY co.cuenta_id
) il ON il.cuenta_id = c.id
LEFT JOIN (
    SELECT ep.cuenta_id, SUM(ep.monto) AS ing
    FROM contrato_electricidad_pagos ep
    GROUP BY ep.cuenta_id
) imel ON imel.cuenta_id = c.id
LEFT JOIN (
    SELECT cuenta_id, SUM(monto) AS egr FROM gasto_pagos WHERE cuenta_id IS NOT NULL GROUP BY cuenta_id
) ge ON ge.cuenta_id = c.id
%s
ORDER BY b.nombre, c.nombre
";

$mbJoin = "
LEFT JOIN (
    SELECT cuenta_id, SUM(monto) AS s FROM movimientos_bancarios WHERE tipo_movimiento = 'ingreso' GROUP BY cuenta_id
) mbi ON mbi.cuenta_id = c.id
LEFT JOIN (
    SELECT cuenta_id, SUM(monto) AS s FROM movimientos_bancarios WHERE tipo_movimiento = 'costo' GROUP BY cuenta_id
) mbc ON mbc.cuenta_id = c.id
";
$mbExpr = ' + COALESCE(mbi.s, 0) - COALESCE(mbc.s, 0)';

$mbJoinComb = $mbJoin . "
LEFT JOIN (
    SELECT cuenta_id, SUM(monto_total) AS s FROM combustible_despachos GROUP BY cuenta_id
) cdin ON cdin.cuenta_id = c.id
";
$mbExprComb = ' + COALESCE(mbi.s, 0) - COALESCE(mbc.s, 0) + COALESCE(cdin.s, 0)';

$sqlBaseSinEle = "
SELECT c.id,
       CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
       COALESCE(im.ing, 0) + COALESCE(il.ing, 0) - COALESCE(ge.egr, 0) %s AS saldo
FROM cuentas c
JOIN bancos b ON b.id = c.banco_id
LEFT JOIN (
    SELECT co.cuenta_id, SUM(mo.monto) AS ing
    FROM cuotas_movimientos mo
    JOIN cuotas cu ON mo.cuota_id = cu.id
    JOIN contratos co ON cu.contrato_id = co.id
    WHERE mo.tipo IN ('pago','abono')
    GROUP BY co.cuenta_id
) im ON im.cuenta_id = c.id
LEFT JOIN (
    SELECT co.cuenta_id, SUM(cu.monto) AS ing
    FROM cuotas cu
    JOIN contratos co ON cu.contrato_id = co.id
    WHERE cu.fecha_pago IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM cuotas_movimientos x WHERE x.cuota_id = cu.id)
    GROUP BY co.cuenta_id
) il ON il.cuenta_id = c.id
LEFT JOIN (
    SELECT cuenta_id, SUM(monto) AS egr FROM gasto_pagos WHERE cuenta_id IS NOT NULL GROUP BY cuenta_id
) ge ON ge.cuenta_id = c.id
%s
ORDER BY b.nombre, c.nombre
";

$filas = [];
try {
    $sql = sprintf($sqlBase, $mbExprComb, $mbJoinComb);
    $filas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    try {
        $sql = sprintf($sqlBaseSinEle, $mbExprComb, $mbJoinComb);
        $filas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e1b) {
        try {
            $sql = sprintf($sqlBase, $mbExpr, $mbJoin);
            $filas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e2) {
            try {
                $sql = sprintf($sqlBaseSinEle, $mbExpr, $mbJoin);
                $filas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e2b) {
                $sql = sprintf($sqlBaseSinEle, '', '');
                $filas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
}

$totalSaldo = 0.0;
foreach ($filas as $f) {
    $totalSaldo += (float) ($f['saldo'] ?? 0);
}

require_once __DIR__ . '/../includes/layout.php';
?>
<div class="row justify-content-center">
<div class="col-12 col-lg-10 col-xl-8">
<h1 class="h4 mb-3 text-center text-lg-start">Saldos de cuentas bancarias</h1>
<p class="text-muted small mb-3 text-center text-lg-start mx-auto" style="max-width: 42rem;">
    Resumen al <?= fechaFormato($fechaRef) ?>: créditos por cuotas (movimientos y compatibilidad con pago único),
    créditos por <a href="<?= MARINA_URL ?>/index.php?p=combustible-despacho">despacho de combustible</a>,
    créditos por pagos de <strong>electricidad</strong> en contratos,
    menos gastos asignados a la cuenta, más créditos manuales y menos débitos manuales en <a href="<?= MARINA_URL ?>/index.php?p=movimiento-bancario">movimientos bancarios</a>.
    Para el detalle por fechas use <a href="<?= MARINA_URL ?>/index.php?p=reporte-estado-cuenta-bancarias">Estado de cuenta bancaria</a>.
</p>

<div class="card p-3 mb-3">
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-center gap-1 gap-sm-3 text-center">
        <div>
            <strong>Total consolidado:</strong>
            <span class="<?= $totalSaldo >= 0 ? 'text-success' : 'text-danger' ?>"><?= dinero($totalSaldo) ?></span>
        </div>
        <span class="text-muted small d-none d-sm-inline">·</span>
        <div class="small text-muted"><?= count($filas) ?> cuenta(s)</div>
    </div>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Cuenta</th>
                    <th class="text-end" style="width: 11rem; white-space: nowrap">Saldo</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $r): ?>
                <?php $s = (float) ($r['saldo'] ?? 0); ?>
                <tr>
                    <td><?= e($r['cuenta_nombre'] ?? '') ?></td>
                    <td class="text-end fw-semibold <?= $s >= 0 ? 'text-success' : 'text-danger' ?>"><?= dinero($s) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($filas)): ?>
                <tr>
                    <td class="text-muted">No hay cuentas registradas.</td>
                    <td class="text-end">—</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
