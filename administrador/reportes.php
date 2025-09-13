<?php
// /ecobici/administrador/reportes.php
declare(strict_types=1);

// ===== Sesión y conexión =====
require_once __DIR__ . '/admin_boot.php';

// ===== Definir ruta de fuentes para FPDF (carpeta puede estar vacía) =====
if (!defined('FPDF_FONTPATH')) {
    define('FPDF_FONTPATH', __DIR__ . '/tools/font/');
}

// ===== Cargar FPDF =====
require_once __DIR__ . '/tools/fpdf.php';

// ================= Helpers =================
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function d2($v){ return number_format((float)$v, 2, '.', ','); }
function today(): string { return (new DateTime('now'))->format('Y-m-d'); }
function dtNow(): string { return (new DateTime('now'))->format('Y-m-d H:i'); }

// Defaults de filtros
$section = $_GET['section'] ?? 'uso'; // uso | ingresos | co2 | usuarios | bikes | estaciones
$group   = $_GET['group']   ?? 'day'; // day|week|month (sólo aplica a analíticos)
$from    = $_GET['from']    ?? date('Y-m-01');
$to      = $_GET['to']      ?? today();
$export  = $_GET['export']  ?? null;  // null|pdf|excel

// ========= Utilidad de consulta por sección =========
function tableQuery(PDO $pdo, string $sql, array $params=[]): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    return ['columns'=>array_keys($rows ? $rows[0] : []), 'rows'=>$rows];
}

function fetch_report(PDO $pdo, string $section, string $group, string $from, string $to): array {
    $fromDt = new DateTime($from.' 00:00:00');
    $toDt   = new DateTime($to.' 23:59:59');

    if (in_array($section, ['uso','ingresos','co2'], true)) {
        switch ($group) {
            case 'week':
                // Semana ISO (WEEK(...,3))
                $grp = "CONCAT(YEAR(trunc_dt), '-W', LPAD(WEEK(trunc_dt, 3),2,'0'))";
                break;
            case 'month':
                $grp = "DATE_FORMAT(trunc_dt, '%Y-%m')";
                break;
            default:
                $grp = "DATE(trunc_dt)";
        }
    }

    if ($section === 'uso') {
        $sql = "
            SELECT $grp AS periodo,
                   COUNT(*) AS viajes,
                   COUNT(DISTINCT bike_id) AS bicis_distintas,
                   SUM(COALESCE(distancia_km,0)) AS km,
                   SUM(COALESCE(costo,0)) AS costo
            FROM (
                SELECT t.*, t.start_at AS trunc_dt
                FROM trips t
                WHERE t.start_at BETWEEN :f AND :t
            ) x
            GROUP BY periodo
            ORDER BY MIN(trunc_dt)
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':f'=>$fromDt->format('Y-m-d H:i:s'), ':t'=>$toDt->format('Y-m-d H:i:s')]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return ['columns'=>['Periodo','Viajes','Bicicletas distintas','Km','Costo'], 'rows'=>$rows];

    } elseif ($section === 'ingresos') {
        $sql = "
            SELECT $grp AS periodo,
                   COUNT(*) AS pagos,
                   SUM(COALESCE(monto,0)) AS ingresos
            FROM (
                SELECT p.*, p.created_at AS trunc_dt
                FROM payments p
                WHERE p.estado='completado'
                  AND p.created_at BETWEEN :f AND :t
            ) x
            GROUP BY periodo
            ORDER BY MIN(trunc_dt)
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':f'=>$fromDt->format('Y-m-d H:i:s'), ':t'=>$toDt->format('Y-m-d H:i:s')]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return ['columns'=>['Periodo','Pagos','Ingresos (Q)'], 'rows'=>$rows];

    } elseif ($section === 'co2') {
        $sql = "
            SELECT $grp AS periodo,
                   COUNT(*) AS viajes,
                   SUM(COALESCE(co2_kg,0)) AS co2_kg,
                   SUM(COALESCE(distancia_km,0)) AS km
            FROM (
                SELECT t.*, t.start_at AS trunc_dt
                FROM trips t
                WHERE t.start_at BETWEEN :f AND :t
            ) x
            GROUP BY periodo
            ORDER BY MIN(trunc_dt)
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':f'=>$fromDt->format('Y-m-d H:i:s'), ':t'=>$toDt->format('Y-m-d H:i:s')]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return ['columns'=>['Periodo','Viajes','CO₂ (kg)','Km'], 'rows'=>$rows];

    } elseif ($section === 'usuarios') {
        $sql = "
            SELECT id, name, apellido, dpi, email, telefono, fecha_nacimiento, foto, role, created_at
            FROM users
            ORDER BY created_at DESC
            LIMIT 1000
        ";
        return tableQuery($pdo, $sql);

    } elseif ($section === 'bikes') {
        $sql = "
            SELECT b.id, b.codigo, b.tipo, b.estado, s.nombre AS estacion, b.created_at
            FROM bikes b
            LEFT JOIN stations s ON s.id=b.station_id
            ORDER BY b.id ASC
            LIMIT 2000
        ";
        return tableQuery($pdo, $sql);

    } elseif ($section === 'estaciones') {
        $sql = "
            SELECT id, nombre, tipo, lat, lng, capacidad, created_at
            FROM stations
            ORDER BY id ASC
            LIMIT 2000
        ";
        return tableQuery($pdo, $sql);
    }

    return ['columns'=>[], 'rows'=>[]];
}

