<?php require_once 'db.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rhodes Island Archives</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .e2-tag {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ffb400;
            color: #000;
            font-size: 10px;
            font-weight: 900;
            padding: 2px 5px;
            border-radius: 2px;
        }
        .card { position: relative; transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); }
    </style>
</head>
<body id="top">
    <header class="main-header">
        <div class="logo">RHODES ISLAND ARCHIVES</div>
        <div class="nav-actions">
            <a href="add.php" class="btn-add">+ RECRUIT OPERATOR</a>
        </div>
    </header>

    <section class="filter-section">
        <form method="GET" action="index.php" class="search-form">
            <input type="text" name="search" placeholder="Search by name..." 
                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            
            <select name="class_filter">
                <option value="">ALL CLASSES</option>
                <?php
                $classes = ['Vanguard', 'Guard', 'Sniper', 'Defender', 'Medic', 'Supporter', 'Caster', 'Specialist'];
                foreach ($classes as $c) {
                    $selected = (isset($_GET['class_filter']) && $_GET['class_filter'] == $c) ? 'selected' : '';
                    echo "<option value='$c' $selected>" . strtoupper($c) . "</option>";
                }
                ?>
            </select>
            <button type="submit" class="btn-search">SEARCH</button>
            <a href="index.php" class="btn-reset">RESET</a>
        </form>
    </section>
   
    <h1 style="text-align: center; letter-spacing: 3px; color: #fff; margin: 30px 0;">OPERATOR DATABASE</h1>
    
    <div class="gallery">
    <?php
    $search = $_GET['search'] ?? '';
    $classFilter = $_GET['class_filter'] ?? '';

   
    $sql = "SELECT * FROM operators WHERE 1=1";
    $params = [];

    if ($search != '') {
        $sql .= " AND name LIKE :search";
        $params['search'] = "%$search%"; 
    }
    if ($classFilter != '') {
        $sql .= " AND class = :class";
        $params['class'] = $classFilter;
    }

    $sql .= " ORDER BY rarity DESC, name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() == 0) {
        echo "<p style='color: #666; text-align: center; grid-column: 1/-1;'>No operators found matching your search.</p>";
    }

    while ($row = $stmt->fetch()) {
        
       
        if ($row['rarity'] == 6) {
            $borderColor = '#ffb400'; 
        } elseif ($row['rarity'] == 5) {
            $borderColor = '#e5b1ff'; 
        } elseif ($row['rarity'] == 4) {
            $borderColor = '#29b6f6'; 
        } else {
            $borderColor = '#666666'; 
        }
        
        $hasE2 = !empty($row['image_path_e2']);
    ?>
        <div class="card" style="border: 2px solid <?php echo $borderColor; ?>;">
            <?php if($hasE2): ?>
                <span class="e2-tag">E2</span>
            <?php endif; ?>

            <a href="details.php?id=<?php echo $row['id']; ?>" style="text-decoration: none; color: inherit;">
                <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="operator">
                <h3 style="margin: 10px 0;"><?php echo strtoupper(htmlspecialchars($row['name'])); ?></h3>
                <p style="color:#888; font-size:12px; margin-bottom: 5px;">CLASS: <?php echo strtoupper($row['class']); ?></p>
                <div class="rarity" style="color: <?php echo $borderColor; ?>;">
                    <?php echo str_repeat('★', $row['rarity']); ?>
                </div>
            </a>

            <a href="delete.php?id=<?php echo $row['id']; ?>" 
               onclick="return confirm('Are you sure you want to dismiss this operator?')" 
               style="display: block; color: #ff4444; text-decoration: none; font-size: 11px; margin-top: 15px; border-top: 1px solid #333; padding-top: 10px; font-weight: bold;">
               [ DISMISS OPERATOR ]
            </a>
        </div>
    <?php } ?>
    </div>
    
    <a href="#top" class="back-to-top" title="Return to Top">▲</a>
</body>
</html>