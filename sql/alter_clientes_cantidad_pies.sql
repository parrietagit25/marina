-- Pies (eslora) del navío en clientes.

ALTER TABLE clientes
ADD COLUMN cantidad_pies DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Pies del navío' AFTER tipo_embarcacion;
