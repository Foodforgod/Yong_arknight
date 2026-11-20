<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 'guest';
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    die("<div style='background:#0B131D; color:#EF4444; text-align:center; padding:60px; font-family:sans-serif;'>
        <h2>⛔ ACCESS DENIED: ADMINISTRATOR PRIVILEGES REQUIRED</h2>
        <p style='color:#94A3B8;'>Only administrators can recruit new operators.</p>
        <a href='index.php' style='color:#00B4D8; font-weight:bold;'>⬅ Return to Index</a>
    </div>");
}

$message = '';
$isError = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name   = trim($_POST['name'] ?? '');
    $rarity = max(1, min(6, (int)($_POST['rarity'] ?? 1)));
    $class  = trim($_POST['class'] ?? 'Vanguard');
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

    if ($name !== '' && $img_url !== '') {
        try {
            $sql = "INSERT INTO operators (
                        name, rarity, class, lore,
                        image_path, hp, atk, def, block_count, deploy_cost,
                        image_path_e2, hp_e2, atk_e2, def_e2, block_count_e2, deploy_cost_e2,
                        wallpaper, video_url, howto_video_url, stage_video, deploy_video, attack_video,
                        s1_name, s1_img, s1_desc,
                        s2_name, s2_img, s2_desc,
                        s3_name, s3_img, s3_desc
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $params = [
                $name, $rarity, $class, $lore,
                $img_url, $hp, $atk, $def, $block, $cost,
                $img_url_e2, $hp_e2, $atk_e2, $def_e2, $block_e2, $cost_e2,
                $wallpaper, $video_url, $howto_video_url, $stage_video, $deploy_video, $attack_video,
                $s1_name, $s1_img, $s1_desc,
                $s2_name, $s2_img, $s2_desc,
                $s3_name, $s3_img, $s3_desc
            ];

            if ($stmt->execute($params)) {
                $message = "[SUCCESS] Operator files archived. Redirecting...";
                $isError = false;
            }
        } catch (PDOException $e) {
            // Fallback insert if optional media columns aren't present on an older schema
            $fallbackSql = "INSERT INTO operators (
                                name, rarity, class, lore,
                                image_path, hp, atk, def, block_count, deploy_cost,
                                image_path_e2, hp_e2, atk_e2, def_e2, block_count_e2, deploy_cost_e2,
                                s1_name, s1_img, s1_desc,
                                s2_name, s2_img, s2_desc,
                                s3_name, s3_img, s3_desc
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $fallbackStmt = $pdo->prepare($fallbackSql);
            $fallbackParams = [
                $name, $rarity, $class, $lore,
                $img_url, $hp, $atk, $def, $block, $cost,
                $img_url_e2, $hp_e2, $atk_e2, $def_e2, $block_e2, $cost_e2,
                $s1_name, $s1_img, $s1_desc,
                $s2_name, $s2_img, $s2_desc,
                $s3_name, $s3_img, $s3_desc
            ];
            if ($fallbackStmt->execute($fallbackParams)) {
                $message = "[SUCCESS] Operator files archived. Redirecting...";
                $isError = false;
            } else {
                $message = "[ERROR] Could not save operator record.";
                $isError = true;
            }
        }
    } else {
        $message = "[ERROR] Name and Base Image are required.";
        $isError = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Management - Rhodes Island</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-section { background: rgba(255, 255, 255, 0.03); padding: 15px; margin-bottom: 20px; border-left: 3px solid #444; }
        .form-section.e2 { border-left-color: #ffb400; }
        .section-title { font-size: 12px; color: #888; margin-bottom: 15px; letter-spacing: 1px; text-transform: uppercase; }
        h3 { margin-top: 0; font-size: 16px; color: #fff; text-transform: uppercase; }
        .status-box { padding: 12px 16px; border-radius: 6px; margin-bottom: 18px; font-weight: bold; text-align: center; }
        .status-ok { background: rgba(16, 185, 129, 0.16); border: 1px solid #10B981; color: #6EE7B7; }
        .status-err { background: rgba(239, 68, 68, 0.16); border: 1px solid #EF4444; color: #FCA5A5; }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="logo">HR MANAGEMENT: NEW RECRUIT</div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <?php if ($role === 'admin'): ?>
                <a href="admin.php" class="btn-add" style="background:#ffb400; color:#000;">⚙️ ADMIN PORTAL</a>
            <?php endif; ?>
            <a href="index.php" class="btn-add">⬅ BACK TO GALLERY</a>
        </div>
    </header>

    <div class="form-container">
        <?php if ($message !== ''): ?>
            <div class="status-box <?php echo $isError ? 'status-err' : 'status-ok'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php if (!$isError): ?>
                <script>setTimeout(() => { window.location.href = 'index.php'; }, 1200);</script>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST">
            <div class="form-section">
                <h3>CORE PROFILE</h3>
                <input type="text" name="name" placeholder="Operator Name" required>
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <select name="rarity" style="flex: 1;">
                        <option value="6">6 Stars ★★★★★★</option>
                        <option value="5">5 Stars ★★★★★</option>
                        <option value="4">4 Stars ★★★★</option>
                        <option value="3">3 Stars ★★★</option>
                        <option value="2">2 Stars ★★</option>
                        <option value="1">1 Star ★</option>
                    </select>
                    <select name="class" style="flex: 1;">
                        <option value="Vanguard">Vanguard</option>
                        <option value="Guard">Guard</option>
                        <option value="Sniper">Sniper</option>
                        <option value="Defender">Defender</option>
                        <option value="Medic">Medic</option>
                        <option value="Caster">Caster</option>
                        <option value="Supporter">Supporter</option>
                        <option value="Specialist">Specialist</option>
                    </select>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title">BASE / ELITE 1 DATA</div>
                <input type="text" name="image_path" placeholder="Base Image URL / Path (Required)" required>
                <div class="grid-inputs">
                    <input type="number" name="hp" placeholder="Max HP" required>
                    <input type="number" name="atk" placeholder="Attack" required>
                    <input type="number" name="def" placeholder="Defense" required>
                    <input type="number" name="block_count" placeholder="Block" required>
                    <input type="number" name="deploy_cost" placeholder="DP Cost" required>
                </div>
            </div>

            <div class="form-section e2">
                <div class="section-title" style="color: #ffb400;">ELITE 2 DATA (OPTIONAL)</div>
                <input type="text" name="image_path_e2" placeholder="Elite 2 Image URL / Path">
                <div class="grid-inputs">
                    <input type="number" name="hp_e2" placeholder="E2 Max HP">
                    <input type="number" name="atk_e2" placeholder="E2 Attack">
                    <input type="number" name="def_e2" placeholder="E2 Defense">
                    <input type="number" name="block_count_e2" placeholder="E2 Block">
                    <input type="number" name="deploy_cost_e2" placeholder="E2 DP Cost">
                </div>
            </div>

            <div class="form-section">
                <h3>SKILLS CONFIGURATION</h3>
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <div style="border-bottom: 1px solid #333; margin-bottom: 10px; padding-bottom: 10px;">
                        <p style="color: #888; font-size: 11px;">SKILL 0<?php echo $i; ?></p>
                        <input type="text" name="s<?php echo $i; ?>_name" placeholder="Skill Name">
                        <input type="text" name="s<?php echo $i; ?>_img" placeholder="Skill Icon URL">
                        <textarea name="s<?php echo $i; ?>_desc" placeholder="Skill Description" style="height: 60px;"></textarea>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="form-section">
                <h3>WALLPAPER & STAGE MEDIA (OPTIONAL)</h3>
                <input type="text" name="wallpaper" placeholder="Wallpaper URL or path (e.g., uploads/bg.jpg)">
                <input type="text" name="video_url" placeholder="Dossier YouTube URL">
                <input type="text" name="howto_video_url" placeholder="How to Play Video URL or MP4/WEBM path">
                <div class="grid-inputs">
                    <input type="text" name="deploy_video" placeholder="Deploy Video MP4/WEBM path">
                    <input type="text" name="stage_video" placeholder="Stand Video MP4/WEBM path">
                    <input type="text" name="attack_video" placeholder="Attack Video MP4/WEBM path">
                </div>
            </div>

            <div class="form-section">
                <h3>ARCHIVE / LORE</h3>
                <textarea name="lore" placeholder="Enter background story..." style="width: 100%; height: 100px; background: #2a2a2a; color: white; border: 1px solid #333; padding: 10px; box-sizing: border-box;"></textarea>
            </div>

            <button type="submit" style="width: 100%; padding: 15px; background: #ffb400; color: #000; font-weight: bold; border: none; cursor: pointer; text-transform: uppercase;">Confirm Recruitment</button>
        </form>
    </div>
</body>
</html>