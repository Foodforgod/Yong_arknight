<?php 
require_once 'db.php'; 


$id = $_GET['id'] ?? 0;
if (!$id) { die("Error: Missing Operator ID."); }


$stmt = $pdo->prepare("SELECT * FROM operators WHERE id = ?");
$stmt->execute([$id]);
$op = $stmt->fetch();

if (!$op) { die("Error: Operator not found."); }


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $name = $_POST['name'];
    $rarity = $_POST['rarity'];
    $class = $_POST['class'];
    $lore = $_POST['lore'];

    
    $img_url = $_POST['image_path'];
    $hp = $_POST['hp'];
    $atk = $_POST['atk'];
    $def = $_POST['def'];
    $block = $_POST['block_count'];
    $cost = $_POST['deploy_cost'];

    
    $img_url_e2 = $_POST['image_path_e2'];
    $hp_e2 = $_POST['hp_e2'] ?: 0;
    $atk_e2 = $_POST['atk_e2'] ?: 0;
    $def_e2 = $_POST['def_e2'] ?: 0;
    $block_e2 = $_POST['block_count_e2'] ?: 0;
    $cost_e2 = $_POST['deploy_cost_e2'] ?: 0;

    
    $s1_name = $_POST['s1_name']; $s1_img = $_POST['s1_img']; $s1_desc = $_POST['s1_desc'];
    $s2_name = $_POST['s2_name']; $s2_img = $_POST['s2_img']; $s2_desc = $_POST['s2_desc'];
    $s3_name = $_POST['s3_name']; $s3_img = $_POST['s3_img']; $s3_desc = $_POST['s3_desc'];

    
    $sql = "UPDATE operators SET 
                name=?, rarity=?, class=?, lore=?,
                image_path=?, hp=?, atk=?, def=?, block_count=?, deploy_cost=?,
                image_path_e2=?, hp_e2=?, atk_e2=?, def_e2=?, block_count_e2=?, deploy_cost_e2=?,
                s1_name=?, s1_img=?, s1_desc=?, s2_name=?, s2_img=?, s2_desc=?, s3_name=?, s3_img=?, s3_desc=?
            WHERE id=?";
    
    $stmt = $pdo->prepare($sql);
    $params = [
        $name, $rarity, $class, $lore,
        $img_url, $hp, $atk, $def, $block, $cost,
        $img_url_e2, $hp_e2, $atk_e2, $def_e2, $block_e2, $cost_e2,
        $s1_name, $s1_img, $s1_desc, $s2_name, $s2_img, $s2_desc, $s3_name, $s3_img, $s3_desc,
        $id
    ];

    if ($stmt->execute($params)) {
        echo "<p style='color: #ffb400; font-weight: bold; text-align:center;'>[SUCCESS] Dossier updated. Redirecting...</p>";
        echo "<script>setTimeout(() => { window.location.href = 'details.php?id=$id'; }, 1500);</script>";
    } else {
        echo "<p style='color: #ff4444; text-align:center;'>[ERROR] Failed to update record.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Dossier: <?php echo htmlspecialchars($op['name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-section { background: rgba(255, 255, 255, 0.03); padding: 15px; margin-bottom: 20px; border-left: 3px solid #444; }
        .form-section.e2 { border-left-color: #ffb400; }
        .section-title { font-size: 11px; color: #888; margin-bottom: 12px; letter-spacing: 1px; text-transform: uppercase; }
        h3 { margin-top: 0; font-size: 16px; color: #fff; text-transform: uppercase; }
        .grid-inputs { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="logo">OPERATOR DATA MODIFICATION</div>
        <a href="details.php?id=<?php echo $id; ?>" class="btn-add">⬅ CANCEL</a>
    </header>

    <div class="form-container">
        <form method="POST">
            
            <div class="form-section">
                <h3>Core Identification</h3>
                <input type="text" name="name" value="<?php echo htmlspecialchars($op['name']); ?>" placeholder="Name" required>
                <div class="grid-inputs">
                    <select name="rarity">
                        <?php for($i=6; $i>=1; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php if($op['rarity'] == $i) echo 'selected'; ?>>
                                <?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <select name="class">
                        <?php 
                        $classes = ['Vanguard', 'Guard', 'Sniper', 'Defender', 'Medic', 'Caster', 'Supporter', 'Specialist'];
                        foreach($classes as $c) {
                            $sel = (strcasecmp($op['class'], $c) == 0) ? 'selected' : '';
                            echo "<option value='$c' $sel>$c</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

           
            <div class="form-section">
                <div class="section-title">Base / Elite 1 Records</div>
                <input type="text" name="image_path" value="<?php echo htmlspecialchars($op['image_path']); ?>" placeholder="Base Image URL" required>
                <div class="grid-inputs">
                    <input type="number" name="hp" value="<?php echo $op['hp']; ?>" placeholder="HP" required>
                    <input type="number" name="atk" value="<?php echo $op['atk']; ?>" placeholder="ATK" required>
                    <input type="number" name="def" value="<?php echo $op['def']; ?>" placeholder="DEF" required>
                    <input type="number" name="block_count" value="<?php echo $op['block_count']; ?>" placeholder="Block" required>
                    <input type="number" name="deploy_cost" value="<?php echo $op['deploy_cost']; ?>" placeholder="Cost" required>
                </div>
            </div>

            
            <div class="form-section e2">
                <div class="section-title" style="color: #ffb400;">Elite 2 Modification (Optional)</div>
                <input type="text" name="image_path_e2" value="<?php echo htmlspecialchars($op['image_path_e2'] ?? ''); ?>" placeholder="Elite 2 Image URL">
                <div class="grid-inputs">
                    <input type="number" name="hp_e2" value="<?php echo $op['hp_e2'] ?? 0; ?>" placeholder="E2 HP">
                    <input type="number" name="atk_e2" value="<?php echo $op['atk_e2'] ?? 0; ?>" placeholder="E2 ATK">
                    <input type="number" name="def_e2" value="<?php echo $op['def_e2'] ?? 0; ?>" placeholder="E2 DEF">
                    <input type="number" name="block_count_e2" value="<?php echo $op['block_count_e2'] ?? 0; ?>" placeholder="E2 Block">
                    <input type="number" name="deploy_cost_e2" value="<?php echo $op['deploy_cost_e2'] ?? 0; ?>" placeholder="E2 Cost">
                </div>
            </div>

            
            <div class="form-section">
                <h3>SKILLS CONFIGURATION</h3>
                <?php for($i=1; $i<=3; $i++): ?>
                    <div style="border-bottom: 1px solid #333; margin-bottom: 10px; padding-bottom: 10px;">
                        <p style="color: #888; font-size: 11px;">SKILL 0<?php echo $i; ?></p>
                        <input type="text" name="s<?php echo $i; ?>_name" placeholder="Skill Name" value="<?php echo htmlspecialchars($op["s{$i}_name"] ?? ''); ?>">
                        <input type="text" name="s<?php echo $i; ?>_img" placeholder="Skill Icon URL" value="<?php echo htmlspecialchars($op["s{$i}_img"] ?? ''); ?>">
                        <textarea name="s<?php echo $i; ?>_desc" placeholder="Skill Description" style="height: 60px;"><?php echo htmlspecialchars($op["s{$i}_desc"] ?? ''); ?></textarea>
                    </div>
                <?php endfor; ?>
            </div>

            
            <div class="form-section">
                <h3>Archive / Biography</h3>
                <textarea name="lore" placeholder="Background story..." style="height: 120px; background: #2a2a2a; color: white; border: 1px solid #333; padding: 10px;"><?php echo htmlspecialchars($op['lore']); ?></textarea>
            </div>
            
            <button type="submit" style="width: 100%; padding: 15px; background: #ffb400; color: #000; font-weight: bold; border: none; cursor: pointer; text-transform: uppercase;">Update Records</button>
        </form>
    </div>
</body>
</html>