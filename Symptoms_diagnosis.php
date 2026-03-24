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
// --- ตัวอย่างการจัดลำดับที่ถูกต้อง ---
$stmt_pt = $pdo->prepare("SELECT age, gender FROM patients WHERE id = ?");
$stmt_pt->execute([$patient_id]);
$pt_info = $stmt_pt->fetch(PDO::FETCH_ASSOC) ?: ['age' => 60, 'gender' => 'ชาย'];
$patient_age = $pt_info['age'];

$stmt_ref = $pdo->prepare("SELECT * FROM symptoms_diagnosis WHERE patient_id = ?");
$stmt_ref->execute([$patient_id]);
$ref = $stmt_ref->fetch(PDO::FETCH_ASSOC);

if (!$ref) {
    // กำหนดค่าเริ่มต้นให้ครบทุก Key ที่ใช้ใน value="<?= ..." ของ HTML
    $ref = [
        'onset_date' => '', 'onset_time' => '', 
        'hospital_date_hatyai' => '', 'hospital_time_hatyai' => '',
        'diag_ekg_date' => '', 'diag_ekg_time' => '',
        'diagnosis_btn' => '', 'initial_diagnosis_main' => '',
        'creatinine' => '', 'hr' => '', 'bp_systolic' => '', 'grace_score' => '',
        'stemi_sub' => '', 'area_infarction' => ''
    ];
}
// Prepare arrays for sticky checkboxes
$stemi_sub_to_check = isset($_POST['stemi_sub']) 
    ? $_POST['stemi_sub'] 
    : (!empty($ref['stemi_sub']) ? explode(', ', $ref['stemi_sub']) : []);

$area_infarction_to_check = isset($_POST['area_infarction']) 
    ? $_POST['area_infarction'] 
    : (!empty($ref['area_infarction']) ? explode(', ', $ref['area_infarction']) : []);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($patient_id)) {
        echo "<script>
            alert('ไม่สามารถบันทึกได้: กรุณากรอกข้อมูลลงทะเบียนผู้ป่วยที่หน้าแรกให้เรียบร้อยก่อน');
            window.location.href = 'Symptoms_diagnosis.php';
        </script>";
        exit; // หยุดการทำงานของคำสั่ง SQL ด้านล่างทันที
    }
    $direction = $_POST['direction'] ?? 'next'; // รับค่าจากปุ่มว่ากด Back หรือ Next
    try {
        // --- ส่วนที่เพิ่ม: ตรวจสอบว่าผู้ป่วยมีตัวตนจริงในฐานข้อมูลหรือไม่ ---
        $check_sql = "SELECT id FROM patients WHERE id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$patient_id]);
        
        // จัดการข้อมูล Array (Checkbox) ให้เป็น String
        $stemi_sub_str = isset($_POST['stemi_sub']) ? implode(', ', $_POST['stemi_sub']) : '';
        $area_infarction_str = isset($_POST['area_infarction']) ? implode(', ', $_POST['area_infarction']) : '';

        $sql = "REPLACE INTO symptoms_diagnosis (
                    patient_id, onset_date, onset_time, onset_unknown, fmc, ems_date, ems_time,
                    hospital_date_rpch, hospital_time_rpch, pain_score_rpch,
                    hospital_date_rpth, hospital_time_rpth, pain_score_rpth,
                    hospital_date_hatyai, hospital_time_hatyai, pain_score_hatyai,
                    first_ekg_date, first_ekg_time, door_to_ekg_time,
                    diag_ekg_date, diag_ekg_time, ekg_interpretation,
                    diagnosis_btn, initial_diagnosis_main, stemi_sub, area_infarction,
                    angina, dyspnea_type, syncope, cardiac_arrest,
                    heart_failure_value, on_ett_value, killip_class_value,
                    arrhythmia_value, arrhythmia_main_type, cpr_value, cpr_detail,
                    death_value, dead_status_value, grace_score, hr, bp_systolic, bp_diastolic, creatinine, gfr
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )";

        $stmt = $pdo->prepare($sql);
        // ลำดับ Execute ที่ถูกต้อง (นับตามเครื่องหมาย ?)
        $stmt->execute([
            $patient_id,                                 // 1
            $_POST['onset_date'] ?: null,                // 2
            $_POST['onset_time'] ?: null,                // 3
            isset($_POST['onset_unknown']) ? 1 : 0,      // 4
            $_POST['fmc'] ?? '',                         // 5
            $_POST['ems_date'] ?: null,                  // 6
            $_POST['ems_time'] ?: null,                  // 7
            $_POST['hospital_date_rpch'] ?: null,        // 8
            $_POST['hospital_time_rpch'] ?: null,        // 9
            $_POST['pain_score_rpch'] ?? null,           // 10
            $_POST['hospital_date_rpth'] ?: null,        // 11
            $_POST['hospital_time_rpth'] ?: null,        // 12
            $_POST['pain_score_rpth'] ?? null,           // 13
            $_POST['hospital_date_hatyai'] ?: null,      // 14
            $_POST['hospital_time_hatyai'] ?: null,      // 15
            $_POST['pain_score_hatyai'] ?? null,         // 16
            $_POST['first_ekg_date'] ?: null,            // 17
            $_POST['first_ekg_time'] ?: null,            // 18
            $_POST['door_to_ekg_time'] ?? null,          // 19
            $_POST['diag_ekg_date'] ?: null,             // 20
            $_POST['diag_ekg_time'] ?: null,             // 21
            $_POST['ekg_interpretation'] ?? '',          // 22 
            $_POST['diagnosis_btn'] ?? '',               // 23
            $_POST['initial_diagnosis_main'] ?? '',      // 24
            $stemi_sub_str,                              // 25
            $area_infarction_str,                        // 26
            $_POST['angina'] ?? '',                      // 27
            $_POST['dyspnea_type'] ?? '',                // 28
            $_POST['syncope'] ?? '',                     // 29
            $_POST['cardiac_arrest'] ?? '',              // 30
            $_POST['heart_failure_value'] ?? '',         // 31
            $_POST['on_ett_value'] ?? '',                // 32
            $_POST['killip_class_value'] ?? '',          // 33
            $_POST['arrhythmia_value'] ?? '',            // 34
            $_POST['arrhythmia_main_type'] ?? '',        // 35
            $_POST['cpr_value'] ?? '',                   // 36
            $_POST['cpr_detail'] ?? '',                  // 37
            $_POST['death_value'] ?? '',                 // 38
            $_POST['dead_status_value'] ?? '',           // 39
            $_POST['grace_score'] ?? null,               // 40
            $_POST['hr'] ?? null,                        // 41
            $_POST['bp'] ?? null,                        // 42 (Systolic)
            $_POST['bp_diastolic'] ?? null,              // 43
            $_POST['creatinine'] ?? null,                // 44
            $_POST['gfr'] ?? null                        // 45
        ]);

        // ✅ แก้ไขเงื่อนไขการ Redirect
        if ($direction === 'back') {
            header("Location: history_risk_factor.php?id=" . $patient_id);
        } elseif ($direction === 'ding') {
            $ding_type = $_POST['ding_type'] ?? 'acs';
            if ($ding_type === 'acs') {
                header("Location: ding_acs.php?id=" . $patient_id);
            } else {
                header("Location: ding_stemi.php?id=" . $patient_id);
            }
        } else {
            header("Location: Medication.php?id=" . $patient_id);
        }
        exit();

    } catch (Exception $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
    }
}


// Define variables from POST for rendering
$onset_unknown = isset($_POST['onset_unknown']);
$initial_diagnosis_main = $_POST['initial_diagnosis_main'] ?? '';
$heart_failure_value = $_POST['heart_failure_value'] ?? '';
$on_ett_value = $_POST['on_ett_value'] ?? '';
$killip_class_value = $_POST['killip_class_value'] ?? '';
$arrhythmia_value = $_POST['arrhythmia_value'] ?? '';
$arrhythmia_main_type = $_POST['arrhythmia_main_type'] ?? '';
$arrhythmia_vtvf_value = $_POST['arrhythmia_vtvf_value'] ?? '';
$arrhythmia_chb_value = $_POST['arrhythmia_chb_value'] ?? '';
$arrhythmia_other = $_POST['arrhythmia_other'] ?? '';
$cpr_value = $_POST['cpr_value'] ?? '';
$cpr_detail = $_POST['cpr_detail'] ?? '';

// Helper function to add 'active' class
function isActive($current_value, $button_value) {
    return ($current_value === $button_value) ? 'active' : '';
}

?>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Symptoms & Diagnosis</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
 <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
