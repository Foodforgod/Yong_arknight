<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$role = $_SESSION['role'] ?? 'guest';
if ($role !== 'admin') {
    http_response_code(403);
    die("<div style='background:#0B131D; color:#EF4444; text-align:center; padding:60px; font-family:sans-serif;'>
        <h2>⛔ ACCESS DENIED: ADMINISTRATOR PRIVILEGES REQUIRED</h2>
        <p style='color:#94A3B8;'>Only administrators can recruit new operators.</p>
        <a href='index.php' style='color:#00B4D8; font-weight:bold;'>⬅ Return to Index</a>
    </div>");
}

function saveNewRecruitUpload(string $fieldName, array $allowedExts, string $subdir, string $prefix, array &$uploadErrors, string $label): ?string {
    if (!isset($_FILES[$fieldName])) {
        return null;
    }
    $errCode = (int)($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errCode === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($errCode !== UPLOAD_ERR_OK) {
        $uploadErrors[] = "{$label} upload failed (error code {$errCode}). Check PHP upload limits if file is large.";
        return null;
    }

    $dir = 'uploads' . ($subdir !== '' ? '/' . trim($subdir, '/') : '');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        $uploadErrors[] = "Could not create upload directory '{$dir}'.";
        return null;
    }

    $origName = (string)($_FILES[$fieldName]['name'] ?? '');
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        $uploadErrors[] = "{$label} rejected: '.{$ext}' is not allowed (" . implode(', ', $allowedExts) . ").";
        return null;
    }

    try {
        $randSuffix = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        $randSuffix = (string)mt_rand(1000, 9999);
    }

    $target = $dir . '/' . $prefix . '_' . time() . '_' . $randSuffix . '.' . $ext;
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $target)) {
        $uploadErrors[] = "Failed to save {$label} to '{$target}'. Check folder permissions.";
        return null;
    }

    return $target;
}

