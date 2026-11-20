<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce admin role if session role is set; allow if unauthenticated session isn't blocking legacy links
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    die("<div style='background:#0B131D; color:#EF4444; text-align:center; padding:60px; font-family:sans-serif;'>
        <h2>⛔ ACCESS DENIED: ADMINISTRATOR PRIVILEGES REQUIRED</h2>
        <p style='color:#94A3B8;'>Only administrators can modify operator records.</p>
        <a href='index.php' style='color:#00B4D8; font-weight:bold;'>⬅ Return to Index</a>
    </div>");
}

// Safely ensure optional media/animation columns exist
try {
    $howtoColStmt = $pdo->query("SHOW COLUMNS FROM operators LIKE 'howto_video_url'");
    if ($howtoColStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE operators ADD COLUMN howto_video_url VARCHAR(500) NULL DEFAULT NULL AFTER video_url");
    }
    $deployColStmt = $pdo->query("SHOW COLUMNS FROM operators LIKE 'deploy_video'");
    if ($deployColStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE operators ADD COLUMN deploy_video VARCHAR(500) NULL DEFAULT NULL AFTER stage_video");
    }
    $attackColStmt = $pdo->query("SHOW COLUMNS FROM operators LIKE 'attack_video'");
    if ($attackColStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE operators ADD COLUMN attack_video VARCHAR(500) NULL DEFAULT NULL AFTER deploy_video");
    }
} catch (Throwable $e) {
    // Ignore if columns already exist or insufficient permissions
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die("Error: Missing Operator ID.");
}

$stmt = $pdo->prepare("SELECT * FROM operators WHERE id = ?");
$stmt->execute([$id]);
$op = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$op) {
    die("Error: Operator not found.");
}

