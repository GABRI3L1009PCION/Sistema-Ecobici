<?php
// /ecobici/administrador/dashboard.php
require_once __DIR__ . '/admin_boot.php'; // valida sesión admin y carga $pdo

// ========== Helpers locales ==========
if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('admin_flash')) {
    function admin_flash($f)
    {
        if (!$f) return;
        echo '<div class="alert alert-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
}
function scalar(PDO $pdo, string $sql, array $params = [], $default = 0)
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v !== false ? $v : $default;
    } catch (Throwable) {
        return $default;
    }
}
function rows(PDO $pdo, string $sql, array $params = [])
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

// ========== KPIs ==========
$usuariosTotal       = scalar($pdo, "SELECT COUNT(*) FROM users");
$clientesTotal       = scalar($pdo, "SELECT COUNT(*) FROM users WHERE role='cliente'");
$planesTotal         = scalar($pdo, "SELECT COUNT(*) FROM plans");
$subsActivas         = scalar($pdo, "SELECT COUNT(*) FROM subscriptions WHERE estado='activa'");
$subsPendientes      = scalar($pdo, "SELECT COUNT(*) FROM subscriptions WHERE estado='pendiente'");
$ingresosMes         = scalar($pdo, "SELECT IFNULL(SUM(monto),0) FROM payments WHERE estado='completado' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
$pagosPendientesMes  = scalar($pdo, "SELECT COUNT(*) FROM payments WHERE estado='pendiente' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
$ticketPromedioMes   = scalar($pdo, "SELECT IFNULL(AVG(monto),0) FROM payments WHERE estado='completado' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
$nuevosUsuariosMes   = scalar($pdo, "SELECT COUNT(*) FROM users WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");

// ========== Datos para gráficas ==========
$pagos6 = rows($pdo, "
  SELECT DATE_FORMAT(DATE_SUB(LAST_DAY(CURDATE()), INTERVAL seq MONTH),'%Y-%m') ym,
         IFNULL((
            SELECT SUM(monto) FROM payments
            WHERE estado='completado'
              AND DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(DATE_SUB(LAST_DAY(CURDATE()), INTERVAL seq MONTH),'%Y-%m')
         ),0) total
  FROM (SELECT 0 seq UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) m
  ORDER BY ym
");
$labelsPagos = array_column($pagos6, 'ym');
$dataPagos   = array_map(fn($r) => round((float)$r['total'], 2), $pagos6);

$subsEstados = rows($pdo, "SELECT estado, COUNT(*) qty FROM subscriptions GROUP BY estado");
$labelsSubs  = array_column($subsEstados, 'estado');
$dataSubs    = array_map(fn($r) => (int)$r['qty'], $subsEstados);

$topPlanes = rows($pdo, "
  SELECT p.nombre, COUNT(*) qty
  FROM subscriptions s
  JOIN plans p ON p.id=s.plan_id
  GROUP BY s.plan_id
  ORDER BY qty DESC
  LIMIT 5
");
$labelsTopPlanes = array_column($topPlanes, 'nombre');
$dataTopPlanes   = array_map(fn($r) => (int)$r['qty'], $topPlanes);

// ========== Listados ==========
$ultimosPagos = rows($pdo, "
  SELECT p.id, p.monto, p.metodo, p.referencia, p.estado, p.created_at,
         u.name AS usuario, pl.nombre AS plan
  FROM payments p
  JOIN subscriptions s ON s.id=p.subscription_id
  JOIN users u ON u.id=s.user_id
  JOIN plans pl ON pl.id=s.plan_id
  ORDER BY p.created_at DESC LIMIT 8
");
$ultimasSubs = rows($pdo, "
  SELECT s.id, u.name usuario, pl.nombre plan, s.estado, s.fecha_inicio, s.fecha_fin, s.created_at
  FROM subscriptions s
  JOIN users u ON u.id=s.user_id
  JOIN plans pl ON pl.id=s.plan_id
  ORDER BY s.created_at DESC LIMIT 8
");
$resumenPlanes = rows($pdo, "
  SELECT p.id, p.nombre, p.precio,
         COUNT(CASE WHEN s.estado='activa' THEN 1 END) activas
  FROM plans p
  LEFT JOIN subscriptions s ON s.plan_id=p.id
  GROUP BY p.id, p.nombre, p.precio
  ORDER BY p.id
");
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>EcoBici • Admin • Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --ring: #e2e8f0;
            --muted: #64748b;
            --bg: #f8fafc;
        }

        body {
            background: var(--bg);
        }

        .card-elev {
            background: #fff;
            border: 1px solid var(--ring);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(2, 6, 23, .06)
        }

        .muted {
            color: var(--muted) !important
        }

        .stat {
            font-weight: 800;
            font-size: clamp(1.25rem, 2.1vw + .25rem, 2rem);
            line-height: 1
        }

        .chart-box {
            height: clamp(220px, 35vh, 340px);
        }

        .table thead th {
            white-space: nowrap
        }

        .table td {
            vertical-align: middle
        }

        @media (max-width: 991.98px) {
            .btn-sm {
                white-space: nowrap
            }
        }
    </style>
</head>

<body>
    <?php admin_nav('dash'); ?>

    <main class="container py-4">
        <?php admin_flash(flash()); ?>

        <!-- KPIs -->
        <div class="row g-3">
            <?php
            $kpis = [
                ['Usuarios', 'bi-people', $usuariosTotal, 'Totales'],
                ['Clientes', 'bi-person-check', $clientesTotal, 'Registrados'],
                ['Planes', 'bi-badge-ad', $planesTotal, 'Disponibles'],
                ['Subs. activas', 'bi-check2-circle', $subsActivas, 'En curso'],
                ['Ingresos mes', 'bi-cash-coin', 'Q ' . number_format($ingresosMes, 2), 'Mes ' . date('m/Y')],
                ['Ticket prom.', 'bi-receipt', 'Q ' . number_format($ticketPromedioMes, 2), 'Mes actual'],
                ['Pagos pend.', 'bi-hourglass-split', $pagosPendientesMes, 'Este mes'],
                ['Usuarios (mes)', 'bi-calendar-plus', $nuevosUsuariosMes, 'Nuevos'],
            ];
            foreach ($kpis as $k): ?>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card-elev p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="muted"><?= e($k[0]) ?></span>
                            <i class="bi <?= e($k[1]) ?> fs-4 text-success"></i>
                        </div>
                        <div class="stat mt-2">
                            <?php if (is_numeric($k[2])): ?>
                                <span class="count" data-target="<?= (float)$k[2] ?>">0</span>
                            <?php else: ?>
                                <?= $k[2] ?>
                            <?php endif; ?>
                        </div>
                        <small class="muted"><?= e($k[3]) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Gráficas -->
        <div class="row g-3 mt-1">
            <div class="col-12 col-lg-6">
                <div class="card-elev p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h5 class="mb-0">Pagos completados (últimos 6 meses)</h5>
                        <a class="btn btn-sm btn-outline-success" href="/ecobici/administrador/pagos.php">Ir a pagos</a>
                    </div>
                    <div class="chart-box"><canvas id="chartPagos"></canvas></div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card-elev p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h5 class="mb-0">Suscripciones por estado</h5>
                        <a class="btn btn-sm btn-outline-success" href="/ecobici/administrador/suscripciones.php">Ver</a>
                    </div>
                    <div class="chart-box"><canvas id="chartSubs"></canvas></div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card-elev p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h5 class="mb-0">Top planes</h5>
                        <a class="btn btn-sm btn-outline-success" href="/ecobici/administrador/planes.php">Ver</a>
                    </div>
                    <div class="chart-box"><canvas id="chartPlanes"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Listados -->
        <div class="row g-3 mt-1">
            <div class="col-12 col-lg-7">
                <div class="card-elev p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Últimos pagos</h5>
                        <a class="btn btn-sm btn-outline-success" href="/ecobici/administrador/pagos.php">Ver todos</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th class="d-none d-sm-table-cell">Plan</th>
                                    <th>Monto</th>
                                    <th class="d-none d-md-table-cell">Estado</th>
                                    <th class="d-none d-lg-table-cell">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$ultimosPagos): ?>
                                    <tr>
                                        <td colspan="6" class="text-center muted">Sin pagos recientes</td>
                                    </tr>
                                    <?php else: foreach ($ultimosPagos as $p): ?>
                                        <tr>
                                            <td>#<?= e($p['id']) ?></td>
                                            <td><?= e($p['usuario']) ?></td>
                                            <td class="d-none d-sm-table-cell"><?= e($p['plan']) ?></td>
                                            <td class="fw-semibold text-success">Q <?= number_format((float)$p['monto'], 2) ?></td>
                                            <td class="d-none d-md-table-cell">
                                                <?php $st = $p['estado'];
                                                $cls = $st === 'completado' ? 'success' : ($st === 'pendiente' ? 'warning' : 'danger'); ?>
                                                <span class="badge rounded-pill text-bg-<?= $cls ?>"><?= e($st) ?></span>
                                            </td>
                                            <td class="small d-none d-lg-table-cell"><?= e($p['created_at']) ?></td>
                                        </tr>
                                <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="card-elev p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Últimas suscripciones</h5>
                        <a class="btn btn-sm btn-outline-success" href="/ecobici/administrador/suscripciones.php">Ver todas</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th class="d-none d-sm-table-cell">Plan</th>
                                    <th>Estado</th>
                                    <th class="d-none d-md-table-cell">Inicio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$ultimasSubs): ?>
                                    <tr>
                                        <td colspan="5" class="text-center muted">Sin registros</td>
                                    </tr>
                                    <?php else: foreach ($ultimasSubs as $s): ?>
                                        <tr>
                                            <td>#<?= e($s['id']) ?></td>
                                            <td><?= e($s['usuario']) ?></td>
                                            <td class="d-none d-sm-table-cell"><?= e($s['plan']) ?></td>
                                            <td>
                                                <?php $st = $s['estado'];
                                                $cls = $st === 'activa' ? 'success' : ($st === 'pendiente' ? 'warning' : 'secondary'); ?>
                                                <span class="badge rounded-pill text-bg-<?= $cls ?>"><?= e($st) ?></span>
                                            </td>
                                            <td class="small d-none d-md-table-cell"><?= e($s['fecha_inicio']) ?></td>
                                        </tr>
                                <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card-elev p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Resumen de planes</h5>
                        <a class="btn btn-sm btn-success" href="/ecobici/administrador/planes.php">
                            <i class="bi bi-gear-wide-connected me-1"></i>Gestionar planes
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Precio</th>
                                    <th>Suscripciones activas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resumenPlanes as $pl): ?>
                                    <tr>
                                        <td>#<?= e($pl['id']) ?></td>
                                        <td><?= e($pl['nombre']) ?></td>
                                        <td>Q <?= number_format((float)$pl['precio'], 2) ?></td>
                                        <td><span class="badge rounded-pill text-bg-success"><?= (int)$pl['activas'] ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <p class="mt-4 muted">EcoBici Puerto Barrios • Dashboard Bootstrap responsivo.</p>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Contadores
        function animateCount(el) {
            const t = parseFloat(el.dataset.target || el.textContent || '0');
            if (!isFinite(t)) return;
            const s = performance.now(),
                d = 900;

            function f(now) {
                const p = Math.min((now - s) / d, 1),
                    v = t * p;
                el.textContent = Number.isInteger(t) ? Math.floor(v) : v.toFixed(2);
                if (p < 1) requestAnimationFrame(f);
            }
            requestAnimationFrame(f);
        }
        document.querySelectorAll('.count').forEach(animateCount);

        // Charts
        const labelsPagos = <?= json_encode($labelsPagos) ?>;
        const dataPagos = <?= json_encode($dataPagos) ?>;
        const labelsSubs = <?= json_encode($labelsSubs ?: ['activa', 'pendiente', 'inactiva']) ?>;
        const dataSubs = <?= json_encode($dataSubs ?: [0, 0, 0]) ?>;
        const labelsTop = <?= json_encode($labelsTopPlanes) ?>;
        const dataTop = <?= json_encode($dataTopPlanes) ?>;

        const common = {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 900
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        };

        new Chart(document.getElementById('chartPagos'), {
            type: 'bar',
            data: {
                labels: labelsPagos,
                datasets: [{
                    label: 'Q',
                    data: dataPagos,
                    borderWidth: 1,
                    backgroundColor: 'rgba(34,197,94,.28)',
                    borderColor: '#16a34a'
                }]
            },
            options: {
                ...common,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        new Chart(document.getElementById('chartSubs'), {
            type: 'doughnut',
            data: {
                labels: labelsSubs,
                datasets: [{
                    data: dataSubs,
                    backgroundColor: ['#16a34a', '#f59e0b', '#9ca3af'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                ...common,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        new Chart(document.getElementById('chartPlanes'), {
            type: 'bar',
            data: {
                labels: labelsTop,
                datasets: [{
                    data: dataTop,
                    borderWidth: 1,
                    backgroundColor: 'rgba(34,197,94,.28)',
                    borderColor: '#16a34a'
                }]
            },
            options: {
                ...common,
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