.top-bar {
    background: #fff;
    padding: 18px;
    border-radius: 8px;
    margin-bottom: 18px;
}
.hospital-title { color: #28a745; font-weight: bold; }

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
hr { border-top: 2px solid #eee; }

table input[type="checkbox"], table input[type="radio"] { margin-right: 4px; transform: scale(1.1); }
td { padding: 4px 12px !important; vertical-align: middle; }

.btn { transition: all 0.2s ease-in-out; }
.btn:hover { transform: scale(1.05); }
.btn:active { transform: scale(0.95); }

.ding-btn {
    padding: 10px 28px;
    font-weight: 600;
    border-radius: 8px;
    background-color: #e9ecef;
    color: #333;
    border: 1px solid #ccc;
}
.ding-btn.active {
    background-color: #28a745 !important;
    color: #fff !important;
    border-color: #28a745 !important;
}

/* ปุ่มเลือก Heart Failure / On ETT - Responsive Wrapper */
.btn-toggle-responsive {
    display: flex;
    flex-wrap: wrap;
    gap: 8px; 
}
.check-btn {
    border: 1px solid #ccc;
    background: #fff;
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
    transition: 0.2s;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}
.check-btn.active {
    background: #28a745;
    color: #fff;
    border-color: #28a745;
}
/* สไตล์ปุ่มปกติ: ขอบเทา ตัวหนังสือเทา พื้นขาว ขนาดตามที่คุณกำหนดไว้เดิม */
    .btn-select {
        cursor: pointer;
        border-radius: 0.25rem;
        padding: 0.375rem 0.75rem;
        font-size: 0.95rem;
        text-align: center;
        border: 1px solid #ced4da; /* ขอบเทา */
        background-color: #fff;     /* พื้นขาว */
        color: #6c757d;             /* ตัวหนังสือเทา */
        transition: all 0.2s;
        min-width: 100px;
        display: inline-block;
    }

    /* เมื่อปุ่มถูกเลือก (Checked): พื้นเขียว ตัวขาว และไม่มีแสงฟุ้ง */
    .btn-check:checked + .btn-select {
        background-color: #28a745 !important; /* สีเขียว success */
        color: #ffffff !important;           /* ตัวหนังสือขาว */
        border-color: #28a745 !important;    /* ขอบเขียว */
        box-shadow: none !important;         /* ตัดแสงเขียวฟุ้งออก */
        transform: none !important;          /* ไม่มีการขยายหรือลอยขึ้น */
    }

    /* สไตล์เมื่อเอาเมาส์วาง (Hover) */
    .btn-select:hover {
        background-color: #f8f9fa;
        border-color: #adb5bd;
        color: #495057;
    }

    /* สไตล์ปุ่มปกติ: ขอบเทา ตัวหนังสือเทา พื้นขาว */
    .btn-outline-custom-gray {
        color: #6c757d;
        background-color: #ffffff;
        border-color: #ced4da;
        transition: all 0.2s ease-in-out;
    }

    /* เมื่อปุ่มถูกเลือก (Checked): พื้นเขียว ตัวขาว แสงเขียวฟุ้ง */
    .btn-check:checked + .btn-outline-custom-gray {
        background-color: #28a745 !important;
        color: #ffffff !important;
        
        transform: translateY(-1px);
    }

    /* ตกแต่ง Card รายละเอียดให้ดูสะอาดตา */
    .card-body {
        border-radius: 12px;
        border: 1px solid #e9ecef;
    }
    /* */
    .btn-check:checked + .btn .check-icon {
        display: block !important;
    }
    .btn-check:checked + .btn {
        border-width: 2px;
        background-color: #f8f9fa;
    }
    .transition-hover:hover {
        transform: translateY(-2px);
        transition: transform 0.2s;
    }
    /* CSS เฉพาะส่วนนี้: ทำให้ปุ่มที่ถูกเลือกดูเด่นชัดขึ้น */
    .btn-check:checked + .btn {
        border-width: 2px !important;      /* ขอบหนาขึ้น */
        font-weight: bold !important;      /* ตัวหนา */
        box-shadow: 0 4px 6px rgba(0,0,0,0.15); /* มีเงา */
        transform: translateY(-2px);       /* ลอยขึ้นเล็กน้อย */
    }
    
    /* ซ่อนไอคอนติ๊กถูกไว้ก่อน */
    .check-indicator {
        display: none;
    }
    
    /* แสดงไอคอนติ๊กถูกเมื่อถูกเลือก */
    .btn-check:checked + .btn .check-indicator {
        display: inline-block !important;
    }
    
    /* ซ่อนไอคอนเดิมเมื่อถูกเลือก (เพื่อให้ไม่รก - Optional) */
    .btn-check:checked + .btn .default-icon {
        display: none;
    }
    .btn-check:checked + .btn .active-icon {
        display: inline-block !important;
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
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap">
        <div class="hospital-title mb-2 mb-md-0">
            STEMI Registry <span class="text-danger">(ระบบจัดเก็บและติดตามตัวชี้วัดคุณภาพการดูแลผู้ป่วยโรคหัวใจขาดเลือด)</span>
        </div>
       
    </div>

     <div class="form-section">
            <div class="card shadow-sm border-0 mb-4 overflow-hidden rounded-4">
                <div class="card-body p-2 bg-white">
                    <ul class="nav nav-pills nav-fill flex-nowrap overflow-auto pb-1" id="mainNav" style="scrollbar-width: none;">
                        <li class="nav-item">
                            <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 <?= basename($_SERVER['PHP_SELF']) == 'patient_form.php' ? 'active shadow-sm' : 'text-secondary' ?>" href="patient_form.php">
                                <i class="bi bi-person-vcard fs-5"></i><span class="small fw-bold">ข้อมูลผู้ป่วย</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 <?= basename($_SERVER['PHP_SELF']) == 'history_risk_factor.php' ? 'active shadow-sm' : 'text-secondary' ?>" href="history_risk_factor.php">
                                <i class="bi bi-clipboard-pulse fs-5"></i><span class="small fw-bold">History & Risk</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 <?= basename($_SERVER['PHP_SELF']) == 'Symptoms_diagnosis.php' ? 'active shadow-sm' : 'text-secondary' ?>" href="Symptoms_diagnosis.php">
                                <i class="bi bi-heart-pulse fs-5"></i><span class="small fw-bold">Diagnosis</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 <?= basename($_SERVER['PHP_SELF']) == 'Medication.php' ? 'active shadow-sm' : 'text-secondary' ?>" href="Medication.php">
                                <i class="bi bi-capsule fs-5"></i><span class="small fw-bold">Medication</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 <?= basename($_SERVER['PHP_SELF']) == 'cardiac_cath.php' ? 'active shadow-sm' : 'text-secondary' ?>" href="cardiac_cath.php">
                                <i class="bi bi-activity fs-5"></i><span class="small fw-bold">Cardiac Cath</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 <?= basename($_SERVER['PHP_SELF']) == 'treatment_results.php' ? 'active shadow-sm' : 'text-secondary' ?>" href="treatment_results.php">
                                <i class="bi bi-clipboard-check fs-5"></i><span class="small fw-bold">Result</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 <?= basename($_SERVER['PHP_SELF']) == 'discharge.php' ? 'active shadow-sm' : 'text-secondary' ?>" href="discharge.php">
                                <i class="bi bi-door-open fs-5"></i><span class="small fw-bold">Discharge</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

        <form method="post" action="">
           <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
    <h6 class="text-primary fw-bold mb-4 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-alarm me-2 fs-5"></i> Onset of Symptom
    </h6>

    <div class="row g-3 align-items-end">
        
        <div class="col-md-5 col-12">
            <label class="form-label small text-secondary fw-bold">วว/ดด/ปป</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-primary"><i class="bi bi-calendar-event"></i></span>
                <input type="date" name="onset_date" id="onset_date_input" class="form-control" 
                       value="<?= htmlspecialchars($_POST['onset_date'] ?? '') ?>"
                       <?= ($onset_unknown ?? false) ? 'disabled' : '' ?>>
            </div>
        </div>

        <div class="col-md-4 col-12">
            <label class="form-label small text-secondary fw-bold">เวลา</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-primary"><i class="bi bi-clock"></i></span>
                <input type="time" name="onset_time" id="onset_time_input" class="form-control" 
                       value="<?= htmlspecialchars($_POST['onset_time'] ?? '') ?>"
                       <?= ($onset_unknown ?? false) ? 'disabled' : '' ?>>
            </div>
        </div>

        <div class="col-md-3 col-12">
            <div class="form-check form-switch p-2 bg-light rounded border d-flex align-items-center ps-5">
                <input class="form-check-input me-2" type="checkbox" role="switch" 
                       name="onset_unknown" id="onset_unknown_chk" value="1" 
                       <?= ($onset_unknown ?? false) ? 'checked' : '' ?>
                       onchange="toggleOnsetInputs(this)">
                <label class="form-check-label fw-bold text-muted cursor-pointer" for="onset_unknown_chk">
                    Not known
                </label>
            </div>
        </div>
        
    </div>
</div>
<hr>
        <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
    <h6 class="text-primary fw-bold mb-4 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-ambulance me-2 fs-5"></i> First Medical Contact (FMC)
    </h6>

    <div class="row g-3 mb-4">
        <?php 
        $options = [
            'EMS' => ['label' => 'EMS', 'icon' => 'bi-cone-striped', 'color' => 'danger'],
            'Walk in' => ['label' => 'Walk in', 'icon' => 'bi-person-walking', 'color' => 'success'],
            'OPD' => ['label' => 'OPD', 'icon' => 'bi-hospital', 'color' => 'primary'],
            'IPD' => ['label' => 'IPD', 'icon' => 'bi-prescription2', 'color' => 'info']
        ];
        
        // Input hidden สำหรับเก็บค่าจริง
        $current_fmc = $_POST['fmc'] ?? '';
        ?>
        <input type="hidden" name="fmc" id="fmc_input" value="<?= htmlspecialchars($current_fmc) ?>">

        <?php foreach ($options as $val => $meta): 
            $isActive = ($current_fmc === $val);
            $outlineClass = "btn-outline-{$meta['color']}";
        ?>
        <div class="col-6 col-md-3">
            <input type="radio" class="btn-check fmc-radio" name="fmc_select" id="btn_<?= $val ?>" value="<?= $val ?>" 
                   <?= $isActive ? 'checked' : '' ?>
                   onclick="handleFMCChange(this.value)">
            <label class="btn <?= $outlineClass ?> w-100 p-3 h-100 d-flex flex-column align-items-center justify-content-center shadow-sm rounded-3 transition-hover" for="btn_<?= $val ?>">
                <i class="bi <?= $meta['icon'] ?> fs-2 mb-2"></i>
                <span class="fw-bold"><?= $meta['label'] ?></span>
            </label>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="emsInfo" class="bg-light p-3 rounded-3 border border-danger border-opacity-25 animate__animated animate__fadeIn" 
         style="display: <?= ($current_fmc === 'EMS') ? 'block' : 'none' ?>;">
        <h6 class="text-danger small fw-bold mb-3"><i class="bi bi-telephone-inbound-fill me-1"></i> EMS Call Details</h6>
        
        <div class="row g-3">
            <div class="col-md-6 col-12">
                <label class="form-label small text-secondary">วว/ดด/ปป</label>
                <div class="input-group">
                    <span class="input-group-text bg-white text-danger"><i class="bi bi-calendar-event"></i></span>
                    <input type="date" name="ems_date" class="form-control" value="<?= htmlspecialchars($_POST['ems_date'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-6 col-12">
                <label class="form-label small text-secondary">เวลา (วางหูโทรศัพท์)</label>
                <div class="input-group">
                    <span class="input-group-text bg-white text-danger"><i class="bi bi-clock-history"></i></span>
                    <input type="time" name="ems_time" class="form-control" value="<?= htmlspecialchars($_POST['ems_time'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <div id="ipdInfo" class="bg-light p-3 rounded-3 border border-info border-opacity-25 animate__animated animate__fadeIn mt-3" 
     style="display: <?= ($current_fmc === 'IPD') ? 'block' : 'none' ?>;">
    <h6 class="text-info small fw-bold mb-3"><i class="bi bi-prescription2 me-1"></i> ระบุข้อมูล IPD</h6>
    
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label small text-secondary">รายละเอียดการรับตัว (เช่น ตึก/วอร์ด/อาการ)</label>
            <div class="input-group">
                <span class="input-group-text bg-white text-info"><i class="bi bi-pencil-square"></i></span>
                <textarea name="ipd_detail" class="form-control" rows="2" placeholder="ระบุข้อมูลเพิ่มเติม..."><?= htmlspecialchars($_POST['ipd_detail'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
</div>
<div id="opdInfo" class="bg-light p-3 rounded-3 border border-primary border-opacity-25 animate__animated animate__fadeIn mt-3" 
     style="display: <?= ($current_fmc === 'OPD') ? 'block' : 'none' ?>;">
    <h6 class="text-primary small fw-bold mb-3"><i class="bi bi-hospital me-1"></i> รายละเอียด OPD</h6>
    
    <div class="row g-3">
        <div class="col-md-6 col-12">
            <label class="form-label small text-secondary">วันที่มารับบริการ</label>
            <div class="input-group">
                <span class="input-group-text bg-white text-primary"><i class="bi bi-calendar-check"></i></span>
                <input type="date" name="opd_date" class="form-control" value="<?= htmlspecialchars($_POST['opd_date'] ?? '') ?>">
            </div>
        </div>
        <div class="col-md-6 col-12">
            <label class="form-label small text-secondary">คลินิก/แผนกที่ตรวจ</label>
            <div class="input-group">
                <span class="input-group-text bg-white text-primary"><i class="bi bi-door-open"></i></span>
                <input type="text" name="opd_clinic" class="form-control" placeholder="เช่น คลินิกอายุรกรรม" value="<?= htmlspecialchars($_POST['opd_clinic'] ?? '') ?>">
            </div>
        </div>
    </div>
</div>
    <h6 class="text-primary fw-bold mt-5 mb-4 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-buildings me-2 fs-5"></i> Hospital Timeline Selection
    </h6>

    <div class="row g-3">
        <div class="col-md-4 col-12">
            <input type="checkbox" class="btn-check" id="btn_check_rpch" autocomplete="off" 
                   data-bs-toggle="collapse" data-bs-target="#details_rpch"
                   <?= (!empty($_POST['hospital_date_rpch'])) ? 'checked' : '' ?>>
            <label class="btn btn-outline-secondary w-100 p-3 text-start shadow-sm position-relative" for="btn_check_rpch">
                <div class="d-flex align-items-center">
                    <div class="bg-secondary bg-opacity-10 p-2 rounded-circle me-3">
                        <i class="bi bi-hospital fs-4 text-secondary"></i>
                    </div>
                    <div>
                        <div class="fw-bold">รพช. ER</div>
                        <div class="small text-muted">Community Hospital</div>
                    </div>
                    <i class="bi bi-check-circle-fill text-success position-absolute top-50 end-0 translate-middle-y me-3 fs-4 check-icon d-none"></i>
                </div>
            </label>
        </div>

        <div class="col-md-4 col-12">
            <input type="checkbox" class="btn-check" id="btn_check_rpth" autocomplete="off" 
                   data-bs-toggle="collapse" data-bs-target="#details_rpth"
                   <?= (!empty($_POST['hospital_date_rpth'])) ? 'checked' : '' ?>>
            <label class="btn btn-outline-primary w-100 p-3 text-start shadow-sm position-relative" for="btn_check_rpth">
                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                        <i class="bi bi-building fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="fw-bold">รพท. ER</div>
                        <div class="small text-muted">General Hospital</div>
                    </div>
                    <i class="bi bi-check-circle-fill text-primary position-absolute top-50 end-0 translate-middle-y me-3 fs-4 check-icon d-none"></i>
                </div>
            </label>
        </div>

        <div class="col-md-4 col-12">
            <input type="checkbox" class="btn-check" id="btn_check_hatyai" autocomplete="off" 
                   data-bs-toggle="collapse" data-bs-target="#details_hatyai"
                   <?= (!empty($_POST['hospital_date_hatyai'])) ? 'checked' : '' ?>>
            <label class="btn btn-outline-success w-100 p-3 text-start shadow-sm position-relative" for="btn_check_hatyai">
                <div class="d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 p-2 rounded-circle me-3">
                        <i class="bi bi-heart-pulse fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="fw-bold">รพ.หาดใหญ่ ER</div>
                        <div class="small text-muted">Regional Hospital</div>
                    </div>
                    <i class="bi bi-check-circle-fill text-success position-absolute top-50 end-0 translate-middle-y me-3 fs-4 check-icon d-none"></i>
                </div>
            </label>
        </div>
    </div>
</div>

<div class="row">
    <div class="collapse <?= !empty($_POST['hospital_date_rpch']) ? 'show' : '' ?>" id="details_rpch">
        <div class="card card-body mb-3 border-success shadow-sm">
            
            <div class="row g-2 align-items-end">
                <div class="col-md-3 col-6">
                    <label class="form-label small text-muted d-block">วว/ดด/ปป</label>
                    <input type="date" name="hospital_date_rpch" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['hospital_date_rpch'] ?? '') ?>">
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small text-muted d-block">เวลา</label>
                    <input type="time" name="hospital_time_rpch" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['hospital_time_rpch'] ?? '') ?>">
                </div>
                <div class="col-md-6 col-12">
                    <label class="form-label fw-bold d-block small">Pain Score</label>
                    <select class="form-select form-select-sm" name="pain_score_rpch">
                        <option value="" selected disabled>เลือกระดับ</option>
                        <?php for ($i = 0; $i <= 10; $i++) { echo "<option value='$i' " . (($_POST['pain_score_rpch'] ?? '') == (string)$i ? 'selected' : '') . ">$i</option>"; } ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="collapse <?= !empty($_POST['hospital_date_rpth']) ? 'show' : '' ?>" id="details_rpth">
        <div class="card card-body mb-3 border-success shadow-sm">
            
            <div class="row g-2 align-items-end">
                <div class="col-md-3 col-6">
                    <label class="form-label small text-muted d-block">วว/ดด/ปป</label>
                    <input type="date" name="hospital_date_rpth" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['hospital_date_rpth'] ?? '') ?>">
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small text-muted d-block">เวลา</label>
                    <input type="time" name="hospital_time_rpth" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['hospital_time_rpth'] ?? '') ?>">
                </div>
                <div class="col-md-6 col-12">
                    <label class="form-label fw-bold d-block small">Pain Score</label>
                    <select class="form-select form-select-sm" name="pain_score_rpth">
                        <option value="" selected disabled>เลือกระดับ</option>
                        <?php for ($i = 0; $i <= 10; $i++) { echo "<option value='$i' " . (($_POST['pain_score_rpth'] ?? '') == (string)$i ? 'selected' : '') . ">$i</option>"; } ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="collapse <?= !empty($_POST['hospital_date_hatyai']) ? 'show' : '' ?>" id="details_hatyai">
        <div class="card card-body mb-3 border-success shadow-sm">
            
            <div class="row g-2 align-items-end">
                <div class="col-md-3 col-6">
                    <label class="form-label small text-muted d-block">วว/ดด/ปป</label>
                    <input type="date" name="hospital_date_hatyai" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['hospital_date_hatyai'] ?? '') ?>">
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small text-muted d-block">เวลา</label>
                    <input type="time" name="hospital_time_hatyai" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['hospital_time_hatyai'] ?? '') ?>">
                </div>
                <div class="col-md-6 col-12">
                    <label class="form-label fw-bold d-block small">Pain Score</label>
                    <select class="form-select form-select-sm" name="pain_score_hatyai">
                        <option value="" selected disabled>เลือกระดับ</option>
                        <?php for ($i = 0; $i <= 10; $i++) { echo "<option value='$i' " . (($_POST['pain_score_hatyai'] ?? '') == (string)$i ? 'selected' : '') . ">$i</option>"; } ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>
    
