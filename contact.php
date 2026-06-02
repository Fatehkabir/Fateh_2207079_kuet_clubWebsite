<?php
session_start();
require_once 'db.php';

$statusMessage = '';
$statusType    = '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$name    = htmlspecialchars(trim($_POST['name']    ?? ''));
$email   = htmlspecialchars(trim($_POST['email']   ?? ''));
$message = htmlspecialchars(trim($_POST['message'] ?? ''));

if (empty($name) || empty($email) || empty($message)) {
    $statusMessage = 'Please fill in all fields.';
    $statusType    = 'error';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $statusMessage = 'Please enter a valid email address.';
    $statusType    = 'error';
} else {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    try {
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, message, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $message, $ip]);

        $logMessage = '[' . date('Y-m-d H:i:s') . "] Name: $name | Email: $email | Message: $message" . PHP_EOL . str_repeat('-', 50) . PHP_EOL;
        file_put_contents('messages.txt', $logMessage, FILE_APPEND | LOCK_EX);

        setcookie('last_contact_name',  $name,  time() + (7 * 24 * 3600), '/');
        setcookie('last_contact_email', $email, time() + (7 * 24 * 3600), '/');

        $statusMessage = "Thank you, $name! Your message has been received and saved.";
        $statusType    = 'success';
    } catch (PDOException $e) {
        $statusMessage = "Oops! Something went wrong. Please try again later.";
        $statusType    = 'error';
    }
}

$memberName = $_SESSION['member_name'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Status | HACK Club KUET</title>
    <meta name="description" content="HACK Club contact message status page.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="myClub.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <style>
        .status-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 120px 20px 60px;
        }
        .status-card {
            background: rgba(15, 20, 35, 0.7);
            backdrop-filter: blur(20px);
            padding: 50px 40px;
            border-radius: 24px;
            border: 1px solid rgba(0, 243, 255, 0.2);
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .status-card.success { border-top: 4px solid #00f3ff; }
        .status-card.error   { border-top: 4px solid #ff4d4d; }
        .status-icon { font-size: 4rem; margin-bottom: 20px; display: block; }
        .status-card h2 { color: white; font-size: 2rem; font-weight: 800; margin-bottom: 15px; }
        .status-card p  { color: #cbd5e1; line-height: 1.6; font-size: 1.05rem; margin-bottom: 30px; }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 32px;
            background: linear-gradient(135deg, #00f3ff, #2d72fc);
            color: white;
            font-weight: 700;
            border-radius: 12px;
            text-decoration: none;
            font-size: 1rem;
            transition: 0.3s;
            box-shadow: 0 0 20px rgba(0,243,255,0.4);
        }
        .back-btn:hover { transform: translateY(-3px); box-shadow: 0 0 30px rgba(0,243,255,0.6); }
        .member-note { margin-top: 20px; color: #64748b; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <nav class="navbar">
        <a href="index.php" class="logo">HACK<span class="dot">.</span></a>
        <ul>
            <li><a href="index.php">HOME</a></li>
            <?php if ($memberName): ?>
                <li><a href="dashboard.php">DASHBOARD</a></li>
            <?php else: ?>
                <li><a href="login.php">LOGIN</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="status-page">
        <div class="status-card <?= $statusType ?>">
            <span class="status-icon"><?= $statusType === 'success' ? '✅' : '❌' ?></span>
            <h2><?= $statusType === 'success' ? 'Message Sent!' : 'Something Went Wrong' ?></h2>
            <p><?= $statusMessage ?></p>
            <?php if ($statusType === 'success' && $memberName): ?>
                <p class="member-note">Logged in as <strong><?= htmlspecialchars($memberName) ?></strong></p>
            <?php endif; ?>
            <a href="index.php#contact" class="back-btn">
                <i class='bx bx-left-arrow-alt'></i> Return to Website
            </a>
        </div>
    </div>
</body>
</html>
