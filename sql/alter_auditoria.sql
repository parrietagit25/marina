-- Registro de actividad por usuario (Seguimiento)

CREATE TABLE IF NOT EXISTS auditoria_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  pagina VARCHAR(80) NOT NULL DEFAULT '',
  accion VARCHAR(40) NOT NULL DEFAULT '',
  modulo VARCHAR(120) NOT NULL DEFAULT '',
  entidad_id INT UNSIGNED NULL DEFAULT NULL,
  descripcion VARCHAR(500) NOT NULL DEFAULT '',
  ip VARCHAR(45) NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_auditoria_fecha (created_at),
  KEY idx_auditoria_usuario (usuario_id, created_at),
  KEY idx_auditoria_modulo (modulo, created_at),
  CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
