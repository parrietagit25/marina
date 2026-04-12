-- Slips sin columna muelle_id (error 1054). Ejecutar en la base de la marina si no usas migrate_light al cargar la app.
-- Debe existir al menos un registro en `muelles` antes de NOT NULL.

ALTER TABLE slips ADD COLUMN muelle_id INT UNSIGNED NULL DEFAULT NULL AFTER id;

UPDATE slips s
SET s.muelle_id = (SELECT m.id FROM muelles m ORDER BY m.id ASC LIMIT 1)
WHERE s.muelle_id IS NULL;

ALTER TABLE slips MODIFY muelle_id INT UNSIGNED NOT NULL;

ALTER TABLE slips ADD CONSTRAINT fk_slips_muelle FOREIGN KEY (muelle_id) REFERENCES muelles(id) ON DELETE RESTRICT;

-- Si al crear uk_slip_muelle aparece error 1062 (duplicados mismo muelle + nombre), ejecutar el bloque siguiente y volver a ADD UNIQUE:
-- UPDATE slips s
-- INNER JOIN (
--   SELECT muelle_id, nombre, MIN(id) AS keep_id
--   FROM slips
--   GROUP BY muelle_id, nombre
--   HAVING COUNT(*) > 1
-- ) dup ON s.muelle_id = dup.muelle_id AND s.nombre = dup.nombre AND s.id <> dup.keep_id
-- SET s.nombre = CONCAT(TRIM(s.nombre), ' (id ', s.id, ')');

UPDATE slips s
INNER JOIN (
    SELECT muelle_id, nombre, MIN(id) AS keep_id
    FROM slips
    GROUP BY muelle_id, nombre
    HAVING COUNT(*) > 1
) dup ON s.muelle_id = dup.muelle_id AND s.nombre = dup.nombre AND s.id <> dup.keep_id
SET s.nombre = CONCAT(TRIM(s.nombre), ' (id ', s.id, ')');

ALTER TABLE slips ADD UNIQUE KEY uk_slip_muelle (muelle_id, nombre);
