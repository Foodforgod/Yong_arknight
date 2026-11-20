<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure operators.howto_video_url exists so details.php never fails on older schemas
try {
    $howtoColStmt = $pdo->query("SHOW COLUMNS FROM operators LIKE 'howto_video_url'");
    if ($howtoColStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE operators ADD COLUMN howto_video_url VARCHAR(500) NULL DEFAULT NULL AFTER video_url");
    }
} catch (Throwable $e) {
    // Ignore if column already exists or insufficient permissions
}

$role = $_SESSION['role'] ?? 'guest';
$username = $_SESSION['username'] ?? 'Guest';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$view = $_GET['view'] ?? 'phase0';

// Handle comment submission (Logged-in Normal Users & Admins only; Guests cannot post)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    if ($role === 'guest') {
        header("Location: login.php");
        exit;
    }
    $comment = trim($_POST['comment'] ?? '');
    if (!empty($comment)) {
        $c_stmt = $pdo->prepare("INSERT INTO operator_comments (operator_id, username, comment) VALUES (?, ?, ?)");
        $c_stmt->execute([$id, $username, $comment]);
        header("Location: details.php?id=" . $id . "&view=" . urlencode($view));
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM operators WHERE id = ?");
$stmt->execute([$id]);
$op = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$op) {
    die("<div style='background:#070B10; color:#F8FAFC; text-align:center; padding:60px; font-family:sans-serif;'><h2>Error: Operator not found.</h2><a href='index.php' style='color:#ffb400;'>Return to Index</a></div>");
}

// Fetch another random operator ID for quick hopping
$randomId = $id;
try {
    $randStmt = $pdo->prepare("SELECT id FROM operators WHERE id != ? ORDER BY RAND() LIMIT 1");
    $randStmt->execute([$id]);
    $randRow = $randStmt->fetch(PDO::FETCH_ASSOC);
    if ($randRow && !empty($randRow['id'])) {
        $randomId = (int)$randRow['id'];
    }
} catch (Throwable $e) {
    $randomId = $id;
}

$has_e2 = !empty($op['image_path_e2']);
if ($view === 'phase2' && !$has_e2) {
    $view = 'phase0';
}

if ($view === 'phase2') {
    $active_img   = $op['image_path_e2'];
    $active_hp    = (int)($op['hp_e2'] ?? 0);
    $active_atk   = (int)($op['atk_e2'] ?? 0);
    $active_def   = (int)($op['def_e2'] ?? 0);
    $active_block = (int)($op['block_count_e2'] ?? 1);
    $active_cost  = (int)($op['deploy_cost_e2'] ?? 0);
} else {
    $active_img   = $op['image_path'];
    $active_hp    = (int)($op['hp'] ?? 0);
    $active_atk   = (int)($op['atk'] ?? 0);
    $active_def   = (int)($op['def'] ?? 0);
    $active_block = (int)($op['block_count'] ?? 1);
    $active_cost  = (int)($op['deploy_cost'] ?? 0);
}

$rarityInt = max(1, min(6, (int)($op['rarity'] ?? 1)));
$rarityStars = str_repeat('★', $rarityInt);

if ($rarityInt === 6) {
    $rarityAccent = '#ffb400';
} elseif ($rarityInt === 5) {
    $rarityAccent = '#e5b1ff';
} elseif ($rarityInt === 4) {
    $rarityAccent = '#29b6f6';
} else {
    $rarityAccent = '#94A3B8';
}

$custom_wallpaper = trim($op['wallpaper'] ?? '');
$safe_wallpaper = htmlspecialchars($custom_wallpaper, ENT_QUOTES, 'UTF-8');

function getYoutubeEmbedOnly($url) {
    if (empty($url)) return '';
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    }
    return '';
}

