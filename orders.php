<?php
include("connection.php");
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
// HANDLER CRUD & STATUS TRANSAKSI
// ----------------------------------------------------

// 1. Update Status Pesanan
if (isset($_POST['update_status'])) {
    $order_id   = mysqli_real_escape_string($conn, $_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);

    $update = mysqli_query($conn, "UPDATE orders SET status = '$new_status', updated_at = NOW() WHERE id = '$order_id'");
    if ($update) {
        $_SESSION['msg_success'] = "Status pesanan <strong>" . htmlspecialchars($order_id) . "</strong> berhasil diperbarui menjadi <strong>" . strtoupper($new_status) . "</strong>.";
    } else {
        $_SESSION['msg_error'] = "Gagal memperbarui status: " . mysqli_error($conn);
    }
    header("Location: orders.php");
    exit();
}

// 2. Hapus Data Pesanan
if (isset($_GET['delete_order'])) {
    $order_id = mysqli_real_escape_string($conn, $_GET['delete_order']);
    $delete = mysqli_query($conn, "DELETE FROM orders WHERE id = '$order_id'");
    if ($delete) {
        $_SESSION['msg_success'] = "Data transaksi <strong>" . htmlspecialchars($order_id) . "</strong> berhasil dihapus.";
    } else {
        $_SESSION['msg_error'] = "Gagal menghapus transaksi: " . mysqli_error($conn);
    }
    header("Location: orders.php");
    exit();
}

// ----------------------------------------------------
// FILTER, PENCARIAN & PAGINASI
// ----------------------------------------------------
$limit_param = isset($_GET['limit']) ? $_GET['limit'] : '10';
$limit = ($limit_param === 'all') ? 999999 : (int)$limit_param;
if ($limit < 1) $limit = 10;

$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search        = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, trim($_GET['status'])) : '';
$date_filter   = isset($_GET['date_filter']) ? mysqli_real_escape_string($conn, trim($_GET['date_filter'])) : '';

$where_clause = " WHERE 1=1 ";
if (!empty($search)) {
    $where_clause .= " AND (o.id LIKE '%$search%' OR o.customer_name LIKE '%$search%' OR o.customer_phone LIKE '%$search%' OR o.customer_email LIKE '%$search%') ";
}
if (!empty($status_filter) && $status_filter !== 'all') {
    $where_clause .= " AND o.status = '$status_filter' ";
}
if ($date_filter === 'today') {
    $where_clause .= " AND DATE(o.created_at) = CURDATE() ";
} elseif ($date_filter === '7days') {
    $where_clause .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ";
} elseif ($date_filter === 'this_month') {
    $where_clause .= " AND MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE()) ";
}

// Hitung Statistik
$stat_total_res   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM orders"))['total'] ?? 0;
$stat_pending_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE status = 'pending'"))['total'] ?? 0;
$stat_paid_res    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE status IN ('paid', 'processing', 'completed')"))['total'] ?? 0;
$stat_revenue_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE status IN ('paid', 'processing', 'completed')"))['total'] ?? 0;

// Total Data Terfilter
$total_records = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM orders o $where_clause"))['total'] ?? 0;
$total_pages   = ($limit_param === 'all') ? 1 : ceil($total_records / $limit);

