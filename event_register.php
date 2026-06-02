<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit();
}

$memberId = intval($_SESSION['member_id']);
$eventId  = intval($_POST['event_id'] ?? 0);

if ($eventId <= 0) {
    $_SESSION['status_message'] = 'Invalid event selected.';
    $_SESSION['status_type'] = 'error';
    header('Location: dashboard.php?tab=events');
    exit();
}


$stmt = $pdo->prepare("SELECT id, seats FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    $_SESSION['status_message'] = 'Event not found.';
    $_SESSION['status_type'] = 'error';
    header('Location: dashboard.php?tab=events');
    exit();
}


$countStmt = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ?");
$countStmt->execute([$eventId]);
$registered = $countStmt->fetchColumn();

if ($registered >= $event['seats']) {
    $_SESSION['status_message'] = 'Sorry, this event is already full.';
    $_SESSION['status_type'] = 'error';
    header('Location: dashboard.php?tab=events');
    exit();
}


$checkStmt = $pdo->prepare("SELECT event_id FROM event_registrations WHERE event_id = ? AND member_id = ?");
$checkStmt->execute([$eventId, $memberId]);
if ($checkStmt->rowCount() > 0) {
    $_SESSION['status_message'] = 'You are already registered for this event.';
    $_SESSION['status_type'] = 'error';
    header('Location: dashboard.php?tab=events');
    exit();
}

try {
    $insertStmt = $pdo->prepare("INSERT INTO event_registrations (event_id, member_id) VALUES (?, ?)");
    $insertStmt->execute([$eventId, $memberId]);
    
    $_SESSION['status_message'] = 'Successfully registered for the event!';
    $_SESSION['status_type'] = 'success';
} catch (PDOException $e) {
    $_SESSION['status_message'] = 'Registration failed. Please try again.';
    $_SESSION['status_type'] = 'error';
}

header('Location: dashboard.php?tab=myevents');
exit();
