<?php
// กำหนดข้อมูลการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "chalinshop"; // เปลี่ยนชื่อฐานข้อมูลตามที่ระบุ

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ตั้งค่าภาษาของฐานข้อมูลให้รองรับภาษาไทย
$conn->set_charset("utf8mb4");

// ส่วนของการเพิ่มใบรายงานการขาย
$message = "";
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ตรวจสอบว่าเป็นคำขอ AJAX เพื่อดึงรายละเอียด
if ($action == 'get_sale_details' && isset($_GET['sale_id'])) {
    $sale_id = intval($_GET['sale_id']);

    $details_sql = "SELECT si.Quantity, si.PriceAtSale, p.ProductName
                    FROM Sale_Items si
                    JOIN Products p ON si.ProductID = p.ProductID
                    WHERE si.SaleID = ?";
    $stmt = $conn->prepare($details_sql);
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $details_result = $stmt->get_result();

    $items = [];
    while ($row = $details_result->fetch_assoc()) {
        $items[] = [
            'product_name' => htmlspecialchars($row['ProductName']),
            'quantity' => $row['Quantity'],
            'price' => number_format($row['PriceAtSale'], 2)
        ];
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'items' => $items]);
    $stmt->close();
    $conn->close();
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับค่าจากฟอร์ม
    $sale_date = isset($_POST['sale_date']) ? $_POST['sale_date'] : date('Y-m-d H:i:s');
    $shop_id = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : 0;
    $products = isset($_POST['products']) ? $_POST['products'] : [];

    if ($shop_id > 0 && count($products) > 0) {
        $conn->begin_transaction();

        try {
            $total_price = 0;
            $items_to_insert = [];

            foreach ($products as $item) {
                $product_id = intval($item['product_id']);
                $quantity = intval($item['quantity']);
                $sale_price = isset($item['sale_price']) ? floatval($item['sale_price']) : 0;

                if ($product_id <= 0 || $quantity <= 0 || $sale_price <= 0) {
                    continue; // ข้ามรายการที่ไม่ถูกต้อง
                }

                // ดึงสต็อกปัจจุบันเพื่อตรวจสอบ
                $check_stock_sql = "SELECT Stock FROM Shop_Products WHERE ProductID = ? AND ShopID = ?";
                $stmt_check = $conn->prepare($check_stock_sql);
                $stmt_check->bind_param("ii", $product_id, $shop_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $row_check = $result_check->fetch_assoc();
                $current_stock = $row_check['Stock'];
                $stmt_check->close();

                if ($quantity > $current_stock) {
                    throw new Exception("สินค้า " . $product_id . " มีจำนวนไม่เพียงพอในสต็อก!");
                }

                $total_price += ($sale_price * $quantity);

                // อัปเดตสต็อกในตาราง Shop_Products
                $update_stock_sql = "UPDATE Shop_Products SET Stock = Stock - ? WHERE ProductID = ? AND ShopID = ?";
                $stmt_stock = $conn->prepare($update_stock_sql);
                $stmt_stock->bind_param("iii", $quantity, $product_id, $shop_id);
                $stmt_stock->execute();
                $stmt_stock->close();

                $items_to_insert[] = [
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'sale_price' => $sale_price
                ];
            }

            if (empty($items_to_insert)) {
                throw new Exception("ไม่มีรายการสินค้าที่ถูกต้อง!");
            }

            // เพิ่มรายการขายลงในตาราง Sales
            $sale_sql = "INSERT INTO Sales (SaleDate, ShopID, TotalPrice) VALUES (?, ?, ?)";
            $stmt_sale = $conn->prepare($sale_sql);
            $stmt_sale->bind_param("sid", $sale_date, $shop_id, $total_price);
            $stmt_sale->execute();
            $sale_id = $stmt_sale->insert_id;
            $stmt_sale->close();

            // เพิ่มรายการสินค้าที่ขายลงในตาราง Sale_Items
            $sale_item_sql = "INSERT INTO Sale_Items (SaleID, ProductID, Quantity, PriceAtSale) VALUES (?, ?, ?, ?)";
            $stmt_item = $conn->prepare($sale_item_sql);
            foreach ($items_to_insert as $item) {
                $stmt_item->bind_param("iiid", $sale_id, $item['product_id'], $item['quantity'], $item['sale_price']);
                $stmt_item->execute();
            }
            $stmt_item->close();

            $conn->commit();
            $message = "<div class='message success'>เพิ่มใบรายงานการขายสำเร็จ!</div>";

        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='message error'>กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง</div>";
    }
}

// ดึงรายการขายทั้งหมดเพื่อแสดงในตาราง
$sales_sql = "SELECT s.SaleID, s.SaleDate, s.TotalPrice, sh.ShopName
              FROM Sales s
              JOIN Shops sh ON s.ShopID = sh.ShopID
              ORDER BY s.SaleDate DESC";
$sales_result = $conn->query($sales_sql);

// ดึงรายการหน้าร้านและสินค้าสำหรับ dropdowns
$shops_sql = "SELECT ShopID, ShopName FROM Shops ORDER BY ShopName ASC";
$shops_result = $conn->query($shops_sql);

$products_sql = "SELECT ProductID, ProductName FROM Products ORDER BY ProductName ASC";
$products_result = $conn->query($products_sql);

// เพื่อใช้ใน JavaScript
$all_products = [];
while ($row = $products_result->fetch_assoc()) {
    $all_products[] = $row;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการหน้าร้าน</title>

    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">


</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
                <div class="sidebar-brand-icon rotate-n-15">
                    <i class="fas fa-laugh-wink"></i>
                </div>
                <div class="sidebar-brand-text mx-3">Chalin Shop</div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">

            <!-- Nav Item - Dashboard -->
            <li class="nav-item active">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span></a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Interface
            </div>

            <!-- Nav Item - Pages Collapse Menu -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseTwo"
                    aria-expanded="true" aria-controls="collapseTwo">
                    <i class="fas fa-fw fa-cog"></i>
                    <span>Manager</span>
                </a>
                <div id="collapseTwo" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded">
                        <h6 class="collapse-header">Manager Menu:</h6>
                        <a class="collapse-item" href="manage_shops.php">Shops Manager</a>
                        <a class="collapse-item" href="manage_products.php">Products Manager</a>
                        <a class="collapse-item" href="manage_products_stocks.php">Stocks Manager</a>
                    </div>
                </div>
            </li>

            <!-- Nav Item - Utilities Collapse Menu -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseUtilities"
                    aria-expanded="true" aria-controls="collapseUtilities">
                    <i class="fas fa-fw fa-wrench"></i>
                    <span>Reports</span>
                </a>
                <div id="collapseUtilities" class="collapse" aria-labelledby="headingUtilities"
                    data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded">
                        <h6 class="collapse-header">Reports Menu:</h6>
                        <a class="collapse-item" href="manage_sales_reports.php">Sales Report</a>
                        <a class="collapse-item" href="manage_purchases.php">Purchases Report</a>
                    </div>
                </div>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Addons
            </div>

            <!-- Nav Item - Pages Collapse Menu -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePages"
                    aria-expanded="true" aria-controls="collapsePages">
                    <i class="fas fa-fw fa-folder"></i>
                    <span>Pages</span>
                </a>
                <div id="collapsePages" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded">
                        <h6 class="collapse-header">Login Screens:</h6>
                        <a class="collapse-item" href="login.html">Login</a>
                        <a class="collapse-item" href="register.html">Register</a>
                        <a class="collapse-item" href="forgot-password.html">Forgot Password</a>
                        <div class="collapse-divider"></div>
                        <h6 class="collapse-header">Other Pages:</h6>
                        <a class="collapse-item" href="404.html">404 Page</a>
                        <a class="collapse-item" href="blank.html">Blank Page</a>
                    </div>
                </div>
            </li>

            <!-- Nav Item - Charts -->
            <li class="nav-item">
                <a class="nav-link" href="charts.html">
                    <i class="fas fa-fw fa-chart-area"></i>
                    <span>Charts</span></a>
            </li>

            <!-- Nav Item - Tables -->
            <li class="nav-item">
                <a class="nav-link" href="tables.html">
                    <i class="fas fa-fw fa-table"></i>
                    <span>Tables</span></a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            <!-- Sidebar Toggler (Sidebar) -->
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>

            <!-- Sidebar Message -->
            <div class="sidebar-card d-none d-lg-flex">
                <img class="sidebar-card-illustration mb-2" src="img/undraw_rocket.svg" alt="...">
                <p class="text-center mb-2"><strong>SB Admin Pro</strong> is packed with premium features, components,
                    and more!</p>
                <a class="btn btn-success btn-sm" href="https://startbootstrap.com/theme/sb-admin-pro">Upgrade to
                    Pro!</a>
            </div>

        </ul>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                    <!-- Sidebar Toggle (Topbar) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <!-- Topbar Search -->
                    <form
                        class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search">
                        <div class="input-group">
                            <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..."
                                aria-label="Search" aria-describedby="basic-addon2">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="button">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">

                        <!-- Nav Item - Search Dropdown (Visible Only XS) -->
                        <li class="nav-item dropdown no-arrow d-sm-none">
                            <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-search fa-fw"></i>
                            </a>
                            <!-- Dropdown - Messages -->
                            <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in"
                                aria-labelledby="searchDropdown">
                                <form class="form-inline mr-auto w-100 navbar-search">
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-light border-0 small"
                                            placeholder="Search for..." aria-label="Search"
                                            aria-describedby="basic-addon2">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button">
                                                <i class="fas fa-search fa-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </li>

                        <!-- Nav Item - Alerts -->
                        <li class="nav-item dropdown no-arrow mx-1">
                            <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-bell fa-fw"></i>
                                <!-- Counter - Alerts -->
                                <span class="badge badge-danger badge-counter">3+</span>
                            </a>
                            <!-- Dropdown - Alerts -->
                            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="alertsDropdown">
                                <h6 class="dropdown-header">
                                    Alerts Center
                                </h6>
                                <a class="dropdown-item d-flex align-items-center" href="#">
                                    <div class="mr-3">
                                        <div class="icon-circle bg-primary">
                                            <i class="fas fa-file-alt text-white"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="small text-gray-500">December 12, 2019</div>
                                        <span class="font-weight-bold">A new monthly report is ready to download!</span>
                                    </div>
                                </a>
                                <a class="dropdown-item d-flex align-items-center" href="#">
                                    <div class="mr-3">
                                        <div class="icon-circle bg-success">
                                            <i class="fas fa-donate text-white"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="small text-gray-500">December 7, 2019</div>
                                        $290.29 has been deposited into your account!
                                    </div>
                                </a>
                                <a class="dropdown-item d-flex align-items-center" href="#">
                                    <div class="mr-3">
                                        <div class="icon-circle bg-warning">
                                            <i class="fas fa-exclamation-triangle text-white"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="small text-gray-500">December 2, 2019</div>
                                        Spending Alert: We've noticed unusually high spending for your account.
                                    </div>
                                </a>
                                <a class="dropdown-item text-center small text-gray-500" href="#">Show All Alerts</a>
                            </div>
                        </li>

                        <!-- Nav Item - Messages -->
                        <li class="nav-item dropdown no-arrow mx-1">
                            <a class="nav-link dropdown-toggle" href="#" id="messagesDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-envelope fa-fw"></i>
                                <!-- Counter - Messages -->
                                <span class="badge badge-danger badge-counter">7</span>
                            </a>
                            <!-- Dropdown - Messages -->
                            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="messagesDropdown">
                                <h6 class="dropdown-header">
                                    Message Center
                                </h6>
                                <a class="dropdown-item d-flex align-items-center" href="#">
                                    <div class="dropdown-list-image mr-3">
                                        <img class="rounded-circle" src="img/undraw_profile_1.svg" alt="...">
                                        <div class="status-indicator bg-success"></div>
                                    </div>
                                    <div class="font-weight-bold">
                                        <div class="text-truncate">Hi there! I am wondering if you can help me with a
                                            problem I've been having.</div>
                                        <div class="small text-gray-500">Emily Fowler · 58m</div>
                                    </div>
                                </a>
                                <a class="dropdown-item d-flex align-items-center" href="#">
                                    <div class="dropdown-list-image mr-3">
                                        <img class="rounded-circle" src="img/undraw_profile_2.svg" alt="...">
                                        <div class="status-indicator"></div>
                                    </div>
                                    <div>
                                        <div class="text-truncate">I have the photos that you ordered last month, how
                                            would you like them sent to you?</div>
                                        <div class="small text-gray-500">Jae Chun · 1d</div>
                                    </div>
                                </a>
                                <a class="dropdown-item d-flex align-items-center" href="#">
                                    <div class="dropdown-list-image mr-3">
                                        <img class="rounded-circle" src="img/undraw_profile_3.svg" alt="...">
                                        <div class="status-indicator bg-warning"></div>
                                    </div>
                                    <div>
                                        <div class="text-truncate">Last month's report looks great, I am very happy with
                                            the progress so far, keep up the good work!</div>
                                        <div class="small text-gray-500">Morgan Alvarez · 2d</div>
                                    </div>
                                </a>
                                <a class="dropdown-item d-flex align-items-center" href="#">
                                    <div class="dropdown-list-image mr-3">
                                        <img class="rounded-circle" src="https://source.unsplash.com/Mv9hjnEUHR4/60x60"
                                            alt="...">
                                        <div class="status-indicator bg-success"></div>
                                    </div>
                                    <div>
                                        <div class="text-truncate">Am I a good boy? The reason I ask is because someone
                                            told me that people say this to all dogs, even if they aren't good...</div>
                                        <div class="small text-gray-500">Chicken the Dog · 2w</div>
                                    </div>
                                </a>
                                <a class="dropdown-item text-center small text-gray-500" href="#">Read More Messages</a>
                            </div>
                        </li>

                        <div class="topbar-divider d-none d-sm-block"></div>

                        <!-- Nav Item - User Information -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small">Douglas McGee</span>
                                <img class="img-profile rounded-circle" src="img/undraw_profile.svg">
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="#">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Profile
                                </a>
                                <a class="dropdown-item" href="#">
                                    <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Settings
                                </a>
                                <a class="dropdown-item" href="#">
                                    <i class="fas fa-list fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Activity Log
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>

                    </ul>

                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <h1 class="h3 mb-4 text-gray-800">Stocks Products Manager</h1>

                    <div class="row">

                        <div class="col-lg-6">

                            <!-- Circle Buttons -->
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Send Products To The Store</h6>
                                </div>
                                <div class="card-body">
                                    <h2>Add Sale Report</h2>
                                    <form action="manage_sales_reports.php" method="POST">
                                        <div class="form-group">
                                            <label for="sale_date">Sale Date : </label>
                                            <input type="datetime-local" id="sale_date" name="sale_date"
                                                value="<?php echo date('Y-m-d\TH:i:s'); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="shop_id">Shop : </label>
                                            <select id="shop_id" name="shop_id" required>
                                                <option value="">-- Select a Shop --</option>
                                                <?php
                                                if ($shops_result->num_rows > 0) {
                                                    $shops_result->data_seek(0); // Reset pointer
                                                    while ($row = $shops_result->fetch_assoc()) {
                                                        echo "<option value='" . $row['ShopID'] . "'>" . htmlspecialchars($row['ShopName']) . "</option>";
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <h3>Products List</h3>
                                        <div id="product-list">
                                            <!-- Product items will be added here by JavaScript -->
                                        </div>
                                        <button type="button" id="add-product-btn">Add Products</button>

                                        <button type="submit">Submit</button>
                                    </form>
                                </div>
                            </div>

                            <!-- Brand Buttons -->
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">All Sales Report </h6>
                                </div>
                                <div class="card-body">
                                    <?php
                                    if ($sales_result->num_rows > 0) {
                                        echo "<table>";
                                        echo "<thead><tr><th>รหัสบิล</th><th>วันที่ขาย</th><th>หน้าร้าน</th><th>ราคารวม</th><th>Action</th></tr></thead>";
                                        echo "<tbody>";
                                        while ($row = $sales_result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . $row["SaleID"] . "</td>";
                                            echo "<td>" . htmlspecialchars($row["SaleDate"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["ShopName"]) . "</td>";
                                            echo "<td>" . number_format($row["TotalPrice"], 2) . " บาท</td>";
                                            echo "<td><button class='detail-btn' onclick='showDetails(" . $row["SaleID"] . ")'>ดูรายละเอียด</button></td>";
                                            echo "</tr>";
                                        }
                                        echo "</tbody>";
                                        echo "</table>";
                                    } else {
                                        echo "<p>ไม่พบข้อมูลการขาย</p>";
                                    }

                                    $conn->close();
                                    ?>
                                    <!-- Details section to be populated by JS -->
                                    <div id="sale-details" class="details-section">
                                        <h3>รายละเอียดใบรายงานการขาย: <span id="sale-id-display"></span></h3>
                                        <div id="details-content"></div>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                    <!-- /.container-fluid -->

                </div>
                <!-- End of Main Content -->

                <!-- Footer -->
                <footer class="sticky-footer bg-white">
                    <div class="container my-auto">
                        <div class="copyright text-center my-auto">
                            <span>Copyright &copy; Your Website 2020</span>
                        </div>
                    </div>
                </footer>
                <!-- End of Footer -->

            </div>
            <!-- End of Content Wrapper -->

        </div>
        <!-- End of Page Wrapper -->

        <!-- Scroll to Top Button-->
        <a class="scroll-to-top rounded" href="#page-top">
            <i class="fas fa-angle-up"></i>
        </a>

        <!-- Logout Modal-->
        <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
            aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                        <a class="btn btn-primary" href="login.html">Logout</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bootstrap core JavaScript-->
        <script src="vendor/jquery/jquery.min.js"></script>
        <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

        <!-- Core plugin JavaScript-->
        <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

        <!-- Custom scripts for all pages-->
        <script src="js/sb-admin-2.min.js"></script>

        <script>
            const allProducts = <?php echo json_encode($all_products); ?>;
            const productList = document.getElementById('product-list');
            const addProductBtn = document.getElementById('add-product-btn');

            function createProductItem(index) {
                const productItem = document.createElement('div');
                productItem.className = 'product-item';

                const productGroup = document.createElement('div');
                const productLabel = document.createElement('label');
                productLabel.textContent = 'สินค้า:';
                const productSelect = document.createElement('select');
                productSelect.name = `products[${index}][product_id]`;
                productSelect.required = true;
                productSelect.innerHTML = '<option value="">-- เลือกสินค้า --</option>';
                allProducts.forEach(product => {
                    const option = document.createElement('option');
                    option.value = product.ProductID;
                    option.textContent = product.ProductName;
                    productSelect.appendChild(option);
                });
                productGroup.appendChild(productLabel);
                productGroup.appendChild(productSelect);

                const quantityGroup = document.createElement('div');
                const quantityLabel = document.createElement('label');
                quantityLabel.textContent = 'จำนวน:';
                const quantityInput = document.createElement('input');
                quantityInput.type = 'number';
                quantityInput.name = `products[${index}][quantity]`;
                quantityInput.placeholder = 'จำนวน';
                quantityInput.min = '1';
                quantityInput.required = true;
                quantityGroup.appendChild(quantityLabel);
                quantityGroup.appendChild(quantityInput);

                const priceGroup = document.createElement('div');
                const priceLabel = document.createElement('label');
                priceLabel.textContent = 'ราคาขาย:';
                const priceInput = document.createElement('input');
                priceInput.type = 'number';
                priceInput.name = `products[${index}][sale_price]`;
                priceInput.step = '0.01';
                priceInput.placeholder = 'ราคาขาย';
                priceInput.min = '0';
                priceInput.required = true;
                priceGroup.appendChild(priceLabel);
                priceGroup.appendChild(priceInput);

                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.textContent = '-';
                removeButton.onclick = () => productItem.remove();

                productItem.appendChild(productGroup);
                productItem.appendChild(quantityGroup);
                productItem.appendChild(priceGroup);
                productItem.appendChild(removeButton);

                return productItem;
            }

            let productIndex = 0;
            addProductBtn.onclick = () => {
                productList.appendChild(createProductItem(productIndex++));
            };

            // Add one item by default
            addProductBtn.click();

            // Function to show sale details
            async function showDetails(saleId) {
                const detailsSection = document.getElementById('sale-details');
                const detailsContent = document.getElementById('details-content');
                const saleIdDisplay = document.getElementById('sale-id-display');

                // Show loading state
                saleIdDisplay.textContent = saleId;
                detailsContent.innerHTML = '<p>กำลังโหลด...</p>';
                detailsSection.style.display = 'block';

                try {
                    const response = await fetch(`manage_sales_reports.php?action=get_sale_details&sale_id=${saleId}`);
                    const data = await response.json();

                    if (data.success && data.items.length > 0) {
                        let tableHtml = "<table><thead><tr><th>สินค้า</th><th>จำนวน</th><th>ราคาขาย/หน่วย</th><th>ราคารวม</th></tr></thead><tbody>";
                        let totalBillPrice = 0;
                        data.items.forEach(item => {
                            const itemTotalPrice = parseFloat(item.price.replace(/,/g, '')) * parseInt(item.quantity);
                            totalBillPrice += itemTotalPrice;
                            tableHtml += `
                        <tr>
                            <td>${item.product_name}</td>
                            <td>${item.quantity}</td>
                            <td>${item.price} บาท</td>
                            <td>${itemTotalPrice.toLocaleString('en-US', { minimumFractionDigits: 2 })} บาท</td>
                        </tr>
                    `;
                        });
                        tableHtml += `
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align:right; font-weight:bold;">ราคารวมทั้งหมด:</td>
                            <td style="font-weight:bold;">${totalBillPrice.toLocaleString('en-US', { minimumFractionDigits: 2 })} บาท</td>
                        </tr>
                    </tfoot>
                    </table>`;
                        detailsContent.innerHTML = tableHtml;
                    } else {
                        detailsContent.innerHTML = '<p>ไม่พบรายละเอียดสำหรับบิลนี้</p>';
                    }
                } catch (error) {
                    detailsContent.innerHTML = '<p>เกิดข้อผิดพลาดในการโหลดข้อมูล. กรุณาลองอีกครั้ง.</p>';
                    console.error('Error fetching sale details:', error);
                }
            }
        </script>

</body>

</html>