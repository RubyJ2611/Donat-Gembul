<?php
/* =========================================================
   DONAT GEMBUL PEKANBARU
   Single File PHP + MySQL
   ========================================================= */

declare(strict_types=1);

session_start();

/* =========================
   KONFIGURASI
========================= */

$dbHost = "localhost";
$dbName = "donat_gembul";
$dbUser = "root";
$dbPass = "";

$waNumber = "6282122318057";

// Lokasi toko Donat Gembul
$storeLat = 0.507067;
$storeLng = 101.447779;

// Ongkir
$pricePerKm = 2000;
$maxDistanceKm = 100;


/* =========================
   KONEKSI DATABASE
========================= */

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $pdo->exec("
        CREATE DATABASE IF NOT EXISTS `{$dbName}`
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci
    ");

    $pdo->exec("USE `{$dbName}`");

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}


/* =========================
   BUAT TABEL
========================= */

$pdo->exec("
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    price INT NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    emoji VARCHAR(20) DEFAULT '🍩',
    badge VARCHAR(50) DEFAULT NULL,
    featured TINYINT(1) DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(50) NOT NULL UNIQUE,
    customer_name VARCHAR(150) NOT NULL,
    whatsapp VARCHAR(30) NOT NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10,7) DEFAULT NULL,
    longitude DECIMAL(10,7) DEFAULT NULL,
    distance_km DECIMAL(10,2) DEFAULT NULL,
    subtotal INT NOT NULL DEFAULT 0,
    shipping INT NOT NULL DEFAULT 0,
    total INT NOT NULL DEFAULT 0,
    payment_method VARCHAR(30) NOT NULL,
    status VARCHAR(30) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    price INT NOT NULL,
    quantity INT NOT NULL,
    subtotal INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB
");


/* =========================
   CEK KOLOM
========================= */

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");

    $stmt->execute([$table, $column]);

    return (bool)$stmt->fetchColumn();
}


/* =========================
   PERBAIKAN SCHEMA
========================= */

$columns = [
    'products' => [
        'description' => "ALTER TABLE products ADD description TEXT NULL AFTER name",
        'price'       => "ALTER TABLE products ADD price INT NOT NULL DEFAULT 0",
        'stock'       => "ALTER TABLE products ADD stock INT NOT NULL DEFAULT 0",
        'emoji'       => "ALTER TABLE products ADD emoji VARCHAR(20) DEFAULT '🍩'",
        'badge'       => "ALTER TABLE products ADD badge VARCHAR(50) DEFAULT NULL",
        'featured'    => "ALTER TABLE products ADD featured TINYINT(1) DEFAULT 0",
        'active'      => "ALTER TABLE products ADD active TINYINT(1) DEFAULT 1"
    ]
];

foreach ($columns as $table => $tableColumns) {
    foreach ($tableColumns as $column => $sql) {
        if (!columnExists($pdo, $table, $column)) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // Abaikan jika sudah tersedia
            }
        }
    }
}


/* =========================
   AUTO REPAIR DATABASE
   Menyamakan tabel lama dengan struktur terbaru
========================= */
$orderColumns = [
    'order_code'     => "ALTER TABLE orders ADD COLUMN order_code VARCHAR(50) NULL",
    'customer_name'  => "ALTER TABLE orders ADD COLUMN customer_name VARCHAR(150) NOT NULL DEFAULT ''",
    'whatsapp'       => "ALTER TABLE orders ADD COLUMN whatsapp VARCHAR(30) NOT NULL DEFAULT ''",
    'address'        => "ALTER TABLE orders ADD COLUMN address TEXT NULL",
    'latitude'       => "ALTER TABLE orders ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL",
    'longitude'      => "ALTER TABLE orders ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL",
    'distance_km'    => "ALTER TABLE orders ADD COLUMN distance_km DECIMAL(10,2) DEFAULT NULL",
    'subtotal'       => "ALTER TABLE orders ADD COLUMN subtotal INT NOT NULL DEFAULT 0",
    'shipping'       => "ALTER TABLE orders ADD COLUMN shipping INT NOT NULL DEFAULT 0",
    'total'          => "ALTER TABLE orders ADD COLUMN total INT NOT NULL DEFAULT 0",
    'payment_method' => "ALTER TABLE orders ADD COLUMN payment_method VARCHAR(30) NOT NULL DEFAULT 'COD'",
    'status'         => "ALTER TABLE orders ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'",
    'created_at'     => "ALTER TABLE orders ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
];

foreach ($orderColumns as $column => $sql) {
    if (!columnExists($pdo, 'orders', $column)) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            // Lanjutkan pengecekan kolom berikutnya.
        }
    }
}

// Isi kode pesanan lama sebelum kolom dibuat NOT NULL/UNIQUE.
if (columnExists($pdo, 'orders', 'order_code')) {
    try {
        $pdo->exec("UPDATE orders SET order_code = CONCAT('DG-LEGACY-', id) WHERE order_code IS NULL OR order_code = ''");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("ALTER TABLE orders MODIFY order_code VARCHAR(50) NOT NULL");
    } catch (Throwable $e) {}
}

/* =========================
   SEED PRODUK
========================= */

$countProducts = (int)$pdo->query(
    "SELECT COUNT(*) FROM products"
)->fetchColumn();

