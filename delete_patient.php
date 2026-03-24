<?php
// delete_patient.php
require 'connect.php';

$id = $_GET['id'] ?? '';

if (!empty($id)) {
    try {
        // ใช้ Transaction เพื่อความปลอดภัยสูงสุด
        $pdo->beginTransaction();   
        
        $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        header("Location: index.php?status=deleted");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit();
}
// ดึงรายชื่อผู้ป่วยล่าสุด
$stmt = $pdo->query("SELECT id, firstname, lastname, citizen_id, created_at FROM patients ORDER BY created_at DESC");
$patients = $stmt->fetchAll();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>STEMI Registry - Patient List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container">
    <div class="d-flex justify-content-between mb-4">
        <h2>📋 รายชื่อผู้ป่วย STEMI</h2>
        <a href="patient_form.php" class="btn btn-primary">+ เพิ่มคนไข้ใหม่</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>วันที่บันทึก</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>HN / ID</th>
                        <th class="text-center">จัดการข้อมูล</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $p): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                        <td><?= htmlspecialchars($p['firstname'] . ' ' . $p['lastname']) ?></td>
                        <td><?= htmlspecialchars($p['citizen_id']) ?></td>
                        <td class="text-center">
                            <a href="patient_summary.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-info">Dashboard</a>
                            <a href="patient_form.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning">แก้ไข</a>
                            <a href="delete_patient.php?id=<?= $p['id'] ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('ยืนยันการลบข้อมูลผู้ป่วยรายนี้? ข้อมูลการรักษาทั้งหมดจะถูกลบถาวร')">ลบ</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>