<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 'guest';
$username = $_SESSION['username'] ?? 'Guest';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Error: Invalid Operator ID.");
}

try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM operators LIKE 'deploy_video'");
    if ($colCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE operators ADD COLUMN deploy_video VARCHAR(500) NULL DEFAULT NULL AFTER stage_video");
    }
    $colCheckAtk = $pdo->query("SHOW COLUMNS FROM operators LIKE 'attack_video'");
    if ($colCheckAtk->rowCount() === 0) {
        $pdo->exec("ALTER TABLE operators ADD COLUMN attack_video VARCHAR(500) NULL DEFAULT NULL AFTER deploy_video");
    }
} catch (Throwable $e) {
    // Ignore if already exists
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS operator_sounds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        operator_id INT NOT NULL,
        sound_name LONGTEXT NULL,
        file_path VARCHAR(500) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $sndCol = $pdo->query("SHOW COLUMNS FROM operator_sounds LIKE 'sound_name'");
    if ($sndCol->rowCount() > 0) {
        $colInfo = $sndCol->fetch(PDO::FETCH_ASSOC);
        if (stripos($colInfo['Type'] ?? '', 'longtext') === false) {
            $pdo->exec("ALTER TABLE operator_sounds MODIFY COLUMN sound_name LONGTEXT NULL");
        }
    }
} catch (Throwable $e) {
    // Ignore if table exists
}

$stmt = $pdo->prepare("SELECT * FROM operators WHERE id = ?");
$stmt->execute([$id]);
$op = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$op) {
    die("Error: Operator not found.");
}

$soundsStmt = $pdo->prepare("SELECT * FROM operator_sounds WHERE operator_id = ? ORDER BY id ASC");
$soundsStmt->execute([$id]);
$operatorSounds = $soundsStmt->fetchAll(PDO::FETCH_ASSOC);

if (!isset($_SESSION['ai_engine'])) {
    $_SESSION['ai_engine'] = 'ollama';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_engine'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $_SESSION['ai_engine'] = ($_SESSION['ai_engine'] === 'ollama') ? 'basis' : 'ollama';
    echo json_encode(['status' => 'success', 'engine' => $_SESSION['ai_engine']]);
    exit;
}

/**
 * Upgraded Basis AI response generator using operator stats, skills, lore,
 * class role notes, and optional prompt mode ('chat', 'auto', 'combat', 'lore', 'voice').
 */
