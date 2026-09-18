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

// Parameter Pencarian, Filter Kategori, dan Sorting
$search          = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$category_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, trim($_GET['category'])) : '';
$sort_by         = isset($_GET['sort']) ? mysqli_real_escape_string($conn, trim($_GET['sort'])) : 'newest';

$where_conditions = ["1=1"];
if (!empty($search)) {
    $where_conditions[] = "(title LIKE '%$search%' OR author LIKE '%$search%' OR publisher LIKE '%$search%' OR isbn LIKE '%$search%')";
}
if (!empty($category_filter) && $category_filter !== 'all') {
    $where_conditions[] = "category = '$category_filter'";
}
$where_sql = "WHERE " . implode(" AND ", $where_conditions);

// Pengurutan (Sorting)
$order_sql = "ORDER BY created_at DESC";
switch ($sort_by) {
    case 'oldest':
        $order_sql = "ORDER BY created_at ASC";
        break;
    case 'price_low':
        $order_sql = "ORDER BY price ASC";
        break;
    case 'price_high':
        $order_sql = "ORDER BY price DESC";
        break;
    case 'title_asc':
        $order_sql = "ORDER BY title ASC";
        break;
    case 'title_desc':
        $order_sql = "ORDER BY title DESC";
        break;
    default:
        $order_sql = "ORDER BY created_at DESC";
        break;
}

// Ambil Kategori & Hitung Jumlah Produk
$cat_query = mysqli_query($conn, "SELECT c.id, c.name, 
                                   (SELECT COUNT(*) FROM books b WHERE b.category COLLATE utf8mb4_unicode_ci = c.name COLLATE utf8mb4_unicode_ci) AS total_books 
                                   FROM categories c 
                                   ORDER BY c.name ASC");
$categories_list = [];
if ($cat_query) {
    while ($row = mysqli_fetch_assoc($cat_query)) {
        $categories_list[] = $row;
    }
}

// Total Semua Buku
$total_all_books = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM books"))['total'] ?? 0;

// Hitung Total Item di Keranjang
$cart_count = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum($_SESSION['cart']);
}

