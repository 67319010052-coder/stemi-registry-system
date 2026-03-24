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
// 1. DATA FETCHING & LOGIC
// =================================================================================

// 1.1 ดึงข้อมูล Treatment Results เดิม
$stmt = $pdo->prepare("SELECT * FROM treatment_results WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// 1.2 ดึงข้อมูลการตายจาก Discharge (Sync Status)
$stmt_check_dead = $pdo->prepare("SELECT dis_status FROM patient_discharges WHERE patient_id = ?");
$stmt_check_dead->execute([$patient_id]);
$dead_status = $stmt_check_dead->fetchColumn();

// =================================================================================
// 2. VARIABLE INITIALIZATION
// =================================================================================

// กำหนดตัวแปรและค่า Default (POST > DB > Default)
$inhospital_echo = $_POST['inhospital_echo'] ?? $row['inhospital_echo'] ?? '';
$ef_value        = $_POST['ef_value'] ?? $row['ef_value'] ?? '';
$echo_additional = $_POST['echo_additional'] ?? $row['echo_additional'] ?? '';
$inhospital_comp = $_POST['inhospital_comp'] ?? $row['inhospital_comp'] ?? '';

$heart_failure   = $_POST['heart_failure'] ?? $row['heart_failure'] ?? '';
$killip_class    = $_POST['killip_class'] ?? $row['killip_class'] ?? '';
$on_ventilator   = $_POST['ventilator'] ?? $row['on_ventilator'] ?? ''; 

$cardiogenic_shock = $_POST['cardiogenic_shock'] ?? $row['cardiogenic_shock'] ?? '';

$stroke      = $_POST['stroke'] ?? $row['stroke'] ?? '';
$stroke_time = $_POST['stroke_time'] ?? $row['stroke_time'] ?? '';
$stroke_type = $_POST['stroke_type'] ?? $row['stroke_type'] ?? '';

$renal_failure   = $_POST['renal_failure'] ?? $row['renal_failure'] ?? '';
$dialysis_type   = $_POST['dialysis_type'] ?? $row['dialysis_type'] ?? '';
$dialysis_detail = $_POST['dialysis_other_text_input'] ?? $row['dialysis_detail'] ?? '';

// (5) Bleeding
$bleeding          = $_POST['bleeding'] ?? $row['major_bleeding'] ?? ''; 
$blood_transfusion = $_POST['blood_transfusion'] ?? $row['blood_transfusion'] ?? '';

// (6) Arrhythmia
$arrhythmia   = $_POST['arrhythmia'] ?? $row['arrhythmia'] ?? '';
$vtvf         = $_POST['vtvf'] ?? $row['vtvf'] ?? '';
$heart_block  = $_POST['heart_block'] ?? $row['heart_block'] ?? '';
$heart_block_other = $_POST['heart_block_other'] ?? ''; 
$arrhythmia_other  = $_POST['arrhythmia_other'] ?? $row['arrhythmia_detail'] ?? ''; 

// (7) Mechanical
$mechanical_comp = $_POST['mechanical_comp'] ?? $row['mechanical_comp'] ?? '';
$mechanical_type = $_POST['mechanical_type'] ?? $row['mechanical_type'] ?? '';
$mechanical_other_text_input = $_POST['mechanical_other_text_input'] ?? ''; 

// (8) Other & Death
$other_complication = $_POST['other_complication'] ?? $row['other_complication'] ?? '';
$complication_death = $_POST['complication_death'] ?? $row['complication_death'] ?? '';

// Auto-Sync Dead Status
if ($dead_status === 'Dead') {
    $complication_death = 'Yes';
}

// =================================================================================
// 3. SAVE LOGIC
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($patient_id)) {
        echo "<script>alert('Error: ไม่พบ Patient ID'); window.location.href = 'treatment_results.php';</script>";
        exit;
    }
    
    $direction = $_POST['direction'] ?? 'next';

    try {
        // รวมข้อมูล Arrhythmia Detail
        $arr_detail_save = $_POST['arrhythmia_other'] ?? '';
        if (!empty($_POST['heart_block_other'])) {
            $arr_detail_save .= " (HB: " . $_POST['heart_block_other'] . ")";
        }

        $sql = "INSERT INTO treatment_results (
                    patient_id, inhospital_echo, ef_value, echo_additional,
                    inhospital_comp, heart_failure, killip_class, on_ventilator,
                    cardiogenic_shock, stroke, stroke_time, stroke_type,
                    renal_failure, dialysis_type, dialysis_detail,
                    major_bleeding, blood_transfusion, arrhythmia, vtvf,
                    heart_block, arrhythmia_detail, mechanical_comp, mechanical_type,
                    other_complication, complication_death
                ) VALUES (
                    :patient_id, :inhospital_echo, :ef_value, :echo_additional,
                    :inhospital_comp, :heart_failure, :killip_class, :on_ventilator,
                    :cardiogenic_shock, :stroke, :stroke_time, :stroke_type,
                    :renal_failure, :dialysis_type, :dialysis_detail,
                    :major_bleeding, :blood_transfusion, :arrhythmia, :vtvf,
                    :heart_block, :arrhythmia_detail, :mechanical_comp, :mechanical_type,
                    :other_complication, :complication_death
                ) ON DUPLICATE KEY UPDATE
                    inhospital_echo = VALUES(inhospital_echo),
                    ef_value = VALUES(ef_value),
                    echo_additional = VALUES(echo_additional),
                    inhospital_comp = VALUES(inhospital_comp),
                    heart_failure = VALUES(heart_failure),
                    killip_class = VALUES(killip_class),
                    on_ventilator = VALUES(on_ventilator),
                    cardiogenic_shock = VALUES(cardiogenic_shock),
                    stroke = VALUES(stroke),
                    stroke_time = VALUES(stroke_time),
                    stroke_type = VALUES(stroke_type),
                    renal_failure = VALUES(renal_failure),
                    dialysis_type = VALUES(dialysis_type),
                    dialysis_detail = VALUES(dialysis_detail),
                    major_bleeding = VALUES(major_bleeding),
                    blood_transfusion = VALUES(blood_transfusion),
                    arrhythmia = VALUES(arrhythmia),
                    vtvf = VALUES(vtvf),
                    heart_block = VALUES(heart_block),
                    arrhythmia_detail = VALUES(arrhythmia_detail),
                    mechanical_comp = VALUES(mechanical_comp),
                    mechanical_type = VALUES(mechanical_type),
                    other_complication = VALUES(other_complication),
                    complication_death = VALUES(complication_death)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':patient_id' => $patient_id,
            ':inhospital_echo' => $inhospital_echo,
            ':ef_value' => $ef_value,
            ':echo_additional' => $echo_additional,
            ':inhospital_comp' => $inhospital_comp,
            ':heart_failure' => $heart_failure,
            ':killip_class' => $killip_class,
            ':on_ventilator' => $on_ventilator,
            ':cardiogenic_shock' => $cardiogenic_shock,
            ':stroke' => $stroke,
            ':stroke_time' => $stroke_time,
            ':stroke_type' => $stroke_type,
            ':renal_failure' => $renal_failure,
            ':dialysis_type' => $dialysis_type,
            ':dialysis_detail' => $dialysis_detail,
            ':major_bleeding' => $bleeding, 
            ':blood_transfusion' => $blood_transfusion,
            ':arrhythmia' => $arrhythmia,
            ':vtvf' => $vtvf,
            ':heart_block' => $heart_block,
            ':arrhythmia_detail' => $arr_detail_save,
            ':mechanical_comp' => $mechanical_comp,
            ':mechanical_type' => $mechanical_type,
            ':other_complication' => $other_complication,
            ':complication_death' => $complication_death
        ]);

        if ($direction === 'back') {
            header("Location: cardiac_cath.php?id=" . $patient_id);
        } else {
            header("Location: discharge.php?id=" . $patient_id);
        }
        exit();

    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// Helper Functions
