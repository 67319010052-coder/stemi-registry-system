<?php
session_start();
require 'connect.php'; // ไฟล์เชื่อมต่อฐานข้อมูลของคุณ

// ตรวจสอบว่าได้ล็อกอินหรือยัง
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// ดึงชื่อผู้ใช้จาก Session มาเก็บไว้ในตัวแปร
$current_user = $_SESSION['user'];

$patient_id = $_GET['id'] ?? $_POST['patient_id'] ?? '';

// --- LINKING PART 1: ดึงเวลาจากหน้า Diagnosis มาเป็นตัวตั้งต้น ---
$stmt_ref = $pdo->prepare("SELECT onset_date, onset_time, hospital_date_hatyai, hospital_time_hatyai, ems_time, dx_time 
                           FROM symptoms_diagnosis WHERE patient_id = ?");
$stmt_ref->execute([$patient_id]);
$ref_data = $stmt_ref->fetch(PDO::FETCH_ASSOC) ?: [];

// รวม Date+Time ให้เป็น format ที่ JS เอาไปคำนวณได้ (ISO)
// ใช้ !empty() เช็คก่อน เพื่อป้องกัน Error: Undefined array key
$ref_door = (!empty($ref_data['hospital_date_hatyai']) && !empty($ref_data['hospital_time_hatyai'])) 
            ? $ref_data['hospital_date_hatyai'] . 'T' . $ref_data['hospital_time_hatyai'] : '';

$ref_onset = (!empty($ref_data['onset_date']) && !empty($ref_data['onset_time'])) 
             ? $ref_data['onset_date'] . 'T' . $ref_data['onset_time'] : '';

$ref_fmc = $ref_data['ems_time'] ?? ''; // ใช้ ?? เพื่อกำหนดค่าว่างถ้าไม่มี key
$ref_dx  = $ref_data['dx_time'] ?? '';

// ----------------------------------------------------------------------
// 2. ประกาศตัวแปร Global
// ----------------------------------------------------------------------
$segments_map = [
    // RCA
    1 => '1. Proximal RCA', 2 => '2. Mid RCA', 3 => '3. Distal RCA', 
    4 => '4. PDA (Right Dom)', 16 => '16. PLB (Right Dom)',
    // LM & LAD
    5 => '5. Left Main', 6 => '6. Proximal LAD', 7 => '7. Mid LAD', 
    8 => '8. Distal LAD', 9 => '9. Diagonal 1', 10 => '10. Diagonal 2',
    // LCx
    11 => '11. Proximal LCx', 12 => '12. OM 1', 13 => '13. Distal LCx', 
    14 => '14. OM 2', 15 => '15. PDA/PLB (Left Dom)'
];

// ----------------------------------------------------------------------
// 3. เตรียมตัวแปร Sticky Form (ดึงข้อมูลเดิม)
// ----------------------------------------------------------------------
$stmt_old = $pdo->prepare("SELECT * FROM cardiac_cath WHERE patient_id = ?");
$stmt_old->execute([$patient_id]);
$existing_data = $stmt_old->fetch(PDO::FETCH_ASSOC) ?: [];
$segment_data = !empty($existing_data['segment_data']) ? json_decode($existing_data['segment_data'], true) : [];

// แกะข้อมูลจาก Database มาใส่ตัวแปร
$arrived_date       = $existing_data['arrived_date'] ?? '';
$arrived_time       = $existing_data['arrived_time'] ?? '';
$pci_status         = explode(',', $existing_data['pci_status'] ?? ''); 
$cardiogenic_shock  = $existing_data['cardiogenic_shock'] ?? '';
$pci_indication     = explode(',', $existing_data['pci_indication'] ?? '');
$procedure_success  = $existing_data['procedure_success'] ?? '';
$cath_conclusion    = $existing_data['cath_conclusion'] ?? '';

// ข้อมูล CAD & Access
$cad_presentation   = $existing_data['cad_presentation'] ?? '';
$access_site        = $existing_data['access_site'] ?? '';
$access_crossover   = $existing_data['access_crossover'] ?? '';
$dominance          = $existing_data['dominance'] ?? '';
$nonsignificant_cad = $existing_data['nonsignificant_cad'] ?? '';

// ข้อมูลเวลา
$door_in_datetime   = $existing_data['door_in_datetime'] ?? '';
$puncture_time      = $existing_data['puncture_time'] ?? '';
$first_device_time  = $existing_data['first_device_time'] ?? '';
$finish_time        = $existing_data['finish_time'] ?? '';

// Mechanical Support
$iabp       = $existing_data['iabp'] ?? '';
$mc_support = $existing_data['mc_support'] ?? '';
$mc_type    = explode(',', $existing_data['mc_type'] ?? '');

// ----------------------------------------------------------------------
// 4. HANDLE FORM SUBMISSION (Logic หลัก)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($patient_id)) {
        echo "<script>
            alert('ไม่สามารถบันทึกได้: กรุณากรอกข้อมูลลงทะเบียนผู้ป่วยที่หน้าแรกให้เรียบร้อยก่อน');
            window.location.href = 'cardiac_cath.php';
        </script>";
        exit;
    }
    $direction = $_POST['direction'] ?? 'next';

    try {
        // --- 4.1 เตรียมข้อมูล Array/JSON ---
        $pci_status_val = isset($_POST['pci_status']) ? implode(',', $_POST['pci_status']) : '';
        $indication_val = isset($_POST['indication']) ? implode(',', $_POST['indication']) : '';
        $mc_type_val    = isset($_POST['mc_type']) ? implode(',', $_POST['mc_type']) : '';
        $conclusion_val = isset($_POST['diagnosis']) ? implode(',', $_POST['diagnosis']) : '';

        // จัดการ Segment Data ลง JSON
        $segments_save = [];
        foreach ($segments_map as $k => $name) {
            // บันทึกเฉพาะ Segment ที่มีการกรอกข้อมูลสำคัญ
            if (!empty($_POST["seg_{$k}_percent"]) || !empty($_POST["seg_{$k}_method"]) || !empty($_POST["seg_{$k}_lesion"])) {
                 $segments_save[$k] = [
                    'percent' => $_POST["seg_{$k}_percent"] ?? '',
                    'timi_pre' => $_POST["seg_{$k}_timi_pre"] ?? '',
                    'lesion_type' => $_POST["seg_{$k}_lesion"] ?? '',
                    'method' => $_POST["seg_{$k}_method"] ?? '',
                    'device_size' => $_POST["seg_{$k}_device_size"] ?? '',
                    'pressure_atm' => $_POST["seg_{$k}_pressure"] ?? '',
                    'post_dilate' => $_POST["seg_{$k}_dilate"] ?? '',
                    'residual_stenosis' => $_POST["seg_{$k}_resid"] ?? '',
                    'timi_post' => $_POST["seg_{$k}_timi_post"] ?? '',
                    'culprit' => $_POST["seg_{$k}_culprit"] ?? null,
                ];
            }
        }
        $segment_json = json_encode($segments_save, JSON_UNESCAPED_UNICODE);

        // --- 4.2 ระบบคำนวณเวลา Server-Side Calculation (คำนวณซ้ำก่อนบันทึกเพื่อความชัวร์) ---
        $calc_door_to_device = null;
        $calc_onset_to_device = null;
        $first_device_time_post = $_POST['first_device_time'] ?? '';

        // รับค่า Reference จาก Hidden Input หรือ DB (ในที่นี้ใช้ตัวแปร PHP ที่ดึงไว้แล้ว)
        if (!empty($first_device_time_post)) {
            $device_dt = new DateTime($first_device_time_post);

            // คำนวณ Door to Device
            if (!empty($ref_door)) {
                $door_dt = new DateTime($ref_door);
                $diff = $device_dt->getTimestamp() - $door_dt->getTimestamp();
                $calc_door_to_device = floor($diff / 60); // แปลงเป็นนาที
            }

            // คำนวณ Onset to Device
            if (!empty($ref_onset)) {
                $onset_dt = new DateTime($ref_onset);
                $diff = $device_dt->getTimestamp() - $onset_dt->getTimestamp();
                $calc_onset_to_device = floor($diff / 60); // แปลงเป็นนาที
            }
        }

        // --- 4.3 SQL SAVE ---
        $sql = "INSERT INTO cardiac_cath (
                    patient_id, 
                    arrived_date, arrived_time,
                    pci_status, cardiogenic_shock, pci_indication, 
                    procedure_success, door_in_datetime, puncture_time, 
                    first_device_time, finish_time, iabp, mc_support, mc_type, 
                    cad_presentation, access_site, access_crossover, dominance, nonsignificant_cad,
                    door_to_device, onset_to_device, 
                    segment_data, cath_conclusion
                ) VALUES (
                    :id, 
                    :arr_date, :arr_time,
                    :pci_status, :shock, :indication,
                    :success, :door_in, :puncture,
                    :device_time, :finish, :iabp, :mc_support, :mc_type,
                    :cad_pres, :access, :crossover, :dominance, :nonsig_cad,
                    :d2d, :o2d,
                    :seg_data, :concl
                ) ON DUPLICATE KEY UPDATE
                    arrived_date = VALUES(arrived_date),
                    arrived_time = VALUES(arrived_time),
                    pci_status = VALUES(pci_status),
                    cardiogenic_shock = VALUES(cardiogenic_shock),
                    pci_indication = VALUES(pci_indication),
                    procedure_success = VALUES(procedure_success),
                    door_in_datetime = VALUES(door_in_datetime),
                    puncture_time = VALUES(puncture_time),
                    first_device_time = VALUES(first_device_time),
                    finish_time = VALUES(finish_time),
                    iabp = VALUES(iabp),
                    mc_support = VALUES(mc_support),
                    mc_type = VALUES(mc_type),
                    cad_presentation = VALUES(cad_presentation),
                    access_site = VALUES(access_site),
                    access_crossover = VALUES(access_crossover),
                    dominance = VALUES(dominance),
                    nonsignificant_cad = VALUES(nonsignificant_cad),
                    door_to_device = VALUES(door_to_device),
                    onset_to_device = VALUES(onset_to_device),
                    segment_data = VALUES(segment_data),
                    cath_conclusion = VALUES(cath_conclusion)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $patient_id,
            ':arr_date' => $_POST['arrived_date'] ?? null,
            ':arr_time' => $_POST['arrived_time'] ?? null,
            ':pci_status' => $pci_status_val,
            ':shock' => $_POST['shock'] ?? null,
            ':indication' => $indication_val,
            ':success' => $_POST['proc_success'] ?? null,
            ':door_in' => !empty($_POST['door_in_datetime']) ? $_POST['door_in_datetime'] : null,
            ':puncture' => !empty($_POST['puncture_time']) ? $_POST['puncture_time'] : null,
            ':device_time' => !empty($_POST['first_device_time']) ? $_POST['first_device_time'] : null,
            ':finish' => !empty($_POST['finish_time']) ? $_POST['finish_time'] : null,
            ':iabp' => $_POST['iabp'] ?? null,
            ':mc_support' => $_POST['mc_support'] ?? null,
            ':mc_type' => $mc_type_val,
            ':cad_pres' => $_POST['cad_presentation'] ?? null,
            ':access' => $_POST['access_site'] ?? null,
            ':crossover' => $_POST['access_crossover'] ?? null,
            ':dominance' => $_POST['dominance'] ?? null,
            ':nonsig_cad' => $_POST['nonsignificant_cad'] ?? null,
            ':d2d' => $calc_door_to_device,
            ':o2d' => $calc_onset_to_device,
            ':seg_data' => $segment_json,
            ':concl' => $conclusion_val
        ]);

        // --- 4.4 REDIRECT ---
        if ($direction === 'back') {
            header("Location: Medication.php?id=" . $patient_id);
        } else {
            header("Location: treatment_results.php?id=" . $patient_id);
        }
        exit();

    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>

