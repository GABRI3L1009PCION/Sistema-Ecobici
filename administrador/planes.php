<?php
// /ecobici/administrador/planes.php
require_once __DIR__ . '/admin_boot.php';

/* =============== Helpers mínimos locales =============== */
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
if (!function_exists('csrf_field')) {
    function csrf_field()
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">';
    }
}
if (!function_exists('admin_flash')) {
    function admin_flash($f)
    {
        if (!$f) return;
        echo '<div class="alert alert-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
}
function qurl(array $overrides = [])
{
    $q = array_merge($_GET, $overrides);
    return '/ecobici/administrador/planes.php?' . http_build_query($q);
}
function sortLink($key, $label, $currentSort, $currentDir)
{
    $nextDir = ($currentSort === $key && $currentDir === 'asc') ? 'desc' : 'asc';
    $url = qurl(['sort' => $key, 'dir' => $nextDir, 'page' => 1]);
    $icon = '';
    if ($currentSort === $key) {
        $icon = $currentDir === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>';
    }
    return '<a class="link-success text-decoration-none" href="' . e($url) . '">' . e($label) . $icon . '</a>';
}

/* =============== ACCIONES (POST) =============== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash('Token inválido. Intenta de nuevo.', 'danger');
        redirect('/ecobici/administrador/planes.php');
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $nombre      = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $precio      = (float)($_POST['precio'] ?? 0);

            if ($nombre === '' || $precio <= 0) {
                throw new Exception('Nombre y precio son obligatorios.');
            }
            $st = $pdo->prepare("INSERT INTO plans (nombre, descripcion, precio) VALUES (?,?,?)");
            $st->execute([$nombre, $descripcion, $precio]);
            flash('Plan creado correctamente.');
        } elseif ($action === 'update') {
            $id          = (int)($_POST['id'] ?? 0);
            $nombre      = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $precio      = (float)($_POST['precio'] ?? 0);

            if ($id <= 0 || $nombre === '' || $precio <= 0) {
                throw new Exception('Datos inválidos.');
            }
            $st = $pdo->prepare("UPDATE plans SET nombre=?, descripcion=?, precio=? WHERE id=?");
            $st->execute([$nombre, $descripcion, $precio, $id]);
            flash('Plan actualizado.');
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido.');

            // Evita eliminar si tiene suscripciones
            $st = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE plan_id=?");
            $st->execute([$id]);
            if ((int)$st->fetchColumn() > 0) {
                throw new Exception('No puedes eliminar un plan con suscripciones vinculadas.');
            }

            $st = $pdo->prepare("DELETE FROM plans WHERE id=?");
            $st->execute([$id]);
            flash('Plan eliminado.');
        }
    } catch (PDOException $e) {
        // Nombre duplicado (uq_plans_nombre)
        if ($e->getCode() === '23000') {
            flash('Ya existe un plan con ese nombre.', 'danger');
        } else {
            flash('Error de BD: ' . $e->getMessage(), 'danger');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }

    redirect('/ecobici/administrador/planes.php');
}

/* =============== LISTADO con filtros/orden/paginación =============== */
$q        = trim($_GET['q'] ?? '');
$minPrice = trim($_GET['min'] ?? '');
$maxPrice = trim($_GET['max'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 10;

$allowedSort = ['id', 'nombre', 'precio', 'created_at', 'suscripciones']; // ← añadido 'id'
$sort = $_GET['sort'] ?? 'precio';
if (!in_array($sort, $allowedSort, true)) $sort = 'precio';

$dir = strtolower($_GET['dir'] ?? 'asc');
$dir = $dir === 'desc' ? 'desc' : 'asc';

$where = [];
$args  = [];

// Filtro: búsqueda por nombre/descripcion
if ($q !== '') {
    $where[] = "(p.nombre LIKE ? OR p.descripcion LIKE ?)";
    $like = "%$q%";
    $args[] = $like;
    $args[] = $like;
}
// Filtro: rango de precio
if ($minPrice !== '' && is_numeric($minPrice)) {
    $where[] = "p.precio >= ?";
    $args[]  = (float)$minPrice;
}
if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $where[] = "p.precio <= ?";
    $args[]  = (float)$maxPrice;
}
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Para ordenar por suscripciones, usamos el alias del COUNT
$orderMap = [
    'id'             => 'p.id',          // ← añadido
    'nombre'         => 'p.nombre',
    'precio'         => 'p.precio',
    'created_at'     => 'p.created_at',
    'suscripciones'  => 'suscripciones',
];
$orderBy = $orderMap[$sort] . ' ' . strtoupper($dir);

// Total (con los mismos filtros)
$sqlCount = "SELECT COUNT(*) FROM plans p $wsql";
$st = $pdo->prepare($sqlCount);
$st->execute($args);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

// Datos (incluye conteo de suscripciones vinculadas)
$sql = "
  SELECT p.id, p.nombre, p.descripcion, p.precio, p.created_at,
         COUNT(s.id) AS suscripciones
  FROM plans p
  LEFT JOIN subscriptions s ON s.plan_id = p.id
  $wsql
  GROUP BY p.id, p.nombre, p.descripcion, p.precio, p.created_at
  ORDER BY $orderBy, p.id ASC
  LIMIT $perPage OFFSET $offset
";
$st = $pdo->prepare($sql);
$st->execute($args);
$planes = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EcoBici • Admin • Planes</title>
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

        .desc {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            line-clamp: 2;
            /* estándar para quitar warning */
            overflow: hidden;
            color: var(--muted);
        }

        .price {
            font-weight: 700;
        }

        .badge-soft {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534
        }

        /* Responsivo: oculta columnas menos críticas en móviles */
        @media (max-width: 767.98px) {

            .col-desc,
            .col-fecha {
                display: none;
            }
        }
    </style>
</head>

<body>
    <?php admin_nav('planes'); ?>

    <main class="container py-4">
        <?php admin_flash(flash()); ?>

        <!-- Header + Filtros -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
            <h4 class="mb-0">Planes</h4>

            <form class="d-flex flex-wrap gap-2" method="get">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o descripción…">
                </div>
                <div class="input-group" style="max-width:200px">
                    <span class="input-group-text">Q Min</span>
                    <input class="form-control" type="number" step="0.01" name="min" value="<?= e($minPrice) ?>">
                </div>
                <div class="input-group" style="max-width:200px">
                    <span class="input-group-text">Q Máx</span>
                    <input class="form-control" type="number" step="0.01" name="max" value="<?= e($maxPrice) ?>">
                </div>
                <button class="btn btn-outline-success">Aplicar</button>
                <a class="btn btn-outline-secondary" href="/ecobici/administrador/planes.php">Limpiar</a>
                <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#mdlCreate">
                    <i class="bi bi-plus-lg me-1"></i>Nuevo plan
                </button>
            </form>
        </div>

        <!-- Tabla -->
        <div class="card-elev p-3">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th><?= sortLink('id', 'ID', $sort, $dir) ?></th>
                            <th><?= sortLink('nombre', 'Nombre', $sort, $dir) ?></th>
                            <th><?= sortLink('precio', 'Precio', $sort, $dir) ?></th>
                            <th class="col-desc">Descripción</th>
                            <th><?= sortLink('suscripciones', 'Suscripciones', $sort, $dir) ?></th>
                            <th class="col-fecha"><?= sortLink('created_at', 'Creado', $sort, $dir) ?></th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($planes): foreach ($planes as $p): ?>
                                <tr>
                                    <td>#<?= e($p['id']) ?></td>
                                    <td class="fw-semibold"><?= e($p['nombre']) ?></td>
                                    <td class="price">Q <?= number_format((float)$p['precio'], 2) ?></td>
                                    <td class="col-desc">
                                        <div class="desc"><?= nl2br(e($p['descripcion'] ?? '')) ?></div>
                                    </td>
                                    <td>
                                        <?php if ((int)$p['suscripciones'] > 0): ?>
                                            <span class="badge rounded-pill text-bg-secondary"><?= (int)$p['suscripciones'] ?></span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill text-bg-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted col-fecha"><?= e($p['created_at']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-success me-1"
                                            data-bs-toggle="modal" data-bs-target="#mdlEdit"
                                            data-id="<?= e($p['id']) ?>"
                                            data-nombre="<?= e($p['nombre']) ?>"
                                            data-precio="<?= e($p['precio']) ?>"
                                            data-desc="<?= e($p['descripcion']) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <?php if ((int)$p['suscripciones'] > 0): ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled title="Tiene suscripciones vinculadas">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal" data-bs-target="#mdlDelete"
                                                data-id="<?= e($p['id']) ?>" data-name="<?= e($p['nombre']) ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Sin planes</td>
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
                        $next = qurl($qs);
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
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">Nuevo plan</h5>
                        <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body vstack gap-3">
                        <div>
                            <label class="form-label">Nombre</label>
                            <input name="nombre" class="form-control" required placeholder="Paseo / Ruta / Maratón…">
                        </div>
                        <div>
                            <label class="form-label">Precio (Q)</label>
                            <div class="input-group">
                                <span class="input-group-text">Q</span>
                                <input name="precio" type="number" step="0.01" min="0.01" class="form-control" required>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="3" placeholder="Opcional"></textarea>
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
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar plan</h5>
                        <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body vstack gap-3">
                        <div>
                            <label class="form-label">Nombre</label>
                            <input name="nombre" id="edit_nombre" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label">Precio (Q)</label>
                            <div class="input-group">
                                <span class="input-group-text">Q</span>
                                <input name="precio" id="edit_precio" type="number" step="0.01" min="0.01" class="form-control" required>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" id="edit_desc" class="form-control" rows="3"></textarea>
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
                        <h6 class="modal-title">Eliminar plan</h6>
                        <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">¿Eliminar <strong id="del_name">este</strong> plan?</p>
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
        document.getElementById('mdlEdit')?.addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            edit_id.value = b.dataset.id;
            edit_nombre.value = b.dataset.nombre || '';
            edit_precio.value = b.dataset.precio || '';
            edit_desc.value = b.dataset.desc || '';
        });
        // Eliminar: setea id y nombre
        document.getElementById('mdlDelete')?.addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            del_id.value = b.dataset.id;
            del_name.textContent = b.dataset.name || ('#' + b.dataset.id);
        });
    </script>
</body>

</html>