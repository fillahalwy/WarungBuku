<?php 
include('connection.php'); 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, redirect sesuai role
if (isset($_SESSION['status_login']) && $_SESSION['status_login'] == true) {
    if (($_SESSION['role'] ?? '') === 'admin') {
        header("Location: dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

$error_msg = '';

if (isset($_POST['submit'])) {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = $_POST['password'];
    
    try {
        $check = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username' AND password = '" . md5($password) . "'");
        
        if ($check && mysqli_num_rows($check) > 0) {
            $user = mysqli_fetch_object($check);
            
            $_SESSION['status_login'] = true;
            $_SESSION['role']         = $user->role ?? 'customer';
            $_SESSION['global']       = $user;
            $_SESSION['id']           = $user->id;
            
            // Redirect sesuai role
            if ($user->role === 'admin') {
                header("Location: dashboard.php");
            } else {
                if (!empty($_SESSION['cart'])) {
                    header("Location: checkout.php");
                } else {
                    header("Location: index.php");
                }
            }
            exit();
        } else { 
            $error_msg = 'Username atau password salah. Silakan coba lagi.';
        }
    } catch (mysqli_sql_exception $e) {
        $error_msg = 'Database error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/book.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet">
    <title>Masuk Akun | WarungBuku</title>
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, sans-serif;
            padding: 20px 15px;
        }
        .login-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            overflow: hidden;
        }
        .login-header {
            background-color: #0f172a;
            padding: 20px;
            text-align: center;
            color: #ffffff;
        }
        .btn-custom {
            background-color: #0B88D3;
            color: #ffffff;
            border-radius: 8px;
            padding: 10px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-custom:hover {
            background-color: #0970af;
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="w-100 d-flex justify-content-center">
        <div class="card login-card">
            <div class="login-header">
                <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                    <img src="assets/book.ico" alt="logo" width="36">
                    <h3 class="fw-bold m-0 text-white">Warung<span style="color: #0B88D3;">Buku</span></h3>
                </div>
                <small class="text-white-50">Masuk ke Akun Anda</small>
            </div>
            
            <div class="card-body p-4 p-md-5">

                <?php if(!empty($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i><?= $error_msg ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="" method="POST">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="floatingInput" name="username" placeholder="Username" required autocomplete="off">
                        <label for="floatingInput"><i class="bi bi-person me-1"></i> Username</label>
                    </div>
                    
                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" id="floatingPassword" name="password" placeholder="Password" required>
                        <label for="floatingPassword"><i class="bi bi-lock me-1"></i> Password</label>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" name="submit" class="btn btn-custom shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
                        </button>
                    </div>

                    <div class="text-center text-muted small mb-3">
                        Belum memiliki akun pembeli? <a href="register.php" class="text-decoration-none fw-bold text-primary">Daftar Akun Baru</a>
                    </div>

                    <div class="text-center pt-3 border-top">
                        <a href="index.php" class="text-decoration-none small text-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda Toko
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>