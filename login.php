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

// ตรวจสอบว่ามีการส่งข้อมูลแบบ POST จากฟอร์มหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_username = $_POST['username'];
    $input_password = $_POST['password'];

    // เตรียมคำสั่ง SQL เพื่อป้องกัน SQL Injection
    // ดึงข้อมูลผู้ใช้จากตาราง users
    $sql = "SELECT UserID, Username, Password, Role FROM Users WHERE Username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $input_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // ตรวจสอบรหัสผ่านที่กรอกเข้ามากับรหัสผ่านที่ถูก hash ในฐานข้อมูล
        if (password_verify($input_password, $user['Password'])) {
            // รหัสผ่านถูกต้อง
            // สร้าง session เพื่อเก็บข้อมูลผู้ใช้
            $_SESSION['user_id'] = $user['UserID'];
            $_SESSION['username'] = $user['Username'];
            $_SESSION['role'] = $user['Role'];
            
            // เปลี่ยนเส้นทางไปยังหน้า index.php
            header("Location: index.php");
            exit();
        } else {
            // รหัสผ่านไม่ถูกต้อง
            $message = "<div class='message error'>ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง!</div>";
        }
    } else {
        // ไม่พบชื่อผู้ใช้
        $message = "<div class='message error'>ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง!</div>";
    }

    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบจัดการร้านค้า</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f0f2f5;
        }
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            text-align: center;
        }
        .error {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">

<div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md mx-4">
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">เข้าสู่ระบบ</h1>
        <p class="text-gray-500">กรุณากรอกชื่อผู้ใช้และรหัสผ่านเพื่อดำเนินการต่อ</p>
    </div>

    <!-- แสดงข้อความแจ้งเตือนถ้ามี -->
    <?php echo $message; ?>

    <form action="login.php" method="POST" class="space-y-4">
        <div>
            <label for="username" class="block text-sm font-medium text-gray-700">ชื่อผู้ใช้</label>
            <input 
                type="text" 
                id="username" 
                name="username" 
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                required>
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
            <input 
                type="password" 
                id="password" 
                name="password" 
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                required>
        </div>
        <div>
            <button 
                type="submit" 
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                เข้าสู่ระบบ
            </button>
        </div>
    </form>
</div>

</body>
</html>
