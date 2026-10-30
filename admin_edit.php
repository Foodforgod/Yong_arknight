<?php
require_once 'db.php';
session_start();

$id =$_GET['id'] ?? 0;

// 处理表单提交保存
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $class     = trim($_POST['class'] ?? '');
    $rarity    = intval($_POST['rarity'] ?? 1);
    $hp        = intval($_POST['hp'] ?? 0);
    $atk       = intval($_POST['atk'] ?? 0);
    $def       = intval($_POST['def'] ?? 0);
    $lore      = trim($_POST['lore'] ?? '');
    $image_path= trim($_POST['image_path'] ?? '');
    $image_e2  = trim($_POST['image_path_e2'] ?? '');
    $wallpaper = trim($_POST['wallpaper'] ?? '');

    $update_stmt =$pdo->prepare("UPDATE operators SET name = ?, class = ?, rarity = ?, hp = ?, atk = ?, def = ?, lore = ?, image_path = ?, image_path_e2 = ?, wallpaper = ? WHERE id = ?");
    $update_stmt->execute([$name, $class,$rarity, $hp,$atk, $def,$lore, $image_path,$image_e2, $wallpaper,$id]);

    header("Location: admin_edit.php?id=" . $id . "&success=1");
    exit;
}

$stmt =$pdo->prepare("SELECT * FROM operators WHERE id = ?");
$stmt->execute([$id]);
$op =$stmt->fetch();

if (!$op) {
    die("<div style='color: white; text-align: center; margin-top: 50px;'><h2>Error: Operator not found.</h2><a href='index.php' style='color: #ffb400;'>Return to Gallery</a></div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Operator Lore & Wallpaper - <?php echo htmlspecialchars($op['name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #0b0f12;
            color: #ffffff;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .edit-container {
            max-width: 700px;
            margin: 30px auto;
            background: rgba(15, 17, 18, 0.95);
            border: 2px solid #00bcd4;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.9);
        }
        h2 {
            color: #ffb400;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            color: #00bcd4;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-size: 13px;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            background: #111;
            border: 1px solid #444;
            color: #fff;
            font-size: 14px;
            border-radius: 6px;
            outline: none;
            box-sizing: border-box;
        }
        .form-group textarea {
            height: 110px;
            resize: vertical;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .btn-submit {
            background: #ffb400;
            color: #000;
            font-weight: bold;
            padding: 14px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 15px;
            width: 100%;
            margin-top: 10px;
            transition: 0.2s;
        }
        .btn-submit:hover {
            background: #fff;
        }
        .nav-links {
            text-align: center;
            margin-top: 20px;
        }
        .nav-links a {
            color: #00bcd4;
            text-decoration: none;
            margin: 0 10px;
            font-weight: bold;
        }
        .nav-links a:hover {
            text-decoration: underline;
        }
        .success-msg {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid #4caf50;
            color: #4caf50;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="edit-container">
        <h2>⚙️ Edit Operator: <?php echo htmlspecialchars($op['name']); ?></h2>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-msg">Changes saved successfully!</div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Operator Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($op['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Class</label>
                    <input type="text" name="class" value="<?php echo htmlspecialchars($op['class']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Rarity (Stars)</label>
                    <input type="number" name="rarity" min="1" max="6" value="<?php echo intval($op['rarity']); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>HP</label>
                    <input type="number" name="hp" value="<?php echo intval($op['hp']); ?>">
                </div>
                <div class="form-group">
                    <label>ATK</label>
                    <input type="number" name="atk" value="<?php echo intval($op['atk']); ?>">
                </div>
                <div class="form-group">
                    <label>DEF</label>
                    <input type="number" name="def" value="<?php echo intval($op['def']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Background Lore / Disposition (AI Persona)</label>
                <textarea name="lore"><?php echo htmlspecialchars($op['lore'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Portrait Image Path (Standard / E1)</label>
                <input type="text" name="image_path" value="<?php echo htmlspecialchars($op['image_path']); ?>">
            </div>

            <div class="form-group">
                <label>Elite 2 Portrait Image Path</label>
                <input type="text" name="image_path_e2" value="<?php echo htmlspecialchars($op['image_path_e2'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Galgame Custom Wallpaper Path / URL</label>
                <input type="text" name="wallpaper" value="<?php echo htmlspecialchars($op['wallpaper'] ?? ''); ?>" placeholder="e.g., uploads/bg.jpg or https://images.unsplash.com/...">
            </div>

            <button type="submit" class="btn-submit">💾 Save Changes</button>
        </form>

        <div class="nav-links">
            <a href="galgame.php?id=<?php echo $id; ?>">🎮 Return to Galgame Mode</a> | 
            <a href="bag.php">📦 Return to Storage Bag</a> | 
            <a href="index.php">⬅ Gallery Index</a>
        </div>
    </div>

</body>
</html>