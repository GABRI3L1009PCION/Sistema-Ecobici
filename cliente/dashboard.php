<?php
// /ecobici/cliente/dashboard.php
declare(strict_types=1);
require_once __DIR__ . '/cliente_boot.php';

$userId = (int)($_SESSION['user']['id'] ?? 0);

/* ========= Usuario ========= */
$st = $pdo->prepare("
    SELECT id, name, email, role, dpi, telefono, fecha_nacimiento, foto
    FROM users
    WHERE id = ?
    LIMIT 1
");
$st->execute([$userId]);
$user = $st->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    session_destroy();
    header('Location: /ecobici/login.php');
    exit;
}

/* ========= Suscripción vigente (subscriptions + plans) ========= */
$subscription = null;
try {
    $sql = "
        SELECT s.id, s.estado, s.fecha_inicio, s.fecha_fin,
               p.nombre AS plan_nombre, p.precio AS plan_precio
        FROM subscriptions s
        JOIN plans p ON p.id = s.plan_id
        WHERE s.user_id = ?
        ORDER BY (s.estado = 'activa') DESC, s.fecha_fin DESC
        LIMIT 1
    ";
    $stm = $pdo->prepare($sql);
    $stm->execute([$userId]);
    $subscription = $stm->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    $subscription = null;
}

