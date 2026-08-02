<?php
include("koneksi.php");
session_start();
if(!isset($_SESSION['status_login']) || $_SESSION['status_login'] != true){
    echo "<script>window.location='login.php'</script>";
    exit();
}

$admin_id = $_SESSION['id'] ?? 1;
$query    = mysqli_query($conn, "SELECT * FROM admins WHERE id = '$admin_id'");
$admin    = mysqli_fetch_object($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="assets/book.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet"/>
    <link href="css/styles.css" rel="stylesheet" />
    <title>Admin Profile | WarungBuku Admin</title>
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
        .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background-color: #0B88D3;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 15px;
            box-shadow: 0 4px 10px rgba(11, 136, 211, 0.3);
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
                    <h1 class="h3 fw-bold mb-1"><i class="bi bi-person-badge me-2"></i>Account & Profile Settings</h1>
                    <p class="text-white-50 mb-0 small">Manage your administrator profile and account security.</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <section class="py-4">
        <div class="container-fluid px-4">
            <div class="row g-4">
                
                <!-- Profile Overview Card -->
                <div class="col-lg-4">
                    <div class="card card-custom p-4 text-center">
                        <div class="profile-avatar">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <h4 class="fw-bold mb-1"><?= htmlspecialchars($admin->name) ?></h4>
                        <span class="badge bg-primary rounded-pill px-3 py-2 align-self-center mb-3">Administrator</span>
                        
                        <hr class="my-3">
                        
                        <div class="text-start">
                            <div class="mb-2">
                                <small class="text-muted d-block"><i class="bi bi-person me-1"></i> Username</small>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($admin->username) ?></span>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i> Phone Number</small>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($admin->phone ?? '-') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Forms -->
                <div class="col-lg-8">
                    
                    <!-- Edit Profile Card -->
                    <div class="card card-custom mb-4">
                        <div class="card-header card-header-custom bg-dark text-white py-3 px-4">
                            <div class="fw-bold fs-5 mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Profile Information</div>
                        </div>
                        <div class="card-body p-4">
                            
                            <?php
                                if(isset($_POST['update_profile'])){
                                    $name     = mysqli_real_escape_string($conn, trim($_POST['name']));
                                    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
                                    $phone    = mysqli_real_escape_string($conn, trim($_POST['phone']));

                                    $update = mysqli_query($conn, "UPDATE admins SET name='$name', username='$username', phone='$phone' WHERE id='$admin->id'");

                                    if($update){
                                        // Sync session
                                        if(isset($_SESSION['global'])){
                                            $_SESSION['global']->name     = $name;
                                            $_SESSION['global']->username = $username;
                                            $_SESSION['global']->phone    = $phone;
                                        }

                                        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                                <i class="bi bi-check-circle-fill me-2"></i>Profile updated successfully.
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                              </div>';
                                        echo '<script>setTimeout(function(){ window.location="profil.php"; }, 1500);</script>';
                                    } else {
                                        echo '<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i>Failed to update profile: ' . mysqli_error($conn) . '</div>';
                                    }
                                }
                            ?>

                            <form action="" method="post">
                                <div class="row mb-3 align-items-center">
                                    <label class="col-md-3 col-form-label fw-semibold">Full Name</label>
                                    <div class="col-md-9">
                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($admin->name) ?>" required>
                                    </div>
                                </div>
                                <div class="row mb-3 align-items-center">
                                    <label class="col-md-3 col-form-label fw-semibold">Username</label>
                                    <div class="col-md-9">
                                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($admin->username) ?>" required>
                                    </div>
                                </div>
                                <div class="row mb-4 align-items-center">
                                    <label class="col-md-3 col-form-label fw-semibold">Phone Number</label>
                                    <div class="col-md-9">
                                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($admin->phone) ?>" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3"></div>
                                    <div class="col-md-9">
                                        <button type="submit" name="update_profile" class="btn btn-primary px-4 fw-semibold shadow-sm">
                                            <i class="bi bi-save me-1"></i> Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password Card -->
                    <div class="card card-custom">
                        <div class="card-header card-header-custom bg-dark text-white py-3 px-4">
                            <div class="fw-bold fs-5 mb-0"><i class="bi bi-shield-lock me-2"></i>Change Password</div>
                        </div>
                        <div class="card-body p-4">
                            
                            <?php 
                                if(isset($_POST['update_password'])){
                                    $current_password  = $_POST['current_password'];
                                    $new_password      = $_POST['new_password'];
                                    $confirm_password  = $_POST['confirm_password'];

                                    $check = mysqli_query($conn, "SELECT * FROM admins WHERE id = '$admin->id' AND password = '" . MD5($current_password) . "'");
                                    if(mysqli_num_rows($check) > 0){
                                        if(empty($new_password)){
                                            echo '<div class="alert alert-warning" role="alert"><i class="bi bi-exclamation-circle me-2"></i>New password cannot be empty.</div>';
                                        } else if($confirm_password != $new_password){
                                            echo '<div class="alert alert-warning" role="alert"><i class="bi bi-exclamation-circle me-2"></i>New password confirmation does not match.</div>';
                                        } else {
                                            $update_pw = mysqli_query($conn, "UPDATE admins SET password='" . MD5($new_password) . "' WHERE id='$admin->id'");
                                            if($update_pw){
                                                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                                        <i class="bi bi-check-circle-fill me-2"></i>Password updated successfully!
                                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                      </div>';
                                            } else {
                                                echo '<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i>Failed to update password.</div>';
                                            }
                                        }
                                    } else {
                                        echo '<div class="alert alert-danger" role="alert"><i class="bi bi-shield-x me-2"></i>The current password you entered is incorrect.</div>';
                                    }
                                }
                            ?>

                            <form action="" method="post">
                                <div class="row mb-3 align-items-center">
                                    <label class="col-md-4 col-form-label fw-semibold">Current Password</label>
                                    <div class="col-md-8">
                                        <input type="password" name="current_password" class="form-control" placeholder="Enter your current password" required>
                                    </div>
                                </div>
                                <div class="row mb-3 align-items-center">
                                    <label class="col-md-4 col-form-label fw-semibold">New Password</label>
                                    <div class="col-md-8">
                                        <input type="password" name="new_password" class="form-control" placeholder="Enter a new password" required>
                                    </div>
                                </div>
                                <div class="row mb-4 align-items-center">
                                    <label class="col-md-4 col-form-label fw-semibold">Confirm New Password</label>
                                    <div class="col-md-8">
                                        <input type="password" name="confirm_password" class="form-control" placeholder="Repeat the new password" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4"></div>
                                    <div class="col-md-8">
                                        <button type="submit" name="update_password" class="btn btn-warning text-dark px-4 fw-semibold shadow-sm">
                                            <i class="bi bi-key me-1"></i> Change Password
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- Footer-->
    <footer class="py-4 bg-dark mt-auto">
        <div class="container text-center text-white-50">
            <small>&copy; <?= date('Y') ?> WarungBuku Admin Panel. All Rights Reserved.</small>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>