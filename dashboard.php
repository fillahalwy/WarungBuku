<?php 
include('connection.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validasi Sesi & Role Admin
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] != true) {
    header("Location: login.php");
    exit();
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

// ----------------------------------------------------
// STATISTIK & RINGKASAN DATA
// ----------------------------------------------------
$admin_name       = $_SESSION['global']->name ?? 'Admin';
$count_products   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM books"))['total']   ?? 0;
$count_categories = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM categories"))['total'] ?? 0;
$count_orders     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders"))['total']     ?? 0;
$count_users      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users"))['total']      ?? 0;
$count_customers  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'customer'"))['total'] ?? 0;
$count_vouchers   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM vouchers WHERE is_active = 1"))['total'] ?? 0;
$total_revenue    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE status IN ('paid', 'processing', 'completed')"))['total'] ?? 0;

// Status Pesanan Breakdown
$order_pending    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'pending'"))['total'] ?? 0;
$order_paid       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'paid'"))['total'] ?? 0;
$order_processing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'processing'"))['total'] ?? 0;
$order_completed  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'completed'"))['total'] ?? 0;

// ----------------------------------------------------
// QUERY DATA AKTIVITAS TERBARU & PERFORMA
// ----------------------------------------------------
// 1. Transaksi Terbaru (Recent Orders)
$recent_orders = mysqli_query($conn, "SELECT * FROM orders ORDER BY created_at DESC LIMIT 6");

// 2. Buku dengan Stok Menipis (Stok <= 10)
$low_stock_books = mysqli_query($conn, "SELECT * FROM books WHERE stock <= 10 ORDER BY stock ASC, title ASC LIMIT 5");

// 3. Voucher Aktif & Kuota Penggunaan
$active_vouchers = mysqli_query($conn, "SELECT * FROM vouchers WHERE is_active = 1 ORDER BY used_count DESC, id DESC LIMIT 4");

// 4. Pengguna Terdaftar Terbaru
$recent_users = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="assets/book.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <title>Dashboard Admin | WarungBuku</title>
    <style>
        :root {
            --brand-primary: #0B88D3;
        }
        .stat-card {
            border: none;
            border-radius: 14px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
        }
        .card-custom {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            background: #ffffff;
        }
        .card-header-custom {
            border-top-left-radius: 14px !important;
            border-top-right-radius: 14px !important;
        }
        .quick-action-btn {
            transition: all 0.2s ease;
            text-decoration: none;
            border-radius: 12px;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }
        .user-avatar-small {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <!-- Sidebar Navigation -->
    <?php include('sidebar.php'); ?>
    
    <!-- Header / Banner Welcome -->
    <header class="bg-dark text-white py-4 shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
        <div class="container-fluid px-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="h3 fw-bold mb-1">Selamat Datang, <?= htmlspecialchars($admin_name) ?>! 👋</h1>
                    <p class="text-white-50 fs-6 mb-0">Pantau performa penjualan, pesanan masuk, ketersediaan stok buku, dan voucher toko buku Anda.</p>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="products.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" style="background-color: #0B88D3; border: none;">
                        <i class="bi bi-plus-lg me-1"></i> Data Produk
                    </a>
                    <a href="orders.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                        <i class="bi bi-cart-check me-1"></i> Transaksi
                    </a>
                    <a href="index.php" target="_blank" class="btn btn-outline-light btn-sm rounded-pill px-3">
                        <i class="bi bi-globe me-1"></i> Lihat Toko
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Section Main Content -->
    <main class="py-4 flex-grow-1">
        <div class="container-fluid px-4">
            
            <!-- 1. KPI Summary Cards Grid -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card stat-card bg-white shadow-sm p-3 h-100 border-start border-4 border-primary">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-2 text-primary me-2">
                                <i class="bi bi-book fs-4"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold" style="font-size: 11px;">TOTAL PRODUK</div>
                                <div class="fs-5 fw-bold text-dark"><?= $count_products ?> <small class="text-muted fs-7" style="font-size: 11px;">Buku</small></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card stat-card bg-white shadow-sm p-3 h-100 border-start border-4 border-info">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-info bg-opacity-10 p-2 text-info me-2">
                                <i class="bi bi-tags fs-4"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold" style="font-size: 11px;">KATEGORI</div>
                                <div class="fs-5 fw-bold text-dark"><?= $count_categories ?> <small class="text-muted fs-7" style="font-size: 11px;">Kategori</small></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card stat-card bg-white shadow-sm p-3 h-100 border-start border-4 border-secondary">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-secondary bg-opacity-10 p-2 text-dark me-2">
                                <i class="bi bi-people fs-4"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold" style="font-size: 11px;">PENGGUNA</div>
                                <div class="fs-5 fw-bold text-dark"><?= $count_users ?> <small class="text-muted fs-7" style="font-size: 11px;">Akun</small></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card stat-card bg-white shadow-sm p-3 h-100 border-start border-4 border-warning">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-2 text-warning me-2">
                                <i class="bi bi-ticket-perforated fs-4"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold" style="font-size: 11px;">VOUCHER</div>
                                <div class="fs-5 fw-bold text-dark"><?= $count_vouchers ?> <small class="text-muted fs-7" style="font-size: 11px;">Aktif</small></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card stat-card bg-white shadow-sm p-3 h-100 border-start border-4 border-warning">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-2 text-warning me-2">
                                <i class="bi bi-receipt fs-4"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold" style="font-size: 11px;">TRANSAKSI</div>
                                <div class="fs-5 fw-bold text-dark"><?= $count_orders ?> <small class="text-muted fs-7" style="font-size: 11px;">Pesanan</small></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card stat-card bg-white shadow-sm p-3 h-100 border-start border-4 border-success">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success bg-opacity-10 p-2 text-success me-2">
                                <i class="bi bi-cash-stack fs-4"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold" style="font-size: 11px;">TOTAL PENDAPATAN</div>
                                <div class="fw-bold text-success" style="font-size: 13px;">Rp <?= number_format($total_revenue, 0, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Status Transaksi Bar -->
            <div class="card card-custom p-3 mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold text-dark small"><i class="bi bi-funnel text-primary me-1"></i>Status Pesanan Terkini:</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <a href="orders.php?status=pending" class="badge bg-warning text-dark text-decoration-none px-3 py-2 rounded-pill">
                            <i class="bi bi-clock me-1"></i> Menunggu Bayar (<?= $order_pending ?>)
                        </a>
                        <a href="orders.php?status=paid" class="badge bg-success text-decoration-none px-3 py-2 rounded-pill">
                            <i class="bi bi-check-circle me-1"></i> Lunas (<?= $order_paid ?>)
                        </a>
                        <a href="orders.php?status=processing" class="badge bg-info text-decoration-none px-3 py-2 rounded-pill">
                            <i class="bi bi-box-seam me-1"></i> Diproses (<?= $order_processing ?>)
                        </a>
                        <a href="orders.php?status=completed" class="badge bg-primary text-decoration-none px-3 py-2 rounded-pill">
                            <i class="bi bi-patch-check me-1"></i> Selesai (<?= $order_completed ?>)
                        </a>
                    </div>
                </div>
            </div>

            <!-- 3. Row 1: Transaksi Terbaru & Peringatan Stok Menipis -->
            <div class="row g-4 mb-4">
                
                <!-- Kolom Transaksi Terbaru -->
                <div class="col-lg-8">
                    <div class="card card-custom h-100">
                        <div class="card-header card-header-custom bg-dark text-white py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="fw-bold fs-6 mb-0">
                                <i class="bi bi-cart-check me-2"></i>Transaksi Pesanan Terbaru
                            </div>
                            <a href="orders.php" class="btn btn-outline-light btn-sm rounded-pill px-3" style="font-size: 12px;">
                                Lihat Semua Transaksi <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light small text-muted">
                                        <tr>
                                            <th class="ps-4">No Invoice</th>
                                            <th>Pembeli</th>
                                            <th>Metode Bayar</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center pe-4">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($recent_orders && mysqli_num_rows($recent_orders) > 0): ?>
                                            <?php while ($ord = mysqli_fetch_assoc($recent_orders)): ?>
                                                <?php
                                                    $st = $ord['status'];
                                                    $st_badge = '<span class="badge bg-secondary">Unknown</span>';
                                                    if ($st === 'pending') $st_badge = '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending</span>';
                                                    elseif ($st === 'paid') $st_badge = '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Lunas</span>';
                                                    elseif ($st === 'processing') $st_badge = '<span class="badge bg-info"><i class="bi bi-box-seam me-1"></i>Diproses</span>';
                                                    elseif ($st === 'completed') $st_badge = '<span class="badge bg-primary"><i class="bi bi-patch-check me-1"></i>Selesai</span>';
                                                    elseif ($st === 'cancelled') $st_badge = '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Batal</span>';
                                                ?>
                                                <tr>
                                                    <td class="ps-4">
                                                        <span class="fw-bold font-monospace text-primary small"><?= htmlspecialchars($ord['id']) ?></span>
                                                        <div class="text-muted" style="font-size: 11px;"><?= date('d/m/Y H:i', strtotime($ord['created_at'])) ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold text-dark small"><?= htmlspecialchars($ord['customer_name']) ?></div>
                                                        <small class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($ord['customer_phone']) ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-dark border small">
                                                            <?= strtoupper($ord['payment_method']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end fw-bold text-primary small">
                                                        Rp <?= number_format($ord['total_amount'], 0, ',', '.') ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= $st_badge ?>
                                                    </td>
                                                    <td class="text-center pe-4">
                                                        <a href="orders.php?search=<?= urlencode($ord['id']) ?>" class="btn btn-outline-dark btn-sm rounded-pill px-2" title="Kelola di Halaman Transaksi" style="font-size: 11px;">
                                                            <i class="bi bi-eye"></i> Detail
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted small">
                                                    <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                                    Belum ada transaksi pesanan yang tercatat.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom Peringatan Stok Buku Menipis -->
                <div class="col-lg-4">
                    <div class="card card-custom h-100">
                        <div class="card-header card-header-custom bg-dark text-white py-3 px-4 d-flex align-items-center justify-content-between">
                            <div class="fw-bold fs-6 mb-0">
                                <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Peringatan Stok Menipis
                            </div>
                            <a href="products.php" class="text-white-50 text-decoration-none small" style="font-size: 11px;">Kelola Stok</a>
                        </div>
                        <div class="card-body p-3">
                            <p class="text-muted small mb-3">Daftar buku dengan sisa stok $\le 10$ eksemplar atau habis yang perlu segera di-restock:</p>
                            
                            <div class="d-flex flex-column gap-2">
                                <?php if ($low_stock_books && mysqli_num_rows($low_stock_books) > 0): ?>
                                    <?php while ($b = mysqli_fetch_assoc($low_stock_books)): ?>
                                        <?php 
                                            $stk = (int)$b['stock'];
                                            $badge_stk = ($stk == 0) ? 'bg-danger text-white' : (($stk <= 5) ? 'bg-warning text-dark' : 'bg-secondary text-white');
                                        ?>
                                        <div class="p-2 border rounded-3 bg-light d-flex align-items-center justify-content-between gap-2">
                                            <div class="text-truncate" style="max-width: 190px;">
                                                <div class="fw-semibold text-dark small text-truncate" title="<?= htmlspecialchars($b['title']) ?>">
                                                    <?= htmlspecialchars($b['title']) ?>
                                                </div>
                                                <small class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($b['category']) ?></small>
                                            </div>
                                            <div class="text-end flex-shrink-0">
                                                <span class="badge <?= $badge_stk ?> px-2 py-1 small">
                                                    <?= ($stk == 0) ? 'Habis (0)' : "Stok: $stk" ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-4 text-success small">
                                        <i class="bi bi-check-circle fs-3 d-block mb-1"></i>
                                        Seluruh stok buku dalam kondisi aman!
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-3 pt-3 border-top text-center">
                                <a href="products.php" class="btn btn-outline-dark btn-sm rounded-pill w-100" style="font-size: 12px;">
                                    <i class="bi bi-book-half me-1"></i> Buka Katalog Data Produk
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- 4. Row 2: Voucher Aktif & Pengguna Terbaru -->
            <div class="row g-4 mb-4">
                
                <!-- Kolom Voucher Promo Toko -->
                <div class="col-lg-6">
                    <div class="card card-custom h-100">
                        <div class="card-header card-header-custom bg-dark text-white py-3 px-4 d-flex align-items-center justify-content-between">
                            <div class="fw-bold fs-6 mb-0">
                                <i class="bi bi-ticket-perforated me-2"></i>Voucher Promo Toko Aktif
                            </div>
                            <a href="vouchers.php" class="btn btn-outline-light btn-sm rounded-pill px-3" style="font-size: 12px;">
                                Kelola Voucher <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="card-body p-3">
                            <div class="d-flex flex-column gap-2">
                                <?php if ($active_vouchers && mysqli_num_rows($active_vouchers) > 0): ?>
                                    <?php while ($v = mysqli_fetch_assoc($active_vouchers)): ?>
                                        <div class="p-3 border rounded-3 bg-light d-flex align-items-center justify-content-between gap-2">
                                            <div>
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <span class="badge bg-primary font-monospace" style="background-color: #0B88D3 !important;"><?= htmlspecialchars($v['code']) ?></span>
                                                    <span class="fw-bold text-dark small"><?= htmlspecialchars($v['name']) ?></span>
                                                </div>
                                                <div class="text-muted small" style="font-size: 11px;">
                                                    <?php if ($v['discount_type'] === 'percent'): ?>
                                                        Diskon <strong><?= (float)$v['discount_value'] ?>%</strong> (Maks. Rp <?= number_format($v['max_discount'], 0, ',', '.') ?>)
                                                    <?php else: ?>
                                                        Potongan <strong>Rp <?= number_format($v['discount_value'], 0, ',', '.') ?></strong>
                                                    <?php endif; ?>
                                                    &bull; Min. Belanja Rp <?= number_format($v['min_spend'], 0, ',', '.') ?>
                                                </div>
                                            </div>
                                            <div class="text-end flex-shrink-0">
                                                <span class="badge bg-success rounded-pill px-2 py-1 small">Terpakai: <?= $v['used_count'] ?>/<?= $v['quota'] ?></span>
                                                <div class="text-muted" style="font-size: 10px; margin-top: 3px;">Exp: <?= !empty($v['expiry_date']) ? date('d/m/Y', strtotime($v['expiry_date'])) : 'Selamanya' ?></div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted small">
                                        <i class="bi bi-ticket-perforated fs-3 d-block mb-1"></i>
                                        Belum ada voucher promo yang aktif.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom Pengguna Terdaftar Terbaru -->
                <div class="col-lg-6">
                    <div class="card card-custom h-100">
                        <div class="card-header card-header-custom bg-dark text-white py-3 px-4 d-flex align-items-center justify-content-between">
                            <div class="fw-bold fs-6 mb-0">
                                <i class="bi bi-people me-2"></i>Pengguna Terdaftar Terbaru
                            </div>
                            <a href="users.php" class="btn btn-outline-light btn-sm rounded-pill px-3" style="font-size: 12px;">
                                Kelola Pengguna <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="card-body p-3">
                            <div class="d-flex flex-column gap-2">
                                <?php if ($recent_users && mysqli_num_rows($recent_users) > 0): ?>
                                    <?php while ($u = mysqli_fetch_assoc($recent_users)): ?>
                                        <div class="p-2 border rounded-3 bg-light d-flex align-items-center justify-content-between gap-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="user-avatar-small bg-primary text-white" style="background-color: <?= ($u['role'] === 'admin') ? '#212529' : '#0B88D3' ?> !important;">
                                                    <?= strtoupper(substr($u['name'] ?? 'U', 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark small mb-0"><?= htmlspecialchars($u['name']) ?></div>
                                                    <small class="text-muted" style="font-size: 11px;">@<?= htmlspecialchars($u['username']) ?> &bull; <?= htmlspecialchars($u['phone'] ?? '-') ?></small>
                                                </div>
                                            </div>
                                            <div class="text-end flex-shrink-0">
                                                <?php if ($u['role'] === 'admin'): ?>
                                                    <span class="badge bg-dark rounded-pill px-2 py-1" style="font-size: 10px;">ADMIN</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary rounded-pill px-2 py-1" style="background-color: #0B88D3 !important; font-size: 10px;">PELANGGAN</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted small">
                                        Belum ada pengguna terdaftar.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- 5. Quick Actions Shortcut Grid -->
            <div class="card card-custom p-4 mb-4">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Menu Pintas Navigasi Cepat Admin</h6>
                <div class="row g-3">
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="products.php" class="quick-action-btn card bg-light p-3 text-center text-dark h-100 border">
                            <i class="bi bi-book-half fs-3 text-primary mb-1"></i>
                            <span class="small fw-semibold">Kelola Produk</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="categories.php" class="quick-action-btn card bg-light p-3 text-center text-dark h-100 border">
                            <i class="bi bi-tags fs-3 text-info mb-1"></i>
                            <span class="small fw-semibold">Data Kategori</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="orders.php" class="quick-action-btn card bg-light p-3 text-center text-dark h-100 border">
                            <i class="bi bi-cart-check fs-3 text-success mb-1"></i>
                            <span class="small fw-semibold">Data Transaksi</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="vouchers.php" class="quick-action-btn card bg-light p-3 text-center text-dark h-100 border">
                            <i class="bi bi-ticket-perforated fs-3 text-warning mb-1"></i>
                            <span class="small fw-semibold">Voucher & Diskon</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="users.php" class="quick-action-btn card bg-light p-3 text-center text-dark h-100 border">
                            <i class="bi bi-people fs-3 text-secondary mb-1"></i>
                            <span class="small fw-semibold">Data Pengguna</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="profile.php" class="quick-action-btn card bg-light p-3 text-center text-dark h-100 border">
                            <i class="bi bi-person-gear fs-3 text-dark mb-1"></i>
                            <span class="small fw-semibold">Profil Admin</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer-->
    <footer class="py-4 bg-dark mt-auto text-white">
        <div class="container text-center text-white-50 small">
            <div>&copy; <?= date('Y') ?> <strong>WarungBuku</strong> Admin Panel. All Rights Reserved.</div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>