<?php
require_once 'db.php';
session_start();

$role = $_SESSION['role'] ?? 'guest';
$username = $_SESSION['username'] ?? 'Guest';

$search = trim($_GET['search'] ?? '');
$classFilter = trim($_GET['class_filter'] ?? '');
$rarityFilter = isset($_GET['rarity_filter']) && $_GET['rarity_filter'] !== '' ? (int)$_GET['rarity_filter'] : 0;

$sql = "SELECT * FROM operators WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND name LIKE :search";
    $params['search'] = "%$search%";
}
if ($classFilter !== '') {
    $sql .= " AND class = :class";
    $params['class'] = $classFilter;
}
if ($rarityFilter >= 1 && $rarityFilter <= 6) {
    $sql .= " AND rarity = :rarity";
    $params['rarity'] = $rarityFilter;
}

$sql .= " ORDER BY rarity DESC, name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$operators = $stmt->fetchAll(PDO::FETCH_ASSOC);

$randomOpId = 0;
try {
    $randId = $pdo->query("SELECT id FROM operators ORDER BY RAND() LIMIT 1")->fetchColumn();
    if ($randId) {
        $randomOpId = (int)$randId;
    }
} catch (Throwable $e) {
    $randomOpId = !empty($operators) ? (int)$operators[0]['id'] : 0;
}

