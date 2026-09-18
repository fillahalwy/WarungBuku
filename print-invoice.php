<?php
include("connection.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validasi Sesi: Hanya Admin atau Pemilik Pesanan yang boleh mencetak
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] != true) {
    header("Location: login.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? mysqli_real_escape_string($conn, trim($_GET['order_id'])) : '';

if (empty($order_id)) {
    echo "ID Transaksi / Invoice tidak ditemukan.";
    exit();
}

// Ambil data order
$order_res = mysqli_query($conn, "SELECT o.*, u.username, u.name as user_fullname 
                                  FROM orders o 
                                  LEFT JOIN users u ON o.user_id = u.id 
                                  WHERE o.id = '$order_id' LIMIT 1");
if (!$order_res || mysqli_num_rows($order_res) === 0) {
    echo "Data pesanan tidak ditemukan.";
    exit();
}
$order = mysqli_fetch_assoc($order_res);

// Cek hak akses: Jika bukan admin, pastikan ini order miliknya
if (($_SESSION['role'] ?? '') !== 'admin') {
    $current_user_id = $_SESSION['id'] ?? 0;
    $current_phone   = $_SESSION['global']->phone ?? '';
    if ($order['user_id'] != $current_user_id && $order['customer_phone'] !== $current_phone) {
        echo "Akses ditolak: Anda tidak memiliki izin untuk melihat invoice ini.";
        exit();
    }
}

// Ambil item pesanan
$items_res = mysqli_query($conn, "SELECT oi.*, b.image, b.category, b.author 
                                  FROM order_items oi 
                                  LEFT JOIN books b ON oi.book_id = b.id 
                                  WHERE oi.order_id = '$order_id'");
$order_items = [];
$total_qty = 0;
$subtotal_sum = 0;
if ($items_res) {
    while ($item = mysqli_fetch_assoc($items_res)) {
        $order_items[] = $item;
        $total_qty += (int)$item['quantity'];
        $subtotal_sum += (float)$item['subtotal'];
    }
}

$discount_val = (float)($order['discount_amount'] ?? 0);
$grand_total  = (float)($order['total_amount'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= htmlspecialchars($order['id']) ?> - WarungBuku</title>
    <link rel="icon" type="image/x-icon" href="assets/book.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8fafc;
            color: #1e293b;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 14px;
        }
        .invoice-box {
            max-width: 850px;
            margin: 30px auto;
            padding: 40px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            position: relative;
        }
        .invoice-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .brand-title {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #0f172a;
        }
        .brand-title span {
            color: #0B88D3;
        }
        .invoice-status-stamp {
            display: inline-block;
            padding: 6px 16px;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 6px;
        }
        .stamp-paid { background: #dcfce7; color: #15803d; border: 1.5px solid #22c55e; }
        .stamp-pending { background: #fef9c3; color: #a16207; border: 1.5px solid #eab308; }
        .stamp-processing { background: #e0f2fe; color: #0369a1; border: 1.5px solid #38bdf8; }
        .stamp-completed { background: #dbeafe; color: #1d4ed8; border: 1.5px solid #3b82f6; }
        .stamp-cancelled { background: #fee2e2; color: #b91c1c; border: 1.5px solid #ef4444; }

        .info-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            height: 100%;
        }
        .table-invoice thead th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            padding: 10px 14px;
        }
        .table-invoice tbody td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
        }
        @media print {
            body {
                background-color: #ffffff;
                color: #000000;
            }
            .invoice-box {
                margin: 0;
                padding: 20px;
                border: none;
                box-shadow: none;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Action Toolbar (Hidden during Print) -->
    <div class="container max-w-850 py-3 no-print" style="max-width: 850px;">
        <div class="d-flex justify-content-between align-items-center">
            <a href="javascript:window.history.back()" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-dark btn-sm rounded-pill px-4 fw-semibold shadow-sm">
                    <i class="bi bi-printer me-1"></i> Cetak Dokumen / PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Invoice Paper Box -->
    <div class="invoice-box">
        
        <!-- Header -->
        <div class="invoice-header d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="brand-title d-flex align-items-center gap-2 mb-1">
                    <img src="assets/book.ico" alt="Logo" width="36">
                    <span>Warung<span>Buku</span></span>
                </div>
                <div class="text-muted small">
                    Toko Buku Online Terlengkap, Terpercaya & Berkualitas<br>
                    Website: <strong>warungbuku.local</strong> &bull; CS WhatsApp: 0812-3456-7890
                </div>
            </div>
            <div class="text-end">
                <div class="fs-5 fw-bold text-dark font-monospace mb-1"><?= htmlspecialchars($order['id']) ?></div>
                <div class="text-muted small mb-2">Tanggal: <?= date('d F Y, H:i', strtotime($order['created_at'])) ?> WIB</div>
                <div>
                    <?php if ($order['status'] === 'paid'): ?>
                        <span class="invoice-status-stamp stamp-paid"><i class="bi bi-check2-circle me-1"></i>LUNAS / PAID</span>
                    <?php elseif ($order['status'] === 'processing'): ?>
                        <span class="invoice-status-stamp stamp-processing"><i class="bi bi-box-seam me-1"></i>DIPROSES</span>
                    <?php elseif ($order['status'] === 'completed'): ?>
                        <span class="invoice-status-stamp stamp-completed"><i class="bi bi-patch-check me-1"></i>SELESAI</span>
                    <?php elseif ($order['status'] === 'cancelled'): ?>
                        <span class="invoice-status-stamp stamp-cancelled"><i class="bi bi-x-circle me-1"></i>DIBATALKAN</span>
                    <?php else: ?>
                        <span class="invoice-status-stamp stamp-pending"><i class="bi bi-clock me-1"></i>MENUNGGU BAYAR</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Order Information Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6">
                <div class="info-card">
                    <div class="fw-bold text-dark text-uppercase small mb-2 border-bottom pb-1">
                        <i class="bi bi-person-fill text-primary me-1"></i> Penerima & Alamat Pengiriman
                    </div>
                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($order['customer_name']) ?></div>
                    <div class="text-muted small"><i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($order['customer_phone']) ?></div>
                    <?php if (!empty($order['customer_email'])): ?>
                        <div class="text-muted small"><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($order['customer_email']) ?></div>
                    <?php endif; ?>
                    <div class="mt-2 pt-2 border-top small text-dark">
                        <strong>Alamat:</strong><br>
                        <?= nl2br(htmlspecialchars($order['customer_address'])) ?>
                    </div>
                    <?php if (!empty($order['notes'])): ?>
                        <div class="mt-2 text-muted fst-italic small bg-white p-2 rounded border">
                            Catatan: <?= htmlspecialchars($order['notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-sm-6">
                <div class="info-card">
                    <div class="fw-bold text-dark text-uppercase small mb-2 border-bottom pb-1">
                        <i class="bi bi-credit-card-2-front-fill text-primary me-1"></i> Detail Pembayaran
                    </div>
                    <div class="d-flex justify-content-between small py-1">
                        <span class="text-muted">Metode Bayar:</span>
                        <span class="fw-bold text-dark"><?= strtoupper($order['payment_method']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between small py-1">
                        <span class="text-muted">Saluran / Bank:</span>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($order['payment_channel'] ?? 'QRIS Instant') ?></span>
                    </div>
                    <div class="d-flex justify-content-between small py-1">
                        <span class="text-muted">Total Kuantitas:</span>
                        <span class="fw-semibold text-dark"><?= $total_qty ?> Buku (<?= count($order_items) ?> Judul)</span>
                    </div>
                    <div class="d-flex justify-content-between small py-1">
                        <span class="text-muted">Update Terakhir:</span>
                        <span class="text-dark"><?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?> WIB</span>
                    </div>
                    <div class="mt-2 pt-2 border-top small text-muted">
                        <em>Invoice ini dikeluarkan otomatis oleh sistem WarungBuku sebagai bukti transaksi yang sah.</em>
                    </div>
                </div>
            </div>
        </div>

        <!-- Itemized Table -->
        <div class="table-responsive mb-4">
            <table class="table table-invoice table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 40px;">No</th>
                        <th>Judul & Deskripsi Buku</th>
                        <th class="text-center" style="width: 130px;">Harga Satuan</th>
                        <th class="text-center" style="width: 70px;">Qty</th>
                        <th class="text-end" style="width: 150px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($order_items as $item): ?>
                        <tr>
                            <td class="text-center text-muted"><?= $no++ ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($item['book_title']) ?></div>
                                <?php if (!empty($item['category'])): ?>
                                    <span class="badge bg-light text-muted border small"><?= htmlspecialchars($item['category']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">Rp <?= number_format($item['price'], 0, ',', '.') ?></td>
                            <td class="text-center fw-bold"><?= $item['quantity'] ?></td>
                            <td class="text-end fw-bold">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end fw-semibold text-muted">Subtotal Produk:</td>
                        <td class="text-end fw-semibold">Rp <?= number_format($subtotal_sum, 0, ',', '.') ?></td>
                    </tr>
                    <?php if ($discount_val > 0): ?>
                        <tr>
                            <td colspan="4" class="text-end text-success fw-semibold">
                                Potongan Voucher (<strong><?= htmlspecialchars($order['voucher_code'] ?? 'DISKON') ?></strong>):
                            </td>
                            <td class="text-end text-success fw-bold">- Rp <?= number_format($discount_val, 0, ',', '.') ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="table-light">
                        <td colspan="4" class="text-end fs-6 fw-bold text-dark">TOTAL PEMBAYARAN:</td>
                        <td class="text-end fs-5 fw-bold text-primary" style="color: #0B88D3 !important;">
                            Rp <?= number_format($grand_total, 0, ',', '.') ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Footer / Signature -->
        <div class="row pt-3 mt-4 border-top">
            <div class="col-8">
                <div class="small text-muted">
                    <strong>Syarat & Ketentuan Pengiriman:</strong>
                    <ul class="mb-0 ps-3 mt-1" style="font-size: 12px;">
                        <li>Pesanan yang sudah dibayar akan segera dikemas dan dikirimkan maksimal 1x24 jam kerja.</li>
                        <li>Pastikan buku diperiksa saat tiba. Komplain dilayani maksimal 2 hari setelah barang diterima disertai video unboxing.</li>
                    </ul>
                </div>
            </div>
            <div class="col-4 text-center">
                <div class="small text-muted mb-4">Hormat Kami,</div>
                <div class="fw-bold text-dark border-bottom pb-1 mx-auto" style="width: 140px;">WarungBuku Store</div>
                <div class="small text-muted">Bagian Administrasi</div>
            </div>
        </div>

    </div>

</body>
</html>
