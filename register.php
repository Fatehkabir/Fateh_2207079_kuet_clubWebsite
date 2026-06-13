<?php

session_start();
require_once 'db.php';

$error='';
$success='';

if(isset($_SESSION['member_id'])){
    header('Location:index.php');
    exit();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $full_name=htmlspecialchars(trim($_POST['full_name'] ?? ''));
    $student_id=htmlspecialchars(trim($_POST['student_id'] ?? ''));
    $email=htmlspecialchars(trim($_POST['email'] ?? ''));
    $department=htmlspecialchars(trim($_POST['department'] ?? ''));
    $year=intval($_POST['year'] ?? 0);
    $password=$_POST['password'] ?? '';
    $confirm=$_POST['confirm_password'] ?? '';


    if(empty($full_name)||empty($student_id)||empty($email)||empty($password)){
        $error='All fields are required.';
    } elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){
        $error='please enter a valid email address.';
    } elseif(strlen($password)<6){
        $error='password must be at least 6 characters.';
    } elseif($password!==$confirm){
        $error='passwords do not match.';
    } else{
        $stmt=$pdo->prepare("SELECT id FROM members WHERE email=? OR student_id=?");
        $stmt->execute([$email,$student_id]);
        if($stmt->rowCount()>0){
            $error='An account with this email or student ID already exists.';
        } else{
            $hash=password_hash($password,PASSWORD_BCRYPT);
            $avatars=['👨‍💻', '👩‍💻', '🧑‍🔧', '👨‍🔬', '👩‍🔬', '🤖', '⚙️', '🔌'];
            $avatar=$avatars[array_rand($avatars)];
            $stmt=$pdo->prepare("INSERT INTO members (full_name,student_id,email,password,department,year,avatar) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$full_name,$student_id,$email,$hash,$department,$year,$avatar]);
            $success='Registration successful! You can now log in.';
        }
    }

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | HACK Club KUET</title>
    <meta name="description" content="Join HACK Club – KUET's premier hardware and robotics club. Register your member account.">
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
            <li><a href="login.php" id="nav-login">LOGIN</a></li>
        </ul>
    </nav>

    <div class="auth-page">
        <div class="auth-card glass-card">
            <div class="auth-header">
                <div class="auth-logo">HACK<span class="dot">.</span></div>
                <h1>Create Account</h1>
                <p>Join the hardware revolution at KUET</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?= $success ?> <a href="login.php">Login here</a></div>
            <?php endif; ?>

            <form method="POST" action="register.php" class="auth-form" id="registerForm">
                <div class="form-row">
                    <div class="input-box">
                        <i class='bx bx-user'></i>
                        <input type="text" name="full_name" id="full_name" placeholder="Full Name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                    </div>
                    <div class="input-box">
                        <i class='bx bx-id-card'></i>
                        <input type="text" name="student_id" id="student_id" placeholder="Student ID (e.g. 2101001)" value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="input-box">
                    <i class='bx bx-envelope'></i>
                    <input type="email" name="email" id="reg_email" placeholder="University Email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <div class="input-box">
                        <i class='bx bx-building'></i>
                        <select name="department" id="department">
                            <option value="">Department</option>
                            <option value="CSE" <?= (($_POST['department'] ?? '') === 'CSE') ? 'selected' : '' ?>>CSE</option>
                            <option value="EEE" <?= (($_POST['department'] ?? '') === 'EEE') ? 'selected' : '' ?>>EEE</option>
                            <option value="ECE" <?= (($_POST['department'] ?? '') === 'ECE') ? 'selected' : '' ?>>ECE</option>
                            <option value="ME"  <?= (($_POST['department'] ?? '') === 'ME')  ? 'selected' : '' ?>>ME</option>
                            <option value="CE"  <?= (($_POST['department'] ?? '') === 'CE')  ? 'selected' : '' ?>>CE</option>
                            <option value="Other" <?= (($_POST['department'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="input-box">
                        <i class='bx bx-calendar'></i>
                        <select name="year" id="year">
                            <option value="">Year</option>
                            <option value="1" <?= (($_POST['year'] ?? '') === '1') ? 'selected' : '' ?>>1st Year</option>
                            <option value="2" <?= (($_POST['year'] ?? '') === '2') ? 'selected' : '' ?>>2nd Year</option>
                            <option value="3" <?= (($_POST['year'] ?? '') === '3') ? 'selected' : '' ?>>3rd Year</option>
                            <option value="4" <?= (($_POST['year'] ?? '') === '4') ? 'selected' : '' ?>>4th Year</option>
                        </select>
                    </div>
                </div>
                <div class="input-box">
                    <i class='bx bx-lock-alt'></i>
                    <input type="password" name="password" id="reg_password" placeholder="Password (min 6 chars)" required>
                    <button type="button" class="toggle-pw" onclick="togglePw('reg_password', this)"><i class='bx bx-hide'></i></button>
                </div>
                <div class="input-box">
                    <i class='bx bx-lock-open-alt'></i>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                    <button type="button" class="toggle-pw" onclick="togglePw('confirm_password', this)"><i class='bx bx-hide'></i></button>
                </div>

                <div class="pw-strength-bar" id="pwStrengthBar">
                    <div class="pw-strength-fill" id="pwStrengthFill"></div>
                </div>
                <div class="pw-strength-label" id="pwStrengthLabel"></div>

                <button type="submit" class="btn-auth" id="registerBtn">
                    <span>Create Account</span>
                    <i class='bx bx-right-arrow-alt'></i>
                </button>
            </form>

            <p class="auth-switch">Already a member? <a href="login.php">Login here</a></p>
        </div>
    </div>

    <script src="auth.js"></script>

</body>
</html>