// Query Data Orders dengan agregasi total kuantitas produk & detail user
$orders_query = mysqli_query($conn, "SELECT o.*, 
                                        u.username AS customer_username,
                                        (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS total_products,
                                        (SELECT COUNT(oi.id) FROM order_items oi WHERE oi.order_id = o.id) AS total_item_types
                                     FROM orders o 
                                     LEFT JOIN users u ON o.user_id = u.id 
                                     $where_clause 
                                     ORDER BY o.created_at DESC 
                                     LIMIT $offset, $limit");

$orders_list = [];
if ($orders_query && mysqli_num_rows($orders_query) > 0) {
    while ($row = mysqli_fetch_assoc($orders_query)) {
        $orders_list[] = $row;
    }
}
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
    <title>Data Riwayat Transaksi | WarungBuku Admin</title>
    <style>
        .stat-card {
            border: none;
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
        }
        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .card-header-custom {
            border-top-left-radius: 12px !important;
            border-top-right-radius: 12px !important;
        }
        .invoice-link {
            text-decoration: none;
            font-weight: 700;
            font-family: monospace;
            color: #0B88D3;
            transition: all 0.2s ease;
        }
        .invoice-link:hover {
            text-decoration: underline;
            color: #086196;
        }
        .table-orders thead th {
            vertical-align: middle;
            font-size: 13px;
            letter-spacing: 0.3px;
            padding: 12px 14px;
        }
        .table-orders tbody td {
            vertical-align: middle;
            padding: 12px 14px;
        }
        .order-detail-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
        }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <!-- Sidebar Navigation -->
    <?php include('sidebar.php'); ?>

    <!-- Header Banner -->
    <header class="bg-dark text-white py-4 shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
        <div class="container-fluid px-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="h3 fw-bold mb-1"><i class="bi bi-receipt-cutoff me-2"></i>Data Riwayat Transaksi</h1>
                    <p class="text-white-50 mb-0 small">Kelola seluruh riwayat transaksi pesanan buku, status pembayaran, dan rincian pembeli.</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <section class="py-4">
        <div class="container-fluid px-4">

            <!-- Alerts -->
            <?php if(isset($_SESSION['msg_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4 shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= $_SESSION['msg_success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['msg_success']); ?>
            <?php endif; ?>

            <?php if(isset($_SESSION['msg_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $_SESSION['msg_error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['msg_error']); ?>
            <?php endif; ?>

            <!-- Ringkasan Statistik Transaksi -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card bg-white shadow-sm p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary me-3">
                                <i class="bi bi-receipt fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">TOTAL TRANSAKSI</div>
                                <div class="fs-4 fw-bold text-dark"><?= number_format($stat_total_res, 0, ',', '.') ?> Order</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card bg-white shadow-sm p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success me-3">
                                <i class="bi bi-cash-stack fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">TOTAL PENDAPATAN</div>
                                <div class="fs-5 fw-bold text-success">Rp <?= number_format($stat_revenue_res, 0, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card bg-white shadow-sm p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning me-3">
                                <i class="bi bi-clock-history fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">MENUNGGU BAYAR</div>
                                <div class="fs-4 fw-bold text-dark"><?= number_format($stat_pending_res, 0, ',', '.') ?> Order</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card bg-white shadow-sm p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info me-3">
                                <i class="bi bi-bag-check fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">LUNAS / PROSES</div>
                                <div class="fs-4 fw-bold text-dark"><?= number_format($stat_paid_res, 0, ',', '.') ?> Order</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Transaksi -->
            <div class="card card-custom mb-5">
                <div class="card-header card-header-custom bg-dark text-white py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="fw-bold fs-5 mb-0"><i class="bi bi-list-check me-2"></i>Daftar Riwayat Transaksi</div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary rounded-pill px-3 py-2">Total Terdata: <?= $total_records ?> Transaksi</span>
                    </div>
                </div>
                <div class="card-body p-4">
                    
                    <!-- Search & Filter Controls -->
                    <form action="" method="GET" class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Cari No Invoice, Nama, No HP..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">-- Semua Status --</option>
                                <option value="pending" <?= ($status_filter === 'pending') ? 'selected' : '' ?>>Menunggu Pembayaran (Pending)</option>
                                <option value="paid" <?= ($status_filter === 'paid') ? 'selected' : '' ?>>Lunas (Paid)</option>
                                <option value="processing" <?= ($status_filter === 'processing') ? 'selected' : '' ?>>Sedang Diproses (Processing)</option>
                                <option value="completed" <?= ($status_filter === 'completed') ? 'selected' : '' ?>>Selesai (Completed)</option>
                                <option value="cancelled" <?= ($status_filter === 'cancelled') ? 'selected' : '' ?>>Dibatalkan (Cancelled)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="date_filter" class="form-select">
                                <option value="">-- Semua Waktu --</option>
                                <option value="today" <?= ($date_filter === 'today') ? 'selected' : '' ?>>Hari Ini</option>
                                <option value="7days" <?= ($date_filter === '7days') ? 'selected' : '' ?>>7 Hari Terakhir</option>
                                <option value="this_month" <?= ($date_filter === 'this_month') ? 'selected' : '' ?>>Bulan Ini</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-dark flex-grow-1"><i class="bi bi-funnel me-1"></i> Filter</button>
                            <?php if(!empty($search) || !empty($status_filter) || !empty($date_filter) || $limit_param !== '10'): ?>
                                <a href="orders.php" class="btn btn-outline-secondary" title="Reset Filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                            <?php endif; ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-dark dropdown-toggle" type="button" id="dropdownLimit" data-bs-toggle="dropdown" aria-expanded="false" title="Jumlah data per halaman">
                                    <?= ($limit_param === 'all') ? 'Semua' : $limit . '/hal' ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownLimit">
                                    <li><a class="dropdown-item <?= ($limit_param === '10') ? 'active' : '' ?>" href="?search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&date_filter=<?= urlencode($date_filter) ?>&limit=10">10 data / hal</a></li>
                                    <li><a class="dropdown-item <?= ($limit_param === '25') ? 'active' : '' ?>" href="?search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&date_filter=<?= urlencode($date_filter) ?>&limit=25">25 data / hal</a></li>
                                    <li><a class="dropdown-item <?= ($limit_param === '50') ? 'active' : '' ?>" href="?search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&date_filter=<?= urlencode($date_filter) ?>&limit=50">50 data / hal</a></li>
                                    <li><a class="dropdown-item <?= ($limit_param === 'all') ? 'active' : '' ?>" href="?search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&date_filter=<?= urlencode($date_filter) ?>&limit=all">Tampilkan Semua Data</a></li>
                                </ul>
                            </div>
                        </div>
                    </form>

                    <!-- Table Data Transaksi (HANYA: Invoice, Tanggal, Nama, Metode, Total Produk, Total Tagihan, Status, Aksi) -->
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle border mb-0 table-orders">
                            <thead class="table-dark">
                                <tr>
                                    <th>Invoice</th>
                                    <th>Tanggal</th>
                                    <th>Nama</th>
                                    <th>Metode</th>
                                    <th class="text-center">Total Produk</th>
                                    <th class="text-end">Total Tagihan</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width: 140px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($orders_list)): ?>
                                    <?php foreach($orders_list as $row): ?>
                                        <?php
                                            $order_id   = $row['id'];
                                            $modal_id   = preg_replace('/[^a-zA-Z0-9]/', '', $order_id);
                                            $status     = $row['status'];
                                            $total_prod = (int)($row['total_products'] ?? 0);
                                            
                                            // Badge Status Styling
                                            $status_badge = '<span class="badge bg-secondary">Unknown</span>';
                                            if ($status === 'pending') {
                                                $status_badge = '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending</span>';
                                            } elseif ($status === 'paid') {
                                                $status_badge = '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Lunas</span>';
                                            } elseif ($status === 'processing') {
                                                $status_badge = '<span class="badge bg-info text-white"><i class="bi bi-box-seam me-1"></i>Diproses</span>';
                                            } elseif ($status === 'completed') {
                                                $status_badge = '<span class="badge bg-primary"><i class="bi bi-patch-check me-1"></i>Selesai</span>';
                                            } elseif ($status === 'cancelled') {
                                                $status_badge = '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Batal</span>';
                                            }

                                            // Format Metode Bayar
                                            $method_name = strtoupper($row['payment_method']);
                                            $channel_name = !empty($row['payment_channel']) ? htmlspecialchars($row['payment_channel']) : '';
                                            $metode_label = $channel_name ? "$method_name ($channel_name)" : $method_name;
                                        ?>
                                        <tr>
                                            <!-- 1. Invoice -->
                                            <td>
                                                <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalDetail<?= $modal_id ?>" class="invoice-link" title="Klik untuk lihat detail">
                                                    <?= htmlspecialchars($row['id']) ?>
                                                </a>
                                            </td>

                                            <!-- 2. Tanggal -->
                                            <td class="small text-muted">
                                                <i class="bi bi-calendar-event me-1"></i><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>
                                            </td>

                                            <!-- 3. Nama -->
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($row['customer_name']) ?></div>
                                                <?php if(!empty($row['customer_username'])): ?>
                                                    <small class="text-primary"><i class="bi bi-person-check me-1"></i>@<?= htmlspecialchars($row['customer_username']) ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted"><i class="bi bi-person me-1"></i>Tamu</small>
                                                <?php endif; ?>
                                            </td>

                                            <!-- 4. Metode -->
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?= $metode_label ?>
                                                </span>
                                            </td>

                                            <!-- 5. Total Produk -->
                                            <td class="text-center">
                                                <span class="badge bg-secondary rounded-pill px-2 py-1">
                                                    <i class="bi bi-book me-1"></i><?= $total_prod ?> Buku
                                                </span>
                                            </td>

                                            <!-- 6. Total Tagihan -->
                                            <td class="text-end fw-bold text-primary">
                                                Rp <?= number_format($row['total_amount'], 0, ',', '.') ?>
                                            </td>

                                            <!-- 7. Status -->
                                            <td class="text-center">
                                                <?= $status_badge ?>
                                            </td>

                                            <!-- 8. Aksi -->
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalDetail<?= $modal_id ?>" title="Lihat Detail Lengkap">
                                                        <i class="bi bi-eye-fill"></i>
                                                    </button>
                                                    <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#modalStatus<?= $modal_id ?>" title="Ubah Status">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <a href="print-invoice.php?order_id=<?= urlencode($order_id) ?>" target="_blank" class="btn btn-outline-secondary" title="Cetak Struk / Invoice">
                                                        <i class="bi bi-printer-fill"></i>
                                                    </a>
                                                    <a href="orders.php?delete_order=<?= urlencode($order_id) ?>" class="btn btn-outline-danger" onclick="return confirm('Hapus data riwayat transaksi <?= $order_id ?>?')" title="Hapus">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                            <div class="fw-semibold fs-6">Belum ada data riwayat transaksi</div>
                                            <p class="small text-muted mb-0">Transaksi yang dilakukan oleh pembeli akan tercatat secara otomatis di sini.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if($total_pages > 1 && $limit_param !== 'all'): ?>
                        <nav class="mt-4" aria-label="Navigasi Halaman Transaksi">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&date_filter=<?= urlencode($date_filter) ?>&limit=<?= urlencode($limit_param) ?>">&laquo; Prev</a>
                                </li>
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&date_filter=<?= urlencode($date_filter) ?>&limit=<?= urlencode($limit_param) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&date_filter=<?= urlencode($date_filter) ?>&limit=<?= urlencode($limit_param) ?>">Next &raquo;</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </section>

    <!-- ==================================================== -->
    <!-- MODALS CONTAINER (Ditempatkan di luar tabel untuk validitas HTML) -->
    <!-- ==================================================== -->
    <?php if(!empty($orders_list)): ?>
        <?php foreach($orders_list as $row): ?>
            <?php
                $order_id   = $row['id'];
                $modal_id   = preg_replace('/[^a-zA-Z0-9]/', '', $order_id);
                $status     = $row['status'];
                $total_prod = (int)($row['total_products'] ?? 0);
                
                // Badge Status Styling
                $status_badge = '<span class="badge bg-secondary">Unknown</span>';
                if ($status === 'pending') {
                    $status_badge = '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending</span>';
                } elseif ($status === 'paid') {
                    $status_badge = '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Lunas</span>';
                } elseif ($status === 'processing') {
                    $status_badge = '<span class="badge bg-info text-white"><i class="bi bi-box-seam me-1"></i>Diproses</span>';
                } elseif ($status === 'completed') {
                    $status_badge = '<span class="badge bg-primary"><i class="bi bi-patch-check me-1"></i>Selesai</span>';
                } elseif ($status === 'cancelled') {
                    $status_badge = '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Batal</span>';
                }

                // Ambil Item Order & Info Buku untuk Modal Detail
                $items_q = mysqli_query($conn, "SELECT oi.*, b.image, b.category, b.author 
                                                FROM order_items oi 
                                                LEFT JOIN books b ON oi.book_id = b.id 
                                                WHERE oi.order_id = '$order_id'");
                $items_list = [];
                $subtotal_sum = 0;
                if ($items_q) {
                    while ($it = mysqli_fetch_assoc($items_q)) {
                        $items_list[] = $it;
                        $subtotal_sum += (float)$it['subtotal'];
                    }
                }

                // Bersihkan nomor WhatsApp untuk link direct chat
                $clean_phone = preg_replace('/[^0-9]/', '', $row['customer_phone']);
                if (str_starts_with($clean_phone, '0')) {
                    $clean_phone = '62' . substr($clean_phone, 1);
                }
                $wa_chat_msg = "Halo Kak " . $row['customer_name'] . ", kami dari Admin WarungBuku ingin mengonfirmasi pesanan dengan No Invoice *" . $row['id'] . "* (Status: *" . strtoupper($row['status']) . "*).";
                $wa_url = "https://wa.me/" . $clean_phone . "?text=" . urlencode($wa_chat_msg);
            ?>

            <!-- MODAL DETAIL TRANSAKSI LENGKAP -->
            <div class="modal fade" id="modalDetail<?= $modal_id ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-dark text-white">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-receipt fs-5"></i>
                                <div>
                                    <h5 class="modal-title fw-bold mb-0">Detail Transaksi: <?= htmlspecialchars($order_id) ?></h5>
                                    <span class="small text-white-50">Dibuat pada <?= date('d F Y, H:i', strtotime($row['created_at'])) ?> WIB</span>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            
                            <!-- Bagian 1: Data Pembeli & Pembayaran -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="order-detail-card h-100">
                                        <h6 class="fw-bold text-dark text-uppercase small mb-3 border-bottom pb-2">
                                            <i class="bi bi-person-fill text-primary me-1"></i> Data Pembeli & Penerima
                                        </h6>
                                        <div class="mb-1"><strong>Nama:</strong> <?= htmlspecialchars($row['customer_name']) ?></div>
                                        <div class="mb-1"><strong>Akun:</strong> <?= !empty($row['customer_username']) ? '@' . htmlspecialchars($row['customer_username']) . ' (Member)' : '<span class="text-muted">Tamu / Belanja Langsung</span>' ?></div>
                                        <div class="mb-1"><strong>No. HP / WA:</strong> <?= htmlspecialchars($row['customer_phone']) ?></div>
                                        <div class="mb-2"><strong>Email:</strong> <?= !empty($row['customer_email']) ? htmlspecialchars($row['customer_email']) : '<span class="text-muted">-</span>' ?></div>
                                        
                                        <div class="pt-2 border-top">
                                            <strong><i class="bi bi-geo-alt-fill text-danger me-1"></i> Alamat Pengiriman:</strong>
                                            <div class="text-muted small mt-1 bg-white p-2 rounded border"><?= nl2br(htmlspecialchars($row['customer_address'])) ?></div>
                                        </div>

                                        <?php if(!empty($row['notes'])): ?>
                                            <div class="mt-2 text-muted small bg-light p-2 rounded border">
                                                <em><strong>Catatan:</strong> <?= htmlspecialchars($row['notes']) ?></em>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="order-detail-card h-100">
                                        <h6 class="fw-bold text-dark text-uppercase small mb-3 border-bottom pb-2">
                                            <i class="bi bi-credit-card-2-front-fill text-primary me-1"></i> Status & Pembayaran
                                        </h6>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">Status Pesanan:</span>
                                            <div><?= $status_badge ?></div>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-muted">Metode Bayar:</span>
                                            <span class="fw-bold text-dark"><?= strtoupper($row['payment_method']) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-muted">Channel / Bank:</span>
                                            <span class="fw-semibold"><?= htmlspecialchars($row['payment_channel'] ?? 'QRIS Instant') ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-muted">Total Kuantitas:</span>
                                            <span class="fw-bold text-dark"><?= $total_prod ?> Buku (<?= count($items_list) ?> Judul)</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-muted">Waktu Dibuat:</span>
                                            <span class="small"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?> WIB</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Update Terakhir:</span>
                                            <span class="small"><?= date('d/m/Y H:i', strtotime($row['updated_at'])) ?> WIB</span>
                                        </div>

                                        <div class="pt-3 border-top d-grid gap-2">
                                            <a href="<?= $wa_url ?>" target="_blank" class="btn btn-success btn-sm rounded-pill">
                                                <i class="bi bi-whatsapp me-1"></i> Hubungi Pembeli via WhatsApp
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bagian 2: Item Buku yang Dipesan -->
                            <h6 class="fw-bold text-dark text-uppercase small mb-2">
                                <i class="bi bi-book-half text-primary me-1"></i> Rincian Buku yang Dipesan:
                            </h6>
                            <div class="table-responsive mb-3">
                                <table class="table table-bordered table-sm align-middle mb-0">
                                    <thead class="table-light small">
                                        <tr>
                                            <th class="text-center" style="width: 40px;">No</th>
                                            <th>Judul Buku</th>
                                            <th class="text-center" style="width: 120px;">Harga Satuan</th>
                                            <th class="text-center" style="width: 70px;">Qty</th>
                                            <th class="text-end" style="width: 130px;">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $it_num = 1; foreach ($items_list as $it): ?>
                                            <tr>
                                                <td class="text-center small text-muted"><?= $it_num++ ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if (!empty($it['image']) && file_exists('assets/img/buku/' . $it['image'])): ?>
                                                            <img src="assets/img/buku/<?= htmlspecialchars($it['image']) ?>" alt="cover" width="36" height="48" class="rounded object-fit-cover shadow-sm border">
                                                        <?php else: ?>
                                                            <div class="bg-light border rounded d-flex align-items-center justify-content-center text-muted" style="width: 36px; height: 48px;">
                                                                <i class="bi bi-book"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <div class="fw-bold text-dark"><?= htmlspecialchars($it['book_title']) ?></div>
                                                            <?php if(!empty($it['category'])): ?>
                                                                <span class="badge bg-light text-muted border" style="font-size: 10px;"><?= htmlspecialchars($it['category']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center small">Rp <?= number_format($it['price'], 0, ',', '.') ?></td>
                                                <td class="text-center fw-bold"><?= $it['quantity'] ?></td>
                                                <td class="text-end fw-bold">Rp <?= number_format($it['subtotal'], 0, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="text-end text-muted small">Subtotal Produk:</td>
                                            <td class="text-end small fw-semibold">Rp <?= number_format($subtotal_sum, 0, ',', '.') ?></td>
                                        </tr>
                                        <?php if (!empty($row['discount_amount']) && (float)$row['discount_amount'] > 0): ?>
                                            <tr>
                                                <td colspan="4" class="text-end text-success small">
                                                    Potongan Kupon Diskon (<strong><?= htmlspecialchars($row['voucher_code'] ?? 'PROMO') ?></strong>):
                                                </td>
                                                <td class="text-end text-success small fw-bold">- Rp <?= number_format($row['discount_amount'], 0, ',', '.') ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr class="table-light">
                                            <td colspan="4" class="text-end fw-bold fs-6">TOTAL TAGIHAN:</td>
                                            <td class="text-end fw-bolder fs-6 text-primary" style="color: #0B88D3 !important;">
                                                Rp <?= number_format($row['total_amount'], 0, ',', '.') ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                        </div>
                        <div class="modal-footer bg-light justify-content-between">
                            <div>
                                <a href="print-invoice.php?order_id=<?= urlencode($order_id) ?>" target="_blank" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                                    <i class="bi bi-printer me-1"></i> Cetak Dokumen Invoice
                                </a>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalStatus<?= $modal_id ?>">
                                    <i class="bi bi-pencil-square me-1"></i> Ubah Status
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL UBAH STATUS PESANAN -->
            <div class="modal fade" id="modalStatus<?= $modal_id ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <form action="orders.php" method="POST">
                            <div class="modal-header bg-dark text-white">
                                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Ubah Status Transaksi</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">No Invoice:</label>
                                    <input type="text" class="form-control font-monospace fw-bold bg-light" value="<?= htmlspecialchars($order_id) ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">Nama Pembeli:</label>
                                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($row['customer_name']) ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">Pilih Status Baru:</label>
                                    <select name="status" class="form-select fw-semibold" required>
                                        <option value="pending" <?= ($status === 'pending') ? 'selected' : '' ?>>Menunggu Pembayaran (Pending)</option>
                                        <option value="paid" <?= ($status === 'paid') ? 'selected' : '' ?>>Lunas (Paid)</option>
                                        <option value="processing" <?= ($status === 'processing') ? 'selected' : '' ?>>Sedang Diproses (Processing)</option>
                                        <option value="completed" <?= ($status === 'completed') ? 'selected' : '' ?>>Selesai (Completed)</option>
                                        <option value="cancelled" <?= ($status === 'cancelled') ? 'selected' : '' ?>>Dibatalkan (Cancelled)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer bg-light">
                                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="update_status" class="btn btn-primary btn-sm rounded-pill px-4">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <?php endforeach; ?>
    <?php endif; ?>

    <footer class="py-4 bg-dark text-white mt-auto">
        <div class="container-fluid px-4 text-center text-white-50 small">
            <div>&copy; <?= date('Y') ?> <strong>WarungBuku Admin</strong>. All Rights Reserved.</div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
