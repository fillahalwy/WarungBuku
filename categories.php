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

// Add Category
if(isset($_POST['add_category'])){
    $category_name = mysqli_real_escape_string($conn, trim($_POST['name']));
    if(!empty($category_name)){
        $insert = mysqli_query($conn, "INSERT INTO categories (name) VALUES ('$category_name')");
        if($insert){
            $_SESSION['msg_success'] = "New category added successfully.";
        } else {
            $_SESSION['msg_error'] = "Failed to add category: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['msg_error'] = "Category name cannot be empty.";
    }
    header("Location: categories.php");
    exit();
}

// Edit Category
if(isset($_POST['edit_category'])){
    $id            = mysqli_real_escape_string($conn, $_POST['category_id']);
    $category_name = mysqli_real_escape_string($conn, trim($_POST['name']));
    if(!empty($category_name)){
        $update = mysqli_query($conn, "UPDATE categories SET name = '$category_name' WHERE id = '$id'");
        if($update){
            $_SESSION['msg_success'] = "Category updated successfully.";
        } else {
            $_SESSION['msg_error'] = "Failed to update category: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['msg_error'] = "Category name cannot be empty.";
    }
    header("Location: categories.php");
    exit();
}

// Delete Category
if(isset($_GET['delete_category'])){
    $id = mysqli_real_escape_string($conn, $_GET['delete_category']);
    $delete = mysqli_query($conn, "DELETE FROM categories WHERE id = '$id'");
    if($delete){
        $_SESSION['msg_success'] = "Category deleted successfully.";
    } else {
        $_SESSION['msg_error'] = "Failed to delete category: " . mysqli_error($conn);
    }
    header("Location: categories.php");
    exit();
}

// Fetch all categories with product count
$query = "SELECT c.*, COUNT(p.id) AS total_products 
          FROM categories c 
          LEFT JOIN products p ON c.id = p.category_id 
          GROUP BY c.id 
          ORDER BY c.id DESC";
$categories = mysqli_query($conn, $query);
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
        <title>Categories | WarungBuku Admin</title>
        <style>
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
                        <h1 class="h3 fw-bold mb-1"><i class="bi bi-tags me-2"></i>Manage Categories</h1>
                        <p class="text-white-50 mb-0 small">Manage product categories for grouping store items.</p>
                    </div>
                    <div>
                        <button class="btn btn-primary rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddCategory">
                            <i class="bi bi-plus-circle me-1"></i> Add New Category
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <section class="py-4">
            <div class="container-fluid px-4">
                
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

                <div class="card card-custom">
                    <div class="card-header card-header-custom bg-dark text-white py-3 px-4 d-flex align-items-center justify-content-between">
                        <div class="fw-bold fs-5 mb-0"><i class="bi bi-list-ul me-2"></i>Category List</div>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle border">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="70" class="text-center">No.</th>
                                        <th>Category Name</th>
                                        <th width="180" class="text-center">Total Products</th>
                                        <th width="150" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        if(mysqli_num_rows($categories) == 0){
                                             echo '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No categories found.</td></tr>';
                                        } else {
                                            $no = 1;
                                            while($cat = mysqli_fetch_array($categories)){
                                    ?>
                                    <tr>
                                        <td class="text-center fw-semibold"><?= $no++ ?></td>
                                        <td class="fw-bold text-dark"><i class="bi bi-tag-fill text-primary me-2"></i><?= htmlspecialchars($cat['name']) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary rounded-pill px-3 py-2"><?= $cat['total_products'] ?> Products</span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-primary" title="Edit Category" data-bs-toggle="modal" data-bs-target="#modalEditCategory<?= $cat['id'] ?>">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <a href="categories.php?delete_category=<?= $cat['id'] ?>" onclick="return confirm('Are you sure you want to delete this category? All products in this category will also be deleted.')" class="btn btn-outline-danger" title="Delete Category">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php 
                                            }
                                        } 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- Edit Category Modals (Rendered outside table) -->
        <?php 
            if(mysqli_num_rows($categories) > 0){
                mysqli_data_seek($categories, 0);
                while($cat = mysqli_fetch_array($categories)){
        ?>
        <div class="modal fade" id="modalEditCategory<?= $cat['id'] ?>" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="" method="post">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Category</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Category Name</label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($cat['name']) ?>" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_category" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php 
                }
            }
        ?>

        <!-- Add Category Modal -->
        <div class="modal fade" id="modalAddCategory" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="" method="post">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Category</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Novel & Comics" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_category" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Category</button>
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
