<?php
require_once __DIR__ . '/admin_boot.php';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash('Token inválido', 'danger');
        redirect('/ecobici/administrador/estaciones.php');
    }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $st = $pdo->prepare("INSERT INTO stations(nombre,tipo,lat,lng,capacidad) VALUES(?,?,?,?,?)");
            $st->execute([trim($_POST['nombre']), $_POST['tipo'], (float)$_POST['lat'], (float)$_POST['lng'], (int)$_POST['capacidad']]);
            flash('Estación creada.');
        } elseif ($action === 'update') {
            $st = $pdo->prepare("UPDATE stations SET nombre=?, tipo=?, lat=?, lng=?, capacidad=? WHERE id=?");
            $st->execute([trim($_POST['nombre']), $_POST['tipo'], (float)$_POST['lat'], (float)$_POST['lng'], (int)$_POST['capacidad'], (int)$_POST['id']]);
            flash('Estación actualizada.');
        } elseif ($action === 'delete') {
            $st = $pdo->prepare("DELETE FROM stations WHERE id=?");
            $st->execute([(int)$_POST['id']]);
            flash('Estación eliminada.');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('/ecobici/administrador/estaciones.php');
}

$rows = $pdo->query("SELECT * FROM stations ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EcoBici • Estaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card-elev {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(2, 6, 23, .06)
        }
    </style>
</head>

<body>
    <?php admin_nav('estaciones'); ?>
    <main class="container py-4">
        <?php if ($f = flash()): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Estaciones</h4>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#mdlCreate"><i class="bi bi-plus-lg me-1"></i>Nueva</button>
        </div>

        <div class="card-elev p-3">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Lat</th>
                            <th>Lng</th>
                            <th>Capacidad</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows): foreach ($rows as $r): ?>
                                <tr>
                                    <td>#<?= e($r['id']) ?></td>
                                    <td class="fw-semibold"><?= e($r['nombre']) ?></td>
                                    <td><span class="badge text-bg-secondary"><?= e($r['tipo']) ?></span></td>
                                    <td class="small"><?= e($r['lat']) ?></td>
                                    <td class="small"><?= e($r['lng']) ?></td>
                                    <td><?= e($r['capacidad']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#mdlEdit"
                                            data-id="<?= $r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>" data-tipo="<?= $r['tipo'] ?>"
                                            data-lat="<?= $r['lat'] ?>" data-lng="<?= $r['lng'] ?>" data-cap="<?= $r['capacidad'] ?>"><i class="bi bi-pencil"></i></button>
                                        <form class="d-inline" method="post" onsubmit="return confirm('¿Eliminar estación?');">
                                            <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Sin estaciones</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Crear -->
    <div class="modal fade" id="mdlCreate" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">Nueva estación</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body vstack gap-3">
                        <div><label class="form-label">Nombre</label><input name="nombre" class="form-control" required></div>
                        <div class="row g-2">
                            <div class="col-sm-4"><label class="form-label">Tipo</label>
                                <select name="tipo" class="form-select">
                                    <option value="dock">dock</option>
                                    <option value="punto">punto</option>
                                </select>
                            </div>
                            <div class="col-sm-4"><label class="form-label">Lat</label><input type="number" step="0.0000001" name="lat" class="form-control" required></div>
                            <div class="col-sm-4"><label class="form-label">Lng</label><input type="number" step="0.0000001" name="lng" class="form-control" required></div>
                        </div>
                        <div><label class="form-label">Capacidad</label><input type="number" name="capacidad" class="form-control" value="10" min="1" required></div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-success">Guardar</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Editar -->
    <div class="modal fade" id="mdlEdit" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar estación</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body vstack gap-3">
                        <div><label class="form-label">Nombre</label><input name="nombre" id="edit_nombre" class="form-control" required></div>
                        <div class="row g-2">
                            <div class="col-sm-4"><label class="form-label">Tipo</label>
                                <select name="tipo" id="edit_tipo" class="form-select">
                                    <option value="dock">dock</option>
                                    <option value="punto">punto</option>
                                </select>
                            </div>
                            <div class="col-sm-4"><label class="form-label">Lat</label><input type="number" step="0.0000001" name="lat" id="edit_lat" class="form-control" required></div>
                            <div class="col-sm-4"><label class="form-label">Lng</label><input type="number" step="0.0000001" name="lng" id="edit_lng" class="form-control" required></div>
                        </div>
                        <div><label class="form-label">Capacidad</label><input type="number" name="capacidad" id="edit_cap" class="form-control" min="1" required></div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-success">Actualizar</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('mdlEdit')?.addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            edit_id.value = b.dataset.id;
            edit_nombre.value = b.dataset.nombre;
            edit_tipo.value = b.dataset.tipo;
            edit_lat.value = b.dataset.lat;
            edit_lng.value = b.dataset.lng;
            edit_cap.value = b.dataset.cap;
        });
    </script>
</body>

</html>