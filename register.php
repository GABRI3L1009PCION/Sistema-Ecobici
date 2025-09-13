<?php
// /ecobici/register.php
require_once __DIR__ . '/config/db.php';

/* --- Migración ligera: agrega columnas si no existen (MySQL 8+) --- */
try {
  $pdo->exec("
    ALTER TABLE users
      ADD COLUMN IF NOT EXISTS fecha_nacimiento DATE NULL,
      ADD COLUMN IF NOT EXISTS foto VARCHAR(255) NULL
  ");
} catch (Throwable $e) {
  // Si falla (MySQL viejo), seguimos sin romper el registro. Las columnas pueden no guardarse.
}

$planId   = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
$planInfo = null;
if ($planId > 0) {
  try {
    $stmt = $pdo->prepare("SELECT id, nombre, precio FROM plans WHERE id = ?");
    $stmt->execute([$planId]);
    $planInfo = $stmt->fetch();
  } catch (Throwable $e) { $planInfo = null; }
}

$errors = [];
$success = false;

function old($key) {
  return htmlspecialchars($_POST[$key] ?? '', ENT_QUOTES, 'UTF-8');
}

/* Límites/fechas para validación DOB */
$hoy = new DateTime('today');
$minDob = new DateTime('1900-01-01');
$maxDob = (clone $hoy)->modify('-12 years'); // mínimo 12 años

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name     = trim($_POST['name'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm  = $_POST['password_confirmation'] ?? '';
  $telefono = trim($_POST['telefono'] ?? '');
  $dpi      = trim($_POST['dpi'] ?? '');
  $dobStr   = trim($_POST['fecha_nacimiento'] ?? ''); // YYYY-MM-DD
  $planId   = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;

  /* Validaciones básicas */
  if ($name === '') $errors[] = 'El nombre es obligatorio.';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El correo no es válido.';
  if (strlen($password) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
  if ($password !== $confirm) $errors[] = 'La confirmación de contraseña no coincide.';

  /* Teléfono GT (+502 ####-####) — opcional */
  if ($telefono !== '') {
    $telDigits = preg_replace('/\D+/', '', $telefono);
    if (str_starts_with($telDigits, '502')) $telDigits = substr($telDigits, 3);
    if (!preg_match('/^\d{8}$/', $telDigits)) {
      $errors[] = 'El teléfono debe tener formato +502 1234-5678.';
    } else {
      $telefono = '+502 ' . substr($telDigits,0,4) . '-' . substr($telDigits,4);
    }
  }

  /* DPI GT (13 dígitos) — opcional */
  if ($dpi !== '') {
    $dpiDigits = preg_replace('/\D+/', '', $dpi);
    if (!preg_match('/^\d{13}$/', $dpiDigits)) {
      $errors[] = 'El DPI debe tener 13 dígitos (####-#####-####).';
    } else {
      $dpi = substr($dpiDigits,0,4) . '-' . substr($dpiDigits,4,5) . '-' . substr($dpiDigits,9,4);
    }
  }

  /* Fecha de nacimiento — opcional pero validada si viene */
  $dob = null;
  if ($dobStr !== '') {
    $dob = DateTime::createFromFormat('Y-m-d', $dobStr) ?: null;
    if (!$dob) {
      $errors[] = 'La fecha de nacimiento no es válida.';
    } else {
      if ($dob < $minDob || $dob > $maxDob) {
        $errors[] = 'La fecha de nacimiento debe estar entre 1900-01-01 y '.$maxDob->format('Y-m-d').'.';
      }
    }
  }

  /* Foto — opcional: JPG/PNG/WebP ≤ 2MB */
  $fotoPath = null;
  if (!empty($_FILES['foto']['name'])) {
    $file = $_FILES['foto'];
    if ($file['error'] === UPLOAD_ERR_OK) {
      if ($file['size'] > 5 * 1024 * 1024) {
        $errors[] = 'La foto no debe superar 5MB.';
      } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($allowed[$mime])) {
          $errors[] = 'Formato de imagen no permitido. Usa JPG, PNG o WebP.';
        } else {
          $ext = $allowed[$mime];
          $baseDir = __DIR__ . '/uploads/users';
          if (!is_dir($baseDir)) { @mkdir($baseDir, 0775, true); }
          $unique = bin2hex(random_bytes(8)) . '_' . time();
          $filename = $unique . '.' . $ext;
          $dest = $baseDir . '/' . $filename;
          if (move_uploaded_file($file['tmp_name'], $dest)) {
            // ruta relativa para guardar en BD
            $fotoPath = 'uploads/users/' . $filename;
          } else {
            $errors[] = 'No se pudo guardar la foto. Intenta de nuevo.';
          }
        }
      }
    } else {
      $errors[] = 'Error subiendo la foto (código '.$file['error'].').';
    }
  }

  /* Email único */
  if (empty($errors)) {
    try {
      $q = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
      $q->execute([$email]);
      if ($q->fetch()) $errors[] = 'Ya existe una cuenta con ese correo.';
    } catch (Throwable $e) {
      $errors[] = 'Error verificando el correo. Inténtalo de nuevo.';
    }
  }

  /* Insertar usuario */
  if (empty($errors)) {
    try {
      $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

      // Intentamos insertar incluyendo nuevas columnas; si falla, reintentamos sin ellas.
      $ok = false;
      try {
        $ins = $pdo->prepare("
          INSERT INTO users (name, email, password, role, dpi, telefono, fecha_nacimiento, foto, created_at, updated_at)
          VALUES (?, ?, ?, 'cliente', ?, ?, ?, ?, NOW(), NOW())
        ");
        $ins->execute([
          $name, $email, $hash,
          $dpi ?: null, $telefono ?: null,
          $dob ? $dob->format('Y-m-d') : null,
          $fotoPath
        ]);
        $ok = true;
      } catch (Throwable $e) {
        // fallback para MySQL viejo sin columnas nuevas
        $ins2 = $pdo->prepare("
          INSERT INTO users (name, email, password, role, dpi, telefono, created_at, updated_at)
          VALUES (?, ?, ?, 'cliente', ?, ?, NOW(), NOW())
        ");
        $ins2->execute([$name, $email, $hash, $dpi ?: null, $telefono ?: null]);
        $ok = true;
      }

      if ($ok) $success = true;

    } catch (Throwable $e) {
      $errors[] = 'No se pudo crear la cuenta. Intenta más tarde.';
    }
  }
}

/* Para atributos del input date */
$maxDobAttr = htmlspecialchars($maxDob->format('Y-m-d'), ENT_QUOTES, 'UTF-8');
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
  <!-- CSS propio (ya existente) -->
  <link rel="stylesheet" href="cliente/styles/register.css">
</head>
<body>

<div class="container min-vh-100 d-flex align-items-center py-3">
  <div class="row justify-content-center w-100">
    <div class="col-12 col-md-10 col-lg-9 col-xl-8">

      <div class="card shadow-sm border-0 rounded-4 overflow-hidden register-card">
        <div class="px-4 py-2 bg-success-subtle border-bottom small fw-semibold">EcoBici</div>

        <div class="card-body p-4 p-md-4">
          <h1 class="h4 text-center fw-bold mb-1">Crear cuenta</h1>
          <p class="text-center text-muted mb-3">Regístrate y empieza a rodar por Puerto Barrios.</p>

          <?php if ($planInfo): ?>
            <div class="alert alert-success py-2 mb-3 d-flex align-items-center" role="alert">
              <i class="fa fa-bicycle me-2"></i>
              <div class="small">Plan: <strong><?= htmlspecialchars($planInfo['nombre']) ?></strong> — <strong>Q <?= number_format((float)$planInfo['precio'], 2) ?></strong></div>
            </div>
          <?php endif; ?>

          <?php if (!empty($errors)): ?>
            <div id="formErrors" class="alert alert-danger alert-dismissible fade show py-2" role="alert">
              <strong class="small d-block mb-1">Revisa:</strong>
              <ul class="mb-0 small">
                <?php foreach ($errors as $e): ?>
                  <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
              </ul>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
          <?php endif; ?>

          <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
              <strong>¡Cuenta creada!</strong> Ya podés iniciar sesión para activar tu plan.
            </div>
            <div class="d-flex gap-3 justify-content-center">
              <a href="index.php"
                 class="btn btn-outline-secondary btn-smh d-inline-flex align-items-center gap-2"
                 onclick="if (history.length > 1) { event.preventDefault(); history.back(); }">
                <i class="fa fa-arrow-left"></i> Regresar
              </a>
              <a class="btn btn-success btn-smh" href="login.php">
                <i class="fa fa-right-to-bracket me-1"></i> Iniciar sesión
              </a>
            </div>
          <?php else: ?>

          <!-- Form: 2 columnas en lg, 1 en móvil -->
          <form method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="plan_id" value="<?= (int)$planId ?>">

            <div class="row g-3 row-cols-1 row-cols-lg-2">
              <!-- Nombre -->
              <div>
                <label class="form-label mb-1">Nombre completo</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fa fa-user"></i></span>
                  <input type="text" name="name" class="form-control" value="<?= old('name') ?>" placeholder="Tu nombre y apellido" required>
                </div>
              </div>

              <!-- Correo -->
              <div>
                <label class="form-label mb-1">Correo</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                  <input type="email" name="email" class="form-control" value="<?= old('email') ?>" placeholder="ejemplo@correo.com" required>
                </div>
              </div>

              <!-- Contraseña -->
              <div>
                <label class="form-label mb-1">Contraseña</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fa fa-lock"></i></span>
                  <input id="password" type="password" name="password" class="form-control" minlength="6" placeholder="Mínimo 6 caracteres" required>
                  <button class="btn btn-outline-secondary" type="button" id="togglePass"><i class="fa fa-eye-slash"></i></button>
                </div>
              </div>

              <!-- Confirmación -->
              <div>
                <label class="form-label mb-1">Confirmar</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fa fa-lock"></i></span>
                  <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" minlength="6" placeholder="Repite tu contraseña" required>
                  <button class="btn btn-outline-secondary" type="button" id="togglePass2"><i class="fa fa-eye-slash"></i></button>
                </div>
              </div>

              <!-- Teléfono -->
              <div>
                <label class="form-label mb-1">Teléfono (Guatemala)</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fa fa-phone"></i></span>
                  <input
                    id="telefono"
                    type="tel"
                    name="telefono"
                    class="form-control"
                    value="<?= old('telefono') ?>"
                    placeholder="+502 1234-5678"
                    inputmode="numeric"
                    autocomplete="tel"
                    maxlength="16"
                    pattern="\+502\s\d{4}-\d{4}"
                    title="Formato: +502 1234-5678"
                    oninput="formatearTelefonoGT(this)"
                  >
                </div>
              </div>

              <!-- DPI -->
              <div>
                <label class="form-label mb-1">DPI (opcional)</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fa fa-id-card"></i></span>
                  <input
                    id="dpi"
                    type="text"
                    name="dpi"
                    class="form-control"
                    value="<?= old('dpi') ?>"
                    placeholder="Documento personal"
                    inputmode="numeric"
                    pattern="\d{13}|\d{4}-\d{5}-\d{4}"
                    maxlength="15"
                    title="13 dígitos o ####-#####-####"
                    oninput="formatearDPI(this)"
                  >
                </div>
              </div>

              <!-- Fecha de nacimiento -->
              <div>
                <label class="form-label mb-1">Fecha de nacimiento</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                  <input
                    type="date"
                    name="fecha_nacimiento"
                    class="form-control"
                    value="<?= old('fecha_nacimiento') ?>"
                    min="1900-01-01"
                    max="<?= $maxDobAttr ?>"
                  >
                </div>
                <small class="text-muted">Debes tener al menos 12 años.</small>
              </div>

              <!-- Foto -->
              <div>
                <label class="form-label mb-1">Foto (JPG/PNG/WebP, máx. 5MB)</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fa fa-image"></i></span>
                  <input
                    type="file"
                    name="foto"
                    class="form-control"
                    accept="image/jpeg,image/png,image/webp"
                    onchange="previewFoto(this)"
                  >
                </div>
                <div class="mt-2">
                  <img id="fotoPreview" src="" alt="" class="img-thumbnail d-none" style="max-height:110px;">
                </div>
              </div>
            </div>

            <div class="d-flex gap-3 justify-content-center mt-3">
              <a href="index.php"
                 class="btn btn-outline-secondary btn-smh d-inline-flex align-items-center gap-2"
                 onclick="if (history.length > 1) { event.preventDefault(); history.back(); }">
                <i class="fa fa-arrow-left"></i> Regresar
              </a>
              <button type="submit" class="btn btn-success btn-smh">
                <i class="fa fa-id-card me-2"></i> Crear cuenta
              </button>
            </div>

            <div class="text-center mt-2">
              <small class="text-muted">¿Ya tienes cuenta? <a href="login.php" class="text-decoration-none">Inicia sesión</a></small>
            </div>
          </form>

          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle mostrar/ocultar contraseña
const bindToggle = (btnId, inputId) => {
  const btn = document.getElementById(btnId);
  const inp = document.getElementById(inputId);
  if (!btn || !inp) return;
  btn.addEventListener('click', () => {
    const isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    const i = btn.querySelector('i');
    if (i) i.className = isText ? 'fa fa-eye-slash' : 'fa fa-eye';
  });
};
bindToggle('togglePass','password');
bindToggle('togglePass2','password_confirmation');

// Fade-out automático de la alerta de errores
const err = document.getElementById('formErrors');
if (err) {
  setTimeout(() => {
    err.classList.remove('show');        // activa el fade
    setTimeout(() => err.remove(), 600); // limpia el nodo al final de la transición
  }, 6000);
}

// Máscara Teléfono GT: +502 ####-####
function formatearTelefonoGT(el){
  let d = (el.value.match(/\d+/g) || []).join(''); // solo dígitos
  if (d.startsWith('502')) d = d.slice(3);
  d = d.slice(0, 8);
  let visible = '+502';
  if (d.length > 0) {
    visible += ' ';
    visible += d.length <= 4 ? d : d.slice(0,4) + '-' + d.slice(4);
  }
  el.value = visible;
}
document.getElementById('telefono')?.addEventListener('blur', e => {
  const el = e.currentTarget;
  let d = (el.value.match(/\d+/g) || []).join('');
  if (d.startsWith('502')) d = d.slice(3);
  if (d.length === 8) el.value = `+502 ${d.slice(0,4)}-${d.slice(4)}`;
});

// Máscara DPI GT: ####-#####-####
function formatearDPI(el) {
  const digits = el.value.replace(/\D/g, '').slice(0, 13);
  if (digits.length <= 4) {
    el.value = digits;
  } else if (digits.length <= 9) {
    el.value = digits.slice(0,4) + '-' + digits.slice(4);
  } else {
    el.value = digits.slice(0,4) + '-' + digits.slice(4,9) + '-' + digits.slice(9);
  }
}

// Preview de foto
function previewFoto(input){
  const img = document.getElementById('fotoPreview');
  if (!input.files || !input.files[0]) { img.classList.add('d-none'); img.src=''; return; }
  const file = input.files[0];
  const ok = ['image/jpeg','image/png','image/webp'].includes(file.type);
  if (!ok) { img.classList.add('d-none'); img.src=''; return; }
  const url = URL.createObjectURL(file);
  img.src = url;
  img.classList.remove('d-none');
}
</script>
</body>
</html>