<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>Cardiac Cath</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<style>
/* --- Layout & General Styles --- */
 body { 
    font-family: 'Sarabun', sans-serif;
    /* ฟ้าอ่อนพาสเทล -> ขาว */
    background: linear-gradient(180deg, #e3f2fd 0%, #ffffff 100%);
    min-height: 100vh;
    margin: 0;
    background-repeat: no-repeat;
    background-attachment: fixed;
}
.top-bar {
    background: #fff;
    padding: 18px;
    border-radius: 8px;
    margin-bottom: 18px;
}
.hospital-title { color: #19a974; font-weight: bold; }
.form-section {
    background: #f6f8f9;
    padding: 32px;
    border-radius: 12px;
    margin-top: 24px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
}
.section-title {
    font-weight: bold;
    margin-top: 16px;
    margin-bottom: 12px;
    color: #2c3e50;
}
.form-label strong {
    min-width: 250px;
    display: inline-block;
}
/* --- Coronary Table Styles --- */
.table-coronary th {
    background-color: #f2f2f2;
    vertical-align: middle;
    text-align: center;
    line-height: 1.2; 
}
.table-coronary td {
    vertical-align: middle;
    padding: 8px; 
}
/* ปรับสไตล์ของช่องกรอกในตารางให้เล็กและชิดกัน */
.table-coronary .form-control-sm,
.table-coronary .form-select-sm { 
    padding: 0.25rem 0.4rem;
    height: calc(1.5em + 0.5rem + 2px);
}
.table-coronary .input-group-sm .input-group-text {
    padding: 0.25rem 0.4rem;
    font-size: 0.875rem;
}
.culprit-label {
    color: #dc3545; /* แดง */
    font-weight: bold;
    font-size: 0.75rem;
    margin-left: 5px;
}

/* ทำให้ตาราง responsive มากขึ้นโดยการจำกัด min-width */
@media (max-width: 992px) {
    .table-coronary {
        min-width: 700px; /* ลด min-width ลงเล็กน้อยเพื่อให้ scroll ได้ง่ายบน tablet/มือถือ */
    }
}
@media (max-width: 576px) {
    /* ปรับขนาดส่วนหัวข้อให้เล็กลงมาก ๆ สำหรับมือถือ */
    .table-coronary th {
        font-size: 0.7rem;
    }
    .table-coronary td {
        padding: 4px;
    }
}
/* สไตล์ปุ่มกดพื้นฐาน (สีขาว) */
    .btn-select {
        cursor: pointer;
        border-radius: 0.25rem;
        padding: 0.375rem 0.75rem;
        font-size: 0.95rem;
        text-align: center;
        border: 1px solid #ced4da;
        background-color: #fff;
        color: #212529;
        transition: all 0.2s;
        min-width: 100px;
        display: inline-block;
    }

    /* เมื่อปุ่มถูกเลือก (Checked) ให้เปลี่ยนเป็นสีเขียว */
    .btn-check:checked + .btn-select {
        background-color: #28a745 !important; /* สีเขียว Success */
        color: white !important;
        *
        box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
    }
    
#btnConclusion {
    background-color: white;
    border: 1px solid #969996ff;
    color: black;
    padding: 6px 12px;
    cursor: pointer;
}
#btnConclusion.active {
    background-color: #28a745;
    color: white;
}
.btn-select {
    background-color: white; 
    border: 1px solid #969996ff; 
    color: black;
    padding: 6px 12px; 
    cursor: pointer;
    border-radius: 4px;
    margin-right: 5px;
}

.btn-check:checked + .btn-select {
    background-color: #969996ff;
    color: white;
}

.btn-outline-custom {
    border: 1px solid #969996ff;
    background-color: white;
    color: #969996ff;
    padding: 6px 12px;
    cursor: pointer;
    border-radius: 4px;
    margin-bottom:5px;
}

.btn-selected {
    background-color: #28a745;
    color: white;
}
/* --- ปรับแต่งปุ่มให้รองรับทุกอุปกรณ์ --- */

/* 1. จัดการกลุ่มปุ่มให้ยืดหยุ่น */
.btn-group {
    display: flex !important;
    flex-wrap: wrap; /* ให้ปุ่มตัดขึ้นบรรทัดใหม่ได้ */
    gap: 5px; /* ระยะห่างระหว่างปุ่ม */
}

/* 2. ปรับแต่งปุ่มพื้นฐาน */
.btn-select {
    cursor: pointer;
    border: 1px solid #ced4da;
    background-color: #fff;
    color: #212529;
    padding: 8px 15px;
    font-size: 0.9rem;
    border-radius: 8px !important; /* ทำปุ่มให้มีความมน */
    transition: all 0.2s ease;
    flex: 1 1 auto; /* ให้ปุ่มยืดหยุ่นตามพื้นที่ */
    text-align: center;
    min-width: 100px;
}

/* 3. เมื่อปุ่มถูกเลือก */
.btn-check:checked + .btn-select {
    background-color: #19a974 !important;
    color: white !important;
    border-color: #19a974 !important;
    box-shadow: 0 4px 6px rgba(25, 169, 116, 0.2);
}