// ===== Obtener datos =====
$data = fetch_report($pdo, $section, $group, $from, $to);

// =============== Exportar ===============

// CSV (Excel)
if ($export === 'excel') {
    $filename = "ecobici_{$section}_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $out = fopen('php://output', 'w');
    // BOM UTF-8 para Excel
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, $data['columns']);
    foreach ($data['rows'] as $r) {
        fputcsv($out, array_values($r));
    }
    fclose($out);
    exit;
}

// PDF
if ($export === 'pdf') {
    class PDF extends FPDF {
        function Header(){
            // Logo
            $logo = __DIR__ . '/styles/logo.png';
            if (file_exists($logo)) {
                $this->Image($logo, 10, 8, 18);
            }
            $this->SetFont('Arial','B',14);
            $this->Cell(0,6,utf8_decode('EcoBici Puerto Barrios - Reportes'),0,1,'R');
            $this->SetFont('Arial','',10);
            $this->Cell(0,5,utf8_decode('Generado: '.dtNow()),0,1,'R');
            $this->Ln(5);
            $this->SetDrawColor(40,167,69); // verde
            $this->SetLineWidth(0.6);
            $this->Line(10, 27, 200, 27);
            $this->Ln(5);
        }
        function Footer(){
            $this->SetY(-15);
            $this->SetFont('Arial','I',8);
            $this->Cell(0,10,utf8_decode('Página '.$this->PageNo().'/{nb}'),0,0,'C');
        }
        function FancyTable($title, $filters, $header, $data){
            // Título
            $this->SetFont('Arial','B',12);
            $this->SetFillColor(40,167,69);
            $this->SetTextColor(255);
            $this->Cell(0,8,utf8_decode($title),0,1,'L',true);
            $this->Ln(2);
            // Filtros
            $this->SetTextColor(0);
            $this->SetFont('Arial','',9);
            foreach ($filters as $k=>$v) {
                $this->Cell(0,5,utf8_decode("$k: $v"),0,1,'L');
            }
            $this->Ln(2);
            // Encabezado tabla
            $this->SetFont('Arial','B',9);
            $this->SetFillColor(230, 255, 237);
            $this->SetDrawColor(200,200,200);
            $w = [];
            $count = count($header);
            $totalW = 190; // ancho útil
            for ($i=0; $i<$count; $i++) $w[$i] = max(25, intval($totalW/$count));
            foreach ($header as $i=>$col) {
                $this->Cell($w[$i],7,utf8_decode($col),1,0,'C',true);
            }
            $this->Ln();
            // Filas
            $this->SetFont('Arial','',8.5);
            $fill=false;
            foreach ($data as $row) {
                $i=0;
                foreach ($row as $val) {
                    $text = is_null($val) ? '' : (string)$val;
                    $this->Cell($w[$i],6,utf8_decode($text),1,0,'L',$fill);
                    $i++;
                }
                $this->Ln();
                $fill = !$fill;
            }
        }
    }

    $titles = [
        'uso'       => 'Reporte de Uso de Bicicletas',
        'ingresos'  => 'Reporte de Ingresos',
        'co2'       => 'Reporte de CO₂ Reducido',
        'usuarios'  => 'Catálogo de Usuarios',
        'bikes'     => 'Catálogo de Bicicletas',
        'estaciones'=> 'Catálogo de Estaciones'
    ];
    $filters = [
        'Rango'      => "$from a $to",
        'Agrupación' => in_array($section, ['uso','ingresos','co2'], true) ? $group : 'N/A'
    ];

    $pdf = new PDF('P','mm','A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->FancyTable($titles[$section] ?? 'Reporte', $filters, $data['columns'], array_map('array_values', $data['rows']));
    $pdf->Output('I', "ecobici_{$section}_".date('Ymd_His').".pdf");
    exit;
}

?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reportes | EcoBici</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{ --verde:#28a745; --verde2:#20c997; }
body{ background:#f7fff9; }
.nav-eco .nav-link{ color:#198754; font-weight:600; }
.nav-eco .nav-link.active{ background:var(--verde); color:#fff; }
.card{ border:1px solid #e7f5ea; box-shadow:0 2px 8px rgba(0,0,0,0.04); }
.btn-eco{ background:var(--verde); color:#fff; border:none; }
.btn-eco:hover{ background:#218838; color:#fff; }
.badge-eco{ background:#e9fff0; color:#198754; }
.table thead th{ background:#eafff0; }
.line-title{ height:3px; background:var(--verde); opacity:.25; }
</style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 m-0">Reportes EcoBici</h1>
    <div>
      <a href="/ecobici/administrador/index.php" class="btn btn-outline-success">Volver</a>
    </div>
  </div>
  <div class="line-title mb-3"></div>

  <!-- Tabs -->
  <ul class="nav nav-pills nav-eco mb-3" role="tablist">
    <?php
      $tabs = [
        'uso'=>'Uso de bicicletas',
        'ingresos'=>'Ingresos',
        'co2'=>'CO₂ reducido',
        'usuarios'=>'Catálogo usuarios',
        'bikes'=>'Catálogo bicicletas',
        'estaciones'=>'Catálogo estaciones'
      ];
      foreach ($tabs as $key=>$label):
    ?>
      <li class="nav-item"><a class="nav-link <?= $section===$key?'active':'' ?>"
        href="?section=<?= e($key) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&group=<?= e($group) ?>"><?= e($label) ?></a></li>
    <?php endforeach; ?>
  </ul>

  <!-- Filtros -->
  <form class="card p-3 mb-3" method="get">
    <input type="hidden" name="section" value="<?= e($section) ?>">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-3">
        <label class="form-label">Desde</label>
        <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Hasta</label>
        <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
      </div>
      <?php if (in_array($section, ['uso','ingresos','co2'], true)): ?>
      <div class="col-12 col-md-3">
        <label class="form-label">Agrupar por</label>
        <select name="group" class="form-select">
          <option value="day"   <?= $group==='day'?'selected':'' ?>>Día</option>
          <option value="week"  <?= $group==='week'?'selected':'' ?>>Semana</option>
          <option value="month" <?= $group==='month'?'selected':'' ?>>Mes</option>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button class="btn btn-eco w-100" type="submit">Aplicar</button>
      </div>
    </div>
  </form>

  <!-- Acciones de exportación -->
  <div class="d-flex gap-2 mb-3">
    <a class="btn btn-outline-success"
       href="?section=<?= e($section) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&group=<?= e($group) ?>&export=pdf">
       Exportar PDF
    </a>
    <a class="btn btn-outline-success"
       href="?section=<?= e($section) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&group=<?= e($group) ?>&export=excel">
       Exportar Excel
    </a>
  </div>

  <!-- Contenido -->
  <div class="card p-3">
    <div class="d-flex justify-content-between align-items-center">
      <h2 class="h5 m-0">
        <?php
          $titles = [
            'uso'=>'Uso de bicicletas', 'ingresos'=>'Ingresos',
            'co2'=>'CO₂ reducido', 'usuarios'=>'Catálogo de usuarios',
            'bikes'=>'Catálogo de bicicletas', 'estaciones'=>'Catálogo de estaciones'
          ];
          echo e($titles[$section] ?? 'Reporte');
        ?>
      </h2>
      <span class="badge rounded-pill badge-eco"><?= count($data['rows']) ?> filas</span>
    </div>
    <hr>

    <?php if (in_array($section, ['uso','ingresos','co2'], true)): ?>
      <div class="mb-3">
        <canvas id="chart"></canvas>
      </div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead>
          <tr>
            <?php foreach ($data['columns'] as $c): ?>
              <th><?= e($c) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php if (!$data['rows']): ?>
          <tr><td colspan="<?= count($data['columns']) ?>" class="text-center text-muted py-4">Sin datos en el rango seleccionado.</td></tr>
        <?php else: ?>
          <?php foreach ($data['rows'] as $r): ?>
            <tr>
              <?php foreach ($r as $v): ?>
                <td><?= e((string)$v) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="text-muted small mt-3">* Los CSV se abren en Excel. El PDF usa fuentes core (Arial) y muestra logo, fecha y filtros.</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const section = "<?= e($section) ?>";
  <?php if (in_array($section, ['uso','ingresos','co2'], true) && !empty($data['rows'])): ?>
    const rows = <?= json_encode(array_values($data['rows'])) ?>;
    let labels = rows.map(r => r.periodo);
    let datasetLabel = '';
    let dataMain = [];

    if (section === 'uso') { datasetLabel = 'Viajes'; dataMain = rows.map(r => Number(r.viajes)); }
    if (section === 'ingresos') { datasetLabel = 'Ingresos (Q)'; dataMain = rows.map(r => Number(r.ingresos)); }
    if (section === 'co2') { datasetLabel = 'CO₂ (kg)'; dataMain = rows.map(r => Number(r.co2_kg)); }

    const ctx = document.getElementById('chart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: { labels, datasets: [{ label: datasetLabel, data: dataMain, tension: .25 }]},
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true } }
      }
    });
  <?php endif; ?>
})();
</script>
</body>
</html>
