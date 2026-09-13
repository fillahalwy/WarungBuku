<?php 
include('connection.php');
session_start();
if(!isset($_SESSION['status_login']) || $_SESSION['status_login'] != true){
   echo "<script> window.location='login.php' </script>";
   exit();
}

// ----------------------------------------------------
// PRODUCT CRUD HANDLERS
// ----------------------------------------------------

// 1. ADD PRODUCT
if(isset($_POST['add_product'])){
    $name        = mysqli_real_escape_string($conn, trim($_POST['name']));
    $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
    $price       = mysqli_real_escape_string($conn, $_POST['price']);
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));

    $filename        = $_FILES['image']['name'];
    $tmp_name        = $_FILES['image']['tmp_name'];
    $ext_parts       = explode('.', $filename);
    $ext             = strtolower(end($ext_parts));
    $allowed_types   = ['jpg', 'jpeg', 'png', 'webp'];
    
    if(in_array($ext, $allowed_types)){
        $image_filename = 'product_' . time() . '.' . $ext;
        $target_dir     = 'assets/images/product/';

        if(!file_exists($target_dir)){
            mkdir($target_dir, 0777, true);
        }

        if(move_uploaded_file($tmp_name, $target_dir . $image_filename)){
            $insert = mysqli_query($conn, "INSERT INTO products (name, category_id, price, image, description) 
                                           VALUES ('$name', '$category_id', '$price', '$image_filename', '$description')");
            if($insert){
                $_SESSION['msg_success'] = "New product has been added successfully.";
            } else {
                $_SESSION['msg_error'] = "Failed to save product to database: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['msg_error'] = "Failed to upload product image.";
        }
    } else {
        $_SESSION['msg_error'] = "Unsupported file format. Please use JPG, JPEG, PNG, or WEBP.";
    }
    header("Location: dashboard.php");
    exit();
}

// 2. EDIT PRODUCT
if(isset($_POST['edit_product'])){
    $id          = mysqli_real_escape_string($conn, $_POST['product_id']);
    $name        = mysqli_real_escape_string($conn, trim($_POST['name']));
    $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
    $price       = mysqli_real_escape_string($conn, $_POST['price']);
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $old_image   = mysqli_real_escape_string($conn, $_POST['old_image']);

    $filename = $_FILES['image']['name'];
    $tmp_name = $_FILES['image']['tmp_name'];

    // Replace image if a new file is uploaded
    if(!empty($filename)){
        $ext_parts     = explode('.', $filename);
        $ext           = strtolower(end($ext_parts));
        $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
        
        if(in_array($ext, $allowed_types)){
            $image_filename = 'product_' . time() . '.' . $ext;
            $target_dir     = 'assets/images/product/';

            if(move_uploaded_file($tmp_name, $target_dir . $image_filename)){
                // Delete old image if it exists
                if(!empty($old_image) && file_exists($target_dir . $old_image)){
                    unlink($target_dir . $old_image);
                }
                $final_image = $image_filename;
            } else {
                $final_image = $old_image;
            }
        } else {
            $_SESSION['msg_error'] = "Unsupported image format.";
            header("Location: dashboard.php");
            exit();
        }
    } else {
        $final_image = $old_image;
    }

    $update = mysqli_query($conn, "UPDATE products SET 
        name        = '$name',
        category_id = '$category_id',
        price       = '$price',
        image       = '$final_image',
        description = '$description'
        WHERE id = '$id'");

    if($update){
        $_SESSION['msg_success'] = "Product updated successfully.";
    } else {
        $_SESSION['msg_error'] = "Failed to update product: " . mysqli_error($conn);
    }
    header("Location: dashboard.php");
    exit();
}

// 3. DELETE PRODUCT
if(isset($_GET['delete_product'])){
    $id = mysqli_real_escape_string($conn, $_GET['delete_product']);
    
    // Fetch image to delete from filesystem
    $get_img  = mysqli_query($conn, "SELECT image FROM products WHERE id = '$id'");
    $img_data = mysqli_fetch_assoc($get_img);
    if(!empty($img_data['image']) && file_exists('assets/images/product/' . $img_data['image'])){
        unlink('assets/images/product/' . $img_data['image']);
    }

    $delete = mysqli_query($conn, "DELETE FROM products WHERE id = '$id'");
    if($delete){
        $_SESSION['msg_success'] = "Product deleted successfully.";
    } else {
        $_SESSION['msg_error'] = "Failed to delete product: " . mysqli_error($conn);
    }
    header("Location: dashboard.php");
    exit();
}

// ----------------------------------------------------
// PAGINATION & FILTER CONFIG
// ----------------------------------------------------
$limit           = 5;
$page            = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
$offset          = ($page > 1) ? ($page * $limit) - $limit : 0;

$search          = isset($_GET['search'])   ? mysqli_real_escape_string($conn, trim($_GET['search']))   : '';
$category_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category'])       : '';

$where_clause = " WHERE 1=1 ";
if ($search != '') {
    $where_clause .= " AND p.name LIKE '%$search%' ";
}
if ($category_filter != '') {
    $where_clause .= " AND p.category_id = '$category_filter' ";
}

// Count totals
$total_records  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products p $where_clause"))['total'] ?? 0;
$total_pages    = ceil($total_records / $limit);

// Stats
$count_products   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products"))['total']   ?? 0;
$count_categories = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM categories"))['total'] ?? 0;
$admin_name       = isset($_SESSION['global']->name) ? $_SESSION['global']->name : 'Admin';

// Fetch products
$query_products = "SELECT p.*, c.name AS category_name 
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id
                   $where_clause 
                   ORDER BY p.id DESC 
                   LIMIT $offset, $limit";
$products = mysqli_query($conn, $query_products);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" type="image/x-icon" href="assets/book.ico" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <title>Dashboard Admin | WarungBuku</title>
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
            .product-img-thumb {
                width: 50px;
                height: 50px;
                object-fit: cover;
                border-radius: 6px;
                border: 1px solid #dee2e6;
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
                        <h1 class="h3 fw-bold mb-1">Welcome, <?= htmlspecialchars($admin_name) ?>! 👋</h1>
                        <p class="lead text-white-50 fs-6 mb-0">Manage your product catalog and store information directly from this Dashboard.</p>
                    </div>
                    <div>
                        <button class="btn btn-primary px-4 py-2 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddProduct">
                            <i class="bi bi-plus-circle me-1"></i> Add New Product
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Section Main Content -->
        <section class="py-4">
            <div class="container-fluid px-4">
                
                <!-- Stat Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-lg-6">
                        <div class="card stat-card bg-white shadow-sm p-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary me-3">
                                    <i class="bi bi-box-seam fs-2"></i>
                                </div>
                                <div>
                                    <div class="text-muted small fw-semibold">TOTAL PRODUCTS</div>
                                    <div class="fs-3 fw-bold"><?= $count_products ?> Items</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6">
                        <div class="card stat-card bg-white shadow-sm p-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info me-3">
                                    <i class="bi bi-tags fs-2"></i>
                                </div>
                                <div>
                                    <div class="text-muted small fw-semibold">TOTAL CATEGORIES</div>
                                    <div class="fs-3 fw-bold"><?= $count_categories ?> Categories</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if(isset($_SESSION['msg_success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i><?= $_SESSION['msg_success']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['msg_success']); ?>
                <?php endif; ?>

                <?php if(isset($_SESSION['msg_error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $_SESSION['msg_error']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['msg_error']); ?>
                <?php endif; ?>

                <!-- Product Inventory Table -->
                <div class="card card-custom mb-5">
                    <div class="card-header card-header-custom bg-dark text-white py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="fw-bold fs-5 mb-0"><i class="bi bi-table me-2"></i>Product Inventory</div>
                        <div class="d-flex gap-2 align-items-center">
                            <span class="badge bg-primary rounded-pill px-3 py-2">Total: <?= $total_records ?> Records</span>
                            <button class="btn btn-sm btn-outline-light rounded-circle" data-bs-toggle="modal" data-bs-target="#modalAddProduct" title="Add Product">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        
                        <!-- Search & Filter Form -->
                        <form action="" method="get" class="row g-3 mb-4">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Search product name..." value="<?= htmlspecialchars($search) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select name="category" class="form-select">
                                    <option value="">-- All Categories --</option>
                                    <?php 
                                        $categories_list = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
                                        while($cat = mysqli_fetch_array($categories_list)){
                                            $selected = ($category_filter == $cat['id']) ? 'selected' : '';
                                            echo "<option value='".$cat['id']."' $selected>" . htmlspecialchars($cat['name']) . "</option>";
                                        }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-dark w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                                <?php if($search != '' || $category_filter != ''): ?>
                                    <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i> Reset</a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <!-- Products Table -->
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle border">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="50" class="text-center">No.</th>
                                        <th width="80" class="text-center">Image</th>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th width="120" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        if(mysqli_num_rows($products) == 0){
                                            echo '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No products found.</td></tr>';
                                        } else {
                                            $row_num = $offset + 1;
                                            while($p = mysqli_fetch_array($products)){
                                                $img_src = !empty($p['image']) && file_exists('assets/images/product/' . $p['image']) 
                                                    ? 'assets/images/product/' . $p['image'] 
                                                    : 'https://dummyimage.com/100x100/dee2e6/6c757d.jpg&text=No+Image';
                                    ?>
                                    <tr>
                                        <td class="text-center fw-semibold"><?= $row_num++ ?></td>
                                        <td class="text-center">
                                            <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="product-img-thumb shadow-sm">
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($p['name']) ?></div>
                                            <?php if(!empty($p['description'])): ?>
                                                <small class="text-muted text-truncate d-inline-block" style="max-width: 250px;"><?= htmlspecialchars($p['description']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info text-dark px-2 py-1"><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></span>
                                        </td>
                                        <td class="fw-bold text-success">
                                            Rp <?= number_format($p['price'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-primary" title="Edit Product" data-bs-toggle="modal" data-bs-target="#modalEditProduct<?= $p['id'] ?>">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <a href="dashboard.php?delete_product=<?= $p['id'] ?>" onclick="return confirm('Are you sure you want to delete this product?')" class="btn btn-outline-danger" title="Delete Product">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- EDIT PRODUCT MODAL -->
                                    <div class="modal fade" id="modalEditProduct<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form action="" method="post" enctype="multipart/form-data">
                                                    <div class="modal-header bg-dark text-white">
                                                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Product</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                                        <input type="hidden" name="old_image" value="<?= $p['image'] ?>">

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                                                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($p['name']) ?>" required>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                                            <select class="form-select" name="category_id" required>
                                                                <?php
                                                                    $cat_opts = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
                                                                    while($co = mysqli_fetch_array($cat_opts)){
                                                                        $sel = ($co['id'] == $p['category_id']) ? 'selected' : '';
                                                                        echo "<option value='".$co['id']."' $sel>" . htmlspecialchars($co['name']) . "</option>";
                                                                    }
                                                                ?>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Price (Rp) <span class="text-danger">*</span></label>
                                                            <div class="input-group">
                                                                <span class="input-group-text">Rp</span>
                                                                <input type="number" name="price" class="form-control" min="0" value="<?= $p['price'] ?>" required>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Product Image</label>
                                                            <div class="d-flex align-items-center gap-3 mb-2">
                                                                <img src="<?= $img_src ?>" width="60" height="60" class="product-img-thumb">
                                                                <small class="text-muted">Current image: <?= htmlspecialchars($p['image'] ?? 'None') ?></small>
                                                            </div>
                                                            <input type="file" name="image" class="form-control" accept="image/*">
                                                            <div class="form-text">Leave empty to keep the current image.</div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Description</label>
                                                            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($p['description']) ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="edit_product" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Changes</button>
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

                        <!-- Pagination -->
                        <?php if($total_pages > 1): ?>
                        <nav aria-label="Product Pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>">&laquo; Previous</a>
                                </li>
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>">Next &raquo;</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>

                    </div>
                </div>

            </div>
        </section>

        <!-- ADD PRODUCT MODAL -->
        <div class="modal fade" id="modalAddProduct" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form action="" method="post" enctype="multipart/form-data">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Product</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="Enter product or book title..." required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select class="form-select" name="category_id" required>
                                    <option value="" disabled selected>--- Select Category ---</option>
                                    <?php
                                        $cat_opts2 = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
                                        while($co2 = mysqli_fetch_array($cat_opts2)){
                                            echo "<option value='".$co2['id']."'>" . htmlspecialchars($co2['name']) . "</option>";
                                        }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Price (Rp) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" name="price" class="form-control" min="0" placeholder="e.g. 50000" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Product Image <span class="text-danger">*</span></label>
                                <input type="file" name="image" class="form-control" accept="image/*" required>
                                <div class="form-text">Allowed formats: .jpg, .jpeg, .png, .webp</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Enter a detailed product description..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_product" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Product</button>
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
    </body>
</html>