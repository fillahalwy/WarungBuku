<?php
include("connection.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validasi Sesi Customer
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] != true) {
    header("Location: login.php");
    exit();
}

// Jika admin, arahkan ke profile admin
if (($_SESSION['role'] ?? '') === 'admin') {
    header("Location: profile.php");
    exit();
}

$user_id = $_SESSION['id'];
$query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$user = mysqli_fetch_object($query);

$msg_success = '';
$msg_error = '';

// Update Profil
if (isset($_POST['update_profile'])) {
    $name    = mysqli_real_escape_string($conn, trim($_POST['name']));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $email   = mysqli_real_escape_string($conn, trim($_POST['email']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));

    $update = mysqli_query($conn, "UPDATE users SET 
                                   name = '$name', 
                                   phone = '$phone', 
                                   email = '$email', 
                                   address = '$address', 
                                   updated_at = NOW() 
                                   WHERE id = '$user_id'");
    if ($update) {
        $msg_success = "Profil Anda berhasil diperbarui.";
        // Refresh object session
        $query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
        $user = mysqli_fetch_object($query);
        $_SESSION['global'] = $user;
    } else {
        $msg_error = "Gagal memperbarui profil: " . mysqli_error($conn);
    }
}

// Update Password
if (isset($_POST['update_password'])) {
    $current_pass = $_POST['current_pass'];
    $new_pass     = $_POST['new_pass'];
    $confirm_pass = $_POST['confirm_pass'];

    if (md5($current_pass) !== $user->password) {
        $msg_error = "Password lama yang Anda masukkan salah.";
    } elseif ($new_pass !== $confirm_pass) {
        $msg_error = "Konfirmasi password baru tidak cocok.";
    } elseif (strlen($new_pass) < 4) {
        $msg_error = "Password baru minimal terdiri dari 4 karakter.";
    } else {
        $hashed = md5($new_pass);
        $update = mysqli_query($conn, "UPDATE users SET password = '$hashed', updated_at = NOW() WHERE id = '$user_id'");
        if ($update) {
            $msg_success = "Password Anda berhasil diganti.";
        } else {
            $msg_error = "Gagal mengganti password: " . mysqli_error($conn);
        }
    }
}

// Hitung total pesanan customer ini
$order_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE customer_phone = '" . mysqli_real_escape_string($conn, $user->phone) . "' OR user_id = '$user_id'"))['total'] ?? 0;
$cart_count = !empty($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="icon" type="image/x-icon" href="assets/book.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <title>Profil Saya | WarungBuku</title>
    <style>
        :root {
            --brand-primary: #0B88D3;
        }
        .profile-card {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        .avatar-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #0B88D3;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-weight: bold;
            margin: 0 auto 15px;
            box-shadow: 0 6px 15px rgba(11, 136, 211, 0.25);
        }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark fixed-top shadow-sm">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
                <img src="assets/book.ico" alt="logo" width="34">
                <span>Warung<span style="color: #0B88D3;">Buku</span></span>
            </a>
            
            <div class="ms-auto d-flex align-items-center gap-2">
                <a class="btn btn-outline-light btn-sm rounded-pill px-3" href="cart.php">
                    <i class="bi bi-cart3 me-1"></i> Keranjang
                    <span class="badge bg-primary rounded-pill ms-1" style="background-color: #0B88D3 !important;"><?= $cart_count ?></span>
                </a>
                <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </nav>
    <div class="py-4"></div>

    <!-- Main Content -->
    <main class="py-4 flex-grow-1">
        <div class="container px-4 px-lg-5">
            
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Beranda</a></li>
                    <li class="breadcrumb-item active text-dark fw-bold" aria-current="page">Profil Saya</li>
                </ol>
            </nav>

            <h2 class="h4 fw-bold mb-4 text-dark"><i class="bi bi-person-circle text-primary me-2"></i>Pengaturan Akun & Profil</h2>

            <?php if(!empty($msg_success)): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= $msg_success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if(!empty($msg_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $msg_error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                
                <!-- Kolom Ringkasan Akun -->
                <div class="col-lg-4">
                    <div class="profile-card p-4 text-center mb-4">
                        <div class="avatar-circle">
                            <?= strtoupper(substr($user->name, 0, 1)) ?>
                        </div>
                        <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($user->name) ?></h5>
                        <p class="text-muted small mb-2">@<?= htmlspecialchars($user->username) ?></p>
                        <span class="badge bg-primary rounded-pill px-3 py-1 mb-4" style="background-color: #0B88D3 !important;">Pelanggan WarungBuku</span>

                        <div class="list-group list-group-flush text-start small border-top pt-3">
                            <a href="my-orders.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                                <span><i class="bi bi-bag-check me-2 text-primary"></i>Riwayat Pesanan</span>
                                <span class="badge bg-secondary rounded-pill"><?= $order_count ?></span>
                            </a>
                            <a href="cart.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                                <span><i class="bi bi-cart3 me-2 text-primary"></i>Keranjang Belanja</span>
                                <span class="badge bg-secondary rounded-pill"><?= $cart_count ?></span>
                            </a>
                            <a href="logout.php" class="list-group-item list-group-item-action text-danger py-3">
                                <i class="bi bi-box-arrow-right me-2"></i>Keluar (Logout)
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Kolom Formulir Edit Data & Password -->
                <div class="col-lg-8">
                    
                    <!-- 1. Edit Data Diri & Alamat Pengiriman -->
                    <div class="profile-card p-4 mb-4">
                        <h5 class="fw-bold mb-3 text-dark pb-2 border-bottom"><i class="bi bi-card-text text-primary me-2"></i>Informasi Kontak & Alamat Pengiriman</h5>
                        
                        <form action="" method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Nama Lengkap</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user->name) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Username</label>
                                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user->username) ?>" readonly disabled>
                                    <small class="text-muted" style="font-size: 0.72rem;">Username tidak dapat diubah.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">No Telepon / WhatsApp</label>
                                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user->phone ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Alamat Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user->email ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Alamat Lengkap Pengiriman</label>
                                    <textarea name="address" class="form-control" rows="3" required placeholder="Alamat rumah atau kantor untuk pengiriman pesanan"><?= htmlspecialchars($user->address ?? '') ?></textarea>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" name="update_profile" class="btn btn-primary rounded-pill px-4" style="background-color: #0B88D3; border: none;">
                                        <i class="bi bi-save me-1"></i> Simpan Perubahan Profil
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- 2. Ganti Password -->
                    <div class="profile-card p-4">
                        <h5 class="fw-bold mb-3 text-dark pb-2 border-bottom"><i class="bi bi-key text-primary me-2"></i>Keamanan & Ganti Password</h5>
                        
                        <form action="" method="POST">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Password Lama</label>
                                    <input type="password" name="current_pass" class="form-control" required placeholder="Password saat ini">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Password Baru</label>
                                    <input type="password" name="new_pass" class="form-control" required placeholder="Minimal 4 karakter">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Ulangi Password Baru</label>
                                    <input type="password" name="confirm_pass" class="form-control" required placeholder="Konfirmasi password">
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" name="update_password" class="btn btn-dark rounded-pill px-4">
                                        <i class="bi bi-shield-lock me-1"></i> Ganti Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>

            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="py-4 bg-dark text-white mt-auto">
        <div class="container text-center text-white-50 small">
            <div>&copy; <?= date('Y') ?> <strong>WarungBuku</strong>. All Rights Reserved.</div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
