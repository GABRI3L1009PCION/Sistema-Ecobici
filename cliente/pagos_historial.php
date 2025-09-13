<?php
// /ecobici/cliente/pagos_historial.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

/* ====== BASE del módulo ====== */
$BASE = '/ecobici/cliente';

/* ====== Guardas ====== */
if (!isset($_SESSION['user'])) { header('Location: /ecobici/login.php'); exit; }
$uid = (int)$_SESSION['user']['id'];

/* ====== Helpers ====== */
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function moneyGTQ($n){ return 'Q '.number_format((float)$n, 2); }
function badgeEstado(string $estado): string {
  $map = [
    'completado' => 'success',
    'pendiente'  => 'secondary',
    'fallido'    => 'danger',
  ];
  $cls = $map[$estado] ?? 'secondary';
  return "<span class=\"badge rounded-pill text-bg-$cls\">".e(ucfirst($estado))."</span>";
}

/* ====== Filtros (GET) ====== */
$estado = $_GET['estado'] ?? ''; // '', 'pendiente', 'completado', 'fallido'
$metodo = $_GET['metodo'] ?? ''; // ej. 'card', 'paypal', 'simulado'
$qWhere = " WHERE s.user_id = :uid ";
$params = [':uid'=>$uid];

if ($estado !== '' && in_array($estado, ['pendiente','completado','fallido'], true)) {
  $qWhere .= " AND p.estado = :estado ";
  $params[':estado'] = $estado;
}
if ($metodo !== '') {
  $qWhere .= " AND p.metodo = :metodo ";
  $params[':metodo'] = $metodo;
}

/* ====== Paginación ====== */
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

/* ====== Totales (para resumen) ====== */
$sqlTot = "
  SELECT
    COUNT(*)                   AS total_pagos,
    SUM(CASE WHEN p.estado='completado' THEN p.monto ELSE 0 END) AS total_pagado,
    SUM(p.monto)               AS total_montos
  FROM payments p
  JOIN subscriptions s ON s.id = p.subscription_id
  $qWhere
";
$st = $pdo->prepare($sqlTot);
$st->execute($params);
$tot = $st->fetch(PDO::FETCH_ASSOC) ?: ['total_pagos'=>0,'total_pagado'=>0,'total_montos'=>0];

/* ====== Distintos métodos para el filtro ====== */
$sqlMet = "
  SELECT DISTINCT p.metodo
  FROM payments p
  JOIN subscriptions s ON s.id = p.subscription_id
  WHERE s.user_id = :uid
";
$metSt = $pdo->prepare($sqlMet);
$metSt->execute([':uid'=>$uid]);
$metodos = array_filter(array_map(fn($r)=>$r['metodo']??'', $metSt->fetchAll(PDO::FETCH_ASSOC)));

/* ====== Consulta principal (con plan) ====== */
$sql = "
  SELECT
    p.id, p.monto, p.metodo, p.referencia, p.estado, p.created_at,
    s.plan_id,
    pl.nombre AS plan_nombre
  FROM payments p
  JOIN subscriptions s ON s.id = p.subscription_id
  JOIN plans pl        ON pl.id = s.plan_id
  $qWhere
  ORDER BY p.created_at DESC
  LIMIT :limit OFFSET :offset
";
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) $st->bindValue($k, $v);
$st->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$st->bindValue(':offset', $offset,  PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* ====== Total para paginación ====== */
$sqlCount = "
  SELECT COUNT(*) AS c
  FROM payments p
  JOIN subscriptions s ON s.id = p.subscription_id
  $qWhere
";
$cst = $pdo->prepare($sqlCount);
$cst->execute($params);
$totalRows = (int)($cst->fetchColumn() ?: 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

/* ====== Flash ====== */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Historial de pagos | EcoBici</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $BASE ?>/styles/app.css">
<style>
  body{background:#f1fff4;}
  .card{border-radius:16px}
  .btn-eco{background:#19c37d;border:0;color:#fff}
  .btn-eco:hover{filter:brightness(0.95);color:#fff}
  .kpi{background:#e7f8ee;border-radius:16px;padding:.75rem 1rem}
  .table> :not(caption)>*>*{vertical-align:middle}
</style>
</head>
<body class="pb-5">
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 m-0">Historial de pagos</h1>
    <a href="<?= $BASE ?>/dashboard.php" class="btn btn-outline-success">← Volver</a>
  </div>

  <?php if($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> shadow-sm"><?= e($flash['msg']) ?></div>
  <?php endif; ?>

  <!-- KPIs -->
  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="kpi"><div class="text-muted small">Pagos registrados</div><div class="fs-4 fw-semibold"><?= (int)$tot['total_pagos'] ?></div></div></div>
    <div class="col-md-4"><div class="kpi"><div class="text-muted small">Total pagado (completados)</div><div class="fs-4 fw-semibold"><?= moneyGTQ($tot['total_pagado'] ?: 0) ?></div></div></div>
    <div class="col-md-4"><div class="kpi"><div class="text-muted small">Suma de importes</div><div class="fs-4 fw-semibold"><?= moneyGTQ($tot['total_montos'] ?: 0) ?></div></div></div>
  </div>

  <!-- Filtros -->
  <form class="row g-2 align-items-end mb-3" method="get">
    <div class="col-sm-4">
      <label class="form-label">Estado</label>
      <select name="estado" class="form-select">
        <option value="">Todos</option>
        <?php foreach (['completado','pendiente','fallido'] as $op): ?>
          <option value="<?= $op ?>" <?= $estado===$op?'selected':'' ?>><?= ucfirst($op) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-4">
      <label class="form-label">Método</label>
      <select name="metodo" class="form-select">
        <option value="">Todos</option>
        <?php foreach ($metodos as $m): ?>
          <option value="<?= e($m) ?>" <?= $metodo===$m?'selected':'' ?>><?= strtoupper(e($m)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-4 d-flex gap-2">
      <button class="btn btn-eco mt-4" type="submit">Filtrar</button>
      <a class="btn btn-outline-secondary mt-4" href="<?= $BASE ?>/pagos_historial.php">Limpiar</a>
    </div>
  </form>

  <!-- Tabla -->
  <div class="card p-3">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Fecha</th>
            <th>Plan</th>
            <th>Método</th>
            <th>Referencia</th>
            <th class="text-end">Monto</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-center py-4">No hay pagos con los filtros actuales.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['created_at']) ?></td>
            <td><?= e($r['plan_nombre']) ?></td>
            <td><?= strtoupper(e($r['metodo'])) ?></td>
            <td><?= e($r['referencia'] ?? '—') ?></td>
            <td class="text-end"><?= moneyGTQ($r['monto']) ?></td>
            <td><?= badgeEstado($r['estado']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <?php if ($totalPages > 1): ?>
      <nav class="mt-2">
        <ul class="pagination pagination-sm mb-0">
          <?php
            // construir query base conservando filtros:
            $baseQS = http_build_query(array_filter(['estado'=>$estado,'metodo'=>$metodo]));
            $mk = fn($p) => $BASE.'/pagos_historial.php?'.($baseQS ? $baseQS.'&' : '').'page='.$p;
          ?>
          <li class="page-item <?= $page<=1?'disabled':'' ?>">
            <a class="page-link" href="<?= $mk(max(1,$page-1)) ?>">«</a>
          </li>
          <?php for($p=1;$p<=$totalPages;$p++): ?>
            <li class="page-item <?= $p===$page?'active':'' ?>">
              <a class="page-link" href="<?= $mk($p) ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
            <a class="page-link" href="<?= $mk(min($totalPages,$page+1)) ?>">»</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
