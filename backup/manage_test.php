<?php
// เริ่มการทำงานของ session
session_start();

// กำหนดข้อมูลการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "chalinshop"; // เปลี่ยนชื่อฐานข้อมูลของคุณ

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ตั้งค่าภาษาของฐานข้อมูลให้รองรับภาษาไทย
$conn->set_charset("utf8mb4");

$message = "";

// ส่วนของการจัดการการส่งสินค้า
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['transfer_stock'])) {
    $source_shop_id = intval($_POST['source_shop']);
    $destination_shop_id = intval($_POST['destination_shop']);
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);

    // ตรวจสอบว่ามีสินค้าในร้านค้าต้นทางเพียงพอหรือไม่
    $check_stock_sql = "SELECT Stock FROM Shop_Products WHERE ShopID = ? AND ProductID = ?";
    $stmt = $conn->prepare($check_stock_sql);
    $stmt->bind_param("ii", $source_shop_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $current_stock = $row['Stock'];
    $stmt->close();

    if ($current_stock >= $quantity) {
        // ลดจำนวนสินค้าในร้านค้าต้นทาง
        $update_source_sql = "UPDATE Shop_Products SET Stock = Stock - ? WHERE ShopID = ? AND ProductID = ?";
        $stmt = $conn->prepare($update_source_sql);
        $stmt->bind_param("iii", $quantity, $source_shop_id, $product_id);
        $stmt->execute();
        $stmt->close();

        // เพิ่มจำนวนสินค้าในร้านค้าปลายทาง
        // ตรวจสอบว่ามีสินค้านั้นในร้านค้าปลายทางอยู่แล้วหรือไม่
        $check_dest_sql = "SELECT ShopProductID FROM Shop_Products WHERE ShopID = ? AND ProductID = ?";
        $stmt = $conn->prepare($check_dest_sql);
        $stmt->bind_param("ii", $destination_shop_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // ถ้ามีอยู่แล้ว ให้อัปเดตสต็อก
            $update_dest_sql = "UPDATE Shop_Products SET Stock = Stock + ? WHERE ShopID = ? AND ProductID = ?";
            $stmt_update = $conn->prepare($update_dest_sql);
            $stmt_update->bind_param("iii", $quantity, $destination_shop_id, $product_id);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            // ถ้ายังไม่มี ให้เพิ่มรายการใหม่เข้าไป
            $insert_dest_sql = "INSERT INTO Shop_Products (ShopID, ProductID, Stock) VALUES (?, ?, ?)";
            $stmt_insert = $conn->prepare($insert_dest_sql);
            $stmt_insert->bind_param("iii", $destination_shop_id, $product_id, $quantity);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        $stmt->close();
        $message = "<div class='message success'>โอนย้ายสต็อกสินค้าเรียบร้อยแล้ว!</div>";
    } else {
        $message = "<div class='message error'>สต็อกสินค้าไม่เพียงพอสำหรับการโอนย้าย!</div>";
    }
}

// ดึงข้อมูลร้านค้าทั้งหมดสำหรับ Dropdown
$shops_sql = "SELECT ShopID, ShopName FROM Shops";
$shops_result = $conn->query($shops_sql);
$shops = [];
while($row = $shops_result->fetch_assoc()) {
    $shops[] = $row;
}

// ดึงข้อมูลสินค้าทั้งหมดสำหรับ Dropdown
$products_sql = "SELECT ProductID, ProductName FROM Products";
$products_result = $conn->query($products_sql);
$products = [];
while($row = $products_result->fetch_assoc()) {
    $products[] = $row;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสต็อกสินค้า - ระบบจัดการร้านค้า</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f0f2f5; }
        .message { padding: 12px; border-radius: 8px; margin-bottom: 16px; text-align: center; }
        .error { background-color: #fee2e2; color: #991b1b; }
        .success { background-color: #d1fae5; color: #065f46; }
    </style>
</head>
<body class="p-8">

    <div class="max-w-4xl mx-auto bg-white p-8 rounded-xl shadow-lg">
        <h1 class="text-3xl font-bold text-gray-800 text-center mb-6">จัดการสต็อกสินค้า</h1>
        
        <?php echo $message; ?>

        <!-- 1. หน้าต่างสต็อกสำหรับส่งสินค้าระหว่างหน้าร้าน -->
        <div class="mb-8">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">โอนย้ายสินค้า</h2>
            <form action="manage_products_stocks.php" method="POST" class="space-y-4">
                <div>
                    <label for="source_shop" class="block text-sm font-medium text-gray-700">หน้าร้านต้นทาง</label>
                    <select id="source_shop" name="source_shop" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <?php foreach ($shops as $shop): ?>
                            <option value="<?php echo $shop['ShopID']; ?>"><?php echo htmlspecialchars($shop['ShopName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="destination_shop" class="block text-sm font-medium text-gray-700">หน้าร้านปลายทาง</label>
                    <select id="destination_shop" name="destination_shop" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <?php foreach ($shops as $shop): ?>
                            <option value="<?php echo $shop['ShopID']; ?>"><?php echo htmlspecialchars($shop['ShopName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="product_id" class="block text-sm font-medium text-gray-700">สินค้า</label>
                    <select id="product_id" name="product_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['ProductID']; ?>"><?php echo htmlspecialchars($product['ProductName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="quantity" class="block text-sm font-medium text-gray-700">จำนวน</label>
                    <input type="number" id="quantity" name="quantity" min="1" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <button type="submit" name="transfer_stock" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                        โอนย้ายสต็อก
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. หน้าต่างแสดงสต็อกทั้งหมด เป็นตาราง -->
        <div>
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">สต็อกสินค้าทั้งหมด</h2>
            <p class="text-sm text-gray-500 mb-4">แสดงหน้าร้าน "Chalin Shop" เป็นหน้าร้านหลัก</p>
            <div class="bg-gray-50 p-4 rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">หน้าร้าน</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สินค้า</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ราคาขาย</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">จำนวนคงเหลือ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        // ดึงข้อมูลสต็อกสินค้าทั้งหมด
                        $all_stock_sql = "SELECT s.ShopName, p.ProductName, sp.SalePrice, sp.Stock 
                                          FROM Shop_Products sp
                                          JOIN Shops s ON sp.ShopID = s.ShopID
                                          JOIN Products p ON sp.ProductID = p.ProductID
                                          ORDER BY s.ShopName DESC, p.ProductName ASC";
                        $all_stock_result = $conn->query($all_stock_sql);

                        if ($all_stock_result->num_rows > 0) {
                            while($row = $all_stock_result->fetch_assoc()) {
                                $row_class = '';
                                if ($row['ShopName'] === 'Chalin Shop') {
                                    $row_class = 'bg-yellow-50 font-bold';
                                }
                                echo "<tr class='$row_class'>";
                                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>" . htmlspecialchars($row['ShopName']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>" . htmlspecialchars($row['ProductName']) . "</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>" . number_format($row['SalePrice'], 2) . " บาท</td>";
                                echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>" . htmlspecialchars($row['Stock']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='px-6 py-4 text-center text-sm text-gray-500'>ไม่พบข้อมูลสต็อกสินค้า</td></tr>";
                        }
                        $conn->close();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>