// Query Produk Sesuai Filter
$books_query = mysqli_query($conn, "SELECT * FROM books $where_sql $order_sql");
$total_found = $books_query ? mysqli_num_rows($books_query) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="WarungBuku - Toko Buku Online Terlengkap dan Terpercaya" />
    <meta name="author" content="WarungBuku" />
    <!-- Favicon-->
    <link rel="icon" type="image/x-icon" href="assets/book.ico" />
    <!-- Bootstrap icons-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet" />
    <!-- Core theme CSS (includes Bootstrap)-->
    <link href="css/styles.css" rel="stylesheet" />
    <title>WarungBuku - Toko Buku Online Terpercaya</title>
    <style>
        :root {
            --brand-primary: #0B88D3;
            --brand-hover: #0970af;
        }

        /* Navbar Brand & Hover */
        .navbar-brand img {
            transition: transform 0.2s ease;
        }
        .navbar-brand:hover img {
            transform: rotate(-8deg) scale(1.05);
        }
        .nav-link.active {
            color: #38bdf8 !important;
            font-weight: 600;
        }

        /* Hero Header */
        .header-hero {
            background-color: #212529;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .quote-box {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 50px;
            padding: 8px 24px;
            display: inline-block;
        }

        /* Search Box */
        .search-container {
            background: #ffffff;
            border-radius: 50px;
            padding: 6px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
        }
        .search-container input {
            border: none;
            box-shadow: none;
            padding-left: 15px;
        }
        .search-container input:focus {
            outline: none;
            box-shadow: none;
        }

        /* Category Filter Chips */
        .category-chip {
            display: inline-flex;
            align-items: center;
            padding: 8px 18px;
            border-radius: 50px;
            background-color: #ffffff;
            color: #495057;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            border: 1px solid #dee2e6;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .category-chip:hover {
            color: var(--brand-primary);
            border-color: var(--brand-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .category-chip.active {
            background-color: #212529;
            color: #ffffff;
            border-color: #212529;
            box-shadow: 0 4px 12px rgba(33, 37, 41, 0.25);
        }
        .category-chip .count-badge {
            background: rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            padding: 2px 7px;
            font-size: 0.75rem;
            margin-left: 6px;
        }
        .category-chip.active .count-badge {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }

        /* Feature Cards */
        .feature-item {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }
        .feature-item:hover {
            border-color: var(--brand-primary);
            box-shadow: 0 6px 15px rgba(0,0,0,0.05);
        }
        .feature-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: rgba(11, 136, 211, 0.1);
            color: var(--brand-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        /* Product Cards */
        .card-product {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .card-product:hover {
            transform: translateY(-6px);
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.1);
            border-color: #ced4da;
        }

        /* Book Cover Container */
        .card-cover-wrapper {
            position: relative;
            background: #f8f9fa;
            height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 14px;
            border-bottom: 1px solid #f1f3f5;
        }
        .card-cover-img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            transition: transform 0.3s ease;
        }
        .card-product:hover .card-cover-img {
            transform: scale(1.04);
        }

        /* Fallback Book Cover */
        .cover-fallback {
            width: 130px;
            height: 180px;
            background: linear-gradient(135deg, #212529 0%, #343a40 100%);
            border-radius: 6px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 14px 10px;
            color: #ffffff;
            border-left: 4px solid var(--brand-primary);
            text-align: center;
            transition: transform 0.3s ease;
        }
        .card-product:hover .cover-fallback {
            transform: scale(1.04);
        }

        .product-title {
            font-size: 1.02rem;
            font-weight: 700;
            line-height: 1.35;
            color: #212529;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.7rem;
            margin-bottom: 6px;
        }
        .product-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .product-title a:hover {
            color: var(--brand-primary);
        }

        .product-price {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--brand-primary);
        }

        /* Buttons */
        .btn-brand {
            background-color: var(--brand-primary);
            border-color: var(--brand-primary);
            color: #ffffff;
        }
        .btn-brand:hover {
            background-color: var(--brand-hover);
            border-color: var(--brand-hover);
            color: #ffffff;
        }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <!-- ========================================== -->
    <!-- NAVIGATION BAR                             -->
    <!-- ========================================== -->
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark fixed-top shadow-sm">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
                <img src="assets/book.ico" alt="logo" width="36">
                <span>Warung<span style="color: #0B88D3;">Buku</span></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link <?= (empty($category_filter) && empty($search)) ? 'active' : '' ?>" aria-current="page" href="index.php">
                            <i class="bi bi-house-door me-1"></i> Beranda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#produk">
                            <i class="bi bi-book me-1"></i> Produk
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= !empty($category_filter) ? 'active' : '' ?>" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-tags me-1"></i> Kategori
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark shadow" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="index.php#produk">Semua produk (<?= $total_all_books ?>)</a></li>
                            <li><hr class="dropdown-divider" /></li>
                            <?php foreach($categories_list as $cat): ?>
                                <li>
                                    <a class="dropdown-item <?= ($category_filter === $cat['name']) ? 'active' : '' ?>" href="index.php?category=<?= urlencode($cat['name']) ?>#produk">
                                        <?= htmlspecialchars($cat['name']) ?> 
                                        <span class="badge bg-secondary rounded-pill ms-1 float-end"><?= $cat['total_books'] ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>

                    <li class="nav-item ms-lg-3 mt-2 mt-lg-0 d-flex align-items-center gap-2">
                        <a class="btn btn-outline-light btn-sm rounded-pill px-3 position-relative" href="cart.php">
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
                            <a class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold" style="background-color: #0B88D3; border: none;" href="register.php">
                                <i class="bi bi-person-plus me-1"></i> Daftar
                            </a>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ========================================== -->
    <!-- HEADER HERO BANNER                         -->
    <!-- ========================================== -->
    <header class="header-hero py-5 mt-5">
        <div class="container px-4 px-lg-5 my-4">
            <div class="row justify-content-center text-center text-white">
                <div class="col-lg-9">
                    
                    <div class="quote-box mb-3">
                        <span class="small text-white-50">
                            <i class="bi bi-quote me-1 text-info"></i>
                            <em>"Selama toko buku ada, selama itu pustaka bisa dibentuk kembali."</em> — Tan Malaka
                        </span>
                    </div>

                    <h1 class="display-4 fw-bolder mb-2">
                        Warung<span style="color: #0B88D3;">Buku</span>
                    </h1>
                    <p class="lead fw-normal text-white-50 mb-4">
                        Pusat buku bacaan berkualitas, mendidik, dan terpercaya dengan harga terjangkau.
                    </p>

                    <!-- Search Box -->
                    <div class="search-container mx-auto" style="max-width: 600px;">
                        <form action="index.php#produk" method="GET" class="d-flex align-items-center">
                            <?php if(!empty($category_filter)): ?>
                                <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>">
                            <?php endif; ?>
                            <span class="text-muted ps-2"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Cari judul buku, penulis, atau ISBN..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-dark rounded-pill px-4 fw-semibold flex-shrink-0">
                                Cari
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </header>

    <!-- ========================================== -->
    <!-- VALUE PROPOSITIONS (FITUR UNGGULAN)        -->
    <!-- ========================================== -->
    <section class="py-4 bg-white border-bottom shadow-sm">
        <div class="container px-4 px-lg-5">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="feature-item d-flex align-items-center gap-3">
                        <div class="feature-icon"><i class="bi bi-patch-check"></i></div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">100% Original</h6>
                            <small class="text-muted">Buku asli terjamin</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="feature-item d-flex align-items-center gap-3">
                        <div class="feature-icon"><i class="bi bi-truck"></i></div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Kirim Cepat</h6>
                            <small class="text-muted">Packing rapi & aman</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="feature-item d-flex align-items-center gap-3">
                        <div class="feature-icon"><i class="bi bi-tags"></i></div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Harga Hemat</h6>
                            <small class="text-muted">Koleksi terlengkap</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="feature-item d-flex align-items-center gap-3">
                        <div class="feature-icon"><i class="bi bi-ticket-perforated"></i></div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Diskon & Promo</h6>
                            <small class="text-muted">Voucher potongan belanja</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- SECTION PRODUK (KATALOG)                   -->
    <!-- ========================================== -->
    <section class="py-5" id="produk">
        <div class="container px-4 px-lg-5">

            <!-- Category Chips Filter -->
            <div class="d-flex align-items-center gap-2 overflow-auto pb-3 mb-4" style="scrollbar-width: thin;">
                <a href="index.php?search=<?= urlencode($search) ?>#produk" class="category-chip <?= empty($category_filter) ? 'active' : '' ?>">
                    <i class="bi bi-collection me-1"></i> Semua Kategori 
                    <span class="count-badge"><?= $total_all_books ?></span>
                </a>
                <?php foreach($categories_list as $c): ?>
                    <a href="index.php?category=<?= urlencode($c['name']) ?>&search=<?= urlencode($search) ?>#produk" 
                       class="category-chip <?= ($category_filter === $c['name']) ? 'active' : '' ?>">
                        <?= htmlspecialchars($c['name']) ?> 
                        <span class="count-badge"><?= $c['total_books'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Header Title & Sort Filter -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-2 border-bottom">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">
                        <?php if(!empty($category_filter)): ?>
                            Kategori: <?= htmlspecialchars($category_filter) ?>
                        <?php elseif(!empty($search)): ?>
                            Hasil Pencarian: "<?= htmlspecialchars($search) ?>"
                        <?php else: ?>
                            Katalog Buku Pilihan
                        <?php endif; ?>
                    </h3>
                    <p class="text-muted small mb-0">Menampilkan <strong><?= $total_found ?></strong> buku dari total <?= $total_all_books ?> koleksi</p>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <?php if(!empty($category_filter) || !empty($search)): ?>
                        <a href="index.php#produk" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                            <i class="bi bi-x-circle me-1"></i> Reset
                        </a>
                    <?php endif; ?>

                    <form action="index.php#produk" method="GET" class="d-flex align-items-center gap-2">
                        <?php if(!empty($category_filter)): ?>
                            <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>">
                        <?php endif; ?>
                        <?php if(!empty($search)): ?>
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <?php endif; ?>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-sort-down"></i></span>
                            <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="newest" <?= ($sort_by === 'newest') ? 'selected' : '' ?>>Terbaru</option>
                                <option value="oldest" <?= ($sort_by === 'oldest') ? 'selected' : '' ?>>Terlama</option>
                                <option value="price_low" <?= ($sort_by === 'price_low') ? 'selected' : '' ?>>Harga: Terendah</option>
                                <option value="price_high" <?= ($sort_by === 'price_high') ? 'selected' : '' ?>>Harga: Tertinggi</option>
                                <option value="title_asc" <?= ($sort_by === 'title_asc') ? 'selected' : '' ?>>Judul: A - Z</option>
                                <option value="title_desc" <?= ($sort_by === 'title_desc') ? 'selected' : '' ?>>Judul: Z - A</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Product Grid -->
            <?php if($total_found > 0): ?>
                <div class="row gx-4 gx-lg-5 row-cols-2 row-cols-md-3 row-cols-xl-4 justify-content-center">
                    <?php while($book = mysqli_fetch_assoc($books_query)): ?>
                        <?php
                            $book_id     = $book['id'];
                            $title       = htmlspecialchars($book['title']);
                            $author      = htmlspecialchars($book['author'] ?? '');
                            $category    = htmlspecialchars($book['category']);
                            $price       = (float)$book['price'];
                            $stock       = (int)$book['stock'];
                            $isbn        = htmlspecialchars($book['isbn'] ?? '');
                            $image_file  = $book['image'];
                            $has_image   = (!empty($image_file) && file_exists('assets/images/book/' . $image_file));

                            // Pesan WhatsApp Pre-filled
                            $wa_message = "Halo Admin WarungBuku, saya ingin memesan buku: *" . $book['title'] . "* (ISBN: " . $book['isbn'] . ") dengan harga Rp " . number_format($price, 0, ',', '.') . ". Apakah stok masih ada?";
                            $wa_url = "https://wa.me/" . $wa_phone . "?text=" . urlencode($wa_message);
                        ?>
                        <div class="col mb-5">
                            <div class="card-product">
                                
                                <!-- Cover Section -->
                                <div class="card-cover-wrapper">
                                    <!-- Badges -->
                                    <?php if($stock == 0): ?>
                                        <div class="badge bg-danger text-white position-absolute shadow-sm" style="top: 0.6rem; right: 0.6rem; z-index: 2;">
                                            Habis
                                        </div>
                                    <?php else: ?>
                                        <div class="badge bg-dark text-white position-absolute shadow-sm" style="top: 0.6rem; right: 0.6rem; z-index: 2;">
                                            <?= $category ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Product Image Link -->
                                    <a href="product-detail.php?id=<?= $book_id ?>" class="d-flex align-items-center justify-content-center w-100 h-100 text-decoration-none">
                                        <?php if($has_image): ?>
                                            <img class="card-cover-img" src="assets/images/book/<?= htmlspecialchars($image_file) ?>" alt="<?= $title ?>" loading="lazy" />
                                        <?php else: ?>
                                            <div class="cover-fallback">
                                                <small class="text-white-50" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px;">WarungBuku</small>
                                                <div>
                                                    <i class="bi bi-book fs-3 text-info mb-1 d-block"></i>
                                                    <div class="fw-bold" style="font-size: 0.78rem; line-height: 1.2; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                                        <?= $title ?>
                                                    </div>
                                                </div>
                                                <small class="text-white-50" style="font-size: 0.7rem;"><?= $author ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                </div>

                                <!-- Product Details -->
                                <div class="card-body p-3 d-flex flex-column text-center">
                                    <?php if(!empty($author)): ?>
                                        <div class="small text-muted mb-1" style="font-size: 0.8rem;">
                                            <i class="bi bi-person me-1"></i><?= $author ?>
                                        </div>
                                    <?php endif; ?>

                                    <h5 class="product-title" title="<?= $title ?>">
                                        <a href="product-detail.php?id=<?= $book_id ?>"><?= $title ?></a>
                                    </h5>

                                    <div class="mt-auto pt-2">
                                        <div class="product-price mb-3">
                                            Rp <?= number_format($price, 0, ',', '.') ?>
                                        </div>

                                        <!-- Product Actions -->
                                        <div class="d-flex gap-2 justify-content-center">
                                            <a class="btn btn-outline-dark btn-sm rounded-pill flex-grow-1" href="product-detail.php?id=<?= $book_id ?>">
                                                Detail
                                            </a>
                                            <?php if($stock > 0): ?>
                                                <a class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" style="background-color: #0B88D3; border: none;" href="cart.php?action=add&id=<?= $book_id ?>" title="Tambah ke Keranjang">
                                                    <i class="bi bi-cart-plus me-1"></i> Beli
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm rounded-pill px-3 disabled" disabled title="Stok Habis">
                                                    <i class="bi bi-x-circle me-1"></i> Habis
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="text-center py-5 my-4 bg-white rounded-3 shadow-sm border p-4">
                    <div class="text-muted mb-3 fs-1"><i class="bi bi-journal-x"></i></div>
                    <h5 class="fw-bold text-dark mb-2">Buku Tidak Ditemukan</h5>
                    <p class="text-muted mb-4">Tidak ada buku yang sesuai dengan kriteria pencarian atau kategori ini.</p>
                    <a href="index.php#produk" class="btn btn-dark rounded-pill px-4">Tampilkan Semua Buku</a>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <!-- ========================================== -->
    <!-- FOOTER                                     -->
    <!-- ========================================== -->
    <footer class="py-5 bg-dark text-white border-top border-secondary">
        <div class="container px-4 px-lg-5">
            <div class="row g-4 mb-4">
                <div class="col-lg-5">
                    <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <img src="assets/book.ico" alt="logo" width="28">
                        <span>Warung<span style="color: #0B88D3;">Buku</span></span>
                    </h5>
                    <p class="small text-white-50 mb-3">
                        Toko buku online tepercaya dengan koleksi buku original pilihan, harga bersahabat, dan pengiriman aman ke seluruh penjuru Nusantara.
                    </p>
                </div>
                <div class="col-6 col-lg-3">
                    <h6 class="fw-bold text-white mb-3">Kategori Buku</h6>
                    <ul class="list-unstyled small">
                        <?php foreach(array_slice($categories_list, 0, 4) as $cat): ?>
                            <li class="mb-2">
                                <a href="index.php?category=<?= urlencode($cat['name']) ?>#produk" class="text-white-50 text-decoration-none">
                                    <i class="bi bi-chevron-right text-primary me-1"></i> <?= htmlspecialchars($cat['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-6 col-lg-4">
                    <h6 class="fw-bold text-white mb-3">Layanan Pelanggan</h6>
                    <p class="small text-white-50 mb-2">Punya pertanyaan atau ingin mencari buku tertentu? Hubungi kami langsung:</p>
                    <a href="https://wa.me/<?= $wa_phone ?>" target="_blank" class="btn btn-success btn-sm rounded-pill px-3 fw-semibold">
                        <i class="bi bi-whatsapp me-1"></i> WhatsApp: <?= htmlspecialchars($admin_phone) ?>
                    </a>
                </div>
            </div>

            <hr class="border-secondary my-4">
            
            <div class="d-flex flex-wrap justify-content-between align-items-center small text-white-50 gap-2">
                <div>&copy; <?= date('Y') ?> <strong>WarungBuku</strong>. All Rights Reserved.</div>
                <div>"Membaca untuk Membuka Cakrawala Dunia"</div>
                <?php if(isset($_SESSION['status_login']) && $_SESSION['status_login'] == true && ($_SESSION['role'] ?? '') === 'admin'): ?>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3" style="font-size: 0.78rem;">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard Admin
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <!-- Bootstrap core JS-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script> 
    <!-- Core theme JS-->
    <script src="js/scripts.js"></script>
</body>
</html>