if ($countProducts === 0) {

    $productsSeed = [
        [
            "Donat Cokelat",
            "Donat lembut dengan topping cokelat premium.",
            12000,
            12,
            "🍫",
            "BEST SELLER",
            1
        ],
        [
            "Donat Strawberry",
            "Donat manis dengan topping strawberry yang segar.",
            13000,
            8,
            "🍓",
            "FAVORIT",
            1
        ],
        [
            "Donat Matcha",
            "Perpaduan donat lembut dan rasa matcha yang creamy.",
            14000,
            6,
            "🍵",
            "NEW",
            0
        ],
        [
            "Donat Tiramisu",
            "Rasa tiramisu creamy dengan aroma kopi yang nikmat.",
            15000,
            5,
            "☕",
            "PREMIUM",
            1
        ],
        [
            "Donat Keju",
            "Donat lembut dengan topping keju gurih.",
            13000,
            10,
            "🧀",
            "FAVORIT",
            0
        ],
        [
            "Donat Oreo",
            "Donat dengan topping Oreo yang crunchy.",
            14000,
            4,
            "🍪",
            "HAMPIR HABIS",
            0
        ],
        [
            "Donat Milo",
            "Donat cokelat dengan topping Milo.",
            13000,
            9,
            "🥛",
            "FAVORIT",
            0
        ],
        [
            "Donat Mix",
            "Paket berbagai rasa Donat Gembul.",
            45000,
            3,
            "🍩",
            "BEST SELLER",
            1
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO products
        (name, description, price, stock, emoji, badge, featured)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($productsSeed as $product) {
        $stmt->execute($product);
    }
}


/* =========================
   HELPER
========================= */

function rupiah(int|float $number): string
{
    return "Rp " . number_format((float)$number, 0, ',', '.');
}

function jsonResponse(array $data): never
{
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function haversine(
    float $lat1,
    float $lon1,
    float $lat2,
    float $lon2
): float {

    $earthRadius = 6371;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a =
        sin($dLat / 2) ** 2 +
        cos(deg2rad($lat1)) *
        cos(deg2rad($lat2)) *
        sin($dLon / 2) ** 2;

    $c = 2 * atan2(
        sqrt($a),
        sqrt(1 - $a)
    );

    return $earthRadius * $c;
}


/* =========================
   AJAX CHECKOUT
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'checkout') {

        try {

            $customerName = trim(
                $_POST['customer_name'] ?? ''
            );

            $whatsapp = trim(
                $_POST['whatsapp'] ?? ''
            );

            $address = trim(
                $_POST['address'] ?? ''
            );

            $payment = trim(
                $_POST['payment_method'] ?? ''
            );

            $latitude = isset($_POST['latitude'])
                ? (float)$_POST['latitude']
                : 0;

            $longitude = isset($_POST['longitude'])
                ? (float)$_POST['longitude']
                : 0;

            $cartJson = $_POST['cart'] ?? '[]';

            $cart = json_decode(
                $cartJson,
                true
            );

            if (!is_array($cart)) {
                throw new Exception(
                    "Data keranjang tidak valid."
                );
            }

            if ($customerName === '') {
                throw new Exception(
                    "Nama wajib diisi."
                );
            }

            if ($whatsapp === '') {
                throw new Exception(
                    "Nomor WhatsApp wajib diisi."
                );
            }

            if ($address === '') {
                throw new Exception(
                    "Alamat wajib diisi."
                );
            }

            if (!in_array(
                $payment,
                ['QRIS', 'COD'],
                true
            )) {
                throw new Exception(
                    "Metode pembayaran tidak valid."
                );
            }

            if ($latitude == 0 || $longitude == 0) {
                throw new Exception(
                    "Lokasi belum terdeteksi. Silakan izinkan akses lokasi."
                );
            }

            if (count($cart) === 0) {
                throw new Exception(
                    "Keranjang masih kosong."
                );
            }


            /* HITUNG JARAK */

            $distance = haversine(
                $storeLat,
                $storeLng,
                $latitude,
                $longitude
            );

            if ($distance > $maxDistanceKm) {
                throw new Exception(
                    "Lokasi terlalu jauh. Maksimal pengantaran {$maxDistanceKm} km."
                );
            }

            $shipping =
                (int)ceil($distance) *
                $pricePerKm;


            /* TRANSAKSI */

            $pdo->beginTransaction();

            $subtotal = 0;
            $orderItems = [];

            $productStmt = $pdo->prepare("
                SELECT *
                FROM products
                WHERE id = ?
                AND active = 1
                FOR UPDATE
            ");

            foreach ($cart as $item) {

                $productId =
                    (int)($item['id'] ?? 0);

                $quantity =
                    (int)($item['qty'] ?? 0);

                if (
                    $productId <= 0 ||
                    $quantity <= 0
                ) {
                    continue;
                }

                $productStmt->execute([
                    $productId
                ]);

                $product =
                    $productStmt->fetch();

                if (!$product) {
                    throw new Exception(
                        "Produk tidak ditemukan."
                    );
                }

                if (
                    (int)$product['stock'] <
                    $quantity
                ) {
                    throw new Exception(
                        "Stok {$product['name']} tidak mencukupi."
                    );
                }

                $itemSubtotal =
                    (int)$product['price'] *
                    $quantity;

                $subtotal += $itemSubtotal;

                $orderItems[] = [
                    'product_id' => $productId,
                    'name'       => $product['name'],
                    'price'      => (int)$product['price'],
                    'qty'        => $quantity,
                    'subtotal'   => $itemSubtotal
                ];
            }

            if (!$orderItems) {
                throw new Exception(
                    "Keranjang kosong."
                );
            }

            $total =
                $subtotal +
                $shipping;

            $orderCode =
                "DG-" .
                date('YmdHis') .
                "-" .
                random_int(100, 999);


            /* INSERT ORDER */

            $stmtOrder = $pdo->prepare("
                INSERT INTO orders
                (
                    order_code,
                    customer_name,
                    whatsapp,
                    address,
                    latitude,
                    longitude,
                    distance_km,
                    subtotal,
                    shipping,
                    total,
                    payment_method,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");

            $stmtOrder->execute([
                $orderCode,
                $customerName,
                $whatsapp,
                $address,
                $latitude,
                $longitude,
                $distance,
                $subtotal,
                $shipping,
                $total,
                $payment
            ]);

            $orderId =
                (int)$pdo->lastInsertId();


            /* INSERT ITEMS */

            $stmtItem = $pdo->prepare("
                INSERT INTO order_items
                (
                    order_id,
                    product_id,
                    product_name,
                    price,
                    quantity,
                    subtotal
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmtStock = $pdo->prepare("
                UPDATE products
                SET stock = stock - ?
                WHERE id = ?
            ");

            foreach ($orderItems as $item) {

                $stmtItem->execute([
                    $orderId,
                    $item['product_id'],
                    $item['name'],
                    $item['price'],
                    $item['qty'],
                    $item['subtotal']
                ]);

                $stmtStock->execute([
                    $item['qty'],
                    $item['product_id']
                ]);
            }

            $pdo->commit();


            /* PESAN WHATSAPP */

            $message =
                "🍩 *ORDER DONAT GEMBUL PEKANBARU*\n\n";

            $message .=
                "*Kode Pesanan:* {$orderCode}\n";

            $message .=
                "*Nama:* {$customerName}\n";

            $message .=
                "*WhatsApp:* {$whatsapp}\n";

            $message .=
                "*Alamat:* {$address}\n";

            $message .=
                "*Jarak:* " .
                number_format(
                    $distance,
                    2,
                    ',',
                    '.'
                ) .
                " km\n";

            $message .= "\n*PESANAN:*\n";

            foreach ($orderItems as $item) {

                $message .=
                    "🍩 {$item['name']} x{$item['qty']} — " .
                    rupiah($item['subtotal']) .
                    "\n";
            }

            $message .= "\n";

            $message .=
                "Subtotal: " .
                rupiah($subtotal) .
                "\n";

            $message .=
                "Ongkir: " .
                rupiah($shipping) .
                "\n";

            $message .=
                "*TOTAL: " .
                rupiah($total) .
                "*\n\n";

            $message .=
                "Pembayaran: *{$payment}*\n\n";

            $message .=
                "Terima kasih sudah order di Donat Gembul! 🍩❤️";


            $whatsappUrl =
                "https://wa.me/" .
                $waNumber .
                "?text=" .
                rawurlencode($message);


            jsonResponse([
                'success' => true,
                'message' =>
                    'Pesanan berhasil dibuat.',
                'order_code' =>
                    $orderCode,
                'whatsapp_url' =>
                    $whatsappUrl
            ]);

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}


/* =========================
   AMBIL PRODUK
========================= */

$stmtProducts = $pdo->query("
    SELECT *
    FROM products
    WHERE active = 1
    ORDER BY featured DESC, id ASC
");

$products =
    $stmtProducts->fetchAll();

$productsJson = json_encode(
    $products,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
);

?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Donat Gembul Pekanbaru — Donat Fresh Setiap Hari
</title>

<meta
    name="description"
    content="Donat Gembul Pekanbaru. Donat lembut, fresh setiap hari dan bisa diantar ke lokasi kamu."
>

<script src="https://cdn.tailwindcss.com"></script>

<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                cream: "#FFF3DC",
                cream2: "#FFF9ED",
                blackg: "#5A321F",
                black2: "#7A4930",
                brown: "#6B3E26",
                orange: "#C9824B"
            },

            fontFamily: {
                sans: ["DM Sans", "sans-serif"],
                display: ["Playfair Display", "serif"]
            }
        }
    }
}
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">

<link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@500;600;700;800&display=swap"
    rel="stylesheet"
>

<script src="https://unpkg.com/lucide@latest"></script>


<style>

/* =========================================================
   GLOBAL
========================================================= */

:root {
    --cream: #FFF3DC;
    --cream-light: #FFF9ED;
    --black: #5A321F;
    --black2: #7A4930;
    --brown: #6B3E26;
    --orange: #C9824B;
    --white: #FFF9ED;
}

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    background: var(--cream);
    color: var(--white);
    font-family: "DM Sans", sans-serif;
    overflow-x: hidden;
}


/* =========================================================
   BACKGROUND
========================================================= */

.site-background {
    position: fixed;
    inset: 0;
    z-index: -20;
    background: var(--cream);
}


/* =========================================================
   DONUT RAIN
========================================================= */

#donutRain {
    position: fixed;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
    z-index: -5;
}

.falling-donut {
    position: absolute;
    top: -80px;
    opacity: .20;
    animation: fallingDonut linear forwards;
    filter: grayscale(.2);
}

@keyframes fallingDonut {

    0% {
        transform:
            translateY(-100px)
            rotate(0deg);
    }

    100% {
        transform:
            translateY(110vh)
            rotate(360deg);
    }
}


/* =========================================================
   NAVBAR
========================================================= */

.main-nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 100;

    background: rgba(11,9,8,.96);
    backdrop-filter: blur(18px);

    border-bottom:
        1px solid rgba(255,255,255,.08);
}

.nav-inner {
    max-width: 1250px;
    margin: auto;

    padding: 15px 22px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    min-height: 65px;
}

.logo {
    color: var(--white);
    text-decoration: none;

    display: flex;
    align-items: center;
    gap: 10px;

    flex-shrink: 0;
}

.logo-icon {
    width: 42px;
    height: 42px;

    border-radius: 50%;

    background: var(--cream);

    display: grid;
    place-items: center;

    font-size: 22px;
}

.logo-title {
    font-size: 17px;
    font-weight: 900;
    letter-spacing: .05em;
}

.logo-sub {
    font-size: 10px;
    opacity: .55;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 28px;
}

.nav-links a {
    color: rgba(255,248,237,.75);
    text-decoration: none;

    font-size: 13px;
    font-weight: 700;

    transition: .25s;
}

.nav-links a:hover {
    color: var(--cream);
}

.cart-button {
    border: 0;
    cursor: pointer;

    background: var(--cream);
    color: var(--black);

    border-radius: 999px;

    padding: 11px 17px;

    display: flex;
    align-items: center;
    gap: 8px;

    font-weight: 900;

    transition: .25s;

    flex-shrink: 0;
}

.cart-button:hover {
    transform: translateY(-2px);
}

.cart-count {
    min-width: 22px;
    height: 22px;

    border-radius: 50%;

    background: var(--black);
    color: var(--cream);

    display: grid;
    place-items: center;

    font-size: 11px;
}

.mobile-menu-button {
    display: none;

    width: 42px;
    height: 42px;

    border: 0;
    border-radius: 50%;

    background: var(--cream);
    color: var(--black);

    cursor: pointer;

    place-items: center;

    flex-shrink: 0;
}


/* =========================================================
   MOBILE MENU
========================================================= */

.mobile-menu {
    position: fixed;

    top: 75px;
    left: 12px;
    right: 12px;

    z-index: 150;

    display: none;

    padding: 10px;

    background:
        rgba(59,41,32,.98);

    border:
        1px solid rgba(255,248,237,.12);

    border-radius: 20px;

    box-shadow:
        0 20px 50px rgba(0,0,0,.35);

    backdrop-filter:
        blur(18px);

    opacity: 0;

    transform:
        translateY(-10px);

    transition:
        opacity .25s ease,
        transform .25s ease;
}

.mobile-menu.show {
    display: block;
    opacity: 1;
    transform: translateY(0);
}

.mobile-menu a,
.mobile-menu button {
    width: 100%;

    display: flex;
    align-items: center;
    gap: 12px;

    padding: 14px 16px;

    margin-bottom: 4px;

    border: 0;
    border-radius: 13px;

    background: transparent;
    color: var(--cream-light);

    text-decoration: none;

    font-family: inherit;
    font-size: 14px;
    font-weight: 800;

    cursor: pointer;
    text-align: left;

    transition: .2s;
}

.mobile-menu a:hover,
.mobile-menu button:hover {
    background: var(--cream);
    color: var(--black);
}

.mobile-menu button {
    margin-top: 5px;
}


/* =========================================================
   RUNNING TEXT
========================================================= */

.running-text {
    position: relative;
    z-index: 45;

    width: 100%;

    overflow: hidden;

    background: var(--black);
    color: var(--cream);

    border-top:
        1px solid rgba(255,255,255,.06);

    border-bottom:
        1px solid rgba(255,255,255,.08);

    white-space: nowrap;

    margin-top: 72px;
}

.running-track {
    display: flex;
    width: max-content;

    animation:
        runningText 28s linear infinite;

    will-change: transform;
}

.running-item {
    display: flex;
    align-items: center;

    padding: 13px 28px;

    font-size: 12px;
    font-weight: 800;

    letter-spacing: .08em;
    text-transform: uppercase;

    flex-shrink: 0;
}

.running-item::after {
    content: "✦";

    margin-left: 28px;

    color: var(--orange);
}

@keyframes runningText {

    from {
        transform: translateX(0);
    }

    to {
        transform: translateX(-50%);
    }
}

.running-text:hover .running-track {
    animation-play-state: paused;
}


/* =========================================================
   HERO
========================================================= */

.hero {
    min-height: 700px;

    background: var(--black);

    position: relative;
    overflow: hidden;
}

.hero::before {
    content: "";

    position: absolute;

    width: 600px;
    height: 600px;

    border-radius: 50%;

    background:
        radial-gradient(
            circle,
            rgba(229,138,90,.18),
            transparent 65%
        );

    top: -200px;
    right: -120px;
}

.hero-container {
    max-width: 1250px;

    min-height: 700px;

    margin: auto;

    padding: 100px 22px;

    display: grid;

    grid-template-columns:
        1.05fr .95fr;

    align-items: center;

    gap: 50px;

    position: relative;
    z-index: 2;
}

.hero-kicker {
    display: inline-flex;

    align-items: center;
    gap: 8px;

    padding: 8px 13px;

    border:
        1px solid rgba(255,255,255,.12);

    border-radius: 999px;

    font-size: 11px;
    font-weight: 800;

    color: var(--cream);
}

.hero-title {
    margin: 20px 0 12px;

    font-family:
        "Playfair Display",
        serif;

    font-size:
        clamp(58px, 9vw, 115px);

    line-height: .88;

    letter-spacing: -.06em;
}

.hero-title span {
    color: var(--orange);
}

.hero-description {
    max-width: 580px;

    color:
        rgba(255,248,237,.68);

    font-size: 17px;

    line-height: 1.8;
}

.hero-buttons {
    margin-top: 30px;

    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.btn-primary {
    display: inline-flex;

    align-items: center;
    gap: 9px;

    padding: 14px 20px;

    border-radius: 999px;

    background: var(--cream);
    color: var(--black);

    text-decoration: none;

    font-weight: 900;

    transition: .25s;

    border: 0;
    cursor: pointer;
}

.btn-primary:hover {
    transform: translateY(-3px);
}

.btn-secondary {
    display: inline-flex;

    align-items: center;
    gap: 9px;

    padding: 14px 20px;

    border-radius: 999px;

    border:
        1px solid rgba(255,255,255,.18);

    color: var(--cream);

    text-decoration: none;

    font-weight: 800;

    transition: .25s;

    background: transparent;
    cursor: pointer;
}

.btn-secondary:hover {
    background: rgba(255,255,255,.07);
}


/* =========================================================
   HERO DONUT
========================================================= */

.hero-donut-wrap {
    display: flex;
    justify-content: center;
    align-items: center;

    position: relative;
}

.hero-donut {
    width:
        min(470px, 85vw);

    aspect-ratio: 1;

    border-radius: 50%;

    background:
        radial-gradient(
            circle at 50% 50%,
            var(--cream) 0 17%,
            transparent 18%
        ),
        radial-gradient(
            circle,
            #D89462 0 45%,
            #A95F38 46% 65%,
            #70402A 66% 100%
        );

    box-shadow:
        0 40px 100px
        rgba(0,0,0,.6);

    animation:
        heroFloat 5s ease-in-out infinite;
}

.hero-donut::after {
    content: "";

    position: absolute;

    inset: 16%;

    border-radius: 50%;

    border:
        15px dashed
        rgba(255,248,237,.5);

    transform:
        rotate(20deg);
}

@keyframes heroFloat {

    0%,100% {
        transform:
            translateY(0)
            rotate(-3deg);
    }

    50% {
        transform:
            translateY(-16px)
            rotate(3deg);
    }
}


/* =========================================================
   TRUST
========================================================= */

.trust {
    background: var(--black2);

    border-top:
        1px solid rgba(255,255,255,.05);

    border-bottom:
        1px solid rgba(255,255,255,.05);
}

.trust-container {
    max-width: 1250px;

    margin: auto;

    padding: 25px 22px;

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 20px;
}

.trust-item {
    display: flex;
    align-items: center;
    gap: 12px;
}

.trust-icon {
    width: 43px;
    height: 43px;

    border-radius: 50%;

    background: rgba(255,255,255,.07);

    display: grid;
    place-items: center;

    color: var(--cream);
}

.trust-title {
    font-size: 13px;
    font-weight: 900;
}

.trust-text {
    font-size: 11px;
    color: rgba(255,255,255,.5);
}


/* =========================================================
   SECTION
========================================================= */

.section {
    padding: 100px 22px;
    background: var(--black);
}

.section-inner {
    max-width: 1250px;
    margin: auto;
}

.section-head {
    display: flex;

    align-items: end;
    justify-content: space-between;

    gap: 20px;

    margin-bottom: 35px;
}

.section-kicker {
    color: var(--orange);

    font-size: 11px;
    font-weight: 900;

    letter-spacing: .15em;

    text-transform: uppercase;
}

.section-title {
    margin: 8px 0 0;

    font-family:
        "Playfair Display",
        serif;

    font-size:
        clamp(38px, 5vw, 64px);

    line-height: 1;
}

.section-description {
    max-width: 480px;

    color:
        rgba(255,255,255,.58);

    line-height: 1.7;
}


/* =========================================================
   SEARCH FILTER
========================================================= */

.menu-tools {
    display: flex;
    flex-wrap: wrap;

    gap: 12px;

    margin-bottom: 30px;
}

.search-box {
    flex: 1;
    min-width: 240px;

    position: relative;
}

.search-box input {
    width: 100%;

    padding:
        14px 18px 14px 46px;

    border:
        1px solid rgba(255,255,255,.1);

    border-radius: 999px;

    outline: none;

    background: var(--black2);
    color: white;
}

.search-box input::placeholder {
    color: rgba(255,255,255,.4);
}

.search-box input:focus {
    border-color: var(--orange);
}

.search-icon {
    position: absolute;

    left: 17px;
    top: 50%;

    transform:
        translateY(-50%);

    color: rgba(255,255,255,.4);
}

.filter-button {
    border:
        1px solid rgba(255,255,255,.1);

    background: var(--black2);

    color:
        rgba(255,255,255,.7);

    padding:
        12px 17px;

    border-radius: 999px;

    cursor: pointer;

    font-weight: 800;
}

.filter-button.active,
.filter-button:hover {
    background: var(--cream);
    color: var(--black);
}


/* =========================================================
   PRODUCTS
========================================================= */

.product-grid {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;
}

.product-card {
    background:
        linear-gradient(
            145deg,
            #181412,
            #F4DFC4
        );

    border:
        1px solid rgba(255,255,255,.08);

    border-radius: 24px;

    overflow: hidden;

    position: relative;

    transition:
        transform .3s,
        border-color .3s;

    animation:
        cardIn .5s ease both;
}

.product-card:hover {
    transform:
        translateY(-8px);

    border-color:
        rgba(255,255,255,.18);
}

@keyframes cardIn {

    from {
        opacity: 0;
        transform:
            translateY(15px);
    }

    to {
        opacity: 1;
        transform:
            translateY(0);
    }
}

.product-image {
    height: 210px;

    background:
        radial-gradient(
            circle at center,
            #3A2820,
            #7A4930 70%
        );

    display: grid;
    place-items: center;

    font-size: 85px;

    position: relative;
}

.product-badge {
    position: absolute;

    top: 14px;
    left: 14px;

    padding: 6px 10px;

    border-radius: 999px;

    background: var(--cream);
    color: var(--black);

    font-size: 9px;
    font-weight: 900;

    letter-spacing: .05em;
}

.product-content {
    padding: 20px;
}

.product-name {
    font-size: 18px;
    font-weight: 900;
}

.product-description {
    margin-top: 7px;

    color:
        rgba(255,255,255,.48);

    font-size: 12px;

    line-height: 1.6;

    min-height: 40px;
}

.product-bottom {
    margin-top: 18px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 10px;
}

.product-price {
    font-size: 17px;
    font-weight: 900;

    color: var(--cream);
}

.product-stock {
    font-size: 10px;
    color: rgba(255,255,255,.4);
}

.add-button {
    width: 40px;
    height: 40px;

    border: 0;

    border-radius: 50%;

    background: var(--cream);
    color: var(--black);

    display: grid;
    place-items: center;

    cursor: pointer;

    transition: .25s;
}

.add-button:hover {
    transform: scale(1.08);
}

.add-button:disabled {
    opacity: .3;
    cursor: not-allowed;
}


/* =========================================================
   PROMO
========================================================= */

.promo {
    padding:
        30px 22px 100px;

    background: var(--black);
}

.promo-card {
    max-width: 1250px;

    margin: auto;

    background:
        linear-gradient(
            135deg,
            #2B211B,
            #100D0B
        );

    border:
        1px solid rgba(255,255,255,.08);

    border-radius: 30px;

    padding: 50px;

    display: flex;

    justify-content: space-between;
    align-items: center;

    gap: 30px;

    overflow: hidden;

    position: relative;
}

.promo-card::after {
    content: "🍩";

    position: absolute;

    right: 40px;
    bottom: -80px;

    font-size: 260px;

    opacity: .08;

    transform: rotate(-20deg);
}

.promo-title {
    font-family:
        "Playfair Display",
        serif;

    font-size:
        clamp(34px, 5vw, 60px);

    line-height: 1;
}

.promo-text {
    margin-top: 15px;

    color:
        rgba(255,255,255,.6);

    max-width: 550px;
}


/* =========================================================
   TENTANG
========================================================= */

.about {
    background: var(--cream);
    color: var(--black);

    padding:
        100px 22px;
}

.about-grid {
    max-width: 1250px;

    margin: auto;

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 70px;

    align-items: center;
}

.about .section-kicker {
    color: var(--brown);
}

.about-title {
    margin-top: 10px;

    font-family:
        "Playfair Display",
        serif;

    font-size:
        clamp(42px, 5vw, 70px);

    line-height: .98;
}

.about-text {
    margin-top: 25px;

    color:
        rgba(11,9,8,.65);

    line-height: 1.9;
}

.about-box {
    display: grid;

    grid-template-columns:
        repeat(2,1fr);

    gap: 15px;
}

.about-stat {
    background: var(--black);
    color: var(--cream);

    padding: 28px;

    border-radius: 24px;
}

.about-number {
    font-size: 35px;
    font-weight: 900;
}

.about-label {
    margin-top: 5px;

    font-size: 12px;

    opacity: .55;
}


/* =========================================================
   CONTACT
========================================================= */

.contact {
    background: var(--black);

    padding:
        100px 22px;
}

.contact-grid {
    max-width: 1250px;

    margin: auto;

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 25px;
}

.contact-card {
    padding: 35px;

    border:
        1px solid rgba(255,255,255,.08);

    background: var(--black2);

    border-radius: 26px;
}

.contact-icon {
    width: 48px;
    height: 48px;

    display: grid;
    place-items: center;

    border-radius: 50%;

    background: var(--cream);
    color: var(--black);
}

.contact-card h3 {
    margin-top: 20px;

    font-size: 22px;
}

.contact-card p {
    color:
        rgba(255,255,255,.55);

    line-height: 1.7;
}


/* =========================================================
   FOOTER
========================================================= */

footer {
    background: #6B3E26;

    padding: 30px 22px;

    border-top:
        1px solid rgba(255,255,255,.06);
}

.footer-inner {
    max-width: 1250px;

    margin: auto;

    display: flex;

    justify-content: space-between;

    gap: 20px;

    color:
        rgba(255,255,255,.45);

    font-size: 12px;
}


/* =========================================================
   CART OVERLAY
========================================================= */

.cart-overlay {
    position: fixed;

    inset: 0;

    background:
        rgba(0,0,0,.65);

    backdrop-filter:
        blur(4px);

    z-index: 200;

    opacity: 0;

    visibility: hidden;

    transition: .3s;
}

.cart-overlay.show {
    opacity: 1;
    visibility: visible;
}


/* =========================================================
   CART DRAWER
========================================================= */

.cart-drawer {
    position: fixed;

    z-index: 210;

    top: 0;
    right: 0;

    height: 100vh;

    width:
        min(450px, 100%);

    background: var(--black);

    border-left:
        1px solid rgba(255,255,255,.1);

    transform:
        translateX(100%);

    transition:
        transform .35s ease;

    display: flex;

    flex-direction: column;
}

.cart-drawer.show {
    transform:
        translateX(0);
}

.cart-header {
    padding: 22px;

    border-bottom:
        1px solid rgba(255,255,255,.08);

    display: flex;

    align-items: center;
    justify-content: space-between;
}

.cart-title {
    font-size: 22px;
    font-weight: 900;
}

.close-button {
    width: 38px;
    height: 38px;

    border: 0;

    border-radius: 50%;

    background:
        rgba(255,255,255,.07);

    color: white;

    cursor: pointer;
}

.cart-items {
    flex: 1;

    overflow-y: auto;

    padding: 18px;
}

.empty-cart {
    min-height: 300px;

    display: grid;

    place-items: center;

    text-align: center;

    color:
        rgba(255,255,255,.45);
}

.cart-item {
    display: grid;

    grid-template-columns:
        60px 1fr auto;

    gap: 12px;

    padding: 15px 0;

    border-bottom:
        1px solid rgba(255,255,255,.07);
}

.cart-item-icon {
    width: 60px;
    height: 60px;

    border-radius: 16px;

    background: #F1DCC2;

    display: grid;
    place-items: center;

    font-size: 30px;
}

.cart-item-name {
    font-size: 14px;
    font-weight: 800;
}

.cart-item-price {
    margin-top: 5px;

    font-size: 12px;

    color: var(--cream);
}

.qty-controls {
    margin-top: 9px;

    display: flex;
    align-items: center;

    gap: 8px;
}

.qty-btn {
    width: 28px;
    height: 28px;

    border: 0;

    border-radius: 50%;

    background:
        rgba(255,255,255,.08);

    color: white;

    cursor: pointer;
}

.qty-number {
    min-width: 18px;

    text-align: center;

    font-size: 12px;
}

.remove-item {
    border: 0;
    background: transparent;

    color: #D77D68;

    cursor: pointer;
}

.cart-footer {
    padding: 20px;

    border-top:
        1px solid rgba(255,255,255,.08);

    background: var(--black2);
}

.cart-total-row {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 15px;
}

.cart-total-label {
    color:
        rgba(255,255,255,.5);
}

.cart-total-value {
    font-size: 22px;
    font-weight: 900;

    color: var(--cream);
}

.checkout-button {
    width: 100%;

    border: 0;

    border-radius: 999px;

    padding: 15px;

    background: var(--cream);
    color: var(--black);

    font-weight: 900;

    cursor: pointer;
}


/* =========================================================
   CHECKOUT MODAL
========================================================= */

.checkout-modal {
    position: fixed;

    inset: 0;

    z-index: 300;

    background:
        rgba(0,0,0,.75);

    backdrop-filter:
        blur(7px);

    display: none;

    align-items: center;
    justify-content: center;

    padding: 20px;
}

.checkout-modal.show {
    display: flex;
}

.checkout-box {
    width:
        min(650px, 100%);

    max-height:
        90vh;

    overflow-y: auto;

    background: var(--black);

    border:
        1px solid rgba(255,255,255,.1);

    border-radius: 28px;

    padding: 28px;

    box-shadow:
        0 30px 100px
        rgba(0,0,0,.5);
}

.checkout-header {
    display: flex;

    justify-content: space-between;
    align-items: center;

    margin-bottom: 25px;
}

.checkout-title {
    font-size: 25px;
    font-weight: 900;
}

.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;

    margin-bottom: 7px;

    font-size: 12px;
    font-weight: 800;

    color:
        rgba(255,255,255,.7);
}

.form-input {
    width: 100%;

    border:
        1px solid rgba(255,255,255,.1);

    background: var(--black2);

    color: white;

    border-radius: 14px;

    padding: 13px 15px;

    outline: none;

    font-family: inherit;
}

.form-input::placeholder {
    color:
        rgba(255,255,255,.4);
}

.form-input:focus {
    border-color: var(--orange);
}

textarea.form-input {
    min-height: 100px;

    resize: vertical;
}

.payment-grid {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 10px;
}

.payment-option {
    cursor: pointer;

    border:
        1px solid rgba(255,255,255,.1);

    background: var(--black2);

    border-radius: 15px;

    padding: 15px;

    transition: .2s;
}

.payment-option.active {
    border-color: var(--cream);

    background:
        rgba(244,232,210,.08);
}

.payment-option input {
    display: none;
}

.payment-name {
    font-weight: 900;
}

.payment-description {
    margin-top: 5px;

    color:
        rgba(255,255,255,.45);

    font-size: 11px;
}

.checkout-summary {
    margin-top: 20px;

    padding-top: 20px;

    border-top:
        1px solid rgba(255,255,255,.08);
}

.summary-row {
    display: flex;

    justify-content: space-between;

    margin-bottom: 10px;

    color:
        rgba(255,255,255,.55);
}

.summary-total {
    display: flex;

    justify-content: space-between;

    padding-top: 10px;

    border-top:
        1px solid rgba(255,255,255,.08);

    font-size: 20px;

    color: var(--cream);

    font-weight: 900;
}

.location-status {
    margin-top: 8px;

    font-size: 11px;

    color:
        rgba(255,255,255,.45);
}

.location-button {
    margin-top: 8px;

    width: 100%;

    border:
        1px solid rgba(255,255,255,.12);

    background:
        rgba(255,255,255,.05);

    color: white;

    border-radius: 12px;

    padding: 11px;

    cursor: pointer;

    font-weight: 800;

    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.submit-order {
    width: 100%;

    margin-top: 20px;

    border: 0;

    border-radius: 999px;

    padding: 15px;

    background: var(--cream);
    color: var(--black);

    font-weight: 900;

    cursor: pointer;
}

.submit-order:disabled {
    opacity: .5;
    cursor: not-allowed;
}


/* =========================================================
   TOAST
========================================================= */

.toast {
    position: fixed;

    z-index: 500;

    left: 50%;
    bottom: 25px;

    transform:
        translate(-50%, 30px);

    background: var(--black);

    color: var(--cream);

    border:
        1px solid rgba(255,255,255,.12);

    border-radius: 999px;

    padding: 13px 20px;

    font-size: 12px;

    font-weight: 800;

    opacity: 0;

    pointer-events: none;

    transition: .3s;
}

.toast.show {
    opacity: 1;

    transform:
        translate(-50%, 0);
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width: 1000px) {

    .nav-links {
        display: none;
    }

    .mobile-menu-button {
        display: grid;
    }

    .nav-inner {
        gap: 10px;
    }

    .hero-container {
        grid-template-columns: 1fr;

        text-align: center;

        padding-top: 80px;
    }

    .hero-description {
        margin: auto;
    }

    .hero-buttons {
        justify-content: center;
    }

    .hero-donut {
        width: 330px;
    }

    .product-grid {
        grid-template-columns:
            repeat(2,1fr);
    }

    .trust-container {
        grid-template-columns:
            repeat(2,1fr);
    }

    .about-grid,
    .contact-grid {
        grid-template-columns: 1fr;
    }
}


@media(max-width: 600px) {

    .nav-inner {
        padding:
            12px 15px;
    }

    .logo-sub {
        display: none;
    }

    .logo-title {
        font-size: 14px;
    }

    .logo-icon {
        width: 38px;
        height: 38px;
        font-size: 20px;
    }

    .cart-button {
        padding: 9px 12px;
        font-size: 0;
        gap: 5px;
    }

    .cart-button svg {
        width: 18px;
        height: 18px;
    }

    .cart-count {
        font-size: 10px;
        min-width: 20px;
        height: 20px;
    }

    .mobile-menu {
        top: 68px;
    }

    .running-text {
        margin-top: 65px;
    }

    .hero {
        min-height: auto;
    }

    .hero-container {
        min-height: auto;

        padding:
            75px 18px;
    }

    .hero-title {
        font-size: 64px;
    }

    .hero-description {
        font-size: 14px;
    }

    .hero-donut {
        width: 270px;
    }

    .section,
    .about,
    .contact {
        padding:
            70px 18px;
    }

    .section-head {
        display: block;
    }

    .section-description {
        margin-top: 15px;
    }

    .menu-tools {
        gap: 8px;
    }

    .search-box {
        min-width: 100%;
    }

    .filter-button {
        flex: 1;
    }

    .product-grid {
        grid-template-columns: 1fr;
    }

    .trust-container {
        grid-template-columns: 1fr;
    }

    .promo {
        padding:
            20px 18px 70px;
    }

    .promo-card {
        padding: 30px 24px;
    }

    .about-box {
        grid-template-columns: 1fr 1fr;
    }

    .contact-grid {
        gap: 15px;
    }

    .payment-grid {
        grid-template-columns: 1fr;
    }

    .footer-inner {
        flex-direction: column;
    }

    .checkout-box {
        padding: 22px 18px;
        border-radius: 22px;
    }
}


/* =========================================================
   EXTRA MOBILE SAFETY
========================================================= */

@media(max-width: 380px) {

    .logo-title {
        font-size: 12px;
    }

    .logo-icon {
        width: 34px;
        height: 34px;
    }

    .cart-button {
        padding: 8px 9px;
    }

    .mobile-menu-button {
        width: 38px;
        height: 38px;
    }

    .hero-title {
        font-size: 55px;
    }

    .about-box {
        grid-template-columns: 1fr;
    }
}

/* =========================================================
   DONAT GEMBUL - FINAL BAKERY THEME OVERRIDE
========================================================= */\nhtml, body { background:#FFF3DC !important; color:#5A321F !important; }\nbody { background-image:linear-gradient(135deg,#FFF9ED 0%,#FFF3DC 45%,#F6DEC0 100%) !important; }\n.site-background { display:block !important; position:fixed !important; inset:0 !important; z-index:-1 !important; background:\n radial-gradient(circle at 12% 18%,rgba(242,198,109,.35),transparent 26%),\n radial-gradient(circle at 88% 24%,rgba(233,130,130,.22),transparent 25%),\n radial-gradient(circle at 50% 90%,rgba(139,170,114,.16),transparent 28%),\n #FFF3DC !important; }\n.main-nav, header { background:rgba(255,249,237,.96) !important; color:#5A321F !important; border-color:rgba(107,62,38,.14) !important; }\n.hero { background:linear-gradient(135deg,#FFF9ED,#F8E3C3,#F1D3B3) !important; color:#5A321F !important; }\n.hero-title,.hero-description,.section-title,.section-description,.product-name,.product-price,.form-label,.checkout-title { color:#5A321F !important; }\n.trust { background:#6B3E26 !important; color:#FFF9ED !important; }\n.product-card,.contact-card,.about-stat { background:#FFF9ED !important; color:#5A321F !important; border-color:rgba(107,62,38,.14) !important; box-shadow:0 14px 35px rgba(90,50,31,.08) !important; }\n.product-image { background:linear-gradient(135deg,#FFE8C7,#EBC99E) !important; }\n.promo-card { background:linear-gradient(135deg,#6B3E26,#C9824B) !important; color:#FFF9ED !important; }\nfooter { background:#5A321F !important; color:#FFF9ED !important; }\n.cart-overlay,.checkout-modal { background:rgba(90,50,31,.45) !important; }\n.cart-drawer,.checkout-box { background:#FFF9ED !important; color:#5A321F !important; border-color:rgba(107,62,38,.15) !important; }\n.form-input,.payment-option { background:#FFF3DC !important; color:#5A321F !important; border-color:rgba(107,62,38,.16) !important; }\n.checkout-button,.submit-order { background:#5A321F !important; color:#FFF9ED !important; }\n.search-box,.filter-button { background:#FFF9ED !important; color:#6B3E26 !important; border-color:rgba(107,62,38,.16) !important; }\n.filter-button.active,.filter-button:hover { background:#5A321F !important; color:#FFF9ED !important; }\n.nav-links a { color:#6B3E26 !important; border-radius:999px; transition:.2s ease !important; }\n.nav-links a:hover { background:#F4DFC4 !important; color:#5A321F !important; transform:translateY(-2px); }\n.cart-button { background:#5A321F !important; color:#FFF9ED !important; }\n

/* =========================================================
   FINAL BAKERY GRADIENT THEME + CART FIXES
========================================================= */
:root{
    --cream:#FFF3DC;
    --cream-light:#FFF9ED;
    --black:#5A321F;
    --black2:#7A4930;
    --brown:#6B3E26;
    --orange:#C9824B;
    --white:#FFF9ED;
    --strawberry:#E98282;
    --strawberry-light:#FFD1D1;
    --matcha:#8BAA72;
    --cheese:#F2C66D;
}

body{
    background:linear-gradient(135deg,#FFF9ED 0%,#FFF3DC 38%,#F8E3C3 68%,#F4D0B0 100%) !important;
    color:#5A321F !important;
}

.site-background{
    background:
        radial-gradient(circle at 12% 12%,rgba(255,255,255,.9) 0 7%,transparent 28%),
        radial-gradient(circle at 88% 22%,rgba(233,130,130,.16) 0 8%,transparent 28%),
        radial-gradient(circle at 50% 90%,rgba(201,130,75,.18) 0 8%,transparent 30%),
        linear-gradient(135deg,#FFF9ED 0%,#FFF3DC 38%,#F8E3C3 68%,#F4D0B0 100%) !important;
}

.main-nav{
    background:rgba(255,249,237,.88) !important;
    border-bottom:1px solid rgba(107,62,38,.14) !important;
    box-shadow:0 8px 30px rgba(107,62,38,.08);
}

.logo,.nav-links a,.mobile-menu a,.mobile-menu button{
    color:#5A321F !important;
}

.nav-links a:hover,.nav-links a.active{
    background:rgba(201,130,75,.13) !important;
    color:#6B3E26 !important;
}

.cart-button{
    background:linear-gradient(135deg,#6B3E26,#9A5B39) !important;
    color:#FFF9ED !important;
    border:1px solid rgba(107,62,38,.25) !important;
    box-shadow:0 8px 20px rgba(107,62,38,.18);
}

.cart-count{
    background:#E98282 !important;
    color:#FFF9ED !important;
}

.mobile-menu{
    background:rgba(255,249,237,.97) !important;
    border-color:rgba(107,62,38,.12) !important;
    box-shadow:0 20px 50px rgba(107,62,38,.15);
}

.mobile-menu a:hover,.mobile-menu button:hover{
    background:#FFF3DC !important;
}

.hero,.section,.promo-section,footer{
    color:#5A321F;
}

.hero-description,.product-description,.product-stock,.section-description,
.footer-inner,.form-label,.payment-description,.location-status,.cart-total-label{
    color:#7A4930 !important;
}

.btn-primary,.checkout-button,.submit-order{
    background:linear-gradient(135deg,#6B3E26,#A7643F) !important;
    color:#FFF9ED !important;
    box-shadow:0 10px 24px rgba(107,62,38,.18);
}

.btn-secondary{
    background:rgba(255,249,237,.78) !important;
    color:#6B3E26 !important;
    border:1px solid rgba(107,62,38,.18) !important;
}

.filter-button,.search-box,.product-card,.promo-card,.about-card,.contact-card{
    background:rgba(255,249,237,.76) !important;
    color:#5A321F !important;
    border-color:rgba(107,62,38,.12) !important;
    box-shadow:0 16px 40px rgba(107,62,38,.08);
}

.filter-button.active,.filter-button:hover{
    background:#6B3E26 !important;
    color:#FFF9ED !important;
}

.product-image{
    background:linear-gradient(135deg,#FFF0D4,#FFDCC8) !important;
}

.product-badge{background:#E98282 !important;color:#FFF9ED !important;}
.product-price{color:#6B3E26 !important;}
.add-button{background:#6B3E26 !important;color:#FFF9ED !important;}

.running-text{
    background:linear-gradient(90deg,#6B3E26,#9A5B39,#C9824B) !important;
    color:#FFF9ED !important;
}

/* Cart: intentionally light and stable */
.cart-overlay{
    background:rgba(90,50,31,.30) !important;
    backdrop-filter:blur(5px);
}

.cart-drawer{
    background:linear-gradient(180deg,#FFF9ED 0%,#FFF3DC 55%,#F8E3C3 100%) !important;
    color:#5A321F !important;
    border-left:1px solid rgba(107,62,38,.15) !important;
    box-shadow:-20px 0 60px rgba(107,62,38,.18);
}

.cart-header{
    background:rgba(255,249,237,.82) !important;
    border-bottom:1px solid rgba(107,62,38,.12) !important;
}

.cart-title{color:#5A321F !important;}

.close-button{
    background:#F4DFC5 !important;
    color:#6B3E26 !important;
    border:1px solid rgba(107,62,38,.12) !important;
}

.cart-items{background:transparent !important;}

.empty-cart{color:#8A6047 !important;}
.empty-cart div[style*="color:#fff8ed"]{color:#6B3E26 !important;}

.cart-item{
    border-bottom:1px solid rgba(107,62,38,.11) !important;
}

.cart-item-icon{
    background:linear-gradient(135deg,#FFE8C6,#FFD8C4) !important;
    box-shadow:inset 0 0 0 1px rgba(107,62,38,.08);
}

.cart-item-name{color:#5A321F !important;}
.cart-item-price{color:#C9824B !important;}

.qty-btn{
    background:#F0D8BC !important;
    color:#6B3E26 !important;
    border:1px solid rgba(107,62,38,.10) !important;
}
.qty-btn:hover{background:#E9C59F !important;}
.qty-number{color:#5A321F !important;font-weight:800;}
.remove-item{color:#C86D65 !important;}

.cart-footer{
    background:rgba(255,249,237,.92) !important;
    border-top:1px solid rgba(107,62,38,.13) !important;
    box-shadow:0 -10px 30px rgba(107,62,38,.06);
}

.cart-total-label{color:#8A6047 !important;}
.cart-total-value{color:#6B3E26 !important;}

.checkout-button:disabled{
    opacity:.55;
    cursor:not-allowed;
}

/* Checkout: same bakery palette */
.checkout-modal{
    background:rgba(90,50,31,.34) !important;
}

.checkout-box{
    background:linear-gradient(145deg,#FFF9ED,#FFF3DC 65%,#F8E3C3) !important;
    color:#5A321F !important;
    border:1px solid rgba(107,62,38,.14) !important;
    box-shadow:0 30px 100px rgba(107,62,38,.22) !important;
}

.checkout-title,.summary-total,.summary-total span:last-child{color:#5A321F !important;}
.form-input,.payment-option{
    background:rgba(255,255,255,.58) !important;
    color:#5A321F !important;
    border-color:rgba(107,62,38,.16) !important;
}
.form-input::placeholder{color:#A27C63 !important;}
.payment-option.active{
    border-color:#C9824B !important;
    background:#FFF0D8 !important;
}
.payment-name,.summary-row{color:#6B3E26 !important;}
.location-button{background:#F0D8BC !important;color:#6B3E26 !important;}

@media(max-width:700px){
    .cart-drawer{width:100%;}
    .cart-header{padding:18px;}
    .cart-items{padding:14px;}
    .cart-footer{padding:16px;}
    .cart-item{grid-template-columns:54px minmax(0,1fr) auto;gap:10px;}
    .cart-item-icon{width:54px;height:54px;font-size:27px;}
}


/* =========================================================
   FINAL POLISH — PREMIUM DONUT BAKERY
========================================================= */
body{
  min-height:100vh;
  background:
    radial-gradient(circle at 8% 10%,rgba(255,255,255,.92),transparent 23%),
    radial-gradient(circle at 92% 14%,rgba(233,130,130,.14),transparent 25%),
    radial-gradient(circle at 18% 82%,rgba(242,198,109,.18),transparent 24%),
    linear-gradient(135deg,#FFF9ED 0%,#FFF3DC 40%,#F8E3C3 70%,#F4D0B0 100%) !important;
}
.site-background{opacity:1 !important;}
.hero{
  background:
    radial-gradient(circle at 78% 28%,rgba(233,130,130,.18),transparent 25%),
    radial-gradient(circle at 18% 75%,rgba(242,198,109,.18),transparent 24%),
    linear-gradient(135deg,#FFF9ED 0%,#FFF3DC 48%,#F3D5B5 100%) !important;
}
.section{
  background:linear-gradient(135deg,rgba(255,249,237,.88),rgba(248,227,195,.78)) !important;
}
.trust{
  background:linear-gradient(90deg,#6B3E26,#8C5436,#C9824B) !important;
}
.promo{
  background:linear-gradient(135deg,rgba(255,243,220,.72),rgba(244,208,176,.7)) !important;
}
.contact{
  background:linear-gradient(135deg,#FFF3DC,#F4D0B0) !important;
}
.about{
  background:linear-gradient(135deg,#FFF9ED,#FFF3DC,#F8E3C3) !important;
}
footer{
  background:linear-gradient(90deg,#5A321F,#7A4930,#9A5B39) !important;
}
.section-title,.about-title,.promo-title,.hero-title{text-shadow:0 8px 25px rgba(107,62,38,.08);}
.product-card{backdrop-filter:blur(10px);}
.product-card:hover{box-shadow:0 22px 45px rgba(107,62,38,.15) !important;}
.add-button{box-shadow:0 8px 18px rgba(107,62,38,.18);}

/* Navbar interaction */
.nav-links a{position:relative;padding:9px 12px;}
.nav-links a::after{content:"";position:absolute;left:12px;right:12px;bottom:4px;height:2px;border-radius:99px;background:#C9824B;transform:scaleX(0);transform-origin:center;transition:.25s ease;}
.nav-links a:hover::after,.nav-links a.active::after{transform:scaleX(1);}
.cart-button.bump{animation:cartBump .42s ease;}
@keyframes cartBump{0%,100%{transform:scale(1)}35%{transform:scale(1.08) rotate(-2deg)}70%{transform:scale(.97) rotate(2deg)}}

/* Better cart UX */
.cart-drawer{width:min(470px,100vw) !important;}
.cart-items{scrollbar-width:thin;scrollbar-color:#C9824B transparent;}
.cart-item{animation:cartItemIn .25s ease both;}
@keyframes cartItemIn{from{opacity:0;transform:translateX(12px)}to{opacity:1;transform:none}}
.qty-btn{transition:.18s ease;}
.qty-btn:active{transform:scale(.88);}
.remove-item{transition:.18s ease;}
.remove-item:hover{transform:scale(1.12);}
.checkout-button{transition:.22s ease;}
.checkout-button:hover:not(:disabled),.submit-order:hover:not(:disabled){transform:translateY(-2px);filter:brightness(1.05);}
.checkout-button:active:not(:disabled),.submit-order:active:not(:disabled){transform:translateY(0) scale(.99);}

/* Cart and checkout stay above every decorative layer */
.cart-overlay{z-index:200 !important;}
.cart-drawer{z-index:210 !important;}
.checkout-modal{z-index:300 !important;}
.toast{z-index:500 !important;}

/* Prevent accidental page scroll while drawer is open */
body.cart-open,body.checkout-open{overflow:hidden !important;}

@media(max-width:600px){
  .hero-title{font-size:clamp(56px,18vw,76px);}
  .section-title{font-size:clamp(38px,12vw,54px);}
  .cart-drawer{border-radius:22px 0 0 22px;}
  .checkout-box{max-height:92vh;}
}

</style>

</head>


<body>

<!-- BACKGROUND -->
<div class="site-background"></div>

<!-- DONUT RAIN -->
<div id="donutRain"></div>


<!-- =====================================================
     NAVBAR
===================================================== -->

<header class="main-nav">

    <div class="nav-inner">

        <a
            href="#beranda"
            class="logo"
            onclick="closeMobileMenu()"
        >

            <div class="logo-icon">
                🍩
            </div>

            <div>

                <div class="logo-title">
                    DONAT GEMBUL
                </div>

                <div class="logo-sub">
                    PEKANBARU
                </div>

            </div>

        </a>


        <!-- DESKTOP NAV -->

        <nav class="nav-links">

            <a href="#beranda">
                Beranda
            </a>

            <a href="#menu">
                Menu
            </a>

            <a href="#tentang">
                Tentang
            </a>

            <a href="#kontak">
                Kontak
            </a>

        </nav>


        <!-- CART -->

        <button
            type="button"
            class="cart-button"
            onclick="openCart()"
        >

            <i
                data-lucide="shopping-bag"
                width="16"
                height="16"
            ></i>

            <span class="cart-button-text">
                Keranjang
            </span>

            <span
                id="cartCount"
                class="cart-count"
            >
                0
            </span>

        </button>


        <!-- MOBILE BUTTON -->

        <button
            type="button"
            class="mobile-menu-button"
            id="mobileMenuButton"
            onclick="toggleMobileMenu()"
            aria-label="Buka menu"
        >

            <i
                data-lucide="menu"
                id="mobileMenuIcon"
                width="23"
                height="23"
            ></i>

        </button>

    </div>

</header>


<!-- =====================================================
     MOBILE NAVIGATION
===================================================== -->

<div
    id="mobileMenu"
    class="mobile-menu"
>

    <a
        href="#beranda"
        onclick="closeMobileMenu()"
    >

        <i
            data-lucide="home"
            width="17"
        ></i>

        Beranda

    </a>


    <a
        href="#menu"
        onclick="closeMobileMenu()"
    >

        <i
            data-lucide="utensils"
            width="17"
        ></i>

        Menu

    </a>


    <a
        href="#tentang"
        onclick="closeMobileMenu()"
    >

        <i
            data-lucide="heart"
            width="17"
        ></i>

        Tentang

    </a>


    <a
        href="#kontak"
        onclick="closeMobileMenu()"
    >

        <i
            data-lucide="map-pin"
            width="17"
        ></i>

        Kontak

    </a>


    <button
        type="button"
        onclick="openCart(); closeMobileMenu();"
    >

        <i
            data-lucide="shopping-bag"
            width="17"
        ></i>

        Keranjang

    </button>

</div>


<!-- =====================================================
     RUNNING TEXT
===================================================== -->

<div class="running-text">

    <div class="running-track">

        <div class="running-item">
            🍩 DONAT GEMBUL PEKANBARU
        </div>

        <div class="running-item">
            ✨ FRESH SETIAP HARI
        </div>

        <div class="running-item">
            🚀 BISA DIANTAR KE LOKASI KAMU
        </div>

        <div class="running-item">
            🍫 COKELAT • 🍓 STRAWBERRY • 🍵 MATCHA
        </div>

        <div class="running-item">
            ❤️ DIBUAT DENGAN CINTA
        </div>

        <div class="running-item">
            📲 PESAN SEKARANG VIA WHATSAPP
        </div>

        <div class="running-item">
            🍩 DONAT GEMBUL PEKANBARU
        </div>

        <div class="running-item">
            ✨ FRESH SETIAP HARI
        </div>

        <div class="running-item">
            🚀 BISA DIANTAR KE LOKASI KAMU
        </div>

        <div class="running-item">
            🍫 COKELAT • 🍓 STRAWBERRY • 🍵 MATCHA
        </div>

        <div class="running-item">
            ❤️ DIBUAT DENGAN CINTA
        </div>

        <div class="running-item">
            📲 PESAN SEKARANG VIA WHATSAPP
        </div>

    </div>

</div>


<!-- =====================================================
     HERO
===================================================== -->

<section
    id="beranda"
    class="hero"
>

    <div class="hero-container">

        <div>

            <div class="hero-kicker">
                <span>●</span>
                Fresh Every Day
            </div>

            <h1 class="hero-title">

                DONAT
                <br>

                <span>GEMBUL.</span>

            </h1>

            <p class="hero-description">

                Donat lembut, fresh dan dibuat dengan
                sepenuh hati. Pilih rasa favoritmu
                dan pesan langsung dari Donat Gembul
                Pekanbaru.

            </p>

            <div class="hero-buttons">

                <a
                    href="#menu"
                    class="btn-primary"
                >

                    Lihat Menu

                    <i
                        data-lucide="arrow-down"
                        width="17"
                    ></i>

                </a>


                <button
                    type="button"
                    onclick="openCart()"
                    class="btn-secondary"
                >

                    Pesan Sekarang

                    <i
                        data-lucide="shopping-bag"
                        width="17"
                    ></i>

                </button>

            </div>

        </div>


        <div class="hero-donut-wrap">

            <div class="hero-donut"></div>

        </div>

    </div>

</section>


<!-- =====================================================
     TRUST
===================================================== -->

<section class="trust">

    <div class="trust-container">

        <div class="trust-item">

            <div class="trust-icon">
                <i data-lucide="heart"></i>
            </div>

            <div>

                <div class="trust-title">
                    Dibuat Fresh
                </div>

                <div class="trust-text">
                    Setiap hari
                </div>

            </div>

        </div>


        <div class="trust-item">

            <div class="trust-icon">
                <i data-lucide="truck"></i>
            </div>

            <div>

                <div class="trust-title">
                    Bisa Diantar
                </div>

                <div class="trust-text">
                    Area Pekanbaru
                </div>

            </div>

        </div>


        <div class="trust-item">

            <div class="trust-icon">
                <i data-lucide="sparkles"></i>
            </div>

            <div>

                <div class="trust-title">
                    Banyak Rasa
                </div>

                <div class="trust-text">
                    Pilihan favorit
                </div>

            </div>

        </div>


        <div class="trust-item">

            <div class="trust-icon">
                <i data-lucide="message-circle"></i>
            </div>

            <div>

                <div class="trust-title">
                    Order Mudah
                </div>

                <div class="trust-text">
                    Via WhatsApp
                </div>

            </div>

        </div>

    </div>

</section>


<!-- =====================================================
     MENU
===================================================== -->

<section
    id="menu"
    class="section"
>

    <div class="section-inner">

        <div class="section-head">

            <div>

                <div class="section-kicker">
                    MENU DONAT
                </div>

                <h2 class="section-title">
                    Pilih Favoritmu.
                </h2>

            </div>

            <p class="section-description">

                Semua donat dibuat fresh dan siap
                menemani hari kamu.

            </p>

        </div>


        <div class="menu-tools">

            <div class="search-box">

                <i
                    data-lucide="search"
                    class="search-icon"
                    width="17"
                ></i>

                <input
                    type="text"
                    id="searchInput"
                    placeholder="Cari donat..."
                    oninput="filterProducts()"
                >

            </div>


            <button
                type="button"
                class="filter-button active"
                data-filter="all"
                onclick="setFilter('all', this)"
            >
                Semua
            </button>

            <button
                type="button"
                class="filter-button"
                data-filter="featured"
                onclick="setFilter('featured', this)"
            >
                Favorit
            </button>

            <button
                type="button"
                class="filter-button"
                data-filter="available"
                onclick="setFilter('available', this)"
            >
                Tersedia
            </button>

        </div>


        <div
            id="productGrid"
            class="product-grid"
        ></div>

    </div>

</section>


<!-- =====================================================
     PROMO
===================================================== -->

<section class="promo">

    <div class="promo-card">

        <div>

            <div class="section-kicker">
                SPECIAL TODAY
            </div>

            <h2 class="promo-title">
                Lagi pengen<br>
                yang manis?
            </h2>

            <p class="promo-text">

                Pilih donat favorit kamu,
                masukkan ke keranjang,
                lalu checkout dengan mudah.

            </p>

            <br>

            <a
                href="#menu"
                class="btn-primary"
            >

                Pesan Sekarang

                <i
                    data-lucide="arrow-right"
                    width="17"
                ></i>

            </a>

        </div>

    </div>

</section>


<!-- =====================================================
     TENTANG
===================================================== -->

<section
    id="tentang"
    class="about"
>

    <div class="about-grid">

        <div>

            <div class="section-kicker">
                TENTANG KAMI
            </div>

            <h2 class="about-title">
                Kecil bentuknya,
                besar rasanya.
            </h2>

            <p class="about-text">

                Donat Gembul hadir untuk memberikan
                pengalaman menikmati donat yang
                lembut, fresh dan penuh rasa.

                <br><br>

                Dari rasa klasik sampai rasa kekinian,
                semuanya dibuat dengan perhatian agar
                setiap gigitan terasa spesial.

            </p>

        </div>


        <div class="about-box">

            <div class="about-stat">

                <div class="about-number">
                    8+
                </div>

                <div class="about-label">
                    Varian Donat
                </div>

            </div>


            <div class="about-stat">

                <div class="about-number">
                    100%
                </div>

                <div class="about-label">
                    Fresh
                </div>

            </div>


            <div class="about-stat">

                <div class="about-number">
                    🍩
                </div>

                <div class="about-label">
                    Dibuat dengan cinta
                </div>

            </div>


            <div class="about-stat">

                <div class="about-number">
                    🚀
                </div>

                <div class="about-label">
                    Siap diantar
                </div>

            </div>

        </div>

    </div>

</section>


<!-- =====================================================
     KONTAK
===================================================== -->

<section
    id="kontak"
    class="contact"
>

    <div class="contact-grid">

        <div class="contact-card">

            <div class="contact-icon">

                <i data-lucide="message-circle"></i>

            </div>

            <h3>
                WhatsApp
            </h3>

            <p>
                Pesan donat langsung melalui WhatsApp
                dan kami akan membantu proses pesananmu.
            </p>

            <a
                href="https://wa.me/<?php echo $waNumber; ?>"
                target="_blank"
                rel="noopener"
                class="btn-primary"
            >

                Chat Sekarang

            </a>

        </div>


        <div class="contact-card">

            <div class="contact-icon">

                <i data-lucide="map-pin"></i>

            </div>

            <h3>
                Pekanbaru
            </h3>

            <p>
                Donat Gembul melayani pengantaran
                di area Pekanbaru dan sekitarnya.
            </p>

        </div>

    </div>

</section>


<!-- =====================================================
     FOOTER
===================================================== -->

<footer>

    <div class="footer-inner">

        <div>
            © <?php echo date('Y'); ?>
            Donat Gembul Pekanbaru.
        </div>

        <div>
            Fresh • Gembul • Enak 🍩
        </div>

    </div>

</footer>


<!-- =====================================================
     CART OVERLAY
===================================================== -->

<div
    id="cartOverlay"
    class="cart-overlay"
    onclick="closeCart()"
></div>


<!-- =====================================================
     CART DRAWER
===================================================== -->

<aside
    id="cartDrawer"
    class="cart-drawer"
>

    <div class="cart-header">

        <div class="cart-title">
            Keranjang
        </div>

        <button
            type="button"
            class="close-button"
            onclick="closeCart()"
        >

            <i data-lucide="x"></i>

        </button>

    </div>


    <div
        id="cartItems"
        class="cart-items"
    ></div>


    <div class="cart-footer">

        <div class="cart-total-row">

            <span class="cart-total-label">
                Subtotal
            </span>

            <span
                id="cartSubtotal"
                class="cart-total-value"
            >
                Rp 0
            </span>

        </div>


        <button
            type="button"
            id="checkoutButton"
            class="checkout-button"
            onclick="openCheckout()"
        >

            Lanjut Checkout

        </button>

    </div>

</aside>


<!-- =====================================================
     CHECKOUT MODAL
===================================================== -->

<div
    id="checkoutModal"
    class="checkout-modal"
>

    <div class="checkout-box">

        <div class="checkout-header">

            <div class="checkout-title">
                Checkout
            </div>

            <button
                type="button"
                class="close-button"
                onclick="closeCheckout()"
            >

                <i data-lucide="x"></i>

            </button>

        </div>


        <form
            id="checkoutForm"
            onsubmit="submitOrder(event)"
        >

            <div class="form-group">

                <label class="form-label">
                    Nama
                </label>

                <input
                    type="text"
                    id="customerName"
                    name="customer_name"
                    class="form-input"
                    placeholder="Nama kamu"
                    required
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Nomor WhatsApp
                </label>

                <input
                    type="tel"
                    id="customerWhatsapp"
                    name="whatsapp"
                    class="form-input"
                    placeholder="08xxxxxxxxxx"
                    required
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Alamat Pengantaran
                </label>

                <textarea
                    id="customerAddress"
                    name="address"
                    class="form-input"
                    placeholder="Masukkan alamat lengkap..."
                    required
                ></textarea>

            </div>


            <div class="form-group">

                <label class="form-label">
                    Lokasi Pengantaran
                </label>

                <button
                    type="button"
                    class="location-button"
                    onclick="getLocation()"
                >

                    <i
                        data-lucide="map-pin"
                        width="15"
                    ></i>

                    Deteksi Lokasi Saya

                </button>

                <div
                    id="locationStatus"
                    class="location-status"
                >
                    Lokasi belum terdeteksi.
                </div>

            </div>


            <input
                type="hidden"
                id="latitude"
                name="latitude"
            >

            <input
                type="hidden"
                id="longitude"
                name="longitude"
            >


            <div class="form-group">

                <label class="form-label">
                    Metode Pembayaran
                </label>


                <div class="payment-grid">

                    <label
                        class="payment-option active"
                        id="paymentQRIS"
                    >

                        <input
                            type="radio"
                            name="payment_method"
                            value="QRIS"
                            checked
                            onchange="updatePaymentUI()"
                        >

                        <div class="payment-name">
                            QRIS
                        </div>

                        <div class="payment-description">
                            Pembayaran digital
                        </div>

                    </label>


                    <label
                        class="payment-option"
                        id="paymentCOD"
                    >

                        <input
                            type="radio"
                            name="payment_method"
                            value="COD"
                            onchange="updatePaymentUI()"
                        >

                        <div class="payment-name">
                            COD
                        </div>

                        <div class="payment-description">
                            Bayar saat menerima
                        </div>

                    </label>

                </div>

            </div>


            <div class="checkout-summary">

                <div class="summary-row">

                    <span>
                        Subtotal
                    </span>

                    <span id="checkoutSubtotal">
                        Rp 0
                    </span>

                </div>


                <div class="summary-row">

                    <span>
                        Ongkir
                    </span>

                    <span id="checkoutShipping">
                        Rp 0
                    </span>

                </div>


                <div class="summary-total">

                    <span>
                        Total
                    </span>

                    <span id="checkoutTotal">
                        Rp 0
                    </span>

                </div>

            </div>


            <button
                type="submit"
                id="submitOrderButton"
                class="submit-order"
            >

                Pesan Sekarang via WhatsApp

            </button>

        </form>

    </div>

</div>


<!-- TOAST -->

<div
    id="toast"
    class="toast"
>
    Berhasil
</div>


<script>

/* =========================================================
   DATA
========================================================= */

const products =
    <?php echo $productsJson ?: '[]'; ?>;

const WA_NUMBER =
    "<?php echo $waNumber; ?>";

const STORE_LAT =
    <?php echo json_encode($storeLat); ?>;

const STORE_LNG =
    <?php echo json_encode($storeLng); ?>;

const PRICE_PER_KM =
    <?php echo json_encode($pricePerKm); ?>;

const MAX_DISTANCE =
    <?php echo json_encode($maxDistanceKm); ?>;


/* =========================================================
   CART
========================================================= */

const CART_KEY = "gembul_cart";

let cart = [];

let activeFilter = "all";


/* =========================================================
   MOBILE MENU
========================================================= */

function toggleMobileMenu() {

    const menu =
        document.getElementById(
            "mobileMenu"
        );

    const button =
        document.getElementById(
            "mobileMenuButton"
        );

    if (!menu) {
        return;
    }

    const isOpen =
        menu.classList.toggle("show");

    if (button) {
        button.setAttribute(
            "aria-expanded",
            isOpen ? "true" : "false"
        );
    }

    if (window.lucide) {
        lucide.createIcons();
    }
}


function closeMobileMenu() {

    const menu =
        document.getElementById(
            "mobileMenu"
        );

    const button =
        document.getElementById(
            "mobileMenuButton"
        );

    if (menu) {
        menu.classList.remove("show");
    }

    if (button) {
        button.setAttribute(
            "aria-expanded",
            "false"
        );
    }

}


/* =========================================================
   FORMAT RUPIAH
========================================================= */

function rupiah(number) {

    return new Intl.NumberFormat(
        "id-ID",
        {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0
        }
    ).format(number);

}


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(text) {

    const div =
        document.createElement("div");

    div.textContent = text;

    return div.innerHTML;

}


/* =========================================================
   LOAD CART
========================================================= */

function loadCart() {
    try {
        const saved = localStorage.getItem(CART_KEY);
        const parsed = saved ? JSON.parse(saved) : [];
        cart = Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        cart = [];
    }
}

function saveCart() {
    try {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
    } catch (error) {
        console.warn("Cart tidak dapat disimpan.", error);
    }
}

function getProduct(id) {
    return products.find(product => Number(product.id) === Number(id));
}

function cleanCart() {
    const cleaned = [];
    cart.forEach(item => {
        const product = getProduct(item.id);
        const stock = product ? Number(product.stock) : 0;
        let qty = Number(item.qty);
        if (!product || stock <= 0 || !Number.isFinite(qty) || qty <= 0) return;
        qty = Math.min(Math.floor(qty), stock);
        if (qty > 0) cleaned.push({ id: Number(product.id), qty });
    });
    cart = cleaned;
    saveCart();
}

function updateCartCount() {
    const count = cart.reduce((total, item) => total + Number(item.qty || 0), 0);
    const el = document.getElementById("cartCount");
    if (el) el.textContent = count;
    const checkoutButton = document.getElementById("checkoutButton");
    if (checkoutButton) checkoutButton.disabled = count === 0;
}

function getCartSubtotal() {
    return cart.reduce((total, item) => {
        const product = getProduct(item.id);
        if (!product) return total;
        return total + (Number(product.price) * Number(item.qty));
    }, 0);
}

function addToCart(id) {
    const product = getProduct(id);
    if (!product) return;
    const stock = Number(product.stock);
    if (stock <= 0) {
        showToast("Stok produk sudah habis.");
        return;
    }
    const existing = cart.find(item => Number(item.id) === Number(id));
    if (existing) {
        if (Number(existing.qty) >= stock) {
            showToast(`Stok ${product.name} hanya ${stock}.`);
            return;
        }
        existing.qty = Number(existing.qty) + 1;
    } else {
        cart.push({ id: Number(product.id), qty: 1 });
    }
    cleanCart();
    renderCart();
    updateCartCount();
    showToast(`${product.name} ditambahkan ke keranjang 🍩`);
}

function changeQty(id, change) {
    const item = cart.find(item => Number(item.id) === Number(id));
    if (!item) return;
    const product = getProduct(id);
    if (!product) {
        removeFromCart(id);
        return;
    }
    const stock = Number(product.stock);
    const newQty = Number(item.qty) + Number(change);
    if (newQty <= 0) {
        removeFromCart(id);
        return;
    }
    if (newQty > stock) {
        showToast(`Stok ${product.name} hanya ${stock}.`);
        return;
    }
    item.qty = newQty;
    saveCart();
    renderCart();
    updateCartCount();
    updateCheckoutTotal();
}

function removeFromCart(id) {
    cart = cart.filter(item => Number(item.id) !== Number(id));
    saveCart();
    renderCart();
    updateCartCount();
    updateCheckoutTotal();
    showToast("Produk dihapus dari keranjang.");
}

/* =========================================================
   RENDER PRODUCTS
========================================================= */

function renderProducts() {

    const grid =
        document.getElementById(
            "productGrid"
        );

    if (!grid) {
        return;
    }

    const search =
        (
            document.getElementById(
                "searchInput"
            )?.value || ""
        )
        .toLowerCase()
        .trim();

    let filtered =
        products.filter(product => {

            const name =
                String(product.name)
                .toLowerCase();

            const description =
                String(
                    product.description || ""
                )
                .toLowerCase();

            const matchSearch =
                name.includes(search) ||
                description.includes(search);

            let matchFilter = true;

            if (
                activeFilter ===
                "featured"
            ) {

                matchFilter =
                    Number(product.featured) === 1;

            }

            if (
                activeFilter ===
                "available"
            ) {

                matchFilter =
                    Number(product.stock) > 0;

            }

            return matchSearch &&
                matchFilter;

        });


    if (!filtered.length) {

        grid.innerHTML = `

            <div style="
                grid-column:1/-1;
                text-align:center;
                padding:60px 20px;
                color:rgba(255,255,255,.45);
            ">

                <div style="
                    font-size:50px;
                    margin-bottom:15px;
                ">
                    🍩
                </div>

                Produk tidak ditemukan.

            </div>

        `;

        return;
    }


    grid.innerHTML =
        filtered.map(product => {

            const stock =
                Number(product.stock);

            const outOfStock =
                stock <= 0;

            return `

                <article class="product-card">

                    <div class="product-image">

                        ${
                            product.badge
                            ? `
                                <span
                                    class="product-badge"
                                >
                                    ${escapeHtml(
                                        product.badge
                                    )}
                                </span>
                            `
                            : ""
                        }

                        <span>
                            ${escapeHtml(
                                product.emoji ||
                                "🍩"
                            )}
                        </span>

                    </div>


                    <div class="product-content">

                        <div class="product-name">
                            ${escapeHtml(
                                product.name
                            )}
                        </div>


                        <div class="product-description">
                            ${escapeHtml(
                                product.description ||
                                ""
                            )}
                        </div>


                        <div class="product-bottom">

                            <div>

                                <div class="product-price">
                                    ${rupiah(
                                        Number(
                                            product.price
                                        )
                                    )}
                                </div>

                                <div class="product-stock">
                                    ${
                                        outOfStock
                                        ? "Stok habis"
                                        : `Stok ${stock}`
                                    }
                                </div>

                            </div>


                            <button
                                type="button"
                                class="add-button"
                                onclick="addToCart(${product.id})"
                                ${
                                    outOfStock
                                    ? "disabled"
                                    : ""
                                }
                                title="Tambah ke keranjang"
                            >

                                <i
                                    data-lucide="plus"
                                    width="18"
                                ></i>

                            </button>

                        </div>

                    </div>

                </article>

            `;

        }).join("");


    if (window.lucide) {
        lucide.createIcons();
    }

}


/* =========================================================
   FILTER
========================================================= */

function setFilter(filter, button) {

    activeFilter = filter;

    document
        .querySelectorAll(
            ".filter-button"
        )
        .forEach(btn => {

            btn.classList.remove(
                "active"
            );

        });

    if (button) {
        button.classList.add("active");
    }

    renderProducts();

}


/* =========================================================
   FILTER SEARCH
========================================================= */

function filterProducts() {

    renderProducts();

}


/* =========================================================
   RENDER CART
========================================================= */

function renderCart() {
    const container = document.getElementById("cartItems");
    const subtotalEl = document.getElementById("cartSubtotal");
    if (!container) return;

    if (!cart.length) {
        container.innerHTML = `
            <div class="empty-cart">
                <div>
                    <div style="font-size:55px;margin-bottom:15px;">🛒</div>
                    <div style="font-size:17px;font-weight:900;color:#6B3E26;">Keranjang masih kosong</div>
                    <div style="margin-top:7px;font-size:12px;color:#8A6047;">Yuk pilih donat favoritmu.</div>
                </div>
            </div>`;
        if (subtotalEl) subtotalEl.textContent = rupiah(0);
        updateCartCount();
        return;
    }

    const validItems = cart.map(item => {
        const product = getProduct(item.id);
        if (!product) return "";
        const qty = Number(item.qty);
        const itemSubtotal = Number(product.price) * qty;
        return `
            <div class="cart-item" data-product-id="${Number(product.id)}">
                <div class="cart-item-icon">${escapeHtml(product.emoji || "🍩")}</div>
                <div class="cart-item-main">
                    <div class="cart-item-name">${escapeHtml(product.name)}</div>
                    <div class="cart-item-price">${rupiah(itemSubtotal)}</div>
                    <div class="qty-controls">
                        <button type="button" class="qty-btn" onclick="changeQty(${Number(product.id)},-1)" aria-label="Kurangi">−</button>
                        <span class="qty-number">${qty}</span>
                        <button type="button" class="qty-btn" onclick="changeQty(${Number(product.id)},1)" aria-label="Tambah">+</button>
                    </div>
                </div>
                <button type="button" class="remove-item" onclick="removeFromCart(${Number(product.id)})" title="Hapus" aria-label="Hapus ${escapeHtml(product.name)}">
                    <i data-lucide="trash-2" width="17"></i>
                </button>
            </div>`;
    }).join("");

    container.innerHTML = validItems || `
        <div class="empty-cart">
            <div><div style="font-size:55px;">🛒</div><div style="font-weight:900;color:#6B3E26;">Keranjang masih kosong</div></div>
        </div>`;

    if (subtotalEl) subtotalEl.textContent = rupiah(getCartSubtotal());
    updateCartCount();
    if (window.lucide) lucide.createIcons();
}

function openCart() {
    closeMobileMenu();
    cleanCart();
    renderCart();
    document.getElementById("cartDrawer")?.classList.add("show");
    document.getElementById("cartOverlay")?.classList.add("show");
    document.body.style.overflow = "hidden";
}

function closeCart() {
    document.getElementById("cartDrawer")?.classList.remove("show");
    document.getElementById("cartOverlay")?.classList.remove("show");
    if (!document.getElementById("checkoutModal")?.classList.contains("show")) {
        document.body.style.overflow = "";
    }
}

/* =========================================================
   OPEN CHECKOUT
========================================================= */

function openCheckout() {

    if (!cart.length) {

        showToast(
            "Keranjang masih kosong."
        );

        return;
    }

    renderCart();

    updateCheckoutTotal();

    closeCart();

    document
        .getElementById(
            "checkoutModal"
        )
        ?.classList.add("show");

    document.body.style.overflow =
        "hidden";

    getLocation();

}


/* =========================================================
   CLOSE CHECKOUT
========================================================= */

function closeCheckout() {

    document
        .getElementById(
            "checkoutModal"
        )
        ?.classList.remove("show");

    document.body.style.overflow =
        "";

}


/* =========================================================
   HAVERSINE JS
========================================================= */

function calculateDistance(
    lat1,
    lon1,
    lat2,
    lon2
) {

    const R = 6371;

    const dLat =
        (
            (lat2 - lat1) *
            Math.PI
        ) / 180;

    const dLon =
        (
            (lon2 - lon1) *
            Math.PI
        ) / 180;

    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(
            lat1 * Math.PI / 180
        ) *
        Math.cos(
            lat2 * Math.PI / 180
        ) *
        Math.sin(dLon / 2) ** 2;

    const c =
        2 *
        Math.atan2(
            Math.sqrt(a),
            Math.sqrt(1 - a)
        );

    return R * c;

}


/* =========================================================
   UPDATE CHECKOUT TOTAL
========================================================= */

function updateCheckoutTotal() {

    const subtotal =
        getCartSubtotal();

    let shipping = 0;

    const lat =
        parseFloat(
            document.getElementById(
                "latitude"
            )?.value
        );

    const lng =
        parseFloat(
            document.getElementById(
                "longitude"
            )?.value
        );

    if (
        Number.isFinite(lat) &&
        Number.isFinite(lng) &&
        lat !== 0 &&
        lng !== 0
    ) {

        const distance =
            calculateDistance(
                STORE_LAT,
                STORE_LNG,
                lat,
                lng
            );

        if (
            distance <=
            MAX_DISTANCE
        ) {

            shipping =
                Math.ceil(distance) *
                PRICE_PER_KM;

        }

    }

    const subtotalEl =
        document.getElementById(
            "checkoutSubtotal"
        );

    const shippingEl =
        document.getElementById(
            "checkoutShipping"
        );

    const totalEl =
        document.getElementById(
            "checkoutTotal"
        );

    if (subtotalEl) {
        subtotalEl.textContent =
            rupiah(subtotal);
    }

    if (shippingEl) {
        shippingEl.textContent =
            rupiah(shipping);
    }

    if (totalEl) {
        totalEl.textContent =
            rupiah(
                subtotal +
                shipping
            );
    }

}


/* =========================================================
   GET LOCATION
========================================================= */

function getLocation() {

    const status =
        document.getElementById(
            "locationStatus"
        );

    if (!navigator.geolocation) {

        if (status) {
            status.textContent =
                "Browser tidak mendukung lokasi.";
        }

        return;
    }

    if (status) {

        status.textContent =
            "⏳ Sedang mendeteksi lokasi...";

    }

    navigator.geolocation.getCurrentPosition(

        function(position) {

            const lat =
                position.coords.latitude;

            const lng =
                position.coords.longitude;

            const latitudeEl =
                document.getElementById(
                    "latitude"
                );

            const longitudeEl =
                document.getElementById(
                    "longitude"
                );

            if (latitudeEl) {
                latitudeEl.value = lat;
            }

            if (longitudeEl) {
                longitudeEl.value = lng;
            }

            const distance =
                calculateDistance(
                    STORE_LAT,
                    STORE_LNG,
                    lat,
                    lng
                );

            if (
                distance >
                MAX_DISTANCE
            ) {

                if (status) {

                    status.innerHTML =
                        "❌ Lokasi terlalu jauh (" +
                        distance.toFixed(2) +
                        " km).";

                }

                updateCheckoutTotal();

                return;
            }

            const shipping =
                Math.ceil(distance) *
                PRICE_PER_KM;

            if (status) {

                status.innerHTML =
                    "✅ Lokasi terdeteksi • " +
                    distance.toFixed(2) +
                    " km • Ongkir " +
                    rupiah(shipping);

            }

            updateCheckoutTotal();

        },

        function(error) {

            let message =
                "Lokasi belum berhasil dideteksi.";

            if (error.code === 1) {

                message =
                    "❌ Izin lokasi ditolak. Izinkan lokasi browser.";

            }

            if (error.code === 2) {

                message =
                    "❌ Lokasi tidak tersedia.";

            }

            if (error.code === 3) {

                message =
                    "❌ Waktu deteksi lokasi habis.";

            }

            if (status) {
                status.textContent =
                    message;
            }

        },

        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }

    );

}


/* =========================================================
   PAYMENT UI
========================================================= */

function updatePaymentUI() {

    const qris =
        document.getElementById(
            "paymentQRIS"
        );

    const cod =
        document.getElementById(
            "paymentCOD"
        );

    const selected =
        document.querySelector(
            'input[name="payment_method"]:checked'
        )?.value;

    qris?.classList.toggle(
        "active",
        selected === "QRIS"
    );

    cod?.classList.toggle(
        "active",
        selected === "COD"
    );

}


/* =========================================================
   SUBMIT ORDER
========================================================= */

async function submitOrder(event) {

    event.preventDefault();

    if (!cart.length) {

        showToast(
            "Keranjang masih kosong."
        );

        return;
    }

    const button =
        document.getElementById(
            "submitOrderButton"
        );

    const latitude =
        document.getElementById(
            "latitude"
        ).value;

    const longitude =
        document.getElementById(
            "longitude"
        ).value;

    if (!latitude || !longitude) {

        showToast(
            "Silakan deteksi lokasi terlebih dahulu."
        );

        getLocation();

        return;
    }

    button.disabled = true;

    button.textContent =
        "Memproses pesanan...";

    const form =
        document.getElementById(
            "checkoutForm"
        );

    const formData =
        new FormData(form);

    formData.append(
        "action",
        "checkout"
    );

    formData.append(
        "cart",
        JSON.stringify(cart)
    );

    try {

        const response =
            await fetch(
                window.location.href,
                {
                    method: "POST",
                    body: formData
                }
            );

        const result =
            await response.json();

        if (!result.success) {

            throw new Error(
                result.message ||
                "Pesanan gagal."
            );

        }

        cart = [];

        saveCart();

        updateCartCount();

        renderCart();

        closeCheckout();

        showToast(
            "Pesanan berhasil dibuat! 🍩"
        );

        setTimeout(() => {

            window.location.href =
                result.whatsapp_url;

        }, 700);

    } catch (error) {

        showToast(
            error.message ||
            "Terjadi kesalahan."
        );

    } finally {

        button.disabled = false;

        button.textContent =
            "Pesan Sekarang via WhatsApp";

    }

}


/* =========================================================
   TOAST
========================================================= */

let toastTimer;

function showToast(message) {

    const toast =
        document.getElementById(
            "toast"
        );

    if (!toast) {
        return;
    }

    toast.textContent =
        message;

    toast.classList.add("show");

    clearTimeout(toastTimer);

    toastTimer =
        setTimeout(() => {

            toast.classList.remove(
                "show"
            );

        }, 2800);

}


/* =========================================================
   DONUT RAIN
========================================================= */

function createDonutRain() {

    const container =
        document.getElementById(
            "donutRain"
        );

    if (!container) {
        return;
    }

    const emojis = [
        "🍩",
        "🍩",
        "🍩",
        "✨"
    ];

    for (
        let i = 0;
        i < 22;
        i++
    ) {

        const donut =
            document.createElement(
                "span"
            );

        donut.className =
            "falling-donut";

        donut.textContent =
            emojis[
                Math.floor(
                    Math.random() *
                    emojis.length
                )
            ];

        donut.style.left =
            Math.random() * 100 +
            "%";

        donut.style.fontSize =
            (
                14 +
                Math.random() * 20
            ) +
            "px";

        donut.style.animationDuration =
            (
                8 +
                Math.random() * 12
            ) +
            "s";

        donut.style.animationDelay =
            (
                Math.random() * 10
            ) +
            "s";

        container.appendChild(
            donut
        );

    }

}


/* =========================================================
   KEYBOARD
========================================================= */

document.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key === "Escape"
        ) {

            closeMobileMenu();

            closeCart();

            closeCheckout();

        }

    }
);


/* =========================================================
   CLICK OUTSIDE CHECKOUT
========================================================= */

document
    .getElementById(
        "checkoutModal"
    )
    ?.addEventListener(
        "click",
        function(event) {

            if (
                event.target === this
            ) {

                closeCheckout();

            }

        }
    );


/* =========================================================
   CLICK OUTSIDE MOBILE MENU
========================================================= */

document.addEventListener(
    "click",
    function(event) {

        const menu =
            document.getElementById(
                "mobileMenu"
            );

        const button =
            document.getElementById(
                "mobileMenuButton"
            );

        if (!menu || !button) {
            return;
        }

        if (
            menu.classList.contains("show") &&
            !menu.contains(event.target) &&
            !button.contains(event.target)
        ) {

            closeMobileMenu();

        }

    }
);


/* =========================================================
   RESIZE SAFETY
========================================================= */

window.addEventListener(
    "resize",
    function() {

        if (
            window.innerWidth > 1000
        ) {

            closeMobileMenu();

        }

    }
);


/* =========================================================
   INIT
========================================================= */

document.addEventListener(
    "DOMContentLoaded",
    function() {

        loadCart();

        cleanCart();

        renderProducts();

        renderCart();

        updateCartCount();

        updatePaymentUI();

        createDonutRain();

        updateCheckoutTotal();

        if (window.lucide) {
            lucide.createIcons();
        }

    }
);



/* =========================================================
   FINAL INTERACTION POLISH
========================================================= */
function syncCartUI(){
  cleanCart();
  renderCart();
  updateCartCount();
  updateCheckoutTotal();
}

// Active navbar section
const navSections = document.querySelectorAll('section[id]');
const navAnchors = document.querySelectorAll('.nav-links a');
if ('IntersectionObserver' in window) {
  const navObserver = new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{
      if(!entry.isIntersecting) return;
      navAnchors.forEach(a=>a.classList.toggle('active', a.getAttribute('href') === '#'+entry.target.id));
    });
  },{rootMargin:'-35% 0px -55% 0px',threshold:0});
  navSections.forEach(section=>navObserver.observe(section));
}

// Cart button micro-interaction
const originalAddToCart = addToCart;
addToCart = function(id){
  originalAddToCart(id);
  const btn=document.querySelector('.cart-button');
  if(btn){
    btn.classList.remove('bump');
    void btn.offsetWidth;
    btn.classList.add('bump');
    setTimeout(()=>btn.classList.remove('bump'),450);
  }
};

// Keep cart state fresh when returning to the tab.
window.addEventListener('storage', function(event){
  if(event.key !== CART_KEY) return;
  loadCart();
  syncCartUI();
});

document.addEventListener('visibilitychange', function(){
  if(!document.hidden){
    loadCart();
    syncCartUI();
  }
});

</script>

</body>
</html>