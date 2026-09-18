<?php
include("connection.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validasi Keranjang Belanja
if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit();
}

// Data pengguna yang sedang login (jika ada)
$logged_user = $_SESSION['global'] ?? null;
$user_id = $_SESSION['id'] ?? null;

// ----------------------------------------------------
// AMBIL DATA ITEM DARI KERANJANG
// ----------------------------------------------------
$cart_items = [];
$total_price = 0;
$total_items_count = 0;

$ids = array_map(function($id) use ($conn) {
    return "'" . mysqli_real_escape_string($conn, $id) . "'";
}, array_keys($_SESSION['cart']));

$ids_str = implode(',', $ids);
$query_books = mysqli_query($conn, "SELECT * FROM books WHERE id IN ($ids_str)");

if ($query_books) {
    while ($b = mysqli_fetch_assoc($query_books)) {
        $b_id = $b['id'];
        $qty = $_SESSION['cart'][$b_id] ?? 1;

        if ($qty > $b['stock']) {
            $qty = (int)$b['stock'];
            $_SESSION['cart'][$b_id] = $qty;
        }

        if ($qty > 0) {
            $subtotal = $b['price'] * $qty;
            $total_price += $subtotal;
            $total_items_count += $qty;

            $b['qty'] = $qty;
            $b['subtotal'] = $subtotal;
            $cart_items[] = $b;
        }
    }
}

if (empty($cart_items)) {
    $_SESSION['cart'] = [];
    header("Location: cart.php");
    exit();
}

// ----------------------------------------------------
// AMBIL SEMUA VOUCHER DARI DATABASE UNTUK SUGGESTION
// ----------------------------------------------------
$all_vouchers_query = mysqli_query($conn, "SELECT * FROM vouchers ORDER BY is_active DESC, expiry_date ASC, id DESC");
$available_vouchers = [];
$today_date = date('Y-m-d');

if ($all_vouchers_query) {
    while ($v = mysqli_fetch_assoc($all_vouchers_query)) {
        $is_disabled = false;
        $disabled_reason = '';
        $badge_class = 'bg-secondary';

        if ($v['is_active'] != 1) {
            $is_disabled = true;
            $disabled_reason = 'Voucher Non-Aktif';
            $badge_class = 'bg-secondary';
        } elseif (!empty($v['expiry_date']) && $v['expiry_date'] < $today_date) {
            $is_disabled = true;
            $disabled_reason = 'Kedaluwarsa (' . date('d/m/Y', strtotime($v['expiry_date'])) . ')';
            $badge_class = 'bg-danger';
        } elseif ((int)$v['used_count'] >= (int)$v['quota']) {
            $is_disabled = true;
            $disabled_reason = 'Kuota Pemakaian Habis';
            $badge_class = 'bg-danger';
        } elseif ($total_price < (float)$v['min_spend']) {
            $is_disabled = true;
            $kurang = (float)$v['min_spend'] - $total_price;
            $disabled_reason = 'Min. belanja Rp ' . number_format($v['min_spend'], 0, ',', '.') . ' (Kurang Rp ' . number_format($kurang, 0, ',', '.') . ')';
            $badge_class = 'bg-warning text-dark';
        }

        $v['is_disabled'] = $is_disabled;
        $v['disabled_reason'] = $disabled_reason;
        $v['badge_class'] = $badge_class;
        $available_vouchers[] = $v;
    }
}

// ----------------------------------------------------
// HANDLER VOUCHER & DISKON PROMO
// ----------------------------------------------------
$voucher_msg_success = '';
$voucher_msg_error = '';

