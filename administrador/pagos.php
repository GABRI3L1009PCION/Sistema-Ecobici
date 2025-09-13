<?php
require_once __DIR__ . '/admin_boot.php';

// ======================== Acciones (POST) ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash('Token inválido', 'danger');
        redirect('/ecobici/administrador/pagos.php');
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $subscription_id = (int)($_POST['subscription_id'] ?? 0);
            $monto            = (float)($_POST['monto'] ?? 0);
            $metodo           = trim($_POST['metodo'] ?? 'simulado');
            $referencia       = trim($_POST['referencia'] ?? '');
            $estado           = in_array($_POST['estado'] ?? '', ['pendiente', 'completado', 'fallido'], true) ? $_POST['estado'] : 'pendiente';

            if ($subscription_id <= 0 || $monto <= 0) {
                throw new Exception('Suscripción y monto son obligatorios.');
            }

            // validar suscripción existente
            $ok = $pdo->prepare("SELECT 1 FROM subscriptions WHERE id=? LIMIT 1");
            $ok->execute([$subscription_id]);
            if (!$ok->fetch()) throw new Exception('La suscripción no existe.');

            $st = $pdo->prepare("INSERT INTO payments (subscription_id,monto,metodo,referencia,estado) VALUES (?,?,?,?,?)");
            $st->execute([$subscription_id, $monto, $metodo, $referencia, $estado]);
            flash('Pago creado.');
        } elseif ($action === 'update') {
            $id        = (int)($_POST['id'] ?? 0);
            $monto     = (float)($_POST['monto'] ?? 0);
            $metodo    = trim($_POST['metodo'] ?? 'simulado');
            $referencia = trim($_POST['referencia'] ?? '');
            $estado    = in_array($_POST['estado'] ?? '', ['pendiente', 'completado', 'fallido'], true) ? $_POST['estado'] : 'pendiente';

            if ($id <= 0 || $monto <= 0) throw new Exception('Datos inválidos.');

            $st = $pdo->prepare("UPDATE payments SET monto=?,metodo=?,referencia=?,estado=? WHERE id=?");
            $st->execute([$monto, $metodo, $referencia, $estado, $id]);
            flash('Pago actualizado.');
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido.');
            $st = $pdo->prepare("DELETE FROM payments WHERE id=?");
            $st->execute([$id]);
            flash('Pago eliminado.');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('/ecobici/administrador/pagos.php');
}

