<?php 
include("connection.php");
session_start();

// Validasi Sesi Login
if(!isset($_SESSION['status_login']) || $_SESSION['status_login'] != true){
    echo "<script>window.location='login.php'</script>";
    exit();
}

// Fungsi pembantu untuk membuat UUID v4 di PHP jika diperlukan
function generate_uuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // versi 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // varian RFC 4122
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// ============================================================
// HANDLER CRUD DATA BUKU (PRODUCTS)
// ============================================================

// 1. TAMBAH BUKU BARU
if(isset($_POST['add_book'])){
    $id               = generate_uuid();
    $isbn             = mysqli_real_escape_string($conn, trim($_POST['isbn']));
    $title            = mysqli_real_escape_string($conn, trim($_POST['title']));
    $author           = mysqli_real_escape_string($conn, trim($_POST['author']));
    $publisher        = mysqli_real_escape_string($conn, trim($_POST['publisher']));
    $publication_year = (int)$_POST['publication_year'];
    $category         = mysqli_real_escape_string($conn, trim($_POST['category']));
    
    // Parsing harga dengan menghilangkan tanda titik pemisah ribuan
    $raw_price        = str_replace('.', '', $_POST['price']);
    $raw_price        = str_replace(',', '.', $raw_price);
    $price            = (float)$raw_price;
    
    $stock            = (int)$_POST['stock'];
    $description      = mysqli_real_escape_string($conn, trim($_POST['description']));

    // Validasi ISBN Duplikat
    $check_isbn = mysqli_query($conn, "SELECT id FROM books WHERE isbn = '$isbn'");
    if(mysqli_num_rows($check_isbn) > 0){
        $_SESSION['msg_error'] = "ISBN <strong>" . htmlspecialchars($isbn) . "</strong> sudah terdaftar dalam sistem. Gunakan nomor ISBN lain.";
        header("Location: products.php");
        exit();
    }

    // Upload Cover Gambar Buku
    $image_filename = null;
    if(isset($_FILES['image']) && !empty($_FILES['image']['name'])){
        $filename      = $_FILES['image']['name'];
        $tmp_name      = $_FILES['image']['tmp_name'];
        $ext_parts     = explode('.', $filename);
        $ext           = strtolower(end($ext_parts));
        $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
        
        if(in_array($ext, $allowed_types)){
            $image_filename = 'book_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $target_dir     = 'assets/images/book/';

            if(!file_exists($target_dir)){
                mkdir($target_dir, 0777, true);
            }

            if(!move_uploaded_file($tmp_name, $target_dir . $image_filename)){
                $image_filename = null;
                $_SESSION['msg_error'] = "Gagal mengunggah file gambar cover.";
                header("Location: products.php");
                exit();
            }
        } else {
            $_SESSION['msg_error'] = "Format file gambar tidak didukung. Harap gunakan format JPG, JPEG, PNG, atau WEBP.";
            header("Location: products.php");
            exit();
        }
    }

    $image_sql = $image_filename ? "'$image_filename'" : "NULL";

    $insert = mysqli_query($conn, "INSERT INTO books (id, isbn, title, author, publisher, publication_year, category, image, price, stock, description, created_at, updated_at) 
                                   VALUES ('$id', '$isbn', '$title', '$author', '$publisher', '$publication_year', '$category', $image_sql, '$price', '$stock', '$description', NOW(), NOW())");
    if($insert){
        $_SESSION['msg_success'] = "Buku baru <strong>" . htmlspecialchars($title) . "</strong> berhasil ditambahkan.";
    } else {
        $_SESSION['msg_error'] = "Gagal menambahkan data buku: " . mysqli_error($conn);
    }
    header("Location: products.php");
    exit();
}

// 2. UBAH / EDIT DATA BUKU
if(isset($_POST['edit_book'])){
    $id               = mysqli_real_escape_string($conn, $_POST['book_id']);
    $isbn             = mysqli_real_escape_string($conn, trim($_POST['isbn']));
    $title            = mysqli_real_escape_string($conn, trim($_POST['title']));
    $author           = mysqli_real_escape_string($conn, trim($_POST['author']));
    $publisher        = mysqli_real_escape_string($conn, trim($_POST['publisher']));
    $publication_year = (int)$_POST['publication_year'];
    $category         = mysqli_real_escape_string($conn, trim($_POST['category']));
    
    // Parsing harga dengan menghilangkan tanda titik pemisah ribuan
    $raw_price        = str_replace('.', '', $_POST['price']);
    $raw_price        = str_replace(',', '.', $raw_price);
    $price            = (float)$raw_price;

    $stock            = (int)$_POST['stock'];
    $description      = mysqli_real_escape_string($conn, trim($_POST['description']));
    $old_image        = mysqli_real_escape_string($conn, $_POST['old_image'] ?? '');

    // Validasi ISBN Duplikat pada ID berbeda
    $check_isbn = mysqli_query($conn, "SELECT id FROM books WHERE isbn = '$isbn' AND id != '$id'");
    if(mysqli_num_rows($check_isbn) > 0){
        $_SESSION['msg_error'] = "Gagal memperbarui: ISBN <strong>" . htmlspecialchars($isbn) . "</strong> sudah digunakan oleh buku lain.";
        header("Location: products.php");
        exit();
    }

    // Upload & Ganti Cover Gambar Buku jika ada file baru
    $final_image = $old_image;
    if(isset($_FILES['image']) && !empty($_FILES['image']['name'])){
        $filename      = $_FILES['image']['name'];
        $tmp_name      = $_FILES['image']['tmp_name'];
        $ext_parts     = explode('.', $filename);
        $ext           = strtolower(end($ext_parts));
        $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
        
        if(in_array($ext, $allowed_types)){
            $image_filename = 'book_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $target_dir     = 'assets/images/book/';

            if(!file_exists($target_dir)){
                mkdir($target_dir, 0777, true);
            }

            if(move_uploaded_file($tmp_name, $target_dir . $image_filename)){
                // Hapus cover lama jika ada file fisiknya
                if(!empty($old_image) && file_exists($target_dir . $old_image)){
                    unlink($target_dir . $old_image);
                }
                $final_image = $image_filename;
            }
        } else {
            $_SESSION['msg_error'] = "Format file gambar tidak didukung. Harap gunakan format JPG, JPEG, PNG, atau WEBP.";
            header("Location: products.php");
            exit();
        }
    }

    $image_sql = !empty($final_image) ? "'$final_image'" : "NULL";

    $update = mysqli_query($conn, "UPDATE books SET 
        isbn             = '$isbn',
        title            = '$title',
        author           = '$author',
        publisher        = '$publisher',
        publication_year = '$publication_year',
        category         = '$category',
        image            = $image_sql,
        price            = '$price',
        stock            = '$stock',
        description      = '$description',
        updated_at       = NOW()
        WHERE id = '$id'");
        
    if($update){
        $_SESSION['msg_success'] = "Data buku <strong>" . htmlspecialchars($title) . "</strong> berhasil diperbarui.";
    } else {
        $_SESSION['msg_error'] = "Gagal memperbarui data buku: " . mysqli_error($conn);
    }
    header("Location: products.php");
    exit();
}

// 3. HAPUS DATA BUKU
if(isset($_GET['delete_book'])){
    $id = mysqli_real_escape_string($conn, $_GET['delete_book']);
    
    // Ambil info buku & file gambar sebelum dihapus
    $get_info = mysqli_query($conn, "SELECT title, image FROM books WHERE id = '$id'");
    $book_info = mysqli_fetch_assoc($get_info);
    $book_title = $book_info['title'] ?? 'Buku';
    $book_image = $book_info['image'] ?? '';

    // Hapus file fisik gambar jika ada
    if(!empty($book_image) && file_exists('assets/images/book/' . $book_image)){
        unlink('assets/images/book/' . $book_image);
    }

    $delete = mysqli_query($conn, "DELETE FROM books WHERE id = '$id'");
    if($delete){
        $_SESSION['msg_success'] = "Data buku <strong>" . htmlspecialchars($book_title) . "</strong> berhasil dihapus.";
    } else {
        $_SESSION['msg_error'] = "Gagal menghapus data buku: " . mysqli_error($conn);
    }
    header("Location: products.php");
    exit();
}

// ============================================================
// FILTER, PENCARIAN & PAGINASI (MAKSIMAL 10 PRODUK PER HALAMAN)
// ============================================================
$limit           = 10; // Maksimal 10 produk per halaman
$page            = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
$offset          = ($page > 1) ? ($page * $limit) - $limit : 0;

$search          = isset($_GET['search'])   ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$category_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, trim($_GET['category'])) : '';
$sort_by         = isset($_GET['sort'])     ? mysqli_real_escape_string($conn, trim($_GET['sort'])) : 'newest';

$where_clause = " WHERE 1=1 ";
if (!empty($search)) {
    $where_clause .= " AND (title LIKE '%$search%' OR isbn LIKE '%$search%' OR author LIKE '%$search%' OR publisher LIKE '%$search%') ";
}
if (!empty($category_filter)) {
    $where_clause .= " AND category = '$category_filter' ";
}

// Pengaturan Urutan Data
$order_clause = " ORDER BY created_at DESC ";
switch($sort_by){
    case 'oldest':
        $order_clause = " ORDER BY created_at ASC ";
        break;
    case 'title_asc':
        $order_clause = " ORDER BY title ASC ";
        break;
    case 'title_desc':
        $order_clause = " ORDER BY title DESC ";
        break;
    case 'price_low':
        $order_clause = " ORDER BY price ASC ";
        break;
    case 'price_high':
        $order_clause = " ORDER BY price DESC ";
        break;
    case 'stock_low':
        $order_clause = " ORDER BY stock ASC ";
        break;
    case 'stock_high':
        $order_clause = " ORDER BY stock DESC ";
        break;
    default:
        $order_clause = " ORDER BY created_at DESC ";
        break;
}

// Hitung total data terfilter
$total_records_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM books $where_clause");
$total_records       = mysqli_fetch_assoc($total_records_query)['total'] ?? 0;
$total_pages         = max(1, ceil($total_records / $limit));

// Query Data Buku
$query_books = "SELECT * FROM books $where_clause $order_clause LIMIT $offset, $limit";
$books       = mysqli_query($conn, $query_books);

// Statistik Keseluruhan
$stat_total_books_res = mysqli_query($conn, "SELECT COUNT(*) AS total_items, COALESCE(SUM(stock), 0) AS total_stock FROM books");
$stat_books           = mysqli_fetch_assoc($stat_total_books_res);
$total_all_books      = $stat_books['total_items'] ?? 0;
$total_all_stock      = $stat_books['total_stock'] ?? 0;

$stat_out_of_stock_res = mysqli_query($conn, "SELECT COUNT(*) AS total_out FROM books WHERE stock = 0");
$total_out_of_stock    = mysqli_fetch_assoc($stat_out_of_stock_res)['total_out'] ?? 0;

// Ambil Kategori untuk dropdown filter & form
$categories_query = mysqli_query($conn, "SELECT name FROM categories ORDER BY name ASC");
$category_list = [];
while($row = mysqli_fetch_assoc($categories_query)){
    $category_list[] = $row['name'];
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
    <title>Manajemen Produk Buku | WarungBuku Admin</title>
    <style>
        .stat-card {
            border: none;
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08) !important;
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
        .badge-isbn {
            font-family: monospace;
            font-size: 0.82rem;
            background-color: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 3px 6px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .table-responsive {
            border-radius: 8px;
        }
        .price-text {
            color: #0d6efd;
            font-weight: 700;
        }
        .uuid-badge {
            font-family: monospace;
            font-size: 0.75rem;
            background-color: #f8f9fa;
            color: #6c757d;
            border: 1px dashed #ced4da;
            padding: 3px 6px;
            border-radius: 4px;
            word-break: break-all;
        }
        .book-cover-thumb {
            width: 45px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            border: 1px solid #e2e8f0;
        }
        .book-cover-placeholder {
            width: 45px;
            height: 60px;
            border-radius: 6px;
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 1.25rem;
            border: 1px solid #cbd5e1;
        }
        .book-cover-modal {
            width: 100%;
            max-height: 280px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            border: 1px solid #cbd5e1;
        }
        .prefix-rp {
            background-color: #f1f5f9 !important;
            color: #475569 !important;
            font-weight: 700 !important;
            border-color: #ced4da;
            user-select: none;
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
                    <h1 class="h3 fw-bold mb-1"><i class="bi bi-book-half me-2"></i>Manajemen Produk Buku</h1>
                    <p class="text-white-50 mb-0 small">Kelola katalog buku, cover, informasi ISBN, harga, stok, dan deskripsi produk.</p>
                </div>
                <div>
                    <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddBook">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Buku Baru
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <section class="py-4">
        <div class="container-fluid px-4">

            <!-- Alert Notifikasi -->
            <?php if(isset($_SESSION['msg_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4 shadow-sm" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill fs-5 me-2"></i>
                        <div><?= $_SESSION['msg_success']; ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['msg_success']); ?>
            <?php endif; ?>

            <?php if(isset($_SESSION['msg_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                        <div><?= $_SESSION['msg_error']; ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['msg_error']); ?>
            <?php endif; ?>

            <!-- Ringkasan Statistik Cepat -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card bg-white shadow-sm p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary me-3">
                                <i class="bi bi-book fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">TOTAL JUDUL BUKU</div>
                                <div class="fs-4 fw-bold text-dark"><?= number_format($total_all_books, 0, ',', '.') ?> Judul</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card bg-white shadow-sm p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success me-3">
                                <i class="bi bi-boxes fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">TOTAL STOK KESELURUHAN</div>
                                <div class="fs-4 fw-bold text-dark"><?= number_format($total_all_stock, 0, ',', '.') ?> Eks</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card bg-white shadow-sm p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info me-3">
                                <i class="bi bi-tags fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">KATEGORI TERDAFTAR</div>
                                <div class="fs-4 fw-bold text-dark"><?= count($category_list) ?> Kategori</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card bg-white shadow-sm p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger me-3">
                                <i class="bi bi-exclamation-octagon fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">STOK HABIS (0)</div>
                                <div class="fs-4 fw-bold <?= $total_out_of_stock > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= $total_out_of_stock ?> Judul
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kartu Tabel Data Buku -->
            <div class="card card-custom mb-4">
                <div class="card-header card-header-custom bg-dark text-white py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="fw-bold fs-5 mb-0 d-flex align-items-center">
                        <i class="bi bi-table me-2"></i>Daftar Koleksi Buku
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-primary rounded-pill px-3 py-2">Ditemukan: <?= $total_records ?> Data (Maks. 10/Hal)</span>
                        <button class="btn btn-sm btn-outline-light rounded-circle" data-bs-toggle="modal" data-bs-target="#modalAddBook" title="Tambah Buku">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-4">

                    <!-- Form Pencarian & Filter -->
                    <form action="" method="get" class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Cari judul, ISBN, penulis..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="category" class="form-select">
                                <option value="">-- Semua Kategori --</option>
                                <?php foreach($category_list as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>" <?= ($category_filter == $cat) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="sort" class="form-select">
                                <option value="newest" <?= ($sort_by == 'newest') ? 'selected' : '' ?>>Urutkan: Terbaru</option>
                                <option value="oldest" <?= ($sort_by == 'oldest') ? 'selected' : '' ?>>Urutkan: Terlama</option>
                                <option value="title_asc" <?= ($sort_by == 'title_asc') ? 'selected' : '' ?>>Judul: A - Z</option>
                                <option value="title_desc" <?= ($sort_by == 'title_desc') ? 'selected' : '' ?>>Judul: Z - A</option>
                                <option value="price_low" <?= ($sort_by == 'price_low') ? 'selected' : '' ?>>Harga: Terendah</option>
                                <option value="price_high" <?= ($sort_by == 'price_high') ? 'selected' : '' ?>>Harga: Tertinggi</option>
                                <option value="stock_low" <?= ($sort_by == 'stock_low') ? 'selected' : '' ?>>Stok: Paling Sedikit</option>
                                <option value="stock_high" <?= ($sort_by == 'stock_high') ? 'selected' : '' ?>>Stok: Paling Banyak</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                            <?php if(!empty($search) || !empty($category_filter) || $sort_by != 'newest'): ?>
                                <a href="products.php" class="btn btn-outline-secondary" title="Reset Filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Tabel Data Buku -->
                    <div class="table-responsive border">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th width="50" class="text-center">No.</th>
                                    <th width="70" class="text-center">Cover</th>
                                    <th width="150">ISBN</th>
                                    <th>Judul Buku</th>
                                    <th>Penulis & Penerbit</th>
                                    <th width="90" class="text-center">Tahun</th>
                                    <th width="150">Kategori</th>
                                    <th width="130" class="text-end">Harga</th>
                                    <th width="110" class="text-center">Stok</th>
                                    <th width="130" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    if(mysqli_num_rows($books) == 0){
                                        echo '<tr>
                                                <td colspan="10" class="text-center py-5 text-muted">
                                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                                    <div class="fw-bold fs-6">Tidak ada data buku yang ditemukan</div>
                                                    <small>Silakan tambahkan data buku baru atau sesuaikan filter pencarian.</small>
                                                </td>
                                              </tr>';
                                    } else {
                                        $no = $offset + 1;
                                        while($book = mysqli_fetch_array($books)){
                                            // Tentukan badge status stok
                                            $stok_val = (int)$book['stock'];
                                            if($stok_val == 0){
                                                $stock_badge = '<span class="badge bg-danger rounded-pill px-2 py-1"><i class="bi bi-x-circle me-1"></i>Habis</span>';
                                            } else if($stok_val <= 10){
                                                $stock_badge = '<span class="badge bg-warning text-dark rounded-pill px-2 py-1"><i class="bi bi-exclamation-circle me-1"></i>' . $stok_val . ' Eks</span>';
                                            } else {
                                                $stock_badge = '<span class="badge bg-success rounded-pill px-2 py-1"><i class="bi bi-check-circle me-1"></i>' . $stok_val . ' Eks</span>';
                                            }

                                            // Formatted price untuk modal edit
                                            $formatted_price_edit = number_format($book['price'], 0, ',', '.');
                                ?>
                                <tr>
                                    <td class="text-center fw-semibold text-muted"><?= $no++ ?></td>
                                    <td class="text-center">
                                        <?php if(!empty($book['image']) && file_exists('assets/images/book/' . $book['image'])): ?>
                                            <img src="assets/images/book/<?= htmlspecialchars($book['image']) ?>" alt="Cover <?= htmlspecialchars($book['title']) ?>" class="book-cover-thumb" data-bs-toggle="modal" data-bs-target="#modalDetailBook<?= $book['id'] ?>" style="cursor: pointer;">
                                        <?php else: ?>
                                            <div class="book-cover-placeholder mx-auto" title="Cover tidak tersedia">
                                                <i class="bi bi-journal-bookmark"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="badge-isbn" title="ISBN Buku">
                                            <i class="bi bi-upc-scan text-primary"></i>
                                            <?= htmlspecialchars($book['isbn']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($book['title']) ?></div>
                                        <?php if(!empty($book['description'])): ?>
                                            <small class="text-muted text-truncate d-block" style="max-width: 250px;">
                                                <?= htmlspecialchars(substr($book['description'], 0, 75)) . (strlen($book['description']) > 75 ? '...' : '') ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><i class="bi bi-person me-1 text-secondary"></i><?= htmlspecialchars($book['author']) ?></div>
                                        <small class="text-muted"><i class="bi bi-building me-1"></i><?= htmlspecialchars($book['publisher']) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border"><?= $book['publication_year'] ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1 rounded">
                                            <i class="bi bi-tag-fill me-1"></i><?= htmlspecialchars($book['category']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="price-text">Rp <?= number_format($book['price'], 0, ',', '.') ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?= $stock_badge ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <!-- Tombol Detail -->
                                            <button class="btn btn-outline-info" title="Lihat Detail Buku" data-bs-toggle="modal" data-bs-target="#modalDetailBook<?= $book['id'] ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <!-- Tombol Edit -->
                                            <button class="btn btn-outline-primary" title="Ubah Data Buku" data-bs-toggle="modal" data-bs-target="#modalEditBook<?= $book['id'] ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <!-- Tombol Hapus -->
                                            <a href="products.php?delete_book=<?= $book['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus buku \'<?= addslashes(htmlspecialchars($book['title'])) ?>\'? Tindakan ini tidak dapat dibatalkan.')" class="btn btn-outline-danger" title="Hapus Buku">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                                <!-- ======================================================= -->
                                <!-- MODAL DETAIL BUKU -->
                                <!-- ======================================================= -->
                                <div class="modal fade" id="modalDetailBook<?= $book['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header bg-dark text-white">
                                                <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Detail Informasi Buku</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row g-4">
                                                    <!-- Cover Buku di Modal Detail -->
                                                    <div class="col-md-4 text-center">
                                                        <?php if(!empty($book['image']) && file_exists('assets/images/book/' . $book['image'])): ?>
                                                            <img src="assets/images/book/<?= htmlspecialchars($book['image']) ?>" alt="Cover <?= htmlspecialchars($book['title']) ?>" class="book-cover-modal mb-2">
                                                        <?php else: ?>
                                                            <div class="book-cover-placeholder w-100 mb-2 py-5" style="height: 240px;">
                                                                <div class="text-center">
                                                                    <i class="bi bi-image fs-1 text-muted d-block mb-1"></i>
                                                                    <span class="small text-muted">Belum ada cover</span>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <span class="badge-isbn w-100 justify-content-center py-2"><i class="bi bi-upc-scan me-1"></i><?= htmlspecialchars($book['isbn']) ?></span>
                                                    </div>

                                                    <div class="col-md-8">
                                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                                            <span class="badge bg-primary px-3 py-2"><i class="bi bi-tag-fill me-1"></i><?= htmlspecialchars($book['category']) ?></span>
                                                            <div class="fs-4 fw-bold text-primary">Rp <?= number_format($book['price'], 0, ',', '.') ?></div>
                                                        </div>

                                                        <h4 class="fw-bold text-dark mb-3"><?= htmlspecialchars($book['title']) ?></h4>

                                                        <div class="row g-2 mb-3">
                                                            <div class="col-sm-6">
                                                                <small class="text-muted d-block">Penulis</small>
                                                                <div class="fw-semibold text-dark"><i class="bi bi-person-fill text-secondary me-1"></i><?= htmlspecialchars($book['author']) ?></div>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <small class="text-muted d-block">Penerbit</small>
                                                                <div class="fw-semibold text-dark"><i class="bi bi-building text-secondary me-1"></i><?= htmlspecialchars($book['publisher']) ?></div>
                                                            </div>
                                                            <div class="col-sm-6 mt-2">
                                                                <small class="text-muted d-block">Tahun Terbit</small>
                                                                <div class="fw-semibold text-dark"><i class="bi bi-calendar3 text-secondary me-1"></i><?= $book['publication_year'] ?></div>
                                                            </div>
                                                            <div class="col-sm-6 mt-2">
                                                                <small class="text-muted d-block">Status Stok</small>
                                                                <div><?= $stock_badge ?> <span class="ms-1 small text-muted">(<?= $book['stock'] ?> unit)</span></div>
                                                            </div>
                                                        </div>

                                                        <small class="text-muted d-block mb-1">Deskripsi / Sinopsis Buku</small>
                                                        <div class="p-3 bg-light rounded border text-secondary small" style="line-height: 1.6; max-height: 150px; overflow-y: auto;">
                                                            <?= !empty($book['description']) ? nl2br(htmlspecialchars($book['description'])) : '<em class="text-muted">Tidak ada deskripsi tersedia.</em>' ?>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 border-top pt-3">
                                                        <div class="row text-muted small">
                                                            <div class="col-md-6 mb-1">
                                                                <strong>ID Buku (UUID):</strong><br>
                                                                <span class="uuid-badge"><?= $book['id'] ?></span>
                                                            </div>
                                                            <div class="col-md-3 mb-1">
                                                                <strong>Dibuat Pada:</strong><br>
                                                                <span><?= date('d M Y, H:i', strtotime($book['created_at'])) ?></span>
                                                            </div>
                                                            <div class="col-md-3 mb-1">
                                                                <strong>Diperbarui:</strong><br>
                                                                <span><?= date('d M Y, H:i', strtotime($book['updated_at'])) ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#modalEditBook<?= $book['id'] ?>">
                                                    <i class="bi bi-pencil-square me-1"></i> Ubah Data
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ======================================================= -->
                                <!-- MODAL EDIT BUKU -->
                                <!-- ======================================================= -->
                                <div class="modal fade" id="modalEditBook<?= $book['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content border-0 shadow">
                                            <form action="" method="post" enctype="multipart/form-data">
                                                <div class="modal-header bg-dark text-white">
                                                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Ubah Data Buku</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                                    <input type="hidden" name="old_image" value="<?= htmlspecialchars($book['image'] ?? '') ?>">
                                                    
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold">Nomor ISBN <span class="text-danger">*</span></label>
                                                            <input type="text" name="isbn" class="form-control" value="<?= htmlspecialchars($book['isbn']) ?>" placeholder="e.g. 978-602-03-8591-4" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold">Kategori Buku <span class="text-danger">*</span></label>
                                                            <select name="category" class="form-select" required>
                                                                <option value="">-- Pilih Kategori --</option>
                                                                <?php foreach($category_list as $cat): ?>
                                                                    <option value="<?= htmlspecialchars($cat) ?>" <?= ($book['category'] == $cat) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($cat) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                                <?php if(!in_array($book['category'], $category_list) && !empty($book['category'])): ?>
                                                                    <option value="<?= htmlspecialchars($book['category']) ?>" selected><?= htmlspecialchars($book['category']) ?></option>
                                                                <?php endif; ?>
                                                            </select>
                                                        </div>

                                                        <div class="col-12">
                                                            <label class="form-label fw-semibold">Judul Buku <span class="text-danger">*</span></label>
                                                            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($book['title']) ?>" placeholder="Masukkan judul buku lengkap" required>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold">Penulis <span class="text-danger">*</span></label>
                                                            <input type="text" name="author" class="form-control" value="<?= htmlspecialchars($book['author']) ?>" placeholder="Nama penulis / pengarang" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold">Penerbit <span class="text-danger">*</span></label>
                                                            <input type="text" name="publisher" class="form-control" value="<?= htmlspecialchars($book['publisher']) ?>" placeholder="Nama penerbit buku" required>
                                                        </div>

                                                        <div class="col-md-4">
                                                            <label class="form-label fw-semibold">Tahun Terbit <span class="text-danger">*</span></label>
                                                            <input type="number" name="publication_year" class="form-control" min="1900" max="<?= date('Y') + 1 ?>" value="<?= $book['publication_year'] ?>" required>
                                                        </div>
                                                        
                                                        <!-- Input Harga dengan Prefix Rp dan Separator Titik Realtime -->
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-semibold">Harga Buku <span class="text-danger">*</span></label>
                                                            <div class="input-group">
                                                                <span class="input-group-text prefix-rp">Rp</span>
                                                                <input type="text" name="price" class="form-control rupiah-input text-end fw-semibold" value="<?= $formatted_price_edit ?>" placeholder="50.000" required>
                                                            </div>
                                                        </div>

                                                        <div class="col-md-4">
                                                            <label class="form-label fw-semibold">Jumlah Stok <span class="text-danger">*</span></label>
                                                            <input type="number" name="stock" class="form-control text-center" min="0" value="<?= $book['stock'] ?>" required>
                                                        </div>

                                                        <!-- Input Cover Gambar Buku -->
                                                        <div class="col-12">
                                                            <label class="form-label fw-semibold">Ganti Cover Gambar Buku (Opsional)</label>
                                                            <div class="d-flex align-items-center gap-3">
                                                                <?php if(!empty($book['image']) && file_exists('assets/images/book/' . $book['image'])): ?>
                                                                    <img src="assets/images/book/<?= htmlspecialchars($book['image']) ?>" alt="Cover Lama" class="book-cover-thumb" style="width: 50px; height: 65px;">
                                                                <?php endif; ?>
                                                                <div class="flex-grow-1">
                                                                    <input type="file" name="image" class="form-control" accept="image/png, image/jpeg, image/jpg, image/webp">
                                                                    <small class="text-muted">Biarkan kosong jika tidak ingin mengubah cover gambar (Format: JPG, PNG, WEBP).</small>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12">
                                                            <label class="form-label fw-semibold">Deskripsi / Sinopsis Buku</label>
                                                            <textarea name="description" class="form-control" rows="3" placeholder="Tuliskan deskripsi atau sinopsis singkat buku..."><?= htmlspecialchars($book['description'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_book" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <?php 
                                        }
                                    } 
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginasi Maksimal 10 Produk Per Halaman -->
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4">
                        <small class="text-muted">
                            <?php if($total_records > 0): ?>
                                Menampilkan <?= min($offset + 1, $total_records) ?> sampai <?= min($offset + $limit, $total_records) ?> dari <?= $total_records ?> total data buku (Maks. 10/Hal)
                            <?php else: ?>
                                Menampilkan 0 data
                            <?php endif; ?>
                        </small>
                        <?php if($total_pages > 1): ?>
                            <nav aria-label="Navigasi Halaman Produk">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>&sort=<?= urlencode($sort_by) ?>" aria-label="Sebelumnya">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>&sort=<?= urlencode($sort_by) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>&sort=<?= urlencode($sort_by) ?>" aria-label="Berikutnya">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div>
    </section>

    <!-- ======================================================= -->
    <!-- MODAL TAMBAH BUKU BARU -->
    <!-- ======================================================= -->
    <div class="modal fade" id="modalAddBook" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Buku Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nomor ISBN <span class="text-danger">*</span></label>
                                <input type="text" name="isbn" class="form-control" placeholder="Contoh: 978-602-03-8591-4" required>
                                <small class="text-muted" style="font-size: 11px;">Nomor ISBN harus unik untuk setiap judul buku.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Kategori Buku <span class="text-danger">*</span></label>
                                <select name="category" class="form-select" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach($category_list as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Judul Buku <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" placeholder="Masukkan judul buku lengkap" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Penulis <span class="text-danger">*</span></label>
                                <input type="text" name="author" class="form-control" placeholder="Nama penulis / pengarang" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Penerbit <span class="text-danger">*</span></label>
                                <input type="text" name="publisher" class="form-control" placeholder="Nama penerbit buku" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Tahun Terbit <span class="text-danger">*</span></label>
                                <input type="number" name="publication_year" class="form-control" min="1900" max="<?= date('Y') + 1 ?>" value="<?= date('Y') ?>" required>
                            </div>
                            
                            <!-- Input Harga dengan Prefix Rp dan Separator Titik Realtime -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Harga Buku <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text prefix-rp">Rp</span>
                                    <input type="text" name="price" class="form-control rupiah-input text-end fw-semibold" placeholder="50.000" required>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Jumlah Stok Awal <span class="text-danger">*</span></label>
                                <input type="number" name="stock" class="form-control text-center" min="0" value="0" required>
                            </div>

                            <!-- Input Cover Gambar Buku Baru -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">Cover Gambar Buku</label>
                                <input type="file" name="image" class="form-control" accept="image/png, image/jpeg, image/jpg, image/webp">
                                <small class="text-muted">Format yang didukung: JPG, JPEG, PNG, WEBP (Opsional).</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Deskripsi / Sinopsis Buku</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Tuliskan deskripsi atau sinopsis singkat buku..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_book" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Buku</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer-->
    <footer class="py-4 bg-dark mt-auto">
        <div class="container text-center text-white-50">
            <small>&copy; <?= date('Y') ?> WarungBuku Admin Panel. All Rights Reserved.</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi Real-time Separator Ribuan dengan Titik (Rupiah Input Formatter)
        function formatRupiahRealtime(angka) {
            let number_string = angka.replace(/[^,\d]/g, '').toString();
            let split         = number_string.split(',');
            let sisa          = split[0].length % 3;
            let rupiah        = split[0].substr(0, sisa);
            let ribuan        = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                let separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
            return rupiah;
        }

        // Pasang Event Listener ke seluruh elemen berkelas .rupiah-input
        document.addEventListener('input', function(e) {
            if (e.target && e.target.classList.contains('rupiah-input')) {
                e.target.value = formatRupiahRealtime(e.target.value);
            }
        });
    </script>
</body>
</html>
