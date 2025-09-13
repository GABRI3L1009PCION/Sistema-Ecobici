<?php
require_once __DIR__ . '/admin_boot.php';
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Conteos por estado y tipo
$est = $pdo->query("SELECT estado, COUNT(*) c FROM bikes GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
$tip = $pdo->query("SELECT tipo, COUNT(*) c FROM bikes GROUP BY tipo")->fetchAll(PDO::FETCH_KEY_PAIR);

// Viajes últimos 30 días
$rows = $pdo->query("
  SELECT DATE(start_at) d, COUNT(*) viajes, IFNULL(SUM(distancia_km),0) km
  FROM trips
  WHERE start_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  GROUP BY DATE(start_at) ORDER BY d
")->fetchAll(PDO::FETCH_ASSOC);
$labels = array_column($rows, 'd');
$viajes = array_map('intval', array_column($rows, 'viajes'));
$kms = array_map(fn($v) => (float)$v, array_column($rows, 'km'));
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EcoBici • Reporte Bicis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>

<body>
    <?php admin_nav('rep_bicis'); ?>
    <main class="container py-4">
        <h4 class="mb-3">Reporte de bicicletas</h4>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="p-3 border rounded-3">
                    <h6 class="mb-2">Bicis por estado</h6>
                    <canvas id="chEstado" height="160"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="p-3 border rounded-3">
                    <h6 class="mb-2">Bicis por tipo</h6>
                    <canvas id="chTipo" height="160"></canvas>
                </div>
            </div>
            <div class="col-12">
                <div class="p-3 border rounded-3">
                    <h6 class="mb-2">Últimos 30 días (viajes y km)</h6>
                    <canvas id="chSeries" height="90"></canvas>
                </div>
            </div>
        </div>
    </main>

    <script>
        const est = <?= json_encode($est + ['disponible' => 0, 'uso' => 0, 'mantenimiento' => 0]) ?>;
        const tip = <?= json_encode($tip + ['tradicional' => 0, 'electrica' => 0]) ?>;
        new Chart(chEstado, {
            type: 'doughnut',
            data: {
                labels: Object.keys(est),
                datasets: [{
                    data: Object.values(est)
                }]
            }
        });
        new Chart(chTipo, {
            type: 'doughnut',
            data: {
                labels: Object.keys(tip),
                datasets: [{
                    data: Object.values(tip)
                }]
            }
        });
        new Chart(chSeries, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                        label: 'Viajes',
                        data: <?= json_encode($viajes) ?>,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Km',
                        data: <?= json_encode($kms) ?>,
                        type: 'line',
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>

</html>