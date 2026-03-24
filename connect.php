<?php
// รายละเอียดการเชื่อมต่อฐานข้อมูล
$host     = 'localhost';
$db_name  = 'steme'; // ชื่อฐานข้อมูลของคุณ
$username = 'root';              // ชื่อผู้ใช้ (ปกติคือ root)
$password = '';                  // รหัสผ่าน (ปกติคือค่าว่างสำหรับ XAMPP)
$charset  = 'utf8mb4';           // รองรับภาษาไทยและ Emoji

// กำหนด DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";

// การตั้งค่า Option สำหรับ PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // แจ้งเตือนเมื่อเกิด Error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // ให้ดึงข้อมูลมาเป็น Array แบบชื่อคอลัมน์
    PDO::ATTR_EMULATE_PREPARES   => false,                  // ใช้ Prepared Statements จริง (ป้องกัน SQL Injection)
];

try {
    // สร้างการเชื่อมต่อ
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    // หากเชื่อมต่อไม่ได้ ให้แจ้งเตือน
    die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . $e->getMessage());
}
?>