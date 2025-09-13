<?php
// /ecobici/register.php
require_once __DIR__ . '/config/db.php';

$planId   = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
$planInfo = null;
if ($planId > 0) {
  try {
    $stmt = $pdo->prepare("SELECT id, nombre, precio FROM plans WHERE id = ?");
    $stmt->execute([$planId]);
    $planInfo = $stmt->fetch();
  } catch (Throwable $e) {
    $planInfo = null;
  }
}

$errors = [];
$success = false;

// Helper para mantener valores tras submit
function old($key) {
  return htmlspecialchars($_POST[$key] ?? '', ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Datos del formulario
  $name     = trim($_POST['name'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm  = $_POST['password_confirmation'] ?? '';
  $telefono = trim($_POST['telefono'] ?? '');
  $dpi      = trim($_POST['dpi'] ?? '');
  $planId   = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;

  // Validaciones
  if ($name === '') $errors[] = 'El nombre es obligatorio.';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El correo no es válido.';
  if (strlen($password) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
  if ($password !== $confirm) $errors[] = 'La confirmación de contraseña no coincide.';

  // (Opcional) Validaciones simples de formato
  if ($telefono !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $telefono)) {
    $errors[] = 'El teléfono contiene un formato no válido.';
  }
  if ($dpi !== '' && !preg_match('/^[0-9A-Za-z\-]{5,30}$/', $dpi)) {
    $errors[] = 'El DPI contiene un formato no válido.';
  }

  // Verificar email único
  if (empty($errors)) {
    try {
      $q = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
      $q->execute([$email]);
      if ($q->fetch()) {
        $errors[] = 'Ya existe una cuenta con ese correo.';
      }
    } catch (Throwable $e) {
      $errors[] = 'Error verificando el correo. Inténtalo de nuevo.';
    }
  }

  // Insertar
  if (empty($errors)) {
    try {
      $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
      $ins = $pdo->prepare("
        INSERT INTO users (name, email, password, role, dpi, telefono, created_at, updated_at)
        VALUES (?, ?, ?, 'cliente', ?, ?, NOW(), NOW())
      ");
      $ins->execute([$name, $email, $hash, $dpi ?: null, $telefono ?: null]);
      $success = true;

      // Si hay plan, podrías redirigir al flujo de pago/activación aquí.
      // header("Location: pay.php?plan_id=" . $planId); exit;

    } catch (Throwable $e) {
      $errors[] = 'No se pudo crear la cuenta. Intenta más tarde.';
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Crear cuenta | EcoBici Puerto Barrios</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Iconos -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- Tu CSS -->
  <link rel="stylesheet" href="cliente/styles/register.css">
</head>
<body>

<!-- Navbar simple (reutiliza estilos) -->
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top eco-navbar">
  <div class="container">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php#inicio">
      <i class="fa-solid fa-bicycle"></i> EcoBici
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div id="nav" class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="index.php#mision">Misión</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php#como-funciona">Cómo funciona</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php#planes">Planes</a></li>
        <li class="nav-item ms-lg-3">
          <a class="btn btn-success" href="login.php">
            <i class="fa fa-right-to-bracket me-1"></i> Iniciar sesión
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<main class="section-pad">
  <div class="container" style="max-width: 880px;">
    <div class="row g-4">
      <div class="col-12">
        <h1 class="h3 fw-bold mb-3">Crear cuenta</h1>
        <p class="text-muted mb-0">Regístrate para empezar a rodar por Puerto Barrios.</p>
      </div>

      <?php if ($planInfo): ?>
      <div class="col-12">
        <div class="alert alert-success d-flex align-items-center" role="alert">
          <i class="fa fa-bicycle me-2"></i>
          <div>
            Plan seleccionado: <strong><?= htmlspecialchars($planInfo['nombre']) ?></strong>
            — <strong>Q <?= number_format((float)$planInfo['precio'], 2) ?></strong>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
      <div class="col-12">
        <div class="alert alert-danger" role="alert">
          <strong>Ups…</strong> corrige lo siguiente:
          <ul class="mb-0 mt-2">
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="col-12">
        <div class="alert alert-success" role="alert">
          <strong>¡Cuenta creada!</strong> Ya podés iniciar sesión para activar tu plan.
        </div>
        <a class="btn btn-success" href="login.php"><i class="fa fa-right-to-bracket me-2"></i>Iniciar sesión</a>
        <?php if ($planInfo): ?>
          <a class="btn btn-outline-success ms-2" href="pay.php?plan_id=<?= (int)$planInfo['id'] ?>">
            <i class="fa fa-credit-card me-2"></i> Activar plan
          </a>
        <?php endif; ?>
      </div>
      <?php else: ?>

      <!-- Formulario -->
      <div class="col-12">
        <form method="post" class="row g-3 bg-white p-4 rounded-4 border shadow-sm">
          <input type="hidden" name="plan_id" value="<?= (int)$planId ?>">

          <div class="col-md-6">
            <label class="form-label">Nombre completo</label>
            <input type="text" name="name" class="form-control" value="<?= old('name') ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Correo</label>
            <input type="email" name="email" class="form-control" value="<?= old('email') ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" minlength="6" required>
            <div class="form-text">Mínimo 6 caracteres.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Confirmar contraseña</label>
            <input type="password" name="password_confirmation" class="form-control" minlength="6" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Teléfono (opcional)</label>
            <input type="text" name="telefono" class="form-control" value="<?= old('telefono') ?>" placeholder="+502 5555-5555">
          </div>

          <div class="col-md-6">
            <label class="form-label">DPI (opcional)</label>
            <input type="text" name="dpi" class="form-control" value="<?= old('dpi') ?>">
          </div>

          <div class="col-12 d-flex align-items-center justify-content-between">
            <a href="index.php#planes" class="btn btn-outline-primary">
              <i class="fa fa-arrow-left me-2"></i> Volver a planes
            </a>
            <button type="submit" class="btn btn-primary">
              <i class="fa fa-id-card me-2"></i> Crear cuenta
            </button>
          </div>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<footer class="py-4 border-top">
  <div class="container text-center small text-muted">
    © <?= date('Y') ?> EcoBici Puerto Barrios — Movilidad sostenible.
  </div>
</footer>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
