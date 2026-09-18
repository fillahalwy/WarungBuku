<?php
include("connection.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$order_id = isset($_GET['order_id']) ? mysqli_real_escape_string($conn, trim($_GET['order_id'])) : '';

if (empty($order_id)) {
    header("Location: index.php");
    exit();
}

// Ambil Data Order
$order_query = mysqli_query($conn, "SELECT * FROM orders WHERE id = '$order_id'");
if (!$order_query || mysqli_num_rows($order_query) == 0) {
    header("Location: index.php");
    exit();
}

$order = mysqli_fetch_assoc($order_query);

// Jika sudah lunas, langsung arahkan ke halaman sukses
if ($order['status'] === 'paid' || $order['status'] === 'completed') {
    header("Location: order-success.php?order_id=" . urlencode($order_id));
    exit();
}

// Ambil Item Pesanan
$items_query = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id = '$order_id'");
$order_items = [];
if ($items_query) {
    while ($it = mysqli_fetch_assoc($items_query)) {
        $order_items[] = $it;
    }
}

// ----------------------------------------------------
// HANDLER SIMULASI PEMBAYARAN SUKSES
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simulate_payment'])) {
    
    // 1. Update status order menjadi 'paid'
    $update_order = mysqli_query($conn, "UPDATE orders SET status = 'paid', updated_at = NOW() WHERE id = '$order_id'");

    if ($update_order) {
        // 2. Kurangi stok buku secara otomatis di tabel books
        foreach ($order_items as $item) {
            $book_id = mysqli_real_escape_string($conn, $item['book_id']);
            $qty     = (int)$item['quantity'];
            mysqli_query($conn, "UPDATE books SET stock = GREATEST(0, stock - $qty) WHERE id = '$book_id'");
        }

        // 3. Jika menggunakan kupon/voucher, tambahkan count pemakaian voucher
        if (!empty($order['voucher_code'])) {
            $v_code = mysqli_real_escape_string($conn, $order['voucher_code']);
            mysqli_query($conn, "UPDATE vouchers SET used_count = used_count + 1 WHERE code = '$v_code'");
        }

        // 4. Kosongkan keranjang belanja
        $_SESSION['cart'] = [];

        // 5. Redirect ke halaman order success / nota
        header("Location: order-success.php?order_id=" . urlencode($order_id));
        exit();
    }
}

// Info Rekening Bank
$bank_info = [
    'BCA' => [
        'name' => 'Bank Central Asia (BCA)',
        'acc_number' => '123-456-7890',
        'acc_name' => 'WarungBuku Official'
    ],
    'Mandiri' => [
        'name' => 'Bank Mandiri',
        'acc_number' => '137-00-1234567-8',
        'acc_name' => 'WarungBuku Official'
    ],
    'BRI' => [
        'name' => 'Bank Rakyat Indonesia (BRI)',
        'acc_number' => '0123-01-001234-50-8',
        'acc_name' => 'WarungBuku Official'
    ]
];

