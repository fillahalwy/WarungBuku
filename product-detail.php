<?php
include("connection.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ambil Informasi Kontak Admin untuk Pemesanan WhatsApp
$admin_query = mysqli_query($conn, "SELECT name, phone FROM users LIMIT 1");
$admin_data  = mysqli_fetch_assoc($admin_query);
$admin_phone = $admin_data['phone'] ?? '081234567890';
$wa_phone    = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $admin_phone));

// Ambil ID Buku dari Parameter GET
$book_id = isset($_GET['id']) ? mysqli_real_escape_string($conn, trim($_GET['id'])) : '';

$book = null;
if (!empty($book_id)) {
    $query = mysqli_query($conn, "SELECT * FROM books WHERE id = '$book_id'");
    if ($query && mysqli_num_rows($query) > 0) {
        $book = mysqli_fetch_assoc($query);
    }
}

// Ambil Rekomendasi Buku Lain (Kategori Serupa)
$related_books = [];
if ($book) {
    $category_escaped = mysqli_real_escape_string($conn, $book['category']);
    $related_query = mysqli_query($conn, "SELECT * FROM books WHERE category = '$category_escaped' AND id != '$book_id' LIMIT 4");
    if ($related_query) {
        while ($r = mysqli_fetch_assoc($related_query)) {
            $related_books[] = $r;
        }
    }
}