$statusMessage = '';
$statusType = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name   = trim($_POST['name'] ?? '');
    $rarity = (int)($_POST['rarity'] ?? 1);
    $class  = trim($_POST['class'] ?? '');
    $lore   = trim($_POST['lore'] ?? '');

    $img_url = trim($_POST['image_path'] ?? '');
    $hp      = (int)($_POST['hp'] ?? 0);
    $atk     = (int)($_POST['atk'] ?? 0);
    $def     = (int)($_POST['def'] ?? 0);
    $block   = (int)($_POST['block_count'] ?? 1);
    $cost    = (int)($_POST['deploy_cost'] ?? 0);

    $img_url_e2 = trim($_POST['image_path_e2'] ?? '');
    $hp_e2      = !empty($_POST['hp_e2']) ? (int)$_POST['hp_e2'] : 0;
    $atk_e2     = !empty($_POST['atk_e2']) ? (int)$_POST['atk_e2'] : 0;
    $def_e2     = !empty($_POST['def_e2']) ? (int)$_POST['def_e2'] : 0;
    $block_e2   = !empty($_POST['block_count_e2']) ? (int)$_POST['block_count_e2'] : 0;
    $cost_e2    = !empty($_POST['deploy_cost_e2']) ? (int)$_POST['deploy_cost_e2'] : 0;

    $wallpaper       = trim($_POST['wallpaper'] ?? '');
    $video_url       = trim($_POST['video_url'] ?? '');
    $howto_video_url = trim($_POST['howto_video_url'] ?? '');
    $stage_video     = trim($_POST['stage_video'] ?? '');
    $deploy_video    = trim($_POST['deploy_video'] ?? '');
    $attack_video    = trim($_POST['attack_video'] ?? '');

    $s1_name = trim($_POST['s1_name'] ?? '');
    $s1_img  = trim($_POST['s1_img'] ?? '');
    $s1_desc = trim($_POST['s1_desc'] ?? '');

    $s2_name = trim($_POST['s2_name'] ?? '');
    $s2_img  = trim($_POST['s2_img'] ?? '');
    $s2_desc = trim($_POST['s2_desc'] ?? '');

    $s3_name = trim($_POST['s3_name'] ?? '');
    $s3_img  = trim($_POST['s3_img'] ?? '');
    $s3_desc = trim($_POST['s3_desc'] ?? '');

    $sql = "UPDATE operators SET
                name=?, rarity=?, class=?, lore=?,
                image_path=?, hp=?, atk=?, def=?, block_count=?, deploy_cost=?,
                image_path_e2=?, hp_e2=?, atk_e2=?, def_e2=?, block_count_e2=?, deploy_cost_e2=?,
                wallpaper=?, video_url=?, howto_video_url=?, stage_video=?, deploy_video=?, attack_video=?,
                s1_name=?, s1_img=?, s1_desc=?,
                s2_name=?, s2_img=?, s2_desc=?,
                s3_name=?, s3_img=?, s3_desc=?
            WHERE id=?";

    $updateStmt = $pdo->prepare($sql);
    $params = [
        $name, $rarity, $class, $lore,
        $img_url, $hp, $atk, $def, $block, $cost,
        $img_url_e2, $hp_e2, $atk_e2, $def_e2, $block_e2, $cost_e2,
        $wallpaper, $video_url, $howto_video_url, $stage_video, $deploy_video, $attack_video,
        $s1_name, $s1_img, $s1_desc,
        $s2_name, $s2_img, $s2_desc,
        $s3_name, $s3_img, $s3_desc,
        $id
    ];

    if ($updateStmt->execute($params)) {
        $statusType = 'success';
        $statusMessage = '[SUCCESS] Dossier updated. Redirecting to details...';
        $refreshStmt = $pdo->prepare("SELECT * FROM operators WHERE id = ?");
        $refreshStmt->execute([$id]);
        $op = $refreshStmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $statusType = 'error';
        $statusMessage = '[ERROR] Failed to update record.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Dossier: <?php echo htmlspecialchars($op['name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        * { box-sizing: border-box; }
        body.edit-page {
            background-color: #070B10 !important;
            color: #E2E8F0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            text-align: left;
            min-height: 100vh;
        }
        .edit-shell {
            width: 92%;
            max-width: 820px;
            margin: 28px auto 54px;
            background: rgba(13, 22, 34, 0.94);
            backdrop-filter: blur(12px);
            border: 1px solid #1E2E42;
            border-top: 3px solid #ffb400;
            border-radius: 12px;
            padding: 28px 30px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.8);
        }
        .edit-header-title {
            margin: 0 0 18px 0;
            padding-bottom: 12px;
            border-bottom: 1px solid #1E2E42;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 1.2px;
            color: #ffb400;
            text-transform: uppercase;
        }
        .form-section {
            background: rgba(7, 11, 16, 0.78);
            border: 1px solid #1E2E42;
            border-left: 3px solid #00bcd4;
            border-radius: 8px;
            padding: 16px 18px;
            margin-bottom: 18px;
        }
        .form-section.e2 {
            border-left-color: #ffb400;
        }
        .section-title {
            font-size: 11px;
            font-weight: 800;
            color: #38BDF8;
            margin-bottom: 12px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }
        .form-section.e2 .section-title {
            color: #ffb400;
        }
        .form-section h3 {
            margin: 0 0 12px 0;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.9px;
            color: #FFFFFF;
            text-transform: uppercase;
        }
        .field-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #94A3B8;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .edit-shell input,
        .edit-shell select,
        .edit-shell textarea {
            width: 100%;
            background: #0b131d;
            border: 1px solid #2A3B52;
            color: #F8FAFC;
            padding: 11px 12px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 13px;
            outline: none;
            font-family: inherit;
        }
        .edit-shell input:focus,
        .edit-shell select:focus,
        .edit-shell textarea:focus {
            border-color: #00bcd4;
        }
        .grid-inputs {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .skill-block {
            border-bottom: 1px solid #1E2E42;
            margin-bottom: 12px;
            padding-bottom: 10px;
        }
        .skill-block:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .skill-tag {
            color: #ffb400;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.8px;
            margin: 0 0 8px 0;
        }
        .btn-save {
            width: 100%;
            padding: 14px;
            background: #ffb400;
            color: #070B10;
            font-weight: 900;
            font-size: 14px;
            letter-spacing: 1px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            text-transform: uppercase;
            transition: background 0.16s ease, transform 0.16s ease;
        }
        .btn-save:hover {
            background: #ffc93c;
            transform: translateY(-1px);
        }
        .alert-banner {
            padding: 12px 16px;
            border-radius: 7px;
            margin-bottom: 18px;
            font-weight: 800;
            font-size: 13px;
            text-align: center;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.16);
            border: 1px solid #10B981;
            color: #6EE7B7;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.16);
            border: 1px solid #EF4444;
            color: #FCA5A5;
        }
        @media (max-width: 620px) {
            .grid-inputs { grid-template-columns: 1fr; }
            .edit-shell { padding: 20px 16px; }
        }
    </style>
