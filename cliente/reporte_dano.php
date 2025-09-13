<?php
// /ecobici/cliente/reporte_dano.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

/* ====== BASE del módulo ====== */
$BASE = '/ecobici/cliente';

/* ====== Guardas ====== */
if (!isset($_SESSION['user'])) { header('Location: /ecobici/login.php'); exit; }
$uid = (int)$_SESSION['user']['id'];

/* ====== CSRF ====== */
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];

/* ====== Datos útiles ====== */
// 1) Bicicleta en uso (viaje abierto)
$st = $pdo->prepare("
  SELECT t.bike_id, b.codigo, b.tipo
  FROM trips t
  JOIN bikes b ON b.id = t.bike_id
  WHERE t.user_id=? AND t.end_at IS NULL
  ORDER BY t.start_at DESC
  LIMIT 1
");
$st->execute([$uid]);
$actual = $st->fetch(PDO::FETCH_ASSOC);

// 2) Última bicicleta usada (último viaje finalizado)
$lt = $pdo->prepare("
  SELECT t.bike_id, b.codigo, b.tipo
  FROM trips t
  JOIN bikes b ON b.id = t.bike_id
  WHERE t.user_id=? AND t.end_at IS NOT NULL
  ORDER BY t.end_at DESC, t.id DESC
  LIMIT 1
");
$lt->execute([$uid]);
$ultima = $lt->fetch(PDO::FETCH_ASSOC);

// 3) Últimos reportes del usuario
$rep = $pdo->prepare("
  SELECT dr.id, dr.created_at, dr.estado, b.codigo
  FROM damage_reports dr
  JOIN bikes b ON b.id = dr.bike_id
  WHERE dr.user_id=? ORDER BY dr.id DESC LIMIT 8
");
$rep->execute([$uid]);
$misReportes = $rep->fetchAll(PDO::FETCH_ASSOC);

/* ====== Helpers ====== */
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ====== Mensajes (flash + querystring) ====== */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
if (!$flash && isset($_GET['ok']))  $flash = ['type'=>'success','msg'=>'Reporte enviado. ¡Gracias por avisar!'];
if (!$flash && isset($_GET['err'])) $flash = ['type'=>'danger', 'msg'=>'No se pudo enviar el reporte. Revisa el formulario.'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reportar daño | EcoBici</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $BASE ?>/styles/app.css">
<style>
  body{background:#f1fff4;}
  .card{border-radius:16px}
  .btn-eco{background:#19c37d;border:0;color:#fff}
  .btn-eco:hover{filter:brightness(0.95);color:#fff}
  .avatar-bike{width:42px;height:42px;border-radius:8px;object-fit:cover;background:#e7f8ee;display:inline-flex;align-items:center;justify-content:center}
  .chip{background:#e7f8ee;border-radius:999px;padding:.25rem .6rem;font-size:.85rem}
</style>
</head>
<body class="pb-5">
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 m-0">Reportar daño</h1>
    <a href="<?= $BASE ?>/dashboard.php" class="btn btn-outline-success">← Volver</a>
  </div>

  <?php if($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> shadow-sm"><?= e($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Formulario -->
    <div class="col-lg-7">
      <div class="card p-4">
        <form class="needs-validation" novalidate method="post" enctype="multipart/form-data"
              action="<?= $BASE ?>/reportar_dano_guardar.php">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="MAX_FILE_SIZE" value="5242880"><!-- 5 MB -->
          <h2 class="h5 mb-3">Detalles del reporte</h2>

          <?php if ($actual): ?>
            <div class="mb-3">
              <label class="form-label">Bicicleta detectada en uso</label>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar-bike">🚲</div>
                <div>
                  <div class="fw-semibold">
                    <?= e($actual['codigo']) ?>
                    <span class="text-muted">(<?= e($actual['tipo']) ?>)</span>
                  </div>
                  <div class="small text-muted">Se usará por defecto para el reporte.</div>
                </div>
              </div>
              <input type="hidden" name="bike_id" value="<?= (int)$actual['bike_id'] ?>">
            </div>
            <div class="text-muted small mb-3">¿No es esta bici? Ingresa el código abajo.</div>
          <?php elseif ($ultima): ?>
            <div class="mb-3">
              <label class="form-label">Última bicicleta usada</label>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar-bike">🕘</div>
                <div>
                  <div class="fw-semibold">
                    <?= e($ultima['codigo']) ?>
                    <span class="text-muted">(<?= e($ultima['tipo']) ?>)</span>
                  </div>
                  <div class="small text-muted">No tienes un viaje activo. Usamos esta como sugerencia.</div>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label">Código de bicicleta (opcional)</label>
            <input
              name="codigo"
              class="form-control"
              placeholder="Ej: EB-0009"
              value="<?= !$actual && $ultima ? e($ultima['codigo']) : '' ?>"
            >
            <div class="form-text">
              Si lo indicas, se usará ese código (prioridad sobre la bici en uso si existiera).
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Descripción del daño</label>
            <textarea name="nota" class="form-control" rows="4" required
                      placeholder="Ej: Freno delantero flojo, luz trasera no enciende, pinchazo, etc."></textarea>
            <div class="invalid-feedback">Describe el problema.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Foto (opcional, máx. 5 MB)</label>
            <input id="foto" type="file" name="foto" class="form-control" accept=".jpg,.jpeg,.png,.webp">
            <div class="form-text">Una imagen ayuda al equipo de mantenimiento.</div>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-eco px-4" type="submit">Enviar reporte</button>
            <a class="btn btn-outline-secondary" href="<?= $BASE ?>/dashboard.php">Cancelar</a>
          </div>
        </form>
      </div>
    </div>

    <!-- Historial corto -->
    <div class="col-lg-5">
      <div class="card p-3">
        <h2 class="h6 px-2 mt-2">Mis últimos reportes</h2>
        <ul class="list-group list-group-flush">
          <?php if (!$misReportes): ?>
            <li class="list-group-item">Aún no has enviado reportes.</li>
          <?php else: foreach($misReportes as $r): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-semibold"><?= e($r['codigo']) ?></div>
                <div class="small text-muted"><?= e($r['created_at']) ?></div>
              </div>
              <span class="chip text-success"><?= e($r['estado']) ?></span>
            </li>
          <?php endforeach; endif; ?>
        </ul>
      </div>
      <p class="small text-muted mt-3 px-1">
        Los reportes se guardan con estado <strong>nuevo</strong> y quedan vinculados a la bici y a tu cuenta.
      </p>
    </div>
  </div>
</div>

<script>
(() => {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form=>{
    form.addEventListener('submit', e=>{
      if(!form.checkValidity()){ e.preventDefault(); e.stopPropagation(); }
      form.classList.add('was-validated');
    });
  });

  const input = document.getElementById('foto');
  const LIMITE = 5 * 1024 * 1024; // 5 MB
  if (input) input.addEventListener('change', (e)=>{
    const f = e.target.files?.[0]; if(!f) return;
    if (f.size > LIMITE) { alert('La imagen supera 5 MB'); input.value=''; }
  });
})();
</script>
</body>
</html>
