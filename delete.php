<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 'guest';
if ($role !== 'admin') {
    http_response_code(403);
    die("<div style='background:#0B131D; color:#EF4444; text-align:center; padding:60px; font-family:sans-serif;'>
        <h2>⛔ ACCESS DENIED: ADMINISTRATOR PRIVILEGES REQUIRED</h2>
        <p style='color:#94A3B8;'>Only administrators can dismiss operator records.</p>
        <a href='index.php' style='color:#00B4D8; font-weight:bold;'>⬅ Return to Index</a>
    </div>");
}

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '' || !is_string($postedToken) || !hash_equals($sessionToken, $postedToken)) {
        http_response_code(403);
        die("CRITICAL ERROR: Invalid CSRF token.");
    }
}

if ($id > 0) {
    try {
        // Remove local sound files if table exists
        try {
            $sndStmt = $pdo->prepare("SELECT file_path FROM operator_sounds WHERE operator_id = ?");
            $sndStmt->execute([$id]);
            foreach ($sndStmt->fetchAll(PDO::FETCH_ASSOC) as $sndRow) {
                $path = trim((string)($sndRow['file_path'] ?? ''));
                if ($path !== '' && strpos($path, 'uploads/') === 0 && strpos($path, '..') === false && file_exists($path)) {
                    @unlink($path);
                }
            }
            $pdo->prepare("DELETE FROM operator_sounds WHERE operator_id = ?")->execute([$id]);
        } catch (Throwable $e) {
            // Ignore if operator_sounds does not exist
        }

        try {
            $pdo->prepare("DELETE FROM operator_comments WHERE operator_id = ?")->execute([$id]);
        } catch (Throwable $e) {
            // Ignore if operator_comments does not exist
        }

        try {
            $pdo->prepare("DELETE FROM user_inventory WHERE operator_id = ?")->execute([$id]);
        } catch (Throwable $e) {
            // Ignore if user_inventory does not exist
        }

        $stmt = $pdo->prepare("DELETE FROM operators WHERE id = ?");
        $stmt->execute([$id]);

        header("Location: index.php?status=dismissed");
        exit;
    } catch (PDOException $e) {
        die("CRITICAL ERROR: Could not remove operator record. " . htmlspecialchars($e->getMessage()));
    }
}

header("Location: index.php");
exit;