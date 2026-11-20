<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 'guest';
$username = $_SESSION['username'] ?? 'Guest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=UTF-8');
    ini_set('display_errors', '0');

    try {
        $action = $_POST['action'];
        $pull_count = ($action === 'ten') ? 10 : 1;

        // Detect whether user_inventory uses quantity or count
        $qtyCol = 'quantity';
        try {
            $colsStmt = $pdo->query("SHOW COLUMNS FROM user_inventory");
            $colNames = array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
            if (!in_array('quantity', $colNames, true) && in_array('count', $colNames, true)) {
                $qtyCol = 'count';
            }
        } catch (Throwable $e) {
            // Default to quantity
        }

        $stmt = $pdo->query("SELECT * FROM operators");
        $operators = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($operators)) {
            echo json_encode(['status' => 'error', 'message' => 'No operators found in operators table!']);
            exit;
        }

        $results = [];
        $hasHighRarityInTen = false;

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

            // Guarantee at least one 5★ or 6★ on the final card of a 10-pull if none appeared yet
            if ($pull_count === 10 && $i === 9 && !$hasHighRarityInTen) {
                $target_rarity = (mt_rand(1, 100) <= 20) ? 6 : 5;
            }

            if ($target_rarity >= 5) {
                $hasHighRarityInTen = true;
            }

            $pool = array_values(array_filter($operators, fn($op) => (int)$op['rarity'] === $target_rarity));
            if (empty($pool)) {
                $pool = $operators;
            }

            $selected_op = $pool[array_rand($pool)];
            $op_id = (int)$selected_op['id'];

            try {
                $check = $pdo->prepare("SELECT * FROM user_inventory WHERE operator_id = ? LIMIT 1");
                $check->execute([$op_id]);
                $existingRow = $check->fetch(PDO::FETCH_ASSOC);

                if ($existingRow) {
                    $up = $pdo->prepare("UPDATE user_inventory SET {$qtyCol} = {$qtyCol} + 1 WHERE operator_id = ?");
                    $up->execute([$op_id]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO user_inventory (operator_id, {$qtyCol}) VALUES (?, 1)");
                    $ins->execute([$op_id]);
                }
            } catch (Throwable $invErr) {
                // Ignore inventory write errors so headhunting UI still displays results
            }

            $results[] = [
                'id'     => $op_id,
                'name'   => (string)$selected_op['name'],
                'rarity' => max(1, min(6, (int)$selected_op['rarity'])),
                'class'  => (string)$selected_op['class'],
                'image'  => !empty($selected_op['image_path_e2']) ? $selected_op['image_path_e2'] : $selected_op['image_path']
            ];
        }

        echo json_encode(['status' => 'success', 'results' => $results]);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

