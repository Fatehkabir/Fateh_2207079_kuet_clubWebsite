<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit();
}

$memberId = intval($_SESSION['member_id']);
$eventId  = intval($_POST['event_id'] ?? 0);

if ($eventId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid event selected.']);
    exit();
}

$stmt = $pdo->prepare("SELECT id, seats FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    echo json_encode(['success' => false, 'message' => 'Event not found.']);
    exit();
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ?");
$countStmt->execute([$eventId]);
$registered = $countStmt->fetchColumn();

if ($registered >= $event['seats']) {
    echo json_encode(['success' => false, 'message' => 'Sorry, this event is already full.']);
    exit();
}

$checkStmt = $pdo->prepare("SELECT event_id FROM event_registrations WHERE event_id = ? AND member_id = ?");
$checkStmt->execute([$eventId, $memberId]);
if ($checkStmt->rowCount() > 0) {
    echo json_encode(['success' => false, 'message' => 'You are already registered for this event.']);
    exit();
}

try {
    $insertStmt = $pdo->prepare("INSERT INTO event_registrations (event_id, member_id) VALUES (?, ?)");
    $insertStmt->execute([$eventId, $memberId]);
    echo json_encode(['success' => true, 'message' => 'Successfully registered for the event!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}
