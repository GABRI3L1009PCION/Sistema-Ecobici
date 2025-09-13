<?php
// /ecobici/cliente/mis_reportes.php
declare(strict_types=1);

// ===== Sesión & DB =====
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php'; // Debe definir $pdo (PDO MySQL)
if (!isset($_SESSION['user'])) { header('Location: /ecobici/login.php'); exit; }

$uid = (int)($_SESSION['user']['id'] ?? 0);

// ===== Helpers =====
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dmy($s){ return $s ? date('d/m/Y', strtotime($s)) : '—'; }
function num($n,$d=2){ return number_format((float)$n,$d,'.',''); }

// ===== Factor CO₂ =====
$co2Factor = 0.21;
try {
    $v = $pdo->query("SELECT value FROM settings WHERE `key`='co2_factor_kg_km' LIMIT 1")->fetchColumn();
    if ($v !== false && $v !== null) $co2Factor = (float)$v;
} catch(Throwable $e){}

// ===== Datos del usuario =====
$st = $pdo->prepare("SELECT id,name,apellido,email,telefono,dpi,fecha_nacimiento,foto FROM users WHERE id=? LIMIT 1");
$st->execute([$uid]);
$user = $st->fetch(PDO::FETCH_ASSOC) ?: [];

// ---------- Resolver URL de foto del usuario ----------
$fallback = '/ecobici/cliente/styles/avatar.png'; // pon aquí tu avatar por defecto
$raw = trim((string)($user['foto'] ?? ''));        // en tu BD viene como "uploads/users/xxxx.png"  :contentReference[oaicite:1]{index=1}
if ($raw === '') {
    $photoUrl = $fallback;
} elseif (preg_match('~^https?://~i', $raw)) {
    $photoUrl = $raw; // si guardaste URL absoluta
} else {
    // Normaliza y prefija el base path del proyecto
    $raw = str_replace('\\','/',$raw);
    $photoUrl = '/ecobici/' . ltrim($raw,'/'); // -> /ecobici/uploads/users/archivo.png
    // Validación opcional en disco para evitar 404 en dev
    $fs = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . $photoUrl;
    if (!is_file($fs)) { $photoUrl = $fallback; }
}