/* ========= KPIs ========= */
// Última bici usada
$lastBike = null;
try {
    $q = $pdo->prepare("
        SELECT b.codigo, b.tipo
        FROM trips t
        JOIN bikes b ON b.id = t.bike_id
        WHERE t.user_id = ?
        ORDER BY t.id DESC
        LIMIT 1
    ");
    $q->execute([$userId]);
    $lastBike = $q->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) { $lastBike = null; }

// Viajes últimos 30 días (usa trips.start_at)
$trips30 = 0;
try {
    $q = $pdo->prepare("
        SELECT COUNT(*) FROM trips
        WHERE user_id = ?
          AND start_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $q->execute([$userId]);
    $trips30 = (int)$q->fetchColumn();
} catch (Throwable $e) { $trips30 = 0; }

/* ========= Último pago (payments por subscription_id y estado completado) ========= */
$lastPay = null;
try {
    if ($subscription) {
        $qp = $pdo->prepare("
            SELECT id, monto, metodo, referencia, estado, created_at
            FROM payments
            WHERE subscription_id = ?
              AND estado = 'completado'
            ORDER BY id DESC
            LIMIT 1
        ");
        $qp->execute([$subscription['id']]);
        $lastPay = $qp->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $e) { $lastPay = null; }

/* ========= Helpers ========= */
function fmtDate(?string $d): string {
    if (!$d) return '-';
    $dt = new DateTime($d);
    return $dt->format('d/m/Y');
}
function diasRestantes(?string $fin): ?int {
    if (!$fin) return null;
    $hoy = new DateTime('today');
    $f   = new DateTime($fin);
    return (int)$hoy->diff($f)->format('%r%a');
}

/**
 * Convierte cualquier valor guardado en users.foto (URL, ruta relativa, ruta absoluta de Windows/Linux)
 * a una URL web válida para el navegador. Ajusta $BASE si tu proyecto no vive en /ecobici/.
 */
function resolve_photo_url(?string $raw): string {
    $placeholder = '/ecobici/cliente/styles/avatar_placeholder.png';
    if (!$raw) return $placeholder;

    $p = trim($raw);

    // 1) Si ya es http/https, devolver tal cual
    if (preg_match('~^https?://~i', $p)) return $p;

    // 2) Normalizar backslashes de Windows
    $p = str_replace('\\', '/', $p);

    // 3) Detectar base del proyecto automáticamente a partir del script
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Ej: /ecobici/cliente/dashboard.php  -> /ecobici/
    $autoBase = '/';
    if ($script) {
        $autoBase = rtrim(str_replace(['cliente/dashboard.php','cliente\\dashboard.php'], '', $script), '/').'/';
    }
    // Si el autoBase no contiene 'ecobici', usa valor por defecto
    $BASE = (stripos($autoBase, '/ecobici/') !== false) ? $autoBase : '/ecobici/';

    // 4) Si la ruta del FS incluye el document_root, recórtala
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    if ($docRoot && str_starts_with($p, $docRoot)) {
        $p = substr($p, strlen($docRoot)); // queda /EcoBici/uploads/...
    }

    // 5) Si empieza con / lo tomamos como ruta web, si no la hacemos relativa a $BASE
    $url = str_starts_with($p, '/') ? $p : $BASE . ltrim($p, '/');

    // 6) Validar existencia física (si podemos)
    $fsPath = $docRoot ? ($docRoot . $url) : null;
    if ($fsPath && is_file($fsPath)) return $url;

    // 7) Fallback: intenta en /uploads/users/<basename>
    $fallback = $BASE . 'uploads/users/' . basename($p);
    $fsFallback = $docRoot ? ($docRoot . $fallback) : null;
    if ($fsFallback && is_file($fsFallback)) return $fallback;

    return $placeholder;
}

/* Foto desde BD (ej.: 'uploads/users/xxxx.png' según tu dump) */
$foto = resolve_photo_url($user['foto'] ?? null);

$planNombre = $subscription['plan_nombre'] ?? null;
$planPrecio = isset($subscription['plan_precio']) ? (float)$subscription['plan_precio'] : null;
$estado     = $subscription['estado'] ?? 'sin_suscripcion';
$inicio     = $subscription['fecha_inicio'] ?? null;
$fin        = $subscription['fecha_fin'] ?? null;
$dias       = $fin ? diasRestantes($fin) : null;

// progreso
$pct = 0;
if ($inicio && $fin) {
    $di = new DateTime($inicio);
    $df = new DateTime($fin);
    $hoy = new DateTime();
    $total = max(1, (int)$di->diff($df)->format('%a'));
    $trans = (int)$di->diff(min($hoy, $df))->format('%a');
    $pct = max(0, min(100, (int)round(($trans / $total) * 100)));
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Dashboard Cliente | EcoBici</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5.3 vía CDN (CSS) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- CSS local personalizado -->
  <link rel="stylesheet" href="/ecobici/cliente/styles/dashboard.css">

  <style>
    :root{
      --eco-green:#1DAA4B; --eco-green-2:#128A3B; --eco-bg:#f6fff9; --eco-card:#ffffff;
      --eco-border:#dcf5e6; --eco-soft:#ecfff3;
    }
    html,body{background:var(--eco-bg);}
    .navbar { background:#eafff0; border-bottom:1px solid var(--eco-border); }
    .brand { color:var(--eco-green); }
    .card{ border:1px solid var(--eco-border); background:var(--eco-card); box-shadow:0 6px 18px rgba(0,0,0,.05); }
    .avatar-xl{ width:120px; height:120px; border-radius:50%; object-fit:cover; border:4px solid var(--eco-soft); }
    .badge-estado{ font-size:.9rem; padding:.45rem .75rem; border-radius:999px; }
    .badge-activa{ background:var(--eco-green); color:#fff; }
    .badge-pendiente{ background:#f1c40f; color:#000; }
    .badge-sin{ background:#95a5a6; color:#fff; }
    .btn-eco{ background:var(--eco-green); color:#fff; border:none; }
    .btn-eco:hover{ background:var(--eco-green-2); color:#fff; }
    .kpi{ background:#eafff0; border:1px dashed #c9efd7; border-radius:12px; padding:12px 14px; }
    .icon-circle{ width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:var(--eco-soft); color:var(--eco-green); }
    .launcher .card{ transition:transform .08s ease; }
    .launcher .card:hover{ transform:translateY(-2px); }
    .section-title{ font-weight:700; font-size:1.05rem; }
    .text-eco{ color:var(--eco-green); }
    .progress{ height:10px; }
    .muted{ color:#6b7b72; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand fw-bold brand" href="/ecobici/index.php">EcoBici</a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <span class="muted small me-2">Hola, <strong><?= htmlspecialchars($user['name']) ?></strong></span>
      <a class="btn btn-sm btn-outline-success" href="/ecobici/cliente/perfil.php">Editar perfil</a>
      <a class="btn btn-sm btn-danger" href="/ecobici/logout.php">Cerrar sesión</a>
    </div>
  </div>
</nav>

<main class="container py-4">
  <div class="row g-4">
    <!-- Perfil -->
    <div class="col-12 col-lg-4">
      <div class="card p-3">
        <div class="text-center">
          <img src="<?= htmlspecialchars($foto) ?>" class="avatar-xl mb-3" alt="Foto de perfil">
          <h5 class="mb-0"><?= htmlspecialchars($user['name']) ?></h5>
          <small class="muted"><?= htmlspecialchars($user['email']) ?></small>
          <div class="mt-2">
            <span class="badge rounded-pill text-bg-success-subtle text-success fw-semibold">
              <?= htmlspecialchars($user['role'] ?? 'cliente') ?>
            </span>
          </div>
        </div>
        <hr>
        <ul class="list-unstyled mb-0 small">
          <li class="d-flex justify-content-between mb-2">
            <span class="muted">Teléfono</span><span class="fw-semibold"><?= htmlspecialchars($user['telefono'] ?: '—') ?></span>
          </li>
          <li class="d-flex justify-content-between mb-2">
            <span class="muted">DPI</span><span class="fw-semibold"><?= htmlspecialchars($user['dpi'] ?: '—') ?></span>
          </li>
          <li class="d-flex justify-content-between">
            <span class="muted">Nacimiento</span><span class="fw-semibold"><?= $user['fecha_nacimiento'] ? htmlspecialchars(fmtDate($user['fecha_nacimiento'])) : '—' ?></span>
          </li>
        </ul>
      </div>

      <!-- KPIs -->
      <div class="card p-3 mt-3">
        <div class="section-title mb-2">Tu actividad</div>
        <div class="kpi mb-2 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <div class="icon-circle"><i class="bi bi-clock-history"></i></div>
            <div>
              <small class="muted d-block">Viajes (últimos 30 días)</small>
              <strong class="text-eco"><?= (int)$trips30 ?></strong>
            </div>
          </div>
          <a href="/ecobici/cliente/historial.php" class="btn btn-sm btn-outline-success">Ver</a>
        </div>
        <div class="kpi d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <div class="icon-circle"><i class="bi bi-bicycle"></i></div>
            <div>
              <small class="muted d-block">Última bicicleta usada</small>
              <strong class="text-eco"><?= $lastBike ? htmlspecialchars($lastBike['codigo'].' ('.$lastBike['tipo'].')') : '—' ?></strong>
            </div>
          </div>
          <a href="/ecobici/cliente/seleccionar_bici.php" class="btn btn-sm btn-outline-success">Usar bici</a>
        </div>
      </div>
    </div>

    <!-- Suscripción + Lanzador -->
    <div class="col-12 col-lg-8">
      <div class="card p-3">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="section-title mb-1">Mi suscripción</div>
            <?php if ($estado === 'activa'): ?>
              <span class="badge-estado badge-activa">Activa</span>
            <?php elseif ($estado === 'pendiente' || $estado === 'inactiva'): ?>
              <span class="badge-estado badge-pendiente"><?= htmlspecialchars($estado) ?></span>
            <?php else: ?>
              <span class="badge-estado badge-sin">Sin suscripción</span>
            <?php endif; ?>
          </div>
          <!-- Solo 'Gestionar' (sin botón Pagos aquí) -->
          <div>
            <a href="/ecobici/cliente/membresia.php" class="btn btn-sm btn-eco">Gestionar</a>
          </div>
        </div>

        <hr class="my-3">

        <?php if ($subscription): ?>
          <div class="row g-3 small">
            <div class="col-6 col-md-3">
              <span class="muted d-block">Plan</span>
              <span class="fw-semibold text-uppercase"><?= htmlspecialchars($planNombre) ?></span>
            </div>
            <div class="col-6 col-md-3">
              <span class="muted d-block">Precio</span>
              <span class="fw-semibold">Q <?= number_format((float)$planPrecio, 2) ?></span>
            </div>
            <div class="col-6 col-md-3">
              <span class="muted d-block">Inicio</span>
              <span class="fw-semibold"><?= htmlspecialchars(fmtDate($inicio)) ?></span>
            </div>
            <div class="col-6 col-md-3">
              <span class="muted d-block">Fin</span>
              <span class="fw-semibold"><?= htmlspecialchars($fin ? fmtDate($fin) : '—') ?></span>
            </div>
          </div>

          <div class="mt-3">
            <div class="d-flex justify-content-between small">
              <span class="muted">Progreso del periodo</span>
              <span class="muted">
                <?php if ($dias !== null): ?>
                  <?php if ($dias >= 0): ?>
                    <strong><?= $dias ?></strong> días restantes
                  <?php else: ?>
                    vencida hace <strong><?= abs($dias) ?></strong> días
                  <?php endif; ?>
                <?php endif; ?>
              </span>
            </div>
            <div class="progress">
              <div class="progress-bar bg-success" role="progressbar"
                   style="width: <?= (int)$pct ?>%;"
                   aria-valuenow="<?= (int)$pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
          </div>

          <?php if ($estado !== 'activa'): ?>
            <div class="alert alert-warning mt-3 mb-0 small">
              Tu suscripción no está activa. Ve a <a href="/ecobici/cliente/membresia.php" class="alert-link">Membresía</a>.
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="text-center py-4">
            <p class="muted mb-3">Aún no tienes una suscripción activa.</p>
            <a href="/ecobici/cliente/membresia.php" class="btn btn-eco btn-lg">Elegir un plan</a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Lanzador -->
      <div class="launcher row g-3 mt-3">
        <div class="col-12 col-md-6">
          <a href="/ecobici/cliente/estaciones.php" class="text-decoration-none">
            <div class="card p-3 h-100"><div class="d-flex align-items-center gap-3">
              <div class="icon-circle"><i class="bi bi-geo-alt"></i></div>
              <div><h6 class="mb-1 text-dark">Estaciones</h6><small class="muted">Mapa/listado y disponibilidad.</small></div>
            </div></div>
          </a>
        </div>
        <div class="col-12 col-md-6">
          <a href="/ecobici/cliente/rutas.php" class="text-decoration-none">
            <div class="card p-3 h-100"><div class="d-flex align-items-center gap-3">
              <div class="icon-circle"><i class="bi bi-signpost-2"></i></div>
              <div><h6 class="mb-1 text-dark">Rutas personalizadas</h6><small class="muted">Simulada o con mapa.</small></div>
            </div></div>
          </a>
        </div>
        <div class="col-12 col-md-6">
          <a href="/ecobici/cliente/co2.php" class="text-decoration-none">
            <div class="card p-3 h-100"><div class="d-flex align-items-center gap-3">
              <div class="icon-circle"><i class="bi bi-cloud-check"></i></div>
              <div><h6 class="mb-1 text-dark">Mi CO₂</h6><small class="muted">Impacto ambiental de mis viajes.</small></div>
            </div></div>
          </a>
        </div>
        <div class="col-12 col-md-6">
          <a href="/ecobici/cliente/seleccionar_bici.php" class="text-decoration-none">
            <div class="card p-3 h-100"><div class="d-flex align-items-center gap-3">
              <div class="icon-circle"><i class="bi bi-lightning-charge"></i></div>
              <div><h6 class="mb-1 text-dark">Elegir bicicleta</h6><small class="muted">Tradicional o eléctrica.</small></div>
            </div></div>
          </a>
        </div>
        <div class="col-12 col-md-6">
          <a href="/ecobici/cliente/historial.php" class="text-decoration-none">
            <div class="card p-3 h-100"><div class="d-flex align-items-center gap-3">
              <div class="icon-circle"><i class="bi bi-journal-text"></i></div>
              <div><h6 class="mb-1 text-dark">Historial</h6><small class="muted">Mis viajes por fecha/bici.</small></div>
            </div></div>
          </a>
        </div>
        <div class="col-12 col-md-6">
          <a href="/ecobici/cliente/reportes.php" class="text-decoration-none">
            <div class="card p-3 h-100"><div class="d-flex align-items-center gap-3">
              <div class="icon-circle"><i class="bi bi-graph-up"></i></div>
              <div><h6 class="mb-1 text-dark">Mis reportes</h6><small class="muted">Bicis, CO₂ y pagos.</small></div>
            </div></div>
          </a>
        </div>
        <div class="col-12 col-md-6">
          <a href="/ecobici/cliente/pagos_historial.php" class="text-decoration-none">
            <div class="card p-3 h-100"><div class="d-flex align-items-center gap-3">
              <div class="icon-circle"><i class="bi bi-credit-card"></i></div>
              <div><h6 class="mb-1 text-dark">Historial de Pagos</h6><small class="muted">Comprobantes y estado.</small></div>
            </div></div>
          </a>
        </div>
        <div class="col-12 col-md-6">
          <a href="/ecobici/cliente/membresia.php" class="text-decoration-none">
            <div class="card p-3 h-100"><div class="d-flex align-items-center gap-3">
              <div class="icon-circle"><i class="bi bi-badge-ad"></i></div>
              <div><h6 class="mb-1 text-dark">Membresía</h6><small class="muted">Cambiar/renovar plan.</small></div>
            </div></div>
          </a>
        </div>
        <div class="col-12 col-md-6">
          <a href="/ecobici/cliente/perfil.php" class="text-decoration-none">
            <div class="card p-3 h-100"><div class="d-flex align-items-center gap-3">
              <div class="icon-circle"><i class="bi bi-person-gear"></i></div>
              <div><h6 class="mb-1 text-dark">Mi perfil</h6><small class="muted">Editar datos y foto.</small></div>
            </div></div>
          </a>
        </div>
        <div class="col-12 col-md-6">
          <a href="/ecobici/cliente/reporte_dano.php" class="text-decoration-none">
            <div class="card p-3 h-100"><div class="d-flex align-items-center gap-3">
              <div class="icon-circle"><i class="bi bi-tools"></i></div>
              <div><h6 class="mb-1 text-dark">Reportar daño</h6><small class="muted">Mecanismo de confianza.</small></div>
            </div></div>
          </a>
        </div>
      </div>

<!-- Bootstrap 5.3 vía CDN (JS) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
