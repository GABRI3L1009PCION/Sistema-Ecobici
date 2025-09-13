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
    try {
        $a = $_POST['action'] ?? '';
        if ($a === 'create') {
            $nombre = trim($_POST['nombre'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');
            $lat = ($_POST['lat'] !== '') ? (float)$_POST['lat'] : null;
            $lng = ($_POST['lng'] !== '') ? (float)$_POST['lng'] : null;
            $estado = in_array($_POST['estado'] ?? 'operativa', ['operativa', 'mantenimiento', 'cerrada'], true) ? $_POST['estado'] : 'operativa';
            if ($nombre === '') throw new Exception('Nombre requerido');
            $st = $pdo->prepare("INSERT INTO stations(nombre,direccion,lat,lng,estado) VALUES(?,?,?,?,?)");
            $st->execute([$nombre, $direccion, $lat, $lng, $estado]);
            flash('Estación creada');
        } elseif ($a === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido');
            $nombre = trim($_POST['nombre'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');
            $lat = ($_POST['lat'] !== '') ? (float)$_POST['lat'] : null;
            $lng = ($_POST['lng'] !== '') ? (float)$_POST['lng'] : null;
            $estado = in_array($_POST['estado'] ?? 'operativa', ['operativa', 'mantenimiento', 'cerrada'], true) ? $_POST['estado'] : 'operativa';
            if ($nombre === '') throw new Exception('Nombre requerido');
            $st = $pdo->prepare("UPDATE stations SET nombre=?,direccion=?,lat=?,lng=?,estado=? WHERE id=?");
            $st->execute([$nombre, $direccion, $lat, $lng, $estado, $id]);
            flash('Estación actualizada');
        } elseif ($a === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido');
            // Evita borrar si tiene bicis
            $c = (int)$pdo->query("SELECT COUNT(*) FROM bikes WHERE station_id=" . $id)->fetchColumn();
            if ($c > 0) throw new Exception('No puedes eliminar: tiene bicicletas asignadas.');
            $st = $pdo->prepare("DELETE FROM stations WHERE id=?");
            $st->execute([$id]);
            flash('Estación eliminada');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('/ecobici/administrador/estaciones.php');
}

$q = trim($_GET['q'] ?? '');
$estado = trim($_GET['estado'] ?? '');
$where = [];
$args = [];
if ($q !== '') {
    $where[] = "(nombre LIKE ? OR direccion LIKE ?)";
    $args[] = "%$q%";
    $args[] = "%$q%";
}
if ($estado !== '' && in_array($estado, ['operativa', 'mantenimiento', 'cerrada'], true)) {
    $where[] = "estado=?";
    $args[] = $estado;
}
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$rows = $pdo->prepare("SELECT id,nombre,direccion,lat,lng,estado,created_at FROM stations $wsql ORDER BY nombre ASC");
$rows->execute($args);
$estaciones = $rows->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EcoBici • Estaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body><?php admin_nav('stations'); ?>
    <main class="container py-4"><?php admin_flash(flash()); ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Estaciones</h4>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#mdlCreate"><i class="bi bi-plus-lg me-1"></i>Nueva estación</button>
        </div>
        <form class="row g-2 mb-3">
            <div class="col-md"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar..."></div>
            <div class="col-md">
                <select class="form-select" name="estado">
                    <option value="">Estado...</option>
                    <?php foreach (['operativa', 'mantenimiento', 'cerrada'] as $es): ?>
                        <option value="<?= $es ?>" <?= $estado === $es ? 'selected' : '' ?>><?= ucfirst($es) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-success">Aplicar</button></div>
            <div class="col-auto"><a class="btn btn-outline-secondary" href="/ecobici/administrador/estaciones.php">Limpiar</a></div>
        </form>

        <div class="card p-3">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Dirección</th>
                            <th>Lat/Lng</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$estaciones): ?><tr>
                                <td colspan="6" class="text-center text-muted">Sin estaciones</td>
                            </tr><?php endif; ?>
                        <?php foreach ($estaciones as $s): ?>
                            <tr>
                                <td>#<?= e($s['id']) ?></td>
                                <td class="fw-semibold"><?= e($s['nombre']) ?></td>
                                <td class="small text-muted"><?= e($s['direccion'] ?? '—') ?></td>
                                <td class="small"><?= e($s['lat'] !== null ? $s['lat'] : '—') ?> / <?= e($s['lng'] !== null ? $s['lng'] : '—') ?></td>
                                <td><span class="badge text-bg-<?= $s['estado'] === 'operativa' ? 'success' : ($s['estado'] === 'mantenimiento' ? 'warning' : 'secondary') ?>"><?= e($s['estado']) ?></span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-success me-1" data-bs-toggle="modal" data-bs-target="#mdlEdit"
                                        data-id="<?= e($s['id']) ?>" data-nombre="<?= e($s['nombre']) ?>" data-direccion="<?= e($s['direccion']) ?>"
                                        data-lat="<?= e($s['lat']) ?>" data-lng="<?= e($s['lng']) ?>" data-estado="<?= e($s['estado']) ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form class="d-inline" method="post" onsubmit="return confirm('¿Eliminar esta estación?');">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($s['id']) ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modales -->
    <div class="modal fade" id="mdlCreate" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">Nueva estación</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body vstack gap-2">
                        <input class="form-control" name="nombre" placeholder="Nombre" required>
                        <input class="form-control" name="direccion" placeholder="Dirección">
                        <div class="row g-2">
                            <div class="col"><input class="form-control" name="lat" placeholder="Lat"></div>
                            <div class="col"><input class="form-control" name="lng" placeholder="Lng"></div>
                        </div>
                        <select class="form-select" name="estado">
                            <option value="operativa">Operativa</option>
                            <option value="mantenimiento">Mantenimiento</option>
                            <option value="cerrada">Cerrada</option>
                        </select>
                    </div>
                    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-success">Guardar</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="mdlEdit" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" id="e_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar estación</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body vstack gap-2">
                        <input class="form-control" name="nombre" id="e_nombre" required>
                        <input class="form-control" name="direccion" id="e_direccion">
                        <div class="row g-2">
                            <div class="col"><input class="form-control" name="lat" id="e_lat"></div>
                            <div class="col"><input class="form-control" name="lng" id="e_lng"></div>
                        </div>
                        <select class="form-select" name="estado" id="e_estado">
                            <option value="operativa">Operativa</option>
                            <option value="mantenimiento">Mantenimiento</option>
                            <option value="cerrada">Cerrada</option>
                        </select>
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
            e_id.value = b.dataset.id;
            e_nombre.value = b.dataset.nombre || '';
            e_direccion.value = b.dataset.direccion || '';
            e_lat.value = b.dataset.lat || '';
            e_lng.value = b.dataset.lng || '';
            e_estado.value = b.dataset.estado || 'operativa';
        });
    </script>
</body>

</html>

</html>