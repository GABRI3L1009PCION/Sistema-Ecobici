<?php
// /ecobici/administrador/admin_boot.php
declare(strict_types=1);

// ---- Sesión y conexión ----
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// ---- Guardas de acceso ----
if (!isset($_SESSION['user']) || (($_SESSION['user']['role'] ?? null) !== 'admin')) {
    header('Location: /ecobici/login.php');
    exit;
}
if (!isset($pdo) || !$pdo instanceof PDO) {
    die('Error: $pdo no está definido. Revisa config/db.php');
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ================= Helpers básicos =================
if (!function_exists('e')) {
    function e($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }
}

// ---------------- Flash messages -------------------
if (!function_exists('flash')) {
    /**
     * flash() -> obtiene y limpia el flash activo
     * flash($msg, $type) -> setea un flash
     */
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
}
if (!function_exists('admin_flash')) {
    /** Imprime un alert Bootstrap a partir de flash() */
    function admin_flash(?array $f): void
    {
        if (!$f) return;
        $type = preg_replace('/[^a-z]/i', '', $f['type'] ?? 'success') ?: 'success';
        echo '<div class="alert alert-', e($type), ' alert-dismissible fade show" role="alert">',
        e($f['msg'] ?? ''),
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>',
        '</div>';
    }
}

// ---------------- CSRF minimal ---------------------
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(16));
}
if (!function_exists('csrf')) {
    function csrf(): string
    {
        return $_SESSION['_csrf'] ?? '';
    }
}
if (!function_exists('csrf_check')) {
    function csrf_check(?string $token): bool
    {
        return hash_equals($_SESSION['_csrf'] ?? '', $token ?? '');
    }
}
if (!function_exists('csrf_field')) {
    /** Campo oculto listo para forms */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">';
    }
}

// ================= Navbar Admin ====================
if (!function_exists('admin_nav')) {
    /**
     * Navbar Bootstrap del administrador
     * @param string $active  'dash'|'planes'|'users'|'subs'|'pagos'|'rep_co2'|'ajustes'
     */
    function admin_nav(string $active = 'dash'): void
    {
        $items = [
            ['key' => 'dash',   'href' => '/ecobici/administrador/dashboard.php',     'icon' => 'bi-speedometer2', 'text' => 'Dashboard'],
            ['key' => 'planes', 'href' => '/ecobici/administrador/planes.php',        'icon' => 'bi-badge-ad',     'text' => 'Planes'],
            ['key' => 'users',  'href' => '/ecobici/administrador/usuarios.php',      'icon' => 'bi-people',       'text' => 'Usuarios'],
            ['key' => 'subs',   'href' => '/ecobici/administrador/suscripciones.php', 'icon' => 'bi-diagram-3',    'text' => 'Suscripciones'],
            ['key' => 'pagos',  'href' => '/ecobici/administrador/pagos.php',         'icon' => 'bi-cash-coin',    'text' => 'Pagos'],
            // ['key'=>'rep_co2','href'=>'/ecobici/administrador/reportes_co2.php','icon'=>'bi-cloud-check','text'=>'CO₂'],
            // ['key'=>'ajustes','href'=>'/ecobici/administrador/ajustes.php',     'icon'=>'bi-gear',         'text'=>'Ajustes'],
        ];

        // Estilos suaves verde/blanco + ajuste de logo
        echo <<<CSS
<style>
  .adminbar{border-bottom:1px solid #e2e8f0}
  .adminbar .navbar-brand{font-weight:700;letter-spacing:.2px}
  .adminbar .navbar-brand img{height:38px}
  .adminbar .nav-link{color:#198754}
  .adminbar .nav-link:hover{color:#0a6f3c}
  .adminbar .nav-link.active{background:#16a34a;color:#fff!important;border-radius:999px;box-shadow:0 0 0 .12rem rgba(22,163,74,.18)}
  @media (max-width: 991.98px){ .adminbar .nav-link{padding:.55rem 1rem;margin:.25rem 0} }
</style>
CSS;

        // Navbar
        echo '<nav class="navbar navbar-expand-lg bg-white adminbar sticky-top">',
             '<div class="container-fluid">',

             // ====== AQUÍ VA EL LOGO ======
             '<a class="navbar-brand d-flex align-items-center gap-2" href="/ecobici/administrador/dashboard.php">',
             '<img src="/ecobici/cliente/styles/logo.jpg" alt="EcoBici">',
             '<span class="visually-hidden">EcoBici Admin</span>',
             '</a>',

             '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Menú">',
             '<span class="navbar-toggler-icon"></span></button>',

             '<div class="collapse navbar-collapse" id="adminNav">',
             '<ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">';
        foreach ($items as $it) {
            $is = ($active === $it['key']) ? ' active' : '';
            echo '<li class="nav-item">',
                 '<a class="nav-link px-3 py-2', $is, '" href="', e($it['href']), '">',
                 '<i class="bi ', e($it['icon']), ' me-1"></i>', e($it['text']),
                 '</a>',
                 '</li>';
        }
        echo    '</ul>',
                '<div class="d-flex gap-2 ms-lg-3 mt-3 mt-lg-0">',
                '<a class="btn btn-outline-success" href="/ecobici/index.php"><i class="bi bi-house-door me-1"></i>Pública</a>',
                '<a class="btn btn-outline-danger" href="/ecobici/logout.php"><i class="bi bi-box-arrow-right me-1"></i>Salir</a>',
                '</div>',
                '</div>',
                '</div>',
                '</nav>';
    }
}
