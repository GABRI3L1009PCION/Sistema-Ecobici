<?php
require_once __DIR__ . '/admin_boot.php';
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$rows = $pdo->query("
  SELECT DATE_FORMAT(created_at,'%Y-%m') ym, SUM(monto) total, SUM(estado='pendiente') pend
  FROM payments
  GROUP BY DATE_FORMAT(created_at,'%Y-%m')
  ORDER BY ym
")->fetchAll(PDO::FETCH_ASSOC);

$labels = array_column($rows, 'ym');
$tot = array_map(fn($v) => (float)$v, array_column($rows, 'total'));
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EcoBici • Reporte Ingresos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>

<body>
    <?php admin_nav('rep_ing'); ?>
    <main class="container py-4">
        <h4 class="mb-3">Ingresos por mes</h4>
        <div class="p-3 border rounded-3 mb-3"><canvas id="chIngr" height="90"></canvas></div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th>Total (Q)</th>
                        <th>Pagos pendientes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= e($r['ym']) ?></td>
                            <td class="fw-semibold text-success">Q <?= number_format((float)$r['total'], 2) ?></td>
                            <td><?= e($r['pend']) ?></td>
                        </tr>
                    <?php endforeach;
                    if (!$rows): ?><tr>
                            <td colspan="3" class="text-center text-muted">Sin datos</td>
                        </tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
    <script>
        new Chart(chIngr, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    label: 'Q',
                    data: <?= json_encode($tot) ?>,
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>

</html>