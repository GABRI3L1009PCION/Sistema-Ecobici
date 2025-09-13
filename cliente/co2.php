<?php
// /ecobici/cliente/co2.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db.php'; // Debe exponer $pdo (PDO)

// --- Usuario actual (ajusta si usas otro índice de sesión) ---
$userId = (int)($_SESSION['user']['id'] ?? 0);
if ($userId <= 0) {
  header('Location: /ecobici/login.php');
  exit;
}

// =============== LECTURA DE DATOS DESDE BD ==================
// 1) Factor de CO2 (kg por km) desde settings (fallback a 0.12 si no existe)
$factorKgKm = 0.12;
$st = $pdo->prepare("SELECT value FROM settings WHERE `key`='co2_factor_kg_km' LIMIT 1");
$st->execute();
$val = $st->fetchColumn();
if ($val !== false && is_numeric($val)) $factorKgKm = (float)$val;

// 2) Totales del usuario: distancia, viajes finalizados, CO2 (preferir columna co2_kg si está poblada)
$st = $pdo->prepare("
  SELECT
    COALESCE(SUM(distancia_km),0)              AS dist_km,
    COALESCE(SUM(CASE WHEN co2_kg IS NULL OR co2_kg = 0
                      THEN distancia_km * :f
                      ELSE co2_kg END), 0)     AS co2_total,
    SUM(CASE WHEN end_at IS NOT NULL THEN 1 ELSE 0 END) AS trips_done
  FROM trips
  WHERE user_id = :u
");
$st->execute([':f' => $factorKgKm, ':u' => $userId]);
$row = $st->fetch(PDO::FETCH_ASSOC) ?: ['dist_km'=>0,'co2_total'=>0,'trips_done'=>0];

$distance_km = (float)$row['dist_km'];
$co2_kg      = (float)$row['co2_total'];
$trips_count = (int)$row['trips_done'];

// 3) Serie por mes (últimos 6 meses incluyendo el actual)
//    Sumamos co2_kg si existe; si es 0, usamos distancia_km * factor
$st = $pdo->prepare("
  SELECT DATE_FORMAT(start_at, '%Y-%m-01') AS ym,
         DATE_FORMAT(start_at, '%b')       AS mes,
         ROUND(SUM(CASE WHEN co2_kg IS NULL OR co2_kg = 0
                        THEN distancia_km * :f
                        ELSE co2_kg END), 3) AS co2_mes
  FROM trips
  WHERE user_id = :u
    AND start_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-01')
  GROUP BY YEAR(start_at), MONTH(start_at)
  ORDER BY ym ASC
");
$st->execute([':f'=>$factorKgKm, ':u'=>$userId]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Rellenar meses faltantes (0) para mostrar siempre 6 puntos
$map = [];
foreach ($rows as $r) $map[$r['ym']] = ['label'=>$r['mes'], 'val'=>(float)$r['co2_mes']];

$labelsMeses = [];
$serieCo2Mes = [];
for ($i=5; $i>=0; $i--) {
  $ym = (new DateTime('first day of this month'))->modify("-$i month")->format('Y-m-01');
  $labelsMeses[] = $map[$ym]['label'] ?? date('M', strtotime($ym));
  $serieCo2Mes[] = $map[$ym]['val']   ?? 0.0;
}

// 4) Equivalencias (ajusta si usas otras referencias)
$gas_liters  = round($co2_kg / 2.3, 2);    // ~2.3 kg CO2 / litro gasolina
$trees_equiv = round($co2_kg / 21.77, 2);  // ~21.77 kg CO2 por árbol/año
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Mi CO₂ | EcoBici</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/ecobici/cliente/styles/app.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

  <style>
    /* Compactar solo esta vista */
    .co2-grid .eco-card .card-body{ padding:.75rem 1rem; }
    .co2-grid .eco-summary{ padding:.75rem; }
  </style>
</head>
<body class="ecobici">
  <div class="container py-3 eco-tight"><!-- modo compacto -->
    <div class="eco-header">
      <div class="eco-header-left">
        <span class="eco-header-icon">☁️</span>
        <h1 class="eco-title">Mi CO₂</h1>
      </div>
      <a href="/ecobici/cliente/dashboard.php" class="btn btn-back">← Volver</a>
    </div>

    <div class="row g-3 co2-grid">
      <div class="col-12 col-md-4">
        <div class="eco-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="eco-muted">CO₂ evitado</div>
                <div class="h3 mb-0"><?= number_format($co2_kg, 2) ?> kg</div>
                <small class="text-muted">Factor: <?= rtrim(rtrim(number_format($factorKgKm,3,'.',''), '0'), '.') ?> kg/km</small>
              </div>
              <span class="badge eco-badge">Total</span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="eco-card">
          <div class="card-body">
            <div class="eco-muted">Distancia total</div>
            <div class="h3 mb-0"><?= number_format($distance_km, 2) ?> km</div>
            <small class="text-muted">Real o estimada</small>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="eco-card">
          <div class="card-body">
            <div class="eco-muted">Viajes</div>
            <div class="h3 mb-0"><?= (int)$trips_count ?></div>
            <small class="text-muted">Finalizados</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Equivalencias -->
    <div class="row g-3 mt-1">
      <div class="col-12">
        <div class="eco-card">
          <div class="card-body">
            <div class="eco-muted mb-2">Equivalencias</div>
            <div class="row g-3">
              <div class="col-6 col-md-3">
                <div class="eco-summary">
                  <div class="d-flex justify-content-between">
                    <strong><?= number_format($gas_liters, 2) ?></strong>
                    <span class="eco-muted">L gasolina</span>
                  </div>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="eco-summary">
                  <div class="d-flex justify-content-between">
                    <strong><?= number_format($trees_equiv, 2) ?></strong>
                    <span class="eco-muted">árboles*</span>
                  </div>
                  <small class="text-muted d-block mt-1">*Año promedio por árbol</small>
                </div>
              </div>
              <!-- Añade más equivalencias si quieres -->
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Gráfica CO2 por mes -->
    <div class="row g-3 mt-1">
      <div class="col-12">
        <div class="eco-card">
          <div class="card-body">
            <div class="eco-muted mb-2">CO₂ por mes (kg)</div>
            <div class="chart-box">
              <canvas id="chartCo2Mes" class="chart-canvas"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /container -->

  <script>
    const labelsMeses = <?= json_encode($labelsMeses, JSON_UNESCAPED_UNICODE) ?>;
    const dataCo2Mes  = <?= json_encode($serieCo2Mes) ?>;

    const ctx1 = document.getElementById('chartCo2Mes');
    new Chart(ctx1, {
      type: 'line',
      data: {
        labels: labelsMeses,
        datasets: [{
          label: 'CO₂ (kg)',
          data: dataCo2Mes,
          tension: 0.35,
          borderWidth: 2,
          pointRadius: 3,
          fill: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
        scales: { y: { beginAtZero: true } }
      }
    });
  </script>
</body>
</html>
