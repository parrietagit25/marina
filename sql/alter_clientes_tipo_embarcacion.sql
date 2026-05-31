-- Tipo de embarcación en clientes (CAT, MYT, MT).

ALTER TABLE clientes
ADD COLUMN tipo_embarcacion VARCHAR(10) NULL DEFAULT NULL COMMENT 'CAT|MYT|MT' AFTER dueno_capitan;
