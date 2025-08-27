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
    $input_role = "Guest"; // กำหนดบทบาทเริ่มต้นเป็น 'Shop' หรือตามที่คุณต้องการ

    // ตรวจสอบว่าชื่อผู้ใช้มีอยู่แล้วหรือไม่
    $check_sql = "SELECT UserID FROM Users WHERE Username = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $input_username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $message = "<div class='message error'>ชื่อผู้ใช้ '{$input_username}' มีอยู่แล้ว!</div>";
    } else {
        // เข้ารหัสรหัสผ่านก่อนบันทึกลงในฐานข้อมูล
        $hashed_password = password_hash($input_password, PASSWORD_DEFAULT);

        // เตรียมคำสั่ง SQL สำหรับเพิ่มผู้ใช้ใหม่
        $insert_sql = "INSERT INTO Users (Username, Password, Role) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sss", $input_username, $hashed_password, $input_role);

        if ($insert_stmt->execute()) {
            $message = "<div class='message success'>สมัครสมาชิกสำเร็จ! <a href='login.php' class='text-indigo-600 hover:underline'>เข้าสู่ระบบ</a></div>";
        } else {
            $message = "<div class='message error'>เกิดข้อผิดพลาดในการสมัครสมาชิก: " . $insert_stmt->error . "</div>";
        }
        $insert_stmt->close();
    }
    $check_stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - ระบบจัดการร้านค้า</title>
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
        .success {
            background-color: #d1fae5;
            color: #065f46;
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
        <h1 class="text-2xl font-bold text-gray-800 mb-2">สร้างบัญชีใหม่</h1>
        <p class="text-gray-500">กรุณากรอกข้อมูลเพื่อสมัครสมาชิก</p>
    </div>

    <!-- แสดงข้อความแจ้งเตือนถ้ามี -->
    <?php echo $message; ?>

    <form action="register.php" method="POST" class="space-y-4">
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
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                สมัครสมาชิก
            </button>
        </div>
    </form>
    <div class="mt-4 text-center text-gray-500">
        มีบัญชีอยู่แล้ว? <a href="login.php" class="text-indigo-600 hover:underline">เข้าสู่ระบบ</a>
    </div>
</div>

</body>
</html>
