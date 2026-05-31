-- Tipo de embarcación en despachos (ejecutar si no usa migrate_light al cargar la app).
ALTER TABLE combustible_despachos
  ADD COLUMN tipo_embarcacion VARCHAR(10) NULL DEFAULT NULL COMMENT 'CAT|MYT|MT' AFTER embarcacion;
