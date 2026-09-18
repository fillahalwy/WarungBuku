<?php
include("connection.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validasi Sesi
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] != true) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id'];
$user_data = $_SESSION['global'];
$user_phone = mysqli_real_escape_string($conn, $user_data->phone ?? '');

// Query daftar order milik user
$orders_query = mysqli_query($conn, "SELECT * FROM orders 
                                      WHERE user_id = '$user_id' OR customer_phone = '$user_phone' 
                                      ORDER BY created_at DESC");

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
    <title>Riwayat Pesanan Saya | WarungBuku</title>
    <style>
        :root {
            --brand-primary: #0B88D3;
        }
        .order-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }
        .order-card:hover {
            border-color: var(--brand-primary);
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
                <a href="customer-profile.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="bi bi-person me-1"></i> Profil
                </a>
                <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="bi bi-house me-1"></i> Beranda
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
                    <li class="breadcrumb-item"><a href="customer-profile.php" class="text-decoration-none text-muted">Profil</a></li>
                    <li class="breadcrumb-item active text-dark fw-bold" aria-current="page">Pesanan Saya</li>
                </ol>
            </nav>

            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                <div>
                    <h2 class="h4 fw-bold mb-1 text-dark"><i class="bi bi-bag-check text-primary me-2"></i>Riwayat Transaksi Pesanan</h2>
                    <p class="text-muted small mb-0">Pantau status transaksi belanja buku dan pembayaran Anda.</p>
                </div>
                <a href="index.php" class="btn btn-dark btn-sm rounded-pill px-3">
                    <i class="bi bi-plus-lg me-1"></i> Belanja Buku Baru
                </a>
            </div>

            <?php if($orders_query && mysqli_num_rows($orders_query) > 0): ?>
                <div class="row g-3">
                    <?php while($ord = mysqli_fetch_assoc($orders_query)): ?>
                        <?php
                            $order_id = $ord['id'];
                            $status = $ord['status'];

                            // Ambil item pesanan
                            $it_res = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id = '$order_id'");
                            $items = [];
                            if ($it_res) {
                                while ($item = mysqli_fetch_assoc($it_res)) {
                                    $items[] = $item;
                                }
                            }

                            // Status badge
                            $status_badge = '<span class="badge bg-secondary">Unknown</span>';
                            if ($status === 'pending') {
                                $status_badge = '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Menunggu Pembayaran</span>';
                            } elseif ($status === 'paid') {
                                $status_badge = '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Lunas (Paid)</span>';
                            } elseif ($status === 'processing') {
                                $status_badge = '<span class="badge bg-info"><i class="bi bi-box-seam me-1"></i>Sedang Diproses</span>';
                            } elseif ($status === 'completed') {
                                $status_badge = '<span class="badge bg-primary"><i class="bi bi-patch-check me-1"></i>Selesai</span>';
                            } elseif ($status === 'cancelled') {
                                $status_badge = '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Dibatalkan</span>';
                            }
                        ?>
                        <div class="col-12">
                            <div class="order-card p-4">
                                <div class="d-flex justify-content-between align-items-center pb-3 mb-3 border-bottom flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="fw-bold font-monospace text-primary fs-6"><?= htmlspecialchars($order_id) ?></span>
                                        <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y, H:i', strtotime($ord['created_at'])) ?> WIB</span>
                                    </div>
                                    <div>
                                        <?= $status_badge ?>
                                    </div>
                                </div>

                                <div class="row align-items-center g-3">
                                    <div class="col-md-7">
                                        <h6 class="fw-bold text-dark small text-uppercase mb-2">Item yang Dipesan (<?= count($items) ?> Judul):</h6>
                                        <ul class="list-unstyled mb-0 small">
                                            <?php foreach($items as $i): ?>
                                                <li class="mb-1 text-dark">
                                                    &bull; <strong><?= htmlspecialchars($i['book_title']) ?></strong> (<?= $i['quantity'] ?>x &bull; Rp <?= number_format($i['subtotal'], 0, ',', '.') ?>)
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>

                                    <div class="col-md-5 text-md-end">
                                        <div class="small text-muted mb-1">Total Pembayaran:</div>
                                        <div class="fs-4 fw-bold text-primary mb-3" style="color: #0B88D3 !important;">
                                            Rp <?= number_format($ord['total_amount'], 0, ',', '.') ?>
                                        </div>

                                        <div class="d-flex gap-2 justify-content-md-end">
                                            <?php if ($status === 'pending'): ?>
                                                <a href="payment.php?order_id=<?= urlencode($order_id) ?>" class="btn btn-warning btn-sm rounded-pill px-4 fw-bold">
                                                    <i class="bi bi-credit-card me-1"></i> Bayar Sekarang
                                                </a>
                                            <?php else: ?>
                                                <a href="order-success.php?order_id=<?= urlencode($order_id) ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                                                    <i class="bi bi-receipt me-1"></i> Lihat Invoice
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded-3 shadow-sm border p-5 my-4">
                    <div class="text-muted mb-3 fs-1"><i class="bi bi-bag-x"></i></div>
                    <h4 class="fw-bold text-dark mb-2">Belum Ada Transaksi</h4>
                    <p class="text-muted mb-4">Anda belum memiliki riwayat pembelian buku. Yuk mulai pilih buku favorit Anda!</p>
                    <a href="index.php" class="btn btn-dark rounded-pill px-4">
                        <i class="bi bi-book me-1"></i> Jelajahi Buku Sekarang
                    </a>
                </div>
            <?php endif; ?>

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
