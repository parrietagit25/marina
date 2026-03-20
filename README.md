# Marina – Sistema de gestión

Sistema para una marina: muelles, slips, contratos por cuotas, gastos por partidas jerárquicas, ingresos y costos.

## Requisitos

- PHP 7.4+ (con PDO MySQL, session)
- MySQL o MariaDB
- Servidor web (XAMPP, etc.) con la app en `/marina`

## Instalación

1. **Crear la base de datos**
   - En phpMyAdmin o consola: `CREATE DATABASE marina CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`

2. **Importar el esquema**
   - Importar el archivo `sql/schema.sql` en la base `marina`.

3. **Crear el usuario admin**
   - Desde la carpeta del proyecto:  
     `php install/crear_admin.php`  
   - O en el navegador: `http://localhost/marina/install/crear_admin.php`  
   - Credenciales: **admin@marina.local** / **admin123**  
   - Conviene cambiar la contraseña tras el primer acceso.

4. **Configuración**
   - Revisar `config/config.php`: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` y `MARINA_URL` (por defecto `/marina`).

## Uso

- **URL de entrada:** `http://localhost/marina/` o `http://localhost/marina/index.php`
- Menú: Usuarios, Bancos, Cuentas, Clientes, Muelles, Slips, Formas de pago, Partidas, Proveedores, Contratos, Gastos, Ingresos/Costos (reportes).
- Todos los registros guardan fecha/hora y usuario que registró (auditoría).

## Módulos

| Módulo        | Descripción |
|---------------|-------------|
| Usuarios      | Alta y edición de usuarios (por ahora solo admin). |
| Bancos / Cuentas | Un banco tiene varias cuentas de la marina. |
| Clientes      | Datos de clientes para contratos. |
| Muelles / Slips | Cada muelle tiene slips; el nombre del slip lo define el usuario. |
| Formas de pago | Catálogo (efectivo, transferencia, etc.). |
| Partidas      | Jerárquicas (partida > subpartida > …). Los gastos se cargan en partidas “hoja”. |
| Proveedores   | Para registrar gastos. |
| Contratos     | Cliente, cuenta donde se acreditan pagos, muelle, slip, fechas, monto total. Tras guardar se definen las cuotas (vencimiento y monto por cuota). |
| Gastos        | Partida (hoja), proveedor, monto, fecha, opcionalmente cuenta y forma de pago. |
| Reportes      | Ingresos (cuotas pagadas) y costos por partida en un rango de fechas. |

## Estructura

- `config/` – Config y conexión BD  
- `includes/` – Layout, auth, funciones  
- `pages/` – Una página por módulo (`?p=nombre`)  
- `sql/schema.sql` – Esquema de tablas  
- `install/crear_admin.php` – Crea el usuario admin inicial  
