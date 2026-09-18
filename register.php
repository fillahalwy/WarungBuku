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
$success_msg = '';

if (isset($_POST['register'])) {
    $name             = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $username         = mysqli_real_escape_string($conn, trim($_POST['username'] ?? ''));
    $phone            = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $email            = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $address          = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validasi
    if (empty($name) || empty($username) || empty($phone) || empty($address) || empty($password)) {
        $error_msg = "Harap lengkapi semua kolom yang bertanda bintang (*).";
    } elseif ($password !== $confirm_password) {
        $error_msg = "Konfirmasi password tidak sesuai. Silakan ketik ulang.";
    } elseif (strlen($password) < 4) {
        $error_msg = "Password minimal terdiri dari 4 karakter.";
    } else {
        // Cek username duplikat
        $check_user = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
        if (mysqli_num_rows($check_user) > 0) {
            $error_msg = "Username <strong>" . htmlspecialchars($username) . "</strong> sudah digunakan. Pilih username lain.";
        } else {
            $hashed_password = md5($password);
            $insert = mysqli_query($conn, "INSERT INTO users (name, username, password, phone, email, address, role, created_at, updated_at) 
                                           VALUES ('$name', '$username', '$hashed_password', '$phone', '$email', '$address', 'customer', NOW(), NOW())");
            
            if ($insert) {
                // Auto login customer
                $new_id = mysqli_insert_id($conn);
                $get_user = mysqli_fetch_object(mysqli_query($conn, "SELECT * FROM users WHERE id = '$new_id'"));
                
                $_SESSION['status_login'] = true;
                $_SESSION['role']         = 'customer';
                $_SESSION['global']       = $get_user;
                $_SESSION['id']           = $get_user->id;

                // Jika ada isi keranjang, arahkan ke checkout, selain itu ke beranda
                if (!empty($_SESSION['cart'])) {
                    header("Location: checkout.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $error_msg = "Gagal mendaftar: " . mysqli_error($conn);
            }
        }
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
    <title>Daftar Akun Baru | WarungBuku</title>
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, sans-serif;
            padding: 30px 15px;
        }
        .register-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 520px;
            background: #ffffff;
            overflow: hidden;
        }
        .register-header {
            background-color: #0f172a;
            padding: 20px;
            text-align: center;
            color: #ffffff;
        }
        .btn-custom {
            background-color: #0B88D3;
            color: #ffffff;
            border-radius: 8px;
            padding: 11px;
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
        <div class="card register-card">
            
            <div class="register-header">
                <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                    <img src="assets/book.ico" alt="logo" width="36">
                    <h3 class="fw-bold m-0 text-white">Warung<span style="color: #0B88D3;">Buku</span></h3>
                </div>
                <p class="text-white-50 small mb-0">Pendaftaran Akun Pelanggan Baru</p>
            </div>
            
            <div class="card-body p-4 p-md-5">

                <?php if(!empty($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i><?= $error_msg ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="" method="POST">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                            <input type="text" name="name" class="form-control" placeholder="Nama lengkap Anda" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-at"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">No WhatsApp/HP <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                <input type="tel" name="phone" class="form-control" placeholder="081234567890" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email (Opsional)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="alamat@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alamat Lengkap Pengiriman <span class="text-danger">*</span></label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Nama Jalan, Nomor Rumah, RT/RW, Kelurahan, Kecamatan, Kota/Kabupaten, Kode Pos" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                        <small class="text-muted" style="font-size: 0.75rem;">Alamat ini akan otomatis digunakan untuk pengiriman buku pesanan Anda.</small>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="Minimal 4 karakter" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Konfirmasi Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-check2-circle"></i></span>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi password" required>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" name="register" class="btn btn-custom shadow-sm">
                            <i class="bi bi-person-plus me-1"></i> Daftar Akun Sekarang
                        </button>
                    </div>

                    <div class="text-center text-muted small">
                        Sudah memiliki akun? <a href="login.php" class="text-decoration-none fw-bold text-primary">Masuk di sini</a>
                    </div>

                    <div class="text-center mt-3 pt-3 border-top">
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
