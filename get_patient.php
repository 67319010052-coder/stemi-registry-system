<?php
// get_patient.php
header('Content-Type: application/json; charset=utf-8');
require 'connect.php'; 

$hn_input = $_GET['id'] ?? ''; // รับค่าที่ user กรอก (คือ HN)
$response = [];

if ($hn_input) {
    try {
        // 1. ค้นหาในตาราง patients ด้วย column 'hn' (ไม่ใช่ id)
        $stmt = $pdo->prepare("SELECT id, firstname, lastname, hn FROM patients WHERE hn = ?");
        $stmt->execute([$hn_input]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($patient) {
            // เจอคนไข้! เอา ID ของเขา (เช่น 51) ไปค้นประวัติการตาย
            $real_id = $patient['id'];
            
            // เช็คใน patient_discharges ว่า ID นี้เคยมีประวัติไหม
            $stmt_death = $pdo->prepare("SELECT * FROM patient_discharges WHERE patient_id = ?");
            $stmt_death->execute([$real_id]); 
            $death_record = $stmt_death->fetch(PDO::FETCH_ASSOC);

            $response = [
                'status' => 'success',
                'patient' => [
                    'firstname' => $patient['firstname'],
                    'lastname'  => $patient['lastname'],
                    'real_id'   => $patient['id'] // ส่ง ID จริงกลับไปเผื่อใช้
                ],
                'death_data' => $death_record ? $death_record : null 
            ];
        } else {
            $response = ['status' => 'not_found'];
        }

    } catch (PDOException $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }
} else {
    $response = ['status' => 'empty_hn'];
}

echo json_encode($response);
?>