$totalOpsCount = 0;
try {
    $totalOpsCount = (int)$pdo->query("SELECT COUNT(*) FROM operators")->fetchColumn();
} catch (Throwable $e) {
    $totalOpsCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rhodes Island Headhunting — Gacha System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * { box-sizing: border-box; }
        body.gacha-page {
            background-color: #070B10 !important;
            background-image:
                radial-gradient(circle at 20% 15%, rgba(255, 180, 0, 0.12), transparent 45%),
                radial-gradient(circle at 80% 20%, rgba(0, 188, 212, 0.12), transparent 45%),
                linear-gradient(rgba(0, 188, 212, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 188, 212, 0.03) 1px, transparent 1px) !important;
            background-size: 100% 100%, 100% 100%, 28px 28px, 28px 28px !important;
            color: #E2E8F0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            text-align: left;
            margin: 0;
            min-height: 100vh;
        }

        .gacha-shell {
            width: 92%;
            max-width: 860px;
            margin: 34px auto 54px;
            background: rgba(13, 22, 34, 0.92);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 180, 0, 0.45);
            border-top: 3px solid #ffb400;
            border-radius: 14px;
            padding: 32px 34px;
            box-shadow: 0 18px 46px rgba(0, 0, 0, 0.8);
            text-align: center;
        }

        .banner-tag {
            display: inline-block;
            background: rgba(255, 180, 0, 0.14);
            border: 1px solid rgba(255, 180, 0, 0.45);
            color: #ffb400;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.4px;
            padding: 5px 12px;
            border-radius: 999px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .gacha-shell h1 {
            color: #FFFFFF;
            margin: 0 0 8px 0;
            font-size: 28px;
            font-weight: 900;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .gacha-shell h1 span { color: #ffb400; }

        .gacha-subtitle {
            color: #94A3B8;
            font-size: 14px;
            margin: 0 auto 24px;
            max-width: 580px;
            line-height: 1.6;
        }

        .rates-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 26px;
        }

        .rate-chip {
            background: rgba(7, 11, 16, 0.85);
            border: 1px solid #1E2E42;
            border-radius: 8px;
            padding: 10px 8px;
        }
        .rate-chip strong {
            display: block;
            font-size: 15px;
            margin-bottom: 2px;
        }
        .rate-chip span {
            font-size: 11px;
            color: #94A3B8;
            font-weight: 700;
        }
        .rate-6 strong { color: #ff5722; }
        .rate-5 strong { color: #ffb400; }
        .rate-4 strong { color: #38BDF8; }
        .rate-13 strong { color: #CBD5E1; }

        .gacha-buttons {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn-pull {
            padding: 15px 30px;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.8px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-transform: uppercase;
            transition: transform 0.16s ease, filter 0.16s ease, box-shadow 0.16s ease;
        }
        .btn-pull:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
        }
        .btn-single {
            background: linear-gradient(135deg, #0284C7, #00bcd4);
            color: #070B10;
            box-shadow: 0 6px 20px rgba(0, 188, 212, 0.25);
        }
        .btn-ten {
            background: linear-gradient(135deg, #E59811, #ffb400);
            color: #070B10;
            box-shadow: 0 6px 20px rgba(255, 180, 0, 0.28);
        }
        .btn-pull:hover:not(:disabled) {
            filter: brightness(1.1);
            transform: translateY(-2px);
        }

        .gacha-footer-links {
            margin-top: 26px;
            padding-top: 18px;
            border-top: 1px solid #1E2E42;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .gacha-footer-links a {
            color: #38BDF8;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
        }
        .gacha-footer-links a:hover { color: #ffb400; }

        #result-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(5, 8, 13, 0.92);
            backdrop-filter: blur(8px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            padding: 20px;
        }

        .modal-header-row {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 18px;
        }
        .modal-header-row h2 {
            color: #ffb400;
            margin: 0;
            font-size: 22px;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }
        .pull-summary-pill {
            background: rgba(0, 188, 212, 0.14);
            border: 1px solid rgba(0, 188, 212, 0.45);
            color: #38BDF8;
            font-size: 12px;
            font-weight: 800;
            padding: 5px 12px;
            border-radius: 999px;
        }

        .result-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 14px;
            max-width: 980px;
            margin-bottom: 22px;
            max-height: 68vh;
            overflow-y: auto;
            padding: 6px;
        }

        .result-card {
            position: relative;
            background: #111924;
            border: 2px solid #334155;
            border-radius: 10px;
            width: 144px;
            padding: 12px 10px;
            text-align: center;
            animation: popIn 0.35s ease-out;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.65);
        }
        .result-card.rarity-6 {
            border-color: #ff5722;
            box-shadow: 0 0 18px rgba(255, 87, 34, 0.55);
        }
        .result-card.rarity-5 {
            border-color: #ffb400;
            box-shadow: 0 0 14px rgba(255, 180, 0, 0.42);
        }
        .result-card.rarity-4 {
            border-color: #38BDF8;
        }
        .result-card img {
            width: 106px;
            height: 106px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .result-name {
            font-size: 13px;
            font-weight: 800;
            margin-top: 8px;
            color: #FFFFFF;
            word-break: break-word;
        }
        .result-rarity {
            font-size: 11px;
            color: #ffb400;
            margin-top: 4px;
            font-weight: 700;
        }
        .result-class {
            font-size: 10px;
            color: #94A3B8;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        @keyframes popIn {
            0% { transform: scale(0.65); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .btn-close {
            background: #ef4444;
            color: #fff;
            padding: 10px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 800;
            font-size: 12px;
            letter-spacing: 0.7px;
            text-transform: uppercase;
        }
        .btn-bag-link {
            background: #00bcd4;
            color: #070B10;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 800;
            font-size: 12px;
            letter-spacing: 0.7px;
            text-transform: uppercase;
        }

        @media (max-width: 640px) {
            .rates-strip { grid-template-columns: repeat(2, 1fr); }
            .gacha-shell { padding: 22px 18px; }
        }
    </style>
</head>
<body class="gacha-page">
    <header class="main-header">
        <div class="logo">RHODES ISLAND HEADHUNTING</div>
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <span style="background: #132030; color: #38BDF8; border: 1px solid #2A3B52; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 700;">
                👤 <?= htmlspecialchars($username) ?> (<?= strtoupper($role) ?>)
            </span>
            <a href="bag.php" class="btn-add" style="background: #ffb400; color: #070B10;">📦 STORAGE BAG</a>
            <a href="index.php" class="btn-add">⬅ INDEX</a>
        </div>
    </header>

    <main class="gacha-shell">
        <div class="banner-tag">PRTS Signal Scanner • <?= (int)$totalOpsCount ?> Operators in Pool</div>
        <h1>🎯 Arknights <span>Headhunting</span></h1>
        <p class="gacha-subtitle">
            Transmit a recruitment signal to bring operators into Rhodes Island. Recruited personnel are automatically logged into your storage bag.
        </p>

        <div class="rates-strip">
            <div class="rate-chip rate-6"><strong>3%</strong><span>6★ Elite</span></div>
            <div class="rate-chip rate-5"><strong>12%</strong><span>5★ Senior</span></div>
            <div class="rate-chip rate-4"><strong>35%</strong><span>4★ Standard</span></div>
            <div class="rate-chip rate-13"><strong>50%</strong><span>1★–3★ Recruit</span></div>
        </div>

        <div class="gacha-buttons">
            <button type="button" class="btn-pull btn-single" id="btn-single" onclick="executeGacha('single')">🌟 Single Pull</button>
            <button type="button" class="btn-pull btn-ten" id="btn-ten" onclick="executeGacha('ten')">⚡ 10-Pull (5★+ Guaranteed)</button>
        </div>

        <div class="gacha-footer-links">
            <a href="index.php">⬅ Return to Operator Index</a>
            <a href="bag.php">📦 View Storage Bag</a>
        </div>
    </main>

    <div id="result-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal-header-row">
            <h2 id="modal-title">🎉 Recruitment Result</h2>
            <span class="pull-summary-pill" id="pull-summary-pill"></span>
        </div>
        <div class="result-grid" id="result-grid"></div>
        <div class="modal-actions">
            <a href="bag.php" class="btn-bag-link">📦 Open Storage Bag</a>
            <button type="button" class="btn-close" onclick="closeModal()">Confirm</button>
        </div>
    </div>

    <script>
        function setPullButtonsDisabled(disabled) {
            document.getElementById('btn-single').disabled = disabled;
            document.getElementById('btn-ten').disabled = disabled;
        }

        function executeGacha(actionType) {
            setPullButtonsDisabled(true);

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
                    throw new Error('Server returned invalid JSON. Check console for details.');
                }
            })
            .then(data => {
                if (data.status === 'success') {
                    showResults(data.results || []);
                } else {
                    alert('Gacha Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                alert(err.message);
            })
            .finally(() => {
                setPullButtonsDisabled(false);
            });
        }

        function showResults(results) {
            const grid = document.getElementById('result-grid');
            const pill = document.getElementById('pull-summary-pill');
            grid.innerHTML = '';

            const sixCount = results.filter(r => Number(r.rarity) === 6).length;
            const fiveCount = results.filter(r => Number(r.rarity) === 5).length;
            pill.textContent = `${results.length} Pull${results.length === 1 ? '' : 's'} • 6★: ${sixCount} • 5★: ${fiveCount}`;

            results.forEach((op, index) => {
                setTimeout(() => {
                    const card = document.createElement('div');
                    card.className = `result-card rarity-${op.rarity}`;

                    const safeImg = String(op.image || '').replace(/"/g, '&quot;');
                    const safeName = String(op.name || 'Operator');
                    const safeClass = String(op.class || '');
                    const stars = '★'.repeat(Math.max(1, Math.min(6, Number(op.rarity) || 1)));

                    card.innerHTML = `
                        <img src="${safeImg}" alt="${safeName.replace(/"/g, '&quot;')}">
                        <div class="result-name"></div>
                        <div class="result-rarity">${stars}</div>
                        <div class="result-class"></div>
                    `;
                    card.querySelector('.result-name').textContent = safeName;
                    card.querySelector('.result-class').textContent = safeClass;

                    grid.appendChild(card);
                    grid.scrollTop = grid.scrollHeight;
                }, index * 90);
            });

            document.getElementById('result-modal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('result-modal').style.display = 'none';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>