<hr>

<div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
    <h6 class="text-primary fw-bold mb-4 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-heart-pulse-fill me-2 fs-5"></i> EKG Timeline
    </h6>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill mb-2">
                <i class="bi bi-1-circle-fill me-1"></i> EKG ครั้งแรก (Screening)
            </span>
        </div>

        <div class="col-md-4 col-12">
            <label class="form-label small text-secondary fw-bold">วว/ดด/ปป</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-primary"><i class="bi bi-calendar-heart"></i></span>
                <input type="date" name="first_ekg_date" id="first_ekg_date" class="form-control" 
                       value="<?= htmlspecialchars($_POST['first_ekg_date'] ?? '') ?>"
                       onchange="calculateDoorToEKG()"> </div>
        </div>

        <div class="col-md-4 col-12">
            <label class="form-label small text-secondary fw-bold">เวลา</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-primary"><i class="bi bi-clock"></i></span>
                <input type="time" name="first_ekg_time" id="first_ekg_time" class="form-control" 
                       value="<?= htmlspecialchars($_POST['first_ekg_time'] ?? '') ?>"
                       onchange="calculateDoorToEKG()"> </div>
        </div>

        <div class="col-md-4 col-12">
            <label class="form-label small text-secondary fw-bold text-danger">Door to ECG (นาที)</label>
            <div class="input-group">
                <span class="input-group-text bg-danger bg-opacity-10 text-danger border-danger border-opacity-25">
                    <i class="bi bi-stopwatch-fill"></i>
                </span>
                <input type="number" name="door_to_ekg_time" id="door_to_ekg_time" 
                       class="form-control bg-danger bg-opacity-10 text-danger fw-bold border-danger border-opacity-25" 
                       placeholder="Auto..." readonly 
                       value="<?= htmlspecialchars($_POST['door_to_ekg_time'] ?? '') ?>">
            </div>
        </div>
    </div>

    <hr class="border-light-subtle my-4">

    <div class="row g-3">
        <div class="col-12">
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill mb-2">
                <i class="bi bi-check-circle-fill me-1"></i> Diagnostic EKG (Confirmed ACS)
            </span>
        </div>

        <div class="col-md-3 col-12">
            <label class="form-label small text-secondary fw-bold">วว/ดด/ปป</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-success"><i class="bi bi-calendar-check"></i></span>
                <input type="date" name="diag_ekg_date" id="diag_ekg_date" class="form-control" 
                       value="<?= htmlspecialchars($ref['diag_ekg_date'] ?? '') ?>">
            </div>
        </div>

        <div class="col-md-3 col-12">
            <label class="form-label small text-secondary fw-bold">เวลา</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-success"><i class="bi bi-clock-history"></i></span>
                <input type="time" name="diag_ekg_time" id="diag_ekg_time" class="form-control" 
                       value="<?= htmlspecialchars($ref['diag_ekg_time'] ?? '') ?>">
            </div>
        </div>

        <div class="col-md-6 col-12">
            <label class="form-label small text-secondary fw-bold">ผลอ่าน EKG / การวินิจฉัย</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-success"><i class="bi bi-file-medical-fill"></i></span>
                <input type="text" name="ekg_interpretation" id="ekg_interpretation" class="form-control" 
                       placeholder="ระบุผลอ่าน เช่น STEMI Inferior wall..." 
                       value="<?= htmlspecialchars($ref['ekg_interpretation'] ?? '') ?>">
            </div>
        </div>
    </div>

</div>

<div class="mt-3 p-3 border rounded shadow-sm" style="border-left: 5px solid #0d6efd !important; background-color: #f8f9fa;">
    <div class="row align-items-center">
        <div class="col-md-7">
            <h6 class="mb-0 fw-bolder text-primary"><i class="bi bi-clock-history"></i> Total Ischemic Time</h6>
            <small class="text-muted">(Onset of Symptom ถึงวินิจฉัย EKG)</small>
        </div>
        <div class="col-md-5 text-end">
            <span id="ischemic_time_display" class="fs-4 fw-bold text-muted">0.0 ชั่วโมง</span>
        </div>
    </div>
</div>
   <hr>

          <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
    <h6 class="text-primary fw-bold mb-4 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-clipboard-pulse me-2 fs-5"></i> Diagnosis & Treatment Plan
    </h6>

    <div class="mb-3">
        <label class="form-label text-secondary fw-bold small mb-3">ระบุการวินิจฉัยเพื่อบันทึกแผนการรักษา:</label>
        
        <input type="hidden" name="diagnosis_btn" id="diagnosis_btn_input" value="<?= htmlspecialchars($_POST['diagnosis_btn'] ?? '') ?>">

        <div class="row g-3">
            <div class="col-6">
                <button type="button" id="btnDingAcs" onclick="selectDing('acs')"
                    class="btn w-100 p-4 h-100 shadow-sm border-2 transition-hover d-flex flex-column align-items-center justify-content-center gap-2
                    <?= ($_POST['diagnosis_btn'] ?? '') === 'acs' ? 'btn-warning text-white' : 'btn-outline-warning text-dark' ?>">
                    <i class="bi bi-activity fs-1"></i>
                    <span class="fw-bold fs-5">ACS</span>
                    <span class="small opacity-75">Acute Coronary Syndrome</span>
                </button>
            </div>

            <div class="col-6">
                <button type="button" id="btnDingStemi" onclick="selectDing('stemi')"
                    class="btn w-100 p-4 h-100 shadow-sm border-2 transition-hover d-flex flex-column align-items-center justify-content-center gap-2
                    <?= ($_POST['diagnosis_btn'] ?? '') === 'stemi' ? 'btn-danger text-white' : 'btn-outline-danger' ?>">
                    <i class="bi bi-heart-pulse-fill fs-1"></i>
                    <span class="fw-bold fs-5">STEMI</span>
                    <span class="small opacity-75">ST-Elevation MI</span>
                </button>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'saved'): ?>
        <?php 
            $type = $_GET['type'] ?? '';
            $msg = "บันทึกข้อมูลเรียบร้อยแล้ว";
            $alertColor = "success";
            $icon = "bi-check-circle-fill";

            if ($type === 'acs') {
                $msg = "บันทึกข้อมูลแผนการรักษา <strong>[ACS Consult]</strong> เรียบร้อยแล้ว";
                $alertColor = "warning"; // ใช้สีเหลืองให้เข้ากับธีม ACS
                $icon = "bi-file-earmark-medical-fill";
            } elseif ($type === 'stemi') {
                $msg = "บันทึกข้อมูลแผนการรักษา <strong>[STEMI Fast Track]</strong> เรียบร้อยแล้ว";
                $alertColor = "danger"; // ใช้สีแดงให้เข้ากับธีม STEMI
                $icon = "bi-lightning-charge-fill";
            }
        ?>
        <div class="alert alert-<?= $alertColor ?> alert-dismissible fade show border-0 shadow-sm mt-4 d-flex align-items-center" role="alert">
            <i class="bi <?= $icon ?> fs-4 me-3"></i>
            <div>
                <?= $msg ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

</div>
            
        <hr>
