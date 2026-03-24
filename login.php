<?php
// login.php
session_start();

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ในระบบจริง: ต้องใช้ password_verify() และเชื่อมต่อฐานข้อมูล
    $valid_username = 'admin';
    $valid_password = '1234'; 
    
    if ($username === $valid_username && $password === $valid_password) {
        $_SESSION['loggedin'] = true;
        $_SESSION['user'] = $username;
        header('Location:dashboard_full.php');
        exit;
    } else {
        $error_message = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง 🏥";
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - Division of Adult Cardiology</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;700&display=swap');
 
       body {
    font-family: 'Sarabun', sans-serif;
    
    /* สีพื้นหลังแบบฟ้าอ่อนไล่ไปขาว */
    background: #e3f2fd; /* สีสำรอง */
    background: linear-gradient(180deg, #e3f2fd 0%, #ffffff 100%);
    
    background-repeat: no-repeat;
    background-attachment: fixed;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    margin: 0;
}

        .login-card {
            background: #ffffff;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px; /* ความกว้างสูงสุด */
            border-top: 6px solid #19a974; /* เพิ่มแถบสีเอกลักษณ์โรงพยาบาล */
        }

        .hospital-logo {
            color: #dc3545;
            margin-bottom: 10px;
            animation: pulse-heart 1.5s infinite;
        }

        @keyframes pulse-heart {
            0% { transform: scale(1); }
            15% { transform: scale(1.1); }
            30% { transform: scale(1); }
            45% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }

        .hospital-title {
            color: #2c3e50;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 2px;
            line-height: 1.2;
        }

        .hospital-subtitle {
            color: #6c757d;
            font-size: 0.85rem;
            margin-bottom: 25px;
            display: block;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.95rem;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: #19a974;
            box-shadow: 0 0 0 0.25rem rgba(25, 169, 116, 0.15);
        }

        .btn-login {
            background-color: #19a974;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1.1rem;
            margin-top: 10px;
            transition: all 0.2s;
        }

        /* ปรับสไตล์ปุ่มเมื่อเอาเมาส์ไปวาง หรือกด */
        .btn-login:hover, .btn-login:active {
            background-color: #148f61 !important;
            transform: translateY(-1px);
        }

        /* ปรับแต่ง Alert ให้ดูเล็กลงและเข้ากับดีไซน์ */
        .alert {
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }

        /* Media Query สำหรับหน้าจอเล็กพิเศษ */
        @media (max-width: 360px) {
            .login-card {
                padding: 20px;
            }
            .hospital-title {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="login-card text-center">
    <div class="hospital-logo">
        <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" fill="currentColor" class="bi bi-heart-pulse" viewBox="0 0 16 16">
            <path d="m8 2.748-.717-.737C5.6.281 2.514.878 1.4 3.053.918 3.995.78 5.323 1.508 7H.43c-2.128-5.697 4.165-8.83 7.394-5.857q.09.083.176.171a3 3 0 0 1 .176-.17c3.23-2.974 9.522.159 7.394 5.856h-1.078c.728-1.677.59-3.005.108-3.947C13.486.878 10.4.28 8.717 2.01zM2.212 10h1.315C4.593 11.183 6.05 12.458 8 13.795c1.949-1.337 3.407-2.612 4.473-3.795h1.315c-1.265 1.566-3.14 3.25-5.788 5-2.648-1.75-4.523-3.434-5.788-5"/>
            <path d="M10.464 3.314a.5.5 0 0 0-.945.049L7.921 8.956 6.464 5.314a.5.5 0 0 0-.88-.091L3.732 8H.5a.5.5 0 0 0 0 1H4a.5.5 0 0 0 .416-.223l1.473-2.209 1.647 4.118a.5.5 0 0 0 .945-.049l1.598-5.593 1.457 3.642A.5.5 0 0 0 12 9h3.5a.5.5 0 0 0 0-1h-3.162z"/>
        </svg>
    </div>

    <h1 class="hospital-title"> STEMI Registry</h1>
    <small class="hospital-subtitle">ระบบจัดเก็บและติดตามตัวชี้วัดคุณภาพการดูแลผู้ป่วยโรคหัวใจขาดเลือด</small>

    <form method="POST" action="">
        <?php if ($error_message): ?>
            <div class="alert alert-danger py-2" role="alert">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="mb-3 text-start">
            <label for="username" class="form-label">ชื่อผู้ใช้</label>
            <input type="text" class="form-control" id="username" name="username" 
                   placeholder="กรอกชื่อผู้ใช้" required value="<?= htmlspecialchars($username) ?>" autocomplete="username">
        </div>
        
        <div class="mb-4 text-start">
            <label for="password" class="form-label">รหัสผ่าน</label>
            <input type="password" class="form-control" id="password" name="password" 
                   placeholder="กรอกรหัสผ่าน" required autocomplete="current-password">
        </div>
        
        <button type="submit" class="btn btn-login btn-success w-100 shadow-sm">
            เข้าสู่ระบบ 🔒
        </button>
    </form>
    
    <div class="mt-4 pt-2">
        <p style="font-size: 0.75rem; color: #adb5bd;">© 2025 Adult Cardiology Information System</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>