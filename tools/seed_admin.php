<?php
// /ecobici/tools/seed_admin.php
require_once __DIR__ . '/../config/db.php';

$name  = 'Mig Admin';
$email = 'admin@local.com';   // <- usa este correo para iniciar sesión
$pass  = '12345678';

try {
    $hash = password_hash($pass, PASSWORD_BCRYPT);

    // ¿ya existe por email?
    $st = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if ($u) {
        $upd = $pdo->prepare("UPDATE users SET name=?, password=?, role='admin', updated_at=NOW() WHERE id=?");
        $upd->execute([$name, $hash, $u['id']]);
        echo "✔ Admin ACTUALIZADO: $name ($email)";
    } else {
        $ins = $pdo->prepare("INSERT INTO users (name,email,password,role,created_at,updated_at) VALUES (?,?,?,'admin',NOW(),NOW())");
        $ins->execute([$name, $email, $hash]);
        echo "✔ Admin CREADO: $name ($email)";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