$message = '';
$isError = false;
$uploadErrors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        $message = "[SECURITY] Invalid or expired CSRF token.";
        $isError = true;
    } else {
        $name   = trim($_POST['name'] ?? '');
        $rarity = max(1, min(6, (int)($_POST['rarity'] ?? 1)));
        $class  = trim($_POST['class'] ?? 'Vanguard');
        $lore   = trim($_POST['lore'] ?? '');

        $img_url = trim($_POST['image_path'] ?? '');
        if ($uploadedBase = saveNewRecruitUpload('image_path_file', ['jpg', 'jpeg', 'png', 'webp', 'gif'], '', 'op_base', $uploadErrors, 'Base Portrait')) {
            $img_url = $uploadedBase;
        }

        $hp      = (int)($_POST['hp'] ?? 0);
        $atk     = (int)($_POST['atk'] ?? 0);
        $def     = (int)($_POST['def'] ?? 0);
        $block   = (int)($_POST['block_count'] ?? 1);
        $cost    = (int)($_POST['deploy_cost'] ?? 0);

        $img_url_e2 = trim($_POST['image_path_e2'] ?? '');
        if ($uploadedE2 = saveNewRecruitUpload('image_path_e2_file', ['jpg', 'jpeg', 'png', 'webp', 'gif'], '', 'op_e2', $uploadErrors, 'Elite 2 Portrait')) {
            $img_url_e2 = $uploadedE2;
        }

        $hp_e2      = !empty($_POST['hp_e2']) ? (int)$_POST['hp_e2'] : 0;
        $atk_e2     = !empty($_POST['atk_e2']) ? (int)$_POST['atk_e2'] : 0;
        $def_e2     = !empty($_POST['def_e2']) ? (int)$_POST['def_e2'] : 0;
        $block_e2   = !empty($_POST['block_count_e2']) ? (int)$_POST['block_count_e2'] : 0;
        $cost_e2    = !empty($_POST['deploy_cost_e2']) ? (int)$_POST['deploy_cost_e2'] : 0;

        $wallpaper = trim($_POST['wallpaper'] ?? '');
        if ($uploadedWall = saveNewRecruitUpload('wallpaper_file', ['jpg', 'jpeg', 'png', 'webp', 'gif'], '', 'bg_op', $uploadErrors, 'Wallpaper')) {
            $wallpaper = $uploadedWall;
        }

        $video_url       = trim($_POST['video_url'] ?? '');
        $howto_video_url = trim($_POST['howto_video_url'] ?? '');
        if ($uploadedHowto = saveNewRecruitUpload('howto_video_file', ['mp4', 'webm'], '', 'howto_op', $uploadErrors, 'How-to-Play Video')) {
            $howto_video_url = $uploadedHowto;
        }

        $stage_video = trim($_POST['stage_video'] ?? '');
        if ($uploadedStand = saveNewRecruitUpload('stage_video_file', ['mp4', 'webm'], '', 'stage_op', $uploadErrors, 'Stand Video')) {
            $stage_video = $uploadedStand;
        }

        $deploy_video = trim($_POST['deploy_video'] ?? '');
        if ($uploadedDeploy = saveNewRecruitUpload('deploy_video_file', ['mp4', 'webm'], '', 'deploy_op', $uploadErrors, 'Deploy Video')) {
            $deploy_video = $uploadedDeploy;
        }

        $attack_video = trim($_POST['attack_video'] ?? '');
        if ($uploadedAttack = saveNewRecruitUpload('attack_video_file', ['mp4', 'webm'], '', 'attack_op', $uploadErrors, 'Attack Video')) {
            $attack_video = $uploadedAttack;
        }

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
            $newOpId = 0;
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
                    $newOpId = (int)$pdo->lastInsertId();
                    $message = "[SUCCESS] Operator files archived. Redirecting...";
                    $isError = false;
                }
            } catch (PDOException $e) {
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
                    $newOpId = (int)$pdo->lastInsertId();
                    $message = "[SUCCESS] Operator files archived. Redirecting...";
                    $isError = false;
                } else {
                    $message = "[ERROR] Could not save operator record.";
                    $isError = true;
                }
            }

            if (!$isError && $newOpId > 0 && isset($_FILES['mp3_files']) && is_array($_FILES['mp3_files']['name'])) {
                if (!is_dir('uploads/sounds')) {
                    @mkdir('uploads/sounds', 0755, true);
                }
                foreach ($_FILES['mp3_files']['name'] as $index => $filename) {
                    $audioErr = (int)($_FILES['mp3_files']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
                    if ($audioErr === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if ($audioErr !== UPLOAD_ERR_OK) {
                        $uploadErrors[] = "Voice file '" . basename((string)$filename) . "' failed (code {$audioErr}).";
                        continue;
                    }
                    $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['mp3', 'wav'], true)) {
                        $uploadErrors[] = "Voice file '" . basename((string)$filename) . "' skipped (only MP3/WAV allowed).";
                        continue;
                    }
                    $soundName = pathinfo((string)$filename, PATHINFO_FILENAME);
                    try {
                        $randSuffix = bin2hex(random_bytes(3));
                    } catch (Throwable $e) {
                        $randSuffix = (string)mt_rand(100, 999);
                    }
                    $audioPath = 'uploads/sounds/sound_' . $newOpId . '_' . time() . '_' . $index . '_' . $randSuffix . '.' . $ext;
                    if (move_uploaded_file($_FILES['mp3_files']['tmp_name'][$index], $audioPath)) {
                        try {
                            $sndIns = $pdo->prepare("INSERT INTO operator_sounds (operator_id, sound_name, file_path) VALUES (?, ?, ?)");
                            $sndIns->execute([$newOpId, $soundName, $audioPath]);
                        } catch (Throwable $e) {
                            // Ignore if table not present
                        }
                    } else {
                        $uploadErrors[] = "Failed to move voice file '" . basename((string)$filename) . "'.";
                    }
                }
            }

            if (!$isError && !empty($uploadErrors)) {
                $message = "[PARTIAL] Operator saved, but some uploads had issues: " . implode(' | ', $uploadErrors);
            }
        } else {
            $detail = !empty($uploadErrors) ? ' (' . implode(' | ', $uploadErrors) . ')' : '';
            $message = "[ERROR] Name and Base Image (URL or file upload) are required." . $detail;
            $isError = true;
        }
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
        .upload-hint { display: block; font-size: 11px; color: #94A3B8; margin: -8px 0 12px; }
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
            <?php if (!$isError && empty($uploadErrors)): ?>
                <script>setTimeout(() => { window.location.href = 'index.php'; }, 1200);</script>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

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
                <input type="text" name="image_path" placeholder="Base Image URL / Path (or upload file below)">
                <input type="file" name="image_path_file" accept=".jpg,.jpeg,.png,.webp,.gif">
                <span class="upload-hint">Upload Base Portrait (JPG, PNG, WEBP, GIF) or provide URL above.</span>
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
                <input type="file" name="image_path_e2_file" accept=".jpg,.jpeg,.png,.webp,.gif">
                <span class="upload-hint">Optional E2 portrait upload.</span>
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
                <h3>WALLPAPER, VOICE & STAGE MEDIA (OPTIONAL)</h3>
                <input type="text" name="wallpaper" placeholder="Wallpaper URL or path (e.g., uploads/bg.jpg)">
                <input type="file" name="wallpaper_file" accept=".jpg,.jpeg,.png,.webp,.gif">
                <span class="upload-hint">Upload background wallpaper image.</span>

                <input type="text" name="video_url" placeholder="Dossier YouTube URL">
                <input type="text" name="howto_video_url" placeholder="How to Play Video URL or MP4/WEBM path">
                <input type="file" name="howto_video_file" accept=".mp4,.webm">
                <span class="upload-hint">Optional How to Play MP4/WEBM upload.</span>

                <div class="grid-inputs">
                    <input type="text" name="deploy_video" placeholder="Deploy Video MP4/WEBM path">
                    <input type="file" name="deploy_video_file" accept=".mp4,.webm">
                    <input type="text" name="stage_video" placeholder="Stand Video MP4/WEBM path">
                    <input type="file" name="stage_video_file" accept=".mp4,.webm">
                    <input type="text" name="attack_video" placeholder="Attack Video MP4/WEBM path">
                    <input type="file" name="attack_video_file" accept=".mp4,.webm">
                </div>

                <label style="display:block; font-size:11px; color:#94A3B8; margin-top:10px; text-transform:uppercase;">Voice Lines (MP3 / WAV)</label>
                <input type="file" name="mp3_files[]" accept=".mp3,.wav,audio/mpeg,audio/wav" multiple>
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