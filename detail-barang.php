<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="icon" type="image/x-icon" href="assets/book.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <title>Detail Produk | WarungBuku</title>
</head>
<body>
    <!-- Public Header Nav -->
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark fixed-top">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand fw-bold" href="index.php"><img src="assets/book.ico" alt="logo" width="30"> Warung<span style="color: #0B88D3;">Buku</span></a>
            <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda</a>
        </div>
    </nav>
    <div class="py-4"></div>
    
    <!-- Product section-->
    <section class="py-5">
        <div class="container px-4 px-lg-5 my-5">
            <div class="row gx-4 gx-lg-5 align-items-center">
                <div class="col-md-6"><img class="card-img-top mb-5 mb-md-0 rounded shadow-sm" src="https://dummyimage.com/600x700/dee2e6/6c757d.jpg" alt="..." /></div>
                <div class="col-md-6">
                    <div class="small mb-1 text-muted">Kategori: Pendidikan & Sejarah</div>
                    <h1 class="display-5 fw-bolder">Judul Buku Sample</h1>
                    <div class="fs-5 mb-4 fw-bold text-success">
                        <span>Rp 75.000</span>
                    </div>
                    <p class="lead mb-4">Buku ini berisi pembahasan mendalam mengenai topik ilmu pengetahuan dan sejarah yang dirancang untuk pembaca umum maupun akademisi.</p>
                    <div class="d-flex gap-2">
                        <a href="https://wa.me/?text=Saya%20tertarik%20dengan%20buku%20ini" target="_blank" class="btn btn-success flex-shrink-0">
                            <i class="bi bi-whatsapp me-1"></i> Beli via WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer-->
    <footer class="py-4 bg-dark">
        <div class="container text-center text-white-50"><small>&copy; <?= date('Y') ?> WarungBuku. All Rights Reserved.</small></div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