$accentColor = ($role === 'admin') ? '#ffb400' : '#00B4D8';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rhodes Island Archives</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * { box-sizing: border-box; }
        body#top {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background-color: #0f1112;
            background-image: linear-gradient(135deg, rgba(11, 15, 20, 0.72), rgba(15, 17, 18, 0.84)), url('https://arknights.wiki.gg/images/71_i03.png');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: #E2E8F0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            text-align: left;
        }

        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            padding: 14px 28px;
            background: rgba(11, 19, 29, 0.9);
            backdrop-filter: blur(12px);
            border-bottom: 2px solid <?php echo $accentColor; ?>;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.65);
            position: sticky;
            top: 0;
            z-index: 30;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 1.5px;
            color: #FFFFFF;
        }
        .logo-pulse {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: <?php echo $accentColor; ?>;
            box-shadow: 0 0 10px <?php echo $accentColor; ?>;
        }
        .logo span { color: #ffb400; }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .role-badge {
            background: #132030;
            color: #38BDF8;
            border: 1px solid #2A3B52;
            padding: 7px 12px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 13px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.6px;
            text-decoration: none;
            text-transform: uppercase;
            transition: transform 0.16s ease, filter 0.16s ease;
        }
        .btn-add:hover {
            transform: translateY(-1px);
            filter: brightness(1.12);
        }

        .container-shell {
            width: 92%;
            max-width: 1280px;
            margin: 24px auto 52px;
        }

        .archive-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            padding: 18px 22px;
            margin-bottom: 18px;
            background: rgba(13, 22, 34, 0.88);
            backdrop-filter: blur(12px);
            border: 1px solid #1E2E42;
            border-left: 4px solid #ffb400;
            border-radius: 10px;
        }
        .archive-banner h1 {
            margin: 0 0 4px 0;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #FFFFFF;
        }
        .archive-banner p {
            margin: 0;
            font-size: 13px;
            color: #94A3B8;
        }
        .banner-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-random {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 6px;
            background: rgba(255, 180, 0, 0.14);
            border: 1px solid rgba(255, 180, 0, 0.55);
            color: #ffb400;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.7px;
            text-decoration: none;
            text-transform: uppercase;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .btn-random:hover {
            background: #ffb400;
            color: #070B10;
        }
        .roster-counter {
            background: rgba(0, 188, 212, 0.12);
            border: 1px solid rgba(0, 188, 212, 0.45);
            color: #38BDF8;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.8px;
        }

        .filter-section {
            margin-bottom: 24px;
            background: transparent;
            padding: 0;
            border: none;
        }
        .search-form {
            display: grid;
            grid-template-columns: 1fr 190px 150px auto auto;
            gap: 10px;
            padding: 14px;
            background: rgba(13, 22, 34, 0.88);
            backdrop-filter: blur(12px);
            border: 1px solid #1E2E42;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
        }
        .search-form input,
        .search-form select {
            width: 100%;
            margin: 0;
            padding: 11px 14px;
            background: #070B10;
            border: 1px solid #2A3B52;
            color: #F8FAFC;
            font-size: 13px;
            border-radius: 6px;
            outline: none;
        }
        .search-form input:focus,
        .search-form select:focus {
            border-color: #00bcd4;
        }
        .rarity-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .rarity-pill {
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(7, 11, 16, 0.82);
            border: 1px solid #2A3B52;
            color: #CBD5E1;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .rarity-pill:hover,
        .rarity-pill.active {
            background: #ffb400;
            border-color: #ffb400;
            color: #070B10;
        }
        .btn-search {
            padding: 11px 20px;
            background: #ffb400;
            color: #070B10;
            font-weight: 800;
            font-size: 12px;
            letter-spacing: 0.8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-transform: uppercase;
        }
        .btn-search:hover { background: #ffc93c; }
        .btn-reset {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 16px;
            background: #1E293B;
            color: #CBD5E1;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-decoration: none;
            border-radius: 6px;
            border: 1px solid #334155;
        }
        .btn-reset:hover { background: #334155; color: #FFFFFF; }

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(255px, 1fr));
            gap: 20px;
            padding: 0;
        }

        .card {
            position: relative;
            background: rgba(13, 22, 34, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.55);
            overflow: hidden;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 34px rgba(0, 0, 0, 0.78);
        }

        .card-media {
            position: relative;
            height: 225px;
            border-radius: 7px;
            overflow: hidden;
            background: radial-gradient(circle at 50% 30%, rgba(255, 255, 255, 0.06), #070B10 78%);
            border: 1px solid #1E293B;
        }
        .card-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: top center;
            display: block;
            transition: transform 0.28s ease;
        }
        .card:hover .card-media img {
            transform: scale(1.04);
        }

        .e2-tag {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #ffb400;
            color: #070B10;
            font-size: 10px;
            font-weight: 900;
            padding: 3px 7px;
            border-radius: 3px;
            letter-spacing: 0.6px;
            z-index: 2;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
        }
        .class-pill {
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
            letter-spacing: 0.7px;
            text-transform: uppercase;
            z-index: 2;
        }

        .card-info {
            padding: 12px 4px 6px;
        }
        .card-info h3 {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #FFFFFF;
        }
        .rarity {
            font-size: 13px;
            letter-spacing: 1.5px;
        }

        .card-footer-bar {
            margin-top: auto;
            border-top: 1px solid #1E2E42;
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }
        .btn-ai-talking {
            background: #00bcd4;
            color: #070B10;
            text-decoration: none;
            font-size: 11px;
            font-weight: 800;
            padding: 6px 10px;
            border-radius: 4px;
            letter-spacing: 0.4px;
            transition: background 0.16s ease, transform 0.16s ease;
        }
        .btn-ai-talking:hover {
            background: #ffffff;
            transform: translateY(-1px);
        }
        .dossier-link {
            color: #38BDF8;
            text-decoration: none;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .dossier-link:hover {
            color: #ffb400;
        }

        .empty-box {
            grid-column: 1 / -1;
            background: rgba(13, 22, 34, 0.88);
            border: 1px dashed #2A3B52;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            color: #94A3B8;
        }

        @media (max-width: 900px) {
            .search-form { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            .search-form { grid-template-columns: 1fr; }
            .main-header { padding: 14px 16px; }
        }
    </style>
</head>
<body id="top">
    <header class="main-header">
        <div class="logo">
            <div class="logo-pulse"></div>
            RHODES ISLAND <span>ARCHIVES</span>
        </div>
        <div class="nav-actions">
            <span class="role-badge">👤 <?= htmlspecialchars($username) ?> (<?= strtoupper($role) ?>)</span>
            <a href="gacha.php" class="btn-add" style="background: #ffb400; color: #070B10;">🎲 SPIN & BAG</a>

            <?php if ($role === 'admin'): ?>
                <a href="admin.php" class="btn-add" style="background: #1e293b; color: #ffb400; border: 1px solid #ffb400;">⚙️ ADMIN PORTAL</a>
                <a href="add.php" class="btn-add" style="background: #00B4D8; color: #070B10;">+ RECRUIT OPERATOR</a>
            <?php endif; ?>

            <?php if ($role === 'guest'): ?>
                <a href="login.php" class="btn-add" style="background: #00B4D8; color: #070B10;">🔑 LOGIN</a>
            <?php else: ?>
                <a href="login.php?logout=1" class="btn-add" style="background: #EF4444; color: #fff;">LOGOUT</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="container-shell">
        <section class="archive-banner">
            <div>
                <h1>OPERATOR DATABASE</h1>
                <p>Browse active personnel files, combat dossiers, and AI neural link terminals.</p>
            </div>
            <div class="banner-right">
                <?php if ($randomOpId > 0): ?>
                    <a href="details.php?id=<?= $randomOpId ?>" class="btn-random">🎲 RANDOM OPERATOR</a>
                <?php endif; ?>
                <div class="roster-counter" id="roster-counter">
                    <?= count($operators) ?> <?= count($operators) === 1 ? 'OPERATOR' : 'OPERATORS' ?>
                </div>
            </div>
        </section>

        <section class="filter-section">
            <form method="GET" action="index.php" class="search-form" id="filter-form">
                <input type="text" name="search" id="live-search" placeholder="Search by operator name..."
                       value="<?php echo htmlspecialchars($search); ?>">

                <select name="class_filter" id="live-class">
                    <option value="">ALL CLASSES</option>
                    <?php
                    $classes = ['Vanguard', 'Guard', 'Sniper', 'Defender', 'Medic', 'Supporter', 'Caster', 'Specialist'];
                    foreach ($classes as $c) {
                        $selected = ($classFilter === $c) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . "' $selected>" . strtoupper(htmlspecialchars($c, ENT_QUOTES, 'UTF-8')) . "</option>";
                    }
                    ?>
                </select>

                <select name="rarity_filter" id="live-rarity">
                    <option value="">ALL RARITIES</option>
                    <?php for ($r = 6; $r >= 1; $r--): ?>
                        <option value="<?= $r ?>" <?= ($rarityFilter === $r) ? 'selected' : '' ?>><?= $r ?>★</option>
                    <?php endfor; ?>
                </select>

                <button type="submit" class="btn-search">SEARCH</button>
                <a href="index.php" class="btn-reset">RESET</a>
            </form>

            <div class="rarity-pills" role="group" aria-label="Rarity Filter">
                <button type="button" class="rarity-pill <?= $rarityFilter === 0 ? 'active' : '' ?>" data-rarity="">ALL ★</button>
                <?php for ($r = 6; $r >= 1; $r--): ?>
                    <button type="button" class="rarity-pill <?= $rarityFilter === $r ? 'active' : '' ?>" data-rarity="<?= $r ?>"><?= $r ?>★</button>
                <?php endfor; ?>
            </div>
        </section>

        <div class="gallery" id="operator-gallery">
        <?php
        foreach ($operators as $row) {
            $rarityVal = max(1, min(6, (int)$row['rarity']));
            if ($rarityVal == 6) {
                $borderColor = '#ffb400';
            } elseif ($rarityVal == 5) {
                $borderColor = '#e5b1ff';
            } elseif ($rarityVal == 4) {
                $borderColor = '#29b6f6';
            } else {
                $borderColor = '#666666';
            }

            $hasE2 = !empty($row['image_path_e2']);
            $imgPath = !empty($row['image_path']) ? $row['image_path'] : ($row['image_path_e2'] ?? '');
        ?>
            <div class="card"
                 data-name="<?php echo htmlspecialchars(strtolower($row['name']), ENT_QUOTES, 'UTF-8'); ?>"
                 data-class="<?php echo htmlspecialchars($row['class'], ENT_QUOTES, 'UTF-8'); ?>"
                 data-rarity="<?php echo $rarityVal; ?>"
                 style="border: 2px solid <?php echo $borderColor; ?>;">
                <a href="details.php?id=<?php echo (int)$row['id']; ?>" style="text-decoration: none; color: inherit;">
                    <div class="card-media">
                        <?php if ($hasE2): ?>
                            <span class="e2-tag">E2</span>
                        <?php endif; ?>
                        <span class="class-pill"><?php echo strtoupper(htmlspecialchars($row['class'])); ?></span>
                        <?php if ($imgPath !== ''): ?>
                            <img src="<?php echo htmlspecialchars($imgPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php endif; ?>
                    </div>

                    <div class="card-info">
                        <h3><?php echo strtoupper(htmlspecialchars($row['name'])); ?></h3>
                        <div class="rarity" style="color: <?php echo $borderColor; ?>;">
                            <?php echo str_repeat('★', $rarityVal); ?>
                        </div>
                    </div>
                </a>

                <div class="card-footer-bar">
                    <a href="ai_talking.php?id=<?php echo (int)$row['id']; ?>" class="btn-ai-talking">💬 AI TALKING</a>
                    <a href="details.php?id=<?php echo (int)$row['id']; ?>" class="dossier-link">DOSSIER →</a>
                </div>
            </div>
        <?php } ?>
            <div class="empty-box" id="live-empty-box" style="display: <?php echo count($operators) === 0 ? 'block' : 'none'; ?>;">
                No operators found matching your search or filter.
            </div>
        </div>
    </div>

    <a href="#top" class="back-to-top" title="Return to Top">▲</a>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('live-search');
        const classSelect = document.getElementById('live-class');
        const raritySelect = document.getElementById('live-rarity');
        const pills = document.querySelectorAll('.rarity-pill');
        const cards = document.querySelectorAll('#operator-gallery .card');
        const counter = document.getElementById('roster-counter');
        const emptyBox = document.getElementById('live-empty-box');

        function syncPills(val) {
            pills.forEach(p => {
                p.classList.toggle('active', String(p.dataset.rarity) === String(val));
            });
        }

        function applyLiveFilter() {
            const q = (searchInput ? searchInput.value : '').trim().toLowerCase();
            const cls = classSelect ? classSelect.value : '';
            const rar = raritySelect ? raritySelect.value : '';
            let visible = 0;

            cards.forEach(card => {
                const nameMatch = !q || (card.dataset.name || '').includes(q);
                const classMatch = !cls || (card.dataset.class || '') === cls;
                const rarityMatch = !rar || String(card.dataset.rarity || '') === String(rar);
                const show = nameMatch && classMatch && rarityMatch;
                card.style.display = show ? 'flex' : 'none';
                if (show) visible++;
            });

            if (counter) {
                counter.textContent = `${visible} ${visible === 1 ? 'OPERATOR' : 'OPERATORS'}`;
            }
            if (emptyBox) {
                emptyBox.style.display = visible === 0 ? 'block' : 'none';
            }
        }

        if (searchInput) searchInput.addEventListener('input', applyLiveFilter);
        if (classSelect) classSelect.addEventListener('change', applyLiveFilter);
        if (raritySelect) {
            raritySelect.addEventListener('change', function() {
                syncPills(this.value);
                applyLiveFilter();
            });
        }

        pills.forEach(pill => {
            pill.addEventListener('click', function() {
                const chosen = this.dataset.rarity || '';
                if (raritySelect) raritySelect.value = chosen;
                syncPills(chosen);
                applyLiveFilter();
            });
        });
    });
    </script>
</body>
</html>