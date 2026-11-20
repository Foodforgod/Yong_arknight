<?php
session_start();
require_once 'db.php';

// Ensure operators.deploy_video and attack_video columns exist
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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 1;
$stmt = $pdo->prepare("SELECT * FROM operators WHERE id = ?");
$stmt->execute([$id]);
$op = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$op) {
    die("<div style='color: white; text-align: center; margin-top: 50px;'><h2>Error: Operator not found.</h2><a href='index.php' style='color: #ffb400;'>Return to Index</a></div>");
}

$raw_name = $op['name'] ?? 'Operator';
$safe_char_name = html_entity_decode(htmlspecialchars($raw_name, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');

if (isset($_GET['reset_chat'])) {
    $_SESSION['chat_history'] = [
        ["sender" => "char", "text" => "Ah, Doctor. Our logs have been cleared. What's our next move?"]
    ];
    header("Location: ai_talking.php?id=" . $id);
    exit;
}

if (!isset($_SESSION['ai_talking_char_id']) || $_SESSION['ai_talking_char_id'] != $id) {
    $_SESSION['ai_talking_char_id'] = $id;
    $_SESSION['chat_history'] = [
        ["sender" => "char", "text" => "Oh, hey Doctor! I'm " . $safe_char_name . ". Glad you dropped by. What's on your mind today?"]
    ];
}

if (!isset($_SESSION['ai_mode'])) {
    $_SESSION['ai_mode'] = 'ollama';
}

if (isset($_GET['toggle_mode'])) {
    $_SESSION['ai_mode'] = ($_SESSION['ai_mode'] === 'ollama') ? 'basis' : 'ollama';
    $mode_name = ($_SESSION['ai_mode'] === 'ollama') ? 'Ollama AI Mode' : 'Basis AI (Natural Human) Mode';
    $_SESSION['chat_history'] = [
        ["sender" => "char", "text" => "System notice: Switched engine to " . $mode_name . ". Let's continue, Doctor."]
    ];
    header("Location: ai_talking.php?id=" . $id);
    exit;
}

$is_ollama = ($_SESSION['ai_mode'] === 'ollama');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $user_msg = trim($_POST['user_message'] ?? '');

    $char_name   = $safe_char_name;
    $char_class  = $op['class'];
    $char_rarity = $op['rarity'];
    $char_hp     = $op['hp'];
    $char_atk    = $op['atk'];
    $char_def    = $op['def'];
    $char_lore   = $op['lore'] ?: 'An elite operator of Rhodes Island.';

    if ($action === 'reply' && !empty($user_msg)) {
        $_SESSION['chat_history'][] = ["sender" => "user", "text" => $user_msg];
    }

    $ai_reply = "";

    if ($is_ollama) {
        $system_instructions = "You are roleplaying as {$char_name}, an operator from Arknights.
Profile: Name {$char_name}, Class {$char_class}, Rarity {$char_rarity} Stars. Combat Stats: HP {$char_hp}, ATK {$char_atk}, DEF {$char_def}.
Background Lore / Disposition: \"{$char_lore}\"
Instructions: Reply to the Doctor in a casual, engaging, human-like tone as {$char_name}. Keep replies concise and in character. Never repeat the user's message back.";

        $history_text = "";
        foreach (array_slice($_SESSION['chat_history'], -8) as $msg) {
            $history_text .= ($msg['sender'] === 'user' ? "Doctor: " : "{$char_name}: ") . $msg['text'] . "\n";
        }

        $prompt = ($action === 'auto')
            ? "{$system_instructions}\n\nContinue speaking naturally as {$char_name}.\n{$char_name}:"
            : "{$system_instructions}\n\nConversation History:\n{$history_text}\n{$char_name}:";

        $api_url = "http://localhost:11434/api/generate";
        $payload = json_encode([
            "model" => "llama3",
            "prompt" => $prompt,
            "stream" => false
        ]);

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $http_code === 200) {
            $result = json_decode($response, true);
            if (!empty(trim($result['response'] ?? ''))) {
                $ai_reply = trim($result['response']);
            }
        }

        if (empty($ai_reply)) {
            $ai_reply = "[Ollama Offline]: Cannot reach Ollama at localhost:11434. Switch to Basis AI Mode or start Ollama.";
        }
    } else {
        $lower_msg = strtolower($user_msg);
        if ($action === 'auto') {
            $auto_pool = [
                "I'm keeping a close eye on our tactical layout today. Let me know if you need anything, Doctor.",
                "Just finishing up my equipment checks. What's next on our agenda?",
                "The landship is running smoothly, but I'm always ready for your command."
            ];
            $ai_reply = $auto_pool[array_rand($auto_pool)];
        } elseif (str_contains($lower_msg, 'ability') || str_contains($lower_msg, 'skill') || str_contains($lower_msg, 'power') || str_contains($lower_msg, 'strong')) {
            $ability_pool = [
                "My combat stats speak for themselves — with my training and gear, I can handle almost any hostile threat on the front lines!",
                "You want to test my strength, Doctor? Just point me at the target, and I'll show you what a {$char_class} can do.",
                "My abilities are honed daily through rigorous drills on the landship. I won't let you down!"
            ];
            $ai_reply = $ability_pool[array_rand($ability_pool)];
        } elseif (str_contains($lower_msg, 'name') || str_contains($lower_msg, 'who are you') || str_contains($lower_msg, 'introduce')) {
            $ai_reply = "I'm {$char_name}, a {$char_rarity}-star {$char_class} operator here at Rhodes Island. I'm always at your service, Doctor.";
        } elseif (str_contains($lower_msg, 'stat') || str_contains($lower_msg, 'hp') || str_contains($lower_msg, 'atk') || str_contains($lower_msg, 'fight')) {
            $ai_reply = "If you're checking my numbers, my current combat parameters are locked in and fully optimized for deployment!";
        } elseif (str_contains($lower_msg, 'lore') || str_contains($lower_msg, 'story') || str_contains($lower_msg, 'background')) {
            $ai_reply = "My past is tied to Rhodes Island's mission... {$char_lore}";
        } elseif (str_contains($lower_msg, 'hi') || str_contains($lower_msg, 'hello') || str_contains($lower_msg, 'hey') || str_contains($lower_msg, 'morning')) {
            $hi_pool = [
                "Hey there, Doctor! Glad you took a break from all that paperwork.",
                "Hello! It's always great to catch you when you're taking a breather.",
                "Hi, Doctor! What's on your mind today? I'm all ears."
            ];
            $ai_reply = $hi_pool[array_rand($hi_pool)];
        } else {
            $natural_pool = [
                "That's really interesting! Tell me more about what you're thinking, Doctor.",
                "I see... We should definitely look into that during our next break on the landship.",
                "Haha, you always come up with unique topics, Doctor! I like that about you.",
                "Got it! I'll keep that in mind as we plan our upcoming missions together.",
                "Hmm, that's a good point. Let's make sure we handle it properly."
            ];
            $ai_reply = $natural_pool[array_rand($natural_pool)];
        }
    }

    if (!empty($ai_reply) && !str_starts_with($ai_reply, '[')) {
        $ai_reply = preg_replace('/^(' . preg_quote($char_name, '/') . ':?\s*)/i', '', $ai_reply);
    }

    $_SESSION['chat_history'][] = ["sender" => "char", "text" => $ai_reply];
    echo json_encode(['reply' => $ai_reply, 'history' => $_SESSION['chat_history']]);
    exit;
}

