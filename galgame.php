<?php
require_once 'db.php';
session_start();

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM operators WHERE id = ?");
$stmt->execute([$id]);
$op = $stmt->fetch();

if (!$op) {
    die("<div style='color: white; text-align: center; margin-top: 50px;'><h2>Error: No character selected for Galgame mode.</h2><a href='bag.php' style='color: #ffb400;'>Return to Storage Bag</a></div>");
}

$raw_name = $op['name'] ?? 'Operator';
$safe_char_name = html_entity_decode(htmlspecialchars($raw_name, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');

// 处理重置聊天记录
if (isset($_GET['reset_chat'])) {
    $_SESSION['chat_history'] = [
        ["sender" => "char", "text" => "Ah, Doctor. Our logs have been cleared. What's our next move?"]
    ];
    header("Location: galgame.php?id=" . $id);
    exit;
}

if (!isset($_SESSION['galgame_char_id']) || $_SESSION['galgame_char_id'] != $id) {
    $_SESSION['galgame_char_id'] = $id;
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
    header("Location: galgame.php?id=" . $id);
    exit;
}

$is_ollama = ($_SESSION['ai_mode'] === 'ollama');

// AJAX 请求处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $user_msg = trim($_POST['user_message'] ?? '');

    $char_name  = $safe_char_name;
    $char_class = $op['class'];
    $char_rarity= $op['rarity'];
    $char_hp    = $op['hp'];
    $char_atk   = $op['atk'];
    $char_def   = $op['def'];
    $char_lore  = $op['lore'] ?: 'An elite operator of Rhodes Island.';

    if ($action === 'reply' && !empty($user_msg)) {
        $_SESSION['chat_history'][] = ["sender" => "user", "text" => $user_msg];
    }

    $ai_reply = "";

    // ---------------------------------------------------------------------
    // 模式一：Ollama 智能本地大模型驱动
    // ---------------------------------------------------------------------
    if ($is_ollama) {
        $system_instructions = "You are roleplaying as {$char_name}, an operator from Arknights.
Profile: Name {$char_name}, Class {$char_class}, Rarity {$char_rarity} Stars. Combat Stats: HP {$char_hp}, ATK {$char_atk}, DEF {$char_def}.
Background Lore / Disposition: \"{$char_lore}\"
Instructions: Reply to the Doctor in a casual, engaging, human-like tone as {$char_name}. Keep replies concise, engaging, and in character. Never repeat the user's message back.";

        $history_text = "";
        foreach (array_slice($_SESSION['chat_history'], -8) as $msg) {
            $history_text .= ($msg['sender'] === 'user' ? "Doctor: " : "{$char_name}: ") . $msg['text'] . "\n";
        }

        $prompt = ($action === 'auto')
            ? "{$system_instructions}\n\nContinue speaking or monologuing naturally on your own as {$char_name}.\n{$char_name}:"
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
            $ai_reply = "[Ollama Error]: Cannot reach Ollama at localhost:11434. Please switch to Basis AI Mode.";
        }
    } 
    // ---------------------------------------------------------------------
    // 模式二：Basis AI 绝对真人化、拒绝复读的智能离线匹配模式
    // ---------------------------------------------------------------------
    else {
        $lower_msg = strtolower($user_msg);

        if ($action === 'auto') {
            $auto_pool = [
                "I'm keeping a close eye on our tactical layout today. Let me know if you need anything, Doctor.",
                "Just finishing up my equipment checks. What's next on our agenda?",
                "The landship is running smoothly, but I'm always ready for your command."
            ];
            $ai_reply = $auto_pool[array_rand($auto_pool)];
        } 
        // 1. 询问能力 / 技能 / 强不强
        elseif (str_contains($lower_msg, 'ability') || str_contains($lower_msg, 'skill') || str_contains($lower_msg, 'power') || str_contains($lower_msg, 'strong')) {
            $ability_pool = [
                "My combat stats speak for themselves — with my training and gear, I can handle almost any hostile threat on the front lines!",
                "You want to test my strength, Doctor? Just point me at the target, and I'll show you what a {$char_class} can do.",
                "My abilities are honed daily through rigorous drills on the landship. I won't let you down!"
            ];
            $ai_reply = $ability_pool[array_rand($ability_pool)];
        } 
        // 2. 询问名字 / 是谁
        elseif (str_contains($lower_msg, 'name') || str_contains($lower_msg, 'who are you') || str_contains($lower_msg, 'introduce')) {
            $ai_reply = "I'm {$char_name}, a {$char_rarity}-star {$char_class} operator here at Rhodes Island. I'm always at your service, Doctor.";
        } 
        // 3. 询问战力属性 (HP / ATK)
        elseif (str_contains($lower_msg, 'stat') || str_contains($lower_msg, 'hp') || str_contains($lower_msg, 'atk') || str_contains($lower_msg, 'fight')) {
            $ai_reply = "If you're checking my numbers, my current combat parameters are locked in and fully optimized for deployment!";
        } 
        // 4. 询问背景 / 故事
        elseif (str_contains($lower_msg, 'lore') || str_contains($lower_msg, 'story') || str_contains($lower_msg, 'background')) {
            $ai_reply = "My past is tied to Rhodes Island's mission... {$char_lore}";
        } 
        // 5. 问候语 (Hi / Hello)
        elseif (str_contains($lower_msg, 'hi') || str_contains($lower_msg, 'hello') || str_contains($lower_msg, 'hey') || str_contains($lower_msg, 'morning')) {
            $hi_pool = [
                "Hey there, Doctor! Glad you took a break from all that paperwork.",
                "Hello! It's always great to catch you when you're taking a breather.",
                "Hi, Doctor! What's on your mind today? I'm all ears."
            ];
            $ai_reply = $hi_pool[array_rand($hi_pool)];
        } 
        // 6. 其他任何随机提问（纯真人日常对话风格，绝对不复读！）
        else {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Galgame Interactive Mode - <?php echo htmlspecialchars($safe_char_name, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { 
            overflow-x: hidden; 
            background-color: #0b0f12;
            <?php if (!empty($custom_wallpaper)): ?>
            background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.7)), url('<?php echo $custom_wallpaper; ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            <?php endif; ?>
            margin: 0;
            padding: 0;
        }
        .main-header { margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; background: rgba(0,0,0,0.8); flex-wrap: wrap; gap: 10px; }
        .logo { font-weight: bold; color: #ffb400; font-size: 16px; }
        .ollama-banner {
            background: linear-gradient(90deg, #1e3c72, #2a5298);
            color: #fff;
            padding: 8px 20px;
            text-align: center;
            font-size: 13px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #00bcd4;
        }
        .ollama-banner a {
            background: #ffb400;
            color: #000;
            padding: 4px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            font-size: 12px;
        }
        .ollama-banner a:hover { background: #fff; }
        .galgame-stage {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 18vh;
            position: relative;
        }
        .galgame-char-img {
            max-height: 170px;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.9));
            transition: 0.3s;
        }
        .chat-container {
            width: 85%;
            max-width: 950px;
            height: 330px;
            background: rgba(15, 17, 18, 0.95);
            border: 2px solid <?php echo $is_ollama ? '#4caf50' : '#ff9800'; ?>;
            border-radius: 10px;
            padding: 20px;
            text-align: left;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.9);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .chat-bubble {
            padding: 14px 18px;
            border-radius: 8px;
            max-width: 80%;
            font-size: 15px;
            line-height: 1.6;
            color: #ffffff;
        }
        .chat-bubble.char {
            background: rgba(255, 180, 0, 0.18);
            border-left: 4px solid #ffb400;
            align-self: flex-start;
        }
        .chat-bubble.user {
            background: rgba(0, 188, 212, 0.18);
            border-right: 4px solid #00bcd4;
            align-self: flex-end;
            text-align: right;
        }
        .bubble-name {
            font-size: 12px;
            color: #ffb400;
            font-weight: bold;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .chat-controls {
            width: 85%;
            max-width: 950px;
            margin: 15px auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .chat-input-row {
            display: flex;
            gap: 10px;
        }
        .chat-input-row input {
            flex: 1;
            padding: 14px;
            background: #111;
            border: 1px solid #444;
            color: #fff;
            font-size: 15px;
            border-radius: 6px;
            outline: none;
        }
        .chat-input-row button {
            padding: 0 25px;
            background: #ffb400;
            color: #000;
            font-weight: bold;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 14px;
        }
        .chat-input-row button:hover { background: #fff; }
        .btn-auto {
            background: #29b6f6 !important;
            color: #000 !important;
            padding: 12px;
            font-weight: bold;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 13px;
        }
        .btn-auto:hover { background: #fff !important; }
        .btn-mode-toggle {
            background: <?php echo $is_ollama ? '#4caf50' : '#ff9800'; ?> !important;
            color: #fff !important;
            margin-right: 5px;
            font-weight: bold;
        }
        .btn-reset {
            background: #e91e63 !important;
            color: #fff !important;
            margin-right: 5px;
            font-weight: bold;
        }
        .btn-add { padding: 8px 15px; border-radius: 5px; text-decoration: none; color: #fff; font-size: 13px; }
    </style>
</head>
<body>
    <div class="ollama-banner">
        <span>💡 <strong>Recommended:</strong> Install Ollama for full AI experience.</span>
        <a href="https://ollama.com" target="_blank">📥 Download Ollama</a>
    </div>

    <header class="main-header">
        <div class="logo">GALGAME DUAL-MODE - <?php echo htmlspecialchars($safe_char_name, ENT_QUOTES, 'UTF-8'); ?></div>
        <div>
            <a href="galgame.php?id=<?php echo $id; ?>&toggle_mode=1" class="btn-add btn-mode-toggle" title="Click to switch engine">
                🤖 MODE: <?php echo $is_ollama ? '🟢 OLLAMA AI' : '🟠 BASIS AI'; ?>
            </a>
            <a href="galgame.php?id=<?php echo $id; ?>&reset_chat=1" class="btn-add btn-reset" onclick="return confirm('Reset chat history?');">🔄 RESET</a>
            <a href="bag.php" class="btn-add" style="background: #ffb400; margin-right: 5px;">📦 BAG</a>
            <a href="index.php" class="btn-add" style="background: #444;">⬅ EXIT</a>
        </div>
    </header>

    <div class="galgame-stage">
        <img src="<?php echo htmlspecialchars(!empty($op['image_path_e2']) ? $op['image_path_e2'] : $op['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Character Portrait" class="galgame-char-img" id="char-img">
    </div>

    <div class="chat-container" id="chat-box">
        <?php foreach ($_SESSION['chat_history'] as $msg): ?>
            <div class="chat-bubble <?php echo $msg['sender']; ?>">
                <div class="bubble-name"><?php echo $msg['sender'] === 'char' ? htmlspecialchars($safe_char_name, ENT_QUOTES, 'UTF-8') : 'Doctor'; ?></div>
                <div><?php echo htmlspecialchars($msg['text'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="chat-controls">
        <button class="btn-auto" onclick="sendAction('auto')">⚡ AUTO-GENERATE CHARACTER DIALOGUE</button>
        <div class="chat-input-row">
            <input type="text" id="user-input" placeholder="Type a reply to talk with <?php echo htmlspecialchars($safe_char_name, ENT_QUOTES, 'UTF-8'); ?>..." autofocus>
            <button onclick="sendAction('reply')">SEND</button>
        </div>
    </div>

    <script>
        const chatBox = document.getElementById('chat-box');
        chatBox.scrollTop = chatBox.scrollHeight;
        const safeCharName = <?php echo json_encode($safe_char_name); ?>;

        document.getElementById('user-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendAction('reply');
            }
        });

        function typeWriter(element, text, i = 0, speed = 25, callback) {
            if (i < text.length) {
                element.innerHTML += text.charAt(i);
                chatBox.scrollTop = chatBox.scrollHeight;
                setTimeout(() => typeWriter(element, text, i + 1, speed, callback), speed);
            } else if (callback) {
                callback();
            }
        }

        function sendAction(actionType) {
            const inputField = document.getElementById('user-input');
            const message = inputField.value.trim();

            if (actionType === 'reply' && !message) return;
            if (actionType === 'reply') inputField.value = '';

            const formData = new URLSearchParams();
            formData.append('action', actionType);
            if (actionType === 'reply') {
                formData.append('user_message', message);
            }

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                chatBox.innerHTML = '';
                const history = data.history;

                for (let idx = 0; idx < history.length - 1; idx++) {
                    const msg = history[idx];
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
                }

                const lastMsg = history[history.length - 1];
                const bubble = document.createElement('div');
                bubble.className = 'chat-bubble ' + lastMsg.sender;
                
                const nameDiv = document.createElement('div');
                nameDiv.className = 'bubble-name';
                nameDiv.innerText = safeCharName;
                
                const textDiv = document.createElement('div');
                
                bubble.appendChild(nameDiv);
                bubble.appendChild(textDiv);
                chatBox.appendChild(bubble);

                typeWriter(textDiv, lastMsg.text);
                chatBox.scrollTop = chatBox.scrollHeight;
            })
            .catch(error => { console.error('Error:', error); });
        }
    </script>
</body>
</html>