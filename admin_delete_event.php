<?php
require_once 'auth.php';
require_once 'db.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Invalid CSRF token');
    }
    $eventId = intval($_POST['event_id'] ?? 0);
    if ($eventId > 0) {
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
    }
    header('Location: admin.php?tab=events');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Event | Admin</title>
    <link rel="stylesheet" href="myClub.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="admin-page">
        <h2>Confirm Delete Event</h2>
        <form method="POST" action="admin_delete_event.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
            <input type="hidden" name="event_id" value="<?php echo intval($_GET['id'] ?? 0); ?>">
            <p>Are you sure you want to delete this event? This action cannot be undone.</p>
            <button type="submit" class="btn-auth" style="background:#ff4d4d;">Delete</button>
            <a href="admin.php?tab=events" class="btn-auth" style="margin-left:10px;">Cancel</a>
        </form>
    </div>
</body>
</html>
