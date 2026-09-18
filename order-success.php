<?php
include("connection.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ambil Informasi Kontak Admin untuk WhatsApp
$admin_query = mysqli_query($conn, "SELECT name, phone FROM users LIMIT 1");
$admin_data  = mysqli_fetch_assoc($admin_query);
$admin_phone = $admin_data['phone'] ?? '081234567890';
$wa_phone    = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $admin_phone));

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

// Ambil Item Pesanan
$items_query = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id = '$order_id'");
$order_items = [];
if ($items_query) {
    while ($it = mysqli_fetch_assoc($items_query)) {
        $order_items[] = $it;
    }
}

// Format Pesan WhatsApp Konfirmasi
$wa_msg = "Halo Admin WarungBuku, saya telah menyelesaikan pembayaran untuk pesanan berikut:\n\n"
        . "📄 *No Invoice:* " . $order['id'] . "\n"
        . "👤 *Nama:* " . $order['customer_name'] . "\n"
        . "📞 *No HP:* " . $order['customer_phone'] . "\n"
        . "💳 *Metode:* " . strtoupper($order['payment_method']) . " (" . ($order['payment_channel'] ?? '-') . ")\n"
        . "💰 *Total Pembayaran:* Rp " . number_format($order['total_amount'], 0, ',', '.') . "\n"
        . "📍 *Alamat:* " . $order['customer_address'] . "\n\n"
        . "Mohon pesanan saya segera diproses dan dikirim. Terima kasih!";