// ======================== Listado con filtros ========================
$allowedEstados = ['pendiente', 'completado', 'fallido'];
$q       = trim($_GET['q'] ?? '');
$estadoF = $_GET['estado'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$where = [];
$args  = [];

if ($q !== '') {
    // Busca por usuario, plan, método, referencia o ID del pago
    $where[] = "(u.name LIKE ? OR pl.nombre LIKE ? OR p.metodo LIKE ? OR p.referencia LIKE ? OR p.id = ?)";
    $like = "%$q%";
    $args[] = $like;
    $args[] = $like;
    $args[] = $like;
    $args[] = $like;
    $args[] = (int)$q; // si no es numérico, coincidirá solo con 0
}
if (in_array($estadoF, $allowedEstados, true)) {
    $where[] = "p.estado = ?";
    $args[]  = $estadoF;
}

$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Total para paginación
$sqlCount = "
  SELECT COUNT(*)
  FROM payments p
  JOIN subscriptions s ON s.id=p.subscription_id
  JOIN users u ON u.id=s.user_id
  JOIN plans pl ON pl.id=s.plan_id
  $wsql
";
$st = $pdo->prepare($sqlCount);
$st->execute($args);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$offset = ($page - 1) * $perPage;

$sql = "
  SELECT p.id,p.monto,p.metodo,p.referencia,p.estado,p.created_at,
         s.id sid, u.name usuario, pl.nombre plan
  FROM payments p
  JOIN subscriptions s ON s.id=p.subscription_id
  JOIN users u ON u.id=s.user_id
  JOIN plans pl ON pl.id=s.plan_id
  $wsql
  ORDER BY p.created_at DESC
  LIMIT $perPage OFFSET $offset
";
$st = $pdo->prepare($sql);
$st->execute($args);
$pagos = $st->fetchAll(PDO::FETCH_ASSOC);

// Suscripciones para el modal de creación (últimas 100)
$subs = $pdo->query("
  SELECT s.id, u.name usuario, pl.nombre plan
  FROM subscriptions s
  JOIN users u ON u.id=s.user_id
  JOIN plans pl ON pl.id=s.plan_id
  ORDER BY s.id DESC LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EcoBici • Admin • Pagos</title>
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

        /* Responsivo: ocultar columnas menos críticas en móviles */
        @media (max-width: 767.98px) {

            .col-metodo,
            .col-fecha {
                display: none;
            }
        }
    </style>
</head>

<body>
    <?php admin_nav('pagos'); ?>

    <main class="container py-4">
        <?php admin_flash(flash()); ?>

        <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center mb-3">
            <h4 class="mb-0">Pagos</h4>

            <!-- Filtros -->
            <form class="d-flex flex-wrap gap-2" method="get">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar: usuario, plan, referencia…">
                </div>
                <select class="form-select" name="estado" style="min-width:160px">
                    <option value="">Estado (todos)</option>
                    <?php foreach ($allowedEstados as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $estadoF === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-success">Aplicar</button>
                <a class="btn btn-outline-secondary" href="/ecobici/administrador/pagos.php">Limpiar</a>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#mdlCreate">
                    <i class="bi bi-plus-lg me-1"></i>Nuevo pago
                </button>
            </form>
        </div>

        <div class="card-elev p-3">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th class="text-nowrap">ID</th>
                            <th>Usuario</th>
                            <th>Plan</th>
                            <th>Monto</th>
                            <th class="col-metodo">Método</th>
                            <th>Estado</th>
                            <th class="col-fecha">Fecha</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pagos): foreach ($pagos as $p): ?>
                                <tr>
                                    <td class="text-nowrap">#<?= e($p['id']) ?></td>
                                    <td><?= e($p['usuario']) ?></td>
                                    <td><?= e($p['plan']) ?> <small class="text-muted">(#<?= e($p['sid']) ?>)</small></td>
                                    <td class="fw-semibold text-success">Q <?= number_format((float)$p['monto'], 2) ?></td>
                                    <td class="col-metodo"><?= e($p['metodo']) ?></td>
                                    <td>
                                        <?php $cls = $p['estado'] === 'completado' ? 'success' : ($p['estado'] === 'pendiente' ? 'warning' : 'danger'); ?>
                                        <span class="badge text-bg-<?= $cls ?>"><?= e($p['estado']) ?></span>
                                    </td>
                                    <td class="small text-muted col-fecha"><?= e($p['created_at']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-success me-1" data-bs-toggle="modal" data-bs-target="#mdlEdit"
                                            data-id="<?= e($p['id']) ?>"
                                            data-monto="<?= e($p['monto']) ?>"
                                            data-metodo="<?= e($p['metodo']) ?>"
                                            data-ref="<?= e($p['referencia']) ?>"
                                            data-estado="<?= e($p['estado']) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar pago?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No hay pagos que coincidan con el filtro.</td>
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
                        $qsBase = $_GET; // preserva filtros
                        $qsBase['page'] = 1;
                        $firstUrl = '/ecobici/administrador/pagos.php?' . http_build_query($qsBase);
                        $qsBase['page'] = max(1, $page - 1);
                        $prevUrl  = '/ecobici/administrador/pagos.php?' . http_build_query($qsBase);
                        $qsBase['page'] = min($pages, $page + 1);
                        $nextUrl  = '/ecobici/administrador/pagos.php?' . http_build_query($qsBase);
                        $qsBase['page'] = $pages;
                        $lastUrl  = '/ecobici/administrador/pagos.php?' . http_build_query($qsBase);
                        ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($firstUrl) ?>" aria-label="Primera">&laquo;</a></li>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($prevUrl) ?>" aria-label="Anterior">&lsaquo;</a></li>
                        <li class="page-item disabled"><span class="page-link">Página <?= $page ?> de <?= $pages ?></span></li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($nextUrl) ?>" aria-label="Siguiente">&rsaquo;</a></li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($lastUrl) ?>" aria-label="Última">&raquo;</a></li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal: Crear -->
    <div class="modal fade" id="mdlCreate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">Nuevo pago</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body vstack gap-3">
                        <div>
                            <label class="form-label">Suscripción</label>
                            <select name="subscription_id" class="form-select" required>
                                <option value="">Selecciona…</option>
                                <?php foreach ($subs as $s): ?>
                                    <option value="<?= e($s['id']) ?>">#<?= e($s['id']) ?> — <?= e($s['usuario']) ?> / <?= e($s['plan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Monto (Q)</label>
                            <input type="number" step="0.01" min="0.01" name="monto" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label">Método</label>
                            <input name="metodo" class="form-control" placeholder="simulado, efectivo, tarjeta…">
                        </div>
                        <div>
                            <label class="form-label">Referencia</label>
                            <input name="referencia" class="form-control">
                        </div>
                        <div>
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="pendiente">Pendiente</option>
                                <option value="completado">Completado</option>
                                <option value="fallido">Fallido</option>
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

    <!-- Modal: Editar -->
    <div class="modal fade" id="mdlEdit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar pago</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body vstack gap-3">
                        <div>
                            <label class="form-label">Monto (Q)</label>
                            <input type="number" step="0.01" min="0.01" name="monto" id="edit_monto" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label">Método</label>
                            <input name="metodo" id="edit_metodo" class="form-control">
                        </div>
                        <div>
                            <label class="form-label">Referencia</label>
                            <input name="referencia" id="edit_ref" class="form-control">
                        </div>
                        <div>
                            <label class="form-label">Estado</label>
                            <select name="estado" id="edit_estado" class="form-select">
                                <option value="pendiente">Pendiente</option>
                                <option value="completado">Completado</option>
                                <option value="fallido">Fallido</option>
                            </select>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const mdlEdit = document.getElementById('mdlEdit');
        mdlEdit?.addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            document.getElementById('edit_id').value = b.dataset.id;
            document.getElementById('edit_monto').value = b.dataset.monto;
            document.getElementById('edit_metodo').value = b.dataset.metodo;
            document.getElementById('edit_ref').value = b.dataset.ref || '';
            document.getElementById('edit_estado').value = b.dataset.estado;
        });
    </script>
</body>

</html>