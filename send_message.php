<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit();
}

$memberId = intval($_SESSION['member_id']);
$subject  = trim($_POST['subject'] ?? '');
$message  = trim($_POST['message'] ?? '');

if (empty($subject) || empty($message)) {
    $_SESSION['status_message'] = 'Please fill in both subject and message.';
    $_SESSION['status_type'] = 'error';
    header('Location: dashboard.php?tab=messages');
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO member_messages (member_id, subject, message, is_read) VALUES (?, ?, ?, 0)");
    $stmt->execute([$memberId, $subject, $message]);
    
    $_SESSION['status_message'] = 'Message sent successfully to admin!';
    $_SESSION['status_type'] = 'success';
} catch (PDOException $e) {
    $_SESSION['status_message'] = 'Failed to send message. Please try again.';
    $_SESSION['status_type'] = 'error';
}

header('Location: dashboard.php?tab=messages');
exit();
