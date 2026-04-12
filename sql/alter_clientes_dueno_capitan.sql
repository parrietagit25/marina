-- Campo Dueño / Capitán en clientes (ejecutar una vez en phpMyAdmin si no usas migrate_light).

ALTER TABLE clientes
ADD COLUMN dueno_capitan VARCHAR(150) NULL DEFAULT NULL COMMENT 'Dueño / Capitán' AFTER direccion;