<div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
    <h6 class="text-primary fw-bold mb-4 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-heart-pulse-fill me-2 fs-5"></i> Initial Diagnosis
    </h6>

    <div class="row g-4 align-items-start">
        
        <div class="col-md-5 col-12">
            <label class="form-label small text-secondary fw-bold mb-2">Diagnosis Selection</label>
            <div class="d-flex flex-column gap-2">
                
                <div>
                    <input type="radio" class="btn-check" name="initial_diagnosis_main" id="btn_dx_stemi" value="STEMI" 
                        <?= ($initial_diagnosis_main === 'STEMI') ? 'checked' : '' ?> 
                        onchange="toggleStemiPanel(true)">
                    <label class="btn btn-outline-danger w-100 rounded-pill text-start px-3 shadow-sm" for="btn_dx_stemi">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-lightning-charge-fill me-2"></i>STEMI</span>
                            <i class="bi bi-chevron-right small"></i>
                        </div>
                    </label>
                </div>
                
                <div>
                    <input type="radio" class="btn-check" name="initial_diagnosis_main" id="btn_dx_nstemi" value="NSTEMI (I214)" 
                        <?= ($initial_diagnosis_main === 'NSTEMI (I214)') ? 'checked' : '' ?> 
                        onchange="toggleStemiPanel(false)">
                    <label class="btn btn-outline-warning text-dark w-100 rounded-pill text-start px-3 shadow-sm" for="btn_dx_nstemi">
                        <i class="bi bi-activity me-2"></i>NSTEMI (I21.4)
                    </label>
                </div>

                <div>
                    <input type="radio" class="btn-check" name="initial_diagnosis_main" id="btn_dx_ua" value="UA (I200)" 
                        <?= ($initial_diagnosis_main === 'UA (I200)') ? 'checked' : '' ?> 
                        onchange="toggleStemiPanel(false)">
                    <label class="btn btn-outline-info text-dark w-100 rounded-pill text-start px-3 shadow-sm" for="btn_dx_ua">
                        <i class="bi bi-info-circle-fill me-2"></i>UA (I20.0)
                    </label>
                </div>
            </div>
        </div>

        <div class="col-md-7 col-12">
            <div id="stemiSubOptionsPanel" class="h-100" style="display: <?= ($initial_diagnosis_main === 'STEMI') ? 'block' : 'none' ?>;">
                
                <div class="card card-body border-0 bg-danger bg-opacity-10 h-100 rounded-3 animate__animated animate__fadeIn">
                    <h6 class="text-danger fw-bold small mb-3 border-bottom border-danger border-opacity-25 pb-2">
                        <i class="bi bi-list-check me-1"></i> ระบุตำแหน่ง STEMI (เลือกได้มากกว่า 1)
                    </h6>
                    
                    <div class="row g-2">
                        <?php
                        $stemi_subs = [
                            'STEMI, anterior or LBBB (I21.0)',
                            'STEMI, inferior wall (I21.1)',
                            'STEMI, other sites (I21.2)',
                            'STEMI, unspecified sites (I21.3)'
                        ];
                        // กัน Error กรณีตัวแปร array ว่าง
                        $checked_subs = $stemi_sub_to_check ?? [];

                        foreach ($stemi_subs as $index => $sub) {
                            $id = 'stemi_sub_' . $index;
                            $isChecked = in_array($sub, $checked_subs) ? 'checked' : '';
                            echo "
                            <div class='col-12'>
                                <div class='form-check bg-white p-2 rounded border border-danger border-opacity-25'>
                                    <input class='form-check-input ms-1' type='checkbox' name='stemi_sub[]' id='$id' value='$sub' $isChecked>
                                    <label class='form-check-label ms-2 small text-secondary fw-bold cursor-pointer' for='$id'>
                                        $sub
                                    </label>
                                </div>
                            </div>";
                        }
                        ?>
                    </div>
                </div>

            </div>
            
            <div id="noStemiPlaceholder" class="h-100 d-flex align-items-center justify-content-center text-muted border rounded-3 bg-light p-4" 
                 style="display: <?= ($initial_diagnosis_main !== 'STEMI') ? 'flex' : 'none' ?>; min-height: 180px;">
                <div class="text-center opacity-50">
                    <i class="bi bi-clipboard-x fs-1"></i>
                    <p class="small mt-2 mb-0">No additional details required<br>for this diagnosis.</p>
                </div>
            </div>

        </div>
    </div>
</div>
            
          <hr>
<div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
    <h6 class="text-primary fw-bold mb-3 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-bullseye me-2 fs-5"></i> Area of Infarction
        <span class="ms-auto badge bg-light text-secondary fw-normal border">เลือกได้มากกว่า 1 ข้อ</span>
    </h6>

    <div class="d-flex flex-wrap gap-2" id="areaInfarctionGroup">
        
        <div>
            <input type="checkbox" class="btn-check infarction-check" name="area_infarction[]" id="anterior_wall" value="Anterior wall" 
                   <?= in_array('Anterior wall', $area_infarction_to_check) ? 'checked' : '' ?> autocomplete="off" onchange="toggleNotKnown(false)">
            <label class="btn btn-outline-danger rounded-pill px-3" for="anterior_wall">
                <i class="bi bi-heart-pulse me-1"></i> Anterior wall
            </label>
        </div>

        <div>
            <input type="checkbox" class="btn-check infarction-check" name="area_infarction[]" id="inferior_wall" value="Inferior wall" 
                   <?= in_array('Inferior wall', $area_infarction_to_check) ? 'checked' : '' ?> autocomplete="off" onchange="toggleNotKnown(false)">
            <label class="btn btn-outline-danger rounded-pill px-3" for="inferior_wall">
                <i class="bi bi-heart-pulse me-1"></i> Inferior wall
            </label>
        </div>

        <div>
            <input type="checkbox" class="btn-check infarction-check" name="area_infarction[]" id="posterior_wall" value="Posterior wall" 
                   <?= in_array('Posterior wall', $area_infarction_to_check) ? 'checked' : '' ?> autocomplete="off" onchange="toggleNotKnown(false)">
            <label class="btn btn-outline-danger rounded-pill px-3" for="posterior_wall">
                <i class="bi bi-heart-pulse me-1"></i> Posterior wall
            </label>
        </div>

        <div>
            <input type="checkbox" class="btn-check infarction-check" name="area_infarction[]" id="lateral_wall" value="Lateral wall" 
                   <?= in_array('Lateral wall', $area_infarction_to_check) ? 'checked' : '' ?> autocomplete="off" onchange="toggleNotKnown(false)">
            <label class="btn btn-outline-danger rounded-pill px-3" for="lateral_wall">
                <i class="bi bi-heart-pulse me-1"></i> Lateral wall
            </label>
        </div>
        
        <div>
            <input type="checkbox" class="btn-check" name="area_infarction[]" id="not_known" value="Not known" 
                   <?= in_array('Not known', $area_infarction_to_check) ? 'checked' : '' ?> autocomplete="off" onchange="toggleNotKnown(true)">
            <label class="btn btn-outline-secondary rounded-pill px-3" for="not_known">
                <i class="bi bi-question-circle me-1"></i> Not known
            </label>
        </div>

    </div>
</div>

          <hr>
<div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
    
    <h6 class="text-primary fw-bold mb-4 d-flex align-items-center border-bottom pb-2">
        <i class="bi bi-heart-half me-2 fs-5"></i> Present Illness
    </h6>

    <div class="row mb-4 align-items-center">
        <div class="col-md-4 col-12 mb-2 mb-md-0">
            <strong class="text-secondary d-flex align-items-center">
                <i class="bi bi-activity me-2 text-primary opacity-50"></i> Angina
            </strong>
        </div>
        <div class="col-md-8 col-12">
            <div class="d-flex flex-wrap gap-2">
                <?php
                $angina_opts = [
                    'No' => ['class' => 'btn-outline-secondary', 'icon' => 'bi-x-circle'],
                    'Atypical' => ['class' => 'btn-outline-warning text-dark', 'icon' => 'bi-exclamation-circle'],
                    'Typical' => ['class' => 'btn-outline-danger', 'icon' => 'bi-exclamation-diamond-fill'],
                    'Unknown' => ['class' => 'btn-outline-warning text-dark', 'icon' => 'bi-question-circle']
                ];
                $cur_angina = $_POST['angina'] ?? '';
                foreach ($angina_opts as $val => $meta) {
                    $checked = ($cur_angina === $val) ? 'checked' : '';
                    echo "
                    <input type='radio' class='btn-check' name='angina' id='angina_$val' value='$val' $checked>
                    <label class='btn {$meta['class']} rounded-pill px-3 position-relative' for='angina_$val'>
                        <span class='default-icon'><i class='bi {$meta['icon']} me-1'></i></span>
                        <span class='check-indicator'><i class='bi bi-check-lg me-1 fw-bold'></i></span>
                        $val
                    </label>";
                }
                ?>
            </div>
        </div>
    </div>

    <div class="row mb-4 align-items-center border-top pt-3 border-light-subtle">
        <div class="col-md-4 col-12 mb-2 mb-md-0">
            <strong class="text-secondary d-flex align-items-center">
                <i class="bi bi-lungs me-2 text-primary opacity-50"></i> Dyspnea
            </strong>
        </div>
        <div class="col-md-8 col-12">
            <div class="d-flex flex-wrap gap-2">
                <?php
                $dyspnea_opts = [
                    'No' => 'btn-outline-secondary',
                    'DOE' => 'btn-outline-danger',
                    'Orthopnea' => 'btn-outline-danger',
                    'PND' => 'btn-outline-danger',
                    'Unknown' => 'btn-outline-warning text-dark'
                ];
                $cur_dyspnea = $_POST['dyspnea_type'] ?? '';
                foreach ($dyspnea_opts as $val => $cls) {
                    $checked = ($cur_dyspnea === $val) ? 'checked' : '';
                    $id = 'dyspnea_' . str_replace(' ', '', $val);
                    echo "
                    <input type='radio' class='btn-check' name='dyspnea_type' id='$id' value='$val' $checked>
                    <label class='btn $cls rounded-pill px-3' for='$id'>
                        <span class='check-indicator'><i class='bi bi-check-lg me-1'></i></span>
                        $val
                    </label>";
                }
                ?>
            </div>
        </div>
    </div>

    <div class="row mb-4 align-items-center border-top pt-3 border-light-subtle">
        <div class="col-md-4 col-12 mb-2 mb-md-0">
            <strong class="text-secondary d-flex align-items-center">
                <i class="bi bi-eye-slash me-2 text-primary opacity-50"></i> Syncope
            </strong>
        </div>
        <div class="col-md-8 col-12">
            <div class="d-flex flex-wrap gap-2">
                <input type="radio" class="btn-check" name="syncope" id="syncope_no" value="No" <?= ($_POST['syncope'] ?? '') == 'No' ? 'checked' : '' ?>>
                <label class="btn btn-outline-secondary rounded-pill px-3" for="syncope_no">
                    <span class='check-indicator'><i class='bi bi-check-lg me-1'></i></span> No
                </label>

                <input type="radio" class="btn-check" name="syncope" id="syncope_yes" value="Yes" <?= ($_POST['syncope'] ?? '') == 'Yes' ? 'checked' : '' ?>>
                <label class="btn btn-outline-danger rounded-pill px-3" for="syncope_yes">
                    <span class='check-indicator'><i class='bi bi-check-lg me-1'></i></span> Yes
                </label>

                <input type="radio" class="btn-check" name="syncope" id="syncope_unk" value="Unknown" <?= ($_POST['syncope'] ?? '') == 'Unknown' ? 'checked' : '' ?>>
                <label class="btn btn-outline-warning text-dark rounded-pill px-3" for="syncope_unk">
                    <span class='check-indicator'><i class='bi bi-check-lg me-1'></i></span> Unknown
                </label>
            </div>
        </div>
    </div>

    <div class="row mb-2 align-items-center border-top pt-3 border-light-subtle">
        <div class="col-md-4 col-12 mb-2 mb-md-0">
            <strong class="text-secondary d-flex align-items-center">
                <i class="bi bi-heartbreak me-2 text-danger"></i> Cardiac Arrest
            </strong>
        </div>
        <div class="col-md-8 col-12">
            <div class="d-flex flex-wrap gap-2">
                <input type="radio" class="btn-check" name="cardiac_arrest" id="arrest_no" value="No" <?= ($_POST['cardiac_arrest'] ?? '') == 'No' ? 'checked' : '' ?>>
                <label class="btn btn-outline-secondary rounded-pill px-3" for="arrest_no">
                    <span class='check-indicator'><i class='bi bi-check-lg me-1'></i></span> No
                </label>

                <input type="radio" class="btn-check" name="cardiac_arrest" id="arrest_yes" value="Yes" <?= ($_POST['cardiac_arrest'] ?? '') == 'Yes' ? 'checked' : '' ?>>
                <label class="btn btn-outline-danger rounded-pill px-3" for="arrest_yes">
                    <span class='check-indicator'><i class='bi bi-check-lg me-1'></i></span> Yes
                </label>

                <input type="radio" class="btn-check" name="cardiac_arrest" id="arrest_unk" value="Unknown" <?= ($_POST['cardiac_arrest'] ?? '') == 'Unknown' ? 'checked' : '' ?>>
                <label class="btn btn-outline-warning text-dark rounded-pill px-3" for="arrest_unk">
                    <span class='check-indicator'><i class='bi bi-check-lg me-1'></i></span> Unknown
                </label>
            </div>
        </div>
    </div>

