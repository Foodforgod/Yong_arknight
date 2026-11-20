<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 'guest';
$username = $_SESSION['username'] ?? 'Guest';

$sql = "SELECT DISTINCT o.*
        FROM operators o
        JOIN user_inventory inv ON o.id = inv.operator_id
        ORDER BY o.rarity DESC, o.name ASC";
$stmt = $pdo->query($sql);
$inventory_ops = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalUniqueOps = count($inventory_ops);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage Bag - Operator Inventory</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * { box-sizing: border-box; }
        body.bag-page {
            background-color: #0f1112;
            background-image: linear-gradient(135deg, rgba(11, 15, 20, 0.72), rgba(15, 17, 18, 0.84)), url('https://arknights.wiki.gg/images/71_i03.png');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: #E2E8F0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            text-align: left;
            margin: 0;
            min-height: 100vh;
        }

        .bag-shell {
            width: 92%;
            max-width: 1280px;
            margin: 26px auto 52px;
        }

        .bag-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            padding: 18px 24px;
            margin-bottom: 24px;
            background: rgba(13, 22, 34, 0.88);
            backdrop-filter: blur(12px);
            border: 1px solid #1E2E42;
            border-left: 4px solid #ffb400;
            border-radius: 10px;
        }
        .bag-banner h1 {
            margin: 0 0 4px 0;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: 1.6px;
            color: #FFFFFF;
            text-transform: uppercase;
        }
        .bag-banner p {
            margin: 0;
            font-size: 13px;
            color: #94A3B8;
        }
        .metric-pill {
            background: rgba(7, 11, 16, 0.85);
            border: 1px solid rgba(0, 188, 212, 0.45);
            color: #38BDF8;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.7px;
        }

        .bag-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .bag-card {
            position: relative;
            background: rgba(13, 22, 34, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 13px;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.6);
        }
        .bag-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 34px rgba(0, 0, 0, 0.8);
        }

        .bag-thumb {
            position: relative;
            height: 215px;
            border-radius: 7px;
            overflow: hidden;
            background: radial-gradient(circle at 50% 35%, rgba(255, 255, 255, 0.06), #070B10 78%);
            border: 1px solid #1E293B;
        }
        .bag-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: top center;
            display: block;
        }

        .class-badge {
            position: absolute;
            bottom: 8px;
            left: 8px;
            background: rgba(7, 11, 16, 0.88);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: #E2E8F0;
            font-size: 10px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .bag-info {
            padding: 12px 4px 10px;
        }
        .bag-info h3 {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 800;
            color: #FFFFFF;
            letter-spacing: 0.5px;
        }
        .rarity-line {
            font-size: 13px;
            letter-spacing: 1.5px;
        }

        .bag-actions {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .bag-row-btns {
            display: flex;
            gap: 8px;
        }
        .bag-btn {
            flex: 1;
            text-align: center;
            padding: 8px 10px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: filter 0.15s ease, transform 0.15s ease;
        }
        .bag-btn:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }
        .btn-talk {
            background: #00bcd4;
            color: #070B10;
        }
        .btn-dossier {
            background: rgba(0, 188, 212, 0.14);
            border: 1px solid #00bcd4;
            color: #38BDF8;
        }
        .btn-edit-link {
            display: block;
            text-align: center;
            font-size: 11px;
            color: #ffb400;
            text-decoration: none;
            border-top: 1px solid #1E2E42;
            padding-top: 8px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .btn-edit-link:hover { color: #ffffff; }

        .empty-bag {
            grid-column: 1 / -1;
            background: rgba(13, 22, 34, 0.9);
            border: 1px dashed #2A3B52;
            border-radius: 10px;
            padding: 46px 24px;
            text-align: center;
            color: #94A3B8;
        }
        .empty-bag h3 {
            margin: 0 0 8px 0;
            color: #FFFFFF;
        }
    </style>
</head>
<body class="bag-page">
    <header class="main-header">
        <div class="logo">STORAGE BAG (INVENTORY)</div>
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <span style="background: #132030; color: #38BDF8; border: 1px solid #2A3B52; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 700;">
                👤 <?= htmlspecialchars($username) ?> (<?= strtoupper($role) ?>)
            </span>
            <a href="gacha.php" class="btn-add" style="background: #ffb400; color: #070B10;">🎲 GO TO SPIN</a>
            <a href="index.php" class="btn-add">⬅ BACK TO GALLERY</a>
        </div>
    </header>

    <main class="bag-shell">
        <section class="bag-banner">
            <div>
                <h1>Collected Operator Storage</h1>
                <p>Operators recruited from headhunting are ready for neural link chat and dossier review.</p>
            </div>
            <span class="metric-pill"><?= $totalUniqueOps ?> <?= $totalUniqueOps === 1 ? 'OPERATOR' : 'OPERATORS' ?></span>
        </section>

        <div class="bag-grid">
            <?php if (empty($inventory_ops)): ?>
                <div class="empty-bag">
                    <h3>Your Storage Bag Is Empty</h3>
                    <p style="margin: 0 0 18px 0;">Head over to the Headhunting page to recruit operators into your roster.</p>
                    <a href="gacha.php" class="btn-add" style="background: #ffb400; color: #070B10;">🎲 GO TO HEADHUNTING</a>
                </div>
            <?php else: ?>
                <?php foreach ($inventory_ops as $row):
                    $rarity = max(1, min(6, (int)($row['rarity'] ?? 1)));
                    $borderColor = ($rarity === 6) ? '#ffb400' : (($rarity === 5) ? '#e5b1ff' : (($rarity === 4) ? '#29b6f6' : '#64748B'));
                    $imgPath = !empty($row['image_path']) ? $row['image_path'] : ($row['image_path_e2'] ?? '');
                    $opId = (int)$row['id'];
                ?>
                    <article class="bag-card" style="border: 2px solid <?php echo $borderColor; ?>;">
                        <div class="bag-thumb">
                            <span class="class-badge"><?php echo strtoupper(htmlspecialchars($row['class'] ?? 'OPERATOR')); ?></span>
                            <?php if ($imgPath !== ''): ?>
                                <img src="<?php echo htmlspecialchars($imgPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($row['name'] ?? 'Operator', ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                        </div>

                        <div class="bag-info">
                            <h3><?php echo strtoupper(htmlspecialchars($row['name'] ?? 'UNKNOWN')); ?></h3>
                            <div class="rarity-line" style="color: <?php echo $borderColor; ?>;">
                                <?php echo str_repeat('★', $rarity); ?>
                            </div>
                        </div>

                        <div class="bag-actions">
                            <div class="bag-row-btns">
                                <a href="ai_talking.php?id=<?php echo $opId; ?>" class="bag-btn btn-talk">💬 AI TALKING</a>
                                <a href="details.php?id=<?php echo $opId; ?>" class="bag-btn btn-dossier">📄 DOSSIER</a>
                            </div>

                            <?php if ($role === 'admin'): ?>
                                <a href="admin_edit.php?id=<?php echo $opId; ?>" class="btn-edit-link">
                                    ⚙️ EDIT LORE & STATS
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>