$wa_url = "https://wa.me/" . $wa_phone . "?text=" . urlencode($wa_msg);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="icon" type="image/x-icon" href="assets/book.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <title>Bukti Pembayaran <?= htmlspecialchars($order_id) ?> | WarungBuku</title>
    <style>
        :root {
            --brand-primary: #0B88D3;
        }
        .invoice-card {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 6px 25px rgba(0,0,0,0.06);
            border: 1px solid #e9ecef;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
            }
            .invoice-card {
                box-shadow: none !important;
                border: none !important;
            }
        }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <!-- Top Navigation (No Print) -->
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark fixed-top shadow-sm no-print">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
                <img src="assets/book.ico" alt="logo" width="34">
                <span>Warung<span style="color: #0B88D3;">Buku</span></span>
            </a>
            <div class="ms-auto d-flex align-items-center gap-2">
                <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="bi bi-house me-1"></i> Beranda
                </a>
            </div>
        </div>
    </nav>
    <div class="py-4 no-print"></div>

    <!-- Main Content -->
    <main class="py-4 flex-grow-1">
        <div class="container px-4 px-lg-5">
            
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    
                    <!-- Alert Status Sukses (No Print) -->
                    <div class="alert alert-success d-flex align-items-center mb-4 rounded-3 shadow-sm no-print" role="alert">
                        <i class="bi bi-check-circle-fill fs-2 me-3 text-success"></i>
                        <div>
                            <h5 class="fw-bold mb-0">Pembayaran Berhasil Diterima!</h5>
                            <small>Terima kasih atas pesanan Anda. Pesanan Anda akan segera kami proses dan dikirim.</small>
                        </div>
                    </div>

                    <!-- Nota Invoice Card -->
                    <div class="invoice-card p-4 p-md-5 mb-4">
                        
                        <!-- Invoice Header -->
                        <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4 flex-wrap gap-3">
                            <div>
                                <h3 class="fw-bold mb-1 d-flex align-items-center gap-2">
                                    <img src="assets/book.ico" alt="logo" width="32">
                                    <span>Warung<span style="color: #0B88D3;">Buku</span></span>
                                </h3>
                                <p class="text-muted small mb-0">Toko Buku Online Terpercaya &bull; www.warungbuku.local</p>
                            </div>
                            <div class="text-md-end">
                                <span class="badge bg-success fs-6 px-3 py-2 rounded-pill mb-1">
                                    <i class="bi bi-patch-check-fill me-1"></i> LUNAS (PAID)
                                </span>
                                <div class="fw-bold text-dark font-monospace"><?= htmlspecialchars($order['id']) ?></div>
                                <small class="text-muted"><?= date('d F Y, H:i', strtotime($order['created_at'])) ?> WIB</small>
                            </div>
                        </div>

                        <!-- Data Pembeli & Metode Pembayaran -->
                        <div class="row g-3 mb-4 pb-3 border-bottom">
                            <div class="col-md-6">
                                <h6 class="fw-bold text-dark small text-uppercase mb-2">Tujuan Pengiriman:</h6>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($order['customer_name']) ?></div>
                                <div class="small text-muted mb-1"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($order['customer_phone']) ?></div>
                                <?php if(!empty($order['customer_email'])): ?>
                                    <div class="small text-muted mb-1"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($order['customer_email']) ?></div>
                                <?php endif; ?>
                                <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= nl2br(htmlspecialchars($order['customer_address'])) ?></div>
                                <?php if(!empty($order['notes'])): ?>
                                    <div class="small text-muted mt-2 bg-light p-2 rounded"><em>Catatan: <?= htmlspecialchars($order['notes']) ?></em></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold text-dark small text-uppercase mb-2">Informasi Pembayaran:</h6>
                                <div class="d-flex justify-content-between small py-1">
                                    <span class="text-muted">Metode Pembayaran:</span>
                                    <span class="fw-bold text-dark"><?= strtoupper($order['payment_method']) ?> (<?= htmlspecialchars($order['payment_channel'] ?? 'Instant') ?>)</span>
                                </div>
                                <div class="d-flex justify-content-between small py-1">
                                    <span class="text-muted">Status Transaksi:</span>
                                    <span class="badge bg-success">Berhasil Terverifikasi</span>
                                </div>
                                <div class="d-flex justify-content-between small py-1">
                                    <span class="text-muted">Waktu Pembayaran:</span>
                                    <span><?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?> WIB</span>
                                </div>
                            </div>
                        </div>

                        <!-- Rincian Item Buku -->
                        <h6 class="fw-bold text-dark small text-uppercase mb-3">Rincian Buku yang Dipesan:</h6>
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th scope="col">No</th>
                                        <th scope="col">Judul Buku</th>
                                        <th scope="col" class="text-center" style="width: 120px;">Harga Satuan</th>
                                        <th scope="col" class="text-center" style="width: 80px;">Qty</th>
                                        <th scope="col" class="text-end" style="width: 140px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($order_items as $item): ?>
                                        <tr>
                                            <td class="text-center small"><?= $no++ ?></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($item['book_title']) ?></div>
                                            </td>
                                            <td class="text-center small">
                                                Rp <?= number_format($item['price'], 0, ',', '.') ?>
                                            </td>
                                            <td class="text-center small"><?= $item['quantity'] ?></td>
                                            <td class="text-end fw-bold">
                                                Rp <?= number_format($item['subtotal'], 0, ',', '.') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <?php 
                                        $subtotal_all = 0;
                                        foreach ($order_items as $item) {
                                            $subtotal_all += $item['subtotal'];
                                        }
                                        $discount_val = (float)($order['discount_amount'] ?? 0);
                                    ?>
                                    <?php if ($discount_val > 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-end text-muted small">Subtotal Produk:</td>
                                            <td class="text-end small fw-semibold">
                                                Rp <?= number_format($subtotal_all, 0, ',', '.') ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="text-end text-success small">
                                                Diskon Kupon (<?= htmlspecialchars($order['voucher_code'] ?? 'PROMO') ?>):
                                            </td>
                                            <td class="text-end text-success small fw-bold">
                                                - Rp <?= number_format($discount_val, 0, ',', '.') ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">Total Pembayaran:</td>
                                        <td class="text-end fw-bolder fs-5 text-primary" style="color: #0B88D3 !important;">
                                            Rp <?= number_format($order['total_amount'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Footer Invoice -->
                        <div class="text-center text-muted small pt-3 border-top">
                            Terima kasih telah berbelanja di <strong>WarungBuku</strong>. Simpan invoice ini sebagai bukti sah transaksi Anda.
                        </div>

                    </div>

                    <!-- Tombol Aksi (No Print) -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-5 no-print">
                        <a href="index.php" class="btn btn-outline-dark rounded-pill px-4">
                            <i class="bi bi-arrow-left me-1"></i> Belanja Buku Lagi
                        </a>
                        <div class="d-flex gap-2">
                            <button onclick="window.print()" class="btn btn-dark rounded-pill px-4">
                                <i class="bi bi-printer me-1"></i> Cetak Invoice
                            </button>
                            <a href="<?= $wa_url ?>" target="_blank" class="btn btn-success rounded-pill px-4 fw-semibold shadow-sm">
                                <i class="bi bi-whatsapp me-1"></i> Konfirmasi ke WA Admin
                            </a>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </main>

    <!-- Footer (No Print) -->
    <footer class="py-4 bg-dark text-white mt-auto no-print">
        <div class="container text-center text-white-50 small">
            <div>&copy; <?= date('Y') ?> <strong>WarungBuku</strong>. All Rights Reserved.</div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