</div>

          <hr>

<div class="form-section border rounded p-4 bg-white shadow-sm mb-4">
    <h5 class="section-title border-bottom pb-2 mb-3 text-primary"><i class="bi bi-exclamation-triangle"></i> Initial Complication</h5>
    
    <input type="hidden" name="heart_failure_value" id="heart_failure_input" value="<?= htmlspecialchars($heart_failure_value) ?>">
    <input type="hidden" name="on_ett_value" id="on_ett_input" value="<?= htmlspecialchars($on_ett_value) ?>">
    <input type="hidden" name="killip_class_value" id="killip_class_input" value="<?= htmlspecialchars($killip_class_value) ?>">
    <input type="hidden" name="arrhythmia_value" id="arrhythmia_input" value="<?= htmlspecialchars($arrhythmia_value) ?>">
    <input type="hidden" name="arrhythmia_vtvf_value" id="arrhythmia_vtvf_input" value="<?= htmlspecialchars($arrhythmia_vtvf_value) ?>">
    <input type="hidden" name="arrhythmia_chb_value" id="arrhythmia_chb_input" value="<?= htmlspecialchars($arrhythmia_chb_value) ?>">
    <input type="hidden" name="cpr_value" id="cpr_input" value="<?= htmlspecialchars($cpr_value) ?>">
    <input type="hidden" name="death_value" id="death_input" value="<?= htmlspecialchars($_POST['death_value'] ?? '') ?>">
    <input type="hidden" name="dead_status_value" id="dead_status_input" value="<?= htmlspecialchars($_POST['dead_status_value'] ?? '') ?>">

    <div class="row g-3">
        <div class="col-md-6">
            <div class="p-3 border rounded h-100 bg-light">
                <label class="fw-bold mb-2">Heart Failure</label>
                <div class="d-flex gap-2 mb-2">
                    <input type="radio" class="btn-check hf-toggle" name="heart_failure_value" id="hf_no" value="No" <?= ($heart_failure_value === 'No') ? 'checked' : '' ?>>
                    <label class="btn btn-outline-custom-gray" for="hf_no">No</label>
                    <input type="radio" class="btn-check hf-toggle" name="heart_failure_value" id="hf_yes" value="Yes" <?= ($heart_failure_value === 'Yes') ? 'checked' : '' ?>>
                    <label class="btn btn-outline-custom-gray" for="hf_yes">Yes</label>
                </div>
                <div id="on_ett_section" class="mt-3 p-2 border-start border-3 ps-3" style="display:<?= ($heart_failure_value === 'Yes') ? 'block' : 'none' ?>;">
                    <label class="fw-bold small text-muted">On ETT</label>
                    <div class="d-flex gap-2">
                        <input type="radio" class="btn-check" name="on_ett_value" id="ett_no" value="No" <?= ($on_ett_value === 'No') ? 'checked' : '' ?>>
                        <label class="btn btn-outline-custom-gray btn-sm" for="ett_no">No</label>
                        <input type="radio" class="btn-check" name="on_ett_value" id="ett_yes" value="Yes" <?= ($on_ett_value === 'Yes') ? 'checked' : '' ?>>
                        <label class="btn btn-outline-custom-gray btn-sm" for="ett_yes">Yes</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="p-3 border rounded h-100 bg-light">
                <label class="fw-bold mb-2">Killip Class</label>
                <div class="btn-toggle-responsive">
                    <button type="button" class="check-btn <?= isActive($killip_class_value, 'I') ?>" data-toggle="killip" data-input-target="killip_class_input" value="I">I</button>
                    <button type="button" class="check-btn <?= isActive($killip_class_value, 'II') ?>" data-toggle="killip" data-input-target="killip_class_input" value="II">II</button>
                    <button type="button" class="check-btn <?= isActive($killip_class_value, 'III') ?>" data-toggle="killip" data-input-target="killip_class_input" value="III">III</button>
                    <button type="button" class="check-btn <?= isActive($killip_class_value, 'IV') ?>" data-toggle="killip" data-input-target="killip_class_input" value="IV">IV</button>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="p-3 border rounded bg-light">
                <label class="fw-bold mb-2">Arrhythmia</label>
                <div class="d-flex gap-2 mb-2">
                    <input type="radio" class="btn-check arr-toggle" name="arrhythmia_value" id="arr_no" value="No" <?= ($arrhythmia_value === 'No') ? 'checked' : '' ?>>
                    <label class="btn btn-outline-custom-gray" for="arr_no">No</label>
                    <input type="radio" class="btn-check arr-toggle" name="arrhythmia_value" id="arr_yes" value="Yes" <?= ($arrhythmia_value === 'Yes') ? 'checked' : '' ?>>
                    <label class="btn btn-outline-custom-gray" for="arr_yes">Yes</label>
                </div>
                <div id="arrhythmiaDetail" class="mt-3 p-3 bg-white border rounded" style="display:<?= ($arrhythmia_value === 'Yes') ? 'block' : 'none' ?>;">
                    <label class="form-label fw-bold small text-muted">Arrhythmia Type (เลือก 1 ชนิดหลัก)</label>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input arr-main-radio" type="radio" name="arrhythmia_main_type" id="arr_vtvf" value="VT/VF" <?= ($arrhythmia_main_type === 'VT/VF') ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold small" for="arr_vtvf">1. VT/VF (Defibrillation)</label>
                                <div id="vtvf_options" class="mt-2 d-flex gap-2 ps-2" style="display:<?= $arrhythmia_main_type === 'VT/VF' ? 'flex' : 'none' ?>;">
                                    <input type="radio" class="btn-check" name="arrhythmia_vtvf_value" id="vtvf_no" value="No" <?= ($arrhythmia_vtvf_value === 'No') ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-custom-gray btn-sm" for="vtvf_no">No</label>
                                    <input type="radio" class="btn-check" name="arrhythmia_vtvf_value" id="vtvf_yes" value="Yes" <?= ($arrhythmia_vtvf_value === 'Yes') ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-custom-gray btn-sm" for="vtvf_yes">Yes</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input arr-main-radio" type="radio" name="arrhythmia_main_type" id="arr_chb" value="CHB" <?= ($arrhythmia_main_type === 'CHB') ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold small" for="arr_chb">2. CHB (Pacemaker)</label>
                                <div id="chb_options" class="mt-2 d-flex gap-2 ps-2" style="display:<?= $arrhythmia_main_type === 'CHB' ? 'flex' : 'none' ?>;">
                                    <input type="radio" class="btn-check" name="arrhythmia_chb_value" id="chb_no" value="No" <?= ($arrhythmia_chb_value === 'No') ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-custom-gray btn-sm" for="chb_no">No</label>
                                    <input type="radio" class="btn-check" name="arrhythmia_chb_value" id="chb_yes" value="Yes" <?= ($arrhythmia_chb_value === 'Yes') ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-custom-gray btn-sm" for="chb_yes">Yes</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check d-flex align-items-center gap-2">
                                <input class="form-check-input arr-main-radio" type="radio" name="arrhythmia_main_type" id="arr_other_chk" value="Other" <?= ($arrhythmia_main_type === 'Other') ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold small" for="arr_other_chk">3. อื่น ๆ</label>
                                <input type="text" class="form-control form-control-sm" name="arrhythmia_other" id="arr_other_input" placeholder="ระบุ..." style="display:<?= $arrhythmia_main_type === 'Other' ? 'block' : 'none' ?>;" value="<?= htmlspecialchars($arrhythmia_other) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="p-3 border rounded h-100 bg-light">
                <label class="fw-bold mb-2">CPR</label>
                <div class="btn-toggle-responsive mb-2">
                    <button type="button" class="check-btn <?= isActive($cpr_value, 'No') ?>" data-toggle="cpr" data-input-target="cpr_input" value="No">No</button>
                    <button type="button" class="check-btn <?= isActive($cpr_value, 'Yes') ?>" data-toggle="cpr" data-input-target="cpr_input" value="Yes">Yes</button>
                </div>
                <input type="text" class="form-control mt-2 shadow-sm" id="cpr_detail_input" name="cpr_detail" placeholder="ระบุรายละเอียด CPR..." style="display:<?= ($cpr_value === 'Yes') ? 'block' : 'none' ?>;" value="<?= htmlspecialchars($cpr_detail) ?>">
            </div>
        </div>

        <div class="col-md-6">
            <div class="p-3 border rounded h-100 bg-light">
                <label class="fw-bold mb-2">Death</label>
                <div class="btn-toggle-responsive mb-2">
                    <button type="button" class="check-btn <?= isActive($_POST['death_value'] ?? '', 'No') ?>" data-toggle="death" data-input-target="death_input" value="No" onclick="toggleDeadStatus(false)">No</button>
                    <button type="button" class="check-btn <?= isActive($_POST['death_value'] ?? '', 'Yes') ?>" data-toggle="death" data-input-target="death_input" value="Yes" onclick="toggleDeadStatus(true)">Yes</button>
                </div>
                <div id="dead_status_section" class="mt-3 p-2 border-start border-3 border-danger ps-3" style="display: <?= ($_POST['death_value'] ?? '') === 'Yes' ? 'block' : 'none' ?>;">
                    <label class="fw-bold small text-danger">Dead Status</label>
                    <div class="btn-toggle-responsive flex-wrap">
                        <button type="button" class="check-btn btn-sm <?= isActive($_POST['dead_status_value'] ?? '', 'Pre Hospital') ?>" data-toggle="dead_status" data-input-target="dead_status_input" value="Pre Hospital">Pre Hospital</button>
                        <button type="button" class="check-btn btn-sm <?= isActive($_POST['dead_status_value'] ?? '', 'ER') ?>" data-toggle="dead_status" data-input-target="dead_status_input" value="ER">ER</button>
                        <button type="button" class="check-btn btn-sm <?= isActive($_POST['dead_status_value'] ?? '', 'Admission') ?>" data-toggle="dead_status" data-input-target="dead_status_input" value="Admission">Admission</button>
                        <button type="button" class="check-btn btn-sm <?= isActive($_POST['dead_status_value'] ?? '', 'During Transfer out') ?>" data-toggle="dead_status" data-input-target="dead_status_input" value="During Transfer out">During Transfer</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="form-section border rounded p-4 bg-white shadow-sm">
    <h5 class="section-title border-bottom pb-2 mb-3 text-success"><i class="bi bi-calculator"></i> GRACE ACS Risk Score</h5>
    
    <input type="hidden" name="arrest_at_admission_value" id="arrest_input" value="<?= htmlspecialchars($_POST['arrest_at_admission_value'] ?? '') ?>">
    <input type="hidden" name="st_segment_deviation_value" id="stdev_input" value="<?= htmlspecialchars($_POST['st_segment_deviation_value'] ?? '') ?>">
    <input type="hidden" name="elevated_troponin_value" id="trop_input" value="<?= htmlspecialchars($_POST['elevated_troponin_value'] ?? '') ?>">
    
    <div class="row g-3 mb-4"> 
        <div class="col-md-4">
            <div class="p-2 border rounded bg-light text-center h-100">
                <label class="small fw-bold">Cardiac Arrest at Admission</label><br>
                <div class="btn-toggle-responsive mt-2">
                    <button type="button" class="check-btn <?= isActive($_POST['arrest_at_admission_value'] ?? '', 'No') ?>" data-toggle="arrest" data-input-target="arrest_input" value="No">No</button>
                    <button type="button" class="check-btn <?= isActive($_POST['arrest_at_admission_value'] ?? '', 'Yes') ?>" data-toggle="arrest" data-input-target="arrest_input" value="Yes">Yes</button>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-2 border rounded bg-light text-center h-100">
                <label class="small fw-bold">S-T Segment Deviation</label><br>
                <div class="btn-toggle-responsive mt-2">
                    <button type="button" class="check-btn <?= isActive($_POST['st_segment_deviation_value'] ?? '', 'No') ?>" data-toggle="stdev" data-input-target="stdev_input" value="No">No</button>
                    <button type="button" class="check-btn <?= isActive($_POST['st_segment_deviation_value'] ?? '', 'Yes') ?>" data-toggle="stdev" data-input-target="stdev_input" value="Yes">Yes</button>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-2 border rounded bg-light text-center h-100">
                <label class="small fw-bold">Elevated Troponin</label><br>
                <div class="btn-toggle-responsive mt-2">
                    <button type="button" class="check-btn <?= isActive($_POST['elevated_troponin_value'] ?? '', 'No') ?>" data-toggle="trop" data-input-target="trop_input" value="No">No</button>
                    <button type="button" class="check-btn <?= isActive($_POST['elevated_troponin_value'] ?? '', 'Yes') ?>" data-toggle="trop" data-input-target="trop_input" value="Yes">Yes</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 p-3 border rounded bg-light">
        <div class="col-md-3 col-6">
            <label class="small fw-bold">HR (bpm)</label>
            <input type="number" step="any" class="form-control grace-input shadow-sm" id="hr" name="hr" value="<?= htmlspecialchars($_POST['hr'] ?? '') ?>">
        </div>
        <div class="col-md-3 col-6">
            <label class="small fw-bold">Systolic BP</label>
            <input type="number" step="any" class="form-control grace-input shadow-sm" id="bp" name="bp" value="<?= htmlspecialchars($_POST['bp'] ?? '') ?>">
        </div>
        <div class="col-md-3 col-6">
            <label class="small fw-bold">Diastolic BP</label>
            <input type="number" step="any" class="form-control grace-input shadow-sm" id="bp_diastolic" name="bp_diastolic" value="<?= htmlspecialchars($_POST['bp_diastolic'] ?? '') ?>">
        </div>
        <div class="col-md-3 col-6">
            <label class="small fw-bold">Creatinine (mg/dL)</label>
            <input type="number" step="0.01" class="form-control grace-input shadow-sm" id="creatinine" name="creatinine" value="<?= htmlspecialchars($_POST['creatinine'] ?? '') ?>">
        </div>
        <div class="col-md-4 col-4">
            <label class="small fw-bold">RR</label>
            <input type="number" step="any" class="form-control" name="rr" value="<?= htmlspecialchars($_POST['rr'] ?? '') ?>">
        </div>
        <div class="col-md-4 col-4">
            <label class="small fw-bold">O2 Sat (%)</label>
            <input type="number" step="any" class="form-control" name="o2sat" value="<?= htmlspecialchars($_POST['o2sat'] ?? '') ?>">
        </div>
        <div class="col-md-4 col-4">
            <label class="small fw-bold">GFR</label>
            <input type="number" step="any" class="form-control grace-input shadow-sm" id="gfr" name="gfr" value="<?= htmlspecialchars($_POST['gfr'] ?? '') ?>">
        </div>
    </div>

    <div class="mt-4">
        <div class="border rounded p-3 text-center bg-white shadow-sm" style="border: 2px solid #0d6efd;">
            <strong class="text-primary fs-5">GRACE Score </strong><br>
            <input type="text" class="form-control mt-2 text-center fw-bold fs-3 border-0 bg-transparent" id="grace_score_display" name="grace_score" readonly style="color: #198754;" value="<?= htmlspecialchars($_POST['grace_score'] ?? '') ?>">
            <div id="risk_level" class="badge p-2 mt-1"></div>
        </div>
    </div>