// Terapkan Voucher
if (isset($_POST['apply_voucher'])) {
    $v_code = !empty($_POST['selected_voucher_code']) 
        ? strtoupper(mysqli_real_escape_string($conn, trim($_POST['selected_voucher_code'])))
        : strtoupper(mysqli_real_escape_string($conn, trim($_POST['voucher_code'] ?? '')));

    if (empty($v_code)) {
        $voucher_msg_error = "Harap masukkan atau pilih kode voucher terlebih dahulu.";
    } else {
        $v_query = mysqli_query($conn, "SELECT * FROM vouchers WHERE code = '$v_code' AND is_active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE())");
        if ($v_query && mysqli_num_rows($v_query) > 0) {
            $voucher_row = mysqli_fetch_assoc($v_query);
            if ($voucher_row['used_count'] >= $voucher_row['quota']) {
                $voucher_msg_error = "Maaf, kuota pemakaian voucher <strong>" . htmlspecialchars($v_code) . "</strong> telah habis.";
            } elseif ($total_price < (float)$voucher_row['min_spend']) {
                $kurang = (float)$voucher_row['min_spend'] - $total_price;
                $voucher_msg_error = "Minimal total belanja untuk voucher ini adalah <strong>Rp " . number_format($voucher_row['min_spend'], 0, ',', '.') . "</strong> (kurang Rp " . number_format($kurang, 0, ',', '.') . ").";
            } else {
                $_SESSION['applied_voucher'] = $voucher_row;
                $voucher_msg_success = "Voucher <strong>" . htmlspecialchars($voucher_row['code']) . "</strong> (" . htmlspecialchars($voucher_row['name']) . ") berhasil diterapkan!";
            }
        } else {
            $voucher_msg_error = "Kode voucher <strong>" . htmlspecialchars($v_code) . "</strong> tidak valid atau sudah kedaluwarsa.";
        }
    }
}

// Batalkan / Hapus Voucher
if (isset($_POST['remove_voucher'])) {
    unset($_SESSION['applied_voucher']);
    $voucher_msg_success = "Voucher telah dibatalkan.";
}

// Hitung Besaran Diskon Voucher
$applied_voucher = $_SESSION['applied_voucher'] ?? null;
$discount_amount = 0;

if ($applied_voucher) {
    // Verifikasi ulang syarat min_spend
    if ($total_price >= (float)$applied_voucher['min_spend']) {
        if ($applied_voucher['discount_type'] === 'percent') {
            $calc = ((float)$applied_voucher['discount_value'] / 100) * $total_price;
            $max_d = (float)$applied_voucher['max_discount'];
            $discount_amount = ($max_d > 0) ? min($calc, $max_d) : $calc;
        } else {
            $discount_amount = min((float)$applied_voucher['discount_value'], $total_price);
        }
    } else {
        // Jika total belanja turun di bawah min_spend, batalkan voucher
        unset($_SESSION['applied_voucher']);
        $applied_voucher = null;
    }
}

$final_total = max(0, $total_price - $discount_amount);

