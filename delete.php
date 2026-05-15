<?php
require_once 'db.php';


$id = $_GET['id'] ?? 0;

if ($id > 0) {
    try {
       
        $stmt = $pdo->prepare("DELETE FROM operators WHERE id = ?");
        $stmt->execute([$id]);
        
       
        header("Location: index.php?status=dismissed");
    } catch (PDOException $e) {
        
        die("CRITICAL ERROR: Could not remove operator record. " . $e->getMessage());
    }
} else {
    
    header("Location: index.php");
}
exit;
?>