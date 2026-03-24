<?php
require 'connect.php';

// รับ patient ID
$patient_id = $_GET['id'] ?? '';

// ดึงข้อมูลอ้างอิงจากหน้าแรก
$stmt_ref = $pdo->prepare("SELECT diag_ekg_date, diag_ekg_time, hospital_date_hatyai, hospital_time_hatyai FROM symptoms_diagnosis WHERE patient_id = ?");
$stmt_ref->execute([$patient_id]);
$ref = $stmt_ref->fetch(PDO::FETCH_ASSOC) ?: [];

// ดึงข้อมูลเดิมจากฐานข้อมูล
$stmt_exist = $pdo->prepare("SELECT * FROM patient_consults WHERE patient_id = ?");
$stmt_exist->execute([$patient_id]);
$existing_data = $stmt_exist->fetch(PDO::FETCH_ASSOC) ?: [];

// กำหนดค่าเริ่มต้น
$diag_date = $existing_data['diag_date'] ?? $ref['diag_ekg_date'] ?? '';
$diag_time = $existing_data['diag_time'] ?? $ref['diag_ekg_time'] ?? '';
$admit_date = $existing_data['admit_date'] ?? $ref['hospital_date_hatyai'] ?? '';
$admit_time = $existing_data['admit_time'] ?? $ref['hospital_time_hatyai'] ?? '';

