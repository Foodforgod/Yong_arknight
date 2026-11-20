<?php
require_once 'db.php';
session_start();

// Strict Admin Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("<div style='background:#0B131D; color:#EF4444; text-align:center; padding:60px; font-family:sans-serif;'>
        <h2>⛔ ACCESS DENIED: ADMINISTRATOR PRIVILEGES REQUIRED</h2>
        <p style='color:#94A3B8;'>Guests and Normal Users cannot edit operator records.</p>
        <a href='index.php' style='color:#00B4D8; font-weight:bold;'>⬅ Return to User Index</a>
    </div>");
}

// Ensure operator_sounds.sound_name supports long subtitle text without truncation
try {
    $colStmt = $pdo->query("SHOW COLUMNS FROM operator_sounds LIKE 'sound_name'");
    $colInfo = $colStmt->fetch(PDO::FETCH_ASSOC);
    if ($colInfo && stripos($colInfo['Type'], 'text') === false) {
        $pdo->exec("ALTER TABLE operator_sounds MODIFY COLUMN sound_name LONGTEXT NULL");
    }
} catch (Throwable $e) {
    // Ignore if table/column check isn't needed or already upgraded
}

// Ensure operators.howto_video_url exists for "How to Play" wiki video
try {
    $howtoColStmt = $pdo->query("SHOW COLUMNS FROM operators LIKE 'howto_video_url'");
    if ($howtoColStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE operators ADD COLUMN howto_video_url VARCHAR(500) NULL DEFAULT NULL AFTER video_url");
    }
} catch (Throwable $e) {
    // Ignore if column already exists or insufficient permissions
}

