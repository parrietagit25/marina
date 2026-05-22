<?php
/**
 * Datos de ocupación (slips / inmuebles) para gráficas del dashboard.
 */
declare(strict_types=1);

/**
 * @return array{slip: array<int, array<string, mixed>>, inm: array<int, array<string, mixed>>}
 */
function marina_ocupacion_contratos_activos_map(PDO $pdo): array
{
    $porSlip = [];
    $porInmueble = [];
    $st = $pdo->query("
        SELECT co.id, co.slip_id, co.inmueble_id
        FROM contratos co
        WHERE COALESCE(co.estado, 'activo') = 'activo'
          AND (co.slip_id IS NOT NULL OR co.inmueble_id IS NOT NULL)
    ");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int) ($r['slip_id'] ?? 0);
        $iid = (int) ($r['inmueble_id'] ?? 0);
        if ($sid > 0 && !isset($porSlip[$sid])) {
            $porSlip[$sid] = $r;
        }
        if ($iid > 0 && !isset($porInmueble[$iid])) {
            $porInmueble[$iid] = $r;
        }
    }

    return ['slip' => $porSlip, 'inm' => $porInmueble];
}

/** @return array<string, mixed> */
function marina_dashboard_ocupacion_datos(PDO $pdo): array
{
    $maps = marina_ocupacion_contratos_activos_map($pdo);
    $occSlips = $maps['slip'];
    $occInm = $maps['inm'];

    $totalSlips = 0;
    $slipsOcup = 0;
    $porMuelle = [];

    $stM = $pdo->query('SELECT id, nombre FROM muelles ORDER BY nombre');
    while ($m = $stM->fetch(PDO::FETCH_ASSOC)) {
        $mid = (int) ($m['id'] ?? 0);
        $nombre = (string) ($m['nombre'] ?? '');
        $stS = $pdo->prepare('SELECT id FROM slips WHERE muelle_id = ?');
        $stS->execute([$mid]);
        $tot = 0;
        $occ = 0;
        while ($s = $stS->fetch(PDO::FETCH_ASSOC)) {
            $tot++;
            $totalSlips++;
            if (isset($occSlips[(int) ($s['id'] ?? 0)])) {
                $occ++;
                $slipsOcup++;
            }
        }
        if ($tot > 0) {
            $porMuelle[] = [
                'nombre' => $nombre,
                'total' => $tot,
                'ocupado' => $occ,
                'libre' => $tot - $occ,
                'pct' => $tot > 0 ? round(100 * $occ / $tot, 1) : 0.0,
            ];
        }
    }

    $totalInm = 0;
    $inmOcup = 0;
    $porGrupo = [];

    $stG = $pdo->query('SELECT id, nombre FROM grupos ORDER BY nombre');
    while ($g = $stG->fetch(PDO::FETCH_ASSOC)) {
        $gid = (int) ($g['id'] ?? 0);
        $nombre = (string) ($g['nombre'] ?? '');
        $stI = $pdo->prepare('SELECT id FROM inmuebles WHERE grupo_id = ?');
        $stI->execute([$gid]);
        $tot = 0;
        $occ = 0;
        while ($i = $stI->fetch(PDO::FETCH_ASSOC)) {
            $tot++;
            $totalInm++;
            if (isset($occInm[(int) ($i['id'] ?? 0)])) {
                $occ++;
                $inmOcup++;
            }
        }
        if ($tot > 0) {
            $porGrupo[] = [
                'nombre' => $nombre,
                'total' => $tot,
                'ocupado' => $occ,
                'libre' => $tot - $occ,
                'pct' => $tot > 0 ? round(100 * $occ / $tot, 1) : 0.0,
            ];
        }
    }

    $slipsLibre = max(0, $totalSlips - $slipsOcup);
    $inmLibre = max(0, $totalInm - $inmOcup);
    $totalUnidades = $totalSlips + $totalInm;
    $totalOcup = $slipsOcup + $inmOcup;
    $totalLibre = $slipsLibre + $inmLibre;

    $pctSlips = $totalSlips > 0 ? round(100 * $slipsOcup / $totalSlips, 1) : 0.0;
    $pctInm = $totalInm > 0 ? round(100 * $inmOcup / $totalInm, 1) : 0.0;
    $pctGeneral = $totalUnidades > 0 ? round(100 * $totalOcup / $totalUnidades, 1) : 0.0;

    return [
        'total_slips' => $totalSlips,
        'slips_ocupados' => $slipsOcup,
        'slips_libres' => $slipsLibre,
        'pct_slips' => $pctSlips,
        'total_inmuebles' => $totalInm,
        'inmuebles_ocupados' => $inmOcup,
        'inmuebles_libres' => $inmLibre,
        'pct_inmuebles' => $pctInm,
        'total_unidades' => $totalUnidades,
        'total_ocupados' => $totalOcup,
        'total_libres' => $totalLibre,
        'pct_general' => $pctGeneral,
        'por_muelle' => $porMuelle,
        'por_grupo' => $porGrupo,
    ];
}
