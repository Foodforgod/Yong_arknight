<?php
require_once 'db.php';

// Fetch operators present in user inventory
$sql = "SELECT o.* FROM operators o JOIN user_inventory inv ON o.id = inv.operator_id ORDER BY o.rarity DESC";
$stmt = $pdo->query($sql);
$inventory_ops = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Storage Bag - Operator Inventory</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="main-header">
        <div class="logo">STORAGE BAG (INVENTORY)</div>
        <div>
            <a href="gacha.php" class="btn-add" style="background: #ffb400; margin-right: 10px;">🎲 GO TO SPIN</a>
            <a href="index.php" class="btn-add">⬅ BACK TO GALLERY</a>
        </div>
    </header>

    <h1 style="text-align: center; letter-spacing: 3px; color: #fff; margin: 30px 0;">YOUR COLLECTED OPERATORS (LIMIT: 1 EACH)</h1>

    <div class="gallery">
        <?php if (count($inventory_ops) == 0): ?>
            <p style='color: #666; text-align: center; grid-column: 1/-1;'>Your storage bag is empty! Go spin characters in the Gacha page.</p>
        <?php else: ?>
            <?php foreach ($inventory_ops as $row): 
                $borderColor = ($row['rarity'] == 6) ? '#ffb400' : (($row['rarity'] == 5) ? '#e5b1ff' : '#29b6f6');
            ?>
                <div class="card" style="border: 2px solid <?php echo $borderColor; ?>;">
                    <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="operator">
                    <h3 style="margin: 10px 0;"><?php echo strtoupper(htmlspecialchars($row['name'])); ?></h3>
                    <p style="color:#888; font-size:12px; margin-bottom: 5px;">CLASS: <?php echo strtoupper($row['class']); ?></p>
                    <div class="rarity" style="color: <?php echo $borderColor; ?>; margin-bottom: 10px;">
                        <?php echo str_repeat('★', $row['rarity']); ?>
                    </div>
                    
                    <!-- Send chosen character to Galgame mode -->
                    <a href="galgame.php?id=<?php echo $row['id']; ?>" class="btn-add" style="display: block; font-size: 11px; padding: 8px; background: #00bcd4; text-align: center; text-decoration: none; margin-bottom: 8px; color: #000;">
                        💬 CHOOSE FOR GALGAME
                    </a>

                    <!-- Admin Edit Lore & Disposition -->
                    <a href="admin_edit.php?id=<?php echo $row['id']; ?>" style="display: block; font-size: 11px; color: #ffb400; text-align: center; text-decoration: none; border-top: 1px solid #333; padding-top: 8px; font-weight: bold;">
                        ⚙️ EDIT LORE & STATS
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>