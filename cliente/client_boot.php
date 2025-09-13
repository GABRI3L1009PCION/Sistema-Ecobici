<?php
// /ecobici/cliente/client_boot.php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || (($_SESSION['user']['role'] ?? null) !== 'cliente')) {
    header('Location: /ecobici/login.php');
    exit;
}
if (!isset($pdo)) {
    die('Error: $pdo no está definido.');
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Helpers
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

// Flash
function flash(?string $msg = null, string $type = 'success'): ?array
{
    if ($msg === null) {
        $f = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return $f;
    }
    $_SESSION['_flash'] = ['msg' => $msg, 'type' => $type];
    return null;
}
function client_flash(?array $f): void
{
    if (!$f) return;
    $type = preg_replace('/[^a-z]/i', '', $f['type'] ?? 'success') ?: 'success';
    echo '<div class="alert alert-', $type, ' alert-dismissible fade show" role="alert">',
    e($f['msg'] ?? ''), '<button class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// CSRF
if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
function csrf(): string
{
    return $_SESSION['_csrf'] ?? '';
}
function csrf_check(?string $t): bool
{
    return hash_equals($_SESSION['_csrf'] ?? '', $t ?? '');
}
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">';
}

// Navbar cliente
function client_nav(string $active = 'dash'): void
{
    $items = [
        ['key' => 'dash', 'href' => '/ecobici/cliente/dashboard.php', 'icon' => 'bi-speedometer2', 'text' => 'Dashboard'],
        ['key' => 'planes', 'href' => '/ecobici/cliente/planes.php', 'icon' => 'bi-badge-ad', 'text' => 'Planes'],
        ['key' => 'pagos', 'href' => '/ecobici/cliente/pagos.php', 'icon' => 'bi-cash-coin', 'text' => 'Pagos'],
        ['key' => 'viajes', 'href' => '/ecobici/cliente/viajes.php', 'icon' => 'bi-geo-alt', 'text' => 'Viajes'],
        ['key' => 'perfil', 'href' => '/ecobici/cliente/perfil.php', 'icon' => 'bi-person', 'text' => 'Perfil'],
    ];
    echo <<<CSS
<style>
  .clientbar{border-bottom:1px solid #e2e8f0}
  .clientbar .nav-link{color:#198754}
  .clientbar .nav-link:hover{color:#0a6f3c}
  .clientbar .nav-link.active{background:#16a34a;color:#fff!important;border-radius:999px}
</style>
CSS;
    echo '<nav class="navbar navbar-expand-lg bg-white clientbar"><div class="container-fluid">',
    '<a class="navbar-brand d-flex align-items-center gap-2" href="/ecobici/cliente/dashboard.php">',
    '<i class="bi bi-bicycle text-success"></i> EcoBici</a>',
    '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#clientNav">',
    '<span class="navbar-toggler-icon"></span></button>',
    '<div id="clientNav" class="collapse navbar-collapse"><ul class="navbar-nav ms-auto gap-lg-1">';
    foreach ($items as $it) {
        $is = $active === $it['key'] ? ' active' : '';
        echo '<li class="nav-item"><a class="nav-link px-3 py-2', $is, '" href="', e($it['href']), '">',
        '<i class="bi ', e($it['icon']), ' me-1"></i>', e($it['text']), '</a></li>';
    }
    echo '</ul><div class="d-flex gap-2 ms-lg-3 mt-3 mt-lg-0">',
    '<a class="btn btn-outline-success" href="/ecobici/index.php"><i class="bi bi-house-door me-1"></i>Pública</a>',
    '<a class="btn btn-outline-danger" href="/ecobici/logout.php"><i class="bi bi-box-arrow-right me-1"></i>Salir</a>',
    '</div></div></div></nav>';
}
