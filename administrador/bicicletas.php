<?php
require_once __DIR__ . '/admin_boot.php';
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$stations = $pdo->query("SELECT id,nombre FROM stations ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash('Token inválido', 'danger');
        redirect('/ecobici/administrador/bicicletas.php');
    }
    try {
        $a = $_POST['action'] ?? '';
        if ($a === 'create') {
            $codigo = trim($_POST['codigo'] ?? '');
            $station_id = (int)($_POST['station_id'] ?? 0);
            $estado = in_array($_POST['estado'] ?? 'operativa', ['operativa', 'mantenimiento', 'baja'], true) ? $_POST['estado'] : 'operativa';
            $notas = trim($_POST['notas'] ?? '');
            if ($codigo === '') throw new Exception('Código requerido');
            $st = $pdo->prepare("INSERT INTO bikes(codigo,station_id,estado,notas) VALUES(?,?,?,?)");
            $st->execute([$codigo, $station_id ?: null, $estado, $notas]);
            flash('Bici creada');
        } elseif ($a === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido');
            $codigo = trim($_POST['codigo'] ?? '');
            $station_id = (int)($_POST['station_id'] ?? 0);
            $estado = in_array($_POST['estado'] ?? 'operativa', ['operativa', 'mantenimiento', 'baja'], true) ? $_POST['estado'] : 'operativa';
            $notas = trim($_POST['notas'] ?? '');
            if ($codigo === '') throw new Exception('Código requerido');
            $st = $pdo->prepare("UPDATE bikes SET codigo=?,station_id=?,estado=?,notas=? WHERE id=?");
            $st->execute([$codigo, $station_id ?: null, $estado, $notas, $id]);
            flash('Bici actualizada');
        } elseif ($a === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido');
            // Evita borrar si tiene viajes
            $c = (int)$pdo->query("SELECT COUNT(*) FROM trips WHERE bike_id=" . $id)->fetchColumn();
            if ($c > 0) throw new Exception('No puedes eliminar: tiene viajes asociados.');
            $st = $pdo->prepare("DELETE FROM bikes WHERE id=?");
            $st->execute([$id]);
            flash('Bici eliminada');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('/ecobici/administrador/bicicletas.php');
}

$q = trim($_GET['q'] ?? '');
$f_est = trim($_GET['estado'] ?? '');
$f_sta = (int)($_GET['station'] ?? 0);
$where = [];
$args = [];
if ($q !== '') {
    $where[] = "(b.codigo LIKE ? OR b.notas LIKE ?)";
    $args[] = "%$q%";
    $args[] = "%$q%";
}
if ($f_est !== '' && in_array($f_est, ['operativa', 'mantenimiento', 'baja'], true)) {
    $where[] = "b.estado=?";
    $args[] = $f_est;
}
if ($f_sta > 0) {
    $where[] = "b.station_id=?";
    $args[] = $f_sta;
}
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$rows = $pdo->prepare("
  SELECT b.id,b.codigo,b.estado,b.notas,b.created_at, s.nombre estacion
  FROM bikes b LEFT JOIN stations s ON s.id=b.station_id
  $wsql ORDER BY b.created_at DESC
");
$rows->execute($args);
$bikes = $rows->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EcoBici • Bicicletas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body><?php admin_nav('bikes'); ?>
    <main class="container py-4"><?php admin_flash(flash()); ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Bicicletas</h4>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#mdlCreate"><i class="bi bi-plus-lg me-1"></i>Nueva bici</button>
        </div>

        <form class="row g-2 mb-3">
            <div class="col-md"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por código/notas"></div>
            <div class="col-md">
                <select class="form-select" name="station">
                    <option value="0">Estación...</option>
                    <?php foreach ($stations as $s): ?><option value="<?= $s['id'] ?>" <?= $f_sta === $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md">
                <select class="form-select" name="estado">
                    <option value="">Estado...</option>
                    <?php foreach (['operativa', 'mantenimiento', 'baja'] as $es): ?>
                        <option value="<?= $es ?>" <?= $f_est === $es ? 'selected' : '' ?>><?= ucfirst($es) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-success">Aplicar</button></div>
            <div class="col-auto"><a class="btn btn-outline-secondary" href="/ecobici/administrador/bicicletas.php">Limpiar</a></div>
        </form>

        <div class="card p-3">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Estación</th>
                            <th>Estado</th>
                            <th>Notas</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$bikes): ?><tr>
                                <td colspan="6" class="text-center text-muted">Sin bicis</td>
                            </tr><?php endif; ?>
                        <?php foreach ($bikes as $b): ?>
                            <tr>
                                <td>#<?= e($b['id']) ?></td>
                                <td class="fw-semibold"><?= e($b['codigo']) ?></td>
                                <td><?= e($b['estacion'] ?? '—') ?></td>
                                <td><span class="badge text-bg-<?= $b['estado'] === 'operativa' ? 'success' : ($b['estado'] === 'mantenimiento' ? 'warning' : 'secondary') ?>"><?= e($b['estado']) ?></span></td>
                                <td class="small text-muted"><?= nl2br(e($b['notas'] ?? '')) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-success me-1" data-bs-toggle="modal" data-bs-target="#mdlEdit"
                                        data-id="<?= $b['id'] ?>" data-codigo="<?= e($b['codigo']) ?>" data-station="<?= e($b['estacion']) ?>"
                                        data-stid="<?= $f_sta ?>" data-estado="<?= $b['estado'] ?>" data-notas="<?= e($b['notas']) ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form class="d-inline" method="post" onsubmit="return confirm('¿Eliminar bicicleta?');">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($b['id']) ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modales crear/editar (muy similares a estaciones, con select estación y estado) -->
    <div class="modal fade" id="mdlCreate" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">Nueva bici</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body vstack gap-2">
                        <input class="form-control" name="codigo" placeholder="Código" required>
                        <select class="form-select" name="station_id">
                            <option value="0">Sin estación</option>
                            <?php foreach ($stations as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['nombre']) ?></option><?php endforeach; ?>
                        </select>
                        <select class="form-select" name="estado">
                            <option value="operativa">Operativa</option>
                            <option value="mantenimiento">Mantenimiento</option>
                            <option value="baja">Baja</option>
                        </select>
                        <textarea class="form-control" name="notas" rows="3" placeholder="Notas (opcional)"></textarea>
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
                        <h5 class="modal-title">Editar bici</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body vstack gap-2">
                        <input class="form-control" name="codigo" id="e_codigo" required>
                        <select class="form-select" name="station_id" id="e_station">
                            <option value="0">Sin estación</option>
                            <?php foreach ($stations as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['nombre']) ?></option><?php endforeach; ?>
                        </select>
                        <select class="form-select" name="estado" id="e_estado">
                            <option value="operativa">Operativa</option>
                            <option value="mantenimiento">Mantenimiento</option>
                            <option value="baja">Baja</option>
                        </select>
                        <textarea class="form-control" name="notas" id="e_notas" rows="3"></textarea>
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
            e_codigo.value = b.dataset.codigo || '';
            e_estado.value = b.dataset.estado || 'operativa';
            e_notas.value = b.dataset.notas || '';
        });
    </script>
</body>

</html>