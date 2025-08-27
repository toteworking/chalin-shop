<?php
// เริ่มการทำงานของ session
session_start();

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['user_id'])) {
    // ถ้ายังไม่ได้ล็อกอิน ให้เปลี่ยนเส้นทางไปหน้าล็อกอิน
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];

// ตรวจสอบว่าผู้ใช้มีสิทธิ์เป็น 'Admin' หรือไม่
if ($role !== 'Admin') {
    // ถ้าไม่ใช่ Admin ให้เปลี่ยนเส้นทางกลับไปหน้าหลัก
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];

// โค้ดสำหรับหน้า Dashboard
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ระบบจัดการร้านค้า</title>
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
    </style>
</head>
<body class="flex flex-col items-center justify-center min-h-screen">
    
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-2xl mx-4 text-center">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">Dashboard สำหรับ Admin</h1>
        <p class="text-gray-600 text-lg mb-2">ยินดีต้อนรับ, <span class="font-bold text-blue-600"><?php echo htmlspecialchars($username); ?></span>!</p>
        <p class="text-gray-500">นี่คือพื้นที่สำหรับผู้ดูแลระบบเท่านั้น</p>
        
        <div class="mt-8 space-y-4">
            <a href="index.php" class="inline-block w-full py-3 px-6 bg-gray-400 text-white font-medium rounded-lg hover:bg-gray-500 transition-colors">
                กลับสู่หน้าหลัก
            </a>
        </div>

        <a href="logout.php" class="mt-8 inline-block text-gray-500 hover:text-gray-700 hover:underline transition-colors">ออกจากระบบ</a>
    </div>

</body>
</html>
