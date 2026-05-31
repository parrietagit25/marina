-- Precio por galón al registrar despacho (ejecutar si no usa migrate_light al cargar la app).
ALTER TABLE combustible_despachos
  ADD COLUMN precio_venta_galon DECIMAL(12,4) NULL COMMENT 'Precio venta/galón al registrar despacho' AFTER gls;

UPDATE combustible_despachos d
SET d.precio_venta_galon = (
    SELECT p.precio_venta_galon
    FROM combustible_precios p
    WHERE p.tipo_combustible = d.tipo_combustible
      AND p.vigente_desde <= d.fecha
    ORDER BY p.vigente_desde DESC, p.id DESC
    LIMIT 1
)
WHERE d.precio_venta_galon IS NULL;

UPDATE combustible_despachos
SET precio_venta_galon = ROUND(monto_total / gls, 4)
WHERE precio_venta_galon IS NULL AND gls > 0;
