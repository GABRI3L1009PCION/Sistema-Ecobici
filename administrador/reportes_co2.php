<?php
// /ecobici/administrador/reportes_co2.php
require_once __DIR__ . '/admin_boot.php';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* ===== Factor CO₂ por defecto (kg por km) ===== */
$factor = 0.21;
try {
    $v = $pdo->query("SELECT `value` FROM settings WHERE `key`='co2_factor_kg_km'")->fetchColumn();
    if ($v !== false && $v !== null) $factor = (float)$v;
} catch (Throwable $e) { /* settings puede no existir; usamos 0.21 */
}

/* ===== Agregación mensual desde trips (si existe) ===== */
$rows = [];
try {
    $sql = "
    SELECT DATE_FORMAT(start_at,'%Y-%m') ym,
           SUM(distancia_km) km,
           SUM(CASE WHEN co2_kg>0 THEN co2_kg ELSE distancia_km * :factor END) co2
    FROM trips
    GROUP BY DATE_FORMAT(start_at,'%Y-%m')
    ORDER BY ym
  ";
    $st = $pdo->prepare($sql);
    $st->execute([':factor' => $factor]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $rows = []; // si no hay tabla trips aún, mostramos "Sin datos"
}

$labels = array_column($rows, 'ym');
$kms    = array_map(fn($v) => round((float)$v, 2), array_column($rows, 'km'));
$co2    = array_map(fn($v) => round((float)$v, 2), array_column($rows, 'co2'));
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EcoBici • CO₂ evitado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>

<body>
    <?php admin_nav('rep_co2'); ?>

    <main class="container py-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h4 class="mb-0">CO₂ evitado por mes</h4>
            <span class="badge text-bg-success">Factor: <?= number_format($factor, 3) ?> kg/km</span>
        </div>

        <div class="p-3 border rounded-3 mb-3">
            <canvas id="chCO2" height="90"></canvas>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th>Kilómetros</th>
                        <th>CO₂ (kg)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows): ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e($r['ym']) ?></td>
                                <td><?= number_format((float)$r['km'], 2) ?></td>
                                <td class="fw-semibold"><?= number_format((float)$r['co2'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">Sin datos</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        (function() {
            const el = document.getElementById('chCO2');
            if (!el) return;

            const labels = <?= json_encode($labels) ?>;
            const kms = <?= json_encode($kms) ?>;
            const co2 = <?= json_encode($co2) ?>;

            new Chart(el, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                            label: 'Km',
                            data: kms,
                            yAxisID: 'y'
                        },
                        {
                            label: 'CO₂ (kg)',
                            data: co2,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Km'
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'CO₂ (kg)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        })();
    </script>
</body>

</html>