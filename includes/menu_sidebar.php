<?php
/**
 * Menú lateral filtrado por permisos del usuario.
 */
declare(strict_types=1);

require_once __DIR__ . '/permisos.php';

function marina_render_sidebar_menu(string $p, string $accordionParentId, string $suffix): void
{
    $def = marina_menu_permisos_definicion();
    $mapaSeccionId = [
        'General' => null,
        'Mantenimiento' => 'Mant',
        'Banco' => 'Banco',
        'Costo o Gastos' => 'Costo',
        'Combustible' => 'Combustible',
        'Marina Ingresos' => 'Marina',
        'Reportes' => 'Rep',
    ];

    marina_menu_enlace('dashboard', 'Inicio', $p, 'layout-dashboard');
    marina_menu_enlace('manual', 'Manual', $p, 'book-open');

    echo '<div class="sidebar-accordion mt-2" id="sidebarAccordion' . htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8') . '">';

    foreach ($def as $bloque) {
        $titulo = $bloque['seccion'];
        if ($titulo === 'General') {
            continue;
        }
        $items = $bloque['items'];
        if (!marina_menu_seccion_visible($items)) {
            continue;
        }
        $sid = $mapaSeccionId[$titulo] ?? preg_replace('/[^a-zA-Z0-9]/', '', $titulo);
        $collapseId = 'menu' . $sid . $suffix;
        $expandido = marina_menu_seccion_activa($items, $p);
        $tituloBtn = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
        echo '<button class="menu-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '" aria-expanded="' . ($expandido ? 'true' : 'false') . '">';
        echo $tituloBtn;
        echo '</button>';
        echo '<div id="' . $collapseId . '" class="collapse' . ($expandido ? ' show' : '') . '" data-bs-parent="#sidebarAccordion' . htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8') . '">';
        foreach ($items as $it) {
            marina_menu_enlace(
                $it['pagina'],
                $it['etiqueta'],
                $p,
                $it['icono'] ?? null
            );
        }
        echo '</div>';
    }

    echo '</div>';
}
