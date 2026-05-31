<?php
require_once 'auth.php';
require_once 'db.php';
requireAdmin();

$eventId = intval($_GET['id'] ?? 0);
if ($eventId <= 0) {
    die('Invalid event ID');
}

// Fetch existing data
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event) {
    die('Event not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Invalid CSRF token');
    }
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $seats = intval($_POST['seats'] ?? 0);
    if ($title && $event_date && $start_time && $end_time && $location) {
        $upd = $pdo->prepare("UPDATE events SET title = ?, description = ?, event_date = ?, start_time = ?, end_time = ?, location = ?, seats = ? WHERE id = ?");
        $upd->execute([$title, $description, $event_date, $start_time, $end_time, $location, $seats, $eventId]);
        header('Location: admin.php?tab=events');
        exit();
    } else {
        $error = 'Please fill all required fields.';
    }
    // Refresh $event after update for display
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Event | Admin</title>
    <link rel="stylesheet" href="myClub.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="admin-page">
        <h2>Edit Event</h2>
        <?php if (!empty($error)): ?>
            <p style="color:#ff6b6b;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST" action="admin_edit_event.php?id=<?php echo $eventId; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
            <label>Title:<br><input type="text" name="title" value="<?php echo htmlspecialchars($event['title']); ?>" required></label><br>
            <label>Description:<br><textarea name="description" rows="5"><?php echo htmlspecialchars($event['description']); ?></textarea></label><br>
            <label>Date:<br><input type="date" name="event_date" value="<?php echo $event['event_date']; ?>" required></label><br>
            <label>Start Time:<br><input type="time" name="start_time" value="<?php echo $event['start_time']; ?>" required></label><br>
            <label>End Time:<br><input type="time" name="end_time" value="<?php echo $event['end_time']; ?>" required></label><br>
            <label>Location:<br><input type="text" name="location" value="<?php echo htmlspecialchars($event['location']); ?>" required></label><br>
            <label>Seats (0 = unlimited):<br><input type="number" name="seats" min="0" value="<?php echo $event['seats']; ?>"></label><br>
            <button type="submit" class="btn-auth">Save Changes</button>
        </form>
        <p><a href="admin.php?tab=events">Back to Events</a></p>
    </div>
</body>
</html>
