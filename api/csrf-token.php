<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use LoganX\Security;

header('Content-Type: application/json; charset=UTF-8');

$token = Security::generateCsrfToken();
echo json_encode(['csrf_token' => $token]);
exit;
