<?php
session_start();

/* =========================
   KONFIGURASI DATABASE
========================= */
$dbHost = "localhost";
$dbName = "donat_gembul";
$dbUser = "root";
$dbPass = "";

$ADMIN_USERNAME = "admin";
$ADMIN_PASSWORD = "gembul123";

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database belum tersedia. Pastikan MySQL aktif dan database donat_gembul sudah dibuat.");
}

/* =========================
   BUAT TABEL OTOMATIS
========================= */
$pdo->exec("
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    price INT NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    emoji VARCHAR(20) DEFAULT '🍩',
    badge VARCHAR(50) DEFAULT '',
    active TINYINT(1) DEFAULT 1,
    featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150) NOT NULL,
    whatsapp VARCHAR(30) NOT NULL,
    total INT NOT NULL DEFAULT 0,
    status VARCHAR(30) DEFAULT 'Baru',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    price INT NOT NULL DEFAULT 0,
    quantity INT NOT NULL DEFAULT 1,
    subtotal INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* =========================
   LOGIN
========================= */
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === $ADMIN_USERNAME && $password === $ADMIN_PASSWORD) {
        $_SESSION['gembul_admin'] = true;
        header("Location: admin.php");
        exit;
    }

    $login_error = "Username atau password salah.";
}

/* =========================
   LOGOUT
========================= */
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

/* =========================
   HALAMAN LOGIN
========================= */
if (empty($_SESSION['gembul_admin'])):
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Admin — Donat Gembul</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        radial-gradient(circle at top left, #ffe5e8, transparent 35%),
        radial-gradient(circle at bottom right, #f7d7c8, transparent 35%),
        #fff9f5;
    font-family: "DM Sans", sans-serif;
    color: #3d2925;
}

.login-box {
    width: 420px;
    max-width: 92%;
    background: rgba(255,255,255,.95);
    padding: 42px;
    border-radius: 28px;
    box-shadow: 0 25px 70px rgba(80,40,30,.15);
}

.logo {
    text-align: center;
    font-size: 52px;
    margin-bottom: 5px;
}

h1 {
    font-family: "Playfair Display", serif;
    text-align: center;
    margin: 0;
    font-size: 32px;
}

.subtitle {
    text-align: center;
    color: #8d7169;
    margin: 8px 0 30px;
}

label {
    display: block;
    font-weight: 700;
    margin-bottom: 8px;
}

input {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid #eadbd5;
    border-radius: 14px;
    margin-bottom: 18px;
    font-size: 15px;
    outline: none;
}

input:focus {
    border-color: #d86f78;
    box-shadow: 0 0 0 4px rgba(216,111,120,.1);
}

button {
    width: 100%;
    border: 0;
    padding: 15px;
    border-radius: 14px;
    background: #492c27;
    color: white;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
}

button:hover {
    background: #673d35;
}

.error {
    background: #ffe5e5;
    color: #a53d3d;
    padding: 12px 14px;
    border-radius: 12px;
    margin-bottom: 18px;
}

.demo {
    text-align: center;
    margin-top: 22px;
    font-size: 13px;
    color: #967c74;
}
</style>
</head>

<body>

<div class="login-box">

    <div class="logo">🍩</div>

    <h1>Donat Gembul</h1>

    <div class="subtitle">
        Panel Administrator
    </div>

    <?php if (!empty($login_error)): ?>
        <div class="error">
            <?= htmlspecialchars($login_error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Username</label>
        <input
            type="text"
            name="username"
            placeholder="Masukkan username"
            required
        >

        <label>Password</label>
        <input
            type="password"
            name="password"
            placeholder="Masukkan password"
            required
        >

        <button type="submit" name="login">
            Masuk ke Dashboard
        </button>

    </form>

    <div class="demo">
        Demo: admin / gembul123
    </div>

</div>

</body>
</html>

<?php
exit;
endif;


/* =========================
   HAPUS PRODUK
========================= */
if (isset($_GET['delete_product'])) {

    $id = (int) $_GET['delete_product'];

    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: admin.php?success=Produk berhasil dihapus");
    exit;
}


/* =========================
   SIMPAN PRODUK
========================= */
if (isset($_POST['save_product'])) {

    $id = (int) ($_POST['id'] ?? 0);

    $name = trim($_POST['name'] ?? '');
    $price = (int) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);
    $emoji = trim($_POST['emoji'] ?? '🍩');
    $badge = trim($_POST['badge'] ?? '');

    $active = isset($_POST['active']) ? 1 : 0;
    $featured = isset($_POST['featured']) ? 1 : 0;

    if ($id > 0) {

        $stmt = $pdo->prepare("
            UPDATE products
            SET name = ?,
                price = ?,
                stock = ?,
                emoji = ?,
                badge = ?,
                active = ?,
                featured = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $name,
            $price,
            $stock,
            $emoji,
            $badge,
            $active,
            $featured,
            $id
        ]);

        $message = "Produk berhasil diperbarui.";

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO products
            (name, price, stock, emoji, badge, active, featured)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $name,
            $price,
            $stock,
            $emoji,
            $badge,
            $active,
            $featured
        ]);

        $message = "Produk baru berhasil ditambahkan.";
    }

    header("Location: admin.php?success=" . urlencode($message));
    exit;
}


