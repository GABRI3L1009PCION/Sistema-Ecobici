<?php
// /ecobici/login.php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/db.php';

// === Configura el prefijo base si tu app no vive en la raíz del host ===
$BASE = '/ecobici';

// --- Si ya hay sesión, redirige según rol ---
if (!empty($_SESSION['user']) && !empty($_SESSION['user']['role'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header("Location: {$BASE}/administrador/dashboard.php");
        exit;
    } else {
        header("Location: {$BASE}/cliente/dashboard.php");
        exit;
    }
}

if (!isset($pdo)) {
    die('Error: $pdo no está definido. Revisa config/db.php');
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Helpers
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// CSRF mínimo
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(16));
}
function csrf(): string
{
    return $_SESSION['_csrf'] ?? '';
}
function csrf_check(?string $t): bool
{
    return hash_equals($_SESSION['_csrf'] ?? '', $t ?? '');
}

// Estado del form
$errors = [];
$emailVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        $errors[] = 'Token inválido. Vuelve a intentarlo.';  // evita CSRF
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $emailVal = $email;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ingresa un correo válido.';
        }
        if ($password === '') {
            $errors[] = 'Ingresa tu contraseña.';
        }

        if (empty($errors)) {
            try {
                $st = $pdo->prepare("SELECT id,name,email,password,role FROM users WHERE email=? LIMIT 1");
                $st->execute([$email]);
                $user = $st->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    // Rehash si el algoritmo por defecto cambió
                    if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
                        $nh = password_hash($password, PASSWORD_BCRYPT);
                        $up = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
                        $up->execute([$nh, (int)$user['id']]);
                    }

                    session_regenerate_id(true);
                    // Compat: claves sueltas + bloque unificado
                    $_SESSION['user_id']   = (int)$user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'] ?: 'cliente';
                    $_SESSION['user'] = [
                        'id'    => (int)$user['id'],
                        'name'  => $user['name'],
                        'email' => $user['email'],
                        'role'  => $user['role'] ?: 'cliente',
                    ];

                    if ($_SESSION['user_role'] === 'admin') {
                        header("Location: {$BASE}/administrador/dashboard.php");
                        exit;
                    } else {
                        header("Location: {$BASE}/cliente/dashboard.php");
                        exit;
                    }
                } else {
                    $errors[] = 'Credenciales inválidas. Verifica tu correo y contraseña.';
                }
            } catch (Throwable $e) {
                // Descomenta si necesitas ver el error real durante desarrollo:
                // $errors[] = 'Error: '.$e->getMessage();
                $errors[] = 'Error de autenticación. Intenta de nuevo.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Iniciar sesión | EcoBici Puerto Barrios</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap + Iconos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- Reutilizamos estilos del register para consistencia -->
    <link rel="stylesheet" href="cliente/styles/register.css">
    <style>
        .btn-smh {
            padding: .55rem 1rem;
            border-radius: .75rem;
        }

        .register-card {
            backdrop-filter: blur(4px);
        }

        .input-group .btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .input-group-text {
            min-width: 42px;
            justify-content: center;
        }
    </style>
</head>

<body>

    <div class="container min-vh-100 d-flex align-items-center py-3">
        <div class="row justify-content-center w-100">
            <div class="col-12 col-md-10 col-lg-9 col-xl-8">

                <div class="card shadow-sm border-0 rounded-4 overflow-hidden register-card">
                    <div class="px-4 py-2 bg-success-subtle border-bottom small fw-semibold">EcoBici</div>

                    <div class="card-body p-4 p-md-4">
                        <h1 class="h4 text-center fw-bold mb-1">Iniciar sesión</h1>
                        <p class="text-center text-muted mb-3">Bienvenido de vuelta a EcoBici Puerto Barrios.</p>

                        <?php if (!empty($errors)): ?>
                            <div id="formErrors" class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                                <strong class="small d-block mb-1">Ups…</strong>
                                <ul class="mb-0 small">
                                    <?php foreach ($errors as $e): ?>
                                        <li><?= e($e) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="" novalidate>
                            <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">

                            <!-- Correo -->
                            <div class="mb-3">
                                <label class="form-label mb-1" for="email">Correo</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                                    <input id="email" type="email" name="email" class="form-control"
                                        placeholder="ejemplo@correo.com" value="<?= e($emailVal) ?>" required>
                                </div>
                            </div>

                            <!-- Contraseña -->
                            <div class="mb-3">
                                <label class="form-label mb-1" for="password">Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-lock"></i></span>
                                    <input id="password" type="password" name="password" class="form-control" placeholder="Tu contraseña" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePass" aria-label="Mostrar u ocultar contraseña">
                                        <i class="fa fa-eye-slash"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Acciones -->
                            <div class="d-flex gap-3 justify-content-center mt-2">
                                <a href="index.php"
                                    class="btn btn-outline-secondary btn-smh d-inline-flex align-items-center gap-2"
                                    onclick="if (history.length > 1) { event.preventDefault(); history.back(); }"
                                    title="Regresar">
                                    <i class="fa fa-arrow-left"></i> Regresar
                                </a>
                                <button type="submit" class="btn btn-success btn-smh">
                                    <i class="fa fa-right-to-bracket me-2"></i> Iniciar sesión
                                </button>
                            </div>

                            <div class="text-center mt-2">
                                <small class="text-muted">¿No tienes cuenta?
                                    <a href="register.php" class="text-decoration-none">Crear cuenta</a>
                                </small>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle mostrar/ocultar contraseña
        const btn = document.getElementById('togglePass');
        const inp = document.getElementById('password');
        btn?.addEventListener('click', () => {
            const isText = inp.type === 'text';
            inp.type = isText ? 'password' : 'text';
            const icon = btn.querySelector('i');
            if (icon) icon.className = isText ? 'fa fa-eye-slash' : 'fa fa-eye';
        });

        // Auto fade-out de alertas tras 6s
        const err = document.getElementById('formErrors');
        if (err) {
            setTimeout(() => {
                err.classList.remove('show');
                setTimeout(() => err.remove(), 600);
            }, 6000);
        }
    </script>
</body>

</html>