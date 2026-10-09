<?php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use LoganX\Database;
use LoganX\Security;
use LoganX\RateLimiter;
use LoganX\Config;

Security::startSession();

// If already logged in, redirect to admin home
if (!empty($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$error = null;
$clientIp = Security::getClientIp();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrfToken($csrf)) {
        $error = 'Session security token expired. Please reload and try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        // Rate limit admin login attempts by IP
        $rateCheck = RateLimiter::check("admin_login:{$clientIp}", 'admin_login');
        if (!$rateCheck['allowed']) {
            $error = $rateCheck['reason'];
        } elseif ($username === '' || $password === '') {
            $error = 'Please enter both username and password.';
        } else {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Success: regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id']   = (int)$user['id'];
                $_SESSION['admin_username']  = $user['username'];
                $_SESSION['admin_role']      = $user['role'] ?? 'admin';

                $pdo->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = :id")->execute([':id' => $user['id']]);
                RateLimiter::clear("admin_login:{$clientIp}", 'admin_login');

                header("Location: index.php");
                exit;
            } else {
                $error = 'Invalid admin credentials.';
            }
        }
    }
}

$csrfToken = Security::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — LOGANX Downloads</title>
  <style>
    :root {
      --bg: #080a0f;
      --panel: #11151f;
      --text: #f6f7fb;
      --muted: #9da5b5;
      --line: rgba(255,255,255,.09);
      --accent: #8cff5b;
      --danger: #ff5876;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: Inter, ui-sans-serif, system-ui, sans-serif;
      background: radial-gradient(circle at 50% 10%, rgba(140,255,91,.08), transparent 30%), var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 20px;
    }
    .login-card {
      width: 100%;
      max-width: 420px;
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 20px;
      padding: 38px 32px;
      box-shadow: 0 30px 80px rgba(0,0,0,.6);
    }
    .logo {
      font-size: 26px;
      font-weight: 900;
      letter-spacing: -1px;
      text-align: center;
      margin-bottom: 8px;
    }
    .logo span { color: var(--accent); }
    .subtitle {
      text-align: center;
      color: var(--muted);
      font-size: 13px;
      margin-bottom: 28px;
    }
    .alert-error {
      background: rgba(255,88,118,.12);
      border: 1px solid rgba(255,88,118,.3);
      color: #ff859d;
      padding: 12px 14px;
      border-radius: 12px;
      font-size: 13px;
      margin-bottom: 20px;
    }
    .form-group {
      margin-bottom: 18px;
    }
    label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .8px;
      color: var(--muted);
      margin-bottom: 8px;
    }
    input {
      width: 100%;
      padding: 14px 16px;
      background: #090c12;
      border: 1px solid var(--line);
      border-radius: 12px;
      color: #fff;
      font-size: 14px;
      outline: none;
      transition: border-color .2s;
    }
    input:focus { border-color: var(--accent); }
    .btn {
      width: 100%;
      padding: 15px;
      background: var(--accent);
      color: #071006;
      border: 0;
      border-radius: 12px;
      font-weight: 800;
      font-size: 15px;
      cursor: pointer;
      margin-top: 10px;
      transition: transform .2s, box-shadow .2s;
    }
    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(140,255,91,.25);
    }
    .back {
      display: block;
      text-align: center;
      margin-top: 22px;
      color: var(--muted);
      font-size: 13px;
      text-decoration: none;
    }
    .back:hover { color: #fff; }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="logo">LOGAN<span>X</span></div>
    <div class="subtitle">Admin Management Portal</div>

    <?php if ($error): ?>
      <div class="alert-error"><?= Security::e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken) ?>">

      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus placeholder="Admin username">
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required placeholder="••••••••••••">
      </div>

      <button type="submit" class="btn">Sign In to Dashboard &rarr;</button>
    </form>

    <a class="back" href="../">← Back to LOGANX Website</a>
  </div>
</body>
</html>
