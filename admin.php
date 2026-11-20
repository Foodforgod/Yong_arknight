<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Strict Admin Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die("<div style='background:#0B131D; color:#EF4444; text-align:center; padding:60px; font-family:sans-serif;'>
        <h2>⛔ ACCESS DENIED: ADMINISTRATOR PRIVILEGES REQUIRED</h2>
        <p style='color:#94A3B8;'>Guests and Normal Users cannot access the admin portal.</p>
        <a href='index.php' style='color:#00B4D8; font-weight:bold;'>⬅ Return to User Index</a>
    </div>");
}

$operators = $pdo->query("SELECT id, name, class, rarity, image_path FROM operators ORDER BY rarity DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - Rhodes Island Archives</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #0B131D; color: #E2E8F0; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; }
        .main-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; background: #111B27; border-bottom: 2px solid #E59811; }
        .logo { color: #E59811; font-weight: bold; font-size: 18px; letter-spacing: 1px; }
        .admin-container { max-width: 1060px; margin: 40px auto; background: #162231; border: 1px solid #2A3B52; border-radius: 8px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.8); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #2A3B52; font-size: 14px; vertical-align: middle; }
        th { color: #E59811; background: #0B131D; text-transform: uppercase; letter-spacing: 1px; }
        .action-group { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .btn-edit { background: #E59811; color: #000; padding: 6px 12px; font-weight: bold; border-radius: 4px; text-decoration: none; font-size: 11px; transition: 0.2s; }
        .btn-edit:hover { background: #fff; }
        .btn-talk { background: #00B4D8; color: #070B10; padding: 6px 11px; font-weight: bold; border-radius: 4px; text-decoration: none; font-size: 11px; }
        .btn-dismiss { background: rgba(239, 68, 68, 0.16); color: #FCA5A5; border: 1px solid #EF4444; padding: 5px 10px; font-weight: bold; border-radius: 4px; font-size: 11px; cursor: pointer; }
        .btn-dismiss:hover { background: #EF4444; color: #fff; }
        .btn-back { color: #00B4D8; text-decoration: none; font-weight: bold; font-size: 13px; margin-left: 12px; }
        .btn-back:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="logo">⚙️ ADMIN CONTROL PANEL / 管理员控制台</div>
        <div>
            <a href="add.php" class="btn-edit">+ RECRUIT OPERATOR</a>
            <a href="index.php" class="btn-back">⬅ EXIT TO USER INDEX</a>
        </div>
    </header>

    <div class="admin-container">
        <h2 style="color: #E59811; margin-top: 0; font-size: 20px;">Operator Record Management</h2>
        <p style="color: #94A3B8; font-size: 13px; margin-bottom: 20px;">Select an operator record below to modify tactical attributes, background lore, custom wallpapers, stage animations, voice lines, or dismiss records.</p>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Operator Name</th>
                    <th>Class</th>
                    <th>Rarity</th>
                    <th>Management Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($operators)): ?>
                    <tr><td colspan="5" style="text-align: center; color: #64748B;">No operator records found in database.</td></tr>
                <?php else: ?>
                    <?php foreach ($operators as $op): ?>
                        <tr>
                            <td><?= (int)$op['id'] ?></td>
                            <td><strong><?= htmlspecialchars($op['name']) ?></strong></td>
                            <td><?= strtoupper(htmlspecialchars($op['class'])) ?></td>
                            <td style="color: #E59811;"><?= str_repeat('★', max(1, min(6, (int)$op['rarity']))) ?></td>
                            <td>
                                <div class="action-group">
                                    <a href="admin_edit.php?id=<?= (int)$op['id'] ?>" class="btn-edit">EDIT RECORD</a>
                                    <a href="ai_talking.php?id=<?= (int)$op['id'] ?>" class="btn-talk">💬 AI TALK</a>
                                    <form method="POST" action="delete.php" style="margin:0;" onsubmit="return confirm('Dismiss <?= htmlspecialchars(addslashes($op['name'])) ?> from the archive?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="id" value="<?= (int)$op['id'] ?>">
                                        <button type="submit" class="btn-dismiss">DISMISS</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>