// ----------------------------------------------------
// PROSES SUBMIT CHECKOUT / BUAT PESANAN
// ----------------------------------------------------
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $customer_name    = mysqli_real_escape_string($conn, trim($_POST['customer_name'] ?? ''));
    $customer_phone   = mysqli_real_escape_string($conn, trim($_POST['customer_phone'] ?? ''));
    $customer_email   = mysqli_real_escape_string($conn, trim($_POST['customer_email'] ?? ''));
    $customer_address = mysqli_real_escape_string($conn, trim($_POST['customer_address'] ?? ''));
    $payment_method   = mysqli_real_escape_string($conn, trim($_POST['payment_method'] ?? 'transfer'));
    $payment_channel  = mysqli_real_escape_string($conn, trim($_POST['payment_channel'] ?? 'BCA'));
    $notes            = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));

    if (empty($customer_name)) {
        $errors[] = "Nama lengkap penerima wajib diisi.";
    }
    if (empty($customer_phone)) {
        $errors[] = "Nomor telepon / WhatsApp wajib diisi.";
    }
    if (empty($customer_address)) {
        $errors[] = "Alamat pengiriman lengkap wajib diisi.";
    }

    if (empty($errors)) {
        // Generate Order ID / Nomor Invoice
        $order_id = 'INV-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 5));

        $sql_user_id = $user_id ? "'$user_id'" : "NULL";
        $sql_voucher = $applied_voucher ? "'" . mysqli_real_escape_string($conn, $applied_voucher['code']) . "'" : "NULL";
        $sql_disc    = (float)$discount_amount;
        $sql_total   = (float)$final_total;

        // Simpan ke tabel orders
        $insert_order = mysqli_query($conn, "INSERT INTO orders (id, user_id, voucher_code, discount_amount, customer_name, customer_phone, customer_email, customer_address, payment_method, payment_channel, total_amount, status, notes, created_at, updated_at) 
                                              VALUES ('$order_id', $sql_user_id, $sql_voucher, '$sql_disc', '$customer_name', '$customer_phone', '$customer_email', '$customer_address', '$payment_method', '$payment_channel', '$sql_total', 'pending', '$notes', NOW(), NOW())");

        if ($insert_order) {
            // Simpan setiap item ke order_items
            foreach ($cart_items as $item) {
                $b_id    = $item['id'];
                $b_title = mysqli_real_escape_string($conn, $item['title']);
                $b_price = $item['price'];
                $b_qty   = $item['qty'];
                $b_sub   = $item['subtotal'];

                mysqli_query($conn, "INSERT INTO order_items (order_id, book_id, book_title, price, quantity, subtotal) 
                                     VALUES ('$order_id', '$b_id', '$b_title', '$b_price', '$b_qty', '$b_sub')");
            }

            // Bersihkan voucher yang diaplikasikan dari session
            unset($_SESSION['applied_voucher']);

            // Arahkan ke halaman simulasi pembayaran
            header("Location: payment.php?order_id=" . urlencode($order_id));
            exit();
        } else {
            $errors[] = "Gagal membuat pesanan: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="icon" type="image/x-icon" href="assets/book.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <title>Checkout Pesanan | WarungBuku</title>
    <style>
        :root {
            --brand-primary: #0B88D3;
        }
        .checkout-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        .payment-option-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .payment-option-card:hover {
            border-color: var(--brand-primary);
        }
        .payment-option-card.active {
            border-color: var(--brand-primary);
            background-color: rgba(11, 136, 211, 0.04);
        }
        .item-thumb {
            width: 45px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }

        /* Voucher Suggestion Styles */
        .voucher-suggest-card {
            border: 1px dashed #0B88D3;
            border-radius: 10px;
            background: #f8fafc;
            transition: all 0.2s ease;
        }
        .voucher-suggest-card:hover:not(.disabled-voucher) {
            background: #f0f9ff;
            border-color: #0284c7;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 136, 211, 0.12);
        }
        .voucher-suggest-card.disabled-voucher {
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
            opacity: 0.68;
            cursor: not-allowed;
        }
        .voucher-tag {
            background: #0B88D3;
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 3px 8px;
            border-radius: 4px;
        }
        .voucher-suggest-card.disabled-voucher .voucher-tag {
            background: #94a3b8;
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
            <div class="ms-auto d-flex align-items-center gap-2">
                <a href="cart.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="bi bi-cart me-1"></i> Kembali ke Keranjang
                </a>
            </div>
        </div>
    </nav>
    <div class="py-4"></div>

    <!-- Main Checkout Content -->
    <main class="py-4 flex-grow-1">
        <div class="container px-4 px-lg-5">

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Beranda</a></li>
                    <li class="breadcrumb-item"><a href="cart.php" class="text-decoration-none text-muted">Keranjang</a></li>
                    <li class="breadcrumb-item active text-dark fw-bold" aria-current="page">Checkout</li>
                </ol>
            </nav>

            <h2 class="h4 fw-bold mb-4 text-dark">
                <i class="bi bi-credit-card text-primary me-2"></i>Checkout & Pembayaran
            </h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Mohon lengkapi formulir:</h6>
                    <ul class="mb-0 small ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= $err ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if(!empty($voucher_msg_success)): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= $voucher_msg_success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if(!empty($voucher_msg_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $voucher_msg_error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="checkout.php" method="POST" id="checkoutForm">
                <div class="row g-4">
                    
                    <!-- Kolom Formulir Pengiriman & Pembayaran -->
                    <div class="col-lg-7">
                        
                        <!-- 1. Data Penerima -->
                        <div class="checkout-card p-4 mb-4">
                            <h5 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                                <span class="badge bg-dark rounded-circle" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px;">1</span>
                                Informasi Pengiriman
                            </h5>

                            <?php if ($logged_user): ?>
                                <div class="alert alert-light border small mb-3 py-2 d-flex align-items-center justify-content-between">
                                    <span><i class="bi bi-person-check text-success me-1"></i> Masuk sebagai <strong><?= htmlspecialchars($logged_user->name) ?></strong></span>
                                    <a href="customer-profile.php" class="text-primary text-decoration-none small">Ubah di Profil</a>
                                </div>
                            <?php endif; ?>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Nama Lengkap Penerima <span class="text-danger">*</span></label>
                                    <input type="text" name="customer_name" class="form-control" placeholder="Contoh: Budi Santoso" value="<?= htmlspecialchars($_POST['customer_name'] ?? ($logged_user->name ?? '')) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Nomor WhatsApp / HP <span class="text-danger">*</span></label>
                                    <input type="tel" name="customer_phone" class="form-control" placeholder="Contoh: 081234567890" value="<?= htmlspecialchars($_POST['customer_phone'] ?? ($logged_user->phone ?? '')) ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Alamat Email (Opsional)</label>
                                    <input type="email" name="customer_email" class="form-control" placeholder="Contoh: budi@gmail.com" value="<?= htmlspecialchars($_POST['customer_email'] ?? ($logged_user->email ?? '')) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Alamat Pengiriman Lengkap <span class="text-danger">*</span></label>
                                    <textarea name="customer_address" class="form-control" rows="3" placeholder="Nama Jalan, Nomor Rumah, RT/RW, Kelurahan, Kecamatan, Kota/Kabupaten, Kode Pos" required><?= htmlspecialchars($_POST['customer_address'] ?? ($logged_user->address ?? '')) ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Catatan Pesanan (Opsional)</label>
                                    <input type="text" name="notes" class="form-control" placeholder="Contoh: Titipkan di satpam, bungkus kado, dll." value="<?= htmlspecialchars($_POST['notes'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- 2. Pilihan Metode Pembayaran -->
                        <div class="checkout-card p-4 mb-4">
                            <h5 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                                <span class="badge bg-dark rounded-circle" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px;">2</span>
                                Metode Pembayaran
                            </h5>

                            <div class="row g-3">
                                
                                <!-- Opsi 1: QRIS -->
                                <div class="col-md-6">
                                    <label class="payment-option-card w-100 d-flex align-items-start gap-3 active" id="card_qris">
                                        <input type="radio" name="payment_method" value="qris" class="form-check-input mt-1" checked onchange="togglePaymentOptions('qris')">
                                        <div>
                                            <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                                <i class="bi bi-qr-code-scan text-primary fs-5"></i> QRIS Instan
                                            </div>
                                            <small class="text-muted d-block mt-1">BCA, GoPay, OVO, Dana, ShopeePay, LinkAja</small>
                                        </div>
                                    </label>
                                </div>

                                <!-- Opsi 2: Transfer Bank -->
                                <div class="col-md-6">
                                    <label class="payment-option-card w-100 d-flex align-items-start gap-3" id="card_transfer">
                                        <input type="radio" name="payment_method" value="transfer" class="form-check-input mt-1" onchange="togglePaymentOptions('transfer')">
                                        <div>
                                            <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                                <i class="bi bi-bank text-primary fs-5"></i> Transfer Bank
                                            </div>
                                            <small class="text-muted d-block mt-1">BCA, Bank Mandiri, BRI (Verifikasi Cepat)</small>
                                        </div>
                                    </label>
                                </div>

                            </div>

                            <!-- Pilihan Bank Khusus Transfer -->
                            <div class="mt-3 p-3 bg-light rounded-3 d-none" id="bank_select_box">
                                <label class="form-label small fw-semibold">Pilih Bank Tujuan:</label>
                                <select name="payment_channel" class="form-select form-select-sm">
                                    <option value="BCA">Bank Central Asia (BCA) - 123-456-7890</option>
                                    <option value="Mandiri">Bank Mandiri - 137-00-1234567-8</option>
                                    <option value="BRI">Bank BRI - 0123-01-001234-50-8</option>
                                </select>
                            </div>

                        </div>

                    </div>

                    <!-- Kolom Ringkasan Rincian Pesanan -->
                    <div class="col-lg-5">
                        
                        <!-- Form Voucher & Diskon -->
                        <div class="checkout-card p-4 mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-ticket-perforated text-primary me-2"></i>Kupon & Voucher Diskon</h6>
                                <?php 
                                    $active_vouchers_count = count(array_filter($available_vouchers, function($item) { return !$item['is_disabled']; }));
                                ?>
                                <span class="badge bg-light text-primary border border-primary small"><?= $active_vouchers_count ?> Kupon Tersedia</span>
                            </div>
                            
                            <?php if ($applied_voucher): ?>
                                <div class="p-3 bg-success bg-opacity-10 border border-success rounded-3 d-flex align-items-center justify-content-between mb-2">
                                    <div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-success font-monospace"><?= htmlspecialchars($applied_voucher['code']) ?></span>
                                            <span class="fw-bold text-success small"><?= htmlspecialchars($applied_voucher['name']) ?></span>
                                        </div>
                                        <small class="text-muted d-block mt-1">Potongan diskon sebesar <strong>Rp <?= number_format($discount_amount, 0, ',', '.') ?></strong> telah berhasil diterapkan pada pesanan ini.</small>
                                    </div>
                                    <button type="submit" name="remove_voucher" class="btn btn-outline-danger btn-sm rounded-pill px-3 ms-2 flex-shrink-0">
                                        <i class="bi bi-x"></i> Batalkan
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="input-group mb-3">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-ticket-detailed"></i></span>
                                    <input type="text" id="inputVoucherCode" name="voucher_code" class="form-control text-uppercase font-monospace" placeholder="Ketik atau pilih kode voucher...">
                                    <button type="submit" name="apply_voucher" id="btnApplyVoucher" class="btn btn-dark px-3 fw-semibold">
                                        Terapkan
                                    </button>
                                </div>

                                <!-- Suggestion List Vouchers -->
                                <?php if (!empty($available_vouchers)): ?>
                                    <div class="voucher-suggestion-section mt-3 pt-3 border-top">
                                        <div class="small fw-bold text-muted mb-2 d-flex align-items-center justify-content-between">
                                            <span><i class="bi bi-stars text-warning me-1"></i>Pilihan Voucher Promo:</span>
                                            <span class="text-muted" style="font-size: 0.72rem;">Klik "Gunakan" untuk pakai</span>
                                        </div>
                                        
                                        <div class="d-flex flex-column gap-2" style="max-height: 290px; overflow-y: auto; padding-right: 4px;">
                                            <?php foreach ($available_vouchers as $vc): ?>
                                                <div class="voucher-suggest-card p-3 <?= $vc['is_disabled'] ? 'disabled-voucher' : '' ?>">
                                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                                        <div class="flex-grow-1">
                                                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                                                <span class="voucher-tag font-monospace"><?= htmlspecialchars($vc['code']) ?></span>
                                                                <span class="fw-bold text-dark small"><?= htmlspecialchars($vc['name']) ?></span>
                                                            </div>
                                                            <div class="text-muted" style="font-size: 0.78rem; line-height: 1.4;">
                                                                <?php if ($vc['discount_type'] === 'percent'): ?>
                                                                    <span>Diskon <strong><?= (float)$vc['discount_value'] ?>%</strong><?php if((float)$vc['max_discount'] > 0): ?> (Maks. Rp <?= number_format($vc['max_discount'], 0, ',', '.') ?>)<?php endif; ?></span>
                                                                <?php else: ?>
                                                                    <span>Potongan Langsung <strong>Rp <?= number_format($vc['discount_value'], 0, ',', '.') ?></strong></span>
                                                                <?php endif; ?>
                                                                &bull; Min. Belanja Rp <?= number_format($vc['min_spend'], 0, ',', '.') ?>
                                                            </div>
                                                            
                                                            <?php if ($vc['is_disabled']): ?>
                                                                <div class="mt-1">
                                                                    <span class="badge <?= $vc['badge_class'] ?>" style="font-size: 0.7rem; font-weight: normal;">
                                                                        <i class="bi bi-exclamation-circle me-1"></i><?= $vc['disabled_reason'] ?>
                                                                    </span>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="text-muted mt-1" style="font-size: 0.72rem;">
                                                                    <i class="bi bi-clock-history me-1"></i>Berlaku s.d. <?= !empty($vc['expiry_date']) ? date('d M Y', strtotime($vc['expiry_date'])) : 'Selamanya' ?>
                                                                    &bull; Sisa kuota: <?= (int)$vc['quota'] - (int)$vc['used_count'] ?>x
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="flex-shrink-0 text-end">
                                                            <?php if ($vc['is_disabled']): ?>
                                                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 disabled" disabled style="font-size: 0.75rem;">
                                                                    Tidak Tersedia
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold" onclick="applySuggestedVoucher('<?= htmlspecialchars($vc['code']) ?>')" style="font-size: 0.75rem;">
                                                                    <i class="bi bi-check2 me-1"></i> Gunakan
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Ringkasan Belanja -->
                        <div class="checkout-card p-4 sticky-top" style="top: 80px;">
                            <h5 class="fw-bold mb-3 text-dark pb-2 border-bottom">Rincian Belanja (<?= $total_items_count ?> Item)</h5>

                            <div class="mb-3" style="max-height: 240px; overflow-y: auto;">
                                <?php foreach ($cart_items as $item): ?>
                                    <?php
                                        $has_img = (!empty($item['image']) && file_exists('assets/images/book/' . $item['image']));
                                    ?>
                                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($has_img): ?>
                                                <img src="assets/images/book/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="item-thumb">
                                            <?php else: ?>
                                                <div class="item-thumb bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center text-muted">
                                                    <i class="bi bi-book"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-0 text-dark small fw-bold" style="max-width: 180px; text-overflow: ellipsis; white-space: nowrap; overflow: hidden;">
                                                    <?= htmlspecialchars($item['title']) ?>
                                                </h6>
                                                <small class="text-muted"><?= $item['qty'] ?> x Rp <?= number_format($item['price'], 0, ',', '.') ?></small>
                                            </div>
                                        </div>
                                        <span class="fw-semibold small">
                                            Rp <?= number_format($item['subtotal'], 0, ',', '.') ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-flex justify-content-between mb-2 text-muted small">
                                <span>Subtotal Produk:</span>
                                <span class="fw-semibold text-dark">Rp <?= number_format($total_price, 0, ',', '.') ?></span>
                            </div>
                            
                            <?php if ($discount_amount > 0): ?>
                                <div class="d-flex justify-content-between mb-2 small text-success">
                                    <span>Diskon Kupon (<?= htmlspecialchars($applied_voucher['code']) ?>):</span>
                                    <span class="fw-bold">- Rp <?= number_format($discount_amount, 0, ',', '.') ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between mb-3 text-muted small">
                                <span>Biaya Pengiriman:</span>
                                <span class="text-success fw-bold">GRATIS</span>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <span class="fw-bold text-dark fs-5">Total Tagihan:</span>
                                <span class="fs-4 fw-bold" style="color: #0B88D3;">
                                    Rp <?= number_format($final_total, 0, ',', '.') ?>
                                </span>
                            </div>

                            <button type="submit" name="place_order" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold shadow-sm" style="background-color: #0B88D3; border: none;">
                                <i class="bi bi-lock-fill me-1"></i> Buat Pesanan & Bayar
                            </button>

                            <small class="d-block text-center text-muted mt-3" style="font-size: 0.75rem;">
                                Dengan mengklik tombol di atas, Anda menyetujui proses transaksi belanja di WarungBuku.
                            </small>
                        </div>
                    </div>

                </div>
            </form>

        </div>
    </main>

    <!-- Footer -->
    <footer class="py-4 bg-dark text-white mt-auto">
        <div class="container d-flex flex-wrap justify-content-between align-items-center text-white-50 small gap-2">
            <div>&copy; <?= date('Y') ?> <strong>WarungBuku</strong>. All Rights Reserved.</div>
            <?php if(isset($_SESSION['status_login']) && $_SESSION['status_login'] == true && ($_SESSION['role'] ?? '') === 'admin'): ?>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3" style="font-size: 0.78rem;">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard Admin
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePaymentOptions(type) {
            const cardQris = document.getElementById('card_qris');
            const cardTransfer = document.getElementById('card_transfer');
            const bankBox = document.getElementById('bank_select_box');

            if (type === 'qris') {
                cardQris.classList.add('active');
                cardTransfer.classList.remove('active');
                bankBox.classList.add('d-none');
            } else {
                cardTransfer.classList.add('active');
                cardQris.classList.remove('active');
                bankBox.classList.remove('d-none');
            }
        }

        function applySuggestedVoucher(code) {
            const input = document.getElementById('inputVoucherCode');
            if (input) {
                input.value = code;
                const btnApply = document.getElementById('btnApplyVoucher');
                if (btnApply) {
                    btnApply.click();
                }
            }
        }

        // Live search filter in suggestions when user types
        document.addEventListener('DOMContentLoaded', function() {
            const inputVoucher = document.getElementById('inputVoucherCode');
            if (inputVoucher) {
                inputVoucher.addEventListener('input', function() {
                    const filter = this.value.toUpperCase().trim();
                    const cards = document.querySelectorAll('.voucher-suggest-card');
                    cards.forEach(card => {
                        const code = card.querySelector('.voucher-tag')?.innerText || '';
                        const name = card.querySelector('.fw-bold')?.innerText || '';
                        if (code.toUpperCase().includes(filter) || name.toUpperCase().includes(filter)) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