$door_dt = ($ref['hospital_date_hatyai'] ?? '') . 'T' . ($ref['hospital_time_hatyai'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($patient_id)) {
        echo "<script>
            alert('ไม่สามารถบันทึกได้: กรุณากรอกข้อมูลลงทะเบียนผู้ป่วยที่หน้าแรกให้เรียบร้อยก่อน');
            window.location.href = 'ding_stemi.php';
        </script>";
        exit; // หยุดการทำงานของคำสั่ง SQL ด้านล่างทันที
    }
    try {
        $sql = "REPLACE INTO patient_consults (
            patient_id, diag_date, diag_time, cardio_name, cardio_date, cardio_time,
            inter_name, inter_date, inter_time, exit_er_date, exit_er_time,
            admit_ward, admit_date, admit_time
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $patient_id, 
            $_POST['diag_date'], $_POST['diag_time'], 
            $_POST['cardio_name'], $_POST['cardio_date'], $_POST['cardio_time'],
            $_POST['inter_name'], $_POST['inter_date'], $_POST['inter_time'],
            $_POST['exit_er_date'], $_POST['exit_er_time'],
            $_POST['admit_ward'], $_POST['admit_date'], $_POST['admit_time']
        ]);

        header("Location: Symptoms_diagnosis.php?id=" . $patient_id . "&status=saved&type=stemi"); 
        exit();
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STEMI Consult & Admission</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
    font-family: 'Sarabun', sans-serif;
    
    /* ฟ้าอ่อนพาสเทล -> ขาว */
    background: linear-gradient(180deg, #e3f2fd 0%, #ffffff 100%);
    
    min-height: 100vh;
    margin: 0;
    background-repeat: no-repeat;
    background-attachment: fixed;
}
        .form-section { background: white; border-radius: 15px; border-top: 5px solid #0d6efd; }
        .inner-card { border: 1px solid #dee2e6; border-radius: 10px; padding: 20px; margin-bottom: 20px; background: #fff; }
        .section-header { font-weight: bold; color: #495057; border-bottom: 2px solid #f1f3f5; margin-bottom: 15px; padding-bottom: 5px; }
    </style>
</head>
<body>

<div class="container py-5">
    <form method="POST" class="form-section shadow p-4">
        <input type="hidden" name="patient_id" value="<?= htmlspecialchars($patient_id) ?>">
        <input type="hidden" id="door_datetime" value="<?= htmlspecialchars($door_dt) ?>">

        <div class="d-flex align-items-center mb-4">
            <span class="fs-2 me-3">🩺</span>
            <h3 class="text-primary mb-0">STEMI</h3>
        </div>

        <div class="inner-card shadow-sm">
            <div class="section-header">Diagnosis Information</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Diagnosis Date</label>
                    <input type="date" name="diag_date" id="diag_date" class="form-control" value="<?= htmlspecialchars($diag_date) ?>" onchange="calcConsultDelay()">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Diagnosis Time</label>
                    <input type="time" name="diag_time" id="diag_time" class="form-control" value="<?= htmlspecialchars($diag_time) ?>" onchange="calcConsultDelay()">
                </div>
            </div>
        </div>

        <div class="inner-card shadow-sm">
            <div class="section-header">Cardiologist Consulted</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">ชื่อแพทย์ที่ปรึกษา</label>
                    <select name="cardio_name" class="form-select">
                        <option value="">-- เลือกรายชื่อ --</option>
                        <option value="พ.วรัญญา" <?= (isset($existing_data['cardio_name']) && $existing_data['cardio_name'] == 'พ.วรัญญา') ? 'selected' : '' ?>>พ.วรัญญา</option>
                        <option value="พ.วิสันต์" <?= (isset($existing_data['cardio_name']) && $existing_data['cardio_name'] == 'พ.วิสันต์') ? 'selected' : '' ?>>พ.วิสันต์</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Date Consulted</label>
                    <input type="date" name="cardio_date" id="cardio_date" class="form-control" value="<?= htmlspecialchars($existing_data['cardio_date'] ?? date('Y-m-d')) ?>" onchange="calcConsultDelay()">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Time Consulted</label>
                    <input type="time" name="cardio_time" id="cardio_time" class="form-control" onchange="calcConsultDelay()" value="<?= htmlspecialchars($existing_data['cardio_time'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <div id="kpi_consult" class="alert alert-info py-2 mb-0 mt-2" style="display:none;">
                        ⏱️ **KPI:** Dx to Consult: <span id="delay_val" class="fw-bold">0</span> minutes
                    </div>
                </div>
            </div>
        </div>

        <div class="inner-card shadow-sm">
            <div class="section-header">Interventionist</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Interventionist Name</label>
                    <input type="text" name="inter_name" class="form-control" list="inter_list" placeholder="พิมพ์หรือเลือกชื่อ" value="<?= htmlspecialchars($existing_data['inter_name'] ?? '') ?>">
                    <datalist id="inter_list">
                        <option value="พ.สริราม"><option value="พ.อิทธิพล"><option value="พ.ธนวัฒน์">
                    </datalist>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Consult Date</label>
                    <input type="date" name="inter_date" class="form-control" value="<?= htmlspecialchars($existing_data['inter_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Consult Time</label>
                    <input type="time" name="inter_time" class="form-control" value="<?= htmlspecialchars($existing_data['inter_time'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="inner-card shadow-sm bg-light">
            <div class="section-header text-danger">Exit ER & Admission Ward</div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Exit ER Date</label>
                    <input type="date" id="exit_date" name="exit_er_date" class="form-control border-danger" onchange="calcERDwellTime()" value="<?= htmlspecialchars($existing_data['exit_er_date'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Exit ER Time</label>
                    <input type="time" id="exit_time" name="exit_er_time" class="form-control border-danger" onchange="calcERDwellTime()" value="<?= htmlspecialchars($existing_data['exit_er_time'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <div id="er_dwell_box" class="alert alert-warning py-2 mb-0 mt-1" style="display:none;">
                        ⏳ **ER Dwell Time:** <span id="dwell_min" class="fw-bold">0</span> minutes
                    </div>
                </div>
            </div>
            <div class="row g-3 border-top pt-3 mt-2">
                <div class="col-md-4">
                    <label class="form-label fw-bold text-primary">Admission Ward</label>
                    <input type="text" name="admit_ward" class="form-control border-primary" placeholder="เช่น CCU / Ward 7" value="<?= htmlspecialchars($existing_data['admit_ward'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-primary">Admit Date</label>
                    <input type="date" id="admit_date" name="admit_date" class="form-control border-primary" value="<?= htmlspecialchars($admit_date) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-primary">Admit Time</label>
                    <input type="time" id="admit_time" name="admit_time" class="form-control border-primary" value="<?= htmlspecialchars($admit_time) ?>">
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <a href="Symptoms_diagnosis.php?id=<?= htmlspecialchars($patient_id) ?>" class="btn btn-secondary px-4 shadow-sm">Back</a>
            <button type="submit" class="btn btn-success px-5 shadow-sm">SAVE & NEXT</button>
        </div>
    </form>
</div>

<script>
/**
 * ✅ ฟังก์ชันคำนวณความล่าช้าในการตามแพทย์ (Dx to Consult)
 * เป้าหมายทางการแพทย์: < 30 นาที
 */
function calcConsultDelay() {
    const diagDate = document.getElementById('diag_date').value;
    const diagTime = document.getElementById('diag_time').value;
    const consultDate = document.getElementById('cardio_date').value;
    const consultTime = document.getElementById('cardio_time').value;
    
    const delayBox = document.getElementById('kpi_consult');
    const delayVal = document.getElementById('delay_val');

    if (diagDate && diagTime && consultDate && consultTime) {
        const start = new Date(`${diagDate}T${diagTime}`);
        const end = new Date(`${consultDate}T${consultTime}`);
        let diff = (end - start) / 60000; // แปลงผลต่างเป็นนาที

        if (diff >= 0) {
            delayVal.innerText = Math.round(diff);
            delayBox.style.display = 'block';
            
            // 🚨 การแจ้งเตือนตามเกณฑ์ KPI (30 นาที)
            // เขียว = ปกติ, แดง = ล่าช้าเกินเกณฑ์
            delayVal.style.color = (diff > 30) ? '#dc3545' : '#198754';
        } else {
            delayBox.style.display = 'none';
        }
    }
}

/**
 * ✅ ฟังก์ชันคำนวณเวลาที่อยู่ใน ER ทั้งหมด (ER Dwell Time)
 * คำนวณจากเวลาที่มาถึงโรงพยาบาลจนถึงเวลาออกจาก ER
 */
function calcERDwellTime() {
    const doorStr = document.getElementById('door_datetime').value; // เวลามาถึงที่ดึงมาจากหน้าแรก
    const exitDate = document.getElementById('exit_date').value;
    const exitTime = document.getElementById('exit_time').value;

    if (doorStr && exitDate && exitTime) {
        const door = new Date(doorStr);
        const exit = new Date(`${exitDate}T${exitTime}`);
        let diff = (exit - door) / 60000; // นาที
        
        if (diff >= 0) {
            document.getElementById('er_dwell_box').style.display = 'block';
            document.getElementById('dwell_min').innerText = Math.round(diff);
            
            // ⚡ Auto-fill Logic: เมื่อระบุเวลาออกจาก ER ให้ถือว่าเป็นเวลา Admit เข้าวอร์ดโดยอัตโนมัติ
            if (document.getElementById('admit_date')) document.getElementById('admit_date').value = exitDate;
            if (document.getElementById('admit_time')) document.getElementById('admit_time').value = exitTime;
        } else {
             document.getElementById('er_dwell_box').style.display = 'none';
        }
    }
}

/**
 * ✅ ฟังก์ชันหลักสำหรับรันการคำนวณทั้งหมด
 */
function runAllCalculations() {
    calcConsultDelay();
    calcERDwellTime();
}

// 1. ผูกเหตุการณ์เมื่อโหลดหน้าเว็บเสร็จ (แสดงผลข้อมูลที่มีอยู่เดิม)
window.onload = runAllCalculations;

// 2. ผูกเหตุการณ์แบบ Real-time เมื่อมีการเปลี่ยนค่าในช่องวันที่หรือเวลา
document.querySelectorAll('input[type="time"], input[type="date"]').forEach(input => {
    input.addEventListener('change', runAllCalculations);
});
</script>
</body>
</html>