<?php
session_start();
require 'connect.php'; // ไฟล์เชื่อมต่อฐานข้อมูล

// ตรวจสอบ Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$current_user = $_SESSION['user'];
$patient_id = $_GET['id'] ?? $_POST['patient_id'] ?? '';

// =================================================================================
// 1. DATA PRE-FETCHING & LOGIC
// =================================================================================

// 1.1 ดึงข้อมูล Discharge เดิม (ถ้ามี)
$stmt_dis = $pdo->prepare("SELECT * FROM patient_discharges WHERE patient_id = ?");
$stmt_dis->execute([$patient_id]);
$dis_data = $stmt_dis->fetch(PDO::FETCH_ASSOC) ?: [];

// 1.2 ดึงข้อมูลการตายจากตารางอื่น (ถ้ามีการลงข้อมูลไว้แล้ว)
// เพื่อให้ข้อมูลตรงกัน (Consistency)
// ... (Logic การดึงข้อมูลอื่นๆ ถ้ามี) ...

// =================================================================================
// 2. VARIABLE INITIALIZATION
// =================================================================================

// กำหนดตัวแปรทั้งหมดและค่า Default
$fields = [
    'discharge_ward', 'discharge_date', 'discharge_time', 'hospital_cost',
    'admit_date', 'admit_time', 
    'ds_against_reason', 'ds_dead_cause', 
    'final_dx', 'final_dx_other', 'mi_type', 'icd', 'icd_other',
    'fup1_date', 'fup1_detail', 'fup2_date', 'fup2_detail', 'fup3_date', 'fup3_detail',
    'dis_notes',
    // เพิ่มฟิลด์ Refer ที่เดิมไม่มีใน SQL
    'ds_refer1_hosp', 'ds_refer1_reason', 
    'ds_refer2_hosp', 'ds_refer2_reason', 
    'ds_referback_hosp'
];

foreach ($fields as $f) {
    $$f = $_POST[$f] ?? $dis_data[$f] ?? '';
}
// ส่วนบนของไฟล์ discharge.php หลังจากการดึงข้อมูล $dis_data
// ตรวจสอบว่ามีข้อมูลการตายจากหน้าอื่นหรือไม่ เพื่อเชื่อมข้อมูลให้ตรงกัน
$stmt_check_death = $pdo->prepare("SELECT dis_status, ds_dead_cause, death_cause_list FROM patient_discharges WHERE patient_id = ?");
$stmt_check_death->execute([$patient_id]);
$death_info = $stmt_check_death->fetch();

if ($death_info && $death_info['dis_status'] === 'Dead') {
    $dis_status = ['Dead']; // บังคับให้สถานะเป็น Dead อัตโนมัติ
    $ds_dead_cause = $death_info['ds_dead_cause'];
}
// Arrays handling (Checkbox)
$dis_status_str = $_POST['dis_status'] ?? $dis_data['dis_status'] ?? '';
$dis_status = is_array($dis_status_str) ? $dis_status_str : (empty($dis_status_str) ? [] : explode(',', $dis_status_str));

$death_cause_str = $_POST['death_cause'] ?? $dis_data['death_cause_list'] ?? '';
$death_cause = is_array($death_cause_str) ? $death_cause_str : (empty($death_cause_str) ? [] : explode(',', $death_cause_str));

// --- SMART FILL: ADMIT DATE ---
// ถ้าวัน Admit ยังว่าง ให้ไปดึงจาก Consult หรือ ER Diagnosis
if (empty($admit_date)) {
    // 1. จาก Consult
    $stmt_con = $pdo->prepare("SELECT admit_date, admit_time FROM patient_consults WHERE patient_id = ?");
    $stmt_con->execute([$patient_id]);
    $con_row = $stmt_con->fetch(PDO::FETCH_ASSOC);
    
    if ($con_row && !empty($con_row['admit_date'])) {
        $admit_date = $con_row['admit_date'];
        $admit_time = $con_row['admit_time'];
    } else {
        // 2. จาก ER Diagnosis
        $stmt_er = $pdo->prepare("SELECT hospital_date_hatyai, hospital_time_hatyai FROM symptoms_diagnosis WHERE patient_id = ?");
        $stmt_er->execute([$patient_id]);
        $er_row = $stmt_er->fetch(PDO::FETCH_ASSOC);
        if ($er_row) {
            $admit_date = $er_row['hospital_date_hatyai'];
            $admit_time = $er_row['hospital_time_hatyai'];
        }
    }
}

