<?php
// administrador/dashboard.php
session_start();
if (!isset($_SESSION['user']) || (($_SESSION['user']['role'] ?? null) !== 'admin')) {
    header('Location: /ecobici/login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php'; // Debe exponer $pdo
if (!isset($pdo)) {
    die('Error: $pdo no está definido. Revisa config/db.php');
}

// Helpers
function scalar($pdo, $sql, $params = [], $default = 0)
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v !== false ? $v : $default;
    } catch (Throwable $e) {
        return $default;
    }
}
function rows($pdo, $sql, $params = [])
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/* ===================== KPIs (tu BD) ===================== */
$usuariosTotal       = scalar($pdo, "SELECT COUNT(*) FROM users");
$clientesTotal       = scalar($pdo, "SELECT COUNT(*) FROM users WHERE role='cliente'");
$planesTotal         = scalar($pdo, "SELECT COUNT(*) FROM plans");
$subsActivas         = scalar($pdo, "SELECT COUNT(*) FROM subscriptions WHERE estado='activa'");
$subsPendientes      = scalar($pdo, "SELECT COUNT(*) FROM subscriptions WHERE estado='pendiente'");
$ingresosMes         = scalar($pdo, "SELECT IFNULL(SUM(monto),0) FROM payments WHERE estado='completado' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
$pagosPendientesMes  = scalar($pdo, "SELECT COUNT(*) FROM payments WHERE estado='pendiente' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
$ticketPromedioMes   = scalar($pdo, "SELECT IFNULL(AVG(monto),0) FROM payments WHERE estado='completado' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
$nuevosUsuariosMes   = scalar($pdo, "SELECT COUNT(*) FROM users WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");

/* ===================== Charts ===================== */
// Pagos completados últimos 6 meses
$pagos6 = rows($pdo, "
  SELECT DATE_FORMAT(DATE_SUB(LAST_DAY(CURDATE()), INTERVAL seq MONTH), '%Y-%m') ym,
         IFNULL((
           SELECT SUM(monto) FROM payments
           WHERE estado='completado'
             AND DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(DATE_SUB(LAST_DAY(CURDATE()), INTERVAL seq MONTH), '%Y-%m')
         ),0) total
  FROM (SELECT 0 seq UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) m
  ORDER BY ym
");
$labelsPagos = array_map(fn($r) => $r['ym'], $pagos6);
$dataPagos   = array_map(fn($r) => round((float)$r['total'], 2), $pagos6);

// Suscripciones por estado
$subsEstados = rows($pdo, "SELECT estado, COUNT(*) qty FROM subscriptions GROUP BY estado");
$labelsSubs  = array_map(fn($r) => $r['estado'], $subsEstados);
$dataSubs    = array_map(fn($r) => (int)$r['qty'], $subsEstados);

// Top planes por suscripciones
$topPlanes = rows($pdo, "
  SELECT p.nombre, COUNT(*) qty
  FROM subscriptions s
  JOIN plans p ON p.id=s.plan_id
  GROUP BY s.plan_id
  ORDER BY qty DESC
  LIMIT 5
");
$labelsTopPlanes = array_map(fn($r) => $r['nombre'], $topPlanes);
$dataTopPlanes   = array_map(fn($r) => (int)$r['qty'], $topPlanes);

/* ===================== Listados ===================== */
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
         COUNT(CASE WHEN s.estado='activa' THEN 1 END) AS activas
  FROM plans p
  LEFT JOIN subscriptions s ON s.plan_id=p.id
  GROUP BY p.id, p.nombre, p.precio
  ORDER BY p.id ASC
");
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>EcoBici • Panel Administrador</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --bg: #f8fafc;
            --card: #fff;
            --text: #0f172a;
            --muted: #64748b;
            --green: #16a34a;
            --green-2: #22c55e;
            --ring: #e2e8f0;
            --shadow: 0 10px 30px rgba(2, 6, 23, .06);
        }

        body {
            background: var(--bg);
            color: var(--text)
        }

        .navbar {
            background: #fff;
            border-bottom: 1px solid var(--ring)
        }

        .navbar .navbar-brand {
            font-weight: 700;
            letter-spacing: .2px
        }

        .btn-success {
            background: var(--green);
            border-color: var(--green)
        }

        .btn-success:hover {
            background: var(--green-2);
            border-color: var(--green-2)
        }

        .btn-glass {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            transition: transform .2s
        }

        .btn-glass:hover {
            transform: translateY(-1px)
        }

        .chip {
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #065f46;
            padding: .25rem .6rem;
            border-radius: 999px;
            font-size: .78rem
        }

        .card-elev {
            background: #fff;
            border: 1px solid var(--ring);
            border-radius: 16px;
            box-shadow: var(--shadow)
        }

        .tbl thead th {
            background: #f8fafc;
            border-bottom-color: var(--ring) !important
        }

        .tbl tbody td {
            border-top-color: #eef2f7 !important
        }

        .kpi {
            position: relative;
            overflow: hidden;
            border-radius: 16px
        }

        .kpi::after {
            content: "";
            position: absolute;
            inset: -2px;
            border-radius: 16px;
            padding: 1.2px;
            background: conic-gradient(from 180deg, rgba(34, 197, 94, .25), rgba(187, 247, 208, .25), rgba(34, 197, 94, .25));
            mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            mask-composite: exclude;
            -webkit-mask-composite: xor;
            animation: spin 6s linear infinite
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        .stat {
            font-size: 1.9rem;
            font-weight: 800;
            line-height: 1
        }

        .muted {
            color: var(--muted) !important
        }

        .reveal {
            opacity: 0;
            transform: translateY(10px);
            transition: opacity .5s, transform .5s
        }

        .reveal.show {
            opacity: 1;
            transform: translateY(0)
        }

        .badge-soft {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="bi bi-bicycle me-2 text-success"></i>EcoBici Admin</a>
            <div class="ms-auto d-flex gap-2">
                <span class="chip"><i class="bi bi-shield-lock me-1"></i>Administrador</span>
                <a class="btn btn-sm btn-glass" href="/ecobici/index.php"><i class="bi bi-house-door me-1"></i>Pública</a>
                <a class="btn btn-sm btn-outline-danger" href="/ecobici/logout.php"><i class="bi bi-box-arrow-right me-1"></i>Salir</a>
            </div>
        </div>
    </nav>

    <main class="container py-4">
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
            foreach ($kpis as $k) {
            ?>
                <div class="col-6 col-lg-3">
                    <div class="card-elev kpi p-3 reveal">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="muted"><?= htmlspecialchars($k[0]) ?></span>
                            <i class="bi <?= $k[1] ?> fs-4 text-success"></i>
                        </div>
                        <div class="stat mt-2">
                            <?php if (is_numeric($k[2])): ?>
                                <span class="count" data-target="<?= (float)$k[2] ?>">0</span>
                            <?php else: ?>
                                <?= $k[2] ?>
                            <?php endif; ?>
                        </div>
                        <small class="muted"><?= htmlspecialchars($k[3]) ?></small>
                    </div>
                </div>
            <?php } ?>
        </div>

        <!-- Charts -->
        <div class="row g-3 mt-1">
            <div class="col-lg-6">
                <div class="card-elev p-3 reveal">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Pagos completados (últimos 6 meses)</h5>
                        <span class="badge rounded-pill text-bg-success">Q</span>
                    </div>
                    <canvas id="chartPagos" height="120"></canvas>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card-elev p-3 reveal">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Suscripciones por estado</h5>
                        <span class="badge rounded-pill text-bg-success"><i class="bi bi-diagram-3"></i></span>
                    </div>
                    <canvas id="chartSubs" height="120"></canvas>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card-elev p-3 reveal">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Top planes</h5>
                        <span class="badge rounded-pill text-bg-success"><i class="bi bi-trophy"></i></span>
                    </div>
                    <canvas id="chartPlanes" height="120"></canvas>
                </div>
            </div>
        </div>

        <!-- Tablas -->
        <div class="row g-3 mt-1">
            <div class="col-lg-7">
                <div class="card-elev p-3 reveal">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Últimos pagos</h5>
                        <a class="btn btn-sm btn-outline-success" href="/ecobici/pagos/index.php">Ver todos</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table tbl align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Plan</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$ultimosPagos): ?>
                                    <tr>
                                        <td colspan="6" class="text-center muted">Sin pagos recientes</td>
                                    </tr>
                                    <?php else: foreach ($ultimosPagos as $p): ?>
                                        <tr>
                                            <td>#<?= htmlspecialchars($p['id']) ?></td>
                                            <td><?= htmlspecialchars($p['usuario']) ?></td>
                                            <td><?= htmlspecialchars($p['plan']) ?></td>
                                            <td><span class="fw-semibold text-success">Q <?= number_format((float)$p['monto'], 2) ?></span></td>
                                            <td>
                                                <?php $st = $p['estado'];
                                                $cls = $st === 'completado' ? 'success' : ($st === 'pendiente' ? 'warning' : 'danger'); ?>
                                                <span class="badge rounded-pill text-bg-<?= $cls ?>"><?= htmlspecialchars($st) ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($p['created_at']) ?></td>
                                        </tr>
                                <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card-elev p-3 reveal">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Últimas suscripciones</h5>
                        <a class="btn btn-sm btn-outline-success" href="/ecobici/suscripciones/index.php">Ver todas</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table tbl align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Plan</th>
                                    <th>Estado</th>
                                    <th>Inicio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$ultimasSubs): ?>
                                    <tr>
                                        <td colspan="5" class="text-center muted">Sin registros</td>
                                    </tr>
                                    <?php else: foreach ($ultimasSubs as $s): ?>
                                        <tr>
                                            <td>#<?= htmlspecialchars($s['id']) ?></td>
                                            <td><?= htmlspecialchars($s['usuario']) ?></td>
                                            <td><?= htmlspecialchars($s['plan']) ?></td>
                                            <td>
                                                <?php $st = $s['estado'];
                                                $cls = $st === 'activa' ? 'success' : ($st === 'pendiente' ? 'warning' : 'secondary'); ?>
                                                <span class="badge rounded-pill text-bg-<?= $cls ?>"><?= htmlspecialchars($st) ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($s['fecha_inicio']) ?></td>
                                        </tr>
                                <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card-elev p-3 reveal">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Resumen de planes</h5>
                        <a class="btn btn-sm btn-success" href="/ecobici/planes/index.php"><i class="bi bi-gear-wide-connected me-1"></i>Gestionar planes</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table tbl align-middle">
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
                                        <td>#<?= htmlspecialchars($pl['id']) ?></td>
                                        <td><?= htmlspecialchars($pl['nombre']) ?></td>
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

        <p class="mt-4 muted">EcoBici Puerto Barrios • Panel claro con detalles en verde.</p>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Reveal on scroll
        const obs = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('show');
                    obs.unobserve(e.target);
                }
            })
        }, {
            threshold: .1
        });
        document.querySelectorAll('.reveal').forEach(el => obs.observe(el));

        // Count-up
        function animateCount(el) {
            const target = parseFloat(el.dataset.target || el.textContent || '0');
            if (!isFinite(target)) return;
            const dur = 900,
                start = performance.now();

            function frame(t) {
                const p = Math.min((t - start) / dur, 1),
                    val = target * p;
                el.textContent = Number.isInteger(target) ? Math.floor(val) : val.toFixed(2);
                if (p < 1) requestAnimationFrame(frame);
            }
            requestAnimationFrame(frame);
        }
        document.querySelectorAll('.count').forEach(animateCount);

        // Charts
        const labelsPagos = <?= json_encode($labelsPagos) ?>;
        const dataPagos = <?= json_encode($dataPagos) ?>;
        const labelsSubs = <?= json_encode($labelsSubs ?: ['activa', 'pendiente', 'inactiva']) ?>;
        const dataSubs = <?= json_encode($dataSubs ?: [0, 0, 0]) ?>;
        const labelsTop = <?= json_encode($labelsTopPlanes) ?>;
        const dataTop = <?= json_encode($dataTopPlanes) ?>;

        new Chart(document.getElementById('chartPagos'), {
            type: 'bar',
            data: {
                labels: labelsPagos,
                datasets: [{
                    label: 'Q',
                    data: dataPagos,
                    borderWidth: 1,
                    backgroundColor: 'rgba(34,197,94,.3)',
                    borderColor: '#16a34a'
                }]
            },
            options: {
                animation: {
                    duration: 900
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
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
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                animation: {
                    duration: 900
                },
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
                    backgroundColor: 'rgba(34,197,94,.3)',
                    borderColor: '#16a34a'
                }]
            },
            options: {
                animation: {
                    duration: 900
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>

</html>