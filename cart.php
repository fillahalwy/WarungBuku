<?php
include("connection.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ----------------------------------------------------
// HANDLER AKSI KERANJANG BELANJA
// ----------------------------------------------------
$action = $_GET['action'] ?? '';

// 1. Tambah Buku ke Keranjang
if ($action === 'add') {
    $book_id = isset($_GET['id']) ? mysqli_real_escape_string($conn, trim($_GET['id'])) : '';
    $qty     = isset($_GET['qty']) ? (int)$_GET['qty'] : 1;
    if ($qty < 1) $qty = 1;

    if (!empty($book_id)) {
        // Cek stok buku di database
        $check_book = mysqli_query($conn, "SELECT id, title, stock FROM books WHERE id = '$book_id'");
        if ($check_book && mysqli_num_rows($check_book) > 0) {
            $book = mysqli_fetch_assoc($check_book);
            $stock = (int)$book['stock'];
            
            $current_qty = $_SESSION['cart'][$book_id] ?? 0;
            $new_qty = $current_qty + $qty;

            if ($stock <= 0) {
                $_SESSION['cart_msg_error'] = "Maaf, stok buku <strong>" . htmlspecialchars($book['title']) . "</strong> sedang habis.";
            } elseif ($new_qty > $stock) {
                $_SESSION['cart'][$book_id] = $stock;
                $_SESSION['cart_msg_warning'] = "Kuantitas buku <strong>" . htmlspecialchars($book['title']) . "</strong> disesuaikan dengan sisa stok maksimal ($stock eks).";
            } else {
                $_SESSION['cart'][$book_id] = $new_qty;
                $_SESSION['cart_msg_success'] = "Buku <strong>" . htmlspecialchars($book['title']) . "</strong> berhasil ditambahkan ke keranjang.";
            }
        }
    }

    // Jika beli langsung, arahkan ke checkout
    if (isset($_GET['buy_now']) && $_GET['buy_now'] == '1') {
        header("Location: checkout.php");
        exit();
    }

    $redirect = $_SERVER['HTTP_REFERER'] ?? 'cart.php';
    header("Location: " . $redirect);
    exit();
}

// 2. Update Kuantitas Keranjang
if ($action === 'update' && isset($_POST['update_cart'])) {
    if (isset($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $book_id => $qty) {
            $book_id = mysqli_real_escape_string($conn, trim($book_id));
            $qty = (int)$qty;

            if ($qty <= 0) {
                unset($_SESSION['cart'][$book_id]);
            } else {
                $check_book = mysqli_query($conn, "SELECT stock FROM books WHERE id = '$book_id'");
                if ($check_book && mysqli_num_rows($check_book) > 0) {
                    $stock = (int)mysqli_fetch_assoc($check_book)['stock'];
                    $_SESSION['cart'][$book_id] = min($qty, $stock);
                }
            }
        }
        $_SESSION['cart_msg_success'] = "Keranjang belanja berhasil diperbarui.";
    }
    header("Location: cart.php");
    exit();
}

// 3. Hapus Item dari Keranjang
if ($action === 'remove') {
    $book_id = isset($_GET['id']) ? mysqli_real_escape_string($conn, trim($_GET['id'])) : '';
    if (!empty($book_id) && isset($_SESSION['cart'][$book_id])) {
        unset($_SESSION['cart'][$book_id]);
        $_SESSION['cart_msg_success'] = "Buku berhasil dihapus dari keranjang.";
    }
    header("Location: cart.php");
    exit();
}

// 4. Kosongkan Keranjang
if ($action === 'clear') {
    $_SESSION['cart'] = [];
    $_SESSION['cart_msg_success'] = "Keranjang belanja telah dikosongkan.";
    header("Location: cart.php");
    exit();
}

// ----------------------------------------------------
// AMBIL DATA PRODUK DALAM KERANJANG
// ----------------------------------------------------
$cart_items = [];
$total_price = 0;
$total_items_count = 0;

if (!empty($_SESSION['cart'])) {
    $ids = array_map(function($id) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $id) . "'";
    }, array_keys($_SESSION['cart']));
    
    $ids_str = implode(',', $ids);
    $query_books = mysqli_query($conn, "SELECT * FROM books WHERE id IN ($ids_str)");

    if ($query_books) {
        while ($b = mysqli_fetch_assoc($query_books)) {
            $b_id = $b['id'];
            $qty = $_SESSION['cart'][$b_id] ?? 1;
            
            // Validasi jika kuantitas melebihi stok yang ada
            if ($qty > $b['stock']) {
                $qty = (int)$b['stock'];
                $_SESSION['cart'][$b_id] = $qty;
            }

            if ($qty > 0) {
                $subtotal = $b['price'] * $qty;
                $total_price += $subtotal;
                $total_items_count += $qty;

                $b['qty'] = $qty;
                $b['subtotal'] = $subtotal;
                $cart_items[] = $b;
            } else {
                unset($_SESSION['cart'][$b_id]);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="icon" type="image/x-icon" href="assets/book.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <title>Keranjang Belanja (<?= $total_items_count ?>) | WarungBuku</title>
    <style>
        :root {
            --brand-primary: #0B88D3;
        }
        .cart-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        .cart-item-img {
            width: 65px;
            height: 85px;
            object-fit: cover;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .btn-qty {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark fixed-top shadow-sm">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
                <img src="assets/book.ico" alt="logo" width="34">
                <span>Warung<span style="color: #0B88D3;">Buku</span></span>
            </a>
            <div class="ms-auto d-flex align-items-center gap-2">
                <?php if(isset($_SESSION['status_login']) && $_SESSION['status_login'] == true): ?>
                    <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                        <div class="dropdown">
                            <a class="btn btn-outline-light btn-sm rounded-pill px-3 dropdown-toggle d-flex align-items-center gap-1" href="#" role="button" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['global']->name ?? 'Admin') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow" aria-labelledby="adminDropdown">
                                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> Profil Admin</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Keluar</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="dropdown">
                            <a class="btn btn-outline-light btn-sm rounded-pill px-3 dropdown-toggle d-flex align-items-center gap-1" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['global']->name ?? 'Akun Saya') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="customer-profile.php"><i class="bi bi-person me-2"></i> Profil Saya</a></li>
                                <li><a class="dropdown-item" href="my-orders.php"><i class="bi bi-bag-check me-2"></i> Pesanan Saya</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Keluar</a></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <a class="btn btn-outline-light btn-sm rounded-pill px-3" href="login.php">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                    </a>
                <?php endif; ?>

                <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Lanjut Belanja
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
                    <li class="breadcrumb-item active text-dark fw-bold" aria-current="page">Keranjang Belanja</li>
                </ol>
            </nav>

            <div class="d-flex align-items-center justify-content-between mb-4">
                <h2 class="h4 fw-bold mb-0 text-dark">
                    <i class="bi bi-cart3 text-primary me-2"></i>Keranjang Belanja Anda
                </h2>
                <?php if (!empty($cart_items)): ?>
                    <a href="cart.php?action=clear" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="return confirm('Apakah Anda yakin ingin mengosongkan seluruh isi keranjang?')">
                        <i class="bi bi-trash me-1"></i> Kosongkan Keranjang
                    </a>
                <?php endif; ?>
            </div>

            <!-- Alerts -->
            <?php if(isset($_SESSION['cart_msg_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= $_SESSION['cart_msg_success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['cart_msg_success']); ?>
            <?php endif; ?>

            <?php if(isset($_SESSION['cart_msg_warning'])): ?>
                <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $_SESSION['cart_msg_warning']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['cart_msg_warning']); ?>
            <?php endif; ?>

            <?php if(isset($_SESSION['cart_msg_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-x-circle-fill me-2"></i><?= $_SESSION['cart_msg_error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['cart_msg_error']); ?>
            <?php endif; ?>

            <?php if (!empty($cart_items)): ?>
                <form action="cart.php?action=update" method="POST">
                    <div class="row g-4">
                        
                        <!-- List Item Keranjang -->
                        <div class="col-lg-8">
                            <div class="cart-card p-3 p-md-4 mb-4">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead class="text-muted small text-uppercase">
                                            <tr>
                                                <th scope="col" style="min-width: 250px;">Buku</th>
                                                <th scope="col" class="text-center" style="width: 140px;">Harga Satuan</th>
                                                <th scope="col" class="text-center" style="width: 130px;">Jumlah</th>
                                                <th scope="col" class="text-end" style="width: 140px;">Subtotal</th>
                                                <th scope="col" class="text-center" style="width: 50px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cart_items as $item): ?>
                                                <?php
                                                    $has_img = (!empty($item['image']) && file_exists('assets/images/book/' . $item['image']));
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-3">
                                                            <?php if ($has_img): ?>
                                                                <img src="assets/images/book/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="cart-item-img">
                                                            <?php else: ?>
                                                                <div class="cart-item-img bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center text-muted">
                                                                    <i class="bi bi-book fs-3"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <h6 class="fw-bold mb-1">
                                                                    <a href="product-detail.php?id=<?= $item['id'] ?>" class="text-dark text-decoration-none">
                                                                        <?= htmlspecialchars($item['title']) ?>
                                                                    </a>
                                                                </h6>
                                                                <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($item['category']) ?></span>
                                                                <small class="text-muted d-block mt-1">Sisa Stok: <?= $item['stock'] ?> eks</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center fw-semibold">
                                                        Rp <?= number_format($item['price'], 0, ',', '.') ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                                            <input type="number" name="qty[<?= $item['id'] ?>]" value="<?= $item['qty'] ?>" min="1" max="<?= $item['stock'] ?>" class="form-control form-control-sm text-center" style="width: 65px;">
                                                        </div>
                                                    </td>
                                                    <td class="text-end fw-bold text-primary">
                                                        Rp <?= number_format($item['subtotal'], 0, ',', '.') ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="cart.php?action=remove&id=<?= $item['id'] ?>" class="text-danger" title="Hapus dari keranjang" onclick="return confirm('Hapus buku ini dari keranjang?')">
                                                            <i class="bi bi-trash fs-5"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top flex-wrap gap-2">
                                    <a href="index.php" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                                        <i class="bi bi-arrow-left me-1"></i> Tambah Buku Lain
                                    </a>
                                    <button type="submit" name="update_cart" class="btn btn-dark btn-sm rounded-pill px-4">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Perbarui Keranjang
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Ringkasan Belanja -->
                        <div class="col-lg-4">
                            <div class="cart-card p-4 sticky-top" style="top: 80px;">
                                <h5 class="fw-bold mb-3 text-dark">Ringkasan Pesanan</h5>
                                
                                <div class="d-flex justify-content-between mb-2 text-muted">
                                    <span>Total Item:</span>
                                    <span class="fw-semibold text-dark"><?= $total_items_count ?> Buku</span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-3 text-muted">
                                    <span>Subtotal Belanja:</span>
                                    <span class="fw-bold text-dark">Rp <?= number_format($total_price, 0, ',', '.') ?></span>
                                </div>

                                <hr>

                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <span class="fs-5 fw-bold text-dark">Total Pembayaran:</span>
                                    <span class="fs-4 fw-bold" style="color: #0B88D3;">
                                        Rp <?= number_format($total_price, 0, ',', '.') ?>
                                    </span>
                                </div>

                                <a href="checkout.php" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold shadow-sm" style="background-color: #0B88D3; border: none;">
                                    Lanjut ke Checkout <i class="bi bi-arrow-right ms-1"></i>
                                </a>

                                <div class="mt-3 text-center text-muted small">
                                    <i class="bi bi-shield-lock me-1 text-success"></i> Transaksi Aman & Terpercaya
                                </div>
                            </div>
                        </div>

                    </div>
                </form>
            <?php else: ?>
                <!-- Keranjang Kosong -->
                <div class="text-center py-5 bg-white rounded-3 shadow-sm border p-5 my-4">
                    <div class="text-muted mb-3 fs-1"><i class="bi bi-cart-x"></i></div>
                    <h4 class="fw-bold text-dark mb-2">Keranjang Belanja Anda Kosong</h4>
                    <p class="text-muted mb-4">Yuk, cari dan temukan buku-buku menarik pilihan Anda di WarungBuku!</p>
                    <a href="index.php" class="btn btn-dark rounded-pill px-4">
                        <i class="bi bi-book me-1"></i> Mulai Belanja Sekarang
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Footer -->
    <footer class="py-4 bg-dark text-white mt-auto">
        <div class="container d-flex flex-wrap justify-content-between align-items-center text-white-50 small gap-2">
            <div>&copy; <?= date('Y') ?> <strong>WarungBuku</strong>. All Rights Reserved.</div>
            <?php if(isset($_SESSION['status_login']) && $_SESSION['status_login'] == true && ($_SESSION['role'] ?? '') === 'admin'): ?>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3" style="font-size: 0.78rem;">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard Admin
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
