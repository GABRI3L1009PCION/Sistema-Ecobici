<?php
require_once __DIR__ . '/admin_boot.php';
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash('Token inválido', 'danger');
        redirect('/ecobici/administrador/bicicletas.php');
    }
    $a = $_POST['action'] ?? '';
    try {
        if ($a === 'create') {
            $st = $pdo->prepare("INSERT INTO bikes(codigo,tipo,estado,station_id) VALUES(?,?,?,?)");
            $st->execute([trim($_POST['codigo']), $_POST['tipo'], $_POST['estado'], ($_POST['station_id'] ?: null)]);
            flash('Bicicleta creada.');
        } elseif ($a === 'update') {
            $st = $pdo->prepare("UPDATE bikes SET codigo=?, tipo=?, estado=?, station_id=? WHERE id=?");
            $st->execute([trim($_POST['codigo']), $_POST['tipo'], $_POST['estado'], ($_POST['station_id'] ?: null), (int)$_POST['id']]);
            flash('Bicicleta actualizada.');
        } elseif ($a === 'delete') {
            $st = $pdo->prepare("DELETE FROM bikes WHERE id=?");
            $st->execute([(int)$_POST['id']]);
            flash('Bicicleta eliminada.');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('/ecobici/administrador/bicicletas.php');
}

$stations = $pdo->query("SELECT id,nombre FROM stations ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$rows = $pdo->query("SELECT b.*, s.nombre station FROM bikes b LEFT JOIN stations s ON s.id=b.station_id ORDER BY b.id DESC")->fetchAll(PDO::FETCH_ASSOC);

$kpis = $pdo->query("
  SELECT
    SUM(estado='disponible') disp,
    SUM(estado='uso') uso,
    SUM(estado='mantenimiento') mant,
    SUM(tipo='electrica') elec,
    SUM(tipo='tradicional') trad
  FROM bikes
")->fetch(PDO::FETCH_ASSOC) ?: ['disp' => 0, 'uso' => 0, 'mant' => 0, 'elec' => 0, 'trad' => 0];
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EcoBici • Bicicletas</title>
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
    <?php admin_nav('bicis'); ?>
    <main class="container py-4">
        <?php if ($f = flash()): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endif; ?>

        <div class="row g-3 mb-2">
            <div class="col-6 col-lg-3">
                <div class="card-elev p-3">
                    <div class="small text-muted">Disponibles</div>
                    <div class="fs-4 fw-bold text-success"><?= $kpis['disp'] ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card-elev p-3">
                    <div class="small text-muted">En uso</div>
                    <div class="fs-4 fw-bold"><?= $kpis['uso'] ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card-elev p-3">
                    <div class="small text-muted">Mantenimiento</div>
                    <div class="fs-4 fw-bold text-warning"><?= $kpis['mant'] ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card-elev p-3">
                    <div class="small text-muted">Eléctricas / Tradicionales</div>
                    <div class="fs-5 fw-semibold"><?= $kpis['elec'] ?> / <?= $kpis['trad'] ?></div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="mb-0">Bicicletas</h4>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#mdlCreate"><i class="bi bi-plus-lg me-1"></i>Nueva</button>
        </div>

        <div class="card-elev p-3">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Estación</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows): foreach ($rows as $r): ?>
                                <tr>
                                    <td>#<?= e($r['id']) ?></td>
                                    <td class="fw-semibold"><?= e($r['codigo']) ?></td>
                                    <td><span class="badge text-bg-secondary"><?= e($r['tipo']) ?></span></td>
                                    <td>
                                        <?php $cls = $r['estado'] === 'disponible' ? 'success' : ($r['estado'] === 'uso' ? 'primary' : 'warning'); ?>
                                        <span class="badge text-bg-<?= $cls ?>"><?= e($r['estado']) ?></span>
                                    </td>
                                    <td><?= e($r['station'] ?: '—') ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#mdlEdit"
                                            data-id="<?= $r['id'] ?>" data-codigo="<?= e($r['codigo']) ?>" data-tipo="<?= $r['tipo'] ?>"
                                            data-estado="<?= $r['estado'] ?>" data-station="<?= $r['station_id'] ?>"><i class="bi bi-pencil"></i></button>
                                        <form class="d-inline" method="post" onsubmit="return confirm('¿Eliminar bici?');">
                                            <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Sin bicicletas</td>
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
                        <h5 class="modal-title">Nueva bicicleta</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body vstack gap-3">
                        <div><label class="form-label">Código</label><input name="codigo" class="form-control" required></div>
                        <div class="row g-2">
                            <div class="col-sm-4"><label class="form-label">Tipo</label>
                                <select name="tipo" class="form-select">
                                    <option value="tradicional">tradicional</option>
                                    <option value="electrica">eléctrica</option>
                                </select>
                            </div>
                            <div class="col-sm-4"><label class="form-label">Estado</label>
                                <select name="estado" class="form-select">
                                    <option value="disponible">disponible</option>
                                    <option value="uso">uso</option>
                                    <option value="mantenimiento">mantenimiento</option>
                                </select>
                            </div>
                            <div class="col-sm-4"><label class="form-label">Estación</label>
                                <select name="station_id" class="form-select">
                                    <option value="">—</option>
                                    <?php foreach ($stations as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['nombre']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
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
                    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar bicicleta</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body vstack gap-3">
                        <div><label class="form-label">Código</label><input name="codigo" id="edit_codigo" class="form-control" required></div>
                        <div class="row g-2">
                            <div class="col-sm-4"><label class="form-label">Tipo</label>
                                <select name="tipo" id="edit_tipo" class="form-select">
                                    <option value="tradicional">tradicional</option>
                                    <option value="electrica">eléctrica</option>
                                </select>
                            </div>
                            <div class="col-sm-4"><label class="form-label">Estado</label>
                                <select name="estado" id="edit_estado" class="form-select">
                                    <option value="disponible">disponible</option>
                                    <option value="uso">uso</option>
                                    <option value="mantenimiento">mantenimiento</option>
                                </select>
                            </div>
                            <div class="col-sm-4"><label class="form-label">Estación</label>
                                <select name="station_id" id="edit_station" class="form-select">
                                    <option value="">—</option>
                                    <?php foreach ($stations as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['nombre']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
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
            edit_codigo.value = b.dataset.codigo;
            edit_tipo.value = b.dataset.tipo;
            edit_estado.value = b.dataset.estado;
            edit_station.value = b.dataset.station || '';
        });
    </script>
</body>

</html>