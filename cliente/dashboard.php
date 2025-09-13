<?php
require_once __DIR__ . '/client_boot.php';
$uid = (int)($_SESSION['user']['id'] ?? 0);

function scalar(PDO $pdo, string $sql, array $p = [], $d = 0)
{
    $st = $pdo->prepare($sql);
    $st->execute($p);
    $v = $st->fetchColumn();
    return $v !== false ? $v : $d;
}
function rows(PDO $pdo, string $sql, array $p = [])
{
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// Suscripción principal del usuario
$sub = $pdo->prepare("SELECT s.*, p.nombre plan, p.precio FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.user_id=? ORDER BY (s.estado='activa') DESC, s.created_at DESC LIMIT 1");
$sub->execute([$uid]);
$sub = $sub->fetch(PDO::FETCH_ASSOC);

// KPIs del cliente
$totalKm = (float)scalar($pdo, "SELECT IFNULL(SUM(distancia_km),0) FROM trips WHERE user_id=?", [$uid], 0);
$co2Factor = (float)scalar($pdo, "SELECT `value` FROM settings WHERE `key`='co2_factor_kg_km'", [], 0.21);
$totalCO2 = (float)scalar($pdo, "SELECT IFNULL(SUM(CASE WHEN co2_kg>0 THEN co2_kg ELSE distancia_km*? END),0) FROM trips WHERE user_id=?", [$co2Factor, $uid], 0);
$pagado = (float)scalar($pdo, "SELECT IFNULL(SUM(p.monto),0) FROM payments p JOIN subscriptions s ON s.id=p.subscription_id WHERE s.user_id=? AND p.estado='completado'", [$uid], 0);
$viajes = (int)scalar($pdo, "SELECT COUNT(*) FROM trips WHERE user_id=?", [$uid], 0);

// Gráfica: km últimos 6 meses
$km6 = rows($pdo, "
  SELECT DATE_FORMAT(DATE_SUB(LAST_DAY(CURDATE()), INTERVAL seq MONTH),'%Y-%m') ym,
         IFNULL((SELECT SUM(distancia_km) FROM trips WHERE user_id=? AND DATE_FORMAT(start_at,'%Y-%m') = DATE_FORMAT(DATE_SUB(LAST_DAY(CURDATE()), INTERVAL seq MONTH),'%Y-%m')),0) km
  FROM (SELECT 0 seq UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) m
  ORDER BY ym
", [$uid]);
$labelsKm = array_column($km6, 'ym');
$dataKm   = array_map(fn($r) => (float)$r['km'], $km6);

// Listados
$ultimosPagos = rows($pdo, "SELECT p.id,p.monto,p.estado,p.created_at,pl.nombre plan
  FROM payments p JOIN subscriptions s ON s.id=p.subscription_id JOIN plans pl ON pl.id=s.plan_id
  WHERE s.user_id=? ORDER BY p.created_at DESC LIMIT 5", [$uid]);

$ultimosViajes = rows($pdo, "SELECT t.id,t.start_at,t.end_at,t.distancia_km,st1.nombre ini, st2.nombre fin
  FROM trips t
  LEFT JOIN stations st1 ON st1.id=t.start_station_id
  LEFT JOIN stations st2 ON st2.id=t.end_station_id
  WHERE t.user_id=? ORDER BY t.start_at DESC LIMIT 5", [$uid]);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EcoBici • Mi panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        .card-elev {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(2, 6, 23, .06)
        }
    </style>
</head>

<body>
    <?php client_nav('dash'); ?>
    <main class="container py-4">
        <?php client_flash(flash()); ?>

        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="card-elev p-3 h-100">
                    <h5 class="mb-2">Mi suscripción</h5>
                    <?php if ($sub): ?>
                        <div><strong>Plan:</strong> <?= e($sub['plan']) ?> — <strong>Estado:</strong>
                            <span class="badge text-bg-<?= $sub['estado'] === 'activa' ? 'success' : ($sub['estado'] === 'pendiente' ? 'warning' : 'secondary') ?>"><?= e($sub['estado']) ?></span>
                        </div>
                        <div class="small text-muted">Inicio: <?= e($sub['fecha_inicio']) ?> <?= $sub['fecha_fin'] ? '• Fin: ' . e($sub['fecha_fin']) : '' ?></div>
                    <?php else: ?>
                        <div class="text-muted">Aún no tienes suscripción. <a class="link-success" href="/ecobici/cliente/planes.php">Ver planes</a></div>
                    <?php endif; ?>
                    <div class="row text-center mt-3 g-2">
                        <div class="col-6">
                            <div class="card p-3">
                                <div class="text-muted">Pagado</div>
                                <div class="fs-5 fw-bold">Q <?= number_format($pagado, 2) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card p-3">
                                <div class="text-muted">Viajes</div>
                                <div class="fs-5 fw-bold"><?= $viajes ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card-elev p-3 h-100">
                    <h5 class="mb-2">Impacto</h5>
                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <div class="card p-3">
                                <div class="text-muted">Km totales</div>
                                <div class="fs-5 fw-bold"><?= number_format($totalKm, 2) ?> km</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card p-3">
                                <div class="text-muted">CO₂ evitado</div>
                                <div class="fs-5 fw-bold"><?= number_format($totalCO2, 2) ?> kg</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3" style="height:220px"><canvas id="chartKm"></canvas></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-12 col-lg-6">
                <div class="card-elev p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Últimos pagos</h5>
                        <a class="btn btn-sm btn-outline-success" href="/ecobici/cliente/pagos.php">Ver todos</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Plan</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th class="d-none d-md-table-cell">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$ultimosPagos): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Sin pagos</td>
                                    </tr>
                                    <?php else: foreach ($ultimosPagos as $p): $cls = $p['estado'] === 'completado' ? 'success' : ($p['estado'] === 'pendiente' ? 'warning' : 'danger'); ?>
                                        <tr>
                                            <td>#<?= e($p['id']) ?></td>
                                            <td><?= e($p['plan']) ?></td>
                                            <td class="fw-semibold text-success">Q <?= number_format((float)$p['monto'], 2) ?></td>
                                            <td><span class="badge text-bg-<?= $cls ?>"><?= e($p['estado']) ?></span></td>
                                            <td class="small d-none d-md-table-cell"><?= e($p['created_at']) ?></td>
                                        </tr>
                                <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card-elev p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Últimos viajes</h5>
                        <a class="btn btn-sm btn-outline-success" href="/ecobici/cliente/viajes.php">Ver todos</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Inicio</th>
                                    <th class="d-none d-sm-table-cell">Fin</th>
                                    <th>Km</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$ultimosViajes): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Sin viajes</td>
                                    </tr>
                                    <?php else: foreach ($ultimosViajes as $v): ?>
                                        <tr>
                                            <td>#<?= e($v['id']) ?></td>
                                            <td><?= e($v['start_at']) . ' • ' . e($v['ini'] ?? '—') ?></td>
                                            <td class="d-none d-sm-table-cell"><?= e($v['end_at']) . ' • ' . e($v['fin'] ?? '—') ?></td>
                                            <td class="fw-semibold"><?= number_format((float)$v['distancia_km'], 2) ?></td>
                                        </tr>
                                <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        new Chart(document.getElementById('chartKm'), {
            type: 'line',
            data: {
                labels: <?= json_encode($labelsKm) ?>,
                datasets: [{
                    data: <?= json_encode($dataKm) ?>,
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22,163,74,.2)',
                    tension: .3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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