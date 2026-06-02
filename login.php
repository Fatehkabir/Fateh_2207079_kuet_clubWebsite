<?php

require_once 'auth.php';
require_once 'db.php';

$error='';

if(isset($_SESSION['member_id'])){
    header('Location:index.php');
    exit();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])){
        $error='Invalid CSRF token';
    } else{
        $email=trim($_POST['email'] ?? '');
        $password=$_POST['password'] ?? '';
        $remember=isset($_POST['remember']);

        if(empty($email) || empty($password)){
            $error='Please fill in all fields.';
        } else{
            $stmt=$pdo->prepare("SELECT * FROM members WHERE email = ?");
            $stmt->execute([$email]);
            $member=$stmt->fetch();

            if($member && password_verify($password,$member['password'])){
            $_SESSION['member_id']=$member['id'];
            $_SESSION['member_name']=$member['full_name'];
            $_SESSION['member_role']=$member['role'];
            $_SESSION['member_avatar']=$member['avatar'];

            $pdo->prepare("UPDATE members SET last_login = now() WHERE id = ?")->execute([$member['id']]);
           
            if($remember){
                $token=bin2hex(random_bytes(32));
                setcookie('remember_token',$token,time()+(30*24*3600),'/','',false,true);
                setcookie('remember_email', $email, time() + (30 * 24 * 3600), '/');
            }

                setcookie('hack_visited', 'true', time() + (365 * 24 * 3600), '/');
                setcookie('hack_theme', 'dark', time() + (365 * 24 * 3600), '/');

                header('Location: index.php');
                exit(); 


            } else{
                $error='Invalid email or password';
            }
        }
    }
}

$rememberedEmail = $_COOKIE['remember_email'] ?? '';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | HACK Club KUET</title>
    <meta name="description" content="Login to your HACK Club member account.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="myClub.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <div class="bg-grid"></div>

    <nav class="navbar">
        <a href="index.php" class="logo">HACK<span class="dot">.</span></a>
        <ul>
            <li><a href="index.php">HOME</a></li>
            <li><a href="register.php" id="nav-register">REGISTER</a></li>
        </ul>
    </nav>

    <div class="auth-page">
        <div class="auth-card glass-card">
            <div class="auth-header">
                <div class="auth-logo">HACK<span class="dot">.</span></div>
                <h1>Welcome Back</h1>
                <p>Login to your member account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success"><i class='bx bx-check-circle'></i> Account created! Please log in.</div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="auth-form" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                <div class="input-box">
                    <i class='bx bx-envelope'></i>
                    <input type="email" name="email" id="login_email" placeholder="University Email" value="<?= htmlspecialchars($rememberedEmail) ?>" required>
                </div>
                <div class="input-box">
                    <i class='bx bx-lock-alt'></i>
                    <input type="password" name="password" id="login_password" placeholder="Password" required>
                    <button type="button" class="toggle-pw" onclick="togglePw('login_password', this)"><i class='bx bx-hide'></i></button>
                </div>

                <div class="form-extras">
                    <label class="remember-label" for="remember">
                        <input type="checkbox" name="remember" id="remember" <?= $rememberedEmail ? 'checked' : '' ?>>
                        <span class="custom-checkbox"></span>
                        Remember me for 30 days
                    </label>
                </div>

                <button type="submit" class="btn-auth" id="loginBtn">
                    <span>Login</span>
                    <i class='bx bx-log-in'></i>
                </button>
            </form>

            <p class="auth-switch">New to HACK? <a href="register.php">Create an account</a></p>
        </div>
    </div>

    <script src="auth.js"></script>
</body>
</html>