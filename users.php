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
// HANDLER CRUD PENGGUNA (USERS)
// ----------------------------------------------------

// 1. Tambah Pengguna Baru
if (isset($_POST['add_user'])) {
    $name     = mysqli_real_escape_string($conn, trim($_POST['name']));
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $phone    = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
    $address  = mysqli_real_escape_string($conn, trim($_POST['address']));
    $role     = mysqli_real_escape_string($conn, trim($_POST['role']));
    $password = $_POST['password'];

    // Cek username duplikat
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['msg_error'] = "Username <strong>" . htmlspecialchars($username) . "</strong> sudah digunakan. Pilih username lain.";
    } else {
        $hashed = md5($password);
        $insert = mysqli_query($conn, "INSERT INTO users (name, username, password, phone, email, address, role, created_at, updated_at) 
                                       VALUES ('$name', '$username', '$hashed', '$phone', '$email', '$address', '$role', NOW(), NOW())");
        if ($insert) {
            $_SESSION['msg_success'] = "Pengguna baru <strong>" . htmlspecialchars($name) . "</strong> (" . ucfirst($role) . ") berhasil ditambahkan.";
        } else {
            $_SESSION['msg_error'] = "Gagal menambahkan pengguna: " . mysqli_error($conn);
        }
    }
    header("Location: users.php");
    exit();
}

// 2. Edit Data Pengguna
if (isset($_POST['edit_user'])) {
    $id       = (int)$_POST['user_id'];
    $name     = mysqli_real_escape_string($conn, trim($_POST['name']));
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $phone    = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
    $address  = mysqli_real_escape_string($conn, trim($_POST['address']));
    $role     = mysqli_real_escape_string($conn, trim($_POST['role']));
    $password = $_POST['password'];

    // Cek duplikat username pada user lain
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' AND id != '$id'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['msg_error'] = "Username <strong>" . htmlspecialchars($username) . "</strong> sudah digunakan pengguna lain.";
    } else {
        $pass_sql = "";
        if (!empty($password)) {
            $hashed = md5($password);
            $pass_sql = ", password = '$hashed'";
        }

        $update = mysqli_query($conn, "UPDATE users SET 
                                       name = '$name', 
                                       username = '$username', 
                                       phone = '$phone', 
                                       email = '$email', 
                                       address = '$address', 
                                       role = '$role' 
                                       $pass_sql, 
                                       updated_at = NOW() 
                                       WHERE id = '$id'");
        if ($update) {
            $_SESSION['msg_success'] = "Data pengguna <strong>" . htmlspecialchars($name) . "</strong> berhasil diperbarui.";
        } else {
            $_SESSION['msg_error'] = "Gagal memperbarui pengguna: " . mysqli_error($conn);
        }
    }
    header("Location: users.php");
    exit();
}

// 3. Hapus Pengguna
if (isset($_GET['delete_user'])) {
    $id = (int)$_GET['delete_user'];

    if ($id == $_SESSION['id']) {
        $_SESSION['msg_error'] = "Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.";
    } else {
        $delete = mysqli_query($conn, "DELETE FROM users WHERE id = '$id'");
        if ($delete) {
            $_SESSION['msg_success'] = "Pengguna berhasil dihapus dari sistem.";
        } else {
            $_SESSION['msg_error'] = "Gagal menghapus pengguna: " . mysqli_error($conn);
        }
    }
    header("Location: users.php");
    exit();
}

// ----------------------------------------------------
// FILTER, PENCARIAN & PAGINASI
// ----------------------------------------------------
$limit  = 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search      = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$role_filter = isset($_GET['role']) ? mysqli_real_escape_string($conn, trim($_GET['role'])) : '';

$where_clause = " WHERE 1=1 ";
if (!empty($search)) {
    $where_clause .= " AND (name LIKE '%$search%' OR username LIKE '%$search%' OR phone LIKE '%$search%' OR email LIKE '%$search%') ";
}
if (!empty($role_filter) && $role_filter !== 'all') {
    $where_clause .= " AND role = '$role_filter' ";
}

// Statistik Pengguna
$stat_total_users     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users"))['total'] ?? 0;
$stat_total_admins    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'admin'"))['total'] ?? 0;
$stat_total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'customer'"))['total'] ?? 0;

$total_records = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users $where_clause"))['total'] ?? 0;
$total_pages   = ceil($total_records / $limit);