function getBasisReply(array $op, string $userMsg, string $mode = 'chat'): string {
    $name   = trim((string)($op['name'] ?? 'Operator'));
    $class  = trim((string)($op['class'] ?? 'Operator'));
    $rarity = max(1, min(6, (int)($op['rarity'] ?? 1)));
    $lore   = trim((string)($op['lore'] ?? ''));

    $hp     = (int)($op['hp'] ?? 0);
    $atk    = (int)($op['atk'] ?? 0);
    $def    = (int)($op['def'] ?? 0);
    $block  = (int)($op['block_count'] ?? 1);
    $cost   = (int)($op['deploy_cost'] ?? 0);

    $hpE2   = (int)($op['hp_e2'] ?? 0);
    $atkE2  = (int)($op['atk_e2'] ?? 0);
    $defE2  = (int)($op['def_e2'] ?? 0);

    $skills = [];
    for ($i = 1; $i <= 3; $i++) {
        $sName = trim((string)($op["s{$i}_name"] ?? ''));
        $sDesc = trim((string)($op["s{$i}_desc"] ?? ''));
        if ($sName !== '') {
            $skills[] = [
                'slot' => "Skill 0{$i}",
                'name' => $sName,
                'desc' => $sDesc,
            ];
        }
    }

    $q = mb_strtolower(trim($userMsg));

    // Extract clean opening/snippet from archive lore if available
    $loreSnippet = '';
    if ($lore !== '') {
        $cleanLore = preg_replace('/\s+/', ' ', $lore);
        $sentences = preg_split('/(?<=[.!?。！？])\s+/u', $cleanLore, -1, PREG_SPLIT_NO_EMPTY);
        $loreSnippet = $sentences[0] ?? mb_substr($cleanLore, 0, 170);
        if (mb_strlen($loreSnippet) > 185) {
            $loreSnippet = mb_substr($loreSnippet, 0, 182) . '...';
        }
    }

    $classStyle = [
        'Vanguard'   => ['tone' => 'alert and forward-ready', 'roleNote' => 'securing early deployment points and generating DP before the main wave arrives'],
        'Guard'      => ['tone' => 'steady and frontline-focused', 'roleNote' => 'holding pressure lanes and punishing enemies that step into range'],
        'Sniper'     => ['tone' => 'calm and precise', 'roleNote' => 'picking off high-priority targets and aerial threats from safe elevation'],
        'Defender'   => ['tone' => 'resolute and protective', 'roleNote' => 'anchoring the line and absorbing heavy hits so the squad stays standing'],
        'Medic'      => ['tone' => 'attentive and composed', 'roleNote' => 'monitoring vitals, stabilizing injuries, and keeping frontline operators active'],
        'Caster'     => ['tone' => 'measured and analytical', 'roleNote' => 'breaking through high-armor targets with Arts damage'],
        'Supporter'  => ['tone' => 'observant and tactical', 'roleNote' => 'controlling enemy tempo, slowing advances, and shifting battlefield advantage'],
        'Specialist' => ['tone' => 'adaptable and sharp', 'roleNote' => 'handling unconventional objectives, redeployment timing, and positional shifts'],
    ];
    $style = $classStyle[$class] ?? [
        'tone' => 'focused and ready',
        'roleNote' => 'carrying out orders on the field',
    ];

    $isGreeting = ($mode === 'auto')
        || (bool)preg_match('/\b(hi|hello|hey|greetings|good morning|good afternoon|good evening|yo|sup|greet|auto)\b/i', $q)
        || mb_strpos($q, '你好') !== false;
    $isCombat   = ($mode === 'combat')
        || (bool)preg_match('/\b(skill|attack|atk|def|hp|cost|dp|block|deploy|fight|battle|combat|tactic|strategy|tip|e2|elite)\b/i', $q);
    $isLore     = ($mode === 'lore')
        || (bool)preg_match('/\b(lore|story|past|history|background|who are you|rhodes|origin|about you|archive)\b/i', $q);
    $isVoice    = ($mode === 'voice')
        || (bool)preg_match('/\b(voice|line|quote|say something|greeting line)\b/i', $q);

    // 1. Voice line / quote
    if ($isVoice) {
        if (!empty($skills)) {
            $pick = $skills[array_rand($skills)];
            return "{$name} ({$rarity}★ {$class}, {$style['tone']}): \"Orders received, Doctor. {$pick['name']} is primed—stay sharp out there.\"";
        }
        return "{$name} ({$rarity}★ {$class}, {$style['tone']}): \"{$name} reporting in. Every step on this land counts, Doctor—let's make sure we return safely.\"";
    }

    // 2. Combat / stats / skills
    if ($isCombat) {
        $statSummary = "Base stats: HP {$hp}, ATK {$atk}, DEF {$def}, Block {$block}, DP Cost {$cost}.";
        if ($hpE2 > 0 || $atkE2 > 0 || $defE2 > 0) {
            $statSummary .= " At Elite 2, my output reaches HP {$hpE2}, ATK {$atkE2}, DEF {$defE2}.";
        }
        $skillLine = '';
        if (!empty($skills)) {
            $namesList = implode(', ', array_map(fn($s) => "{$s['slot']}: {$s['name']}", $skills));
            $firstDesc = $skills[0]['desc'] !== ''
                ? ' — ' . mb_substr(preg_replace('/\s+/', ' ', $skills[0]['desc']), 0, 120)
                : '';
            $skillLine = " Available skills: {$namesList}.{$firstDesc}";
        }
        return "As a {$rarity}★ {$class}, I focus on {$style['roleNote']}. {$statSummary}{$skillLine}";
    }

    // 3. Lore / archive background
    if ($isLore) {
        if ($loreSnippet !== '') {
            return "Archive note from {$name}: {$loreSnippet} Even when things get complicated, I keep my footing as a {$class} under Rhodes Island.";
        }
        return "My personnel file at Rhodes Island is brief, Doctor, though my role as a {$rarity}★ {$class} is clear: {$style['roleNote']}. Ask me about my combat stats or skills anytime.";
    }

    // 4. Greeting / Auto check-in
    if ($isGreeting) {
        $opening = $loreSnippet !== '' ? " {$loreSnippet}" : '';
        return "Good to see you, Doctor. I'm {$name}, a {$rarity}★ {$class}—{$style['tone']} and ready for duty.{$opening} What are we looking into today?";
    }

    // 5. Contextual general reply
    $trimmedPrompt = mb_substr(preg_replace('/\s+/', ' ', $userMsg), 0, 120);
    $contextNote = $loreSnippet !== ''
        ? " Keeping my background in mind—{$loreSnippet}"
        : " From my perspective as a {$class},";
    return "You brought up \"{$trimmedPrompt}\", Doctor.{$contextNote} I'm staying {$style['tone']} and focused on {$style['roleNote']}. How would you like to proceed?";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_message'])) {
    header('Content-Type: application/json; charset=UTF-8');
    ini_set('display_errors', '0');

    $userMsg = trim((string)$_POST['chat_message']);
    $mode = trim((string)($_POST['mode'] ?? 'chat'));
    if (!in_array($mode, ['chat', 'auto', 'combat', 'lore', 'voice'], true)) {
        $mode = 'chat';
    }

    if ($userMsg === '') {
        echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty.']);
        exit;
    }

    $engine = $_SESSION['ai_engine'] ?? 'ollama';
    $reply = '';
    $usedSource = 'basis';

    if ($engine === 'ollama') {
        $name = $op['name'] ?? 'Operator';
        $class = $op['class'] ?? 'Operator';
        $rarity = $op['rarity'] ?? '';
        $lore = mb_substr((string)($op['lore'] ?? ''), 0, 600);
        $skillsSummary = implode(', ', array_filter([
            $op['s1_name'] ?? '',
            $op['s2_name'] ?? '',
            $op['s3_name'] ?? ''
        ]));

        $prompt = "You are {$name}, a {$rarity}-star {$class} operator from Arknights at Rhodes Island. "
                . "Reply in character to the Doctor concisely (2-4 sentences).\n"
                . ($skillsSummary !== '' ? "Skills: {$skillsSummary}.\n" : '')
                . ($lore !== '' ? "Background: {$lore}\n" : '')
                . "Doctor says: {$userMsg}\n{$name}:";

        $payload = json_encode([
            'model'  => 'llama3',
            'prompt' => $prompt,
            'stream' => false
        ]);

        $ollamaText = '';
        if (function_exists('curl_init')) {
            $ch = curl_init('http://localhost:11434/api/generate');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 12,
                CURLOPT_CONNECTTIMEOUT => 4
            ]);
            $rawResp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($rawResp !== false && $httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode($rawResp, true);
                if (is_array($decoded) && !empty($decoded['response'])) {
                    $ollamaText = trim((string)$decoded['response']);
                }
            }
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 12
                ]
            ]);
            $rawResp = @file_get_contents('http://localhost:11434/api/generate', false, $ctx);
            if ($rawResp !== false) {
                $decoded = json_decode($rawResp, true);
                if (is_array($decoded) && !empty($decoded['response'])) {
                    $ollamaText = trim((string)$decoded['response']);
                }
            }
        }

        if ($ollamaText !== '') {
            $reply = $ollamaText;
            $usedSource = 'ollama';
        } else {
            $reply = getBasisReply($op, $userMsg, $mode);
            $usedSource = 'basis';
        }
    } else {
        $reply = getBasisReply($op, $userMsg, $mode);
        $usedSource = 'basis';
    }

    echo json_encode([
        'status'        => 'success',
        'reply'         => $reply,
        'engine'        => $engine,
        'active_source' => $usedSource
    ]);
    exit;
}