</div>

            <hr>
      <hr>
<div class="d-flex justify-content-between gap-2 mt-4 d-print-none">
    <button type="submit" name="direction" value="back" class="btn btn-secondary px-4">
        <i class="bi bi-arrow-left"></i> BACK
    </button>
    
    <button type="submit" name="direction" value="next" class="btn btn-success px-5">
        SAVE & NEXT <i class="bi bi-arrow-right"></i>
    </button>
</div>
        </form>
    </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ✅ Logic สำหรับ Onset Unknown (Disable Date/Time Input)
document.addEventListener("DOMContentLoaded", function() {
    const onsetUnknownChk = document.getElementById('onset_unknown_chk');
    const onsetDateInput = document.getElementById('onset_date_input');
    const onsetTimeInput = document.getElementById('onset_time_input');
    const cprDetailInput = document.getElementById('cpr_detail_input');
    const onEttSection = document.getElementById('on_ett_section');
    const arrhythmiaDetail = document.getElementById('arrhythmiaDetail');
    const arrhythmiaInput = document.getElementById('arrhythmia_input'); // Hidden input for Arrhythmia Yes/No

    const arrMainRadios = document.querySelectorAll('.arr-main-radio');
    const vtvfOptions = document.getElementById('vtvf_options');
    const chbOptions = document.getElementById('chb_options');
    const arrOtherInput = document.getElementById('arr_other_input');

    // --- 1. Onset Unknown Toggle ---
    if (onsetUnknownChk && onsetDateInput && onsetTimeInput) {
        function toggleOnsetInputs() {
            const isDisabled = onsetUnknownChk.checked;
            onsetDateInput.disabled = isDisabled;
            onsetTimeInput.disabled = isDisabled;
            
            if (isDisabled) {
                onsetDateInput.value = '';
                onsetTimeInput.value = '';
            }
        }
        
        toggleOnsetInputs();
        onsetUnknownChk.addEventListener('change', toggleOnsetInputs);
    }
    
    // --- 2. FMC Logic is now handled by the general check-btn logic below ---

    // --- 3. Ding Button Persistence ---
    document.querySelectorAll('.ding-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Unselect others (visual only)
            document.querySelectorAll('.ding-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Set hidden input value
            document.getElementById('diagnosis_btn_input').value = this.id === 'btnDingAcs' ? 'acs' : 'stemi';
            
            // Trigger navigation (as intended)
            selectDing(this.id === 'btnDingAcs' ? 'acs' : 'stemi');
        });
    });
    
    // --- 4. STEMI Sub Options Toggle is handled by the general check-btn logic below ---

    // --- 5. General Check-btn Logic (Refactored) ---
    document.querySelectorAll('.check-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const group = this.dataset.toggle;
            const inputTargetId = this.dataset.inputTarget;
            const currentValue = this.value;

            // Update active class for the button group
            document.querySelectorAll(`.check-btn[data-toggle="${group}"]`).forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            // Update hidden input value
            if (inputTargetId) {
                document.getElementById(inputTargetId).value = currentValue;
            }

            // Generic show/hide logic based on data-show/data-hide attributes
            const showTarget = this.dataset.show;
            const hideTarget = this.dataset.hide;
            if (showTarget) document.getElementById(showTarget).style.display = 'block';
            if (hideTarget) document.getElementById(hideTarget).style.display = 'none';

            // --- Special logic for clearing values or other side-effects ---

            if (group === 'fmc_select' && currentValue !== 'EMS') {
                document.querySelector('input[name="ems_date"]').value = '';
                document.querySelector('input[name="ems_time"]').value = '';
            }

            if (group === 'initial_dx' && currentValue !== 'STEMI') {
                document.querySelectorAll('input[name="stemi_sub[]"]').forEach(chk => chk.checked = false);
            }

            if (group === 'heart_failure' && currentValue !== 'Yes') {
                document.querySelectorAll('.check-btn[data-toggle="on_ett"]').forEach(b => b.classList.remove('active'));
                document.getElementById('on_ett_input').value = '';
            }
            
            if (group === 'cpr') {
                if (currentValue === 'Yes') {
                    cprDetailInput.focus();
                } else {
                    cprDetailInput.value = '';
                }
            }
        });
    });

document.addEventListener('change', function(e) {
    // 1. ควบคุม Arrhythmia หลัก (Yes/No)
    if (e.target.classList.contains('arr-toggle')) {
        const detailDiv = document.getElementById('arrhythmiaDetail');
        detailDiv.style.display = (e.target.value === 'Yes') ? 'block' : 'none';
    }

    // เมื่อมีการเปลี่ยน Arrhythmia Main Type (VT/VF, CHB, Other)
    if (e.target.classList.contains('arr-main-radio')) {
        const vtvfOptions = document.getElementById('vtvf_options');
        const chbOptions = document.getElementById('chb_options');
        const otherInput = document.getElementById('arr_other_input');

        // รีเซ็ตการแสดงผล
        vtvfOptions.style.display = 'none';
        chbOptions.style.display = 'none';
        otherInput.style.display = 'none';

        // แสดงผลเฉพาะตัวที่เลือก และล้างค่าตัวที่ไม่เกี่ยวข้อง
        if (e.target.value === 'VT/VF') {
            vtvfOptions.style.display = 'flex';
            // ล้างค่า CHB
            document.querySelectorAll('input[name="arrhythmia_chb_value"]').forEach(el => el.checked = false);
            document.getElementById('arr_other_input').value = '';
        } 
        else if (e.target.value === 'CHB') {
            chbOptions.style.display = 'flex';
            // ล้างค่า VT/VF
            document.querySelectorAll('input[name="arrhythmia_vtvf_value"]').forEach(el => el.checked = false);
            document.getElementById('arr_other_input').value = '';
        } 
        else if (e.target.value === 'Other') {
            otherInput.style.display = 'block';
            otherInput.focus();
            // ล้างค่าทั้งสอง
            document.querySelectorAll('input[name="arrhythmia_vtvf_value"], input[name="arrhythmia_chb_value"]').forEach(el => el.checked = false);
        }
    }
});

    // --- 7. ER Details Collapse Toggle (Improved) ---
    const erCollapseElement = document.getElementById('erDetailsCollapse');
    const erButton = document.getElementById('btn_toggle_er_details');
    
    if (erButton && erCollapseElement) {
        // สร้าง instance ของ Collapse และกำหนดให้ไม่ Toggle โดยอัตโนมัติเมื่อสร้าง
        const erCollapseInstance = new bootstrap.Collapse(erCollapseElement, { toggle: false });

        // จัดการการคลิกที่ปุ่มเพื่อเปิด/ปิด Collapse
        erButton.addEventListener('click', function() {
            erCollapseInstance.toggle();
        });

        // จัดการสถานะ active ของปุ่มเมื่อ Collapse ถูกเปิด
        erCollapseElement.addEventListener('shown.bs.collapse', function() {
            erButton.classList.add('active');
        });

        // จัดการสถานะ active ของปุ่มเมื่อ Collapse ถูกปิด
        erCollapseElement.addEventListener('hidden.bs.collapse', function() {
            erButton.classList.remove('active');
        });
    }
});

