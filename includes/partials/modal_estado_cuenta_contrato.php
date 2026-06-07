<?php
/**
 * Modal compartido: estado de cuenta del contrato (cuotas + electricidad).
 * Requiere assets/js/app.js (manejador .btn-estado-cuenta-contrato).
 */
?>
<div class="modal fade" id="modalEstadoCuentaContrato" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ecContratoTitle">Estado de cuenta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="ecContratoBody">
                <p class="text-muted mb-0">Cargando…</p>
            </div>
            <div class="modal-footer flex-wrap gap-2">
                <button type="button" class="btn btn-outline-success btn-sm d-none" id="ecBtnEnviarEmail">Enviar por correo</button>
                <a href="#" class="btn btn-outline-primary btn-sm d-none" id="ecLinkCuotas" target="_self">Ir a cuotas</a>
                <a href="#" class="btn btn-outline-info btn-sm text-white d-none" id="ecLinkElectricidad" target="_self">Ir a electricidad</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<script>window.MARINA_EC_BASE = <?= json_encode(MARINA_URL . '/index.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
