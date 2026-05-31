<?php

require_once 'auth.php';
require_once 'db.php';
requireAdmin();

if($_SERVER['REQUEST_METHOD']=='POST'){
    $token=$_POST['csrf_token'] ?? '';
    if(!verify_csrf_token($token)){
        die('Invalid CSRF token');
    }

    $title=trim($_POST['title'] ?? '');
    $description=trim($_POST['description'] ?? '');
    $event_date=$_POST['event_date'] ?? '';
    $start_time=$_POST['start_time'] ?? '';
    $end_time=$_POST['end_time'] ?? '';
    $location=trim($_POST['location'] ?? '');
    $seats=intval($_POST['seats'] ?? 0);
    if($title && $event_date && $start_time && $end_time && $location){
        $stmt=$pdo->prepare("INSERT INTO events (title,description,event_date,start_time,end_time,location,seats,created_by) values (?,?,?,?,?,?,?,?)");
        $stmt->execute([$title,$description,$event_date,$start_time,$end_time,$location,$seats,getuserId()]);
        header('Location:admin.php?tab=events');
        exit();
    } else{
        $error='Please fill all required fields.';
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Event | Admin</title>
    <link rel="stylesheet" href="myClub.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="admin-page">
        <h2>Add New Event</h2>
        <?php if (!empty($error)): ?>
            <p style="color:#ff6b6b;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST" action="admin_add_event.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
            <label>Title:<br><input type="text" name="title" required></label><br>
            <label>Description:<br><textarea name="description" rows="5"></textarea></label><br>
            <label>Date:<br><input type="date" name="event_date" required></label><br>
            <label>Start Time:<br><input type="time" name="start_time" required></label><br>
            <label>End Time:<br><input type="time" name="end_time" required></label><br>
            <label>Location:<br><input type="text" name="location" required></label><br>
            <label>Seats (0 = unlimited):<br><input type="number" name="seats" min="0" value="0"></label><br>
            <button type="submit" class="btn-auth">Create Event</button>
        </form>
        <p><a href="admin.php?tab=events">Back to Events</a></p>
    </div>
</body>
</html>