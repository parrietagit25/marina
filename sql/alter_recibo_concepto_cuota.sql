-- Opcional: si no usa la app (migrate_light), ejecutar manualmente en MySQL/MariaDB.

ALTER TABLE contratos
  ADD COLUMN numero_recibo VARCHAR(100) NULL DEFAULT NULL COMMENT 'Número de recibo al cliente' AFTER observaciones;

ALTER TABLE cuotas_movimientos
  ADD COLUMN concepto VARCHAR(255) NULL DEFAULT NULL COMMENT 'Término / descripción del pago de cuota' AFTER referencia;
