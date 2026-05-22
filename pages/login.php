<?php
/**
 * Login
 */
require_once __DIR__ . '/../includes/permisos.php';

if (usuarioActual()) {
    redirigir(MARINA_URL . '/index.php');
}

$error = '';
if (enviado()) {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($email === '' || $pass === '') {
        $error = 'Email y contraseña obligatorios.';
    } else {
        try {
            $pdo = getDb();
            $st = $pdo->prepare('SELECT id, nombre, email, password_hash, activo FROM usuarios WHERE email = ? LIMIT 1');
            $st->execute([$email]);
            $u = $st->fetch();
            if ($u && $u['activo'] && password_verify($pass, $u['password_hash'])) {
                $_SESSION['usuario'] = [
                    'id' => (int) $u['id'],
                    'nombre' => $u['nombre'],
                    'email' => $u['email'],
                ];
                marina_permisos_hidratar_sesion($pdo, (int) $u['id']);
                require_once __DIR__ . '/../includes/auditoria.php';
                marina_auditoria_registrar($pdo, 'login', 'login', 'Inicio de sesión', (int) $u['id']);
                redirigir(MARINA_URL . '/index.php');
            }
            $error = 'Usuario o contraseña incorrectos.';
        } catch (PDOException $e) {
            $error = 'Error de conexión. ¿Creaste la base de datos y ejecutaste el schema?';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Marina</title>
    <link rel="stylesheet" href="<?= MARINA_URL ?>/assets/css/estilos.css">
</head>
<body class="login-page">
    <div class="login-box">
        <h1>Marina</h1>
        <form method="post" action="">
            <?php if ($error): ?>
                <p class="error"><?= e($error) ?></p>
            <?php endif; ?>
            <label>Email</label>
            <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
            <label>Contraseña</label>
            <input type="password" name="password" required>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
