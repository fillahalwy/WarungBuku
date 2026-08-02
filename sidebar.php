<!-- Header / Top Bar -->
<nav class="navbar navbar-expand navbar-dark bg-dark fixed-top shadow-sm px-3 z-index-1030">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-dark text-white border-0 me-2" id="sidebarToggle" type="button" title="Toggle Sidebar">
            <i class="bi bi-list fs-4"></i>
        </button>
        <a class="navbar-brand fw-bold fs-4 d-flex align-items-center gap-2 m-0" href="dashboard.php">
            <img src="assets/book.ico" alt="logo" width="30"> Warung<span style="color: #0B88D3;">Buku</span>
        </a>
    </div>
    
    <div class="ms-auto d-flex align-items-center gap-3">
        <a href="index.php" target="_blank" class="btn btn-outline-light btn-sm rounded-pill d-none d-sm-inline-block">
            <i class="bi bi-globe me-1"></i> Lihat Toko
        </a>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-weight: bold;">
                    <?= strtoupper(substr($_SESSION['global']->name ?? 'A', 0, 1)) ?>
                </div>
                <span class="d-none d-md-inline fw-semibold"><?= htmlspecialchars($_SESSION['global']->name ?? 'Admin') ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow" aria-labelledby="dropdownUser">
                <li><a class="dropdown-item" href="profil.php"><i class="bi bi-person me-2"></i> Profil Saya</a></li>
                <li><a class="dropdown-item" href="index.php" target="_blank"><i class="bi bi-globe me-2"></i> Lihat Toko</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Sidebar Overlay (Backdrop for Mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Collapsible Sidebar -->
<div class="sidebar-wrapper bg-dark text-white" id="sidebarWrapper">
    <div class="sidebar-heading px-3 py-3 border-bottom border-secondary d-flex align-items-center justify-content-between">
        <span class="text-uppercase small fw-bold text-muted">Menu Navigasi</span>
        <button class="btn-close btn-close-white d-md-none" id="sidebarClose"></button>
    </div>
    
    <div class="list-group list-group-flush p-2">
        <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
        
        <!-- Main Section -->
        <small class="text-uppercase text-muted px-2 pt-2 pb-1 fw-bold fs-7" style="font-size: 11px;">UTAMA</small>
        <a href="dashboard.php" class="list-group-item list-group-item-action bg-transparent text-white rounded mb-1 <?= ($current_page == 'dashboard.php') ? 'active-sidebar' : '' ?>">
            <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
        
        <!-- Master Data Section -->
        <small class="text-uppercase text-muted px-2 pt-3 pb-1 fw-bold fs-7" style="font-size: 11px;">MASTER DATA</small>
        <a href="data-kategori.php" class="list-group-item list-group-item-action bg-transparent text-white rounded mb-1 <?= ($current_page == 'data-kategori.php') ? 'active-sidebar' : '' ?>">
            <i class="bi bi-tags me-2"></i> Data Kategori
        </a>

        <!-- Pengaturan & Lainnya -->
        <small class="text-uppercase text-muted px-2 pt-3 pb-1 fw-bold fs-7" style="font-size: 11px;">PENGATURAN</small>
        <a href="profil.php" class="list-group-item list-group-item-action bg-transparent text-white rounded mb-1 <?= ($current_page == 'profil.php') ? 'active-sidebar' : '' ?>">
            <i class="bi bi-person-circle me-2"></i> Profil Admin
        </a>
        <a href="index.php" target="_blank" class="list-group-item list-group-item-action bg-transparent text-white rounded mb-1">
            <i class="bi bi-globe me-2"></i> Lihat Toko Utama
        </a>
        
        <hr class="text-secondary my-2">
        <a href="logout.php" class="list-group-item list-group-item-action bg-transparent text-danger rounded">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>
    </div>
</div>

<style>
/* CSS Layout untuk Sidebar & Content Wrapper */
body {
    padding-top: 56px;
    transition: padding-left 0.3s ease;
}

.sidebar-wrapper {
    position: fixed;
    top: 56px;
    left: 0;
    width: 250px;
    height: calc(100vh - 56px);
    z-index: 1020;
    transition: all 0.3s ease;
    overflow-y: auto;
    border-right: 1px solid rgba(255,255,255,0.1);
}

.active-sidebar {
    background-color: #0B88D3 !important;
    color: #fff !important;
    font-weight: 600;
}

.list-group-item-action:hover:not(.active-sidebar) {
    background-color: rgba(255,255,255,0.1) !important;
    color: #fff !important;
}

/* State ketika Sidebar disembunyikan / collapsed pada Desktop */
body.sidebar-collapsed .sidebar-wrapper {
    left: -250px;
}

body:not(.sidebar-collapsed) {
    padding-left: 250px;
}

/* Mobile Responsive Adjustments */
@media (max-width: 767.98px) {
    body {
        padding-left: 0 !important;
    }
    .sidebar-wrapper {
        left: -250px;
    }
    body.sidebar-mobile-show .sidebar-wrapper {
        left: 0;
    }
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 56px;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0,0,0,0.5);
        z-index: 1015;
    }
    body.sidebar-mobile-show .sidebar-overlay {
        display: block;
    }
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const sidebarToggle = document.getElementById("sidebarToggle");
    const sidebarOverlay = document.getElementById("sidebarOverlay");
    const sidebarClose = document.getElementById("sidebarClose");

    // Toggle Sidebar State
    if(sidebarToggle) {
        sidebarToggle.addEventListener("click", function(e) {
            e.preventDefault();
            if (window.innerWidth < 768) {
                document.body.classList.toggle("sidebar-mobile-show");
            } else {
                document.body.classList.toggle("sidebar-collapsed");
            }
        });
    }

    if(sidebarOverlay) {
        sidebarOverlay.addEventListener("click", function() {
            document.body.classList.remove("sidebar-mobile-show");
        });
    }

    if(sidebarClose) {
        sidebarClose.addEventListener("click", function() {
            document.body.classList.remove("sidebar-mobile-show");
        });
    }
});
</script>