// Hitung Total Item di Keranjang
$cart_count = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum($_SESSION['cart']);
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
    <title><?= $book ? htmlspecialchars($book['title']) . " | WarungBuku" : "Detail Produk | WarungBuku" ?></title>
    <style>
        :root {
            --brand-primary: #0B88D3;
        }
        .detail-card-box {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        .cover-showcase-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 380px;
            border: 1px solid #e9ecef;
        }
        .cover-showcase-img {
            max-height: 360px;
            max-width: 100%;
            object-fit: contain;
            border-radius: 6px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .specs-table td {
            padding: 8px 12px;
            font-size: 0.92rem;
        }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <!-- Public Header Nav -->
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
                    <i class="bi bi-arrow-left me-1"></i> Beranda
                </a>
            </div>
        </div>
    </nav>
    <div class="py-4"></div>
    
    <!-- Product section-->
    <section class="py-5 flex-grow-1">
        <div class="container px-4 px-lg-5 my-3">
            <?php if($book): ?>
                <?php
                    $title        = htmlspecialchars($book['title']);
                    $author       = htmlspecialchars($book['author'] ?? '-');
                    $publisher    = htmlspecialchars($book['publisher'] ?? '-');
                    $pub_year     = (int)($book['publication_year'] ?? 0);
                    $category     = htmlspecialchars($book['category']);
                    $price        = (float)$book['price'];
                    $stock        = (int)$book['stock'];
                    $isbn         = htmlspecialchars($book['isbn'] ?? '-');
                    $description  = $book['description'] ?? 'Belum ada deskripsi untuk buku ini.';
                    $image_file   = $book['image'];
                    $has_image    = (!empty($image_file) && file_exists('assets/images/book/' . $image_file));

                    // Pesan WhatsApp
                    $wa_message = "Halo Admin WarungBuku, saya ingin memesan buku berikut:\n\n"
                                . "📚 *Judul:* " . $book['title'] . "\n"
                                . "🔖 *Kategori:* " . $book['category'] . "\n"
                                . "🔢 *ISBN:* " . $book['isbn'] . "\n"
                                . "💰 *Harga:* Rp " . number_format($price, 0, ',', '.') . "\n\n"
                                . "Apakah stok buku ini masih tersedia? Terima kasih.";
                    $wa_url = "https://wa.me/" . $wa_phone . "?text=" . urlencode($wa_message);
                ?>

                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted"><i class="bi bi-house me-1"></i>Beranda</a></li>
                        <li class="breadcrumb-item"><a href="index.php?category=<?= urlencode($category) ?>#produk" class="text-decoration-none text-muted"><?= $category ?></a></li>
                        <li class="breadcrumb-item active text-dark fw-bold" aria-current="page"><?= $title ?></li>
                    </ol>
                </nav>

                <div class="detail-card-box p-4 p-lg-5 mb-5">
                    <div class="row gx-4 gx-lg-5 align-items-center">
                        <div class="col-md-5">
                            <div class="cover-showcase-box mb-4 mb-md-0">
                                <?php if($has_image): ?>
                                    <img class="cover-showcase-img" src="assets/images/book/<?= htmlspecialchars($image_file) ?>" alt="<?= $title ?>" />
                                <?php else: ?>
                                    <img class="cover-showcase-img" src="https://dummyimage.com/600x700/dee2e6/6c757d.jpg&text=<?= urlencode($title) ?>" alt="<?= $title ?>" />
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-dark rounded-pill px-3 py-2"><?= $category ?></span>
                                <?php if($stock > 0): ?>
                                    <span class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-check2 me-1"></i>Stok: <?= $stock ?> Eks</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill px-3 py-2"><i class="bi bi-x-circle me-1"></i>Stok Habis</span>
                                <?php endif; ?>
                            </div>

                            <h1 class="display-6 fw-bolder mb-2 text-dark"><?= $title ?></h1>
                            
                            <div class="fs-4 mb-4 fw-bold" style="color: #0B88D3;">
                                <span>Rp <?= number_format($price, 0, ',', '.') ?></span>
                            </div>

                            <!-- Spesifikasi Buku Table -->
                            <div class="table-responsive mb-4">
                                <table class="table table-sm table-bordered bg-light rounded specs-table mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 30%;"><i class="bi bi-person me-2 text-primary"></i>Penulis</td>
                                            <td class="fw-semibold"><?= $author ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="bi bi-building me-2 text-primary"></i>Penerbit</td>
                                            <td class="fw-semibold"><?= $publisher ?> (<?= $pub_year > 0 ? $pub_year : '-' ?>)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="bi bi-upc-scan me-2 text-primary"></i>ISBN</td>
                                            <td class="fw-semibold"><?= $isbn ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mb-4">
                                <h6 class="fw-bold text-dark mb-2">Sinopsis / Ringkasan:</h6>
                                <p class="text-muted leading-relaxed" style="line-height: 1.7;"><?= nl2br(htmlspecialchars($description)) ?></p>
                            </div>
                            
                            <div class="d-flex flex-wrap gap-2 pt-3 border-top">
                                <?php if($stock > 0): ?>
                                    <a href="cart.php?action=add&id=<?= $book_id ?>&buy_now=1" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background-color: #0B88D3; border: none;">
                                        <i class="bi bi-bag-check-fill me-1"></i> Beli Sekarang
                                    </a>
                                    <a href="cart.php?action=add&id=<?= $book_id ?>" class="btn btn-outline-dark rounded-pill px-3 fw-semibold">
                                        <i class="bi bi-cart-plus me-1"></i> + Keranjang
                                    </a>
                                    <a href="<?= $wa_url ?>" target="_blank" class="btn btn-success rounded-pill px-3 fw-semibold shadow-sm">
                                        <i class="bi bi-whatsapp me-1"></i> Chat WA
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary rounded-pill px-4 disabled">
                                        <i class="bi bi-slash-circle me-1"></i> Stok Habis
                                    </button>
                                <?php endif; ?>
                                <a href="index.php?category=<?= urlencode($category) ?>#produk" class="btn btn-outline-secondary rounded-pill px-3">
                                    Buku Sejenis
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Buku Terkait Section -->
                <?php if(!empty($related_books)): ?>
                    <div class="mb-4">
                        <h4 class="fw-bold mb-3 text-dark"><i class="bi bi-book me-2 text-primary"></i>Buku Terkait</h4>
                        <div class="row gx-4 gx-lg-5 row-cols-2 row-cols-md-4">
                            <?php foreach($related_books as $rel): ?>
                                <?php
                                    $rel_has_img = (!empty($rel['image']) && file_exists('assets/images/book/' . $rel['image']));
                                ?>
                                <div class="col mb-4">
                                    <div class="card h-100 shadow-sm border-0 rounded-3">
                                        <div class="p-3 text-center bg-light" style="height: 180px; display: flex; align-items: center; justify-content: center;">
                                            <?php if($rel_has_img): ?>
                                                <img src="assets/images/book/<?= htmlspecialchars($rel['image']) ?>" alt="<?= htmlspecialchars($rel['title']) ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;" />
                                            <?php else: ?>
                                                <i class="bi bi-book text-muted fs-1"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body p-3 text-center d-flex flex-column">
                                            <h6 class="fw-bold mb-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.4rem;">
                                                <?= htmlspecialchars($rel['title']) ?>
                                            </h6>
                                            <div class="small text-muted mb-2"><?= htmlspecialchars($rel['author']) ?></div>
                                            <div class="mt-auto">
                                                <div class="fw-bold mb-2" style="color: #0B88D3;">Rp <?= number_format($rel['price'], 0, ',', '.') ?></div>
                                                <a href="product-detail.php?id=<?= $rel['id'] ?>" class="btn btn-outline-dark btn-sm rounded-pill w-100">Detail</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5 bg-white rounded-3 shadow-sm border my-4">
                    <div class="text-muted mb-3 fs-1"><i class="bi bi-exclamation-circle"></i></div>
                    <h3 class="fw-bold mb-2">Buku Tidak Ditemukan</h3>
                    <p class="text-muted mb-4">Buku yang Anda tuju mungkin tidak tersedia atau telah dihapus.</p>
                    <a href="index.php" class="btn btn-dark rounded-pill px-4">Kembali ke Beranda</a>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Footer-->
    <footer class="py-4 bg-dark text-white">
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
