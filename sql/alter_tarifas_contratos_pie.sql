-- Tarifas por pie + datos de pies/impuesto en contratos
ALTER TABLE tarifas
  ADD COLUMN tipo VARCHAR(10) NOT NULL DEFAULT 'dia' COMMENT 'dia|pie' AFTER nombre;

ALTER TABLE contratos
  ADD COLUMN tarifa_pie_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'Tarifa por pie aplicada' AFTER monto_total,
  ADD COLUMN cantidad_pies DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Pies del barco' AFTER tarifa_pie_id,
  ADD COLUMN impuesto_porcentaje DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'ITBMS %; NULL = sin impuesto' AFTER cantidad_pies;

ALTER TABLE contratos
  ADD CONSTRAINT fk_contratos_tarifa_pie FOREIGN KEY (tarifa_pie_id) REFERENCES tarifas(id) ON DELETE SET NULL;