// =================================================================================
// 3. SAVE LOGIC
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($patient_id)) {
        echo "<script>alert('Error: ไม่พบ Patient ID'); window.location.href = 'discharge.php';</script>";
        exit;
    }
    
    $direction = $_POST['direction'] ?? 'next';

    try {
        // คำนวณ Length of Stay (Server Side)
        $length_days = 0;
        $length_hours = 0;
        if ($admit_date && $discharge_date) {
            $start = new DateTime("$admit_date " . ($admit_time ?: '00:00'));
            $end   = new DateTime("$discharge_date " . ($discharge_time ?: '00:00'));
            if ($end >= $start) {
                $diff = $end->diff($start);
                $length_days = $diff->days;
                $length_hours = $diff->h;
            }
        }

        // เตรียมข้อมูล Array เป็น String
        $dis_status_save = isset($_POST['dis_status']) ? implode(',', $_POST['dis_status']) : '';
        $death_cause_save = isset($_POST['death_cause']) ? implode(',', $_POST['death_cause']) : '';

        // SQL (เพิ่มคอลัมน์ Refer ที่หายไปให้ครบ)
        $sql = "REPLACE INTO patient_discharges (
            patient_id, discharge_ward, discharge_date, discharge_time, 
            admit_date, admit_time, hospital_cost, length_days, length_hours,
            dis_status, ds_against_reason, ds_dead_cause, death_cause_list,
            final_diagnosis, final_dx_other, mi_type, icd_code, icd_other,
            fup1_date, fup1_detail, fup2_date, fup2_detail, fup3_date, fup3_detail,
            dis_notes,
            ds_refer1_hosp, ds_refer1_reason, ds_refer2_hosp, ds_refer2_reason, ds_referback_hosp
        ) VALUES (
            ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, 
            ?, 
            ?, ?, ?, ?, ?
        )";

        $params = [
            $patient_id, $discharge_ward, $discharge_date, $discharge_time,
            $admit_date, $admit_time, $hospital_cost, $length_days, $length_hours,
            $dis_status_save, $ds_against_reason, $ds_dead_cause, $death_cause_save,
            $final_dx, $final_dx_other, $mi_type, $icd, $icd_other,
            $fup1_date ?: null, $fup1_detail, $fup2_date ?: null, $fup2_detail, $fup3_date ?: null, $fup3_detail,
            $dis_notes,
            $ds_refer1_hosp, $ds_refer1_reason, $ds_refer2_hosp, $ds_refer2_reason, $ds_referback_hosp
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // --- ส่วนจัดการ Redirect รวมถึงปุ่ม 'back_to_med' ---
        if ($direction === 'back_to_med') {
            // ไปยังหน้ายา (ปรับชื่อไฟล์ได้ตามโครงสร้างจริงของคุณ)
            header("Location: medication_table.php?id=" . $patient_id);
        } elseif ($direction === 'back') {
            header("Location: treatment_results.php?id=" . $patient_id);
        } else {
            // หน้าสุดท้ายแล้ว กลับไปหน้า Dashboard หรือ Index พร้อมสถานะ
            header("Location: index.php?status=saved");
        }
        exit();

    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// Helper
function isChecked($current_val, $check_val) {
    if (is_array($current_val)) return in_array($check_val, $current_val) ? 'checked' : '';
    return ($current_val === $check_val) ? 'checked' : '';
}
$patient_id_query = !empty($patient_id) ? '?id=' . htmlspecialchars($patient_id) : '';
?>

<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Discharge Summary</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<style>
 body { 
    font-family: 'Sarabun', sans-serif;
    background: linear-gradient(180deg, #e3f2fd 0%, #ffffff 100%);
    min-height: 100vh;
    margin: 0;
    background-attachment: fixed;
}
.top-bar { background: #fff; padding: 18px; border-radius: 8px; margin-bottom: 18px; }
.hospital-title { color: #19a974; font-weight: bold; }
.form-section { background: #f6f8f9; padding: 32px; border-radius: 12px; margin-top: 24px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
.section-title { font-weight: bold; margin-top: 16px; margin-bottom: 12px; color: #2c3e50; }

/* Custom Checkbox/Radio */
.btn-check:checked + .btn-outline-primary { background-color: #0d6efd; color: white; }
.btn-check:checked + .btn-outline-success { background-color: #198754; color: white; }
.btn-check:checked + .btn-outline-danger { background-color: #dc3545; color: white; }
.btn-check:checked + .btn-outline-warning { background-color: #ffc107; color: black; }
.btn-check:checked + .btn-outline-info { background-color: #0dcaf0; color: black; }
.btn-check:checked + .btn-outline-secondary { background-color: #6c757d; color: white; }
.btn-check:checked + .btn { border-width: 2px; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transform: translateY(-1px); }

/* Navigation */
.nav-pills .nav-link.active { background-color: #0d6efd; color: white !important; }
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
                <ul class="nav nav-pills nav-fill flex-nowrap overflow-auto pb-1" style="scrollbar-width: none;">
                    <?php 
                    $nav_items = [
                        'patient_form.php' => ['label' => 'ข้อมูลผู้ป่วย', 'icon' => 'bi-person-vcard'],
                        'history_risk_factor.php' => ['label' => 'History & Risk', 'icon' => 'bi-clipboard-pulse'],
                        'Symptoms_diagnosis.php' => ['label' => 'Diagnosis', 'icon' => 'bi-heart-pulse'],
                        'Medication.php' => ['label' => 'Medication', 'icon' => 'bi-capsule'],
                        'cardiac_cath.php' => ['label' => 'Cardiac Cath', 'icon' => 'bi-activity'],
                        'treatment_results.php' => ['label' => 'Result', 'icon' => 'bi-clipboard-check'],
                        'discharge.php' => ['label' => 'Discharge', 'icon' => 'bi-door-open']
                    ];
                    $current_page = basename($_SERVER['PHP_SELF']);
                    foreach ($nav_items as $file => $item) {
                        $active = ($current_page == $file) ? 'active shadow-sm' : 'text-secondary';
                        echo "<li class='nav-item'><a class='nav-link d-flex flex-column align-items-center gap-1 py-2 mx-1 $active' href='$file$patient_id_query'><i class='bi {$item['icon']} fs-5'></i><span class='small fw-bold'>{$item['label']}</span></a></li>";
                    }
                    ?>
                </ul>
            </div>
        </div>

        <form method="post">
            <input type="hidden" name="patient_id" value="<?= htmlspecialchars($patient_id) ?>">

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
                <h6 class="text-primary fw-bold mb-4 border-bottom pb-2"><i class="bi bi-box-arrow-right me-2"></i> Discharge Information</h6>
                
                <div class="p-3 bg-light rounded-3 mb-3 border border-light-subtle">
                    <h6 class="fw-bold small text-secondary mb-2"><i class="bi bi-box-arrow-in-right"></i> Admit Information</h6>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label small text-muted mb-1">วันที่ Admit</label>
                            <input type="date" name="admit_date" id="admit_date" class="form-control" value="<?= htmlspecialchars($admit_date) ?>" onchange="calculateLOS()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">เวลา Admit</label>
                            <input type="time" name="admit_time" id="admit_time" class="form-control" value="<?= htmlspecialchars($admit_time) ?>" onchange="calculateLOS()">
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Ward / หน่วยงาน</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-primary"><i class="bi bi-hospital"></i></span>
                            <input type="text" name="discharge_ward" class="form-control" placeholder="ระบุ Ward..." value="<?= htmlspecialchars($discharge_ward) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">ค่ารักษาพยาบาล</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-success"><i class="bi bi-currency-exchange"></i></span>
                            <input type="number" step="0.01" name="hospital_cost" class="form-control" placeholder="0.00" value="<?= htmlspecialchars($hospital_cost) ?>">
                            <span class="input-group-text bg-light text-muted">THB</span>
                        </div>
                    </div>
                </div>

                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">วันที่ Discharge</label>
                        <input type="date" name="discharge_date" id="discharge_date" class="form-control" value="<?= htmlspecialchars($discharge_date) ?>" onchange="calculateLOS()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small">เวลา</label>
                        <input type="time" name="discharge_time" id="discharge_time" class="form-control" value="<?= htmlspecialchars($discharge_time) ?>" onchange="calculateLOS()">
                    </div>
                    <div class="col-md-7">
                        <div class="p-2 border rounded bg-primary bg-opacity-10 border-primary border-opacity-25">
                            <label class="form-label fw-bold text-primary small mb-1 d-block">Length of Stay (คำนวณอัตโนมัติ)</label>
                            <div class="d-flex gap-2">
                                <div class="input-group input-group-sm">
                                    <input type="text" id="length_days" class="form-control fw-bold text-center text-primary" readonly placeholder="0">
                                    <span class="input-group-text">วัน</span>
                                </div>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="length_hours" class="form-control fw-bold text-center text-primary" readonly placeholder="0">
                                    <span class="input-group-text">ชั่วโมง</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
                <h6 class="text-primary fw-bold mb-4 border-bottom pb-2"><i class="bi bi-person-check-fill me-2"></i> Status & Referral</h6>
                
                <div class="row g-2 mb-4">
                    <div class="col-md-3 col-6">
                        <input class="btn-check" type="checkbox" id="ds_alive" name="dis_status[]" value="Alive" <?= isChecked($dis_status, 'Alive') ?>>
                        <label class="btn btn-outline-success w-100 rounded-pill shadow-sm" for="ds_alive"><i class="bi bi-heart-pulse me-1"></i> Alive</label>
                    </div>
                    <div class="col-md-3 col-6">
                        <input class="btn-check" type="checkbox" id="ds_return" name="dis_status[]" value="หนีกลับ" <?= isChecked($dis_status, 'หนีกลับ') ?>>
                        <label class="btn btn-outline-warning text-dark w-100 rounded-pill shadow-sm" for="ds_return"><i class="bi bi-box-arrow-left me-1"></i> หนีกลับ</label>
                    </div>
                    <div class="col-md-6 col-12">
                        <div class="input-group">
                            <input class="btn-check toggle-section" type="checkbox" id="ds_against" name="dis_status[]" value="Against Advice" <?= isChecked($dis_status, 'Against Advice') ?> data-target="against_reason_box">
                            <label class="btn btn-outline-warning text-dark rounded-start-pill border-end-0 shadow-sm px-3" for="ds_against"><i class="bi bi-exclamation-circle me-1"></i> Against Advice</label>
                            <input type="text" id="against_reason_box" name="ds_against_reason" class="form-control rounded-end-pill shadow-sm" placeholder="ระบุเหตุผล..." value="<?= htmlspecialchars($ds_against_reason) ?>" style="display: <?= isChecked($dis_status, 'Against Advice') ? 'block' : 'none' ?>;">
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded border mb-4">
                    <label class="form-label small fw-bold text-secondary mb-2">การส่งต่อ (Referral)</label>
                    <div class="d-grid gap-2" style="max-width: 600px;">
                        
                        <div class="refer-group">
                            <input type="checkbox" class="btn-check toggle-section" id="ds_refer1" name="dis_status[]" value="Refer (Same/Other hospital)" <?= isChecked($dis_status, 'Refer (Same/Other hospital)') ?> data-target="box_refer1">
                            <label class="btn btn-outline-primary w-100 text-start p-2" for="ds_refer1"><i class="bi bi-hospital me-2"></i> กลับ รพ.เดิม / รพ.อื่น</label>
                            <div id="box_refer1" class="mt-2 ps-3 border-start border-3 border-primary" style="display: <?= isChecked($dis_status, 'Refer (Same/Other hospital)') ? 'block' : 'none' ?>;">
                                <div class="input-group input-group-sm">
                                    <input type="text" name="ds_refer1_hosp" class="form-control" placeholder="ชื่อโรงพยาบาล" value="<?= htmlspecialchars($ds_refer1_hosp) ?>">
                                    <input type="text" name="ds_refer1_reason" class="form-control" placeholder="เหตุผล" value="<?= htmlspecialchars($ds_refer1_reason) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="refer-group">
                            <input type="checkbox" class="btn-check toggle-section" id="ds_refer2" name="dis_status[]" value="Refer (Higher level)" <?= isChecked($dis_status, 'Refer (Higher level)') ?> data-target="box_refer2">
                            <label class="btn btn-outline-danger w-100 text-start p-2" for="ds_refer2"><i class="bi bi-arrow-up-circle me-2"></i> ส่งต่อ รพ.ศักยภาพสูงกว่า</label>
                            <div id="box_refer2" class="mt-2 ps-3 border-start border-3 border-danger" style="display: <?= isChecked($dis_status, 'Refer (Higher level)') ? 'block' : 'none' ?>;">
                                <div class="input-group input-group-sm">
                                    <input type="text" name="ds_refer2_hosp" class="form-control" placeholder="ชื่อโรงพยาบาล" value="<?= htmlspecialchars($ds_refer2_hosp) ?>">
                                    <input type="text" name="ds_refer2_reason" class="form-control" placeholder="เหตุผล" value="<?= htmlspecialchars($ds_refer2_reason) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="refer-group">
                            <input type="checkbox" class="btn-check toggle-section" id="ds_referback" name="dis_status[]" value="Refer Back" <?= isChecked($dis_status, 'Refer Back') ?> data-target="box_referback">
                            <label class="btn btn-outline-success w-100 text-start p-2" for="ds_referback"><i class="bi bi-arrow-return-left me-2"></i> ส่งกลับตามสิทธิ์ (Refer Back)</label>
                            <div id="box_referback" class="mt-2 ps-3 border-start border-3 border-success" style="display: <?= isChecked($dis_status, 'Refer Back') ? 'block' : 'none' ?>;">
                                <input type="text" name="ds_referback_hosp" class="form-control form-control-sm" placeholder="ชื่อโรงพยาบาล..." value="<?= htmlspecialchars($ds_referback_hosp) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <input class="btn-check toggle-section" type="checkbox" id="ds_dead" name="dis_status[]" value="Dead" <?= isChecked($dis_status, 'Dead') ?> data-target="dead_cause_container">
                    <label class="btn btn-outline-danger p-3 shadow-sm fw-bold" for="ds_dead"><i class="bi bi-heartbreak-fill me-2"></i> เสียชีวิต (Dead)</label>
                </div>

                <div id="dead_cause_container" class="p-4 bg-danger bg-opacity-10 border border-danger rounded-3 animate__animated animate__fadeIn" style="display: <?= isChecked($dis_status, 'Dead') ? 'block' : 'none' ?>;">
                    <div class="mb-3">
                        <label class="form-label text-danger small fw-bold">ระบุรายละเอียดสาเหตุ</label>
                        <textarea name="ds_dead_cause" class="form-control" rows="2"><?= htmlspecialchars($ds_dead_cause) ?></textarea>
                    </div>
                    <label class="form-label text-danger small fw-bold border-bottom border-danger pb-1 w-100">Cause of Death (เลือกได้มากกว่า 1)</label>
                    <div class="row g-2">
                        <?php 
                        $causes = [
                            'Pump Failure' => 'Pump Failure (Severe CHF, Shock)',
                            'Arrhythmia' => 'Arrhythmia (VT/VF)',
                            'Mechanical complication' => 'Mechanical complication',
                            'Non-cardiac' => 'Non-cardiac (Sepsis, Renal, etc.)',
                            'SCD' => 'Sudden Cardiac Death (SCD)'
                        ];
                        foreach ($causes as $val => $label) {
                            $chk = isChecked($death_cause, $val);
                            echo "<div class='col-md-6'><div class='form-check'><input class='form-check-input' type='checkbox' name='death_cause[]' value='$val' id='dc_$val' $chk><label class='form-check-label small' for='dc_$val'>$label</label></div></div>";
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
                <h6 class="text-primary fw-bold mb-4 border-bottom pb-2"><i class="bi bi-file-medical-fill me-2"></i> Final Diagnosis</h6>
                <div class="row g-2 mb-3">
                    <?php 
                    $dxs = ['STEMI'=>'danger', 'NSTEMI'=>'warning', 'UA'=>'info', 'Other'=>'secondary'];
                    foreach($dxs as $val => $col) {
                        $cls = ($val=='NSTEMI' || $val=='UA') ? 'text-dark' : '';
                        $act = ($final_dx === $val) ? 'checked' : '';
                        echo "<div class='col-md-3 col-6'>
                                <input type='radio' class='btn-check toggle-section' name='final_dx' id='dx_$val' value='$val' $act data-target='final_dx_other_div' data-value-trigger='Other'>
                                <label class='btn btn-outline-$col $cls w-100 rounded-pill' for='dx_$val'>$val</label>
                              </div>";
                    }
                    ?>
                </div>
                <div id="final_dx_other_div" class="mb-3 animate__animated animate__fadeIn" style="display: <?= ($final_dx === 'Other') ? 'block' : 'none' ?>;">
                    <input type="text" name="final_dx_other" class="form-control bg-light" placeholder="ระบุการวินิจฉัย..." value="<?= htmlspecialchars($final_dx_other) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary">MI Type Classification</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach (['I', 'II', 'III', 'IVa', 'IVb', 'IVc', 'V'] as $t) {
                            $chk = ($mi_type === $t) ? 'checked' : '';
                            echo "<div class='flex-fill'><input type='radio' class='btn-check' name='mi_type' id='mit_$t' value='$t' $chk><label class='btn btn-outline-success w-100 rounded-pill btn-sm' for='mit_$t'>$t</label></div>";
                        } ?>
                    </div>
                </div>
                
                <hr>
                <h6 class="text-primary fw-bold mb-3"><i class="bi bi-bookmarks me-2"></i> ICD-10</h6>
                <div class="row g-2">
                    <div class="col-md-6 d-grid gap-2">
                        <?php 
                        $icds1 = ['I20.0'=>'Unstable angina', 'I21.0'=>'Ant. STEMI', 'I21.1'=>'Inf. STEMI', 'I21.2'=>'Other STEMI', 'I21.3'=>'Unspec STEMI'];
                        foreach($icds1 as $c => $l) echo "<input type='radio' class='btn-check toggle-section' name='icd' id='icd_$c' value='$c' ".isChecked($icd, $c)." data-target='icd_other_div' data-value-trigger='Other'><label class='btn btn-outline-secondary text-start p-2' for='icd_$c'><span class='badge bg-dark me-2'>$c</span> <span class='small'>$l</span></label>";
                        ?>
                    </div>
                    <div class="col-md-6 d-grid gap-2">
                        <?php 
                        $icds2 = ['I21.4'=>'NSTEMI', 'I22.0'=>'Subseq Ant MI', 'I22.1'=>'Subseq Inf MI', 'I22.8'=>'Subseq Other MI'];
                        foreach($icds2 as $c => $l) echo "<input type='radio' class='btn-check toggle-section' name='icd' id='icd_$c' value='$c' ".isChecked($icd, $c)." data-target='icd_other_div' data-value-trigger='Other'><label class='btn btn-outline-secondary text-start p-2' for='icd_$c'><span class='badge bg-dark me-2'>$c</span> <span class='small'>$l</span></label>";
                        ?>
                        <input type="radio" class="btn-check toggle-section" name="icd" id="icd_other" value="Other" <?= isChecked($icd, 'Other') ?> data-target="icd_other_div" data-value-trigger="Other">
                        <label class="btn btn-outline-secondary text-start p-2" for="icd_other"><span class="badge bg-warning text-dark me-2">Other</span> <span class="small">ระบุเอง</span></label>
                        
                        <div id="icd_other_div" class="animate__animated animate__fadeIn" style="display: <?= ($icd === 'Other') ? 'block' : 'none' ?>;">
                            <input type="text" name="icd_other" class="form-control form-control-sm" placeholder="รหัสโรค..." value="<?= htmlspecialchars($icd_other) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
                <h6 class="text-primary fw-bold mb-4 border-bottom pb-2"><i class="bi bi-calendar-range me-2"></i> Follow-up Plan</h6>
                <div class="d-flex flex-column gap-3">
                    <?php for ($i = 1; $i <= 3; $i++): 
                        $d = ${"fup{$i}_date"}; $t = ${"fup{$i}_detail"}; ?>
                        <div class="p-3 border rounded bg-light position-relative">
                            <div class="position-absolute top-0 end-0 p-2 opacity-10 text-primary fw-bold" style="font-size: 3rem; line-height: 0.8;"><?= $i ?></div>
                            <div class="row g-2 position-relative z-1">
                                <div class="col-md-3">
                                    <label class="small fw-bold text-primary">นัดครั้งที่ <?= $i ?></label>
                                    <input type="date" name="fup<?= $i ?>_date" class="form-control" value="<?= htmlspecialchars($d) ?>">
                                </div>
                                <div class="col-md-9">
                                    <label class="small fw-bold text-secondary">รายละเอียด</label>
                                    <textarea name="fup<?= $i ?>_detail" class="form-control" rows="1" placeholder="ระบุ..."><?= htmlspecialchars($t) ?></textarea>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="mt-4">
                    <label class="form-label small fw-bold text-secondary">บันทึกเพิ่มเติม (Note)</label>
                    <textarea name="dis_notes" class="form-control" rows="3"><?= htmlspecialchars($dis_notes) ?></textarea>
                </div>
            </div>
            
            <div class="d-flex justify-content-between mb-2">
                <button type="submit" name="direction" value="back_to_med" class="btn btn-secondary px-4">
                    <i class="bi bi-capsule me-2"></i> ยาที่ได้ขณะ Admit
                </button>
            </div>
            
            <div class="d-flex justify-content-between gap-2 mt-4 d-print-none">
                <button type="submit" name="direction" value="back" class="btn btn-secondary px-4"><i class="bi bi-arrow-left"></i> BACK</button>
                <button type="submit" name="direction" value="next" class="btn btn-success px-5">SAVE & FINISH <i class="bi bi-check-circle-fill"></i></button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Logic: Show/Hide Sections
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('toggle-section')) {
        const targetId = e.target.getAttribute('data-target');
        const triggerValue = e.target.getAttribute('data-value-trigger'); // Optional: Only show if value matches this
        const el = document.getElementById(targetId);
        
        if (el) {
            let shouldShow = e.target.checked;
            if (triggerValue && e.target.value !== triggerValue) {
                // For logic like "Only show when 'Other' is checked"
            }
            // For Radios where we want to HIDE the box if a DIFFERENT radio in the same group is picked:
            if (e.target.type === 'radio' && triggerValue) {
                shouldShow = (e.target.value === triggerValue);
            } 
            
            el.style.display = shouldShow ? 'block' : 'none';
        }
    }
});

// Calculate LOS
function calculateLOS() {
    const admitD = document.getElementById('admit_date').value;
    const admitT = document.getElementById('admit_time').value || '00:00';
    const dischD = document.getElementById('discharge_date').value;
    const dischT = document.getElementById('discharge_time').value || '00:00';

    if (admitD && dischD) {
        const start = new Date(admitD + 'T' + admitT);
        const end = new Date(dischD + 'T' + dischT);
        if (end >= start) {
            const diffMs = end - start;
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
            const diffHrs = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            document.getElementById('length_days').value = diffDays;
            document.getElementById('length_hours').value = diffHrs;
        } else {
            document.getElementById('length_days').value = 'Error';
            document.getElementById('length_hours').value = '';
        }
    }
}
document.addEventListener('DOMContentLoaded', calculateLOS);
</script>
</body>
</html>