// ===== Membresía (subscriptions + plans) =====
$st = $pdo->prepare("
  SELECT s.*, p.nombre AS plan_nombre, p.precio
  FROM subscriptions s
  JOIN plans p ON p.id = s.plan_id
  WHERE s.user_id=? AND s.estado IN ('activa','pendiente')
  ORDER BY (s.estado='activa') DESC, s.created_at DESC
  LIMIT 1
");
$st->execute([$uid]);
$sub = $st->fetch(PDO::FETCH_ASSOC) ?: null;

// ===== KPIs globales y 30 días =====
$kpis = ['viajes_total'=>0,'km_total'=>0.0,'co2_total'=>0.0,'viajes_30'=>0,'km_30'=>0.0,'co2_30'=>0.0];

$st = $pdo->prepare("
  SELECT COUNT(*) cnt,
         COALESCE(SUM(distancia_km),0) kms,
         COALESCE(SUM(CASE WHEN COALESCE(co2_kg,0)>0 THEN co2_kg ELSE distancia_km * :f END),0) co2
  FROM trips WHERE user_id=:u
");
$st->execute([':u'=>$uid, ':f'=>$co2Factor]);
$r = $st->fetch(PDO::FETCH_ASSOC) ?: ['cnt'=>0,'kms'=>0,'co2'=>0];
$kpis['viajes_total']=(int)$r['cnt']; $kpis['km_total']=(float)$r['kms']; $kpis['co2_total']=(float)$r['co2'];

$st = $pdo->prepare("
  SELECT COUNT(*) cnt,
         COALESCE(SUM(distancia_km),0) kms,
         COALESCE(SUM(CASE WHEN COALESCE(co2_kg,0)>0 THEN co2_kg ELSE distancia_km * :f END),0) co2
  FROM trips WHERE user_id=:u AND DATE(start_at) >= (CURRENT_DATE - INTERVAL 30 DAY)
");
$st->execute([':u'=>$uid, ':f'=>$co2Factor]);
$r = $st->fetch(PDO::FETCH_ASSOC) ?: ['cnt'=>0,'kms'=>0,'co2'=>0];
$kpis['viajes_30']=(int)$r['cnt']; $kpis['km_30']=(float)$r['kms']; $kpis['co2_30']=(float)$r['co2'];

// ===== Última bici usada =====
$st = $pdo->prepare("
  SELECT t.*, b.codigo AS bike_codigo, b.tipo AS bike_tipo
  FROM trips t JOIN bikes b ON b.id=t.bike_id
  WHERE t.user_id=? ORDER BY t.start_at DESC LIMIT 1
");
$st->execute([$uid]);
$lastTrip = $st->fetch(PDO::FETCH_ASSOC) ?: null;

// ===== Pagos recientes (máx 6) =====
$st = $pdo->prepare("
  SELECT py.*, p.nombre AS plan_nombre
  FROM payments py
  JOIN subscriptions s ON s.id = py.subscription_id
  JOIN plans p ON p.id = s.plan_id
  WHERE s.user_id=? 
  ORDER BY py.created_at DESC
  LIMIT 6
");
$st->execute([$uid]);
$pagos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

// ===== Gráficas =====
// 1) Viajes y km por día (últimos 30 días)
$st = $pdo->prepare("
  SELECT DATE(start_at) d, COUNT(*) viajes, ROUND(SUM(COALESCE(distancia_km,0)),2) kms
  FROM trips 
  WHERE user_id=? AND DATE(start_at) >= (CURRENT_DATE - INTERVAL 30 DAY)
  GROUP BY DATE(start_at)
  ORDER BY d
");
$st->execute([$uid]);
$rows30 = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
$labels30 = []; $viajes30 = []; $kms30 = [];
if ($rows30){
    foreach ($rows30 as $row){ 
        $labels30[] = $row['d']; 
        $viajes30[] = (int)$row['viajes']; 
        $kms30[]    = (float)$row['kms']; 
    }
} else {
    $labels30 = [date('Y-m-d')]; $viajes30 = [0]; $kms30 = [0];
}

// 2) Uso por tipo de bicicleta
$st = $pdo->prepare("
  SELECT b.tipo, COUNT(*) qty
  FROM trips t JOIN bikes b ON b.id=t.bike_id
  WHERE t.user_id=? GROUP BY b.tipo
");
$st->execute([$uid]);
$byType = $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
$tiposLbl = ['Tradicional','Eléctrica'];
$dataTipos = [ (int)($byType['tradicional'] ?? 0), (int)($byType['electrica'] ?? 0) ];

// 3) Top estaciones (inicio)
$st = $pdo->prepare("
  SELECT st.nombre, COUNT(*) qty
  FROM trips t JOIN stations st ON st.id=t.start_station_id
  WHERE t.user_id=? GROUP BY st.id ORDER BY qty DESC LIMIT 5
");
$st->execute([$uid]);
$rowsTop = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
$labelsStations = array_map(fn($r)=>$r['nombre'],$rowsTop);
$dataStations   = array_map(fn($r)=>(int)$r['qty'],$rowsTop);

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Mis reportes • EcoBici</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<style>
:root{ --ring:#e2e8f0; --muted:#64748b; --teal:#009688; --bg:#f6fff9; }
body{ background: var(--bg); }
.container{ max-width: 1100px; }
.card-elev{ background:#fff; border:1px solid var(--ring); border-radius:16px; box-shadow:0 10px 30px rgba(2,6,23,.06); }
.kpi{ border:1px solid var(--ring); border-radius:14px; padding:14px; background:#f0fff7 }
.kpi .title{ color:#1b5e20; font-weight:600; margin-bottom:2px; }
.kpi .big{ font-size:1.4rem; font-weight:800; }
.section-title{ font-weight:700; color:#14532d; }
.table thead th{ background:#009688; color:#fff; }
.badge-soft{ background:#f0fdf4;border:1px solid #bbf7d0;color:#166534 }
img.avatar{ width:64px; height:64px; object-fit:cover; border-radius:50%; background:#e8f5e9; }
.btn-back{ border-radius:12px; }
</style>
</head>
<body>

<div class="container py-4">
  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Mis reportes</h4>
    <a class="btn btn-outline-secondary btn-back" href="/ecobici/cliente/dashboard.php"><i class="bi bi-arrow-left"></i> Volver</a>
  </div>

  <!-- Perfil + Membresía -->
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card-elev p-3 h-100">
        <div class="d-flex align-items-center gap-3">
          <img class="avatar" src="<?= e($photoUrl) ?>" alt="foto">
          <div>
            <div class="fw-bold"><?= e($user['name'].' '.($user['apellido']??'')) ?></div>
            <small class="text-muted"><?= e($user['email']) ?></small>
          </div>
        </div>
        <hr>
        <div class="small">
          <div><span class="text-muted">Teléfono:</span> <?= e($user['telefono'] ?: '—') ?></div>
          <div><span class="text-muted">DPI:</span> <?= e($user['dpi'] ?: '—') ?></div>
          <div><span class="text-muted">Nacimiento:</span> <?= e(dmy($user['fecha_nacimiento'] ?? '')) ?></div>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card-elev p-3 h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div class="section-title">Membresía</div>
          <?php if ($sub): ?>
            <span class="badge rounded-pill badge-soft"><?= e(ucfirst($sub['estado'])) ?></span>
          <?php endif; ?>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-md-4"><div class="kpi"><div class="title">Plan</div><div class="big"><?= e($sub['plan_nombre'] ?? '—') ?></div></div></div>
          <div class="col-md-4"><div class="kpi"><div class="title">Inicio</div><div class="big"><?= e(dmy($sub['fecha_inicio'] ?? '')) ?></div></div></div>
          <div class="col-md-4"><div class="kpi"><div class="title">Fin</div><div class="big"><?= e(dmy($sub['fecha_fin'] ?? '')) ?></div></div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3 mt-2">
    <div class="col-6 col-md-3"><div class="kpi"><div class="title">Viajes (30 días)</div><div class="big"><?= $kpis['viajes_30'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="kpi"><div class="title">Km (30 días)</div><div class="big"><?= num($kpis['km_30'],2) ?> km</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi"><div class="title">CO₂ (30 días)</div><div class="big"><?= num($kpis['co2_30'],3) ?> kg</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi"><div class="title">Viajes (total)</div><div class="big"><?= $kpis['viajes_total'] ?></div></div></div>
  </div>

  <!-- Charts -->
  <div class="row g-3 mt-1">
    <div class="col-lg-6">
      <div class="card-elev p-3">
        <div class="section-title mb-2">Viajes por día (30 días)</div>
        <canvas id="chartViajes"></canvas>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card-elev p-3">
        <div class="section-title mb-2">Kilómetros por día (30 días)</div>
        <canvas id="chartKms"></canvas>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-6">
      <div class="card-elev p-3">
        <div class="section-title mb-2">Uso por tipo de bicicleta</div>
        <canvas id="chartTipos"></canvas>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card-elev p-3">
        <div class="section-title mb-2">Estaciones más usadas</div>
        <canvas id="chartStations"></canvas>
      </div>
    </div>
  </div>

  <!-- Pagos recientes -->
  <div class="card-elev p-3 mt-3">
    <div class="d-flex justify-content-between align-items-center">
      <div class="section-title">Pagos recientes</div>
      <small class="text-muted"><?= count($pagos) ?> registros</small>
    </div>
    <div class="table-responsive mt-2">
      <table class="table table-sm align-middle">
        <thead><tr><th>Fecha</th><th>Plan</th><th>Método</th><th>Monto</th><th>Estado</th><th>Ref</th></tr></thead>
        <tbody>
          <?php if (!$pagos): ?>
            <tr><td colspan="6" class="text-center text-muted">Sin pagos</td></tr>
          <?php else: foreach ($pagos as $p): ?>
            <tr>
              <td><?= e(date('d/m/Y H:i', strtotime($p['created_at']))) ?></td>
              <td><?= e($p['plan_nombre']) ?></td>
              <td><?= e($p['metodo']) ?></td>
              <td><?= 'Q '.num($p['monto'],2) ?></td>
              <td><?= e(ucfirst($p['estado'])) ?></td>
              <td><?= e($p['referencia'] ?: '—') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Última bici -->
  <div class="card-elev p-3 mt-3 mb-4">
    <div class="section-title mb-1">Última bicicleta usada</div>
    <?php if ($lastTrip): ?>
      <div class="small">
        <span class="text-muted">Bici:</span> <?= e($lastTrip['bike_codigo']) ?> (<?= e($lastTrip['bike_tipo']) ?>) ·
        <span class="text-muted">Fecha:</span> <?= e(date('d/m/Y H:i', strtotime($lastTrip['start_at']))) ?> ·
        <span class="text-muted">Distancia:</span> <?= num($lastTrip['distancia_km'],2) ?> km ·
        <span class="text-muted">CO₂:</span> <?= num(($lastTrip['co2_kg'] ?: $lastTrip['distancia_km']*$co2Factor),3) ?> kg
      </div>
    <?php else: ?>
      <div class="text-muted small">Sin viajes todavía.</div>
    <?php endif; ?>
  </div>

</div>

<script>
// ===== Datos desde PHP =====
const labels30   = <?= json_encode($labels30) ?>;
const viajes30   = <?= json_encode($viajes30) ?>;
const kms30      = <?= json_encode($kms30) ?>;
const tiposLbl   = <?= json_encode($tiposLbl) ?>;
const tiposData  = <?= json_encode($dataTipos) ?>;
const stLabels   = <?= json_encode($labelsStations ?: ['(sin datos)']) ?>;
const stData     = <?= json_encode($dataStations ?: [0]) ?>;

// ===== Chart: Viajes por día =====
new Chart(document.getElementById('chartViajes'), {
  type: 'line',
  data: { labels: labels30, datasets: [{ label: 'Viajes', data: viajes30, tension: .3, fill: false }] },
  options: { responsive: true, plugins: { legend: { display: false } } }
});

// ===== Chart: Km por día =====
new Chart(document.getElementById('chartKms'), {
  type: 'bar',
  data: { labels: labels30, datasets: [{ label: 'Km', data: kms30 }] },
  options: { responsive: true, plugins: { legend: { display: false } } }
});

// ===== Chart: Uso por tipo de bici =====
new Chart(document.getElementById('chartTipos'), {
  type: 'doughnut',
  data: { labels: tiposLbl, datasets: [{ data: tiposData }] },
  options: { responsive: true }
});

// ===== Chart: Top estaciones =====
new Chart(document.getElementById('chartStations'), {
  type: 'bar',
  data: { labels: stLabels, datasets: [{ label: 'Veces', data: stData }] },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { x: { ticks: { autoSkip: false } } }
  }
});
</script>
</body>
</html>