/* 4. Responsive Layout สำหรับแถวที่มี Label และปุ่ม */
@media (max-width: 768px) {
    .row.align-items-center {
        flex-direction: column; /* เปลี่ยนจากแนวนอนเป็นแนวตั้ง */
        align-items: flex-start !important;
    }
    
    .col-md-4, .col-md-8 {
        width: 100%;
        margin-bottom: 10px;
    }

    .btn-select {
        width: 100%; /* ในมือถือให้ปุ่มเต็มความกว้าง */
        margin-right: 0 !important;
    }
}


    /* Custom CSS สำหรับตารางแพทย์ */
    .cath-table-container {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        background: white;
        border: 1px solid #eef2f6;
    }
    
    .cath-table thead th {
        background-color: #f8f9fa;
        color: #495057;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e9ecef;
        vertical-align: middle;
    }

    /* Sticky Header */
    .cath-table thead {
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .cath-table td {
        vertical-align: middle;
        padding: 8px 12px;
        border-color: #f1f3f5;
    }

    .cath-table tr:hover td {
        background-color: #f8f9fa;
    }

    /* Territory Colors (แถบสีด้านซ้าย) */
    .row-rca { border-left: 5px solid #fd7e14; } /* ส้ม */
    .row-lad { border-left: 5px solid #dc3545; } /* แดง */
    .row-lcx { border-left: 5px solid #198754; } /* เขียว */

    /* Input Styling */
    .form-control-flush {
        border: 1px solid #e9ecef;
        border-radius: 6px;
        background-color: #fff;
        font-size: 0.85rem;
        text-align: center;
        padding: 4px 2px;
    }
    .form-control-flush:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
    }
    
    .form-select-flush {
        border: 1px solid #e9ecef;
        border-radius: 6px;
        font-size: 0.8rem;
        padding: 4px 20px 4px 8px; /* จัด padding ให้ลูกศรไม่ทับ */
        background-position: right 4px center;
    }

    .text-label-segment { font-size: 0.9rem; font-weight: 600; color: #343a40; }
    
    /* Culprit Switch Styling */
    .culprit-wrapper .form-check-input {
        cursor: pointer;
        width: 3em; 
        height: 1.5em;
    }
    .culprit-wrapper .form-check-input:checked {
        background-color: #dc3545;
        border-color: #dc3545;
    }
</style>
</head>
<body>
   <nav class="navbar navbar-light bg-white shadow-sm sticky-top mb-4">
        <div class="container d-flex justify-content-start">
            <button class="navbar-toggler border-0 me-2" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <i class="bi bi-heart-pulse-fill text-danger fs-3"></i>
            </a>

            <div class="offcanvas offcanvas-start border-0 " tabindex="-1" id="offcanvasNavbar"
                aria-labelledby="offcanvasNavbarLabel" style="width: 280px;">
                
                <div class="offcanvas-header text-white" style="background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);">
                    <h5 class="offcanvas-title fw-bold" id="offcanvasNavbarLabel">
                        <i class="bi bi-list me-2"></i>Menu
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>

                <div class="offcanvas-body d-flex flex-column" style="background: linear-gradient(180deg, #f0f8ff 0%, #ffffff 100%);">
                    <div class="mb-4">
                        <div class="d-flex align-items-center p-3 rounded-4 bg-white border border-primary-subtle shadow-sm">
                            <i class="bi bi-person-circle text-primary fs-2 me-3"></i>
                            <div class="overflow-hidden">
                                <span class="text-muted d-block small uppercase"
                                    style="font-size: 0.65rem; font-weight: 700;">USER LOGIN</span>
                                <span class="fw-bold text-dark text-truncate d-block"><?= htmlspecialchars($current_user) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="list-group list-group-flush mb-auto">
                        <a href="dashboard_full.php"
                            class="list-group-item list-group-item-action border-0 px-0 py-3 d-flex align-items-center bg-transparent">
                            <div class="bg-white rounded-3 p-2 me-3 shadow-sm">
                                <i class="bi bi-grid-1x2 text-primary"></i>
                            </div>
                            <span class="fw-semibold text-secondary">Dashboard</span>
                        </a>
                        <a href="index.php"
                            class="list-group-item list-group-item-action border-0 px-0 py-3 d-flex align-items-center bg-transparent">
                            <div class="bg-white rounded-3 p-2 me-3 shadow-sm">
                                <i class="bi bi-table text-primary"></i>
                            </div>
                            <span class="fw-semibold text-secondary">รายชื่อผู้ป่วย</span>
                        </a>
                        <a href="patient_form.php"
                            class="list-group-item list-group-item-action border-0 px-0 py-3 d-flex align-items-center bg-transparent">
                            <div class="bg-white rounded-3 p-2 me-3 shadow-sm">
                                <i class="bi bi-person-plus text-primary"></i>
                            </div>
                            <span class="fw-semibold text-secondary">ลงทะเบียนผู้ป่วย</span>
                        </a>
                        <a href="death_form.php"
                            class="list-group-item list-group-item-action border-0 px-0 py-3 d-flex align-items-center bg-transparent">
                            <div class="bg-white rounded-3 p-2 me-3 shadow-sm">
                                <i class="bi bi-heartbreak text-danger"></i>
                            </div>
                            <span class="fw-semibold text-secondary">ลงข้อมูลคนไข้เสียชีวิต</span>
                        </a>
                    </div>

                    <div class="mt-4 pt-4 border-top">
                        <a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill py-2 shadow-sm fw-bold">
                            <i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ
                        </a>
                        <div class="text-center mt-3">
                            <small class="text-muted" style="font-size: 0.7rem;">Adult Cardiology v1.0</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
<div class="container py-4">
    <div class="top-bar d-flex justify-content-between align-items-center">
        <div class="hospital-title">
              STEMI Registry <span class="text-danger">(ระบบจัดเก็บและติดตามตัวชี้วัดคุณภาพการดูแลผู้ป่วยโรคหัวใจขาดเลือด)</span>
        </div>
        
    </div>

    <div class="form-section">
       <div class="card shadow-sm border-0 mb-4 overflow-hidden rounded-4">
    <div class="card-body p-2 bg-white">
        <ul class="nav nav-pills nav-fill flex-nowrap overflow-auto pb-1" id="mainNav" style="scrollbar-width: none;">
            
            <li class="nav-item">
                <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 <?= basename($_SERVER['PHP_SELF']) == 'patient_form.php' ? 'active shadow-sm' : 'text-secondary' ?>" 
                   href="patient_form.php">
                    <i class="bi bi-person-vcard fs-5"></i>
                    <span class="small fw-bold">ข้อมูลผู้ป่วย</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 <?= basename($_SERVER['PHP_SELF']) == 'history_risk_factor.php' ? 'active shadow-sm' : 'text-secondary' ?>" 
                   href="history_risk_factor.php">
                    <i class="bi bi-clipboard-pulse fs-5"></i>
                    <span class="small fw-bold">History & Risk</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 <?= basename($_SERVER['PHP_SELF']) == 'Symptoms_diagnosis.php' ? 'active shadow-sm' : 'text-secondary' ?>" 
                   href="Symptoms_diagnosis.php">
                    <i class="bi bi-heart-pulse fs-5"></i>
                    <span class="small fw-bold">Diagnosis</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 <?= basename($_SERVER['PHP_SELF']) == 'Medication.php' ? 'active shadow-sm' : 'text-secondary' ?>" 
                   href="Medication.php">
                    <i class="bi bi-capsule fs-5"></i>
                    <span class="small fw-bold">Medication</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 <?= basename($_SERVER['PHP_SELF']) == 'cardiac_cath.php' ? 'active shadow-sm' : 'text-secondary' ?>" 
                   href="cardiac_cath.php">
                    <i class="bi bi-activity fs-5"></i>
                    <span class="small fw-bold">Cardiac Cath</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 <?= basename($_SERVER['PHP_SELF']) == 'treatment_results.php' ? 'active shadow-sm' : 'text-secondary' ?>" 
                   href="treatment_results.php">
                    <i class="bi bi-clipboard-check fs-5"></i>
                    <span class="small fw-bold">Result</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 <?= basename($_SERVER['PHP_SELF']) == 'discharge.php' ? 'active shadow-sm' : 'text-secondary' ?>" 
                   href="discharge.php">
                    <i class="bi bi-door-open fs-5"></i>
                    <span class="small fw-bold">Discharge</span>
                </a>
            </li>

        </ul>
    </div>
</div>
  <form method="POST">
  <input type="hidden" id="ref_door_hatyai_dt" value="<?= htmlspecialchars($ref_door) ?>">
  <input type="hidden" id="ref_onset_dt" value="<?= htmlspecialchars($ref_onset) ?>">
  <input type="hidden" id="ref_fmc_dt" value="<?= htmlspecialchars($ref_fmc) ?>"> 
  <input type="hidden" id="ref_dx_dt" value="<?= htmlspecialchars($ref_dx) ?>"> 
             

<div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
    <h6 class="text-primary fw-bold mb-3 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-speedometer2 me-2 fs-5"></i> PCI Status
    </h6>

    <?php 
    // รับค่าเก่า (ถ้ามี)
    $pci_status_arr = $_POST['pci_status'] ?? []; 
    ?>

    <div class="d-flex flex-wrap gap-2">

        <div>
            <input type="checkbox" class="btn-check pci-status-opt" id="status_emergency" name="pci_status[]" value="Emergency" 
                   <?= in_array('Emergency', $pci_status_arr) ? 'checked' : '' ?> 
                   autocomplete="off" onchange="selectSinglePci(this)">
            <label class="btn btn-outline-danger rounded-pill px-3 shadow-sm" for="status_emergency">
                <i class="bi bi-exclamation-octagon-fill me-1"></i> Emergency
            </label>
        </div>

        <div>
            <input type="checkbox" class="btn-check pci-status-opt" id="status_urgent" name="pci_status[]" value="Urgent" 
                   <?= in_array('Urgent', $pci_status_arr) ? 'checked' : '' ?> 
                   autocomplete="off" onchange="selectSinglePci(this)">
            <label class="btn btn-outline-warning text-dark rounded-pill px-3 shadow-sm" for="status_urgent">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> Urgent
            </label>
        </div>

        <div>
            <input type="checkbox" class="btn-check pci-status-opt" id="status_salvage" name="pci_status[]" value="Salvage" 
                   <?= in_array('Salvage', $pci_status_arr) ? 'checked' : '' ?> 
                   autocomplete="off" onchange="selectSinglePci(this)">
            <label class="btn btn-outline-dark rounded-pill px-3 shadow-sm" for="status_salvage">
                <i class="bi bi-life-preserver me-1"></i> Salvage
            </label>
        </div>

        <div>
            <input type="checkbox" class="btn-check pci-status-opt" id="status_elective" name="pci_status[]" value="Elective" 
                   <?= in_array('Elective', $pci_status_arr) ? 'checked' : '' ?> 
                   autocomplete="off" onchange="selectSinglePci(this)">
            <label class="btn btn-outline-primary rounded-pill px-3 shadow-sm" for="status_elective">
                <i class="bi bi-calendar-check me-1"></i> Elective
            </label>
        </div>

    </div>
</div>

<div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
    <h6 class="text-primary fw-bold mb-3 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-activity me-2 fs-5"></i> Cardiogenic Shock at Start of PCI
    </h6>

    <div class="d-flex gap-2">
        
        <div>
            <input type="radio" class="btn-check" name="shock" id="shock_no" value="No" checked autocomplete="off">
            <label class="btn btn-outline-secondary rounded-pill px-3 shadow-sm" for="shock_no">
                <i class="bi bi-x-circle me-1"></i> NO
            </label>
        </div>

        <div>
            <input type="radio" class="btn-check" name="shock" id="shock_yes" value="Yes" autocomplete="off">
            <label class="btn btn-outline-success rounded-pill px-3 shadow-sm" for="shock_yes">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> YES
            </label>
        </div>

    </div>
</div>

 
<div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
    <h6 class="text-primary fw-bold mb-4 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-arrow-return-right me-2 fs-5"></i> PCI Indication
    </h6>

    <div class="row g-3 mb-4">
        
        <div class="col-md-6 col-12">
            <input type="checkbox" class="btn-check indication-toggle" id="ind_primary" name="indication[]" value="Primary PCI" 
                   onchange="toggleIndicationDetails('primary')">
            <label class="btn btn-outline-danger w-100 p-3 h-100 text-start shadow-sm d-flex align-items-center" for="ind_primary">
                <div class="bg-danger bg-opacity-10 p-2 rounded-circle me-3 text-danger">
                    <i class="bi bi-lightning-fill fs-4"></i>
                </div>
                <div>
                    <div class="fw-bold">1. Primary PCI</div>
                    <div class="small text-muted">for STEMI (PPCI)</div>
                </div>
            </label>
        </div>

        <div class="col-md-6 col-12">
            <input type="checkbox" class="btn-check indication-toggle" id="ind_rescue" name="indication[]" value="Rescue PCI" 
                   onchange="toggleIndicationDetails('rescue')">
            <label class="btn btn-outline-warning text-dark w-100 p-3 h-100 text-start shadow-sm d-flex align-items-center" for="ind_rescue">
                <div class="bg-warning bg-opacity-10 p-2 rounded-circle me-3 text-dark">
                    <i class="bi bi-life-preserver fs-4"></i>
                </div>
                <div>
                    <div class="fw-bold">2. Rescue PCI</div>
                    <div class="small text-muted">for STEMI (Failed Fibrinolysis)</div>
                </div>
            </label>
        </div>

        <div class="col-md-6 col-12">
            <input type="checkbox" class="btn-check indication-toggle" id="ind_pharmaco" name="indication[]" value="Pharmacoinvasive">
            <label class="btn btn-outline-primary w-100 p-3 h-100 text-start shadow-sm d-flex align-items-center" for="ind_pharmaco">
                <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3 text-primary">
                    <i class="bi bi-capsule fs-4"></i>
                </div>
                <div>
                    <div class="fw-bold">3. Pharmacoinvasive</div>
                    <div class="small text-muted">Strategy (TNK/SK)</div>
                </div>
            </label>
        </div>

        <div class="col-md-6 col-12">
            <input type="checkbox" class="btn-check indication-toggle" id="ind_routine" name="indication[]" value="Routine early PCI">
            <label class="btn btn-outline-primary w-100 p-3 h-100 text-start shadow-sm d-flex align-items-center" for="ind_routine">
                <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3 text-primary">
                    <i class="bi bi-clock-history fs-4"></i>
                </div>
                <div>
                    <div class="fw-bold">4. Routine early PCI</div>
                    <div class="small text-muted">After Fibrinolysis</div>
                </div>
            </label>
        </div>

        <div class="col-md-6 col-12">
            <input type="checkbox" class="btn-check indication-toggle" id="ind_recent" name="indication[]" value="PCI for Recent STEMI"
                   onchange="toggleIndicationDetails('recent')">
            <label class="btn btn-outline-info text-dark w-100 p-3 h-100 text-start shadow-sm d-flex align-items-center" for="ind_recent">
                <div class="bg-info bg-opacity-10 p-2 rounded-circle me-3 text-dark">
                    <i class="bi bi-calendar-week fs-4"></i>
                </div>
                <div>
                    <div class="fw-bold">5. PCI for Recent STEMI</div>
                    <div class="small text-muted">Late presentation</div>
                </div>
            </label>
        </div>

        <div class="col-md-6 col-12">
            <input type="checkbox" class="btn-check indication-toggle" id="ind_nstemi" name="indication[]" value="PCI for NSTEMI or UA"
                   onchange="toggleIndicationDetails('nstemi')">
            <label class="btn btn-outline-secondary text-dark w-100 p-3 h-100 text-start shadow-sm d-flex align-items-center" for="ind_nstemi">
                <div class="bg-secondary bg-opacity-10 p-2 rounded-circle me-3 text-dark">
                    <i class="bi bi-heart-half fs-4"></i>
                </div>
                <div>
                    <div class="fw-bold">6. PCI for NSTEMI / UA</div>
                    <div class="small text-muted">Non-ST Elevation</div>
                </div>
            </label>
        </div>
    </div>

    <div id="detail_primary" class="detail-section p-4 mb-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded-3 animate__animated animate__fadeIn" style="display: none;">
        <h6 class="text-danger fw-bold border-bottom border-danger border-opacity-25 pb-2 mb-3">
            <i class="bi bi-pencil-square me-2"></i> รายละเอียด Primary PCI
        </h6>
        
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label small fw-bold">CAG Detail</label>
                <textarea class="form-control" rows="2" name="cag_primary" placeholder="ผลฉีดสี..."></textarea>
            </div>
            <div class="col-12">
                <label class="form-label small fw-bold">PPCI Detail</label>
                <textarea class="form-control" rows="2" name="ppci" placeholder="รายละเอียดการทำหัตถการ..."></textarea>
            </div>
            
            <div class="col-12"><hr class="border-danger opacity-25"></div>
            <div class="col-12"><span class="badge bg-danger mb-2">Time Intervals (Minutes)</span></div>

            <div class="col-md-4 col-6">
                <label class="form-label small text-muted">Onset to FMC</label>
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control" name="onset_to_fmc_min">
                    <span class="input-group-text">min</span>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <label class="form-label small text-muted">Door to Balloon</label>
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control" name="door_to_b_min">
                    <span class="input-group-text">min</span>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <label class="form-label small text-muted">EKG to Dx</label>
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control" name="ekg_to_dx_min">
                    <span class="input-group-text">min</span>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <label class="form-label small text-muted">FMC to Device</label>
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control" name="fmc_to_device_min" id="fmc_to_device_min">
                    <span class="input-group-text">min</span>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <label class="form-label small text-muted">STEMI Dx to Device</label>
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control" name="stemi_dx_to_device_min" id="stemi_dx_to_device_min">
                    <span class="input-group-text">min</span>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <label class="form-label small text-muted">FMC to Refer</label>
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control" name="fmc_to_refer_min">
                    <span class="input-group-text">min</span>
                </div>
            </div>
        </div>
    </div>

    <div id="detail_rescue" class="detail-section p-4 mb-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3 animate__animated animate__fadeIn" style="display: none;">
        <h6 class="text-dark fw-bold border-bottom border-warning border-opacity-25 pb-2 mb-3">
            <i class="bi bi-pencil-square me-2"></i> รายละเอียด Rescue PCI
        </h6>
        <div class="row g-3">
             <div class="col-12">
                <label class="form-label small fw-bold">Activated Cathlab</label>
                <input type="text" class="form-control" name="rescue_activated" placeholder="ระบุเวลา/ผู้ตาม...">
            </div>
            <div class="col-12">
                <label class="form-label small fw-bold">CAG Detail</label>
                <textarea class="form-control" rows="2" name="cag_rescue" placeholder="ผลฉีดสี..."></textarea>
            </div>
            
            <div class="col-12"><hr class="border-warning opacity-25"></div>
            
            <div class="col-md-3 col-6">
                <label class="form-label small text-muted">Door to Balloon</label>
                <input type="number" class="form-control form-control-sm" name="rescue_door_to_b">
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small text-muted">Onset to Balloon</label>
                <input type="number" class="form-control form-control-sm" name="rescue_onset_to_b">
            </div>
             <div class="col-md-3 col-6">
                <label class="form-label small text-muted">FMC to Device</label>
                <input type="number" class="form-control form-control-sm" name="rescue_fmc_to_device">
            </div>
             <div class="col-md-3 col-6">
                <label class="form-label small text-muted">STEMI Dx to Device</label>
                <input type="number" class="form-control form-control-sm" name="rescue_stemi_dx_to_device">
            </div>
        </div>
    </div>

    <div id="detail_recent" class="detail-section p-4 mb-3 bg-info bg-opacity-10 border border-info border-opacity-25 rounded-3 animate__animated animate__fadeIn" style="display: none;">
        <h6 class="text-dark fw-bold mb-3"><i class="bi bi-clock me-2"></i> Timeframe (Recent STEMI)</h6>
        <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="recent_when" id="recent_48" value="48h">
            <label class="btn btn-outline-info text-dark" for="recent_48">≤ 48 ชม.</label>

            <input type="radio" class="btn-check" name="recent_when" id="recent_72" value="72h">
            <label class="btn btn-outline-info text-dark" for="recent_72">≤ 72 ชม.</label>

            <input type="radio" class="btn-check" name="recent_when" id="recent_after72" value=">72h">
            <label class="btn btn-outline-info text-dark" for="recent_after72">> 72 ชม.</label>
        </div>
    </div>

    <div id="detail_nstemi" class="detail-section p-4 mb-3 bg-light border rounded-3 animate__animated animate__fadeIn" style="display: none;">
        <h6 class="text-secondary fw-bold border-bottom pb-2 mb-3">
            <i class="bi bi-clipboard-data me-2"></i> รายละเอียด NSTEMI / UA
        </h6>
        
        <div class="mb-3">
            <label class="form-label small fw-bold">ระยะเวลา (Timing)</label>
            <div class="d-flex flex-wrap gap-2">
                <input type="radio" class="btn-check" name="nstemi_when" id="nstemi_2h" value="2h">
                <label class="btn btn-outline-secondary rounded-pill btn-sm px-3" for="nstemi_2h">≤ 2 ชม. (Immediate)</label>

                <input type="radio" class="btn-check" name="nstemi_when" id="nstemi_24h" value="24h">
                <label class="btn btn-outline-secondary rounded-pill btn-sm px-3" for="nstemi_24h">≤ 24 ชม. (Early)</label>

                <input type="radio" class="btn-check" name="nstemi_when" id="nstemi_72h" value="72h">
                <label class="btn btn-outline-secondary rounded-pill btn-sm px-3" for="nstemi_72h">≤ 72 ชม. (Delayed)</label>
                
                <input type="radio" class="btn-check" name="nstemi_when" id="nstemi_discharge" value="72h discharge">
                <label class="btn btn-outline-secondary rounded-pill btn-sm px-3" for="nstemi_discharge">> 72 ชม. - Discharge</label>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small fw-bold">วัน/เวลา นัด (Appointment)</label>
                <input type="datetime-local" class="form-control" name="nstemi_date">
            </div>
            <div class="col-md-12">
                <label class="form-label small fw-bold">CAG Detail</label>
                <textarea class="form-control" rows="2" name="cag_nstemi" placeholder="ผลฉีดสี..."></textarea>
            </div>
        </div>
    </div>

</div>


    

<div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
    <h6 class="text-primary fw-bold mb-4 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-clipboard-check me-2 fs-5"></i> Procedure Information
    </h6>

    <div class="row align-items-center mb-4">
        <div class="col-md-4">
            <label class="form-label fw-bold text-secondary mb-0">Procedure Success</label>
            
        </div>
        <div class="col-md-8">
            <div class="d-flex gap-2">
                <div class="flex-fill">
                    <input type="radio" class="btn-check" name="proc_success" id="proc_no" value="No" checked onchange="toggleProcTimes(false)">
                    <label class="btn btn-outline-secondary w-100 rounded-pill shadow-sm" for="proc_no">
                        <i class="bi bi-x-circle me-1"></i> No
                    </label>
                </div>
                <div class="flex-fill">
                    <input type="radio" class="btn-check" name="proc_success" id="proc_yes" value="Yes" onchange="toggleProcTimes(true)">
                    <label class="btn btn-outline-success w-100 rounded-pill shadow-sm" for="proc_yes">
                        <i class="bi bi-check-circle me-1"></i> Yes
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div id="procedure_times" class="animate__animated animate__fadeIn" style="display:none;">
        <div class="card bg-light border-0">
            <div class="card-body">
                <h6 class="fw-bold text-primary small mb-3 border-bottom pb-2">
                    <i class="bi bi-clock-history me-1"></i> Timeline & KPI
                </h6>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Door in Date/Time (รพ.ที่ทำหัตถการ)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-primary"><i class="bi bi-door-open"></i></span>
                            <input type="datetime-local" class="form-control" name="door_in_datetime" id="door_in_datetime">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Puncture Time</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-secondary"><i class="bi bi-eyedropper"></i></span>
                            <input type="datetime-local" class="form-control" name="puncture_time">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-success">Time at First Device (เปิดหลอดเลือด)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-success text-white"><i class="bi bi-heart-pulse"></i></span>
                            <input type="datetime-local" class="form-control border-success" name="first_device_time" id="first_device_time">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Finish Time (Final Angio)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-secondary"><i class="bi bi-flag"></i></span>
                            <input type="datetime-local" class="form-control" name="finish_time">
                        </div>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="p-2 border rounded bg-white shadow-sm text-center h-100 d-flex flex-column justify-content-center">
                            <label class="small text-muted fw-bold mb-1">Door to Device</label>
                            <div class="d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-stopwatch text-primary"></i>
                                <input type="number" name="door_to_device" id="door_to_device_min" class="form-control form-control-sm border-0 bg-transparent text-center fw-bold fs-5 text-primary p-0" style="width: 80px;" readonly placeholder="-" value="">
                                <span class="small text-muted">min</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 border rounded bg-white shadow-sm text-center h-100 d-flex flex-column justify-content-center">
                            <label class="small text-muted fw-bold mb-1">Onset to Device</label>
                            <div class="d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-activity text-danger"></i>
                                <input type="number" name="onset_to_device" id="onset_to_device_min" class="form-control form-control-sm border-0 bg-transparent text-center fw-bold fs-5 text-danger p-0" style="width: 80px;" readonly placeholder="-" value="">
                                <span class="small text-muted">min</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

      
<div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
    <h6 class="text-primary fw-bold mb-4 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-gear-wide-connected me-2 fs-5"></i> Mechanical Circulatory Support
    </h6>

    <div class="row align-items-center mb-4">
        <div class="col-md-5">
            <strong class="text-secondary small d-flex align-items-center">
                <i class="bi bi-heart-pulse me-2 text-danger opacity-75"></i> IABP
            </strong>
            
        </div>
        <div class="col-md-7">
            <div class="d-flex gap-2">
                <div class="flex-fill">
                    <input type="radio" class="btn-check" name="iabp" id="iabp_no" value="No" checked>
                    <label class="btn btn-outline-secondary w-100 rounded-pill shadow-sm" for="iabp_no">NO</label>
                </div>
                <div class="flex-fill">
                    <input type="radio" class="btn-check" name="iabp" id="iabp_yes" value="Yes">
                    <label class="btn btn-outline-success w-100 rounded-pill shadow-sm" for="iabp_yes">YES</label>
                </div>
            </div>
        </div>
    </div>

    <div class="row align-items-start">
        <div class="col-md-5 pt-2">
            <strong class="text-secondary small d-flex align-items-center">
                <i class="bi bi-robot me-2 text-primary opacity-75"></i>Other Support
            </strong>
            
        </div>
        <div class="col-md-7">
            <div class="d-flex gap-2 mb-3">
                <div class="flex-fill">
                    <input type="radio" class="btn-check" name="mc_support" id="mc_no" value="No" checked 
                           onchange="toggleMcsOptions(false)">
                    <label class="btn btn-outline-secondary w-100 rounded-pill shadow-sm" for="mc_no">NO</label>
                </div>
                <div class="flex-fill">
                    <input type="radio" class="btn-check" name="mc_support" id="mc_yes" value="Yes" 
                           onchange="toggleMcsOptions(true)">
                    <label class="btn btn-outline-success w-100 rounded-pill shadow-sm" for="mc_yes">YES</label>
                </div>
            </div>

            <div id="mc_types" class="p-3 bg-light border rounded-3 animate__animated animate__fadeIn" style="display:none;">
                <label class="form-label small fw-bold text-secondary mb-2">เลือกชนิดอุปกรณ์ (Select Device)</label>
                
                <div class="d-flex flex-wrap gap-2">
                    <div>
                        <input type="checkbox" class="btn-check" id="mc_ecmo" name="mc_type[]" value="ECMO">
                        <label class="btn btn-outline-primary bg-white btn-sm rounded-pill px-3" for="mc_ecmo">
                            <i class="bi bi-lungs me-1"></i> ECMO
                        </label>
                    </div>

                    <div>
                        <input type="checkbox" class="btn-check" id="mc_lvad" name="mc_type[]" value="LVAD">
                        <label class="btn btn-outline-primary bg-white btn-sm rounded-pill px-3" for="mc_lvad">
                            <i class="bi bi-hdd-network me-1"></i> LVAD
                        </label>
                    </div>

                    <div>
                        <input type="checkbox" class="btn-check" id="mc_other" name="mc_type[]" value="Other" 
                               onchange="toggleMcsOtherInput()">
                        <label class="btn btn-outline-secondary bg-white btn-sm rounded-pill px-3" for="mc_other">
                            <i class="bi bi-three-dots me-1"></i> Other
                        </label>
                    </div>
                </div>

                <div id="mc_other_text_div" class="mt-2" style="display:none;">
                    <input type="text" class="form-control form-control-sm" id="mc_other_text" name="mc_other_text" 
                           placeholder="ระบุชื่ออุปกรณ์อื่นๆ...">
                </div>
            </div>
        </div>
    </div>

</div>

     
           <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
    <h6 class="text-primary fw-bold mb-4 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-stopwatch-fill me-2 fs-5"></i> Cath Lab Arrival & Procedure Timing
    </h6>

    <div class="row g-4 mb-4">
        
        <div class="col-md-6">
            <div class="p-3 bg-light rounded-3 border h-100">
                <h6 class="fw-bold small text-secondary mb-3 d-flex align-items-center">
                    <i class="bi bi-door-open-fill me-2 text-primary"></i> 1. Arrived Cath Lab
                </h6>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small text-muted">Date</label>
                        <input type="date" name="arrived_date" id="arrived_date" class="form-control" value="<?= htmlspecialchars($arrived_date ?? '') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted">Time</label>
                        <input type="time" name="arrived_time" id="arrived_time" class="form-control" value="<?= htmlspecialchars($arrived_time ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="p-3 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25 h-100">
                <h6 class="fw-bold small text-success mb-3 d-flex align-items-center">
                    <i class="bi bi-heart-pulse-fill me-2"></i> 2. Pass Wire Time
                </h6>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small text-muted">Date</label>
                        <input type="date" name="pass_wire_date" id="pass_wire_date" class="form-control border-success border-opacity-25" value="<?= htmlspecialchars($_POST['pass_wire_date'] ?? '') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted">Time</label>
                        <input type="time" name="pass_wire_time" id="pass_wire_time" class="form-control border-success border-opacity-25" value="<?= htmlspecialchars($_POST['pass_wire_time'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

         <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
    <h6 class="text-primary fw-bold mb-4 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-heart-pulse-fill me-2 fs-5"></i> CAD Presentation Indication
    </h6>

    <div class="mb-4">
        <div class="d-flex flex-wrap gap-2">
            <?php
            $cad = ['STEMI', 'NSTEMI', 'Unstable angina', 'Other'];
            // ป้องกัน undefined variable
            $cur_cad = $cad_presentation ?? '';
            
            foreach ($cad as $c) {
                $checked = ($cur_cad === $c) ? 'checked' : '';
                $id = 'cad_' . str_replace(' ', '_', $c);
                // กำหนดสีปุ่มตามความรุนแรง (STEMI/NSTEMI = แดง/ส้ม)
                $btnClass = ($c === 'Other') ? 'btn-outline-secondary' : 'btn-outline-danger';
                
                echo "
                <div class='flex-fill'>
                    <input type='radio' class='btn-check' name='cad_presentation' id='$id' value='$c' $checked onclick='toggleCADOther(this)'>
                    <label class='btn $btnClass w-100 rounded-pill shadow-sm' for='$id'>$c</label>
                </div>";
            }
            ?>
        </div>

        <div id="cad_other_div" class="mt-2 animate__animated animate__fadeIn" style="display: <?= ($cur_cad === 'Other') ? 'block' : 'none' ?>;">
            <div class="input-group">
                <span class="input-group-text bg-light border-0"><i class="bi bi-pencil"></i></span>
                <input type="text" id="cad_other_text" name="cad_other_text" class="form-control bg-light border-0" 
                       placeholder="ระบุอาการอื่นๆ..." 
                       value="<?= htmlspecialchars($cad_other_text ?? '') ?>">
            </div>
        </div>
    </div>

    <hr class="text-muted opacity-25 my-4">

    <h6 class="text-primary fw-bold mb-4 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-bezier2 me-2 fs-5"></i> Arterial Access
    </h6>

    <div class="row g-4 align-items-center mb-4">
        <div class="col-md-6">
            <label class="form-label fw-bold small text-secondary">Access Site</label>
            <div class="d-flex flex-wrap gap-2">
                <?php
                $access_sites = ['Femoral a.', 'Radial a.', 'Brachial a.'];
                $cur_site = $access_site ?? '';
                
                foreach ($access_sites as $site) {
                    $checked = ($cur_site === $site) ? 'checked' : '';
                    $id = 'site_' . str_replace([' ', '.'], '_', $site);
                    echo "
                    <div class='flex-fill'>
                        <input type='radio' class='btn-check' name='access_site' id='$id' value='$site' $checked>
                        <label class='btn btn-outline-primary w-100 rounded-pill shadow-sm' for='$id'>$site</label>
                    </div>";
                }
                ?>
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-bold small text-secondary">Site Cross-over?</label>
            <div class="d-flex gap-2">
                <?php
                $crossovers = ['No', 'Yes'];
                $cur_cross = $access_crossover ?? '';
                
                foreach ($crossovers as $co) {
                    $checked = ($cur_cross === $co) ? 'checked' : '';
                    $id = 'cross_' . $co;
                    $btnClass = ($co === 'Yes') ? 'btn-outline-success' : 'btn-outline-secondary';
                    echo "
                    <div class='flex-fill'>
                        <input type='radio' class='btn-check' name='access_crossover' id='$id' value='$co' $checked>
                        <label class='btn $btnClass w-100 rounded-pill shadow-sm' for='$id'>$co</label>
                    </div>";
                }
                ?>
            </div>
        </div>
    </div>

    <hr class="text-muted opacity-25 my-4">

    <h6 class="text-primary fw-bold mb-4 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-camera-reels me-2 fs-5"></i> Coronary Angiogram
    </h6>

    <div class="row g-4 align-items-center">
        <div class="col-md-6">
            <label class="form-label fw-bold small text-secondary">Dominance</label>
            <div class="d-flex flex-wrap gap-2">
                <?php
                $dominance_options = ['Right', 'Left', 'Co-dominant'];
                $cur_dom = $dominance ?? '';
                
                foreach ($dominance_options as $dom) {
                    $checked = ($cur_dom === $dom) ? 'checked' : '';
                    $id = 'dom_' . str_replace(['-', ' '], '_', $dom);
                    echo "
                    <div class='flex-fill'>
                        <input type='radio' class='btn-check' name='dominance' id='$id' value='$dom' $checked>
                        <label class='btn btn-outline-dark w-100 rounded-pill shadow-sm' for='$id'>$dom</label>
                    </div>";
                }
                ?>
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-bold small text-secondary">Normal / Non-significant CAD</label>
            <div class="d-flex gap-2">
                <?php
                $cad_options = ['No', 'Yes'];
                $cur_non_sig = $nonsignificant_cad ?? '';
                
                foreach ($cad_options as $cad_opt) {
                    $checked = ($cur_non_sig === $cad_opt) ? 'checked' : '';
                    $id = 'cad_opt_' . $cad_opt;
                    // Yes = เขียว (เพราะ Normal คือดี), No = เทา
                    $btnClass = ($cad_opt === 'Yes') ? 'btn-outline-success' : 'btn-outline-secondary';
                    echo "
                    <div class='flex-fill'>
                        <input type='radio' class='btn-check' name='nonsignificant_cad' id='$id' value='$cad_opt' $checked>
                        <label class='btn $btnClass w-100 rounded-pill shadow-sm' for='$id'>$cad_opt</label>
                    </div>";
                }
                ?>
            </div>
        </div>
    </div>

</div><br>
                
                <div class="mb-3 row justify-content-center">
                    <div class="col-md-10 text-center">
                        <label class="form-label mb-2 text-primary"><strong>Coronary tree segments annotation</strong></label>
                        <img src="img/Screenshot 2025-11-10 092437.jpg" alt="Coronary tree segmentation diagram" 
                             class="img-fluid" 
                             style="width:100%; max-height:500px; border:1px solid #ccc; border-radius:6px;">
                          
                    </div>
                </div>
                
               <div class="mt-4">
    <div class="d-flex justify-content-between align-items-end mb-3">
       
       
            <div class="d-flex align-items-center bg-white p-2 rounded-pill shadow-sm border" style="max-width: fit-content;">
    <span class="fw-bold text-muted small me-3 ps-2"><i class="bi bi-diagram-3-fill"></i> DOMINANCE:</span>
    
    <div class="btn-group" role="group">
        <input type="radio" class="btn-check" name="dominance" id="dom_right" value="Right" 
               <?= ($dominance ?? 'Right') == 'Right' ? 'checked' : '' ?> onchange="updateDominanceLabels()">
        <label class="btn btn-outline-warning text-dark fw-bold px-3 rounded-start-pill" for="dom_right">Right</label>

        <input type="radio" class="btn-check" name="dominance" id="dom_left" value="Left" 
               <?= ($dominance ?? '') == 'Left' ? 'checked' : '' ?> onchange="updateDominanceLabels()">
        <label class="btn btn-outline-success text-dark fw-bold px-3" for="dom_left">Left</label>

        <input type="radio" class="btn-check" name="dominance" id="dom_co" value="Co" 
               <?= ($dominance ?? '') == 'Co' ? 'checked' : '' ?> onchange="updateDominanceLabels()">
        <label class="btn btn-outline-primary text-dark fw-bold px-3 rounded-end-pill" for="dom_co">Co</label>
    </div>
</div>
        </div>
    

    <div class="cath-table-container">
        <div class="table-responsive">
            <table class="table cath-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 20%; padding-left: 20px;">Segment</th>
                        <th class="text-center" style="width: 5%;">Culprit</th>
                        
                        <th class="text-center bg-light border-start" style="width: 8%;">% Stenosis</th>
                        <th class="text-center bg-light" style="width: 8%;">TIMI (Pre)</th>
                        <th class="text-center bg-light border-end" style="width: 12%;">Lesion Type</th>
                        
                        <th class="text-center" style="width: 10%;">Method</th>
                        <th class="text-center" style="width: 12%;">Device (mm)<br><span class="fw-light text-muted" style="font-size:0.65rem">Diameter x Length</span></th>
                        <th class="text-center" style="width: 8%;">Pressure<br><span class="fw-light text-muted" style="font-size:0.65rem">(atm)</span></th>
                        <th class="text-center" style="width: 9%;">Dilate</th>
                        
                        <th class="text-center bg-light border-start" style="width: 8%;">Resid %</th>
                        <th class="text-center bg-light" style="width: 8%;">TIMI (Post)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Mapping Segment
                    $segments_map = [
                        1 => '1. Proximal RCA', 2 => '2. Mid RCA', 3 => '3. Distal RCA', 
                        4 => '4. PDA (Right Dom)', 16 => '16. PLB (Right Dom)',
                        5 => '5. Left Main', 6 => '6. Proximal LAD', 7 => '7. Mid LAD', 
                        8 => '8. Distal LAD', 9 => '9. Diagonal 1', 10 => '10. Diagonal 2',
                        11 => '11. Proximal LCx', 12 => '12. OM 1', 13 => '13. Distal LCx', 
                        14 => '14. OM 2', 15 => '15. PDA/PLB (Left Dom)'
                    ];

                    foreach ($segments_map as $i => $name):
                        // Prepare Data
                        $d = $segment_data[$i] ?? [];
                        
                        // Clean Data
                        $pct = is_array($d['percent'] ?? '') ? '' : htmlspecialchars($d['percent'] ?? '');
                        $size = is_array($d['device_size'] ?? '') ? '' : htmlspecialchars($d['device_size'] ?? '');
                        $atm = is_array($d['pressure_atm'] ?? '') ? '' : htmlspecialchars($d['pressure_atm'] ?? '');
                        $resid = is_array($d['residual_stenosis'] ?? '') ? '' : htmlspecialchars($d['residual_stenosis'] ?? '');
                        
                        // Selectors
                        $timi_pre = $d['timi_pre'] ?? '3';
                        $lesion = $d['lesion_type'] ?? '';
                        $method = $d['method'] ?? '';
                        $dilate = $d['post_dilate'] ?? '';
                        $timi_post = $d['timi_post'] ?? '3';
                        
                        // Determine Row Color Class
                        $row_class = '';
                        if($i<=4 || $i==16) $row_class = 'row-rca';
                        elseif($i>=5 && $i<=10) $row_class = 'row-lad';
                        else $row_class = 'row-lcx';
                    ?>
                    <tr id="row_seg_<?= $i ?>" class="<?= $row_class ?>">
                        <td style="padding-left: 20px;">
                            <span class="text-label-segment" id="label_seg_<?= $i ?>"><?= $name ?></span>
                        </td>

                        <td class="text-center culprit-wrapper">
                            <div class="form-check form-switch d-flex justify-content-center">
                                <input class="form-check-input" type="checkbox" name="seg_<?= $i ?>_culprit" value="1" title="Culprit Lesion" <?= !empty($d['culprit']) ? 'checked' : '' ?>>
                            </div>
                        </td>

                        <td class="bg-light border-start">
                            <input type="number" name="seg_<?= $i ?>_percent" class="form-control form-control-flush sten-input" placeholder="-" value="<?= $pct ?>">
                        </td>
                        <td class="bg-light">
                            <select name="seg_<?= $i ?>_timi_pre" class="form-select form-select-flush text-center timi-pre-select">
                                <option value="3" <?= ($timi_pre=='3')?'selected':'' ?>>3</option>
                                <option value="2" <?= ($timi_pre=='2')?'selected':'' ?>>2</option>
                                <option value="1" <?= ($timi_pre=='1')?'selected':'' ?>>1</option>
                                <option value="0" <?= ($timi_pre=='0')?'selected':'' ?> class="text-danger fw-bold">0</option>
                            </select>
                        </td>
                        <td class="bg-light border-end">
                            <select name="seg_<?= $i ?>_lesion" class="form-select form-select-flush lesion-select">
                                <option value="" class="text-muted">- Type -</option>
                                <option value="DeNovo" <?= ($lesion=='DeNovo')?'selected':'' ?>>De Novo</option>
                                <option value="ISR" <?= ($lesion=='ISR')?'selected':'' ?>>ISR</option>
                                <option value="Calcified" <?= ($lesion=='Calcified')?'selected':'' ?>>Calcified</option>
                                <option value="Thrombus" <?= ($lesion=='Thrombus')?'selected':'' ?>>Thrombus</option>
                                <option value="CTO" <?= ($lesion=='CTO')?'selected':'' ?>>CTO</option>
                                <option value="Bifurcation" <?= ($lesion=='Bifurcation')?'selected':'' ?>>Bifurcation</option>
                            </select>
                        </td>

                        <td>
                            <select name="seg_<?= $i ?>_method" class="form-select form-select-flush fw-bold text-primary">
                                <option value="">-</option>
                                <option value="DES" <?= ($method=='DES')?'selected':'' ?>>DES</option>
                                <option value="BMS" <?= ($method=='BMS')?'selected':'' ?>>BMS</option>
                                <option value="DCB" <?= ($method=='DCB')?'selected':'' ?>>DCB</option>
                                <option value="POBA" <?= ($method=='POBA')?'selected':'' ?>>POBA</option>
                                <option value="Thrombosuct" <?= ($method=='Thrombosuct')?'selected':'' ?>>Aspire</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="seg_<?= $i ?>_device_size" class="form-control form-control-flush" placeholder="D x L" value="<?= $size ?>">
                        </td>
                        <td>
                            <input type="number" name="seg_<?= $i ?>_pressure" class="form-control form-control-flush" placeholder="-" value="<?= $atm ?>">
                        </td>
                        <td>
                            <select name="seg_<?= $i ?>_dilate" class="form-select form-select-flush text-center">
                                <option value="">-</option>
                                <option value="Pre" <?= ($dilate=='Pre')?'selected':'' ?>>Pre</option>
                                <option value="Post" <?= ($dilate=='Post')?'selected':'' ?>>Post</option>
                                <option value="Both" <?= ($dilate=='Both')?'selected':'' ?>>Both</option>
                            </select>
                        </td>

                        <td class="bg-light border-start">
                            <input type="number" name="seg_<?= $i ?>_resid" class="form-control form-control-flush" placeholder="-" value="<?= $resid ?>">
                        </td>
                        <td class="bg-light">
                            <select name="seg_<?= $i ?>_timi_post" class="form-select form-select-flush text-center text-success fw-bold">
                                <option value="3" <?= ($timi_post=='3')?'selected':'' ?>>3</option>
                                <option value="2" <?= ($timi_post=='2')?'selected':'' ?>>2</option>
                                <option value="1" <?= ($timi_post=='1')?'selected':'' ?>>1</option>
                                <option value="0" <?= ($timi_post=='0')?'selected':'' ?>>0</option>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-end align-items-center gap-3 mt-3 small text-muted">
    <span class="d-flex align-items-center">
        <span style="width: 12px; height: 12px; background-color: #ffc107; border-radius: 50%; display: inline-block; margin-right: 6px;"></span> 
        RCA Territory
    </span>
    <span class="d-flex align-items-center">
        <span style="width: 12px; height: 12px; background-color: #dc3545; border-radius: 50%; display: inline-block; margin-right: 6px;"></span> 
        LAD Territory
    </span>
    <span class="d-flex align-items-center">
        <span style="width: 12px; height: 12px; background-color: #198754; border-radius: 50%; display: inline-block; margin-right: 6px;"></span> 
        LCx Territory
    </span>
</div>
</div>

<div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
   
    <div class="mb-3">
        <button type="button" class="btn btn-outline-primary w-100 d-flex justify-content-between align-items-center p-3 rounded-3 shadow-sm" 
                data-bs-toggle="collapse" data-bs-target="#conclusionContent" aria-expanded="false">
            <span class="fw-bold"><i class="bi bi-clipboard-check me-2"></i> Conclusion (สรุปผลวินิจฉัย)</span>
            <i class="bi bi-chevron-down"></i>
        </button>

        <div class="collapse mt-3" id="conclusionContent">
            <div class="card card-body bg-light border-0">
                
                <div class="mb-4">
                    <label class="form-label fw-bold text-secondary">1. Left Main Disease</label>
                    <div class="d-flex gap-2">
                        <div class="flex-fill">
                            <input type="radio" class="btn-check" name="left_main" id="lm_no" value="No" autocomplete="off">
                            <label class="btn btn-outline-secondary w-100 rounded-pill bg-white" for="lm_no">No</label>
                        </div>
                        <div class="flex-fill">
                            <input type="radio" class="btn-check" name="left_main" id="lm_yes" value="Yes" autocomplete="off">
                            <label class="btn btn-outline-success w-100 rounded-pill bg-white" for="lm_yes">Yes</label>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-secondary">2. Extent of disease</label>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="flex-fill">
                            <input type="radio" class="btn-check" name="disease_extent" id="single" value="Single" autocomplete="off">
                            <label class="btn btn-outline-primary w-100 rounded-pill bg-white" for="single">Single VD</label>
                        </div>
                        <div class="flex-fill">
                            <input type="radio" class="btn-check" name="disease_extent" id="double" value="Double" autocomplete="off">
                            <label class="btn btn-outline-primary w-100 rounded-pill bg-white" for="double">Double VD</label>
                        </div>
                        <div class="flex-fill">
                            <input type="radio" class="btn-check" name="disease_extent" id="triple" value="Triple" autocomplete="off">
                            <label class="btn btn-outline-primary w-100 rounded-pill bg-white" for="triple">Triple VD</label>
                        </div>
                        <div class="flex-fill">
                            <input type="checkbox" class="btn-check" id="dx_normal" name="diagnosis[]" value="Normal CAD" autocomplete="off">
                            <label class="btn btn-outline-success w-100 rounded-pill bg-white" for="dx_normal">Normal / Non-sig CAD</label>
                        </div>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label fw-bold text-secondary">3. Other diagnosis</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php 
                        $diags = [
                            'dx_pericarditis' => 'Pericarditis',
                            'dx_spasm' => 'Coronary artery spasm',
                            'dx_takotsubo' => 'Takotsubo cardiomyopathy',
                            'dx_aortic_dissection' => 'Aortic dissection',
                            'dx_non_cardiac' => 'Non-cardiac disease',
                            'dx_vhd' => 'VHD',
                            'dx_minoca' => 'MINOCA',
                            'dx_myocarditis' => 'Myocarditis'
                        ];
                        foreach($diags as $id => $label): ?>
                        <div>
                            <input type="checkbox" class="btn-check" id="<?= $id ?>" name="diagnosis[]" value="<?= $label ?>" autocomplete="off">
                            <label class="btn btn-outline-secondary bg-white btn-sm rounded-pill px-3 shadow-sm" for="<?= $id ?>"><?= $label ?></label>
                        </div>
                        <?php endforeach; ?>
                        
                        <div>
                            <input type="checkbox" class="btn-check" id="dx_other" name="diagnosis[]" value="Other" autocomplete="off" onclick="toggleOtherInput(this)">
                            <label class="btn btn-outline-secondary bg-white btn-sm rounded-pill px-3 shadow-sm" for="dx_other">Other</label>
                        </div>
                    </div>
                    <div id="other_text_div" class="mt-2" style="display:none;">
                        <input type="text" id="other_text" name="other_text" class="form-control form-control-sm" placeholder="ระบุการวินิจฉัยอื่นๆ...">
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="mb-3">
        <button type="button" class="btn btn-outline-success w-100 d-flex justify-content-between align-items-center p-3 rounded-3 shadow-sm" 
                data-bs-toggle="collapse" data-bs-target="#pciSection" aria-expanded="false">
            <span class="fw-bold"><i class="bi bi-heart-pulse me-2"></i> PCI Procedure</span>
            <i class="bi bi-chevron-down"></i>
        </button>

        <div class="collapse mt-3" id="pciSection">
            <div class="card card-body bg-light border-0">
                
                <label class="form-label fw-bold text-secondary">ทำหัตถการ PCI หรือไม่?</label>
                <div class="d-flex gap-2 mb-3">
                    <div class="flex-fill">
                        <input type="radio" class="btn-check" name="left_main_pci" id="lm_no_pci" value="No" autocomplete="off" onclick="showPCIOptions(true)">
                        <label class="btn btn-outline-secondary w-100 rounded-pill bg-white" for="lm_no_pci">NO (ไม่ได้ทำ)</label>
                    </div>
                    <div class="flex-fill">
                        <input type="radio" class="btn-check" name="left_main_pci" id="lm_yes_pci" value="Yes" autocomplete="off" onclick="showPCIOptions(false)">
                        <label class="btn btn-outline-success w-100 rounded-pill bg-white" for="lm_yes_pci">YES (ทำ)</label>
                    </div>
                </div>

                <div id="pciOptions" class="p-3 bg-white border rounded-3 animate__animated animate__fadeIn" style="display:none;">
                    <label class="form-label small fw-bold text-muted mb-2">เหตุผล / การรักษาอื่น (ระบุ)</label>
                    <div class="d-flex flex-wrap gap-2">
                        <div>
                            <input type="checkbox" class="btn-check" id="spontaneous" name="pci_option[]" value="Spontaneous reperfusion">
                            <label class="btn btn-outline-secondary btn-sm rounded-pill px-3" for="spontaneous">Spontaneous reperfusion</label>
                        </div>
                        <div>
                            <input type="checkbox" class="btn-check" id="fibrinolysis" name="pci_option[]" value="Reperfusion after fibrinolysis">
                            <label class="btn btn-outline-secondary btn-sm rounded-pill px-3" for="fibrinolysis">Reperfusion after fibrinolysis</label>
                        </div>
                        <div>
                            <input type="checkbox" class="btn-check" id="cabg" name="pci_option[]" value="CABG">
                            <label class="btn btn-outline-secondary btn-sm rounded-pill px-3" for="cabg">CABG</label>
                        </div>
                        <div>
                            <input type="checkbox" class="btn-check" id="pci_other" name="pci_option[]" value="Other" onclick="togglePCIText(this)">
                            <label class="btn btn-outline-secondary btn-sm rounded-pill px-3" for="pci_other">Other</label>
                        </div>
                    </div>
                    <div id="pci_other_text_div" class="mt-2" style="display:none;">
                        <input type="text" id="pci_other_text" name="pci_other_text" class="form-control form-control-sm" placeholder="ระบุเหตุผลอื่นๆ...">
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>
                
<div class="d-flex justify-content-between gap-2 mt-4 d-print-none">
    <button type="submit" name="direction" value="back" class="btn btn-secondary px-4">
        <i class="bi bi-arrow-left"></i> BACK
    </button>
    
    <button type="submit" name="direction" value="next" class="btn btn-success px-5">
        SAVE & NEXT <i class="bi bi-arrow-right"></i>
    </button>
</div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- ฟังก์ชันคำนวณเวลา (นาที) ---
    function calculateDiff(startISO, endISO) {
        if (!startISO || !endISO) return "";
        // รองรับกรณีที่มี T หรือไม่มี
        const start = new Date(startISO.includes('T') ? startISO : '2000-01-01T'+startISO);
        const end = new Date(endISO.includes('T') ? endISO : '2000-01-01T'+endISO);
        
        // ถ้าเป็น Date Object ที่ Valid
        if (isNaN(start.getTime()) || isNaN(end.getTime())) return "";

        let diff = (end - start) / (1000 * 60); 
        return (diff >= 0) ? Math.round(diff) : ""; // ปัดเศษ
    }

    function setVal(id, val) {
        const el = document.getElementById(id);
        if(el) el.value = val;
    }

    // --- อ้างอิง Input ที่เป็นตัวแปรหลัก (End Point) ---
    const deviceTimeInput = document.getElementById('first_device_time'); // Time at first device (เปิดหลอดเลือด)

    // --- ฟังก์ชันคำนวณ KPI ทั้งหมด ---
    function updateAllKPIs() {
        const deviceTime = deviceTimeInput.value;
        if (!deviceTime) return;

        // 1. ดึงเวลาตั้งต้น (Start Points) จาก Hidden Input
        const doorTime = document.getElementById('ref_door_hatyai_dt').value; 
        const onsetTime = document.getElementById('ref_onset_dt').value;
        const fmcTime = document.getElementById('ref_fmc_dt').value;
        const dxTime = document.getElementById('ref_dx_dt').value;

        // A. Door to Device (เทียบกับเวลาถึง รพ.)
        if (doorTime) {
            const val = calculateDiff(doorTime, deviceTime);
            const el = document.getElementById('door_to_device_min');
            if(el) {
                el.value = val;
                // Highlight Rule: > 90 min = Red, <= 90 min = Green
                el.style.color = (val > 90) ? '#dc3545' : '#198754';
                el.style.fontWeight = 'bold';
            }
        }

        // B. Onset to Device
        if (onsetTime) {
            const val = calculateDiff(onsetTime, deviceTime);
            setVal('onset_to_device_min', val);
        }

        // C. FMC to Device
        if (fmcTime) {
            const val = calculateDiff(fmcTime, deviceTime);
            setVal('fmc_to_device_min', val);
        }
         // D. STEMI Dx to Device
        if (dxTime) {
            const val = calculateDiff(dxTime, deviceTime);
            setVal('stemi_dx_to_device_min', val);
        }
    }

    // --- สั่งให้ทำงานเมื่อเปลี่ยนเวลา Device ---
    if (deviceTimeInput) {
        updateAllKPIs(); // คำนวณครั้งแรกเผื่อมีค่าเดิม
        deviceTimeInput.addEventListener('change', updateAllKPIs);
    }
    
    // -----------------------------------------------------------------
    // --- 2. การจัดการ Toggle Sections (แสดง/ซ่อน) ---
    // -----------------------------------------------------------------
    
    // Helper Function: ผูก Event กับ Radio/Checkbox
    function bindToggle(triggerId, targetId, isRadioGroup = false, radioName = '') {
        const trigger = document.getElementById(triggerId);
        const target = document.getElementById(targetId);
        
        if (trigger && target) {
            if (isRadioGroup) {
                // สำหรับ Radio Group (เช่น Yes/No)
                document.querySelectorAll(`input[name="${radioName}"]`).forEach(rad => {
                    rad.addEventListener('change', () => {
                        // ถ้า trigger (เช่น Yes) ถูกเลือก ให้แสดง
                        target.style.display = trigger.checked ? 'block' : 'none';
                        if(trigger.id === 'proc_yes' && trigger.checked) target.style.display = 'flex'; // กรณีพิเศษ
                    });
                });
                // Initial State
                if(trigger.checked) target.style.display = (trigger.id === 'proc_yes') ? 'flex' : 'block';
            } else {
                // สำหรับ Checkbox เดี่ยว
                trigger.addEventListener('change', () => {
                    if (trigger.checked) {
                        target.classList.remove('d-none');
                        target.style.display = 'block';
                    } else {
                        target.classList.add('d-none');
                        target.style.display = 'none';
                    }
                });
                // Initial State
                if (trigger.checked) {
                    target.classList.remove('d-none');
                    target.style.display = 'block';
                }
            }
        }
    }

    // ผูก Logic เข้ากับ Form
    bindToggle('ind_primary', 'detail_primary');
    bindToggle('ind_rescue', 'detail_rescue');
    bindToggle('ind_recent', 'detail_recent');
    bindToggle('ind_nstemi', 'detail_nstemi');
    bindToggle('mc_yes', 'mc_types', true, 'mc_support');
    bindToggle('proc_yes', 'procedure_times', true, 'proc_success');
    bindToggle('lm_no_pci', 'pciOptions', true, 'left_main_pci'); // สลับ Logic ได้ตามต้องการ

    // ปุ่ม Conclusion (Custom Toggle)
    const btnConclusion = document.getElementById('btnConclusion');
    if (btnConclusion) {
        btnConclusion.addEventListener('click', function() {
            const content = document.getElementById('conclusionContent');
            if (content.style.display === 'none') {
                content.style.display = 'block';
                this.classList.add('btn-selected');
            } else {
                content.style.display = 'none';
                this.classList.remove('btn-selected');
            }
        });
    }

    // เรียกฟังก์ชันจัดหน้าจอครั้งแรก
    updateDominanceLabels();
});

// --- 3. ฟังก์ชัน Global (เรียกจาก onchange ใน HTML) ---

// เปลี่ยนชื่อ Segment ตาม Dominance
function updateDominanceLabels() {
    const isRight = document.getElementById('dom_right').checked;
    const isLeft = document.getElementById('dom_left').checked;
    
    const label4 = document.getElementById('label_seg_4');
    const label16 = document.getElementById('label_seg_16');
    const row4 = document.getElementById('row_seg_4');

    if (isRight) {
        if(label4) label4.innerText = "4. PDA (Right Dom)";
        if(label16) label16.innerText = "16. PLB (Right Dom)";
        // เปลี่ยนสีแถว 4 เป็นส้ม (RCA)
        if(row4) {
            row4.classList.remove('row-lcx', 'row-lad');
            row4.classList.add('row-rca'); 
        }
    } else if (isLeft) {
        if(label4) label4.innerText = "4. PDA (Small/Absent)";
        if(label16) label16.innerText = "16. Small/Absent";
        // เปลี่ยนสีแถว 4 เป็นเทา หรือเขียว (LCx)
        if(row4) {
            row4.classList.remove('row-rca');
            row4.classList.add('row-lcx');
        }
    }
}

// แสดงช่อง Other Text
function toggleCADOther(radio) {
    const input = document.getElementById('cad_other_text');
    const div = document.getElementById('cad_other_div');
    if(div) {
        div.style.display = (radio.value === 'Other') ? 'block' : 'none';
        if(radio.value !== 'Other' && input) input.value = '';
    }
}

function toggleOtherInput(checkbox) {
    const inputDiv = document.getElementById('other_text_div');
    const input = document.getElementById('other_text');
    if(inputDiv) {
        inputDiv.style.display = checkbox.checked ? 'block' : 'none';
        if(checkbox.checked) {
             if(input) input.focus();
        } else {
             if(input) input.value = '';
        }
    }
}

function togglePCIText(checkbox) {
    const inputDiv = document.getElementById('pci_other_text_div');
    const input = document.getElementById('pci_other_text');
    if(inputDiv) {
        inputDiv.style.display = checkbox.checked ? 'block' : 'none';
        if(checkbox.checked) {
             if(input) input.focus();
        } else {
             if(input) input.value = '';
        }
    }
}

function toggleIndicationDetails(type) {
    const chk = document.getElementById('ind_' + type);
    const detailDiv = document.getElementById('detail_' + type);
    
    if (detailDiv) {
        if (chk.checked) {
            detailDiv.style.display = 'block';
        } else {
            detailDiv.style.display = 'none';
        }
    }
}
function toggleMcsOptions(show) {
    const section = document.getElementById('mc_types');
    if (show) {
        section.style.display = 'block';
    } else {
        section.style.display = 'none';
        // Optional: Reset selections
        document.querySelectorAll('#mc_types input[type="checkbox"]').forEach(cb => cb.checked = false);
        document.getElementById('mc_other_text_div').style.display = 'none';
    }
}

function toggleMcsOtherInput() {
    const cb = document.getElementById('mc_other');
    const inputDiv = document.getElementById('mc_other_text_div');
    if (cb.checked) {
        inputDiv.style.display = 'block';
        document.getElementById('mc_other_text').focus();
    } else {
        inputDiv.style.display = 'none';
    }
}
function showPCIOptions(isNo) {
    const opts = document.getElementById('pciOptions');
    if (isNo) {
        opts.style.display = 'block';
    } else {
        opts.style.display = 'none';
        // Optional: clear checks
        document.querySelectorAll('#pciOptions input[type="checkbox"]').forEach(c => c.checked = false);
        document.getElementById('pci_other_text_div').style.display = 'none';
    }
}
// เพิ่มฟังก์ชันนี้ใน <script> ของไฟล์ cardiac_cath.php
const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            // ดึงค่าเวลาต่างๆ มาสร้างเป็น Date Object
            // ตรวจสอบชื่อ ID/Name ให้ตรงกับใน HTML ของคุณ
            const doorInValue = document.getElementById('door_in_datetime')?.value || (document.getElementsByName('arrived_date')[0]?.value + ' ' + document.getElementsByName('arrived_time')[0]?.value);
            const deviceValue = document.getElementById('first_device_time')?.value || (document.getElementsByName('first_device_date')[0]?.value + ' ' + document.getElementsByName('first_device_time')[0]?.value);
            const onsetValue = document.getElementById('ref_onset_dt')?.value;

            const doorInTime = new Date(doorInValue);
            const deviceTime = new Date(deviceValue);
            const onsetTime = onsetValue ? new Date(onsetValue) : null;

            // 1. ตรวจสอบว่าเวลาเปิดหลอดเลือด ต้องไม่เกิดก่อนเวลาเริ่มมีอาการ
            if (onsetTime && deviceTime < onsetTime) {
                e.preventDefault();
                alert('❌ ผิดพลาด: เวลาที่เปิดหลอดเลือด (First Device) จะเกิดก่อนเวลาที่คนไข้เริ่มมีอาการไม่ได้');
                return false;
            }

            // 2. ตรวจสอบว่าเวลาเปิดหลอดเลือด ต้องไม่เกิดก่อนเวลามาถึงโรงพยาบาล
            if (deviceTime < doorInTime) {
                e.preventDefault();
                alert('❌ ผิดพลาด: เวลาที่เปิดหลอดเลือด ต้องเกิดขึ้นหลังจากคนไข้มาถึงโรงพยาบาลแล้ว');
                return false;
            }
            
            // ถ้าผ่านทุกเงื่อนไข ระบบจะบันทึกข้อมูลตามปกติ
        });
    }

</script>
</body>
</html>