// ✅ แก้ไขใหม่โดยแนบ patient_id ไปด้วย
function selectDing(type) {
    const patientId = "<?= $patient_id ?>"; // ดึงค่าจากตัวแปร PHP ที่ประกาศไว้ด้านบนสุด
    if (type === 'acs') {
        window.location.href = 'ding_acs.php?id=' + patientId;
    } else {
        window.location.href = 'ding_stemi.php?id=' + patientId;
    }
}
document.addEventListener('DOMContentLoaded', function() {
    // 1. ระบุ Checkbox
    const notKnownCheckbox = document.getElementById('not_known');
    const standardCheckboxes = document.querySelectorAll('.infarction-check'); // ตัวเลือก Anterior/Inferior/Posterior/Lateral

    if (notKnownCheckbox && standardCheckboxes.length > 0) {
        
        // --- 1. จัดการเมื่อคลิก 'Not known' ---
        notKnownCheckbox.addEventListener('change', function() {
            if (this.checked) {
                // ถ้า 'Not known' ถูกเลือก ให้ยกเลิกการเลือก Checkbox อื่นทั้งหมด
                standardCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
            }
        });

        // --- 2. จัดการเมื่อคลิก Checkbox มาตรฐาน (Anterior, Inferior, ฯลฯ) ---
        standardCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    // ถ้ามีการเลือก Checkbox มาตรฐานใดๆ ให้ยกเลิกการเลือก 'Not known'
                    notKnownCheckbox.checked = false;
                }
            });
        });
    }
});
document.addEventListener('DOMContentLoaded', function() {
        const notKnownInfarct = document.getElementById('not_known');
        const infarctOpts = document.querySelectorAll('.infarction-check');

        // เมื่อกด "Not known"
        notKnownInfarct.addEventListener('change', function() {
            if (this.checked) {
                // ปลดการเลือกตัวเลือกผนังหัวใจทั้งหมด
                infarctOpts.forEach(opt => {
                    opt.checked = false;
                });
            }
        });

        // เมื่อกดเลือกผนังหัวใจด้านใดด้านหนึ่ง
        infarctOpts.forEach(opt => {
            opt.addEventListener('change', function() {
                if (this.checked) {
                    // ปลดการเลือกปุ่ม "Not known" ออกทันที
                    notKnownInfarct.checked = false;
                }
            });
        });
    });

// ฟังก์ชันคำนวณ Door to ECG
function calculateDoorToEkg() {
    const doorInputs = [
        document.querySelector('input[name="hospital_time_rpch"]'),
        document.querySelector('input[name="hospital_time_rpth"]'),
        document.querySelector('input[name="hospital_time_hatyai"]')
    ];
    
    let arrivalTime = "";
    for (const input of doorInputs) {
        if (input && input.value) {
            arrivalTime = input.value;
            break;
        }
    }

    const ekgTime = document.getElementById('first_ekg_time').value;
    const resultInput = document.getElementById('door_to_ekg_time');

    if (arrivalTime && ekgTime) {
        const start = new Date(`2025-01-01T${arrivalTime}`);
        const end = new Date(`2025-01-01T${ekgTime}`);
        
        let diff = (end - start) / (1000 * 60); // ผลลัพธ์เป็นนาที

        // กรณีข้ามวัน (เช่น มา 23:55 ทำ EKG 00:05)
        if (diff < 0) diff += 1440; 

        resultInput.value = Math.round(diff);

        // เตือนสีแดงถ้าเกิน 10 นาที (ตามมาตรฐาน KPI)
        resultInput.style.color = (diff > 10) ? 'red' : '#198754';
    }
}

// ฟังก์ชันก๊อปปี้วันที่และเวลาจากใบแรกมาใบวินิจฉัย
function copyEkgData() {
    document.getElementById('diag_ekg_date').value = document.getElementById('first_ekg_date').value;
    document.getElementById('diag_ekg_time').value = document.getElementById('first_ekg_time').value;
}

// ผูก Event การคำนวณเมื่อมีการเปลี่ยนเวลา
document.getElementById('first_ekg_time').addEventListener('change', calculateDoorToEkg);
// ดักฟัง event จาก input เวลาของโรงพยาบาลทั้งหมด
document.querySelector('input[name="hospital_time_rpch"]')?.addEventListener('change', calculateDoorToEkg);
document.querySelector('input[name="hospital_time_rpth"]')?.addEventListener('change', calculateDoorToEkg);
document.querySelector('input[name="hospital_time_hatyai"]')?.addEventListener('change', calculateDoorToEkg);
document.addEventListener('DOMContentLoaded', function() {
    const fmcRadios = document.querySelectorAll('.fmc-radio');
    const fmcInput = document.getElementById('fmc_input');
    const emsInfo = document.getElementById('emsInfo');

    fmcRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            // อัปเดตค่าใน Hidden Input
            fmcInput.value = this.value;

            // ควบคุมการแสดงผลช่อง EMS Info
            if (this.value === 'EMS') {
                emsInfo.style.display = 'block';
            } else {
                emsInfo.style.display = 'none';
                // ล้างค่าในช่อง EMS เมื่อถูกซ่อน (Optional)
                // document.querySelector('input[name="ems_date"]').value = '';
                // document.querySelector('input[name="ems_time"]').value = '';
            }
        });
    });
});


document.addEventListener('change', function(e) {
    if (e.target.classList.contains('hf-toggle')) {
        const onEttSection = document.getElementById('on_ett_section');
        if (e.target.value === 'Yes') {
            onEttSection.style.display = 'block';
        } else {
            onEttSection.style.display = 'none';
            // ล้างค่า On ETT เมื่อซ่อน (ป้องกันข้อมูลค้าง)
            document.querySelectorAll('input[name="on_ett_value"]').forEach(radio => radio.checked = false);
        }
    }
});


document.addEventListener('DOMContentLoaded', function() {
    const creatinineInput = document.getElementById('creatinine');
    const gfrInput = document.getElementById('gfr');

    // ฟังก์ชันคำนวณ eGFR เบื้องต้น เมื่อกรอก Creatinine
    if (creatinineInput && gfrInput) {
        creatinineInput.addEventListener('input', function() {
            const scr = parseFloat(this.value);
            // ในระบบจริงควรดึงจากฟิลด์อายุและเพศผู้ป่วย
            const age = <?= $patient_age ?>;
            const isFemale = <?= ($pt_info['gender'] ?? 'Male') === 'Female' ? 'true' : 'false' ?>; // Placeholder for gender

            if (scr > 0) {
                // CKD-EPI formula
                let kappa = isFemale ? 0.7 : 0.9;
                let alpha = isFemale ? -0.241 : -0.411;
                let genderConstant = isFemale ? 1.012 : 1.0;

                let egfr = 142 * Math.pow(Math.min(scr / kappa, 1), alpha) * Math.pow(Math.max(scr / kappa, 1), -1.2) * Math.pow(0.9938, age) * genderConstant;

                gfrInput.value = Math.round(egfr);
                
                // Manually trigger GRACE score update after GFR changes
                calculateGraceScore();
            }
        });
    }

    // Complete GRACE Score calculation function from existing script
    function calculateGraceScore() {
        let score = 0;

        // 1. Age (ดึงค่าจาก input หรือ sessionStorage ถ้ามี)
        let age = <?= $patient_age ?>; // ค่า Default สำหรับทดสอบ
        if (age < 30) score += 0;
        else if (age < 40) score += 8;
        else if (age < 50) score += 25;
        else if (age < 60) score += 41;
        else if (age < 70) score += 58;
        else if (age < 80) score += 75;
        else if (age < 90) score += 91;
        else score += 100;

        // 2. Heart Rate
        const hr = parseFloat(document.getElementById('hr').value) || 0;
        if (hr > 0) {
            if (hr < 50) score += 0;
            else if (hr < 70) score += 3;
            else if (hr < 90) score += 9;
            else if (hr < 110) score += 15;
            else if (hr < 150) score += 24;
            else if (hr < 200) score += 38;
            else score += 46;
        }

        // 3. Systolic BP
        const bp = parseFloat(document.getElementById('bp').value) || 0;
        if (bp > 0) {
            if (bp < 80) score += 58;
            else if (bp < 100) score += 53;
            else if (bp < 120) score += 43;
            else if (bp < 140) score += 34;
            else if (bp < 160) score += 24;
            else if (bp < 200) score += 10;
            else score += 0;
        }

        // 4. Creatinine (mg/dL)
        const creatinine = parseFloat(document.getElementById('creatinine').value) || 0;
        if (creatinine > 0) {
            if (creatinine < 0.4) score += 1;
            else if (creatinine < 0.8) score += 4;
            else if (creatinine < 1.2) score += 7;
            else if (creatinine < 1.6) score += 10;
            else if (creatinine < 2.0) score += 13;
            else if (creatinine < 4.0) score += 21;
            else score += 28;
        }

        // 5. Killip Class
        const killip = document.getElementById('killip_class_input').value;
        if (killip === 'I') score += 0;
        else if (killip === 'II') score += 20;
        else if (killip === 'III') score += 39;
        else if (killip === 'IV') score += 59;

        // 6. Cardiac Arrest at Admission
        const arrest = document.getElementById('arrest_input').value;
        if (arrest === 'Yes') score += 39;

        // 7. ST-segment deviation
        const stdev = document.getElementById('stdev_input').value;
        if (stdev === 'Yes') score += 28;

        // 8. Elevated Troponin
        const trop = document.getElementById('trop_input').value;
        if (trop === 'Yes') score += 14;

        // Display results
        const display = document.getElementById('grace_score_display');
        const riskLabel = document.getElementById('risk_level');
        
        if(display && riskLabel) {
            display.value = score;
            if (score <= 108) {
                riskLabel.innerText = "Low Risk (< 1%)";
                riskLabel.className = "small mt-1 fw-bold text-success";
            } else if (score <= 140) {
                riskLabel.innerText = "Intermediate Risk (1-3%)";
                riskLabel.className = "small mt-1 fw-bold text-warning";
            } else {
                riskLabel.innerText = "High Risk (> 3%)";
                riskLabel.className = "small mt-1 fw-bold text-danger";
            }
        }
    }

    // Add robust event listeners
    document.addEventListener('input', calculateGraceScore);
    document.addEventListener('click', calculateGraceScore);
    
    // Initial calculation on page load
    calculateGraceScore();
});

