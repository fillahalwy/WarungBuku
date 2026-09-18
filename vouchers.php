<?php
include("connection.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validasi Role Admin
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] != true || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

// ----------------------------------------------------
// HANDLER CRUD VOUCHER & DISKON
// ----------------------------------------------------

// 1. Tambah Voucher Baru
if (isset($_POST['add_voucher'])) {
    $code           = strtoupper(mysqli_real_escape_string($conn, trim($_POST['code'])));
    $name           = mysqli_real_escape_string($conn, trim($_POST['name']));
    $discount_type  = mysqli_real_escape_string($conn, trim($_POST['discount_type']));
    $discount_value = (float)$_POST['discount_value'];
    $min_spend      = (float)$_POST['min_spend'];
    $max_discount   = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : 'NULL';
    $quota          = (int)$_POST['quota'];
    $expiry_date    = !empty($_POST['expiry_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['expiry_date']) . "'" : "NULL";
    $is_active      = isset($_POST['is_active']) ? 1 : 0;

    // Cek duplikasi kode voucher
    $check = mysqli_query($conn, "SELECT id FROM vouchers WHERE code = '$code'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['msg_error'] = "Kode voucher <strong>" . htmlspecialchars($code) . "</strong> sudah ada. Gunakan kode lain.";
    } else {
        $insert = mysqli_query($conn, "INSERT INTO vouchers (code, name, discount_type, discount_value, min_spend, max_discount, quota, used_count, expiry_date, is_active, created_at, updated_at) 
                                       VALUES ('$code', '$name', '$discount_type', '$discount_value', '$min_spend', $max_discount, '$quota', 0, $expiry_date, '$is_active', NOW(), NOW())");
        if ($insert) {
            $_SESSION['msg_success'] = "Voucher diskon <strong>" . htmlspecialchars($code) . "</strong> berhasil ditambahkan.";
        } else {
            $_SESSION['msg_error'] = "Gagal menambahkan voucher: " . mysqli_error($conn);
        }
    }
    header("Location: vouchers.php");
    exit();
}

// 2. Edit Voucher
if (isset($_POST['edit_voucher'])) {
    $id             = (int)$_POST['voucher_id'];
    $code           = strtoupper(mysqli_real_escape_string($conn, trim($_POST['code'])));
    $name           = mysqli_real_escape_string($conn, trim($_POST['name']));
    $discount_type  = mysqli_real_escape_string($conn, trim($_POST['discount_type']));
    $discount_value = (float)$_POST['discount_value'];
    $min_spend      = (float)$_POST['min_spend'];
    $max_discount   = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : 'NULL';
    $quota          = (int)$_POST['quota'];
    $expiry_date    = !empty($_POST['expiry_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['expiry_date']) . "'" : "NULL";
    $is_active      = isset($_POST['is_active']) ? 1 : 0;

    // Cek kode duplikat
    $check = mysqli_query($conn, "SELECT id FROM vouchers WHERE code = '$code' AND id != '$id'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['msg_error'] = "Kode voucher <strong>" . htmlspecialchars($code) . "</strong> sudah digunakan voucher lain.";
    } else {
        $update = mysqli_query($conn, "UPDATE vouchers SET 
                                       code = '$code', 
                                       name = '$name', 
                                       discount_type = '$discount_type', 
                                       discount_value = '$discount_value', 
                                       min_spend = '$min_spend', 
                                       max_discount = $max_discount, 
                                       quota = '$quota', 
                                       expiry_date = $expiry_date, 
                                       is_active = '$is_active', 
                                       updated_at = NOW() 
                                       WHERE id = '$id'");
        if ($update) {
            $_SESSION['msg_success'] = "Voucher <strong>" . htmlspecialchars($code) . "</strong> berhasil diperbarui.";
        } else {
            $_SESSION['msg_error'] = "Gagal memperbarui voucher: " . mysqli_error($conn);
        }
    }
    header("Location: vouchers.php");
    exit();
}

// 3. Hapus Voucher
if (isset($_GET['delete_voucher'])) {
    $id = (int)$_GET['delete_voucher'];
    $delete = mysqli_query($conn, "DELETE FROM vouchers WHERE id = '$id'");
    if ($delete) {
        $_SESSION['msg_success'] = "Voucher berhasil dihapus.";
    } else {
        $_SESSION['msg_error'] = "Gagal menghapus voucher: " . mysqli_error($conn);
    }
    header("Location: vouchers.php");
    exit();
}

// 4. Toggle Status Aktif
if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    $update = mysqli_query($conn, "UPDATE vouchers SET is_active = IF(is_active=1, 0, 1), updated_at = NOW() WHERE id = '$id'");
    if ($update) {
        $_SESSION['msg_success'] = "Status voucher berhasil diperbarui.";
    }
    header("Location: vouchers.php");
    exit();
}

// Statistik Voucher
$stat_total_vouchers  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM vouchers"))['total'] ?? 0;
$stat_active_vouchers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM vouchers WHERE is_active = 1"))['total'] ?? 0;
$stat_used_vouchers   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(used_count), 0) as total FROM vouchers"))['total'] ?? 0;

$vouchers_query = mysqli_query($conn, "SELECT * FROM vouchers ORDER BY created_at DESC");
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
    <title>Manajemen Diskon & Voucher | WarungBuku Admin</title>
    <style>
        .stat-card {
            border: none;
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
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
        .voucher-code-badge {
            font-family: monospace;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 1px;
            background-color: #f1f5f9;
            color: #0B88D3;
            border: 1px dashed #0B88D3;
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-block;
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
                    <h1 class="h3 fw-bold mb-1"><i class="bi bi-ticket-perforated me-2"></i>Manajemen Diskon & Voucher</h1>
                    <p class="text-white-50 mb-0 small">Buat dan kelola kode kupon promo potongan harga belanja buku untuk pelanggan.</p>
                </div>
                <div>
                    <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddVoucher">
                        <i class="bi bi-plus-circle me-1"></i> Buat Voucher Baru
                    </button>
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

            <!-- Ringkasan Statistik Voucher -->
            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <div class="card stat-card bg-white shadow-sm p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary me-3">
                                <i class="bi bi-ticket-perforated fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">TOTAL VOUCHER</div>
                                <div class="fs-4 fw-bold text-dark"><?= $stat_total_vouchers ?> Kupon</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card stat-card bg-white shadow-sm p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success me-3">
                                <i class="bi bi-check-circle fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">VOUCHER AKTIF</div>
                                <div class="fs-4 fw-bold text-success"><?= $stat_active_vouchers ?> Kupon</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card stat-card bg-white shadow-sm p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning me-3">
                                <i class="bi bi-graph-up-arrow fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">KLAIM DIGUNAKAN</div>
                                <div class="fs-4 fw-bold text-dark"><?= $stat_used_vouchers ?> Kali</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Voucher -->
            <div class="card card-custom mb-5">
                <div class="card-header card-header-custom bg-dark text-white py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="fw-bold fs-5 mb-0"><i class="bi bi-tags me-2"></i>Daftar Kode Promo & Voucher Diskon</div>
                </div>
                <div class="card-body p-4">
                    
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle border mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Kode Voucher</th>
                                    <th>Nama Promo</th>
                                    <th>Besaran Diskon</th>
                                    <th>Syarat Min. Belanja</th>
                                    <th class="text-center">Kuota Digunakan</th>
                                    <th>Masa Berlaku</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width: 120px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    $vouchers_list = [];
                                    if($vouchers_query && mysqli_num_rows($vouchers_query) > 0) {
                                        while($v = mysqli_fetch_assoc($vouchers_query)) {
                                            $vouchers_list[] = $v;
                                        }
                                    }
                                ?>
                                <?php if(!empty($vouchers_list)): ?>
                                    <?php foreach($vouchers_list as $v): ?>
                                        <tr>
                                            <td>
                                                <span class="voucher-code-badge"><?= htmlspecialchars($v['code']) ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($v['name']) ?></div>
                                            </td>
                                            <td>
                                                <?php if($v['discount_type'] === 'percent'): ?>
                                                    <span class="badge bg-primary fs-6"><?= (float)$v['discount_value'] ?>%</span>
                                                    <?php if(!empty($v['max_discount'])): ?>
                                                        <small class="text-muted d-block mt-1">Maks: Rp <?= number_format($v['max_discount'], 0, ',', '.') ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-success fs-6">Rp <?= number_format($v['discount_value'], 0, ',', '.') ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($v['min_spend'] > 0): ?>
                                                    <span class="small fw-semibold">Rp <?= number_format($v['min_spend'], 0, ',', '.') ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted small">Tanpa Min. Belanja</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="small fw-bold"><?= $v['used_count'] ?> / <?= $v['quota'] ?></span>
                                                <div class="progress mt-1" style="height: 5px;">
                                                    <div class="progress-bar bg-info" style="width: <?= min(100, round(($v['used_count'] / max(1, $v['quota'])) * 100)) ?>%"></div>
                                                </div>
                                            </td>
                                            <td class="small">
                                                <?php if(!empty($v['expiry_date'])): ?>
                                                    <i class="bi bi-calendar-event me-1"></i><?= date('d/m/Y', strtotime($v['expiry_date'])) ?>
                                                    <?php if(strtotime($v['expiry_date']) < time()): ?>
                                                        <span class="badge bg-danger d-block mt-1">Kedaluwarsa</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Tidak Terbatas</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="vouchers.php?toggle_status=<?= $v['id'] ?>" class="badge <?= $v['is_active'] ? 'bg-success' : 'bg-secondary' ?> text-decoration-none" title="Klik untuk ubah status">
                                                    <?= $v['is_active'] ? '<i class="bi bi-check2 me-1"></i>Aktif' : '<i class="bi bi-dash me-1"></i>Nonaktif' ?>
                                                </a>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#modalEditVoucher<?= $v['id'] ?>" title="Edit Voucher">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <a href="vouchers.php?delete_voucher=<?= $v['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Hapus voucher <?= htmlspecialchars($v['code']) ?>?')" title="Hapus">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            <i class="bi bi-ticket-perforated fs-2 d-block mb-1"></i>
                                            Belum ada voucher diskon yang dibuat.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </section>

    <!-- MODAL EDIT VOUCHER LIST (Outside Table) -->
    <?php if(!empty($vouchers_list)): ?>
        <?php foreach($vouchers_list as $v): ?>
            <div class="modal fade" id="modalEditVoucher<?= $v['id'] ?>" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form action="vouchers.php" method="POST">
                            <div class="modal-header bg-dark text-white">
                                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Voucher: <?= htmlspecialchars($v['code']) ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <input type="hidden" name="voucher_id" value="<?= $v['id'] ?>">

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Kode Voucher <span class="text-danger">*</span></label>
                                    <input type="text" name="code" class="form-control text-uppercase font-monospace" value="<?= htmlspecialchars($v['code']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Nama Promo / Keterangan <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($v['name']) ?>" required>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Jenis Potongan</label>
                                        <select name="discount_type" class="form-select" required>
                                            <option value="percent" <?= ($v['discount_type'] === 'percent') ? 'selected' : '' ?>>Persentase (%)</option>
                                            <option value="fixed" <?= ($v['discount_type'] === 'fixed') ? 'selected' : '' ?>>Nominal Tetap (Rp)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Nilai Diskon <span class="text-danger">*</span></label>
                                        <input type="number" name="discount_value" class="form-control" value="<?= $v['discount_value'] ?>" step="0.01" min="1" required>
                                    </div>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Minimal Belanja (Rp)</label>
                                        <input type="number" name="min_spend" class="form-control" value="<?= $v['min_spend'] ?>" min="0">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Maksimal Diskon (Rp)</label>
                                        <input type="number" name="max_discount" class="form-control" value="<?= $v['max_discount'] ?? '' ?>" placeholder="Opsional">
                                    </div>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Kuota Pemakaian</label>
                                        <input type="number" name="quota" class="form-control" value="<?= $v['quota'] ?>" min="1" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Tanggal Kedaluwarsa</label>
                                        <input type="date" name="expiry_date" class="form-control" value="<?= $v['expiry_date'] ?? '' ?>">
                                    </div>
                                </div>

                                <div class="form-check form-switch mt-3">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="edit_active_<?= $v['id'] ?>" <?= $v['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-semibold" for="edit_active_<?= $v['id'] ?>">Voucher Aktif & Dapat Digunakan</label>
                                </div>

                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="edit_voucher" class="btn btn-primary">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- MODAL TAMBAH VOUCHER -->
    <div class="modal fade" id="modalAddVoucher" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="vouchers.php" method="POST">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Buat Voucher Diskon Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Kode Voucher <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control text-uppercase font-monospace" placeholder="Contoh: DISKON10" required autocomplete="off">
                            <small class="text-muted" style="font-size: 0.75rem;">Gunakan huruf kapital tanpa spasi.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nama Promo / Keterangan <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Contoh: Diskon Pengguna Baru 10%" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Jenis Potongan</label>
                                <select name="discount_type" class="form-select" required>
                                    <option value="percent" selected>Persentase (%)</option>
                                    <option value="fixed">Nominal Tetap (Rp)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Nilai Diskon <span class="text-danger">*</span></label>
                                <input type="number" name="discount_value" class="form-control" placeholder="Misal: 10 (jika %) atau 20000 (jika Rp)" step="0.01" min="1" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Minimal Belanja (Rp)</label>
                                <input type="number" name="min_spend" class="form-control" value="0" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Maksimal Diskon (Rp)</label>
                                <input type="number" name="max_discount" class="form-control" placeholder="Khusus % (Opsional)">
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Kuota Pemakaian</label>
                                <input type="number" name="quota" class="form-control" value="100" min="1" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Tanggal Kedaluwarsa</label>
                                <input type="date" name="expiry_date" class="form-control">
                            </div>
                        </div>

                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="add_active" checked>
                            <label class="form-check-label small fw-semibold" for="add_active">Voucher Langsung Aktif</label>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_voucher" class="btn btn-primary">Simpan Voucher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-4 bg-dark text-white mt-auto">
        <div class="container-fluid px-4 text-center text-white-50 small">
            <div>&copy; <?= date('Y') ?> <strong>WarungBuku Admin</strong>. All Rights Reserved.</div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
