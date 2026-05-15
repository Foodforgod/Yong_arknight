<?php require_once 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Management - Rhodes Island</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-section { background: rgba(255, 255, 255, 0.03); padding: 15px; margin-bottom: 20px; border-left: 3px solid #444; }
        .form-section.e2 { border-left-color: #ffb400; }
        .section-title { font-size: 12px; color: #888; margin-bottom: 15px; letter-spacing: 1px; }
        h3 { margin-top: 0; font-size: 16px; color: #fff; }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="logo">HR MANAGEMENT: NEW RECRUIT</div>
        <a href="index.php" class="btn-add">⬅ BACK TO GALLERY</a>
    </header>

    <div class="form-container">
        <?php
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

            if (!empty($name) && !empty($img_url)) {
                
                $sql = "INSERT INTO operators (
                            name, rarity, class, lore,
                            image_path, hp, atk, def, block_count, deploy_cost,
                            image_path_e2, hp_e2, atk_e2, def_e2, block_count_e2, deploy_cost_e2,
                            s1_name, s1_img, s1_desc, s2_name, s2_img, s2_desc, s3_name, s3_img, s3_desc
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $params = [
                    $name, $rarity, $class, $lore,
                    $img_url, $hp, $atk, $def, $block, $cost,
                    $img_url_e2, $hp_e2, $atk_e2, $def_e2, $block_e2, $cost_e2,
                    $s1_name, $s1_img, $s1_desc, $s2_name, $s2_img, $s2_desc, $s3_name, $s3_img, $s3_desc
                ];

                if ($stmt->execute($params)) {
                    echo "<p style='color: #ffb400; font-weight: bold;'>[SUCCESS] Operator files archived.</p>";
                    echo "<script>setTimeout(() => { window.location.href = 'index.php'; }, 1500);</script>";
                }
            } else {
                echo "<p style='color: #ff4444;'>[ERROR] Name and Base Image are required.</p>";
            }
        }
        ?>

        <form method="POST">
            
            <div class="form-section">
                <h3>CORE PROFILE</h3>
                <input type="text" name="name" placeholder="Operator Name" required>
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <select name="rarity" style="flex: 1;">
                        <option value="6">6 Stars ★★★★★★</option>
                        <option value="5">5 Stars ★★★★★</option>
                        <option value="4">4 Stars ★★★★</option>
                        <option value="3">3 Stars ★★★</option>
                        <option value="2">2 Stars ★★</option>
                        <option value="1">1 Star ★</option>
                    </select>
                    <select name="class" style="flex: 1;">
                        <option value="Vanguard">Vanguard</option>
                        <option value="Guard">Guard</option>
                        <option value="Sniper">Sniper</option>
                        <option value="Defender">Defender</option>
                        <option value="Medic">Medic</option>
                        <option value="Caster">Caster</option>
                        <option value="Supporter">Supporter</option>
                        <option value="Specialist">Specialist</option>
                    </select>
                </div>
            </div>

            
            <div class="form-section">
                <div class="section-title">BASE / ELITE 1 DATA</div>
                <input type="text" name="image_path" placeholder="Base Image URL (Required)" required>
                <div class="grid-inputs">
                    <input type="number" name="hp" placeholder="Max HP" required>
                    <input type="number" name="atk" placeholder="Attack" required>
                    <input type="number" name="def" placeholder="Defense" required>
                    <input type="number" name="block_count" placeholder="Block" required>
                    <input type="number" name="deploy_cost" placeholder="DP Cost" required>
                </div>
            </div>

            
            <div class="form-section e2">
                <div class="section-title" style="color: #ffb400;">ELITE 2 DATA (OPTIONAL)</div>
                <input type="text" name="image_path_e2" placeholder="Elite 2 Image URL">
                <div class="grid-inputs">
                    <input type="number" name="hp_e2" placeholder="E2 Max HP">
                    <input type="number" name="atk_e2" placeholder="E2 Attack">
                    <input type="number" name="def_e2" placeholder="E2 Defense">
                    <input type="number" name="block_count_e2" placeholder="E2 Block">
                    <input type="number" name="deploy_cost_e2" placeholder="E2 DP Cost">
                </div>
            </div>

            
            <div class="form-section">
                <h3>SKILLS CONFIGURATION</h3>
                <?php for($i=1; $i<=3; $i++): ?>
                    <div style="border-bottom: 1px solid #333; margin-bottom: 10px; padding-bottom: 10px;">
                        <p style="color: #888; font-size: 11px;">SKILL 0<?php echo $i; ?></p>
                        <input type="text" name="s<?php echo $i; ?>_name" placeholder="Skill Name">
                        <input type="text" name="s<?php echo $i; ?>_img" placeholder="Skill Icon URL">
                        <textarea name="s<?php echo $i; ?>_desc" placeholder="Skill Description" style="height: 60px;"></textarea>
                    </div>
                <?php endfor; ?>
            </div>

            
            <div class="form-section">
                <h3>ARCHIVE / LORE</h3>
                <textarea name="lore" placeholder="Enter background story..." style="width: 100%; height: 100px; background: #2a2a2a; color: white; border: 1px solid #333; padding: 10px; box-sizing: border-box;"></textarea>
            </div>
            
            <button type="submit" style="width: 100%; padding: 15px; background: #ffb400; color: #000; font-weight: bold; border: none; cursor: pointer; text-transform: uppercase;">Confirm Recruitment</button>
        </form>
    </div>
</body>
</html>