function isChecked($dbVal, $checkVal) { return ($dbVal === $checkVal) ? 'checked' : ''; }
function isActive($dbVal, $checkVal) { return ($dbVal === $checkVal) ? 'active' : ''; }
$patient_id_query = !empty($patient_id) ? '?id=' . htmlspecialchars($patient_id) : '';
?>

<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Result of Treatment</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<style>
 body { 
    font-family: 'Sarabun', sans-serif;
    background: linear-gradient(180deg, #e3f2fd 0%, #ffffff 100%);
    min-height: 100vh;
    margin: 0;
    background-repeat: no-repeat;
    background-attachment: fixed;
}
.top-bar { background: #fff; padding: 18px; border-radius: 8px; margin-bottom: 18px; }
.hospital-title { color: #19a974; font-weight: bold; }
.form-section { background: #f6f8f9; padding: 32px; border-radius: 12px; margin-top: 24px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
.section-title { font-weight: bold; margin-top: 16px; margin-bottom: 12px; color: #2c3e50; }
/* Custom Buttons */
.check-btn { border: 1px solid #ccc; background: #fff; padding: 6px 14px; border-radius: 6px; cursor: pointer; transition: 0.2s; }
.check-btn.active { background: #28a745; color: #fff; border-color: #28a745; }
.btn-check:checked + .btn { border-width: 2px; font-weight: bold; transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.15); }
/* Custom Checkbox/Radio */
.btn-check:checked + .btn-outline-success { background-color: #198754; color: white; }
.btn-check:checked + .btn-outline-secondary { background-color: #6c757d; color: white; }
.btn-check:checked + .btn-outline-danger { background-color: #dc3545; color: white; }
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
                <h6 class="text-primary fw-bold mb-3 border-bottom pb-2"><i class="bi bi-search me-2"></i> Inhospital Investigation</h6>
                
                <div class="row align-items-center mb-3">
                    <div class="col-md-4"><strong class="small text-secondary">Inhospital Echo</strong></div>
                    <div class="col-md-8">
                        <div class="d-flex gap-2">
                            <input type="radio" class="btn-check toggle-section" name="inhospital_echo" id="echo_no" value="NO" <?= isChecked($inhospital_echo, 'NO') ?> data-target="echoDetails" data-value="YES"><label class="btn btn-outline-secondary w-100 rounded-pill" for="echo_no">NO</label>
                            <input type="radio" class="btn-check toggle-section" name="inhospital_echo" id="echo_yes" value="YES" <?= isChecked($inhospital_echo, 'YES') ?> data-target="echoDetails" data-value="YES"><label class="btn btn-outline-success w-100 rounded-pill" for="echo_yes">YES</label>
                        </div>
                    </div>
                </div>

                <div id="echoDetails" class="bg-light p-3 rounded border animate__animated animate__fadeIn" style="display: <?= ($inhospital_echo==='YES')?'block':'none' ?>;">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="small fw-bold">LVEF (%)</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" name="ef_value" min="0" max="100" placeholder="Ex. 55" value="<?= htmlspecialchars($ef_value) ?>">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="small fw-bold">Additional Info</label>
                            <input type="text" class="form-control form-control-sm" name="echo_additional" placeholder="Note..." value="<?= htmlspecialchars($echo_additional) ?>">
                        </div>
                    </div>
                </div>
            </div>

           <div class="form-section border rounded p-4 bg-white shadow-sm mb-4">
    <h5 class="section-title border-bottom pb-2 mb-3 text-danger"><i class="bi bi-exclamation-triangle"></i> Inhospital Complications</h5>

    <div class="row align-items-center mb-4 pb-3 border-bottom">
        <div class="col-md-5"><strong class="text-secondary small">Complication Occurred?</strong></div>
        <div class="col-md-7">
            <div class="d-flex gap-2">
                <div class="flex-fill">
                    <input type="radio" class="btn-check" name="inhospital_comp" id="comp_no" value="NO" <?= isChecked($inhospital_comp, 'NO') ?>>
                    <label class="btn btn-outline-secondary w-100 rounded-pill shadow-sm fw-bold" for="comp_no">NO</label>
                </div>
                <div class="flex-fill">
                    <input type="radio" class="btn-check" name="inhospital_comp" id="comp_yes" value="YES" <?= isChecked($inhospital_comp, 'YES') ?>>
                    <label class="btn btn-outline-danger w-100 rounded-pill shadow-sm fw-bold" for="comp_yes">YES</label>
                </div>
            </div>
        </div>
    </div>

    <div class="p-3 border rounded bg-light mb-3">
        <div class="row align-items-center">
            <div class="col-md-5">
                <label class="fw-bold text-dark mb-0"><i class="bi bi-lungs-fill text-primary me-2"></i> (1) Heart Failure</label>
            </div>
            <div class="col-md-7">
                <div class="d-flex gap-2">
                    <div class="flex-fill">
                        <input type="radio" class="btn-check toggle-section" name="heart_failure" id="hf_no" value="No" <?= isChecked($heart_failure, 'No') ?> data-target="hf_details" data-value="Yes">
                        <label class="btn btn-outline-secondary w-100 rounded-pill shadow-sm" for="hf_no">No</label>
                    </div>
                    <div class="flex-fill">
                        <input type="radio" class="btn-check toggle-section" name="heart_failure" id="hf_yes" value="Yes" <?= isChecked($heart_failure, 'Yes') ?> data-target="hf_details" data-value="Yes">
                        <label class="btn btn-outline-danger w-100 rounded-pill shadow-sm" for="hf_yes">Yes</label>
                    </div>
                </div>
            </div>
        </div>
        <div id="hf_details" class="mt-3 ps-3 border-start border-3 border-danger" style="display: <?= ($heart_failure === 'Yes') ? 'block' : 'none' ?>;">
             
             <label class="small fw-bold mb-2 text-secondary">Killip Class:</label>
             <div class="d-flex gap-2 mb-2 flex-wrap">
                 <?php foreach(['I','II','III','IV'] as $k) {
                     echo "<input type='radio' class='btn-check' name='killip_class' id='killip_$k' value='$k' ".isChecked($killip_class, $k).">";
                     echo "<label class='btn btn-outline-primary btn-sm rounded-pill px-4 shadow-sm' for='killip_$k'>$k</label>";
                 } ?>
             </div>
             <label class="small fw-bold mb-1 text-secondary">On Ventilator:</label>
             <div class="d-flex gap-2" style="max-width: 200px;">
                 <input type="radio" class="btn-check" name="ventilator" id="vent_no" value="No" <?= isChecked($on_ventilator, 'No') ?>>
                 <label class="btn btn-outline-secondary btn-sm w-100 rounded-pill shadow-sm" for="vent_no">No</label>
                 
                 <input type="radio" class="btn-check" name="ventilator" id="vent_yes" value="Yes" <?= isChecked($on_ventilator, 'Yes') ?>>
                 <label class="btn btn-outline-danger btn-sm w-100 rounded-pill shadow-sm" for="vent_yes">Yes</label>
             </div>
        </div>
    </div>

    <div class="p-3 border rounded bg-light mb-3">
        <div class="row align-items-center">
            <div class="col-md-5">
                <label class="fw-bold text-dark mb-0"><i class="bi bi-activity text-danger me-2"></i> (2) Cardiogenic Shock</label>
            </div>
            <div class="col-md-7">
                <div class="d-flex gap-2">
                    <div class="flex-fill">
                        <input type="radio" class="btn-check" name="cardiogenic_shock" id="cs_no" value="No" <?= isChecked($cardiogenic_shock, 'No') ?>>
                        <label class="btn btn-outline-secondary w-100 rounded-pill shadow-sm" for="cs_no">No</label>
                    </div>
                    <div class="flex-fill">
                        <input type="radio" class="btn-check" name="cardiogenic_shock" id="cs_yes" value="Yes" <?= isChecked($cardiogenic_shock, 'Yes') ?>>
                        <label class="btn btn-outline-danger w-100 rounded-pill shadow-sm" for="cs_yes">Yes</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="p-3 border rounded bg-light mb-3">
        <div class="row align-items-center">
            <div class="col-md-5">
                <label class="fw-bold text-dark mb-0"><i class="bi bi-person-exclamation text-warning me-2 text-dark"></i> (3) Stroke</label>
            </div>
            <div class="col-md-7">
                <div class="d-flex gap-2">
                    <div class="flex-fill">
                        <input type="radio" class="btn-check toggle-section" name="stroke" id="st_no" value="No" <?= isChecked($stroke, 'No') ?> data-target="st_details" data-value="Yes">
                        <label class="btn btn-outline-secondary w-100 rounded-pill shadow-sm" for="st_no">No</label>
                    </div>
                    <div class="flex-fill">
                        <input type="radio" class="btn-check toggle-section" name="stroke" id="st_yes" value="Yes" <?= isChecked($stroke, 'Yes') ?> data-target="st_details" data-value="Yes">
                        <label class="btn btn-outline-danger w-100 rounded-pill shadow-sm" for="st_yes">Yes</label>
                    </div>
                </div>
            </div>
        </div>
        <div id="st_details" class="mt-3 ps-3 border-start border-3 border-warning" style="display: <?= ($stroke === 'Yes') ? 'block' : 'none' ?>;">
            <div class="mb-2">
                <label class="small fw-bold text-muted">Timing:</label>
                <div class="d-flex gap-2 flex-wrap">
                    <?php foreach(['Before PCI','During PCI','After PCI'] as $t) {
                        $tid = 'stm_'.str_replace(' ','',$t);
                        echo "<input type='radio' class='btn-check' name='stroke_time' id='$tid' value='$t' ".isChecked($stroke_time, $t).">";
                        echo "<label class='btn btn-outline-dark btn-sm rounded-pill px-3 shadow-sm' for='$tid'>$t</label>";
                    } ?>
                </div>
            </div>
            <div>
                <label class="small fw-bold text-muted">Type:</label>
                <div class="d-flex gap-2 flex-wrap">
                    <?php foreach(['Ischemic','Hemorrhage','Unknown'] as $t) {
                        $tid = 'stp_'.$t;
                        echo "<input type='radio' class='btn-check' name='stroke_type' id='$tid' value='$t' ".isChecked($stroke_type, $t).">";
                        echo "<label class='btn btn-outline-dark btn-sm rounded-pill px-3 shadow-sm' for='$tid'>$t</label>";
                    } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="p-3 border rounded bg-light mb-3">
        <div class="row align-items-center">
            <div class="col-md-5">
                <label class="fw-bold text-dark mb-0"><i class="bi bi-droplet-half text-primary me-2"></i> (4) Renal Failure</label>
            </div>
            <div class="col-md-7">
                <div class="d-flex gap-2">
                    <div class="flex-fill">
                        <input type="radio" class="btn-check toggle-section" name="renal_failure" id="rf_no" value="No" <?= isChecked($renal_failure, 'No') ?> data-target="rf_details" data-value="Yes">
                        <label class="btn btn-outline-secondary w-100 rounded-pill shadow-sm" for="rf_no">No</label>
                    </div>
                    <div class="flex-fill">
                        <input type="radio" class="btn-check toggle-section" name="renal_failure" id="rf_yes" value="Yes" <?= isChecked($renal_failure, 'Yes') ?> data-target="rf_details" data-value="Yes">
                        <label class="btn btn-outline-danger w-100 rounded-pill shadow-sm" for="rf_yes">Yes</label>
                    </div>
                </div>
            </div>
        </div>
        <div id="rf_details" class="mt-3 ps-3 border-start border-3 border-primary" style="display: <?= ($renal_failure === 'Yes') ? 'block' : 'none' ?>;">
            <label class="small fw-bold mb-2 text-secondary">Dialysis:</label>
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach(['Hemodialysis', 'Peritoneal', 'Other'] as $d) {
                    $did = 'dt_'.substr($d,0,3);
                    echo "<input type='radio' class='btn-check' name='dialysis_type' id='$did' value='$d' ".isChecked($dialysis_type, $d)." onchange=\"toggleOther('dt_other_box', this.value==='Other')\">";
                    echo "<label class='btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm' for='$did'>$d</label>";
                } ?>
            </div>
            <input type="text" id="dt_other_box" name="dialysis_other_text_input" class="form-control form-control-sm mt-2 shadow-sm" placeholder="ระบุ..." value="<?= htmlspecialchars($dialysis_detail ?? '') ?>" style="display: <?= ($dialysis_type === 'Other') ? 'block' : 'none' ?>;">
        </div>
    </div>

    <div class="p-3 border rounded bg-light mb-3">
        <div class="row align-items-center">
            <div class="col-md-5">
                <label class="fw-bold text-dark mb-0"><i class="bi bi-droplet-fill text-danger me-2"></i> (5) Major/Minor Bleeding</label>
            </div>
            <div class="col-md-7">
                <div class="d-flex gap-2">
                    <div class="flex-fill">
                        <input type="radio" class="btn-check" name="bleeding" id="bleeding_no" value="No" <?= isChecked($bleeding, 'No') ?> onchange="toggleDetail('blood_transfusion_section', false)">
                        <label class="btn btn-outline-secondary w-100 rounded-pill shadow-sm" for="bleeding_no">No</label>
                    </div>
                    <div class="flex-fill">
                        <input type="radio" class="btn-check" name="bleeding" id="bleeding_yes" value="Yes" <?= isChecked($bleeding, 'Yes') ?> onchange="toggleDetail('blood_transfusion_section', true)">
                        <label class="btn btn-outline-success w-100 rounded-pill shadow-sm" for="bleeding_yes">Yes</label>
                    </div>
                </div>
            </div>
        </div>
        <div id="blood_transfusion_section" class="mt-3 ps-3 border-start border-3 border-secondary" style="display: <?= ($bleeding ?? 'No') === 'Yes' ? 'block' : 'none' ?>;">
            
            <label class="small fw-bold text-secondary mb-2">Blood Transfusion Required?</label>
            <div class="d-flex gap-2" style="max-width: 200px;">
                <input type="radio" class="btn-check" name="blood_transfusion" id="bt_no" value="No" <?= isChecked($blood_transfusion, 'No') ?>>
                <label class="btn btn-outline-secondary btn-sm w-100 rounded-pill shadow-sm" for="bt_no">No</label>
                
                <input type="radio" class="btn-check" name="blood_transfusion" id="bt_yes" value="Yes" <?= isChecked($blood_transfusion, 'Yes') ?>>
                <label class="btn btn-outline-danger btn-sm w-100 rounded-pill shadow-sm" for="bt_yes">Yes</label>
            </div>
        </div>
    </div>

    <div class="p-3 border rounded bg-light mb-3">
        <div class="row align-items-center">
            <div class="col-md-5">
                <label class="fw-bold text-dark mb-0"><i class="bi bi-heart-pulse text-warning text-dark me-2"></i> (6) Arrhythmia</label>
            </div>
            <div class="col-md-7">
                <div class="d-flex gap-2">
                    <div class="flex-fill">
                        <input type="radio" class="btn-check" name="arrhythmia" id="arr_no" value="No" <?= isChecked($arrhythmia, 'No') ?> onchange="toggleDetail('arrhythmia_details', false)">
                        <label class="btn btn-outline-secondary w-100 rounded-pill shadow-sm" for="arr_no">No</label>
                    </div>
                    <div class="flex-fill">
                        <input type="radio" class="btn-check" name="arrhythmia" id="arr_yes" value="Yes" <?= isChecked($arrhythmia, 'Yes') ?> onchange="toggleDetail('arrhythmia_details', true)">
                        <label class="btn btn-outline-success w-100 rounded-pill shadow-sm" for="arr_yes">Yes</label>
                    </div>
                </div>
            </div>
        </div>
        <div id="arrhythmia_details" class="mt-3 ps-3 border-start border-3 border-warning" style="display: <?= isChecked($arrhythmia, 'Yes') ? 'block' : 'none' ?>;">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="small fw-bold text-secondary mb-1">VT/VF:</label>
                    <div class="d-flex gap-2">
                        <input type="radio" class="btn-check" name="vtvf" id="vtvf_no" value="No" <?= isChecked($vtvf, 'No') ?>>
                        <label class="btn btn-outline-secondary btn-sm w-100 rounded-pill shadow-sm" for="vtvf_no">No</label>
                        <input type="radio" class="btn-check" name="vtvf" id="vtvf_yes" value="Yes" <?= isChecked($vtvf, 'Yes') ?>>
                        <label class="btn btn-outline-success btn-sm w-100 rounded-pill shadow-sm" for="vtvf_yes">Yes</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="small fw-bold text-secondary mb-1">Heart Block:</label>
                    <div class="d-flex gap-2">
                        <input type="radio" class="btn-check" name="heart_block" id="heart_block_no" value="No" <?= isChecked($heart_block, 'No') ?> onchange="toggleHeartBlockOther(false)">
                        <label class="btn btn-outline-secondary btn-sm w-100 rounded-pill shadow-sm" for="heart_block_no">No</label>
                        <input type="radio" class="btn-check" name="heart_block" id="heart_block_yes" value="Yes" <?= isChecked($heart_block, 'Yes') ?> onchange="toggleHeartBlockOther(true)">
                        <label class="btn btn-outline-success btn-sm w-100 rounded-pill shadow-sm" for="heart_block_yes">Yes</label>
                    </div>
                </div>
            </div>
            <div id="heart_block_other_div" class="mt-2" style="display: <?= ($heart_block ?? '') === 'Yes' ? 'block' : 'none' ?>;">
                <input type="text" name="heart_block_other" class="form-control form-control-sm shadow-sm" placeholder="ระบุชนิด Heart Block..." value="<?= htmlspecialchars($heart_block_other ?? '') ?>">
            </div>
            <div class="mt-3">
                <label class="small fw-bold text-secondary mb-1">Arrhythmia อื่นๆ:</label>
                <input type="text" name="arrhythmia_other" class="form-control form-control-sm shadow-sm" placeholder="ระบุ..." value="<?= htmlspecialchars($arrhythmia_other ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="p-3 border rounded bg-light mb-3">
        <div class="row align-items-center">
            <div class="col-md-5">
                <label class="fw-bold text-dark mb-0"><i class="bi bi-gear-wide-connected text-dark me-2"></i> (7) Mechanical Comp.</label>
            </div>
            <div class="col-md-7">
                <div class="d-flex gap-2">
                    <div class="flex-fill">
                        <input type="radio" class="btn-check toggle-section" name="mechanical_comp" id="mc_no" value="No" <?= isChecked($mechanical_comp, 'No') ?> data-target="mc_details" data-value="Yes">
                        <label class="btn btn-outline-secondary w-100 rounded-pill shadow-sm" for="mc_no">No</label>
                    </div>
                    <div class="flex-fill">
                        <input type="radio" class="btn-check toggle-section" name="mechanical_comp" id="mc_yes" value="Yes" <?= isChecked($mechanical_comp, 'Yes') ?> data-target="mc_details" data-value="Yes">
                        <label class="btn btn-outline-danger w-100 rounded-pill shadow-sm" for="mc_yes">Yes</label>
                    </div>
                </div>
            </div>
        </div>
        <div id="mc_details" class="mt-3 ps-3 border-start border-3 border-danger" style="display: <?= ($mechanical_comp === 'Yes') ? 'block' : 'none' ?>;">
            
            <label class="small fw-bold mb-2">Type:</label>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach(['VSR', 'Rapture Free Wall', 'Severe MR', 'Stent Thrombosis', 'Other'] as $m) {
                    $mid = 'mt_'.substr($m,0,3);
                    echo "<input type='radio' class='btn-check' name='mechanical_type' id='$mid' value='$m' ".isChecked($mechanical_type, $m)." onchange=\"toggleOther('mc_other_box', this.value==='Other')\">";
                    echo "<label class='btn btn-outline-dark btn-sm rounded-pill px-3 shadow-sm' for='$mid'>$m</label>";
                } ?>
            </div>
            <input type="text" id="mc_other_box" name="mechanical_other_text_input" class="form-control form-control-sm mt-2 shadow-sm" placeholder="ระบุ..." value="<?= htmlspecialchars($mechanical_other_text_input ?? '') ?>" style="display: <?= ($mechanical_type === 'Other') ? 'block' : 'none' ?>;">
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <label class="form-label fw-bold">(8) ภาวะแทรกซ้อนอื่นๆ</label>
            <textarea name="other_complication" class="form-control shadow-sm" rows="2"><?= htmlspecialchars($other_complication ?? '') ?></textarea>
        </div>
    </div>
</div>

            <div class="d-flex justify-content-between gap-2 mt-4 d-print-none">
                <button type="submit" name="direction" value="back" class="btn btn-secondary px-4"><i class="bi bi-arrow-left"></i> BACK</button>
                <button type="submit" name="direction" value="next" class="btn btn-success px-5">SAVE & NEXT <i class="bi bi-arrow-right"></i></button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// --- GLOBAL HELPER FUNCTIONS ---
function setHidden(id, val, btn) {
    document.getElementById(id).value = val;
    btn.parentElement.querySelectorAll('.check-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function toggleOther(elementId, show) {
    const el = document.getElementById(elementId);
    if(el) el.style.display = show ? 'block' : 'none';
    if(!show && el) el.value = '';
}

// Logic: Show/Hide Sections based on Radio
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('toggle-section')) {
        const targetId = e.target.getAttribute('data-target');
        const triggerValue = e.target.getAttribute('data-value'); 
        const el = document.getElementById(targetId);
        
        if (el) {
            const groupName = e.target.name;
            const checkedRadio = document.querySelector(`input[name="${groupName}"]:checked`);
            
            if (checkedRadio && checkedRadio.value === triggerValue) {
                el.style.display = 'block';
            } else {
                el.style.display = 'none';
                el.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach(c => c.checked = false);
                el.querySelectorAll('input[type="text"]').forEach(t => t.value = '');
            }
        }
    }
});

// Specific Toggles for Bleeding and Arrhythmia
function toggleDetail(id, show) {
    const el = document.getElementById(id);
    if(el) el.style.display = show ? 'block' : 'none';
}

function toggleHeartBlockOther(show) {
    const div = document.getElementById('heart_block_other_div');
    if(div) div.style.display = show ? 'block' : 'none';
}
</script>
</body>
</html>