$custom_wallpaper = !empty($op['wallpaper']) ? htmlspecialchars($op['wallpaper'], ENT_QUOTES, 'UTF-8') : '';
$stand_video_url  = trim($op['stage_video'] ?? '');
$deploy_video_url = trim($op['deploy_video'] ?? '');
$attack_video_url = trim($op['attack_video'] ?? '');

$has_stand_video  = (bool)preg_match('/\.(mp4|webm)(\?.*)?$/i', $stand_video_url);
$has_deploy_video = (bool)preg_match('/\.(mp4|webm)(\?.*)?$/i', $deploy_video_url);
$has_attack_video = (bool)preg_match('/\.(mp4|webm)(\?.*)?$/i', $attack_video_url);
$has_any_video    = $has_stand_video || $has_deploy_video || $has_attack_video;

$fallback_img = !empty($op['image_path_e2']) ? $op['image_path_e2'] : $op['image_path'];

$sounds_stmt = $pdo->prepare("SELECT file_path, sound_name FROM operator_sounds WHERE operator_id = ?");
$sounds_stmt->execute([$id]);
$operator_sounds = $sounds_stmt->fetchAll(PDO::FETCH_ASSOC);

$rarity_stars = str_repeat('★', max(1, min(6, intval($op['rarity'] ?? 6))));
$accent_color = $is_ollama ? '#00B4D8' : '#E59811';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Talking Mode - <?php echo htmlspecialchars($safe_char_name, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        * { box-sizing: border-box; }
        body { 
            overflow-x: hidden;
            background-color: #070B10;
            color: #E2E8F0;
            <?php if (!empty($custom_wallpaper)): ?>
            background-image: linear-gradient(135deg, rgba(7, 11, 16, 0.72), rgba(11, 19, 29, 0.85)), url('<?php echo $custom_wallpaper; ?>');
            background-size: cover; 
            background-position: center center; 
            background-attachment: fixed;
            background-repeat: no-repeat;
            <?php endif; ?>
            margin: 0; padding: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 30px;
            background: rgba(11, 19, 29, 0.88);
            backdrop-filter: blur(12px);
            border-bottom: 2px solid <?php echo $accent_color; ?>;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.6);
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            color: #F8FAFC;
            font-size: 15px;
            letter-spacing: 1px;
        }
        .logo-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: <?php echo $accent_color; ?>;
            box-shadow: 0 0 10px <?php echo $accent_color; ?>;
        }
        .logo span { color: #E59811; }
        .header-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-action {
            padding: 8px 14px;
            border-radius: 5px;
            font-weight: 700;
            text-decoration: none;
            font-size: 11px;
            letter-spacing: 0.6px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        .btn-action:hover { transform: translateY(-1px); filter: brightness(1.12); }

        .comms-wrapper {
            width: 92%;
            max-width: 1240px;
            margin: 24px auto;
            display: grid;
            grid-template-columns: 340px minmax(0, 1fr);
            gap: 20px;
            flex: 1;
            align-items: stretch;
        }

        .operator-panel {
            background: rgba(13, 22, 34, 0.86);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(0, 180, 216, 0.45);
            border-top: 3px solid #00B4D8;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.75);
        }
        .ai-stage {
            position: relative;
            flex: 1;
            min-height: 290px;
            background: 
                radial-gradient(circle at 50% 45%, rgba(0, 180, 216, 0.14) 0%, rgba(7, 11, 16, 0.95) 75%),
                linear-gradient(rgba(0, 180, 216, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 180, 216, 0.05) 1px, transparent 1px);
            background-size: 100% 100%, 24px 24px, 24px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            overflow: hidden;
            cursor: pointer;
            user-select: none;
        }
        .ai-stage::before, .ai-stage::after {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            border-color: #E59811;
            border-style: solid;
            pointer-events: none;
            z-index: 3;
        }
        .ai-stage::before { top: 10px; right: 10px; border-width: 2px 2px 0 0; }
        .ai-stage::after { bottom: 10px; left: 10px; border-width: 0 0 2px 2px; }

        .ai-stage video {
            width: 100%;
            height: 100%;
            max-height: 310px;
            object-fit: contain;
            border-radius: 6px;
            filter: drop-shadow(0 10px 22px rgba(0, 0, 0, 0.85));
        }
        .ai-stage img.char-img {
            max-height: 300px;
            max-width: 100%;
            object-fit: contain;
            filter: drop-shadow(0 10px 22px rgba(0, 0, 0, 0.9));
            transition: transform 0.18s ease;
        }
        .ai-stage.attacking img.char-img {
            transform: scale(1.08);
        }
        .stage-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(7, 11, 16, 0.88);
            border: 1px solid rgba(229, 152, 17, 0.7);
            color: #E59811;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 9px;
            border-radius: 4px;
            letter-spacing: 0.8px;
            z-index: 3;
        }
        .stage-hint {
            position: absolute;
            bottom: 10px;
            right: 12px;
            background: rgba(7, 11, 16, 0.78);
            border: 1px solid rgba(0, 180, 216, 0.4);
            color: #94A3B8;
            font-size: 10px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 4px;
            z-index: 3;
        }

        .operator-meta {
            padding: 16px 18px;
            background: rgba(7, 11, 16, 0.92);
            border-top: 1px solid #1E293B;
        }
        .op-rarity { color: #FBBF24; font-size: 13px; letter-spacing: 2px; margin-bottom: 2px; }
        .op-title-row { display: flex; justify-content: space-between; align-items: center; }
        .op-name { font-size: 19px; font-weight: 800; color: #FFFFFF; letter-spacing: 0.5px; }
        .op-class-tag {
            background: rgba(0, 180, 216, 0.15);
            border: 1px solid #00B4D8;
            color: #00B4D8;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .op-mini-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #1E293B;
            text-align: center;
        }
        .mini-stat span { display: block; font-size: 10px; color: #64748B; font-weight: 700; }
        .mini-stat strong { font-size: 13px; color: #E2E8F0; }

        .terminal-panel {
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-width: 0;
        }
        .chat-container {
            flex: 1;
            min-height: 360px;
            max-height: 460px;
            background: rgba(13, 22, 34, 0.88);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-top: 3px solid <?php echo $accent_color; ?>;
            border-radius: 12px;
            padding: 22px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.75);
        }
        .chat-container::-webkit-scrollbar { width: 6px; }
        .chat-container::-webkit-scrollbar-track { background: rgba(7, 11, 16, 0.5); border-radius: 4px; }
        .chat-container::-webkit-scrollbar-thumb { background: #2A3B52; border-radius: 4px; }
        .chat-container::-webkit-scrollbar-thumb:hover { background: #00B4D8; }

        .chat-bubble {
            padding: 14px 18px;
            border-radius: 10px;
            max-width: 78%;
            width: fit-content;
            font-size: 14px;
            line-height: 1.65;
            text-align: left;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            word-break: break-word;
            overflow-wrap: break-word;
            white-space: pre-wrap;
        }
        .chat-bubble.char {
            background: linear-gradient(135deg, rgba(229, 152, 17, 0.16), rgba(30, 41, 59, 0.75));
            border: 1px solid rgba(229, 152, 17, 0.35);
            border-left: 4px solid #E59811;
            align-self: flex-start;
            color: #F1F5F9;
        }
        .chat-bubble.user {
            background: linear-gradient(135deg, rgba(0, 180, 216, 0.2), rgba(15, 23, 42, 0.85));
            border: 1px solid rgba(0, 180, 216, 0.35);
            border-right: 4px solid #00B4D8;
            align-self: flex-end;
            color: #FFFFFF;
        }
        .bubble-name {
            font-size: 11px;
            font-weight: 800;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .chat-bubble.char .bubble-name { color: #E59811; }
        .chat-bubble.user .bubble-name { color: #38BDF8; text-align: right; }

        .chat-controls {
            background: rgba(13, 22, 34, 0.9);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.65);
        }
        .btn-auto {
            background: linear-gradient(90deg, #0284C7, #00B4D8);
            color: #070B10;
            padding: 10px;
            font-weight: 800;
            font-size: 12px;
            letter-spacing: 0.8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-auto:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .chat-input-row { display: flex; gap: 10px; }
        .chat-input-row input {
            flex: 1;
            padding: 13px 16px;
            background: #070B10;
            border: 1px solid #2A3B52;
            color: #fff;
            font-size: 14px;
            border-radius: 6px;
            outline: none;
            transition: border-color 0.2s;
        }
        .chat-input-row input:focus { border-color: #00B4D8; }
        .chat-input-row button {
            padding: 0 28px;
            background: #E59811;
            color: #070B10;
            font-weight: 800;
            font-size: 13px;
            letter-spacing: 0.8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.2s;
        }
        .chat-input-row button:hover { background: #FBBF24; }

        @media (max-width: 860px) {
            .comms-wrapper { grid-template-columns: 1fr; }
            .ai-stage { min-height: 220px; }
            .chat-container { max-height: 340px; }
        }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="logo">
            <div class="logo-dot"></div>
            PRTS NEURAL LINK — <span><?php echo strtoupper(htmlspecialchars($safe_char_name)); ?></span>
        </div>
        <div class="header-actions">
            <a href="ai_talking.php?id=<?php echo $id; ?>&toggle_mode=1" class="btn-action" style="background: <?php echo $accent_color; ?>; color: #070B10;">
                ENGINE: <?php echo $is_ollama ? 'OLLAMA AI' : 'BASIS AI'; ?>
            </a>
            <a href="ai_talking.php?id=<?php echo $id; ?>&reset_chat=1" class="btn-action" style="background: rgba(239, 68, 68, 0.2); border-color: #EF4444; color: #FCA5A5;" onclick="return confirm('Clear chat logs?');">
                RESET
            </a>
            <a href="admin_edit.php?id=<?php echo $id; ?>" class="btn-action" style="background: rgba(229, 152, 17, 0.2); border-color: #E59811; color: #FBBF24;">
                EDIT SUBTITLES
            </a>
            <a href="details.php?id=<?php echo $id; ?>" class="btn-action" style="background: rgba(0, 180, 216, 0.18); border-color: #00B4D8; color: #38BDF8;">
                DOSSIER
            </a>
            <a href="index.php" class="btn-action" style="background: #1E293B; color: #E2E8F0;">
                INDEX
            </a>
        </div>
    </header>

    <div class="comms-wrapper">
        <aside class="operator-panel">
            <div class="ai-stage" id="ai-stage" onclick="triggerAttackAnimation()" title="Click to trigger Attack animation">
                <span class="stage-badge" id="stage-badge">
                    <?php echo $has_deploy_video ? '● DEPLOY' : ($has_stand_video ? '● STAND' : '● STATIC FEED'); ?>
                </span>

                <video id="stage-video" autoplay muted playsinline style="<?php echo $has_any_video ? '' : 'display:none;'; ?>"></video>
                <img src="<?php echo htmlspecialchars($fallback_img, ENT_QUOTES, 'UTF-8'); ?>" alt="Portrait" class="char-img" id="stage-img" style="<?php echo $has_any_video ? 'display:none;' : ''; ?>">

                <span class="stage-hint">CLICK FOR ATTACK</span>
            </div>

            <div class="operator-meta">
                <div class="op-rarity"><?php echo $rarity_stars; ?></div>
                <div class="op-title-row">
                    <div class="op-name"><?php echo htmlspecialchars($safe_char_name); ?></div>
                    <div class="op-class-tag"><?php echo htmlspecialchars($op['class']); ?></div>
                </div>
                <div class="op-mini-stats">
                    <div class="mini-stat"><span>MAX HP</span><strong><?php echo intval($op['hp_e2'] ?: $op['hp']); ?></strong></div>
                    <div class="mini-stat"><span>ATK</span><strong><?php echo intval($op['atk_e2'] ?: $op['atk']); ?></strong></div>
                    <div class="mini-stat"><span>DEF</span><strong><?php echo intval($op['def_e2'] ?: $op['def']); ?></strong></div>
                </div>
            </div>
        </aside>

        <main class="terminal-panel">
            <div class="chat-container" id="chat-box">
                <?php foreach ($_SESSION['chat_history'] as $msg): ?>
                    <div class="chat-bubble <?php echo $msg['sender']; ?>">
                        <div class="bubble-name"><?php echo $msg['sender'] === 'char' ? htmlspecialchars($safe_char_name) : 'Doctor'; ?></div>
                        <div><?php echo htmlspecialchars($msg['text']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="chat-controls">
                <div style="display: flex; gap: 10px;">
                    <button class="btn-auto" style="flex: 1;" onclick="sendAction('auto')">⚡ AUTO-GENERATE CHARACTER DIALOGUE</button>
                    <?php if (!empty($operator_sounds)): ?>
                        <button class="btn-auto" style="flex: 1; background: linear-gradient(90deg, #10B981, #059669); color: #fff;" onclick="playRandomSound()">🔊 RANDOM VOICE LINE</button>
                    <?php endif; ?>
                </div>
                <div class="chat-input-row">
                    <input type="text" id="user-input" placeholder="Transmit message to <?php echo htmlspecialchars($safe_char_name); ?>..." autofocus>
                    <button onclick="sendAction('reply')">SEND</button>
                </div>
            </div>
        </main>
    </div>

    <script>
        const stageEl = document.getElementById('ai-stage');
        const stageVideo = document.getElementById('stage-video');
        const stageImg = document.getElementById('stage-img');
        const stageBadge = document.getElementById('stage-badge');

        const stageAnimations = {
            deploy: <?php echo json_encode($has_deploy_video ? $deploy_video_url : ''); ?>,
            stand:  <?php echo json_encode($has_stand_video ? $stand_video_url : ''); ?>,
            attack: <?php echo json_encode($has_attack_video ? $attack_video_url : ''); ?>
        };

        let currentAnimState = 'stand';
        let attackFallbackTimer = null;

        function setStageState(state) {
            currentAnimState = state;
            if (attackFallbackTimer) {
                clearTimeout(attackFallbackTimer);
                attackFallbackTimer = null;
            }
            stageEl.classList.remove('attacking');

            if (state === 'attack') {
                stageBadge.innerText = '● ATTACK';
                if (stageAnimations.attack) {
                    stageImg.style.display = 'none';
                    stageVideo.style.display = 'block';
                    stageVideo.loop = false;
                    stageVideo.src = stageAnimations.attack;
                    stageVideo.currentTime = 0;
                    stageVideo.play().catch(() => {});
                    return;
                }
                stageEl.classList.add('attacking');
                if (stageAnimations.stand) {
                    stageImg.style.display = 'none';
                    stageVideo.style.display = 'block';
                    stageVideo.loop = true;
                    if (!stageVideo.src.endsWith(stageAnimations.stand)) {
                        stageVideo.src = stageAnimations.stand;
                    }
                    stageVideo.play().catch(() => {});
                } else {
                    stageVideo.style.display = 'none';
                    stageImg.style.display = 'block';
                }
                attackFallbackTimer = setTimeout(() => setStageState('stand'), 900);
                return;
            }

            if (state === 'deploy' && stageAnimations.deploy) {
                stageBadge.innerText = '● DEPLOY';
                stageImg.style.display = 'none';
                stageVideo.style.display = 'block';
                stageVideo.loop = false;
                stageVideo.src = stageAnimations.deploy;
                stageVideo.currentTime = 0;
                stageVideo.play().catch(() => setStageState('stand'));
                return;
            }

            stageBadge.innerText = stageAnimations.stand ? '● STAND' : '● STATIC FEED';
            if (stageAnimations.stand) {
                stageImg.style.display = 'none';
                stageVideo.style.display = 'block';
                stageVideo.loop = true;
                if (!stageVideo.src.endsWith(stageAnimations.stand)) {
                    stageVideo.src = stageAnimations.stand;
                }
                stageVideo.play().catch(() => {});
            } else {
                stageVideo.style.display = 'none';
                stageImg.style.display = 'block';
            }
        }

        stageVideo.addEventListener('ended', function() {
            if (currentAnimState === 'deploy' || currentAnimState === 'attack') {
                setStageState('stand');
            }
        });

        function triggerAttackAnimation() {
            setStageState('attack');
        }

        setStageState(stageAnimations.deploy ? 'deploy' : 'stand');

        const chatBox = document.getElementById('chat-box');
        chatBox.scrollTop = chatBox.scrollHeight;
        const safeCharName = <?php echo json_encode($safe_char_name); ?>;
        
        const operatorSounds = <?php echo json_encode($operator_sounds); ?>;
        let currentAudio = null;

        function playRandomSound() {
            if (!operatorSounds || operatorSounds.length === 0) return;
            
            if (currentAudio) {
                currentAudio.pause();
                currentAudio.currentTime = 0;
            }

            const randomIndex = Math.floor(Math.random() * operatorSounds.length);
            const selectedSound = operatorSounds[randomIndex];

            currentAudio = new Audio(selectedSound.file_path);
            currentAudio.play().catch(e => console.log("Audio play blocked or error:", e));

            let rawSubtitle = selectedSound.sound_name || "Voice line played";
            let subtitleText = rawSubtitle
                .replace(/_/g, ' ')
                .replace(/([a-z])([A-Z])/g, '$1 $2')
                .replace(/([a-z,.!?])([A-Z])/g, '$1 $2')
                .replace(/([,!?])([a-zA-Z])/g, '$1 $2')
                .replace(/Youheard/gi, 'You heard ')
                .replace(/Inesand/gi, 'Ines and ')
                .replace(/Hoedererare/gi, 'Hoederer are ')
                .replace(/actuallygonna/gi, 'actually gonna ')
                .replace(/openashop/gi, 'open a shop ')
                .replace(/inKazdel/gi, 'in Kazdel ')
                .replace(/Iknowtheplaces/gi, 'I know the places ')
                .replace(/theyvebeenlookingat/gi, "they've been looking at ")
                .replace(/theyreallshitheaps/gi, "they're all shitheaps ")
                .replace(/Butiguessmyli/gi, 'But I guess my li')
                .replace(/lrevenanthere/gi, 'lrevenant here ')
                .replace(/couldhelpmopthefloorswithhispartytricks/gi, 'could help mop the floors with his party tricks ')
                .replace(/Hisopinion/gi, 'His opinion ')
                .replace(/Lemmeask/gi, 'Lemme ask ')
                .replace(/Ohheisfi/gi, 'Oh, he is fi')
                .replace(/Nomatter/gi, 'No matter ')
                .replace(/thisplace/gi, 'this place ')
                .replace(/isnever/gi, 'is never ')
                .replace(/nevergonna/gi, 'never gonna ')
                .replace(/gonnaBe/gi, 'gonna be ')
                .replace(/Youknow/gi, 'You know ')
                .replace(/ihate/gi, 'I hate ')
                .replace(/moronswho/gi, 'morons who ')
                .replace(/can'tstop/gi, "can't stop ")
                .replace(/naggingabout/gi, 'nagging about ')
                .replace(/thegoodolddays/gi, 'the good old days ')
                .replace(/Sowhydon'twetalkabout/gi, "So why don't we talk about ")
                .replace(/whattoblowup/gi, 'what to blow up ')
                .replace(/\s+/g, ' ')
                .trim();

            const bubble = document.createElement('div');
            bubble.className = 'chat-bubble char';
            
            const nameDiv = document.createElement('div');
            nameDiv.className = 'bubble-name';
            nameDiv.innerText = safeCharName + ' (Voice Line)';
            
            const textDiv = document.createElement('div');
            textDiv.innerText = ""; 
            
            bubble.appendChild(nameDiv);
            bubble.appendChild(textDiv);
            chatBox.appendChild(bubble);
            chatBox.scrollTop = chatBox.scrollHeight;

            let fullLine = `\"${subtitleText}\"`;
            let charIndex = 0;
            function typeWriter() {
                if (charIndex < fullLine.length) {
                    textDiv.innerText += fullLine.charAt(charIndex);
                    charIndex++;
                    chatBox.scrollTop = chatBox.scrollHeight;
                    setTimeout(typeWriter, 25);
                }
            }
            typeWriter();
        }

        document.getElementById('user-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendAction('reply');
        });

        function sendAction(actionType) {
            const inputField = document.getElementById('user-input');
            const message = inputField.value.trim();
            if (actionType === 'reply' && !message) return;
            if (actionType === 'reply') inputField.value = '';

            const formData = new URLSearchParams();
            formData.append('action', actionType);
            if (actionType === 'reply') formData.append('user_message', message);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                chatBox.innerHTML = '';
                data.history.forEach(msg => {
                    const bubble = document.createElement('div');
                    bubble.className = 'chat-bubble ' + msg.sender;
                    const nameDiv = document.createElement('div');
                    nameDiv.className = 'bubble-name';
                    nameDiv.innerText = msg.sender === 'char' ? safeCharName : 'Doctor';
                    const textDiv = document.createElement('div');
                    textDiv.innerText = msg.text;
                    bubble.appendChild(nameDiv);
                    bubble.appendChild(textDiv);
                    chatBox.appendChild(bubble);
                });
                chatBox.scrollTop = chatBox.scrollHeight;
            })
            .catch(error => { console.error('Error:', error); });
        }
    </script>
</body>
</html>