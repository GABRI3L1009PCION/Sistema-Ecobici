<?php
// /ecobici/cliente/perfil.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

// Guardas
if (!isset($_SESSION['user'])) { header('Location: /ecobici/login.php'); exit; }
$uid = (int)($_SESSION['user']['id'] ?? 0);

// CSRF
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];

// Cargar datos del usuario
$st = $pdo->prepare("SELECT id,name,apellido,email,dpi,telefono,fecha_nacimiento,foto FROM users WHERE id=? LIMIT 1");
$st->execute([$uid]);
$u = $st->fetch(PDO::FETCH_ASSOC);
if (!$u) { die('Usuario no encontrado'); }

// Helpers
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Mensaje flash
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mi perfil | EcoBici</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/ecobici/cliente/styles/app.css"><!-- tu tema verde/blanco -->
<style>
  body{background:#f1fff4;}
  .card{border-radius:16px;}
  .btn-eco{background:#19c37d;border:0;color:#fff;}
  .btn-eco:hover{filter:brightness(0.95);color:#fff;}
  .avatar{width:110px;height:110px;border-radius:50%;object-fit:cover;border:3px solid #e7f8ee;}
  .form-label{font-weight:600}
</style>
</head>
<body class="pb-5">
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 m-0">Mi perfil</h1>
    <a class="btn btn-outline-success" href="/ecobici/cliente/dashboard.php">← Volver</a>
  </div>

  <?php if($flash): ?>
    <div class="alert alert-<?= e($flash['type'] ?? 'info') ?> shadow-sm"><?= e($flash['msg'] ?? '') ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-md-4">
      <div class="card p-3">
        <div class="text-center">
          <img id="preview" class="avatar mb-3"
               src="<?= $u['foto'] ? '/ecobici/'.e($u['foto']) : 'https://ui-avatars.com/api/?size=128&name='.urlencode($u['name']) ?>"
               alt="Foto de perfil">
          <p class="text-muted small m-0">Formatos: JPG/PNG/WebP • Máx: 5 MB</p>
        </div>
      </div>
    </div>

    <div class="col-md-8">
      <div class="card p-4">
        <form action="/ecobici/cliente/perfil_guardar.php" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre</label>
              <input name="name" class="form-control" required value="<?= e($u['name']) ?>">
              <div class="invalid-feedback">Ingresa tu nombre.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Apellido</label>
              <input name="apellido" class="form-control" value="<?= e($u['apellido'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">Correo</label>
              <input name="email" type="email" class="form-control" required value="<?= e($u['email']) ?>">
              <div class="invalid-feedback">Correo no válido.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Teléfono</label>
              <input name="telefono" class="form-control" placeholder="+502 0000 0000" value="<?= e($u['telefono'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">DPI</label>
              <input name="dpi" class="form-control" value="<?= e($u['dpi'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Fecha de nacimiento</label>
              <input name="fecha_nacimiento" type="date" class="form-control" value="<?= e($u['fecha_nacimiento'] ?? '') ?>">
            </div>

            <div class="col-12">
              <label class="form-label">Foto (opcional)</label>
              <input id="foto" name="foto" type="file" accept=".jpg,.jpeg,.png,.webp" class="form-control">
            </div>

            <div class="col-12 d-flex gap-2 mt-2">
              <button class="btn btn-eco px-4" type="submit">Guardar cambios</button>
              <a class="btn btn-outline-secondary" href="/ecobici/cliente/dashboard.php">Cancelar</a>
            </div>
          </div>
        </form>
      </div>

      <div class="text-muted small mt-3">
        Consejo: mantén tu perfil actualizado para que tus comprobantes y reportes muestren bien tus datos.
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
      form.classList.add('was-validated');
    }, false);
  });
  const input = document.getElementById('foto');
  const prev  = document.getElementById('preview');
  if (input) {
    input.addEventListener('change', (e) => {
      const f = e.target.files?.[0];
      if (!f) return;
      if (f.size > 5*1024*1024) { alert('La imagen supera 5 MB.'); input.value=''; return; }
      prev.src = URL.createObjectURL(f);
    });
  }
})();
</script>
</body>
</html>
