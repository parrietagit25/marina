-- Error #1062 al crear uk_slip_muelle: hay dos o más slips con el mismo muelle_id y el mismo nombre.
-- Ejecuta esto y luego: ALTER TABLE slips ADD UNIQUE KEY uk_slip_muelle (muelle_id, nombre);

UPDATE slips s
INNER JOIN (
    SELECT muelle_id, nombre, MIN(id) AS keep_id
    FROM slips
    GROUP BY muelle_id, nombre
    HAVING COUNT(*) > 1
) dup ON s.muelle_id = dup.muelle_id AND s.nombre = dup.nombre AND s.id <> dup.keep_id
SET s.nombre = CONCAT(TRIM(s.nombre), ' (id ', s.id, ')');