$chosen_channel = $order['payment_channel'] ?? 'BCA';
$bank_data = $bank_info[$chosen_channel] ?? $bank_info['BCA'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="icon" type="image/x-icon" href="assets/book.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <title>Pembayaran Pesanan <?= htmlspecialchars($order_id) ?> | WarungBuku</title>
    <style>
        :root {
            --brand-primary: #0B88D3;
        }
        .payment-box {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e9ecef;
        }
        .qris-frame {
            background: #ffffff;
            border: 2px dashed #0B88D3;
            border-radius: 12px;
            padding: 20px;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .copy-badge {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .copy-badge:hover {
            opacity: 0.85;
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
            <div class="ms-auto text-white small">
                Status: <span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i>Menunggu Pembayaran</span>
            </div>
        </div>
    </nav>
    <div class="py-4"></div>

    <!-- Main Content -->
    <main class="py-4 flex-grow-1">
        <div class="container px-4 px-lg-5">
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    
                    <div class="payment-box p-4 p-md-5 mb-4">
                        
                        <!-- Header Status Pesanan -->
                        <div class="text-center pb-4 border-bottom mb-4">
                            <div class="text-warning fs-1 mb-2">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <h3 class="fw-bold text-dark mb-1">Menunggu Pembayaran</h3>
                            <p class="text-muted small mb-0">
                                Nomor Pesanan: <strong class="text-dark"><?= htmlspecialchars($order['id']) ?></strong> &bull; <?= date('d M Y, H:i', strtotime($order['created_at'])) ?>
                            </p>
                        </div>

                        <!-- Total Tagihan -->
                        <div class="bg-light p-3 rounded-3 text-center mb-4 border">
                            <div class="small text-muted mb-1 text-uppercase fw-semibold">Total Jumlah yang Harus Dibayar:</div>
                            <div class="fs-2 fw-bolder text-primary mb-1" style="color: #0B88D3 !important;">
                                Rp <?= number_format($order['total_amount'], 0, ',', '.') ?>
                            </div>
                            <small class="text-muted">Metode Pembayaran: <strong><?= strtoupper($order['payment_method']) ?> (<?= htmlspecialchars($order['payment_channel'] ?? 'QRIS') ?>)</strong></small>
                        </div>

                        <!-- KONDISI 1: METODE QRIS -->
                        <?php if ($order['payment_method'] === 'qris'): ?>
                            <div class="text-center mb-4">
                                <div class="qris-frame mb-3">
                                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2 pb-2 border-bottom">
                                        <span class="fw-bold text-danger fs-5" style="letter-spacing: 1px;">QRIS</span>
                                        <span class="badge bg-dark">INSTANT</span>
                                    </div>
                                    
                                    <!-- Dynamic Simulated QR Code via public API or SVG Mockup -->
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=WARUNGBUKU_<?= $order['id'] ?>_AMOUNT_<?= $order['total_amount'] ?>" alt="QRIS WarungBuku" class="img-fluid rounded mb-2" style="width: 200px; height: 200px;">
                                    
                                    <div class="small text-muted fw-semibold mt-1">NMID: ID1020260918001</div>
                                    <small class="text-muted" style="font-size: 0.72rem;">WarungBuku Store &bull; Valid Seluruh E-Wallet & Bank</small>
                                </div>

                                <div class="alert alert-info small text-start mx-auto" style="max-width: 480px;">
                                    <h6 class="fw-bold mb-1"><i class="bi bi-info-circle-fill me-1"></i>Cara Pembayaran QRIS:</h6>
                                    <ol class="mb-0 ps-3">
                                        <li>Buka aplikasi <strong>BCA Mobile, GoPay, OVO, Dana, ShopeePay, atau LinkAja</strong>.</li>
                                        <li>Pilih menu <strong>Scan / Bayar</strong>.</li>
                                        <li>Arahkan kamera ke QR Code di atas.</li>
                                        <li>Periksa nominal tagihan (<strong>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></strong>) dan konfirmasi pembayaran.</li>
                                    </ol>
                                </div>
                            </div>

                        <!-- KONDISI 2: METODE TRANSFER BANK -->
                        <?php else: ?>
                            <div class="mb-4">
                                <div class="card bg-white border-2 border-primary mb-3">
                                    <div class="card-body p-4 text-center">
                                        <div class="badge bg-primary px-3 py-2 rounded-pill mb-2"><?= $bank_data['name'] ?></div>
                                        <div class="small text-muted mb-1">Nomor Rekening Tujuan:</div>
                                        <div class="fs-3 fw-bold text-dark font-monospace mb-2" id="accNumber">
                                            <?= $bank_data['acc_number'] ?>
                                        </div>
                                        <div class="small text-muted mb-3">Atas Nama: <strong><?= $bank_data['acc_name'] ?></strong></div>
                                        <button class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="copyText('<?= $bank_data['acc_number'] ?>')">
                                            <i class="bi bi-clipboard me-1"></i> Salin Nomor Rekening
                                        </button>
                                    </div>
                                </div>

                                <div class="alert alert-info small">
                                    <h6 class="fw-bold mb-1"><i class="bi bi-info-circle-fill me-1"></i>Panduan Transfer Bank:</h6>
                                    <ul class="mb-0 ps-3">
                                        <li>Lakukan transfer sesuai nominal tepat <strong>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></strong>.</li>
                                        <li>Bisa melalui ATM, Mobile Banking (m-Banking), atau Internet Banking.</li>
                                        <li>Setelah transfer berhasil, klik tombol konfirmasi di bawah.</li>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Tombol Simulasi Pembayaran -->
                        <div class="p-3 bg-light rounded-3 text-center border">
                            <h6 class="fw-bold text-dark mb-1"><i class="bi bi-gear-fill me-1 text-primary"></i>Status Pembayaran</h6>
                            <p class="text-muted small mb-3">Silahkan klik tombol di bawah untuk mengkonfirmasi pembayaran.</p>
                            
                            <form action="" method="POST">
                                <button type="submit" name="simulate_payment" class="btn btn-success btn-lg rounded-pill px-5 fw-bold shadow-sm">
                                    <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi Pembayaran
                                </button>
                            </form>
                        </div>

                    </div>

                    <div class="text-center mb-5">
                        <a href="index.php" class="text-muted text-decoration-none small">
                            <i class="bi bi-arrow-left me-1"></i> Batalkan & Kembali ke Beranda
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="py-4 bg-dark text-white mt-auto">
        <div class="container text-center text-white-50 small">
            <div>&copy; <?= date('Y') ?> <strong>WarungBuku</strong>. All Rights Reserved.</div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyText(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert("Nomor rekening (" + text + ") berhasil disalin!");
            });
        }
    </script>
</body>
</html>
