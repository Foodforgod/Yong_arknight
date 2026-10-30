<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'];
        $pull_count = ($action === 'ten') ? 10 : 1;

        $stmt = $pdo->query("SELECT * FROM operators");
        $operators = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($operators)) {
            echo json_encode(['status' => 'error', 'message' => 'No operators found in operators table!']);
            exit;
        }

        $results = [];

        for ($i = 0; $i < $pull_count; $i++) {
            $rand = mt_rand(1, 100);
            if ($rand <= 3) {
                $target_rarity = 6;
            } elseif ($rand <= 15) {
                $target_rarity = 5;
            } elseif ($rand <= 50) {
                $target_rarity = 4;
            } else {
                $target_rarity = mt_rand(1, 3);
            }

            $pool = array_filter($operators, fn($op) => $op['rarity'] == $target_rarity);
            if (empty($pool)) {
                $pool = $operators;
            }

            $selected_op = $pool[array_rand($pool)];
            $op_id = $selected_op['id'];

            // 检查该干员是否已在 user_inventory 中
            $check = $pdo->prepare("SELECT * FROM user_inventory WHERE operator_id = ?");
            $check->execute([$op_id]);
            
            if ($check->rowCount() > 0) {
                // 已存在，数量 +1（兼容 quantity 或 count 字段）
                try {
                    $up = $pdo->prepare("UPDATE user_inventory SET quantity = quantity + 1 WHERE operator_id = ?");
                    $up->execute([$op_id]);
                } catch (PDOException $e) {
                    try {
                        $up = $pdo->prepare("UPDATE user_inventory SET count = count + 1 WHERE operator_id = ?");
                        $up->execute([$op_id]);
                    } catch (PDOException $e2) {
                        // 忽略错误，防止中断
                    }
                }
            } else {
                // 不存在，插入新记录
                try {
                    $ins = $pdo->prepare("INSERT INTO user_inventory (operator_id, quantity) VALUES (?, 1)");
                    $ins->execute([$op_id]);
                } catch (PDOException $e) {
                    try {
                        $ins = $pdo->prepare("INSERT INTO user_inventory (operator_id, count) VALUES (?, 1)");
                        $ins->execute([$op_id]);
                    } catch (PDOException $e2) {
                        try {
                            $ins = $pdo->prepare("INSERT INTO user_inventory (operator_id) VALUES (?)");
                            $ins->execute([$op_id]);
                        } catch (PDOException $e3) {
                            // 忽略
                        }
                    }
                }
            }

            $results[] = [
                'name' => $selected_op['name'],
                'rarity' => $selected_op['rarity'],
                'class' => $selected_op['class'],
                'image' => !empty($selected_op['image_path_e2']) ? $selected_op['image_path_e2'] : $selected_op['image_path']
            ];
        }

        echo json_encode(['status' => 'success', 'results' => $results]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Advanced Gacha System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #0b0f12;
            color: #fff;
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 20px;
        }
        .gacha-container {
            max-width: 800px;
            margin: 40px auto;
            background: rgba(15, 17, 18, 0.95);
            border: 2px solid #ffb400;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.9);
        }
        h1 { color: #ffb400; text-transform: uppercase; letter-spacing: 2px; }
        .gacha-buttons {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        .btn-pull {
            padding: 15px 30px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-transform: uppercase;
            transition: 0.2s;
        }
        .btn-single { background: #00bcd4; color: #000; }
        .btn-ten { background: #ffb400; color: #000; }
        .btn-pull:hover { background: #fff; transform: scale(1.05); }
        
        #result-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .result-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
            max-width: 900px;
            margin-bottom: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }
        .result-card {
            background: #161b22;
            border: 2px solid #444;
            border-radius: 8px;
            width: 130px;
            padding: 10px;
            text-align: center;
            animation: popIn 0.4s ease-out;
        }
        .result-card.rarity-6 { border-color: #ff4500; box-shadow: 0 0 15px rgba(255,69,0,0.6); }
        .result-card.rarity-5 { border-color: #ffb400; box-shadow: 0 0 10px rgba(255,180,0,0.5); }
        .result-card img { width: 100px; height: 100px; object-fit: contain; }
        .result-name { font-size: 13px; font-weight: bold; margin-top: 8px; color: #fff; }
        .result-rarity { font-size: 12px; color: #ffb400; margin-top: 4px; }
        
        @keyframes popIn {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .btn-close {
            background: #ff4500;
            color: #fff;
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .nav-back { margin-top: 25px; display: inline-block; color: #00bcd4; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

    <div class="gacha-container">
        <h1>🎯 Arknights Headhunting System</h1>
        <p>Recruit elite operators to join Rhodes Island. Try your luck with Single or 10-Pull!</p>
        
        <div class="gacha-buttons">
            <button class="btn-pull btn-single" onclick="executeGacha('single')">🌟 Single Pull</button>
            <button class="btn-pull btn-ten" onclick="executeGacha('ten')">⚡ 10-Pull (Multi)</button>
        </div>

        <div>
            <a href="index.php" class="nav-back">⬅ Return to Gallery</a> | 
            <a href="bag.php" class="nav-back">📦 View Storage Bag</a>
        </div>
    </div>

    <div id="result-modal">
        <h2 style="color: #ffb400; margin-bottom: 20px;">🎉 RECRUITMENT RESULT</h2>
        <div class="result-grid" id="result-grid"></div>
        <button class="btn-close" onclick="closeModal()">CONFIRM</button>
    </div>

    <script>
        function executeGacha(actionType) {
            const formData = new URLSearchParams();
            formData.append('action', actionType);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Raw server response:', text);
                    throw new Error('Server returned invalid JSON. Check F12 Console.');
                }
            })
            .then(data => {
                if (data.status === 'success') {
                    showResults(data.results);
                } else {
                    alert('Gacha Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                alert(err.message);
            });
        }

        function showResults(results) {
            const grid = document.getElementById('result-grid');
            grid.innerHTML = '';

            results.forEach((op, index) => {
                setTimeout(() => {
                    const card = document.createElement('div');
                    card.className = `result-card rarity-${op.rarity}`;
                    card.innerHTML = `
                        <img src="${op.image}" alt="${op.name}">
                        <div class="result-name">${op.name}</div>
                        <div class="result-rarity">${'★'.repeat(op.rarity)} (${op.class})</div>
                    `;
                    grid.appendChild(card);
                }, index * 100);
            });

            document.getElementById('result-modal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('result-modal').style.display = 'none';
        }
    </script>
</body>
</html>