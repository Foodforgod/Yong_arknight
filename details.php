<?php 
require_once 'db.php'; 

$id = $_GET['id'] ?? 0;
$view = $_GET['view'] ?? 'phase0';

$stmt = $pdo->prepare("SELECT * FROM operators WHERE id = ?");
$stmt->execute([$id]);
$op = $stmt->fetch();

if (!$op) { die("Error: Operator not found."); }

$has_e2 = !empty($op['image_path_e2']);
if ($view === 'phase2' && !$has_e2) {
    $view = 'phase0';
}

if ($view === 'phase2') {
    $active_img   = $op['image_path_e2'];
    $active_hp    = $op['hp_e2'];
    $active_atk   = $op['atk_e2'];
    $active_def   = $op['def_e2'];
    $active_block = $op['block_count_e2'];
    $active_cost  = $op['deploy_cost_e2'];
} else {
    $active_img   = $op['image_path'];
    $active_hp    = $op['hp'];
    $active_atk   = $op['atk'];
    $active_def   = $op['def'];
    $active_block = $op['block_count'];
    $active_cost  = $op['deploy_cost'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $op['name']; ?> - Dossier</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .header-actions { display: flex; gap: 10px; }
        .promo-selector-wrap { margin: 20px 0; display: flex; align-items: center; gap: 12px; }
        .label-text { font-size: 11px; color: #666; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
        .btn-group { display: flex; background: #1a1a1a; padding: 3px; border: 1px solid #333; }
        .btn-toggle { padding: 6px 18px; color: #888; text-decoration: none; font-size: 12px; font-weight: 800; transition: 0.2s ease; text-transform: uppercase; }
        .btn-toggle.active { background: #ffb400; color: #000; }
        .btn-toggle:hover:not(.active):not(.disabled) { color: #fff; background: #333; }
        .btn-toggle.disabled { opacity: 0.2; cursor: not-allowed; }
        .e2-indicator { background: #ffb400; color: #000; padding: 1px 6px; font-size: 12px; margin-left: 8px; vertical-align: middle; }
    </style>
</head>
<body class="details-page">
    <header class="main-header">
        <div class="logo">OPERATOR DOSSIER</div>
        <div class="header-actions">
            <a href="edit.php?id=<?php echo $id; ?>" class="btn-add" style="background: #444;">MODIFY DATA</a>
            <a href="index.php" class="btn-add">⬅ BACK TO GALLERY</a>
        </div>
    </header>

    <div class="details-container">
       
        <div class="details-image" id="zoom-container">
           <img src="<?php echo htmlspecialchars($active_img); ?>" id="operator-img" alt="Operator Portrait" style="cursor: zoom-in;">
        </div>
        
        <div class="details-info">
            <h1>
                <?php echo strtoupper($op['name']); ?>
                <?php if($view === 'phase2'): ?><span class="e2-indicator">E2</span><?php endif; ?>
            </h1>
            <p class="rarity"><?php echo str_repeat('★', $op['rarity']); ?></p>
            
            <div class="promo-selector-wrap">
                <span class="label-text">PROMOTION</span>
                <div class="btn-group">
                    <a href="?id=<?php echo $id; ?>&view=phase0" 
                       class="btn-toggle <?php echo $view === 'phase0' ? 'active' : ''; ?>">PHASE 0/1</a>
                    
                    <?php if ($has_e2): ?>
                        <a href="?id=<?php echo $id; ?>&view=phase2" 
                           class="btn-toggle <?php echo $view === 'phase2' ? 'active' : ''; ?>">PHASE 2</a>
                    <?php else: ?>
                        <span class="btn-toggle disabled">PHASE 2</span>
                    <?php endif; ?>
                </div>
            </div>
    
            <table class="stats-table">
                <thead>
                    <tr><th colspan="2">OPERATOR ATTRIBUTES (<?php echo strtoupper($view); ?>)</th></tr>
                </thead>
                <tbody>
                    <tr><td>MAX HP</td><td class="stat-val"><?php echo $active_hp; ?></td></tr>
                    <tr><td>ATTACK</td><td class="stat-val"><?php echo $active_atk; ?></td></tr>
                    <tr><td>DEFENSE</td><td class="stat-val"><?php echo $active_def; ?></td></tr>
                    <tr><td>BLOCK COUNT</td><td class="stat-val"><?php echo $active_block; ?></td></tr>
                    <tr><td>DEPLOY COST</td><td class="stat-val" style="color: #ffb400;"><?php echo $active_cost; ?></td></tr>
                </tbody>
            </table>
            <div class="skills-section">
    <h4><i class="fas fa-bolt"></i> COMBAT SKILLS</h4>
    <?php for ($i = 1; $i <= 3; $i++): 
        $s_name = $op["s{$i}_name"];
        $s_img = $op["s{$i}_img"];
        $s_desc = $op["s{$i}_desc"];
        
        if (!empty($s_name)): ?>
        <div class="skill-card">
            <div class="skill-icon">
                <img src="<?php echo htmlspecialchars($s_img ?: 'https://placeholder.com'); ?>" alt="skill">
            </div>
            <div class="skill-info">
                <h5>SKILL 0<?php echo $i; ?>: <?php echo strtoupper($s_name); ?></h5>
                <p><?php echo nl2br(htmlspecialchars($s_desc)); ?></p>
            </div>
        </div>
        <?php endif; 
    endfor; ?>
</div>
  
            <div class="op-bio">
                <h4>ARCHIVE DATA / BIOGRAPHY</h4>
                <div class="bio-content">
                    <?php echo !empty($op['lore']) ? nl2br(htmlspecialchars($op['lore'])) : "No archive data available."; ?>
                </div>
            </div>
        </div>
    </div>

    
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const img = document.getElementById('operator-img');
    if (!img) return;

    let scale = 1;
    const speed = 0.2;

    
    img.addEventListener('wheel', function(e) {
        e.preventDefault(); 
        if (e.deltaY < 0) {
            scale += speed;
        } else {
            scale = Math.max(0.5, scale - speed);
        }
        
        img.style.transform = "scale(" + scale + ")";
    }, { passive: false });

    
    img.addEventListener('click', function() {
        if (scale !== 1) {
            scale = 1;
        } else {
            scale = 2;
        }
        img.style.transition = "transform 0.3s ease";
        img.style.transform = "scale(" + scale + ")";
        
        
        setTimeout(function() {
            img.style.transition = "transform 0.1s ease";
        }, 300);
    });
});
</script>
</body>
</html>