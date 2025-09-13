<?php
require_once __DIR__ . '/admin_boot.php';

// =============== HELPERS LOCALES ===============
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function qurl(array $overrides = [])
{
    $q = array_merge($_GET, $overrides);
    return '/ecobici/administrador/suscripciones.php?' . http_build_query($q);
}

// =============== ACCIONES (POST) ===============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash('Token inválido', 'danger');
        redirect('/ecobici/administrador/suscripciones.php');
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $user_id      = (int)($_POST['user_id'] ?? 0);
            $plan_id      = (int)($_POST['plan_id'] ?? 0);
            $fecha_inicio = $_POST['fecha_inicio'] ?: date('Y-m-d');
            $estado       = in_array($_POST['estado'] ?? '', ['activa', 'inactiva', 'pendiente'], true) ? $_POST['estado'] : 'pendiente';

            if ($user_id <= 0 || $plan_id <= 0) throw new Exception('Usuario y plan son obligatorios.');

            // existencia
            $ok = $pdo->prepare("SELECT 1 FROM users WHERE id=?");
            $ok->execute([$user_id]);
            if (!$ok->fetch()) throw new Exception('El usuario no existe.');
            $ok = $pdo->prepare("SELECT 1 FROM plans WHERE id=?");
            $ok->execute([$plan_id]);
            if (!$ok->fetch()) throw new Exception('El plan no existe.');

            // activa única (además del UNIQUE en BD)
            if ($estado === 'activa') {
                $c = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id=? AND estado='activa'");
                $c->execute([$user_id]);
                if ((int)$c->fetchColumn() > 0) throw new Exception('Ese usuario ya tiene una suscripción activa.');
            }

            $st = $pdo->prepare("INSERT INTO subscriptions (user_id,plan_id,fecha_inicio,estado) VALUES (?,?,?,?)");
            $st->execute([$user_id, $plan_id, $fecha_inicio, $estado]);

            flash('Suscripción creada.');
        } elseif ($action === 'update') {
            $id           = (int)($_POST['id'] ?? 0);
            $plan_id      = (int)($_POST['plan_id'] ?? 0);
            $estado       = in_array($_POST['estado'] ?? '', ['activa', 'inactiva', 'pendiente'], true) ? $_POST['estado'] : 'pendiente';
            $fecha_inicio = $_POST['fecha_inicio'] ?: null;
            $fecha_fin    = $_POST['fecha_fin'] ?: null;
            if ($id <= 0 || $plan_id <= 0) throw new Exception('Datos inválidos.');

            // obtener user de la suscripción
            $u = $pdo->prepare("SELECT user_id FROM subscriptions WHERE id=?");
            $u->execute([$id]);
            $uid = (int)$u->fetchColumn();
            if ($uid <= 0) throw new Exception('Suscripción no existe.');

            // plan existe
            $ok = $pdo->prepare("SELECT 1 FROM plans WHERE id=?");
            $ok->execute([$plan_id]);
            if (!$ok->fetch()) throw new Exception('El plan no existe.');

            // activa única
            if ($estado === 'activa') {
                $c = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id=? AND estado='activa' AND id<>?");
                $c->execute([$uid, $id]);
                if ((int)$c->fetchColumn() > 0) throw new Exception('El usuario ya tiene otra suscripción activa.');
            }

            $st = $pdo->prepare("UPDATE subscriptions SET plan_id=?, estado=?, fecha_inicio=?, fecha_fin=? WHERE id=?");
            $st->execute([$plan_id, $estado, $fecha_inicio, $fecha_fin, $id]);

            flash('Suscripción actualizada.');
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido.');
            // ON DELETE CASCADE elimina pagos asociados
            $st = $pdo->prepare("DELETE FROM subscriptions WHERE id=?");
            $st->execute([$id]);
            flash('Suscripción eliminada (pagos asociados eliminados).');
        }
    } catch (PDOException $e) {
        // por UNIQUE uq_user_active_subscription (user_id, activa_flag)
        if ($e->getCode() === '23000') {
            flash('Regla de negocio: Sólo puede existir una suscripción activa por usuario.', 'danger');
        } else {
            flash('Error de BD: ' . $e->getMessage(), 'danger');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('/ecobici/administrador/suscripciones.php');
}

// =============== FILTROS / ORDEN / PAGINACIÓN ===============
$q       = trim($_GET['q'] ?? '');                 // búsqueda por nombre/email
$f_est   = trim($_GET['estado'] ?? '');            // activa/pendiente/inactiva
$f_plan  = (int)($_GET['plan'] ?? 0);              // id plan
$f_from  = trim($_GET['from'] ?? '');              // fecha_inicio desde
$f_to    = trim($_GET['to'] ?? '');                // fecha_inicio hasta

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$allowedSort = ['id', 'usuario', 'plan', 'estado', 'inicio', 'fin', 'pagos', 'creado'];
$sort = $_GET['sort'] ?? 'creado';
if (!in_array($sort, $allowedSort, true)) $sort = 'creado';

$dir = strtolower($_GET['dir'] ?? 'desc');
$dir = $dir === 'asc' ? 'asc' : 'desc';

$where = [];
$args  = [];

if ($q !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $like = "%$q%";
    $args[] = $like;
    $args[] = $like;
}
if ($f_est !== '' && in_array($f_est, ['activa', 'pendiente', 'inactiva'], true)) {
    $where[] = "s.estado = ?";
    $args[]  = $f_est;
}
if ($f_plan > 0) {
    $where[] = "p.id = ?";
    $args[]  = $f_plan;
}
if ($f_from !== '') {
    $where[] = "s.fecha_inicio >= ?";
    $args[]  = $f_from;
}
if ($f_to !== '') {
    $where[] = "s.fecha_inicio <= ?";
    $args[]  = $f_to;
}
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Mapeo de Orden
$orderMap = [
    'id'      => 's.id',
    'usuario' => 'u.name',
    'plan'    => 'p.nombre',
    'estado'  => 's.estado',
    'inicio'  => 's.fecha_inicio',
    'fin'     => 's.fecha_fin',
    'pagos'   => 'pagos',
    'creado'  => 's.created_at',
];
$orderBy = $orderMap[$sort] . ' ' . strtoupper($dir) . ', s.id DESC';

// Total
$sqlCount = "
  SELECT COUNT(*) FROM subscriptions s
  JOIN users u ON u.id=s.user_id
  JOIN plans p ON p.id=s.plan_id
  $wsql
";
$st = $pdo->prepare($sqlCount);
$st->execute($args);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

// Listado (incluye conteo de pagos asociados)
$sql = "
  SELECT s.id, s.estado, s.fecha_inicio, s.fecha_fin, s.created_at,
         u.name AS usuario, u.email,
         p.id AS pid, p.nombre AS plan,
         COUNT(pay.id) AS pagos
  FROM subscriptions s
  JOIN users u ON u.id=s.user_id
  JOIN plans p ON p.id=s.plan_id
  LEFT JOIN payments pay ON pay.subscription_id = s.id
  $wsql
  GROUP BY s.id, s.estado, s.fecha_inicio, s.fecha_fin, s.created_at, u.name, u.email, p.id, p.nombre
  ORDER BY $orderBy
  LIMIT $perPage OFFSET $offset
";
$st = $pdo->prepare($sql);
$st->execute($args);
$subs = $st->fetchAll(PDO::FETCH_ASSOC);

// Para selects de creación/edición
$users = $pdo->query("SELECT id,name,email FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$plans = $pdo->query("SELECT id,nombre FROM plans ORDER BY precio ASC")->fetchAll(PDO::FETCH_ASSOC);

// =============== RENDER ===============
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EcoBici • Admin • Suscripciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --ring: #e2e8f0;
            --muted: #64748b;
        }

        .card-elev {
            background: #fff;
            border: 1px solid var(--ring);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(2, 6, 23, .06)
        }

        .table td,
        .table th {
            vertical-align: middle
        }

        .badge-soft {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534
        }

        /* Responsivo: oculta columnas menos críticas en móviles */
        @media (max-width: 767.98px) {

            .col-email,
            .col-fin,
            .col-creado {
                display: none;
            }
        }
    </style>
</head>

<body>
    <?php admin_nav('subs'); ?>

    <main class="container py-4">
        <?php admin_flash(flash()); ?>

        <!-- Header + Filtros -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
            <h4 class="mb-0">Suscripciones</h4>

            <form class="d-flex flex-wrap gap-2" method="get">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o correo…">
                </div>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-filter"></i></span>
                    <select class="form-select" name="estado">
                        <option value="">Estado…</option>
                        <option value="activa" <?= $f_est === 'activa' ? 'selected' : '' ?>>Activa</option>
                        <option value="pendiente" <?= $f_est === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="inactiva" <?= $f_est === 'inactiva' ? 'selected' : '' ?>>Inactiva</option>
                    </select>
                </div>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-badge-ad"></i></span>
                    <select class="form-select" name="plan">
                        <option value="0">Plan…</option>
                        <?php foreach ($plans as $p): ?>
                            <option value="<?= e($p['id']) ?>" <?= $f_plan === $p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <span class="input-group-text">Desde</span>
                    <input type="date" class="form-control" name="from" value="<?= e($f_from) ?>">
                </div>
                <div class="input-group">
                    <span class="input-group-text">Hasta</span>
                    <input type="date" class="form-control" name="to" value="<?= e($f_to) ?>">
                </div>
                <button class="btn btn-outline-success">Aplicar</button>
                <a class="btn btn-outline-secondary" href="/ecobici/administrador/suscripciones.php">Limpiar</a>
                <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#mdlCreate">
                    <i class="bi bi-plus-lg me-1"></i>Nueva suscripción
                </button>
            </form>
        </div>

        <!-- Tabla -->
        <div class="card-elev p-3">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <?php
                            $mk = function ($k, $lbl) use ($sort, $dir) {
                                $n = ($sort === $k && $dir === 'asc') ? 'desc' : 'asc';
                                $url = qurl(['sort' => $k, 'dir' => $n, 'page' => 1]);
                                $icon = '';
                                if ($sort === $k) {
                                    $icon = $dir === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>';
                                }
                                return '<a class="link-success text-decoration-none" href="' . e($url) . '">' . e($lbl) . $icon . '</a>';
                            };
                            ?>
                            <th><?= $mk('id', 'ID') ?></th>
                            <th><?= $mk('usuario', 'Usuario') ?></th>
                            <th><?= $mk('plan', 'Plan') ?></th>
                            <th><?= $mk('estado', 'Estado') ?></th>
                            <th><?= $mk('inicio', 'Inicio') ?></th>
                            <th class="col-fin"><?= $mk('fin', 'Fin') ?></th>
                            <th><?= $mk('pagos', 'Pagos') ?></th>
                            <th class="col-creado"><?= $mk('creado', 'Creado') ?></th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($subs): foreach ($subs as $s): ?>
                                <tr>
                                    <td>#<?= e($s['id']) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= e($s['usuario']) ?></div>
                                        <div class="small text-muted col-email"><?= e($s['email']) ?></div>
                                    </td>
                                    <td><?= e($s['plan']) ?></td>
                                    <td>
                                        <?php
                                        $st = $s['estado'];
                                        $cls = $st === 'activa' ? 'success' : ($st === 'pendiente' ? 'warning' : 'secondary');
                                        ?>
                                        <span class="badge text-bg-<?= $cls ?>"><?= e($st) ?></span>
                                    </td>
                                    <td class="small"><?= e($s['fecha_inicio']) ?></td>
                                    <td class="small col-fin"><?= e($s['fecha_fin'] ?: '—') ?></td>
                                    <td>
                                        <?php if ((int)$s['pagos'] > 0): ?>
                                            <span class="badge rounded-pill text-bg-secondary"><?= (int)$s['pagos'] ?></span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill text-bg-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted col-creado"><?= e($s['created_at']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-success me-1"
                                            data-bs-toggle="modal" data-bs-target="#mdlEdit"
                                            data-id="<?= e($s['id']) ?>"
                                            data-estado="<?= e($s['estado']) ?>"
                                            data-fechainicio="<?= e($s['fecha_inicio']) ?>"
                                            data-fechafin="<?= e($s['fecha_fin']) ?>"
                                            data-plan="<?= e($s['pid']) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <button class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal" data-bs-target="#mdlDelete"
                                            data-id="<?= e($s['id']) ?>"
                                            data-user="<?= e($s['usuario']) ?>"
                                            data-planname="<?= e($s['plan']) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">Sin suscripciones</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($pages > 1): ?>
                <nav class="mt-2">
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        $qs = $_GET;
                        $qs['page'] = 1;
                        $first = qurl($qs);
                        $qs['page'] = max(1, $page - 1);
                        $prev  = qurl($qs);
                        $qs['page'] = min($pages, $page + 1);
                        $next  = qurl($qs);
                        $qs['page'] = $pages;
                        $last  = qurl($qs);
                        ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($first) ?>">&laquo;</a></li>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($prev) ?>">&lsaquo;</a></li>
                        <li class="page-item disabled"><span class="page-link">Página <?= $page ?> de <?= $pages ?></span></li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($next) ?>">&rsaquo;</a></li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($last) ?>">&raquo;</a></li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal: CREAR -->
    <div class="modal fade" id="mdlCreate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">Nueva suscripción</h5>
                        <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body vstack gap-3">
                        <div>
                            <label class="form-label">Usuario</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">Selecciona…</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= e($u['id']) ?>"><?= e($u['name']) ?> — <?= e($u['email']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Plan</label>
                            <select name="plan_id" class="form-select" required>
                                <option value="">Selecciona…</option>
                                <?php foreach ($plans as $p): ?>
                                    <option value="<?= e($p['id']) ?>"><?= e($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Fecha inicio</label>
                            <input type="date" name="fecha_inicio" class="form-control" value="<?= e(date('Y-m-d')) ?>">
                        </div>
                        <div>
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="pendiente">Pendiente</option>
                                <option value="activa">Activa</option>
                                <option value="inactiva">Inactiva</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
                        <button class="btn btn-success">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: EDITAR -->
    <div class="modal fade" id="mdlEdit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar suscripción</h5>
                        <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body vstack gap-3">
                        <div>
                            <label class="form-label">Plan</label>
                            <select name="plan_id" id="edit_plan" class="form-select" required>
                                <?php foreach ($plans as $p): ?>
                                    <option value="<?= e($p['id']) ?>"><?= e($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Estado</label>
                            <select name="estado" id="edit_estado" class="form-select">
                                <option value="pendiente">Pendiente</option>
                                <option value="activa">Activa</option>
                                <option value="inactiva">Inactiva</option>
                            </select>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label">Fecha inicio</label>
                                <input type="date" name="fecha_inicio" id="edit_inicio" class="form-control">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Fecha fin (opcional)</label>
                                <input type="date" name="fecha_fin" id="edit_fin" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
                        <button class="btn btn-success">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: ELIMINAR -->
    <div class="modal fade" id="mdlDelete" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="del_id">
                    <div class="modal-header">
                        <h6 class="modal-title">Eliminar suscripción</h6>
                        <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">¿Eliminar la suscripción de <strong id="del_user">usuario</strong> (<span id="del_plan">plan</span>)?</p>
                        <small class="text-muted">Se eliminarán pagos asociados.</small>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
                        <button class="btn btn-danger">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Editar: carga datos
        const mdlEdit = document.getElementById('mdlEdit');
        mdlEdit?.addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            document.getElementById('edit_id').value = b.dataset.id;
            document.getElementById('edit_estado').value = b.dataset.estado;
            document.getElementById('edit_inicio').value = b.dataset.fechainicio || '';
            document.getElementById('edit_fin').value = b.dataset.fechafin || '';
            document.getElementById('edit_plan').value = b.dataset.plan || '';
        });

        // Eliminar: setea info
        const mdlDelete = document.getElementById('mdlDelete');
        mdlDelete?.addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            document.getElementById('del_id').value = b.dataset.id;
            document.getElementById('del_user').textContent = b.dataset.user || ('#' + b.dataset.id);
            document.getElementById('del_plan').textContent = b.dataset.planname || '';
        });
    </script>
</body>

</html>