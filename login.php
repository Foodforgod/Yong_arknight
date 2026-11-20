<?php
require_once 'db.php';
session_start();

$error = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

if (isset($_GET['guest'])) {
    $_SESSION['username'] = 'Guest';
    $_SESSION['role'] = 'guest';
    $_SESSION['is_admin'] = false;
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && ($password === $user['password'] || password_verify($password, $user['password']))) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['is_admin'] = ($user['role'] === 'admin');
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rhodes Island Archives - Login</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #0B131D; color: #E2E8F0; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { width: 100%; max-width: 400px; background: #162231; border: 2px solid #00B4D8; border-radius: 8px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.8); }
        h2 { color: #E59811; margin-top: 0; text-align: center; text-transform: uppercase; font-size: 20px; letter-spacing: 1px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; color: #00B4D8; font-size: 12px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; }
        .form-group input { width: 100%; padding: 12px; background: #0B131D; border: 1px solid #2A3B52; color: #fff; border-radius: 4px; box-sizing: border-box; }
        .btn-submit { width: 100%; padding: 12px; background: #E59811; color: #000; font-weight: bold; border: none; border-radius: 4px; cursor: pointer; text-transform: uppercase; font-size: 14px; margin-top: 5px; }
        .btn-submit:hover { background: #fff; }
        .btn-guest { width: 100%; padding: 12px; background: #1E293B; color: #00B4D8; font-weight: bold; border: 1px solid #00B4D8; border-radius: 4px; cursor: pointer; text-transform: uppercase; font-size: 14px; margin-top: 10px; text-decoration: none; display: block; text-align: center; box-sizing: border-box; }
        .btn-guest:hover { background: #00B4D8; color: #000; }
        .error-msg { background: rgba(239, 68, 68, 0.2); border: 1px solid #EF4444; color: #EF4444; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; font-size: 13px; font-weight: bold; }
        .credentials-hint { background: #0B131D; border: 1px dashed #2A3B52; padding: 10px; border-radius: 4px; font-size: 11px; color: #94A3B8; margin-top: 20px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>🛡️ Rhodes Island Login</h2>
        <p style="text-align: center; color: #94A3B8; font-size: 12px; margin-bottom: 20px;">Access Operator Archives & Tactical Database</p>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="admin or doctor" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-submit">Sign In (Admin / Normal User)</button>
        </form>

        <a href="login.php?guest=1" class="btn-guest">Continue as Guest 👁️</a>

        <div class="credentials-hint">
            <strong>Test Accounts:</strong><br>
            • Admin: <code>admin</code> / <code>password123</code><br>
            • Normal User: <code>doctor</code> / <code>password123</code><br>
            • Guest: View Dossier, Video Wiki & AI Talking only
        </div>
    </div>
</body>
</html>