/* =========================
   UPDATE STATUS PESANAN
========================= */
if (isset($_POST['update_order'])) {

    $id = (int) ($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? 'Baru';

    $allowed = [
        'Baru',
        'Diproses',
        'Selesai',
        'Dibatalkan'
    ];

    if (in_array($status, $allowed, true)) {

        $stmt = $pdo->prepare("
            UPDATE orders
            SET status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $status,
            $id
        ]);
    }

    header("Location: admin.php?success=Status pesanan diperbarui");
    exit;
}


/* =========================
   DATA DASHBOARD
========================= */

$totalProducts = (int) $pdo
    ->query("SELECT COUNT(*) FROM products")
    ->fetchColumn();

$totalStock = (int) $pdo
    ->query("SELECT COALESCE(SUM(stock),0) FROM products")
    ->fetchColumn();

$lowStock = (int) $pdo
    ->query("
        SELECT COUNT(*)
        FROM products
        WHERE stock <= 5
    ")
    ->fetchColumn();

$newOrders = (int) $pdo
    ->query("
        SELECT COUNT(*)
        FROM orders
        WHERE status = 'Baru'
    ")
    ->fetchColumn();

$revenue = (int) $pdo
    ->query("
        SELECT COALESCE(SUM(total),0)
        FROM orders
        WHERE status IN ('Diproses','Selesai')
    ")
    ->fetchColumn();

$products = $pdo
    ->query("
        SELECT *
        FROM products
        ORDER BY id DESC
    ")
    ->fetchAll();

$orders = $pdo
    ->query("
        SELECT *
        FROM orders
        ORDER BY id DESC
        LIMIT 50
    ")
    ->fetchAll();


/* =========================
   EDIT PRODUCT
========================= */
$editProduct = null;

if (isset($_GET['edit_product'])) {

    $id = (int) $_GET['edit_product'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM products
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $editProduct = $stmt->fetch();
}

?>
<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Dashboard — Donat Gembul</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #fff9f5;
    color: #3d2925;
    font-family: "DM Sans", sans-serif;
}

/* =========================
   HEADER
========================= */

.topbar {
    height: 74px;
    background: #492c27;
    color: white;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 32px;

    position: sticky;
    top: 0;
    z-index: 20;

    box-shadow: 0 5px 25px rgba(60,35,30,.15);
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-icon {
    font-size: 32px;
}

.brand-title {
    font-family: "Playfair Display", serif;
    font-size: 23px;
}

.brand small {
    display: block;
    opacity: .7;
    font-size: 11px;
}

.logout {
    color: white;
    text-decoration: none;
    padding: 10px 16px;
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 10px;
}

.logout:hover {
    background: rgba(255,255,255,.1);
}


/* =========================
   CONTAINER
========================= */

.container {
    width: min(1400px, 94%);
    margin: auto;
    padding: 35px 0 60px;
}

.page-title {
    margin-bottom: 28px;
}

.page-title h1 {
    font-family: "Playfair Display", serif;
    font-size: 34px;
    margin: 0 0 5px;
}

.page-title p {
    color: #8d7169;
    margin: 0;
}


/* =========================
   ALERT
========================= */

.alert {
    background: #e9f8ee;
    border: 1px solid #bfe7cc;
    color: #277142;
    padding: 14px 18px;
    border-radius: 14px;
    margin-bottom: 25px;
}


/* =========================
   STAT CARDS
========================= */

.stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 18px;
    margin-bottom: 32px;
}

.stat {
    background: white;
    border-radius: 20px;
    padding: 22px;
    border: 1px solid #f0e1db;
    box-shadow: 0 10px 30px rgba(80,40,30,.06);
}

.stat-icon {
    font-size: 28px;
    margin-bottom: 10px;
}

.stat-label {
    color: #927a72;
    font-size: 13px;
}

.stat-number {
    font-size: 27px;
    font-weight: 700;
    margin-top: 5px;
}


/* =========================
   GRID
========================= */

.grid {
    display: grid;
    grid-template-columns: 390px 1fr;
    gap: 24px;
    align-items: start;
}


/* =========================
   CARD
========================= */

.card {
    background: white;
    border-radius: 22px;
    border: 1px solid #f0e1db;
    box-shadow: 0 10px 30px rgba(80,40,30,.06);
    overflow: hidden;
}

.card-header {
    padding: 20px 22px;
    border-bottom: 1px solid #f0e7e3;
}

.card-header h2 {
    margin: 0;
    font-size: 19px;
}

.card-body {
    padding: 22px;
}


/* =========================
   FORM
========================= */

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-weight: 700;
    font-size: 13px;
    margin-bottom: 7px;
}

.form-group input,
.form-group select {
    width: 100%;
    border: 1px solid #e5d8d2;
    border-radius: 11px;
    padding: 12px 13px;
    font: inherit;
    outline: none;
    background: #fff;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #d66e78;
    box-shadow: 0 0 0 3px rgba(214,110,120,.08);
}

.checks {
    display: flex;
    gap: 18px;
    margin: 15px 0;
}

.checks label {
    font-size: 13px;
}

.btn {
    border: 0;
    border-radius: 11px;
    padding: 12px 18px;
    font-weight: 700;
    cursor: pointer;
}

.btn-primary {
    background: #492c27;
    color: white;
}

.btn-primary:hover {
    background: #673d35;
}

.btn-secondary {
    background: #f2e9e5;
    color: #5d4038;
}

.btn-danger {
    background: #ffe5e5;
    color: #a43f3f;
}

.btn-small {
    padding: 8px 11px;
    font-size: 12px;
}


/* =========================
   PRODUCT TABLE
========================= */

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    text-align: left;
    font-size: 12px;
    color: #927a72;
    text-transform: uppercase;
    padding: 13px 15px;
    background: #fffaf8;
    white-space: nowrap;
}

td {
    padding: 15px;
    border-top: 1px solid #f1e8e4;
    vertical-align: middle;
    font-size: 14px;
}

.product-name {
    font-weight: 700;
}

.product-emoji {
    font-size: 28px;
}

.badge {
    display: inline-block;
    padding: 5px 8px;
    background: #ffe6e8;
    color: #a74b55;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
}

.stock-good {
    color: #2e7d4b;
    font-weight: 700;
}

.stock-low {
    color: #c45c36;
    font-weight: 700;
}

.stock-empty {
    color: #b33d3d;
    font-weight: 700;
}

.actions {
    display: flex;
    gap: 7px;
}


/* =========================
   ORDERS
========================= */

.orders-card {
    margin-top: 24px;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 9px;
    font-size: 11px;
    font-weight: 700;
}

.status-Baru {
    background: #fff0cf;
    color: #a56b00;
}

.status-Diproses {
    background: #e8f0ff;
    color: #4168a8;
}

.status-Selesai {
    background: #e5f7eb;
    color: #287746;
}

.status-Dibatalkan {
    background: #ffe5e5;
    color: #a33d3d;
}

.order-form {
    display: flex;
    gap: 7px;
}

.order-form select {
    padding: 7px;
    border: 1px solid #e2d8d3;
    border-radius: 8px;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns: repeat(3, 1fr);
    }

    .grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 700px) {

    .topbar {
        padding: 0 16px;
    }

    .container {
        width: 94%;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .page-title h1 {
        font-size: 28px;
    }

    .stat {
        padding: 16px;
    }

}

@media (max-width: 450px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .brand-title {
        font-size: 18px;
    }

}

</style>

</head>

<body>


<!-- =========================
     HEADER
========================= -->

<header class="topbar">

    <div class="brand">

        <div class="brand-icon">
            🍩
        </div>

        <div>
            <div class="brand-title">
                Donat Gembul
            </div>

            <small>
                Admin Dashboard
            </small>
        </div>

    </div>

    <a
        class="logout"
        href="admin.php?logout=1"
        onclick="return confirm('Keluar dari admin?')"
    >
        Keluar
    </a>

</header>


<main class="container">


<!-- =========================
     TITLE
========================= -->

<div class="page-title">

    <h1>
        Dashboard
    </h1>

    <p>
        Kelola produk, stok, dan pesanan Donat Gembul.
    </p>

</div>


<?php if (isset($_GET['success'])): ?>

<div class="alert">
    ✅ <?= htmlspecialchars($_GET['success']) ?>
</div>

<?php endif; ?>


<!-- =========================
     STATS
========================= -->

<section class="stats">

    <div class="stat">

        <div class="stat-icon">
            🍩
        </div>

        <div class="stat-label">
            Total Produk
        </div>

        <div class="stat-number">
            <?= $totalProducts ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-icon">
            📦
        </div>

        <div class="stat-label">
            Total Stok
        </div>

        <div class="stat-number">
            <?= $totalStock ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-icon">
            ⚠️
        </div>

        <div class="stat-label">
            Stok Menipis
        </div>

        <div class="stat-number">
            <?= $lowStock ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-icon">
            🛍️
        </div>

        <div class="stat-label">
            Pesanan Baru
        </div>

        <div class="stat-number">
            <?= $newOrders ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-icon">
            💰
        </div>

        <div class="stat-label">
            Pendapatan
        </div>

        <div class="stat-number">
            Rp <?= number_format($revenue, 0, ',', '.') ?>
        </div>

    </div>

</section>


<!-- =========================
     PRODUCT MANAGEMENT
========================= -->

<section class="grid">


<!-- FORM -->

<div class="card">

    <div class="card-header">

        <h2>
            <?= $editProduct ? '✏️ Edit Produk' : '➕ Tambah Produk' ?>
        </h2>

    </div>


    <div class="card-body">

        <form method="POST">

            <input
                type="hidden"
                name="id"
                value="<?= $editProduct['id'] ?? 0 ?>"
            >


            <div class="form-group">

                <label>
                    Nama Donat
                </label>

                <input
                    type="text"
                    name="name"
                    placeholder="Contoh: Donat Cokelat"
                    value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Harga
                </label>

                <input
                    type="number"
                    name="price"
                    min="0"
                    placeholder="12000"
                    value="<?= $editProduct['price'] ?? '' ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Stok
                </label>

                <input
                    type="number"
                    name="stock"
                    min="0"
                    placeholder="10"
                    value="<?= $editProduct['stock'] ?? '' ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Emoji
                </label>

                <input
                    type="text"
                    name="emoji"
                    value="<?= htmlspecialchars($editProduct['emoji'] ?? '🍩') ?>"
                >

            </div>


            <div class="form-group">

                <label>
                    Badge
                </label>

                <input
                    type="text"
                    name="badge"
                    placeholder="BEST SELLER"
                    value="<?= htmlspecialchars($editProduct['badge'] ?? '') ?>"
                >

            </div>


            <div class="checks">

                <label>
                    <input
                        type="checkbox"
                        name="active"
                        <?= (!$editProduct || $editProduct['active']) ? 'checked' : '' ?>
                    >
                    Aktif
                </label>


                <label>
                    <input
                        type="checkbox"
                        name="featured"
                        <?= ($editProduct && $editProduct['featured']) ? 'checked' : '' ?>
                    >
                    Unggulan
                </label>

            </div>


            <button
                class="btn btn-primary"
                type="submit"
                name="save_product"
            >
                <?= $editProduct ? 'Simpan Perubahan' : 'Tambah Produk' ?>
            </button>


            <?php if ($editProduct): ?>

                <a
                    class="btn btn-secondary"
                    href="admin.php"
                    style="text-decoration:none;display:inline-block"
                >
                    Batal
                </a>

            <?php endif; ?>

        </form>

    </div>

</div>


<!-- PRODUCT LIST -->

<div class="card">

    <div class="card-header">

        <h2>
            📋 Daftar Produk
        </h2>

    </div>


    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>Produk</th>

                    <th>Harga</th>

                    <th>Stok</th>

                    <th>Status</th>

                    <th>Aksi</th>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($products as $product): ?>

                <tr>

                    <td>

                        <div style="display:flex;align-items:center;gap:12px">

                            <div class="product-emoji">
                                <?= htmlspecialchars($product['emoji']) ?>
                            </div>

                            <div>

                                <div class="product-name">
                                    <?= htmlspecialchars($product['name']) ?>
                                </div>

                                <?php if ($product['badge']): ?>

                                    <span class="badge">
                                        <?= htmlspecialchars($product['badge']) ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </td>


                    <td>
                        Rp <?= number_format($product['price'], 0, ',', '.') ?>
                    </td>


                    <td>

                        <?php

                        if ($product['stock'] <= 0) {
                            $stockClass = 'stock-empty';
                        } elseif ($product['stock'] <= 5) {
                            $stockClass = 'stock-low';
                        } else {
                            $stockClass = 'stock-good';
                        }

                        ?>

                        <span class="<?= $stockClass ?>">
                            <?= $product['stock'] ?>
                        </span>

                    </td>


                    <td>

                        <?php if ($product['active']): ?>

                            <span class="status status-Selesai">
                                Aktif
                            </span>

                        <?php else: ?>

                            <span class="status status-Dibatalkan">
                                Nonaktif
                            </span>

                        <?php endif; ?>

                    </td>


                    <td>

                        <div class="actions">

                            <a
                                href="admin.php?edit_product=<?= $product['id'] ?>"
                                class="btn btn-secondary btn-small"
                                style="text-decoration:none"
                            >
                                Edit
                            </a>


                            <a
                                href="admin.php?delete_product=<?= $product['id'] ?>"
                                class="btn btn-danger btn-small"
                                style="text-decoration:none"
                                onclick="return confirm('Hapus produk ini?')"
                            >
                                Hapus
                            </a>

                        </div>

                    </td>

                </tr>

            <?php endforeach; ?>


            <?php if (!$products): ?>

                <tr>

                    <td colspan="5" style="text-align:center;padding:35px">

                        Belum ada produk.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</section>


<!-- =========================
     ORDERS
========================= -->

<section class="card orders-card">

    <div class="card-header">

        <h2>
            🛍️ Pesanan Terbaru
        </h2>

    </div>


    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>ID</th>

                    <th>Pelanggan</th>

                    <th>WhatsApp</th>

                    <th>Total</th>

                    <th>Status</th>

                    <th>Tanggal</th>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($orders as $order): ?>

                <tr>

                    <td>
                        #<?= $order['id'] ?>
                    </td>


                    <td>
                        <strong>
                            <?= htmlspecialchars($order['customer_name']) ?>
                        </strong>
                    </td>


                    <td>

                        <a
                            href="https://wa.me/<?= htmlspecialchars($order['whatsapp']) ?>"
                            target="_blank"
                            style="color:#267a49;text-decoration:none"
                        >
                            <?= htmlspecialchars($order['whatsapp']) ?>
                        </a>

                    </td>


                    <td>

                        <strong>
                            Rp <?= number_format($order['total'], 0, ',', '.') ?>
                        </strong>

                    </td>


                    <td>

                        <form
                            method="POST"
                            class="order-form"
                        >

                            <input
                                type="hidden"
                                name="order_id"
                                value="<?= $order['id'] ?>"
                            >

                            <select name="status">

                                <?php

                                $statuses = [
                                    'Baru',
                                    'Diproses',
                                    'Selesai',
                                    'Dibatalkan'
                                ];

                                foreach ($statuses as $status):

                                ?>

                                    <option
                                        value="<?= $status ?>"
                                        <?= $order['status'] === $status ? 'selected' : '' ?>
                                    >
                                        <?= $status ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>


                            <button
                                class="btn btn-primary btn-small"
                                type="submit"
                                name="update_order"
                            >
                                Simpan
                            </button>

                        </form>

                    </td>


                    <td>

                        <?= date(
                            'd/m/Y H:i',
                            strtotime($order['created_at'])
                        ) ?>

                    </td>

                </tr>

            <?php endforeach; ?>


            <?php if (!$orders): ?>

                <tr>

                    <td
                        colspan="6"
                        style="text-align:center;padding:35px"
                    >
                        Belum ada pesanan.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


</main>

</body>
</html>