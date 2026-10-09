<?php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use LoganX\Security;
use LoganX\Config;

Security::startSession();

// Check native session first; fallback to signed stateless auth cookie if session was lost in serverless container
if (empty($_SESSION['admin_user_id']) || empty($_SESSION['admin_logged_in'])) {
    $cookieToken = $_COOKIE['lx_admin_auth'] ?? null;
    $validData = Security::verifyAdminAuthToken($cookieToken);

    if ($validData) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user_id']   = (int)$validData['uid'];
        $_SESSION['admin_username']  = (string)$validData['user'];
        $_SESSION['admin_role']      = (string)($validData['role'] ?? 'admin');
    } else {
        $redirectUrl = Config::appUrl() . '/admin/login.php';
        header("Location: {$redirectUrl}");
        exit;
    }
}

$adminUsername = $_SESSION['admin_username'] ?? 'Admin';
$adminRole     = $_SESSION['admin_role'] ?? 'admin';