// Ensure operators.deploy_video and attack_video exist for AI Talking stage animations
try {
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

function saveUploadedStageVideo(string $fieldName, int $opId, string $prefix): ?string {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true);
    }
    $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp4', 'webm'], true)) {
        return null;
    }
    $target = 'uploads/' . $prefix . '_op_' . $opId . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $target)) {
        return $target;
    }
    return null;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $class       = trim($_POST['class'] ?? '');
    $rarity      = intval($_POST['rarity'] ?? 1);
    
    // Phase 0/1 Stats
    $hp          = intval($_POST['hp'] ?? 0);
    $atk         = intval($_POST['atk'] ?? 0);
    $def         = intval($_POST['def'] ?? 0);
    $block       = intval($_POST['block_count'] ?? 1);
    $cost        = intval($_POST['deploy_cost'] ?? 0);

    // Phase 2 (E2) Stats
    $hp_e2       = intval($_POST['hp_e2'] ?? 0);
    $atk_e2      = intval($_POST['atk_e2'] ?? 0);
    $def_e2      = intval($_POST['def_e2'] ?? 0);
    $block_e2    = intval($_POST['block_count_e2'] ?? 1);
    $cost_e2     = intval($_POST['deploy_cost_e2'] ?? 0);

    // Lore, Media, YouTube Wiki, How to Play Video & AI Talking Stage Animations
    $lore            = trim($_POST['lore'] ?? '');
    $image_path      = trim($_POST['image_path'] ?? '');
    $image_e2        = trim($_POST['image_path_e2'] ?? '');
    $wallpaper       = trim($_POST['wallpaper'] ?? '');
    $video_url       = trim($_POST['video_url'] ?? '');       // YouTube link for details.php
    $howto_video_url = trim($_POST['howto_video_url'] ?? ''); // How to Play video for details.php
    $stage_video     = trim($_POST['stage_video'] ?? '');     // Stand animation for ai_talking.php
    $deploy_video    = trim($_POST['deploy_video'] ?? '');    // Deploy animation for ai_talking.php
    $attack_video    = trim($_POST['attack_video'] ?? '');    // Attack animation for ai_talking.php

    // Handle direct HD wallpaper image file upload (.jpg, .png, .webp, .gif)
    if (isset($_FILES['wallpaper_file']) && $_FILES['wallpaper_file']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['wallpaper_file']['name'], PATHINFO_EXTENSION)); 
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowed)) { 
            $new_filename = 'uploads/bg_op_' . $id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['wallpaper_file']['tmp_name'], $new_filename)) {
                $wallpaper = $new_filename;
            }
        }
    }

    // Handle direct AI Talking Stand, Deploy, and Attack MP4/WEBM file uploads
    if ($uploadedStand = saveUploadedStageVideo('video_file', $id, 'stage')) {
        $stage_video = $uploadedStand;
    }
    if ($uploadedDeploy = saveUploadedStageVideo('deploy_video_file', $id, 'deploy')) {
        $deploy_video = $uploadedDeploy;
    }
    if ($uploadedAttack = saveUploadedStageVideo('attack_video_file', $id, 'attack')) {
        $attack_video = $uploadedAttack;
    }

    // Handle updating existing sound subtitle/translation texts
    if (isset($_POST['sound_texts']) && is_array($_POST['sound_texts'])) {
        foreach ($_POST['sound_texts'] as $snd_id => $custom_text) {
            $clean_text = preg_replace('/\s+/', ' ', trim((string)$custom_text));
            $upd_snd = $pdo->prepare("UPDATE operator_sounds SET sound_name = ? WHERE id = ? AND operator_id = ?");
            $upd_snd->execute([$clean_text, (int)$snd_id, $id]);
        }
    }

    // Handle multiple MP3 and WAV sound file uploads
    if (isset($_FILES['mp3_files'])) {
        if (!is_dir('uploads/sounds')) {
            mkdir('uploads/sounds', 0777, true);
        }
        foreach ($_FILES['mp3_files']['name'] as $index => $filename) {
            if ($_FILES['mp3_files']['error'][$index] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (in_array($ext, ['mp3', 'wav'])) { 
                    $sound_name = pathinfo($filename, PATHINFO_FILENAME); 
                    $new_audio_path = 'uploads/sounds/sound_' . $id . '_' . time() . '_' . $index . '.' . $ext;
                    if (move_uploaded_file($_FILES['mp3_files']['tmp_name'][$index], $new_audio_path)) {
                        $sound_insert = $pdo->prepare("INSERT INTO operator_sounds (operator_id, sound_name, file_path) VALUES (?, ?, ?)");
                        $sound_insert->execute([$id, $sound_name, $new_audio_path]);
                    }
                }
            }
        }
    }

    // Handle deleting individual MP3/WAV sounds if requested
    if (isset($_POST['delete_sound_id']) && !empty($_POST['delete_sound_id'])) {
        $del_id = (int)$_POST['delete_sound_id'];
        $s_fetch = $pdo->prepare("SELECT file_path FROM operator_sounds WHERE id = ? AND operator_id = ?");
        $s_fetch->execute([$del_id, $id]);
        $s_row = $s_fetch->fetch(PDO::FETCH_ASSOC);
        if ($s_row) {
            if (file_exists($s_row['file_path'])) { @unlink($s_row['file_path']); }
            $s_del = $pdo->prepare("DELETE FROM operator_sounds WHERE id = ?");
            $s_del->execute([$del_id]);
        }
    }

    // Combat Skills (01 - 03)
    $s1_name = trim($_POST['s1_name'] ?? '');
    $s1_img  = trim($_POST['s1_img'] ?? '');
    $s1_desc = trim($_POST['s1_desc'] ?? '');

    $s2_name = trim($_POST['s2_name'] ?? '');
    $s2_img  = trim($_POST['s2_img'] ?? '');
    $s2_desc = trim($_POST['s2_desc'] ?? '');

    $s3_name = trim($_POST['s3_name'] ?? '');
    $s3_img  = trim($_POST['s3_img'] ?? '');
    $s3_desc = trim($_POST['s3_desc'] ?? '');

    $update_stmt = $pdo->prepare("UPDATE operators SET 
        name = ?, class = ?, rarity = ?, 
        hp = ?, atk = ?, def = ?, block_count = ?, deploy_cost = ?,
        hp_e2 = ?, atk_e2 = ?, def_e2 = ?, block_count_e2 = ?, deploy_cost_e2 = ?,
        lore = ?, image_path = ?, image_path_e2 = ?, wallpaper = ?, video_url = ?, howto_video_url = ?, stage_video = ?, deploy_video = ?, attack_video = ?,
        s1_name = ?, s1_img = ?, s1_desc = ?,
        s2_name = ?, s2_img = ?, s2_desc = ?,
        s3_name = ?, s3_img = ?, s3_desc = ?
        WHERE id = ?");

    $update_stmt->execute([
        $name, $class, $rarity,
        $hp, $atk, $def, $block, $cost,
        $hp_e2, $atk_e2, $def_e2, $block_e2, $cost_e2,
        $lore, $image_path, $image_e2, $wallpaper, $video_url, $howto_video_url, $stage_video, $deploy_video, $attack_video,
        $s1_name, $s1_img, $s1_desc,
        $s2_name, $s2_img, $s2_desc,
        $s3_name, $s3_img, $s3_desc,
        $id
    ]);

    header("Location: admin_edit.php?id=" . $id . "&success=1");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM operators WHERE id = ?");
$stmt->execute([$id]);
$op = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$op) {
    die("<div style='color: white; text-align: center; margin-top: 50px;'><h2>Error: Operator not found.</h2><a href='admin.php' style='color: #ffb400;'>Return to Admin Portal</a></div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Operator - <?php echo htmlspecialchars($op['name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #0b0f12; color: #ffffff; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; }
        .edit-container { max-width: 860px; margin: 30px auto; background: rgba(22, 34, 49, 0.95); border: 2px solid #E59811; border-radius: 10px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.9); }
        h2 { color: #E59811; text-transform: uppercase; letter-spacing: 1px; margin-top: 0; border-bottom: 2px solid #2A3B52; padding-bottom: 12px; font-size: 18px; }
        h3 { color: #00B4D8; text-transform: uppercase; letter-spacing: 1px; font-size: 13px; margin: 24px 0 12px 0; border-bottom: 1px solid #2A3B52; padding-bottom: 6px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; color: #00B4D8; font-weight: bold; margin-bottom: 6px; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; background: #0B131D; border: 1px solid #2A3B52; color: #E2E8F0; font-size: 14px; border-radius: 6px; outline: none; box-sizing: border-box; }
        .form-group textarea { height: 110px; resize: vertical; }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        .skill-card-edit { background: #0B131D; border: 1px solid #2A3B52; border-left: 3px solid #E59811; padding: 15px; border-radius: 6px; margin-bottom: 14px; }
        .anim-slot-card { background: #0B131D; border: 1px solid #2A3B52; border-left: 3px solid #00B4D8; padding: 15px; border-radius: 6px; margin-bottom: 14px; }
        .sound-row-card { display: flex; gap: 10px; margin-bottom: 12px; align-items: flex-start; background: #101B29; padding: 10px; border-radius: 6px; border: 1px solid #1E2E42; }
        .sound-textarea { flex: 1; min-height: 68px; padding: 10px; font-size: 13px; line-height: 1.5; resize: vertical; background: #0B131D; border: 1px solid #2A3B52; color: #E2E8F0; border-radius: 6px; box-sizing: border-box; }
        .btn-submit { background: #E59811; color: #000; font-weight: bold; padding: 14px 25px; border: none; border-radius: 6px; cursor: pointer; text-transform: uppercase; font-size: 15px; width: 100%; margin-top: 10px; transition: 0.2s; }
        .btn-submit:hover { background: #fff; }
        .nav-links { text-align: center; margin-top: 20px; font-size: 13px; }
        .nav-links a { color: #00B4D8; text-decoration: none; margin: 0 10px; font-weight: bold; }
        .nav-links a:hover { text-decoration: underline; }
        .success-msg { background: rgba(76, 175, 80, 0.2); border: 1px solid #4caf50; color: #4caf50; padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center; font-weight: bold; }
    </style>
</head>
<body>
    <div class="edit-container">
        <h2>⚙️ Edit Operator Record: <?php echo htmlspecialchars($op['name']); ?></h2>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-msg">Changes saved successfully to database!</div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
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

            <h3>📊 Phase 0/1 Combat Stats</h3>
            <div class="form-row">
                <div class="form-group"><label>Max HP</label><input type="number" name="hp" value="<?php echo intval($op['hp']); ?>"></div>
                <div class="form-group"><label>Attack (ATK)</label><input type="number" name="atk" value="<?php echo intval($op['atk']); ?>"></div>
                <div class="form-group"><label>Defense (DEF)</label><input type="number" name="def" value="<?php echo intval($op['def']); ?>"></div>
                <div class="form-group"><label>Block</label><input type="number" name="block_count" value="<?php echo intval($op['block_count'] ?? 1); ?>"></div>
                <div class="form-group"><label>DP Cost</label><input type="number" name="deploy_cost" value="<?php echo intval($op['deploy_cost'] ?? 0); ?>"></div>
            </div>

            <h3>🌟 Elite 2 (Phase 2) Combat Stats</h3>
            <div class="form-row">
                <div class="form-group"><label>E2 Max HP</label><input type="number" name="hp_e2" value="<?php echo intval($op['hp_e2'] ?? 0); ?>"></div>
                <div class="form-group"><label>E2 Attack</label><input type="number" name="atk_e2" value="<?php echo intval($op['atk_e2'] ?? 0); ?>"></div>
                <div class="form-group"><label>E2 Defense</label><input type="number" name="def_e2" value="<?php echo intval($op['def_e2'] ?? 0); ?>"></div>
                <div class="form-group"><label>E2 Block</label><input type="number" name="block_count_e2" value="<?php echo intval($op['block_count_e2'] ?? 1); ?>"></div>
                <div class="form-group"><label>E2 DP Cost</label><input type="number" name="deploy_cost_e2" value="<?php echo intval($op['deploy_cost_e2'] ?? 0); ?>"></div>
            </div>

            <h3>⚡ Combat Skills (Skills 01 – 03)</h3>
            <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="skill-card-edit">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Skill 0<?php echo $i; ?> Name</label>
                            <input type="text" name="s<?php echo $i; ?>_name" value="<?php echo htmlspecialchars($op["s{$i}_name"] ?? ''); ?>" placeholder="e.g., Chi Xiao - Fleeting Night">
                        </div>
                        <div class="form-group">
                            <label>Skill 0<?php echo $i; ?> Icon URL</label>
                            <input type="text" name="s<?php echo $i; ?>_img" value="<?php echo htmlspecialchars($op["s{$i}_img"] ?? ''); ?>" placeholder="https://arknights.wiki.gg/images/...">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Skill 0<?php echo $i; ?> Description</label>
                        <textarea name="s<?php echo $i; ?>_desc" style="height: 70px;" placeholder="Enter skill description..."><?php echo htmlspecialchars($op["s{$i}_desc"] ?? ''); ?></textarea>
                    </div>
                </div>
            <?php endfor; ?>

            <h3>🎵 Operator Voice Lines / Subtitles Manager</h3>
            <div class="form-group">
                <label>Upload Multiple Audio Files (MP3 / WAV)</label>
                <input type="file" name="mp3_files[]" accept=".mp3,.wav,audio/mpeg,audio/wav" multiple style="padding: 9px; background: #0B131D; border: 1px solid #2A3B52; color: #fff; border-radius: 6px; width: 100%; box-sizing: border-box;">
                <small style="color: #94A3B8; display: block; margin-top: 5px;">Select and upload multiple MP3 or WAV audio files at once. Subtitles have no character cap.</small>
            </div>

            <?php
            $sounds_q = $pdo->prepare("SELECT * FROM operator_sounds WHERE operator_id = ?");
            $sounds_q->execute([$id]);
            $existing_sounds = $sounds_q->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($existing_sounds)):
            ?>
                <div style="background: #0B131D; border: 1px solid #2A3B52; padding: 15px; border-radius: 6px; margin-bottom: 16px;">
                    <label style="color: #00B4D8; font-size: 12px; font-weight: bold; margin-bottom: 12px; display: block;">EDIT VOICE LINE SUBTITLES (UNLIMITED LENGTH):</label>
                    <?php foreach ($existing_sounds as $snd): ?>
                        <div class="sound-row-card">
                            <span style="font-size: 12px; color: #94A3B8; width: 145px; padding-top: 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($snd['file_path']); ?>">🎵 <?php echo htmlspecialchars(basename($snd['file_path'])); ?></span>
                            <textarea name="sound_texts[<?php echo $snd['id']; ?>]" class="sound-textarea" placeholder="Enter full voice line subtitle text..."><?php echo htmlspecialchars($snd['sound_name'] ?? ''); ?></textarea>
                            <button type="submit" name="delete_sound_id" value="<?php echo $snd['id']; ?>" style="background: #EF4444; color: #fff; border: none; padding: 9px 12px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer; margin-top: 4px;" onclick="return confirm('Delete this sound?');">DELETE</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h3>📖 Lore, Artwork & Media</h3>
            <div class="form-group">
                <label>Background Lore / Disposition (AI Persona)</label>
                <textarea name="lore"><?php echo htmlspecialchars($op['lore'] ?? ''); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Portrait Image Path (Standard / E1)</label>
                    <input type="text" name="image_path" value="<?php echo htmlspecialchars($op['image_path']); ?>">
                </div>
                <div class="form-group">
                    <label>Elite 2 Portrait Image Path</label>
                    <input type="text" name="image_path_e2" value="<?php echo htmlspecialchars($op['image_path_e2'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Upload HD Background Wallpaper (JPG / PNG / WEBP)</label>
                    <input type="file" name="wallpaper_file" accept=".jpg,.jpeg,.png,.webp,.gif" style="padding: 9px;">
                </div>
                <div class="form-group">
                    <label>Or Paste Background Wallpaper Path / URL</label>
                    <input type="text" name="wallpaper" value="<?php echo htmlspecialchars($op['wallpaper'] ?? ''); ?>" placeholder="uploads/bg.jpg">
                </div>
            </div>

            <div class="form-group">
                <label>🎬 Dossier YouTube Video URL (Shows on details.php ONLY)</label>
                <input type="text" name="video_url" value="<?php echo htmlspecialchars($op['video_url'] ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=...">
            </div>

            <div class="form-group">
                <label>🎮 Wiki "How to Play" Video URL (YouTube Link or MP4/WEBM Path — Shows on details.php)</label>
                <input type="text" name="howto_video_url" value="<?php echo htmlspecialchars($op['howto_video_url'] ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=... or uploads/howto_op.mp4">
            </div>

            <h3>🎞️ AI Talking Stage Animations (Deploy / Stand / Attack)</h3>

            <div class="anim-slot-card">
                <div class="form-row" style="margin-bottom: 0;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>🚀 Deploy Animation File (Plays once on entry)</label>
                        <input type="file" name="deploy_video_file" accept=".mp4,.webm" style="padding: 9px;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Or Deploy MP4/WEBM Path / URL</label>
                        <input type="text" name="deploy_video" value="<?php echo htmlspecialchars($op['deploy_video'] ?? ''); ?>" placeholder="uploads/deploy_op.mp4">
                    </div>
                </div>
            </div>

            <div class="anim-slot-card">
                <div class="form-row" style="margin-bottom: 0;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>🧍 Stand / Idle Animation File (Loops on stage)</label>
                        <input type="file" name="video_file" accept=".mp4,.webm" style="padding: 9px;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Or Stand MP4/WEBM Path / URL</label>
                        <input type="text" name="stage_video" value="<?php echo htmlspecialchars($op['stage_video'] ?? ''); ?>" placeholder="uploads/stage_op.mp4">
                    </div>
                </div>
            </div>

            <div class="anim-slot-card">
                <div class="form-row" style="margin-bottom: 0;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>⚔️ Attack Animation File (Plays on click)</label>
                        <input type="file" name="attack_video_file" accept=".mp4,.webm" style="padding: 9px;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Or Attack MP4/WEBM Path / URL</label>
                        <input type="text" name="attack_video" value="<?php echo htmlspecialchars($op['attack_video'] ?? ''); ?>" placeholder="uploads/attack_op.mp4">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit">💾 Save Changes</button>
        </form>

        <div class="nav-links">
            <a href="ai_talking.php?id=<?php echo $id; ?>">💬 Launch AI Talking Mode</a> | 
            <a href="details.php?id=<?php echo $id; ?>">📄 View Dossier</a> | 
            <a href="admin.php">⚙️ Back to Admin Portal</a>
        </div>
    </div>
</body>
</html>