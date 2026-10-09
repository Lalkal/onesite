<?php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use LoganX\Security;
use LoganX\Config;

Security::startSession();

if (empty($_SESSION['admin_user_id']) || empty($_SESSION['admin_logged_in'])) {
    $redirectUrl = Config::appUrl() . '/admin/login.php';
    header("Location: {$redirectUrl}");
    exit;
}

$adminUsername = $_SESSION['admin_username'] ?? 'Admin';
$adminRole     = $_SESSION['admin_role'] ?? 'admin';
