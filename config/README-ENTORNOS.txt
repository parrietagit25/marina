Marina — dos formas de ejecutar la aplicación
==============================================

1) XAMPP (sin Docker)
---------------------
- Coloca el proyecto en htdocs (ej. C:\xampp\htdocs\marina).
- Crea la base "marina" en phpMyAdmin e importa sql/schema.sql (o deja que migrate_light añada tablas al entrar).
- Ejecuta una vez: php install/crear_admin.php
- Abre: http://localhost/marina/index.php

Por defecto config.php usa: MARINA_URL=/marina, DB_HOST=localhost, DB_USER=root, DB_PASS vacío.

PHP NO lee el archivo .env del proyecto: ese archivo lo usa solo "docker compose".
Si tienes un .env pensado para Docker en la misma carpeta del proyecto, XAMPP lo ignora.
Si alguna variable de entorno global en Windows entra en conflicto, copia
config.local.example.php -> config.local.php y define ahí DB_HOST, etc.

2) Docker
---------
- Ver docker-compose.yml y .env.example
- La app en contenedor usa variables inyectadas por Compose (DB_HOST=db, MARINA_URL vacío, etc.)
