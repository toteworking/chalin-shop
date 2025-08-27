<?php

$connect = new PDO("mysql:host=localhost;dbname=chalinshop;charset=utf8", "root", "");
$received_data = json_decode(file_get_contents("php://input"));
$data = array();

// ตรวจสอบว่ามีการส่งข้อมูล JSON มาหรือไม่ และมีคุณสมบัติ 'action' หรือเปล่า
if (isset($received_data->action)) {
    if ($received_data->action == "fetchAll") {
        $query = "SELECT * FROM products";
        $statement = $connect->prepare($query);
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($result);
    }
} else {
    // ถ้าไม่มีการส่งข้อมูลมา ให้ออกค่าว่าง หรือแสดงผลที่ต้องการ
    // ซึ่งจะทำให้หน้านั้นโหลดโดยไม่มีข้อผิดพลาด
    echo json_encode(['error' => 'No action specified']);
}

?>