function renderHowToVideo($url, $title = 'How to Play Video') {
    $url = trim((string)$url);
    if ($url === '') return '';

    if (preg_match('/\.(mp4|webm)(\?.*)?$/i', $url)) {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $mime = ($ext === 'webm') ? 'video/webm' : 'video/mp4';
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        return '<div class="video-frame">'
             . '<video controls preload="metadata" title="' . $safeTitle . '">'
             . '<source src="' . $safeUrl . '" type="' . $mime . '">'
             . 'Your browser does not support HTML5 video.'
             . '</video></div>';
    }

    $ytEmbed = getYoutubeEmbedOnly($url);
    if ($ytEmbed !== '') {
        $safeEmbed = htmlspecialchars($ytEmbed, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        return '<div class="video-frame">'
             . '<iframe src="' . $safeEmbed . '" title="' . $safeTitle . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'
             . '</div>';
    }

    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    return '<p style="margin:0;"><a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer" style="color: #00B4D8; font-weight: bold;">▶ Open How to Play Guide</a></p>';
}

$embed_video = getYoutubeEmbedOnly($op['video_url'] ?? '');
$howto_video_html = renderHowToVideo($op['howto_video_url'] ?? '', 'How to Play - ' . ($op['name'] ?? 'Operator'));

$comments_stmt = $pdo->prepare("SELECT * FROM operator_comments WHERE operator_id = ? ORDER BY created_at DESC");
$comments_stmt->execute([$id]);
$comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($op['name']); ?> - Dossier</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * { box-sizing: border-box; }

        body.details-page {
            background-color: #070B10 !important;
            min-height: 100vh;
            margin: 0;
            text-align: left;
            color: #E2E8F0;
            <?php if ($custom_wallpaper !== ''): ?>
            background-image: linear-gradient(135deg, rgba(7, 11, 16, 0.72), rgba(11, 19, 29, 0.86)), url('<?php echo $safe_wallpaper; ?>') !important;
            background-size: cover !important;
            background-position: center center !important;
            background-attachment: fixed !important;
            background-repeat: no-repeat !important;
            <?php endif; ?>
        }

        .details-shell {
            display: grid;
            grid-template-columns: minmax(300px, 430px) minmax(0, 1fr);
            gap: 28px;
            width: 92%;
            max-width: 1320px;
            margin: 28px auto 56px;
            align-items: start;
            position: relative;
            z-index: 2;
        }

        .portrait-card {
            position: sticky;
            top: 92px;
            background: rgba(11, 19, 29, 0.86);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid <?php echo $rarityAccent; ?>;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 18px 42px rgba(0, 0, 0, 0.75);
        }

        .portrait-stage {
            position: relative;
            background: radial-gradient(circle at 50% 35%, rgba(0, 188, 212, 0.14), rgba(7, 11, 16, 0.96) 78%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 14px;
            min-height: 360px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .portrait-stage img {
            width: 100%;
            max-height: 460px;
            object-fit: contain;
            border-radius: 6px;
            filter: drop-shadow(0 14px 26px rgba(0, 0, 0, 0.85));
            transition: transform 0.15s ease;
            transform-origin: center top;
            cursor: zoom-in;
        }

        .portrait-tag {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(7, 11, 16, 0.88);
            border: 1px solid rgba(0, 188, 212, 0.45);
            color: #38BDF8;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.8px;
            padding: 4px 9px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .zoom-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 11px;
            color: #94A3B8;
        }

        .btn-zoom-reset {
            background: rgba(30, 41, 59, 0.9);
            color: #E2E8F0;
            border: 1px solid #334155;
            border-radius: 4px;
            padding: 4px 10px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.6px;
            cursor: pointer;
            text-transform: uppercase;
        }
        .btn-zoom-reset:hover { border-color: #00bcd4; color: #fff; }

        .dossier-column {
            display: flex;
            flex-direction: column;
            gap: 22px;
            min-width: 0;
        }

        .dossier-panel {
            background: rgba(11, 19, 29, 0.86);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 14px;
            padding: 24px 28px;
            box-shadow: 0 14px 36px rgba(0, 0, 0, 0.65);
        }

        .op-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        .op-title {
            margin: 0;
            font-size: 32px;
            font-weight: 900;
            letter-spacing: 1.5px;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .op-class-chip {
            background: rgba(0, 188, 212, 0.14);
            border: 1px solid rgba(0, 188, 212, 0.5);
            color: #38BDF8;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
            padding: 5px 11px;
            border-radius: 5px;
            text-transform: uppercase;
        }

        .op-stars {
            color: <?php echo $rarityAccent; ?>;
            font-size: 16px;
            letter-spacing: 2px;
            margin: 6px 0 0;
        }

        .promo-selector-wrap {
            margin: 18px 0 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .label-text {
            font-size: 11px;
            color: #94A3B8;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .btn-group {
            display: inline-flex;
            background: #070B10;
            padding: 3px;
            border: 1px solid #2A3B52;
            border-radius: 6px;
        }
        .btn-toggle {
            padding: 7px 18px;
            color: #94A3B8;
            text-decoration: none;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.8px;
            border-radius: 4px;
            transition: 0.18s ease;
            text-transform: uppercase;
        }
        .btn-toggle.active { background: #ffb400; color: #070B10; }
        .btn-toggle:hover:not(.active):not(.disabled) { color: #fff; background: #1E293B; }
        .btn-toggle.disabled { opacity: 0.28; cursor: not-allowed; }
        .e2-indicator {
            background: #ffb400;
            color: #070B10;
            padding: 2px 8px;
            font-size: 12px;
            font-weight: 900;
            border-radius: 4px;
            vertical-align: middle;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-top: 6px;
        }
        .stat-box {
            background: rgba(7, 11, 16, 0.82);
            border: 1px solid #1E2E42;
            border-radius: 8px;
            padding: 12px 10px;
            text-align: center;
        }
        .stat-box span {
            display: block;
            font-size: 10px;
            font-weight: 800;
            color: #64748B;
            letter-spacing: 0.9px;
            margin-bottom: 4px;
        }
        .stat-box strong {
            font-size: 18px;
            font-weight: 800;
            color: #F8FAFC;
        }
        .stat-box.cost-box strong { color: #ffb400; }

        .section-heading {
            margin: 0 0 14px 0;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: #00bcd4;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-heading.gold { color: #ffb400; }

        .skill-card {
            display: flex;
            gap: 16px;
            background: rgba(7, 11, 16, 0.72);
            border: 1px solid #1E2E42;
            border-left: 4px solid #00bcd4;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            align-items: center;
            transition: transform 0.18s ease, border-color 0.18s ease;
        }
        .skill-card:last-child { margin-bottom: 0; }
        .skill-card:hover {
            transform: translateX(3px);
            border-color: rgba(0, 188, 212, 0.55);
            border-left-color: #ffb400;
        }
        .skill-icon img {
            width: 64px;
            height: 64px;
            object-fit: contain;
            background: #070B10;
            border: 1px solid #2A3B52;
            border-radius: 6px;
            padding: 4px;
            display: block;
        }
        .skill-info h5 {
            color: #ffb400;
            margin: 0 0 6px 0;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .skill-info p {
            font-size: 13px;
            color: #CBD5E1;
            margin: 0;
            line-height: 1.55;
        }

        .video-frame {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border-radius: 8px;
            background: #070B10;
            border: 1px solid #1E2E42;
        }
        .video-frame iframe,
        .video-frame video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
            object-fit: contain;
        }

        .bio-box {
            background: rgba(7, 11, 16, 0.72);
            border: 1px solid #1E2E42;
            border-radius: 8px;
            padding: 16px 18px;
            font-size: 14px;
            line-height: 1.7;
            color: #CBD5E1;
        }

        .comment-box {
            background: rgba(7, 11, 16, 0.82);
            border: 1px solid #1E2E42;
            border-left: 3px solid #00B4D8;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        .comment-box:last-child { margin-bottom: 0; }
        .comment-author { color: #38BDF8; font-weight: 800; font-size: 13px; }
        .comment-date { color: #64748B; font-size: 11px; float: right; }
        .comment-text { color: #E2E8F0; font-size: 13px; margin-top: 6px; line-height: 1.55; }

        .comment-form textarea {
            width: 100%;
            padding: 12px 14px;
            background: #070B10;
            border: 1px solid #2A3B52;
            color: #fff;
            border-radius: 6px;
            margin-bottom: 10px;
            height: 86px;
            resize: vertical;
            outline: none;
            font-family: inherit;
        }
        .comment-form textarea:focus { border-color: #00B4D8; }

        .header-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

        @media (max-width: 980px) {
            .details-shell { grid-template-columns: 1fr; }
            .portrait-card { position: static; }
            .stat-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 600px) {
            .main-header { padding: 14px 18px; }
            .dossier-panel { padding: 18px; }
            .op-title { font-size: 24px; }
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body class="details-page">
    <header class="main-header">
        <div class="logo">OPERATOR DOSSIER</div>
        <div class="header-actions">
            <a href="details.php?id=<?php echo $randomId; ?>" class="btn-add" style="background: rgba(255, 180, 0, 0.16); color: #ffb400; border: 1px solid #ffb400;">🎲 RANDOM OP</a>
            <a href="ai_talking.php?id=<?php echo $id; ?>" class="btn-add" style="background: #00bcd4; color: #070B10; font-weight: bold;">💬 AI TALKING</a>
            <?php if ($role === 'admin'): ?>
                <a href="admin_edit.php?id=<?php echo $id; ?>" class="btn-add" style="background: #E59811; color: #070B10;">⚙️ EDIT (ADMIN)</a>
            <?php endif; ?>
            <a href="index.php" class="btn-add">⬅ BACK TO INDEX</a>
        </div>
    </header>

    <main class="details-shell">
        <aside class="portrait-card">
            <div class="portrait-stage" id="zoom-container">
                <span class="portrait-tag"><?php echo $view === 'phase2' ? 'ELITE 2 ARTWORK' : 'STANDARD ARTWORK'; ?></span>
                <img src="<?php echo htmlspecialchars($active_img); ?>" id="operator-img" alt="<?php echo htmlspecialchars($op['name']); ?>">
            </div>
            <div class="zoom-bar">
                <span>Scroll to zoom • Click to toggle 2×</span>
                <button type="button" class="btn-zoom-reset" id="reset-zoom-btn">Reset Zoom</button>
            </div>
        </aside>

        <div class="dossier-column">
            <section class="dossier-panel" style="border-top: 3px solid <?php echo $rarityAccent; ?>;">
                <div class="op-header-top">
                    <div>
                        <h1 class="op-title">
                            <?php echo strtoupper(htmlspecialchars($op['name'])); ?>
                            <?php if ($view === 'phase2'): ?><span class="e2-indicator">E2</span><?php endif; ?>
                        </h1>
                        <div class="op-stars"><?php echo $rarityStars; ?></div>
                    </div>
                    <span class="op-class-chip"><?php echo strtoupper(htmlspecialchars($op['class'] ?? 'OPERATOR')); ?></span>
                </div>

                <div class="promo-selector-wrap">
                    <span class="label-text">PROMOTION</span>
                    <div class="btn-group">
                        <a href="?id=<?php echo $id; ?>&view=phase0" class="btn-toggle <?php echo $view === 'phase0' ? 'active' : ''; ?>">PHASE 0/1</a>
                        <?php if ($has_e2): ?>
                            <a href="?id=<?php echo $id; ?>&view=phase2" class="btn-toggle <?php echo $view === 'phase2' ? 'active' : ''; ?>">PHASE 2</a>
                        <?php else: ?>
                            <span class="btn-toggle disabled">PHASE 2</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="stat-grid">
                    <div class="stat-box"><span>MAX HP</span><strong><?php echo $active_hp; ?></strong></div>
                    <div class="stat-box"><span>ATTACK</span><strong><?php echo $active_atk; ?></strong></div>
                    <div class="stat-box"><span>DEFENSE</span><strong><?php echo $active_def; ?></strong></div>
                    <div class="stat-box"><span>BLOCK</span><strong><?php echo $active_block; ?></strong></div>
                    <div class="stat-box cost-box"><span>DP COST</span><strong><?php echo $active_cost; ?></strong></div>
                </div>
            </section>

            <?php
            $renderedSkills = '';
            for ($i = 1; $i <= 3; $i++) {
                $s_name = $op["s{$i}_name"] ?? '';
                $s_img  = $op["s{$i}_img"]  ?? '';
                $s_desc = $op["s{$i}_desc"] ?? '';
                if (!empty($s_name)) {
                    $renderedSkills .= '<div class="skill-card">'
                        . '<div class="skill-icon"><img src="' . htmlspecialchars($s_img ?: 'https://placeholder.com', ENT_QUOTES, 'UTF-8') . '" alt="skill"></div>'
                        . '<div class="skill-info">'
                        . '<h5>SKILL 0' . $i . ': ' . strtoupper(htmlspecialchars($s_name, ENT_QUOTES, 'UTF-8')) . '</h5>'
                        . '<p>' . nl2br(htmlspecialchars($s_desc, ENT_QUOTES, 'UTF-8')) . '</p>'
                        . '</div></div>';
                }
            }
            if ($renderedSkills !== ''):
            ?>
            <section class="dossier-panel" style="border-left: 4px solid #00bcd4;">
                <h2 class="section-heading">⚡ COMBAT SKILLS</h2>
                <?php echo $renderedSkills; ?>
            </section>
            <?php endif; ?>

            <section class="dossier-panel" style="border-left: 4px solid #E59811;">
                <h2 class="section-heading gold">🎬 VIDEO WIKI ARCHIVE / 干员影像档案</h2>
                <?php if (!empty($embed_video)): ?>
                    <div class="video-frame">
                        <iframe src="<?= htmlspecialchars($embed_video) ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                <?php else: ?>
                    <p style="color: #94A3B8; font-size: 13px; margin: 0;">No YouTube Video Wiki archive linked for this operator.</p>
                <?php endif; ?>
            </section>

            <section class="dossier-panel" style="border-left: 4px solid #00B4D8;">
                <h2 class="section-heading">🎮 WIKI HOW TO PLAY / 实战玩法教学</h2>
                <?php if (!empty($howto_video_html)): ?>
                    <?= $howto_video_html ?>
                <?php else: ?>
                    <p style="color: #94A3B8; font-size: 13px; margin: 0;">No "How to Play" video linked for this operator yet.</p>
                <?php endif; ?>
            </section>

            <section class="dossier-panel" style="border-left: 4px solid #94A3B8;">
                <h2 class="section-heading" style="color: #E2E8F0;">📖 ARCHIVE DATA / BIOGRAPHY</h2>
                <div class="bio-box">
                    <?php echo !empty($op['lore']) ? nl2br(htmlspecialchars($op['lore'])) : "No archive data available."; ?>
                </div>
            </section>

            <section class="dossier-panel" style="border-left: 4px solid #00B4D8;">
                <h2 class="section-heading">💬 DOCTOR DISCUSSIONS & COMMENTS (<?= count($comments) ?>)</h2>

                <?php if ($role !== 'guest'): ?>
                    <form method="POST" class="comment-form" style="margin-bottom: 20px;">
                        <p style="font-size: 12px; color: #94A3B8; margin: 0 0 8px 0;">Posting as: <strong style="color: #E59811;"><?= htmlspecialchars($username) ?></strong></p>
                        <textarea name="comment" placeholder="Share your thoughts on this operator's story or combat tactics..." required></textarea>
                        <button type="submit" name="submit_comment" style="background: #00B4D8; color: #070B10; font-weight: 800; border: none; padding: 9px 18px; border-radius: 5px; cursor: pointer; font-size: 12px; letter-spacing: 0.6px;">POST COMMENT</button>
                    </form>
                <?php else: ?>
                    <div style="background: rgba(7, 11, 16, 0.8); border: 1px dashed #2A3B52; padding: 12px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; color: #94A3B8;">
                        👁️ You are browsing as a <strong>Guest</strong>. <a href="login.php" style="color: #00B4D8; font-weight: bold;">Login as a Normal User</a> to post comments.
                    </div>
                <?php endif; ?>

                <div class="comment-list">
                    <?php if (empty($comments)): ?>
                        <p style="color: #64748B; font-size: 13px; margin: 0;">No comments yet. Be the first Doctor to leave a remark!</p>
                    <?php else: ?>
                        <?php foreach ($comments as $c): ?>
                            <div class="comment-box">
                                <span class="comment-author"><?= htmlspecialchars($c['username']) ?></span>
                                <span class="comment-date"><?= htmlspecialchars($c['created_at']) ?></span>
                                <div class="comment-text"><?= nl2br(htmlspecialchars($c['comment'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const img = document.getElementById('operator-img');
        const resetBtn = document.getElementById('reset-zoom-btn');
        if (!img) return;
        let scale = 1;

        function applyZoom(newScale) {
            scale = Math.max(0.6, Math.min(3, Number(newScale.toFixed(2))));
            img.style.transform = "scale(" + scale + ")";
            img.style.cursor = scale > 1 ? "zoom-out" : "zoom-in";
        }

        img.addEventListener('wheel', function(e) {
            e.preventDefault();
            applyZoom(e.deltaY < 0 ? scale + 0.2 : scale - 0.2);
        }, { passive: false });

        img.addEventListener('click', function() {
            img.style.transition = "transform 0.25s ease";
            applyZoom(scale !== 1 ? 1 : 2);
            setTimeout(() => { img.style.transition = "transform 0.12s ease"; }, 260);
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                img.style.transition = "transform 0.25s ease";
                applyZoom(1);
                setTimeout(() => { img.style.transition = "transform 0.12s ease"; }, 260);
            });
        }
    });
    </script>
</body>
</html>