document.addEventListener('change', function(e) {
    const ekgResult = document.getElementById('ekg_interpretation');
    
    // 1. ดักจับการเลือก Diagnosis หลัก (STEMI, NSTEMI, UA)
    if (e.target.name === 'initial_diagnosis_main') {
        updateEKGResult();
    }

    // 2. ดักจับการเลือกตำแหน่ง STEMI (anterior, inferior, ฯลฯ)
    if (e.target.name === 'stemi_sub[]') {
        updateEKGResult();
    }

    function updateEKGResult() {
        let mainDx = document.querySelector('input[name="initial_diagnosis_main"]:checked')?.value || "";
        let subs = [];
        
        // ถ้าเป็น STEMI ให้ไปดูว่าเลือกตำแหน่งไหนไว้บ้าง
        if (mainDx === "STEMI") {
            document.querySelectorAll('input[name="stemi_sub[]"]:checked').forEach(cb => {
                // ตัดส่วนรหัส ICD-10 ออกให้เหลือแต่ชื่อตำแหน่งสั้นๆ
                let cleanText = cb.nextElementSibling.innerText.split('(')[0].trim();
                subs.push(cleanText);
            });
            
            // ถ้ามีตำแหน่ง ให้แสดงผลเป็น "STEMI: Anterior..." ถ้าไม่มี ให้แสดงแค่ STEMI
            ekgResult.value = subs.length > 0 ? "STEMI: " + subs.join(', ') : "STEMI";
        } else {
            // ถ้าเป็น NSTEMI หรือ UA ให้แสดงตามนั้นเลย
            ekgResult.value = mainDx;
        }
    }
});
function toggleDeadStatus(show) {
    const section = document.getElementById('dead_status_section');
    const input = document.getElementById('dead_status_input'); // Hidden input สำหรับ dead status

    if (show) {
        section.style.display = 'block';
    } else {
        section.style.display = 'none';
        // ล้างค่าที่เคยเลือกไว้หากผู้ใช้เปลี่ยนใจจาก Yes เป็น No
        if (input) input.value = '';
        document.querySelectorAll('[data-toggle="dead_status"]').forEach(btn => {
            btn.classList.remove('active');
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.grace-input');
    const display = document.getElementById('grace_score_display');
    const riskLabel = document.getElementById('risk_level');

    function calculateGrace() {
        let score = 0;

        // 1. อายุ (Age) - ดึงจากข้อมูลผู้ป่วย หรือกำหนดค่าเริ่มต้น (ควรมี input id="age")
        let age = <?= $patient_age ?>; 
        if (age < 30) score += 0;
        else if (age < 40) score += 8;
        else if (age < 50) score += 25;
        else if (age < 60) score += 41;
        else if (age < 70) score += 58;
        else if (age < 80) score += 75;
        else if (age < 90) score += 91;
        else score += 100;

        // 2. Heart Rate (HR)
        const hr = parseFloat(document.getElementById('hr').value) || 0;
        if (hr >= 200) score += 46;
        else if (hr >= 150) score += 38;
        else if (hr >= 110) score += 24;
        else if (hr >= 90) score += 15;
        else if (hr >= 70) score += 9;
        else if (hr >= 50) score += 3;

        // 3. Systolic BP (ใช้เฉพาะตัวบนในการคำนวณ GRACE)
        const sbp = parseFloat(document.getElementById('bp').value) || 0;
        if (sbp > 0) {
            if (sbp < 80) score += 58;
            else if (sbp < 100) score += 53;
            else if (sbp < 120) score += 43;
            else if (sbp < 140) score += 34;
            else if (sbp < 160) score += 24;
            else if (sbp < 200) score += 10;
        }

        // 4. Creatinine (mg/dL) - รองรับทศนิยม
        const cr = parseFloat(document.getElementById('creatinine').value) || 0;
        if (cr >= 4.0) score += 28;
        else if (cr >= 2.0) score += 21;
        else if (cr >= 1.6) score += 13;
        else if (cr >= 1.2) score += 10;
        else if (cr >= 0.8) score += 7;
        else if (cr >= 0.4) score += 4;
        else if (cr > 0) score += 1;

        // 5. ปุ่มกด Yes/No (Cardiac Arrest, ST Deviation, Troponin)
        if (document.getElementById('arrest_input').value === 'Yes') score += 39;
        if (document.getElementById('stdev_input').value === 'Yes') score += 28;
        if (document.getElementById('trop_input').value === 'Yes') score += 14;

        // 6. Killip Class (ดึงค่าจาก Hidden Input ของส่วน Killip Class)
        const killip = document.getElementById('killip_class_input')?.value;
        if (killip === 'II') score += 20;
        else if (killip === 'III') score += 39;
        else if (killip === 'IV') score += 59;

        // แสดงผลคะแนน
        display.value = score;

        // แปลผลระดับความเสี่ยง
        if (score <= 108) {
            riskLabel.innerText = "Low Risk (Mortality < 1%)";
            riskLabel.style.color = "#198754";
        } else if (score <= 140) {
            riskLabel.innerText = "Intermediate Risk (1-3%)";
            riskLabel.style.color = "#ffc107";
        } else {
            riskLabel.innerText = "High Risk (> 3%)";
            riskLabel.style.color = "#dc3545";
        }
    }

    // ติดตามการเปลี่ยนแปลงในทุกช่องกรอก
    inputs.forEach(input => {
        input.addEventListener('input', calculateGrace);
    });

    // ดักฟังการคลิกปุ่ม Yes/No เพื่ออัปเดตคะแนนทันที
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('check-btn')) {
            setTimeout(calculateGrace, 100); 
        }
    });

    calculateGrace();
});

document.querySelectorAll('[data-toggle="cpr"]').forEach(btn => {
    btn.addEventListener('click', function () {

        const value = this.value;
        const input = document.getElementById('cpr_detail_input');

        // reset active
        document.querySelectorAll('[data-toggle="cpr"]').forEach(b => {
            b.classList.remove('active');
        });

        // set active
        this.classList.add('active');

        // show / hide input
        if (value === 'Yes') {
            input.style.display = 'block';
            input.focus();
        } else {
            input.style.display = 'none';
            input.value = '';
        }

        // ถ้าต้องการส่งค่า CPR ไป backend
        let hidden = document.getElementById('cpr_value');
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'cpr';
            hidden.id = 'cpr_value';
            document.querySelector('.btn-toggle-responsive').appendChild(hidden);
        }
        hidden.value = value;
    });
});
// ปรับปรุงส่วนการคลิกปุ่ม Yes/No ให้คะแนนอัปเดตทันที
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('check-btn')) {
        // ให้รอ JavaScript ตัวอื่นอัปเดตค่าลง Hidden Input ก่อนครู่หนึ่ง
        setTimeout(() => {
            if (typeof calculateGraceScore === 'function') {
                calculateGraceScore();
            }
        }, 50); 
    }
});
// ฟังก์ชันคำนวณ GRACE Score เพียงหนึ่งเดียว
function updateAllClinicalScores() {
    // 1. คำนวณ eGFR ก่อน
    calculateEGFR(); 
    // 2. นำค่าต่างๆ มาคำนวณ GRACE
    calculateGrace(); 
}

// ผูกเหตุการณ์กับทุกปุ่มที่มีคลาส check-btn
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('check-btn')) {
        setTimeout(updateAllClinicalScores, 100); 
    }
});
function calculateEGFR() {
    const scr = parseFloat(document.getElementById('creatinine').value);
    const age = <?= $patient_age ?>;
    const isFemale = <?= ($pt_info['gender'] ?? 'Male') === 'Female' ? 'true' : 'false' ?>;

    if (scr > 0) {
        let kappa = isFemale ? 0.7 : 0.9;
        let alpha = isFemale ? -0.241 : -0.411;
        let egfr = 142 * Math.pow(Math.min(scr / kappa, 1), alpha) * Math.pow(Math.max(scr / kappa, 1), -1.2) * Math.pow(0.9938, age) * (isFemale ? 1.012 : 1.0);
        
        document.getElementById('gfr').value = Math.round(egfr);
    }
}
function selectDing(type) {
    const patientId = "<?= $patient_id ?>"; 
    if (type === 'acs') {
        window.location.href = 'ding_acs.php?id=' + patientId;
    } else {
        window.location.href = 'ding_stemi.php?id=' + patientId;
    }
}
function hideStemiOptions() {
    // ล้างค่า Checkbox ของ STEMI Sub
    document.querySelectorAll('input[name="stemi_sub[]"]').forEach(cb => cb.checked = false);
    // ล้างค่า Interpretation
    document.getElementById('ekg_interpretation').value = document.querySelector('input[name="initial_diagnosis_main"]:checked').value;
}

// ✅ เพิ่มฟังก์ชันนี้ใน <script> ของ Symptoms_diagnosis.php
function calculateIschemicTime() {
    const onsetDate = document.getElementById('onset_date_input').value;
    const onsetTime = document.getElementById('onset_time_input').value;
    const diagDate = document.getElementById('diag_ekg_date').value;
    const diagTime = document.getElementById('diag_ekg_time').value;

    if (onsetDate && onsetTime && diagDate && diagTime) {
        const start = new Date(`${onsetDate}T${onsetTime}`);
        const end = new Date(`${diagDate}T${diagTime}`);
        const diffHours = (end - start) / (1000 * 60 * 60);

        const display = document.getElementById('ischemic_time_display');
        if (diffHours >= 0) {
            display.innerText = diffHours.toFixed(1) + " ชั่วโมง";
            display.style.color = (diffHours > 12) ? '#dc3545' : '#198754';
        }
    }
}
// เรียกใช้เมื่อมีการเปลี่ยนค่าเวลา
['onset_date_input', 'onset_time_input', 'diag_ekg_date', 'diag_ekg_time'].forEach(id => {
    document.getElementById(id)?.addEventListener('change', calculateIschemicTime);
});
document.addEventListener('DOMContentLoaded', function() {
    // 1. ดึงค่า Patient ID จาก PHP มาเช็คใน JS
    const patientId = "<?= $patient_id ?>";

    // 2. ถ้ามี ID อยู่ (แสดงว่ากำลังทำเคส) ให้ล็อกแท็บเมนู
    if (patientId !== "") {
        const navLinks = document.querySelectorAll('.nav-tabs .nav-link');
        
        navLinks.forEach(link => {
            // ยกเว้นแท็บที่ 'active' อยู่ปัจจุบัน (เพื่อให้รู้ว่าอยู่หน้าไหน)
            if (!link.classList.contains('active')) {
                // ทำให้คลิกไม่ได้
                link.style.pointerEvents = 'none'; 
                link.style.opacity = '0.6'; // ทำให้ดูจางลงว่ากดไม่ได้
                link.setAttribute('tabindex', '-1');
                
                // เพิ่มการแจ้งเตือนเมื่อพยายามจะคลิก (Optional)
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    alert('กรุณาใช้ปุ่ม SAVE & NEXT เพื่อป้องกันรหัสผู้ป่วยหลุดจากระบบ');
                });
            }
        });
    }
});
//
function toggleOnsetInputs(checkbox) {
    const dateInput = document.getElementById('onset_date_input');
    const timeInput = document.getElementById('onset_time_input');
    const isUnknown = checkbox.checked;

    dateInput.disabled = isUnknown;
    timeInput.disabled = isUnknown;

    // Optional: เคลียร์ค่าเมื่อเลือก Not known
    if(isUnknown) {
        dateInput.value = '';
        timeInput.value = '';
    }
}
//
function handleFMCChange(value) {
    // อัปเดตค่าใน Hidden Input
    document.getElementById('fmc_input').value = value;

    // รายชื่อ ID ของส่วนข้อมูลทั้งหมด
    const infoSections = ['emsInfo', 'ipdInfo', 'opdInfo'];

    // ปิดการแสดงผลทั้งหมดก่อน
    infoSections.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });

    // เปิดเฉพาะส่วนที่ตรงกับค่าที่เลือก
    if (value === 'EMS') {
        document.getElementById('emsInfo').style.display = 'block';
    } else if (value === 'IPD') {
        document.getElementById('ipdInfo').style.display = 'block';
    } else if (value === 'OPD') {
        document.getElementById('opdInfo').style.display = 'block';
    }
}
function toggleNotKnown(isUnknownClicked) {
    const unknownCheckbox = document.getElementById('not_known');
    const wallCheckboxes = document.querySelectorAll('.infarction-check');

    if (isUnknownClicked && unknownCheckbox.checked) {
        // ถ้าเลือก Not known -> ยกเลิก Wall อื่นทั้งหมด
        wallCheckboxes.forEach(cb => cb.checked = false);
    } else if (!isUnknownClicked) {
        // ถ้าเลือก Wall ใดๆ -> ยกเลิก Not known
        unknownCheckbox.checked = false;
    }
}
function toggleStemiPanel(show) {
    const stemiPanel = document.getElementById('stemiSubOptionsPanel');
    const placeholder = document.getElementById('noStemiPlaceholder');

    if (show) {
        stemiPanel.style.display = 'block';
        placeholder.style.display = 'none';
        placeholder.classList.remove('d-flex'); // เอา Flex ออกเพื่อซ่อน
    } else {
        stemiPanel.style.display = 'none';
        placeholder.style.display = 'flex'; // กลับมาแสดง Flex
        
        // Optional: เคลียร์ Checkbox เมื่อเปลี่ยนไปเลือกโรคอื่น
        const checkboxes = stemiPanel.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => cb.checked = false);
    }
}
</script>
</body>
</html>