</head>
<body class="edit-page">
    <header class="main-header">
        <div class="logo">OPERATOR DATA MODIFICATION</div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="ai_talking.php?id=<?php echo $id; ?>" class="btn-add" style="background: #00bcd4; color: #070B10;">💬 AI TALK</a>
            <a href="details.php?id=<?php echo $id; ?>" class="btn-add">⬅ CANCEL</a>
        </div>
    </header>

    <main class="edit-shell">
        <h1 class="edit-header-title">Edit Dossier — <?php echo htmlspecialchars($op['name']); ?></h1>

        <?php if ($statusMessage !== ''): ?>
            <div class="alert-banner <?php echo $statusType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($statusMessage); ?>
            </div>
            <?php if ($statusType === 'success'): ?>
                <script>
                    setTimeout(() => { window.location.href = 'details.php?id=<?php echo $id; ?>'; }, 1200);
                </script>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST">
            <div class="form-section">
                <h3>Core Identification</h3>
                <label class="field-label">Operator Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($op['name']); ?>" placeholder="Name" required>

                <div class="grid-inputs">
                    <div>
                        <label class="field-label">Rarity</label>
                        <select name="rarity">
                            <?php for ($i = 6; $i >= 1; $i--): ?>
                                <option value="<?php echo $i; ?>" <?php if ((int)$op['rarity'] === $i) echo 'selected'; ?>>
                                    <?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Class</label>
                        <select name="class">
                            <?php
                            $classes = ['Vanguard', 'Guard', 'Sniper', 'Defender', 'Medic', 'Caster', 'Supporter', 'Specialist'];
                            foreach ($classes as $c) {
                                $sel = (strcasecmp($op['class'] ?? '', $c) === 0) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . "' $sel>" . htmlspecialchars($c) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title">Base / Elite 1 Records</div>
                <label class="field-label">Base Image URL / Path</label>
                <input type="text" name="image_path" value="<?php echo htmlspecialchars($op['image_path'] ?? ''); ?>" placeholder="Base Image URL" required>

                <div class="grid-inputs">
                    <div>
                        <label class="field-label">HP</label>
                        <input type="number" name="hp" value="<?php echo (int)($op['hp'] ?? 0); ?>" placeholder="HP" required>
                    </div>
                    <div>
                        <label class="field-label">ATK</label>
                        <input type="number" name="atk" value="<?php echo (int)($op['atk'] ?? 0); ?>" placeholder="ATK" required>
                    </div>
                    <div>
                        <label class="field-label">DEF</label>
                        <input type="number" name="def" value="<?php echo (int)($op['def'] ?? 0); ?>" placeholder="DEF" required>
                    </div>
                    <div>
                        <label class="field-label">Block Count</label>
                        <input type="number" name="block_count" value="<?php echo (int)($op['block_count'] ?? 1); ?>" placeholder="Block" required>
                    </div>
                    <div>
                        <label class="field-label">Deploy Cost (DP)</label>
                        <input type="number" name="deploy_cost" value="<?php echo (int)($op['deploy_cost'] ?? 0); ?>" placeholder="Cost" required>
                    </div>
                </div>
            </div>

            <div class="form-section e2">
                <div class="section-title">Elite 2 Modification (Optional)</div>
                <label class="field-label">Elite 2 Image URL / Path</label>
                <input type="text" name="image_path_e2" value="<?php echo htmlspecialchars($op['image_path_e2'] ?? ''); ?>" placeholder="Elite 2 Image URL">

                <div class="grid-inputs">
                    <div>
                        <label class="field-label">E2 HP</label>
                        <input type="number" name="hp_e2" value="<?php echo (int)($op['hp_e2'] ?? 0); ?>" placeholder="E2 HP">
                    </div>
                    <div>
                        <label class="field-label">E2 ATK</label>
                        <input type="number" name="atk_e2" value="<?php echo (int)($op['atk_e2'] ?? 0); ?>" placeholder="E2 ATK">
                    </div>
                    <div>
                        <label class="field-label">E2 DEF</label>
                        <input type="number" name="def_e2" value="<?php echo (int)($op['def_e2'] ?? 0); ?>" placeholder="E2 DEF">
                    </div>
                    <div>
                        <label class="field-label">E2 Block Count</label>
                        <input type="number" name="block_count_e2" value="<?php echo (int)($op['block_count_e2'] ?? 0); ?>" placeholder="E2 Block">
                    </div>
                    <div>
                        <label class="field-label">E2 Deploy Cost (DP)</label>
                        <input type="number" name="deploy_cost_e2" value="<?php echo (int)($op['deploy_cost_e2'] ?? 0); ?>" placeholder="E2 Cost">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Skills Configuration</h3>
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <div class="skill-block">
                        <p class="skill-tag">SKILL 0<?php echo $i; ?></p>
                        <input type="text" name="s<?php echo $i; ?>_name" placeholder="Skill Name" value="<?php echo htmlspecialchars($op["s{$i}_name"] ?? ''); ?>">
                        <input type="text" name="s<?php echo $i; ?>_img" placeholder="Skill Icon URL" value="<?php echo htmlspecialchars($op["s{$i}_img"] ?? ''); ?>">
                        <textarea name="s<?php echo $i; ?>_desc" placeholder="Skill Description" style="height: 68px; margin-bottom: 0;"><?php echo htmlspecialchars($op["s{$i}_desc"] ?? ''); ?></textarea>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="form-section">
                <h3>Wallpaper & Stage Media</h3>
                <label class="field-label">Custom Wallpaper URL / Path</label>
                <input type="text" name="wallpaper" value="<?php echo htmlspecialchars($op['wallpaper'] ?? ''); ?>" placeholder="uploads/bg_op.jpg or https://...">

                <div class="grid-inputs">
                    <div>
                        <label class="field-label">Dossier YouTube URL</label>
                        <input type="text" name="video_url" value="<?php echo htmlspecialchars($op['video_url'] ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=...">
                    </div>
                    <div>
                        <label class="field-label">How to Play Video (YouTube / MP4)</label>
                        <input type="text" name="howto_video_url" value="<?php echo htmlspecialchars($op['howto_video_url'] ?? ''); ?>" placeholder="https://... or uploads/howto.mp4">
                    </div>
                    <div>
                        <label class="field-label">Deploy Video (MP4 / WEBM)</label>
                        <input type="text" name="deploy_video" value="<?php echo htmlspecialchars($op['deploy_video'] ?? ''); ?>" placeholder="uploads/deploy.mp4">
                    </div>
                    <div>
                        <label class="field-label">Stand / Idle Video (MP4 / WEBM)</label>
                        <input type="text" name="stage_video" value="<?php echo htmlspecialchars($op['stage_video'] ?? ''); ?>" placeholder="uploads/stand.mp4">
                    </div>
                    <div>
                        <label class="field-label">Attack Video (MP4 / WEBM)</label>
                        <input type="text" name="attack_video" value="<?php echo htmlspecialchars($op['attack_video'] ?? ''); ?>" placeholder="uploads/attack.mp4">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Archive / Biography</h3>
                <textarea name="lore" placeholder="Background story..." style="height: 120px; margin-bottom: 0;"><?php echo htmlspecialchars($op['lore'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn-save">Update Records</button>
        </form>
    </main>
</body>
</html>