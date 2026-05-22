-- Permisos por usuario (menú, editar y eliminar en todo el sistema)
-- NULL en permisos_json = acceso completo (usuarios existentes / administradores sin restricción)

ALTER TABLE usuarios
  ADD COLUMN permisos_json TEXT NULL DEFAULT NULL
    COMMENT 'JSON: {"paginas":["dashboard",...],"editar":1,"eliminar":1}' AFTER rol;
