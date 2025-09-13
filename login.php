<?php
// /ecobici/login.php
session_start();
require_once __DIR__ . '/config/db.php';

$logoPath = '/ecobici/cliente/styles/logo.png';

$justLoggedIn = false;
$redirectTo   = '/ecobici/index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $st = $pdo->prepare("SELECT id,name,email,password,role FROM users WHERE email=? LIMIT 1");
    $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if ($u && password_verify($pass, $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => $u['id'],
            'name' => $u['name'],
            'email' => $u['email'],
            'role' => $u['role']
        ];
        $redirectTo = ($u['role'] === 'admin')
            ? '/ecobici/administrador/dashboard.php'
            : '/ecobici/index.php';
        $justLoggedIn = true;
    } else {
        $error = "Correo o contraseña incorrectos.";
    }
}

if (!$justLoggedIn && isset($_SESSION['user'])) {
    $to = (($_SESSION['user']['role'] ?? '') === 'admin')
        ? '/ecobici/administrador/dashboard.php'
        : '/ecobici/index.php';
    header("Location: {$to}");
    exit;
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>EcoBici • Iniciar sesión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --green: #16a34a;
            --green-2: #22c55e;
            --ring: #e5e7eb;
            --bg: #f8fafc;
            --shadow: 0 24px 60px rgba(2, 6, 23, .08);
            --addonW: 46px;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg);
            padding: 24px;
        }

        .card-login {
            width: min(520px, 94vw);
            border: 1px solid var(--ring);
            border-radius: 18px;
            box-shadow: var(--shadow);
            overflow: hidden
        }

        .card-top {
            padding: 16px 18px;
            border-bottom: 1px solid var(--ring);
            background: linear-gradient(135deg, rgba(34, 197, 94, .13), rgba(34, 197, 94, .06))
        }

        .brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            font-weight: 800
        }

        .brand img {
            height: 28px;
            width: auto;
            object-fit: contain
        }

        .btn-success {
            background: var(--green);
            border-color: var(--green)
        }

        .btn-success:hover {
            background: var(--green-2);
            border-color: var(--green-2)
        }

        .btn-ghost {
            border: 1px solid var(--ring);
            background: #fff
        }

        .btn-ghost:hover {
            border-color: #d1d5db
        }

        .helper {
            color: #64748b;
            font-size: .86rem
        }

        /* === Emparejar campos === */
        .input-group.equal {
            align-items: stretch;
        }

        .input-group.equal .form-control,
        .input-group.equal .input-group-text {
            height: 48px
        }

        .input-group.equal .input-group-text {
            width: var(--addonW);
            min-width: var(--addonW);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        /* Mantener bordes de Bootstrap tal cual para que los radios se alineen */

        /* El add-on del correo permanece visible; ocultamos SOLO el ícono */
        .addon-ghost i {
            opacity: 0;
        }

        /* foco */
        .form-control:focus {
            box-shadow: 0 0 0 .25rem rgba(34, 197, 94, .15);
            border-color: var(--green)
        }
    </style>
</head>

<body>

    <div class="card card-login">
        <div class="card-top">
            <div class="brand">
                <img src="<?= htmlspecialchars($logoPath) ?>" alt="EcoBici" onerror="this.style.display='none'">
                <span>EcoBici</span>
            </div>
        </div>

        <div class="card-body p-4">
            <h1 class="h4 text-center mb-1">Iniciar sesión</h1>
            <p class="helper text-center mb-4">Accede con tus credenciales.</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2">
                    <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off" class="vstack gap-3">
                <!-- Correo -->
                <div>
                    <label class="form-label">Correo</label>
                    <div class="input-group equal">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control"
                            placeholder="Por favor ingrese su correo" required>
                        <!-- add-on “gemelo” con mismo ancho; solo se oculta el ícono -->
                        <span class="input-group-text addon-ghost" aria-hidden="true">
                            <i class="bi bi-eye-slash"></i>
                        </span>
                    </div>
                    <div class="form-text helper">Usa el correo con el que te registraste.</div>
                </div>

                <!-- Contraseña -->
                <div>
                    <label class="form-label">Contraseña</label>
                    <div class="input-group equal">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control"
                            placeholder="Por favor ingrese su contraseña" required>
                        <button type="button" class="input-group-text" id="togglePass" title="Ver/ocultar">
                            <i class="bi bi-eye-slash" id="eyeIcon"></i>
                        </button>
                    </div>
                    <div class="form-text helper">No compartas tu contraseña.</div>
                </div>

                <!-- Acciones -->
                <div class="d-flex gap-2">
                    <a href="/ecobici/index.php" class="btn btn-ghost flex-fill">
                        <i class="bi bi-arrow-left me-1"></i> Regresar
                    </a>
                    <button class="btn btn-success flex-fill">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
                    </button>
                </div>
            </form>

            <div class="text-center mt-3">
                <small class="text-muted">¿No tienes cuenta? <a href="/ecobici/index.php#planes">Regístrate</a></small>
            </div>
        </div>
    </div>

    <!-- Modal Acceso concedido -->
    <div class="modal fade" id="loginOkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-body text-center p-4">
                    <div class="display-6 text-success mb-2"><i class="bi bi-shield-check"></i></div>
                    <h5 class="mb-1">Acceso concedido</h5>
                    <p class="helper mb-0">Redirigiendo…</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle ver/ocultar contraseña
        (function() {
            const pass = document.getElementById('password');
            const btn = document.getElementById('togglePass');
            const eye = document.getElementById('eyeIcon');
            if (btn && pass && eye) {
                btn.addEventListener('click', () => {
                    const show = pass.type === 'password';
                    pass.type = show ? 'text' : 'password';
                    eye.classList.toggle('bi-eye', show);
                    eye.classList.toggle('bi-eye-slash', !show);
                });
            }
        })();

        // Modal Acceso concedido y redirección
        (function() {
            const loginOk = <?= $justLoggedIn ? 'true' : 'false' ?>;
            if (!loginOk) return;
            const modalEl = document.getElementById('loginOkModal');
            const modal = new bootstrap.Modal(modalEl, {
                backdrop: 'static',
                keyboard: false
            });
            modal.show();
            setTimeout(() => {
                window.location.href = <?= json_encode($redirectTo) ?>;
            }, 1400);
        })();
    </script>
</body>

</html>