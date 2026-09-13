<?php 
include('connection.php'); 
session_start();

// If already logged in, redirect to dashboard
if(isset($_SESSION['status_login']) && $_SESSION['status_login'] == true){
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/book.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet">
    <title>Login Administrator | WarungBuku</title>
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, sans-serif;
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
            padding: 15px 20px;
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
    <div class="px-3 w-100 d-flex justify-content-center">
        <div class="card login-card">
            <div class="login-header">
                <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                    <img src="assets/book.ico" alt="logo" width="36">
                    <h3 class="fw-bold m-0">Warung<span style="color: #0B88D3;">Buku</span></h3>
                </div>
                <small class="text-white-50">Admin Panel Authentication</small>
            </div>
            
            <div class="card-body p-4">
                <?php 
                    $error_msg = '';
                    if(isset($_POST['submit'])){
                        $username = mysqli_real_escape_string($conn, trim($_POST['username']));
                        $password = $_POST['password'];
                        
                        try {
                            $check = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username' AND password = '".MD5($password)."'");
                            
                            if($check && mysqli_num_rows($check) > 0){
                                $admin = mysqli_fetch_object($check);
                                $_SESSION['status_login'] = true;
                                $_SESSION['global']       = $admin;
                                $_SESSION['id']           = $admin->id;
                                
                                echo "<script>window.location='dashboard.php';</script>";
                                exit();
                            } else { 
                                $error_msg = 'Incorrect username or password. Please try again.';
                            }
                        } catch (mysqli_sql_exception $e) {
                            $error_msg = 'Database error: ' . $e->getMessage();
                        }
                    }
                ?>

                <?php if(!empty($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error_msg ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="" method="post">
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
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                        </button>
                    </div>

                    <div class="text-center">
                        <a href="index.php" class="text-decoration-none small text-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back to Store
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>