$users_query = mysqli_query($conn, "SELECT * FROM users $where_clause ORDER BY created_at DESC LIMIT $offset, $limit");
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
    <title>Manajemen Pengguna | WarungBuku Admin</title>
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
                    <h1 class="h3 fw-bold mb-1"><i class="bi bi-people me-2"></i>Manajemen Data Pengguna</h1>
                    <p class="text-white-50 mb-0 small">Kelola akun administrator dan akun pelanggan terdaftar WarungBuku.</p>
                </div>
                <div>
                    <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddUser">
                        <i class="bi bi-person-plus me-1"></i> Tambah Pengguna Baru
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

            <!-- Ringkasan Statistik Pengguna -->
            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <div class="card stat-card bg-white shadow-sm p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary me-3">
                                <i class="bi bi-people fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">TOTAL PENGGUNA</div>
                                <div class="fs-4 fw-bold text-dark"><?= $stat_total_users ?> Akun</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card stat-card bg-white shadow-sm p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success me-3">
                                <i class="bi bi-person-check fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">PELANGGAN (CUSTOMER)</div>
                                <div class="fs-4 fw-bold text-dark"><?= $stat_total_customers ?> Orang</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card stat-card bg-white shadow-sm p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-dark bg-opacity-10 p-3 text-dark me-3">
                                <i class="bi bi-shield-lock fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">ADMINISTRATOR</div>
                                <div class="fs-4 fw-bold text-dark"><?= $stat_total_admins ?> Admin</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Pengguna -->
            <div class="card card-custom mb-5">
                <div class="card-header card-header-custom bg-dark text-white py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="fw-bold fs-5 mb-0"><i class="bi bi-person-lines-fill me-2"></i>Daftar Pengguna Sistem</div>
                    <span class="badge bg-primary rounded-pill px-3 py-2">Total: <?= $total_records ?> Pengguna</span>
                </div>
                <div class="card-body p-4">
                    
                    <!-- Search & Role Filter -->
                    <form action="" method="GET" class="row g-3 mb-4">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Cari nama, username, telepon, email..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="role" class="form-select">
                                <option value="">-- Semua Role --</option>
                                <option value="admin" <?= ($role_filter === 'admin') ? 'selected' : '' ?>>Administrator</option>
                                <option value="customer" <?= ($role_filter === 'customer') ? 'selected' : '' ?>>Pelanggan (Customer)</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-dark w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                            <?php if(!empty($search) || !empty($role_filter)): ?>
                                <a href="users.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i> Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle border mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>Nama Pengguna</th>
                                    <th>Username</th>
                                    <th>Kontak (HP/Email)</th>
                                    <th>Alamat Pengiriman</th>
                                    <th class="text-center">Role</th>
                                    <th class="text-center" style="width: 120px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    $users_list = [];
                                    if($users_query && mysqli_num_rows($users_query) > 0) {
                                        while($u = mysqli_fetch_assoc($users_query)) {
                                            $users_list[] = $u;
                                        }
                                    }
                                ?>
                                <?php if(!empty($users_list)): ?>
                                    <?php $no = $offset + 1; foreach($users_list as $u): ?>
                                        <tr>
                                            <td class="text-center small text-muted"><?= $no++ ?></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($u['name']) ?></div>
                                                <small class="text-muted">Terdaftar: <?= date('d/m/Y', strtotime($u['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">@<?= htmlspecialchars($u['username']) ?></span>
                                            </td>
                                            <td class="small">
                                                <div><i class="bi bi-telephone me-1 text-primary"></i><?= htmlspecialchars($u['phone'] ?? '-') ?></div>
                                                <?php if(!empty($u['email'])): ?>
                                                    <div><i class="bi bi-envelope me-1 text-muted"></i><?= htmlspecialchars($u['email']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted" style="max-width: 250px;">
                                                <?= htmlspecialchars($u['address'] ?? '-') ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if($u['role'] === 'admin'): ?>
                                                    <span class="badge bg-dark px-3 py-2 rounded-pill"><i class="bi bi-shield-check me-1"></i>Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success px-3 py-2 rounded-pill"><i class="bi bi-person me-1"></i>Customer</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#modalEditUser<?= $u['id'] ?>" title="Edit Pengguna">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <?php if($u['id'] != $_SESSION['id']): ?>
                                                        <a href="users.php?delete_user=<?= $u['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Hapus pengguna <?= htmlspecialchars($u['name']) ?>?')" title="Hapus">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="bi bi-people fs-2 d-block mb-1"></i>
                                            Tidak ada data pengguna yang sesuai.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                        <nav class="mt-4" aria-label="Page navigation">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>">&laquo; Prev</a>
                                </li>
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>">Next &raquo;</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </section>

    <!-- MODAL EDIT PENGGUNA LIST (Outside Table) -->
    <?php if(!empty($users_list)): ?>
        <?php foreach($users_list as $u): ?>
            <div class="modal fade" id="modalEditUser<?= $u['id'] ?>" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form action="users.php" method="POST">
                            <div class="modal-header bg-dark text-white">
                                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Pengguna: <?= htmlspecialchars($u['name']) ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($u['name']) ?>" required>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($u['username']) ?>" required autocomplete="off">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Role <span class="text-danger">*</span></label>
                                        <select name="role" class="form-select" required>
                                            <option value="customer" <?= ($u['role'] === 'customer') ? 'selected' : '' ?>>Customer (Pelanggan)</option>
                                            <option value="admin" <?= ($u['role'] === 'admin') ? 'selected' : '' ?>>Administrator</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">No HP/WA <span class="text-danger">*</span></label>
                                        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($u['phone'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Email</label>
                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email'] ?? '') ?>" placeholder="user@gmail.com">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Alamat Pengiriman</label>
                                    <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($u['address'] ?? '') ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Password Baru (Opsional)</label>
                                    <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
                                </div>

                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="edit_user" class="btn btn-primary">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- MODAL TAMBAH PENGGUNA -->
    <div class="modal fade" id="modalAddUser" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="users.php" method="POST">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Tambah Pengguna Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Nama Lengkap" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" placeholder="username" required autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Role <span class="text-danger">*</span></label>
                                <select name="role" class="form-select" required>
                                    <option value="customer" selected>Customer (Pelanggan)</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">No HP/WA <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control" placeholder="081234567890" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Email</label>
                                <input type="email" name="email" class="form-control" placeholder="user@gmail.com">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Alamat Pengiriman</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Alamat lengkap"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Minimal 4 karakter" required>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_user" class="btn btn-primary">Tambah Pengguna</button>
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