$activeEngine = $_SESSION['ai_engine'] ?? 'ollama';
$opName = $op['name'] ?? 'Operator';
$opClass = $op['class'] ?? 'Operator';
$opRarity = max(1, min(6, (int)($op['rarity'] ?? 1)));
$portraitImg = !empty($op['image_path_e2']) ? $op['image_path_e2'] : ($op['image_path'] ?? '');
$wallpaperUrl = trim((string)($op['wallpaper'] ?? ''));
if ($wallpaperUrl === '') {
    $wallpaperUrl = 'https://arknights.wiki.gg/images/71_i03.png';
}

$deployVideo = trim((string)($op['deploy_video'] ?? ''));
$stageVideo  = trim((string)($op['stage_video'] ?? ''));
$attackVideo = trim((string)($op['attack_video'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Talking — <?= htmlspecialchars($opName) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        * { box-sizing: border-box; }
        body.ai-talking-page {
            margin: 0;
            min-height: 100vh;
            background-color: #070B10 !important;
            background-image: linear-gradient(135deg, rgba(7, 11, 16, 0.78), rgba(11, 19, 29, 0.9)), url('<?= htmlspecialchars($wallpaperUrl, ENT_QUOTES, 'UTF-8') ?>') !important;
            background-size: cover !important;
            background-position: center center !important;
            background-attachment: fixed !important;
            color: #E2E8F0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            text-align: left;
        }

        .ai-shell {
            width: 92%;
            max-width: 1260px;
            margin: 24px auto 48px;
            display: grid;
            grid-template-columns: minmax(310px, 430px) minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }

        .stage-panel, .chat-panel {
            background: rgba(11, 19, 29, 0.9);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 16px 38px rgba(0, 0, 0, 0.72);
        }

        .stage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .stage-header h2 {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: #FFFFFF;
        }
        .stage-badge {
            background: rgba(0, 188, 212, 0.15);
            border: 1px solid #00bcd4;
            color: #38BDF8;
            font-size: 10px;
            font-weight: 800;
            padding: 4px 9px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .stage-viewport {
            position: relative;
            height: 360px;
            border-radius: 10px;
            overflow: hidden;
            background: radial-gradient(circle at 50% 35%, rgba(0, 188, 212, 0.16), #070B10 80%);
            border: 1px solid #1E2E42;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .stage-viewport img,
        .stage-viewport video {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.25s ease, filter 0.25s ease;
        }
        .stage-viewport.anim-deploy img {
            animation: deployDrop 0.65s ease;
        }
        .stage-viewport.anim-attack img {
            animation: attackLunge 0.45s ease;
        }

        @keyframes deployDrop {
            0% { transform: translateY(-28px) scale(0.92); opacity: 0.4; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }
        @keyframes attackLunge {
            0% { transform: translateX(0) scale(1); }
            45% { transform: translateX(18px) scale(1.06); filter: drop-shadow(0 0 14px rgba(255, 180, 0, 0.7)); }
            100% { transform: translateX(0) scale(1); }
        }

        .stage-controls {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 12px;
        }
        .stage-btn {
            padding: 9px 10px;
            border-radius: 6px;
            border: 1px solid #2A3B52;
            background: #132030;
            color: #E2E8F0;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            text-transform: uppercase;
        }
        .stage-btn:hover { border-color: #00bcd4; color: #fff; }

        .voice-box {
            margin-top: 14px;
            padding: 12px 14px;
            background: rgba(7, 11, 16, 0.78);
            border: 1px solid #1E2E42;
            border-radius: 8px;
        }
        .btn-voice {
            width: 100%;
            padding: 10px;
            background: #ffb400;
            color: #070B10;
            border: none;
            border-radius: 6px;
            font-weight: 800;
            font-size: 12px;
            cursor: pointer;
            text-transform: uppercase;
        }
        .btn-voice:hover { background: #ffc93c; }
        .voice-subtitle {
            margin-top: 8px;
            font-size: 12px;
            color: #F8FAFC;
            min-height: 18px;
            line-height: 1.5;
        }

        .chat-top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding-bottom: 12px;
            border-bottom: 1px solid #1E2E42;
        }
        .engine-status-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .source-pill {
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid #10B981;
            color: #6EE7B7;
        }
        .source-pill.basis {
            background: rgba(255, 180, 0, 0.15);
            border-color: #ffb400;
            color: #ffb400;
        }
        .btn-engine {
            padding: 6px 12px;
            border-radius: 5px;
            border: 1px solid #00bcd4;
            background: rgba(0, 188, 212, 0.14);
            color: #38BDF8;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            text-transform: uppercase;
        }
        .btn-engine:hover { background: #00bcd4; color: #070B10; }

        .prompt-chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 12px 0;
        }
        .prompt-chip {
            padding: 6px 11px;
            border-radius: 999px;
            border: 1px solid #2A3B52;
            background: rgba(7, 11, 16, 0.85);
            color: #CBD5E1;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }
        .prompt-chip:hover {
            border-color: #ffb400;
            color: #ffb400;
        }

        .chat-log {
            height: 330px;
            overflow-y: auto;
            padding: 12px;
            background: rgba(7, 11, 16, 0.82);
            border: 1px solid #1E2E42;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .msg-bubble {
            max-width: 84%;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.55;
        }
        .msg-user {
            align-self: flex-end;
            background: rgba(0, 188, 212, 0.2);
            border: 1px solid rgba(0, 188, 212, 0.5);
            color: #FFFFFF;
        }
        .msg-op {
            align-self: flex-start;
            background: #132030;
            border: 1px solid #2A3B52;
            color: #E2E8F0;
        }
        .msg-meta {
            font-size: 10px;
            font-weight: 800;
            color: #ffb400;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        .chat-input-row {
            display: flex;
            gap: 8px;
        }
        .chat-input-row input {
            flex: 1;
            margin: 0;
            padding: 11px 14px;
            background: #070B10;
            border: 1px solid #2A3B52;
            color: #FFFFFF;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
        }
        .chat-input-row input:focus { border-color: #00bcd4; }
        .btn-send {
            padding: 11px 18px;
            background: #00bcd4;
            color: #070B10;
            border: none;
            border-radius: 6px;
            font-weight: 800;
            font-size: 12px;
            cursor: pointer;
            text-transform: uppercase;
        }
        .btn-auto {
            padding: 11px 14px;
            background: #ffb400;
            color: #070B10;
            border: none;
            border-radius: 6px;
            font-weight: 800;
            font-size: 12px;
            cursor: pointer;
            text-transform: uppercase;
        }

        @media (max-width: 920px) {
            .ai-shell { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="ai-talking-page">
    <header class="main-header">
        <div class="logo">NEURAL LINK — <?= strtoupper(htmlspecialchars($opName)) ?></div>
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <span style="background: #132030; color: #38BDF8; border: 1px solid #2A3B52; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 700;">
                👤 <?= htmlspecialchars($username) ?>
            </span>
            <?php if ($role === 'admin'): ?>
                <a href="admin_edit.php?id=<?= $id ?>" class="btn-add" style="background: #ffb400; color: #070B10;">⚙️ EDIT & VOICE</a>
            <?php endif; ?>
            <a href="details.php?id=<?= $id ?>" class="btn-add" style="background: #00bcd4; color: #070B10;">📄 DOSSIER</a>
            <a href="index.php" class="btn-add">⬅ INDEX</a>
        </div>
    </header>

    <main class="ai-shell">
        <section class="stage-panel">
            <div class="stage-header">
                <h2><?= htmlspecialchars($opName) ?> <span style="color:#ffb400; font-size:13px;"><?= str_repeat('★', $opRarity) ?></span></h2>
                <span class="stage-badge" id="stage-state-badge"><?= strtoupper(htmlspecialchars($opClass)) ?> • STAND</span>
            </div>

            <div class="stage-viewport" id="stage-viewport" onclick="triggerStageAnim('attack')" title="Click to trigger Attack animation">
                <video id="stage-video-player" style="display:none;" autoplay loop muted playsinline></video>
                <img src="<?= htmlspecialchars($portraitImg, ENT_QUOTES, 'UTF-8') ?>" id="stage-portrait" alt="<?= htmlspecialchars($opName, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="stage-controls">
                <button type="button" class="stage-btn" onclick="triggerStageAnim('deploy')">🚀 Deploy</button>
                <button type="button" class="stage-btn" onclick="triggerStageAnim('stand')">🧍 Stand</button>
                <button type="button" class="stage-btn" onclick="triggerStageAnim('attack')">⚔️ Attack</button>
            </div>

            <div class="voice-box">
                <button type="button" class="btn-voice" onclick="playRandomVoice()">🔊 Random Voice Line (<?= count($operatorSounds) ?>)</button>
                <div class="voice-subtitle" id="voice-subtitle">
                    <?= count($operatorSounds) > 0 ? 'Click to play a recorded operator voice clip.' : 'No custom voice clips uploaded yet.' ?>
                </div>
                <audio id="voice-audio" style="display:none;"></audio>
            </div>
        </section>

        <section class="chat-panel">
            <div class="chat-top-bar">
                <div>
                    <h2 style="margin:0; font-size:16px; font-weight:800; color:#FFFFFF;">💬 Operator Comms Terminal</h2>
                </div>
                <div class="engine-status-group">
                    <span class="source-pill <?= $activeEngine === 'basis' ? 'basis' : '' ?>" id="source-indicator">
                        Engine: <?= $activeEngine === 'ollama' ? 'Ollama (llama3)' : 'Basis AI' ?>
                    </span>
                    <button type="button" class="btn-engine" id="btn-toggle-engine" onclick="toggleEngine()">
                        Switch to <?= $activeEngine === 'ollama' ? 'Basis AI' : 'Ollama AI' ?>
                    </button>
                </div>
            </div>

            <div class="prompt-chips">
                <button type="button" class="prompt-chip" onclick="sendQuickPrompt('Give me a quick combat tip for your class.', 'combat')">⚔️ Combat Tip</button>
                <button type="button" class="prompt-chip" onclick="sendQuickPrompt('Tell me a bit about your background and lore.', 'lore')">📖 Background Lore</button>
                <button type="button" class="prompt-chip" onclick="sendQuickPrompt('Share a battle voice line with me.', 'voice')">🎙️ Voice Line</button>
                <button type="button" class="prompt-chip" onclick="sendQuickPrompt('How are you holding up at Rhodes Island today?', 'chat')">☕ Check-in</button>
            </div>

            <div class="chat-log" id="chat-log">
                <div class="msg-bubble msg-op">
                    <div class="msg-meta"><?= htmlspecialchars($opName) ?> • Basis AI</div>
                    <div>Welcome back to the terminal, Doctor. <?= htmlspecialchars($opName) ?> is standing by.</div>
                </div>
            </div>

            <form class="chat-input-row" id="chat-form" onsubmit="handleChatSubmit(event)">
                <input type="text" id="chat-input" placeholder="Send a message to <?= htmlspecialchars($opName) ?>..." autocomplete="off">
                <button type="submit" class="btn-send">Send</button>
                <button type="button" class="btn-auto" onclick="sendAutoDialogue()">⚡ Auto</button>
            </form>
        </section>
    </main>

    <script>
        const stageVideos = {
            deploy: <?= json_encode($deployVideo) ?>,
            stand: <?= json_encode($stageVideo) ?>,
            attack: <?= json_encode($attackVideo) ?>
        };
        const operatorSounds = <?= json_encode($operatorSounds, JSON_UNESCAPED_UNICODE) ?>;
        const opDisplayName = <?= json_encode($opName) ?>;

        function triggerStageAnim(mode) {
            const viewport = document.getElementById('stage-viewport');
            const img = document.getElementById('stage-portrait');
            const vid = document.getElementById('stage-video-player');
            const badge = document.getElementById('stage-state-badge');

            if (badge) {
                badge.textContent = <?= json_encode(strtoupper($opClass)) ?> + ' • ' + mode.toUpperCase();
            }

            const chosenVid = stageVideos[mode] || '';
            if (chosenVid && vid && img) {
                img.style.display = 'none';
                vid.style.display = 'block';
                if (vid.getAttribute('src') !== chosenVid) {
                    vid.src = chosenVid;
                }
                vid.loop = (mode === 'stand');
                vid.play().catch(() => {});
                if (mode !== 'stand') {
                    vid.onended = () => triggerStageAnim('stand');
                }
                return;
            }

            if (vid) {
                vid.pause();
                vid.style.display = 'none';
            }
            if (img && viewport) {
                img.style.display = 'block';
                viewport.classList.remove('anim-deploy', 'anim-attack');
                void viewport.offsetWidth;
                if (mode === 'deploy') viewport.classList.add('anim-deploy');
                if (mode === 'attack') viewport.classList.add('anim-attack');
            }
        }

        function playRandomVoice() {
            const sub = document.getElementById('voice-subtitle');
            const audio = document.getElementById('voice-audio');
            if (!operatorSounds || operatorSounds.length === 0) {
                if (sub) sub.textContent = 'No voice lines configured for this operator.';
                return;
            }
            const pick = operatorSounds[Math.floor(Math.random() * operatorSounds.length)];
            const lineText = pick.sound_name || 'Voice clip playing...';
            if (sub) sub.textContent = `“${lineText}”`;
            if (audio && pick.file_path) {
                audio.src = pick.file_path;
                audio.play().catch(() => {});
            }
        }

        function updateEngineBadge(engine, activeSource) {
            const indicator = document.getElementById('source-indicator');
            const toggleBtn = document.getElementById('btn-toggle-engine');
            const effective = activeSource || engine;

            if (indicator) {
                if (effective === 'ollama') {
                    indicator.textContent = 'Engine: Ollama (llama3)';
                    indicator.classList.remove('basis');
                } else {
                    indicator.textContent = engine === 'ollama' ? 'Engine: Basis AI (Ollama Fallback)' : 'Engine: Basis AI';
                    indicator.classList.add('basis');
                }
            }
            if (toggleBtn) {
                toggleBtn.textContent = engine === 'ollama' ? 'Switch to Basis AI' : 'Switch to Ollama AI';
            }
        }

        function toggleEngine() {
            const fd = new URLSearchParams();
            fd.append('toggle_engine', '1');
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    updateEngineBadge(data.engine, data.engine);
                }
            })
            .catch(() => {});
        }

        function appendBubble(senderLabel, text, isUser) {
            const log = document.getElementById('chat-log');
            if (!log) return;
            const div = document.createElement('div');
            div.className = 'msg-bubble ' + (isUser ? 'msg-user' : 'msg-op');
            const meta = document.createElement('div');
            meta.className = 'msg-meta';
            meta.textContent = senderLabel;
            const body = document.createElement('div');
            body.textContent = text;
            div.appendChild(meta);
            div.appendChild(body);
            log.appendChild(div);
            log.scrollTop = log.scrollHeight;
        }

        function sendMessageText(text, mode = 'chat') {
            const trimmed = String(text || '').trim();
            if (!trimmed) return;

            appendBubble(<?= json_encode($username) ?>, trimmed, true);

            const fd = new URLSearchParams();
            fd.append('chat_message', trimmed);
            fd.append('mode', mode);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    updateEngineBadge(data.engine, data.active_source);
                    const sourceTag = data.active_source === 'ollama' ? 'Ollama AI' : 'Basis AI';
                    appendBubble(`${opDisplayName} • ${sourceTag}`, data.reply, false);
                } else {
                    appendBubble('System', data.message || 'Unable to respond.', false);
                }
            })
            .catch(() => {
                appendBubble('System', 'Network error while contacting comms terminal.', false);
            });
        }

        function handleChatSubmit(e) {
            e.preventDefault();
            const input = document.getElementById('chat-input');
            if (!input) return;
            const val = input.value;
            input.value = '';
            sendMessageText(val, 'chat');
        }

        function sendQuickPrompt(promptText, mode = 'chat') {
            sendMessageText(promptText, mode);
        }

        function sendAutoDialogue() {
            sendMessageText('Auto greeting: report readiness and current status to the Doctor.', 'auto');
        }
    </script>
</body>
</html>