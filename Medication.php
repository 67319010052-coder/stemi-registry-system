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
// 1. DATA FETCHING
// =================================================================================

// 1.1 ดึงข้อมูลเวลาอ้างอิง
$stmt_ref = $pdo->prepare("SELECT onset_time, ems_time, hospital_time_rpth, diag_ekg_time FROM symptoms_diagnosis WHERE patient_id = ?");
$stmt_ref->execute([$patient_id]);
$ref_data = $stmt_ref->fetch(PDO::FETCH_ASSOC) ?: [];

$onset_time         = $ref_data['onset_time'] ?? '';
$fmc_time           = $ref_data['ems_time'] ?? '';
$hospital_time_rpth = $ref_data['hospital_time_rpth'] ?? '';
$diag_ekg_time      = $ref_data['diag_ekg_time'] ?? '';

// 1.2 ดึงข้อมูลยาเดิม
$stmt_med = $pdo->prepare("SELECT * FROM patient_medications WHERE patient_id = ?");
$stmt_med->execute([$patient_id]);
$med_data = $stmt_med->fetch(PDO::FETCH_ASSOC);

// =================================================================================
// 2. VARIABLE INITIALIZATION
// =================================================================================

// Handle POST or Default Data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asa = $_POST['asa'] ?? [];
    $p2y12 = $_POST['p2y12'] ?? [];
    $fibrinolytic = $_POST['fibrinolytic'] ?? [];
    $reason = $_POST['reason'] ?? [];
    $subreason = $_POST['subreason'] ?? [];
    $complication = $_POST['complication'] ?? [];
    $refer_out = $_POST['refer_out'] ?? [];
} elseif ($med_data) {
    $asa = explode(',', $med_data['asa'] ?? '');
    $p2y12 = explode(',', $med_data['p2y12'] ?? '');
    $fibrinolytic = explode(',', $med_data['fibrinolytic_type'] ?? '');
    $complication = explode(',', $med_data['complications'] ?? '');
    $refer_out = explode(',', $med_data['refer_out'] ?? '');
    $reason = []; 
    $subreason = [];
} else {
    $asa = []; $p2y12 = []; $fibrinolytic = []; $reason = []; $subreason = []; $complication = []; $refer_out = [];
}

// Ensure Arrays
$asa = is_array($asa) ? $asa : [];
$p2y12 = is_array($p2y12) ? $p2y12 : [];
$fibrinolytic = is_array($fibrinolytic) ? $fibrinolytic : [];
$complication = is_array($complication) ? $complication : [];
$refer_out = is_array($refer_out) ? $refer_out : [];

// Single Values
$asa_reason = $_POST['asa_reason'] ?? $med_data['asa_reason'] ?? '';
$p2y12_reason = $_POST['p2y12_reason'] ?? $med_data['p2y12_reason'] ?? '';

// Complication Details
$comp_detail_arr = isset($_POST['comp_detail']) ? $_POST['comp_detail'] : []; 

// Refer Details (JSON)
$refer_data_db = !empty($med_data['refer_detail']) ? json_decode($med_data['refer_detail'], true) : [];
$ref1_hosp = $_POST['refer_hospital_1'] ?? $refer_data_db['ref1']['hospital'] ?? '';
$ref1_prov = $_POST['refer_province_1'] ?? $refer_data_db['ref1']['province'] ?? '';
$ref1_date = $_POST['refer_date_1'] ?? $refer_data_db['ref1']['date'] ?? '';
$ref1_time = $_POST['refer_time_1'] ?? $refer_data_db['ref1']['time'] ?? '';

$ref2_hosp = $_POST['refer_hospital_2'] ?? $refer_data_db['ref2']['hospital'] ?? '';
$ref2_prov = $_POST['refer_province_2'] ?? $refer_data_db['ref2']['province'] ?? '';
$ref2_date = $_POST['refer_date_2'] ?? $refer_data_db['ref2']['date'] ?? '';
$ref2_time = $_POST['refer_time_2'] ?? $refer_data_db['ref2']['time'] ?? '';

// =================================================================================
// 3. SAVE LOGIC
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($patient_id)) {
        echo "<script>alert('Error: ไม่พบ Patient ID'); window.location.href = 'Medication.php';</script>";
        exit;
    }
    
    $direction = $_POST['direction'] ?? 'next';

    try {
        // Prepare Data Strings
        $asa_val = isset($_POST['asa']) ? implode(',', $_POST['asa']) : '';
        $p2y12_val = isset($_POST['p2y12']) ? implode(',', $_POST['p2y12']) : '';
        $fibrin_val = isset($_POST['fibrinolytic']) ? implode(',', $_POST['fibrinolytic']) : '';
        $sk_opened_val = isset($_POST['sk_opened']) ? implode(',', $_POST['sk_opened']) : '';
        $hatyai_place_val = isset($_POST['hatyai_place']) ? implode(',', $_POST['hatyai_place']) : '';
        $refer_out_val = isset($_POST['refer_out']) ? implode(',', $_POST['refer_out']) : '';
        $complication_val = isset($_POST['complication']) ? implode(',', $_POST['complication']) : '';

        // Complication Details
        $comp_details_list = [];
        if(!empty($_POST['arrhythmia_detail'])) $comp_details_list[] = "Arrhythmia: ".$_POST['arrhythmia_detail'];
        if(!empty($_POST['bleeding_detail'])) $comp_details_list[] = "Bleeding: ".$_POST['bleeding_detail'];
        if(!empty($_POST['allergy_detail'])) $comp_details_list[] = "Allergy: ".$_POST['allergy_detail'];
        
        $comp_details_str = (isset($_POST['comp_detail']) ? implode(',', $_POST['comp_detail']) . " | " : "") . implode('; ', $comp_details_list);

        // Hatyai JSON
        $hatyai_data_save = [];
        foreach (['er', 'ccu', 'icu', 'ward'] as $zone) {
            $hatyai_data_save[$zone] = [
                'date'   => $_POST["hatyai_{$zone}_date"] ?? '',
                'start'  => $_POST["hatyai_{$zone}_time_start"] ?? '',
                'end'    => $_POST["hatyai_{$zone}_time_end"] ?? '',
                'detail' => $_POST["hatyai_{$zone}_detail"] ?? ''
            ];
        }
        $hatyai_data_save['kpi'] = [
            'onset_to_fmc'       => $_POST['hatyai_onset_to_fmc'] ?? '',
            'door_to_needle'     => $_POST['hatyai_door_to_needle'] ?? '',
            'fmc_to_needle'      => $_POST['hatyai_fmc_to_needle'] ?? '',
            'ekg_to_dx'          => $_POST['hatyai_ekg_to_dx'] ?? '',
            'onset_to_needle'    => $_POST['hatyai_onset_to_needle'] ?? '',
            'stemi_dx_to_needle' => $_POST['hatyai_stemi_dx_to_needle'] ?? ''
        ];
        $hatyai_kpi_json = json_encode($hatyai_data_save, JSON_UNESCAPED_UNICODE);

        // Refer JSON
        $refer_data_save = [
            'ref1' => ['hospital' => $_POST['refer_hospital_1'] ?? '', 'province' => $_POST['refer_province_1'] ?? '', 'date' => $_POST['refer_date_1'] ?? '', 'time' => $_POST['refer_time_1'] ?? ''],
            'ref2' => ['hospital' => $_POST['refer_hospital_2'] ?? '', 'province' => $_POST['refer_province_2'] ?? '', 'date' => $_POST['refer_date_2'] ?? '', 'time' => $_POST['refer_time_2'] ?? '']
        ];
        $refer_detail_json = json_encode($refer_data_save, JSON_UNESCAPED_UNICODE);

        // SQL Execution
        $sql = "REPLACE INTO patient_medications (
            patient_id, asa, asa_reason, p2y12, p2y12_reason, fibrinolytic_type, 
            hospital_date_rpth, hospital_time_start_rpth, hospital_time_end_rpth,
            rpth_door_to_needle, rpth_stemi_dx_to_needle, complications, complications_detail,
            sk_opened, hatyai_place, hatyai_kpi_data, refer_out, refer_detail
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $patient_id, $asa_val, $_POST['asa_reason'] ?? '', $p2y12_val, $_POST['p2y12_reason'] ?? '',
            $fibrin_val, $_POST['hospital_date_rpth'] ?: null, $_POST['hospital_time_start_rpth'] ?: null, $_POST['hospital_time_end_rpth'] ?: null,
            $_POST['rpth_door_to_needle'] ?? null, $_POST['rpth_stemi_dx_to_needle'] ?? null,
            $complication_val, $comp_details_str, $sk_opened_val, $hatyai_place_val,
            $hatyai_kpi_json, $refer_out_val, $refer_detail_json
        ]);

        if ($direction === 'back') {
            header("Location: Symptoms_diagnosis.php?id=" . $patient_id);
        } else {
            header("Location: cardiac_cath.php?id=" . $patient_id);
        }
        exit();

    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// Helper Function
function isChecked($value, $array) {
    if (isset($array) && is_array($array)) {
        return in_array($value, $array) ? 'checked' : '';
    }
    return '';
}
$patient_id_query = !empty($patient_id) ? '?id=' . htmlspecialchars($patient_id) : '';
?>

<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medication & Referral</title>
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
.form-section { background: #f6f8f9; padding: 24px; border-radius: 12px; margin-top: 24px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }

/* Custom Buttons */
.check-btn { border: 1px solid #ccc; background: #fff; padding: 6px 14px; border-radius: 6px; cursor: pointer; transition: 0.2s; }
.check-btn.active { background: #28a745; color: #fff; border-color: #28a745; }
.btn-check:checked + .btn { border-width: 2px; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transform: translateY(-1px); }
.btn-check:checked + .btn-outline-primary { background-color: #0d6efd; color: white; }
.btn-check:checked + .btn-outline-success { background-color: #198754; color: white; }
.btn-check:checked + .btn-outline-danger { background-color: #dc3545; color: white; }
.btn-check:checked + .btn-outline-secondary { background-color: #6c757d; color: white; }
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
            <input type="hidden" id="ref_onset" value="<?= htmlspecialchars($onset_time) ?>">
            <input type="hidden" id="ref_fmc" value="<?= htmlspecialchars($fmc_time) ?>">
            <input type="hidden" id="ref_door" value="<?= htmlspecialchars($hospital_time_rpth) ?>">
            <input type="hidden" id="ref_diag" value="<?= htmlspecialchars($diag_ekg_time) ?>">
            
            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-3">
                <h6 class="text-primary fw-bold mb-3"><i class="bi bi-capsule me-2"></i> 1. ASA (Aspirin)</h6>
                <div class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <div class="d-flex gap-2">
                            <div class="flex-fill"><input type="radio" class="btn-check toggle-section" name="asa[]" id="asa_no" value="Null" <?= isChecked('Null', $asa) ?> onchange="toggleAsaReason(true)"><label class="btn btn-outline-secondary w-100 rounded-pill" for="asa_no"><i class="bi bi-x-circle me-1"></i> NO</label></div>
                            <div class="flex-fill"><input type="radio" class="btn-check toggle-section" name="asa[]" id="asa_yes" value="Yes" <?= isChecked('Yes', $asa) ?> onchange="toggleAsaReason(false)"><label class="btn btn-outline-success w-100 rounded-pill" for="asa_yes"><i class="bi bi-check-circle me-1"></i> YES</label></div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div id="asa_reason_div" style="display: <?= isChecked('Null', $asa) ? 'block' : 'none' ?>;">
                            <input type="text" name="asa_reason" class="form-control" placeholder="ระบุเหตุผล (e.g. Allergy)..." value="<?= htmlspecialchars($asa_reason) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
                <h6 class="text-primary fw-bold mb-3"><i class="bi bi-capsule-pill me-2"></i> 2. P2Y12 inhibitors</h6>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php foreach(['Clopidogrel', 'Prasugrel', 'Ticagrelor'] as $drug): ?>
                        <div class="flex-fill">
                            <input type="checkbox" class="btn-check p2-drug" id="p2_<?= $drug ?>" name="p2y12[]" value="<?= $drug ?>" <?= isChecked($drug, $p2y12) ?> onchange="toggleP2Y12('drug')">
                            <label class="btn btn-outline-primary w-100 rounded-pill" for="p2_<?= $drug ?>"><?= $drug ?></label>
                        </div>
                    <?php endforeach; ?>
                    <div class="flex-fill">
                        <input type="checkbox" class="btn-check" id="p2_null" name="p2y12[]" value="Null" <?= isChecked('Null', $p2y12) ?> onchange="toggleP2Y12('null')">
                        <label class="btn btn-outline-secondary w-100 rounded-pill" for="p2_null"><i class="bi bi-x-circle me-1"></i> NO</label>
                    </div>
                </div>
                <div id="p2y12_reason_div" class="animate__animated animate__fadeIn" style="display: <?= isChecked('Null', $p2y12) ? 'block' : 'none' ?>;">
                    <input type="text" name="p2y12_reason" class="form-control" placeholder="ระบุเหตุผล..." value="<?= htmlspecialchars($p2y12_reason) ?>">
                </div>
            </div>

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
                <h6 class="text-primary fw-bold mb-3"><i class="bi bi-droplet-half me-2"></i> 3. Fibrinolytics</h6>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php 
                    $fib_drugs = ['SK' => 'SK (Streptokinase)', 'TNK' => 'TNK (Tenecteplase)', 'rtPA' => 'rtPA'];
                    foreach($fib_drugs as $val => $label): 
                        $isChecked = isChecked($label, $fibrinolytic); ?>
                        <div class="flex-fill">
                            <input type="checkbox" class="btn-check fibr-drug" id="fib_<?= $val ?>" name="fibrinolytic[]" value="<?= $label ?>" <?= $isChecked ?> onchange="toggleFibrinolytic('drug')">
                            <label class="btn btn-outline-primary w-100 rounded-pill" for="fib_<?= $val ?>"><?= $label ?></label>
                        </div>
                    <?php endforeach; ?>
                    <div class="flex-fill">
                        <input type="checkbox" class="btn-check" id="fibr_null" name="fibrinolytic[]" value="Null" <?= isChecked('Null', $fibrinolytic) ?> onchange="toggleFibrinolytic('null')">
                        <label class="btn btn-outline-secondary w-100 rounded-pill" for="fibr_null"><i class="bi bi-x-circle me-1"></i> NO</label>
                    </div>
                </div>

                <div id="fibr_reason_div" class="bg-light p-3 rounded-3 border border-warning border-opacity-25 mb-3" style="display: <?= isChecked('Null', $fibrinolytic) ? 'block' : 'none' ?>;">
                    <h6 class="text-warning text-dark fw-bold small mb-2">Reason for not giving Fibrinolytic</h6>
                    <div class="row g-2">
                        <?php 
                        $reasons = ['1.Contraindicated'=>'1. Contraindicated', '2.LateOnset'=>'2. Late Onset', '3.ReferPPCI'=>'3. Refer PPCI', '4.PreviousSK'=>'4. Previous SK', '5.NotIndicated'=>'5. Not indicated', '6.Other'=>'6. Other'];
                        foreach($reasons as $val => $label): $isChk = isChecked($val, $reason); ?>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="reason[]" value="<?= $val ?>" id="r_<?= md5($val) ?>" <?= $isChk ?> onchange="toggleSubReason(this.value, this.checked)">
                                    <label class="form-check-label small" for="r_<?= md5($val) ?>"><?= $label ?></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="subreason_div" class="mt-2 ms-3 p-2 bg-white border rounded" style="display: <?= isChecked('6.Other', $reason) ? 'block' : 'none' ?>;">
                        <h6 class="fw-bold small text-secondary">Specify Other:</h6>
                        <?php 
                        $subs = ['6.1 Not Consent', '6.2 Spontaneous Resolution', '6.3 Onset Unknown', '6.4 Reason Unknown'];
                        foreach($subs as $s): $isSubChk = isChecked($s, $subreason); ?>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="subreason[]" value="<?= $s ?>" id="sub_<?= md5($s) ?>" <?= $isSubChk ?>><label class="form-check-label small" for="sub_<?= md5($s) ?>"><?= $s ?></label></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="fibrinolytic_details_section" style="display: <?= (!isChecked('Null', $fibrinolytic) && !empty($fibrinolytic)) ? 'block' : 'none' ?>;">
                    <div class="card card-body bg-light border-0 mb-3">
                        <h6 class="fw-bold text-success small mb-2"><i class="bi bi-hospital me-1"></i> Administration Details</h6>
                        <div class="row g-2">
                            <div class="col-md-4"><label class="small fw-bold">Date</label><input type="date" name="hospital_date_rpth" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['hospital_date_rpth'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="small fw-bold">Start Time</label><input type="time" name="hospital_time_start_rpth" class="form-control form-control-sm hatyai-start-time" value="<?= htmlspecialchars($_POST['hospital_time_start_rpth'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="small fw-bold">End Time</label><input type="time" name="hospital_time_end_rpth" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['hospital_time_end_rpth'] ?? '') ?>"></div>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                         <div class="col-12"><h6 class="fw-bold text-secondary small border-bottom pb-1">Time Intervals (Auto)</h6></div>
                         <div class="col-md-6">
                            <div class="input-group input-group-sm mb-2"><span class="input-group-text bg-light">Onset to FMC</span><input type="number" id="rpth_onset_to_fmc" class="form-control" readonly><span class="input-group-text">min</span></div>
                            <div class="input-group input-group-sm mb-2"><span class="input-group-text bg-primary bg-opacity-10 text-primary fw-bold">Door to Needle</span><input type="number" name="rpth_door_to_needle" id="rpth_door_to_needle" class="form-control fw-bold text-primary" readonly value="<?= htmlspecialchars($rpth_door_to_needle ?? '') ?>"><span class="input-group-text bg-primary text-white">min</span></div>
                         </div>
                         <div class="col-md-6">
                            <div class="input-group input-group-sm mb-2"><span class="input-group-text bg-light">Onset to Needle</span><input type="number" id="rpth_onset_to_needle" class="form-control" readonly><span class="input-group-text">min</span></div>
                            <div class="input-group input-group-sm mb-2"><span class="input-group-text bg-light">STEMI DX to Needle</span><input type="number" id="rpth_stemi_dx_to_needle" class="form-control" readonly><span class="input-group-text">min</span></div>
                         </div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
                <h6 class="text-primary fw-bold mb-3"><i class="bi bi-activity me-2"></i> Success of Fibrinolysis</h6>
                <div class="d-flex gap-2">
                    <div class="flex-fill"><input type="checkbox" class="btn-check sk-group" id="sk_no_btn" name="sk_opened[]" value="ไม่ได้" <?= isChecked('ไม่ได้', $_POST['sk_opened'] ?? []) ?> onchange="toggleSkResult('no')"><label class="btn btn-outline-danger w-100 rounded-pill" for="sk_no_btn">Failed</label></div>
                    <div class="flex-fill"><input type="checkbox" class="btn-check sk-group" id="sk_yes_btn" name="sk_opened[]" value="ได้" <?= isChecked('ได้', $_POST['sk_opened'] ?? []) ?> onchange="toggleSkResult('yes')"><label class="btn btn-outline-success w-100 rounded-pill" for="sk_yes_btn">Successful</label></div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
                <h6 class="text-primary fw-bold mb-3"><i class="bi bi-building-add me-2"></i> 4. Hatyai Hospital Data</h6>
                <div class="row g-2 mb-3">
                    <?php foreach (['er'=>'ER', 'ccu'=>'CCU', 'icu'=>'ICU', 'ward'=>'Ward'] as $id => $label): ?>
                    <div class="col-6 col-md-3">
                        <input type="checkbox" class="btn-check place-toggle" id="hatyai_<?= $id ?>" name="hatyai_place[]" value="<?= $label ?>" <?= isChecked($label, $_POST['hatyai_place'] ?? []) ?> onchange="toggleHatyaiDisplay()">
                        <label class="btn btn-outline-primary w-100 py-3 rounded-3" for="hatyai_<?= $id ?>">
                            <span class="fw-bold"><?= $label ?></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php foreach (['er'=>'ER', 'ccu'=>'CCU', 'icu'=>'ICU', 'ward'=>'Ward'] as $id => $label): ?>
                <div id="hatyai_<?= $id ?>_div" class="mb-3 p-3 border rounded bg-light animate__animated animate__fadeIn" style="display:<?= isChecked($label, $_POST['hatyai_place'] ?? []) ? 'block' : 'none' ?>;">
                    <h6 class="fw-bold small text-primary mb-2"><?= $label ?> Record</h6>
                    <div class="row g-2">
                        <div class="col-md-4"><label class="small fw-bold">Date</label><input type="date" name="hatyai_<?= $id ?>_date" class="form-control form-control-sm" value="<?= htmlspecialchars(${'hatyai_'.$id.'_date'} ?? '') ?>"></div>
                        <div class="col-md-4"><label class="small fw-bold">Start</label><input type="time" name="hatyai_<?= $id ?>_time_start" class="form-control form-control-sm hatyai-start-time" value="<?= htmlspecialchars(${'hatyai_'.$id.'_time_start'} ?? '') ?>"></div>
                        <div class="col-md-4"><label class="small fw-bold">End</label><input type="time" name="hatyai_<?= $id ?>_time_end" class="form-control form-control-sm" value="<?= htmlspecialchars(${'hatyai_'.$id.'_time_end'} ?? '') ?>"></div>
                        <div class="col-12"><input type="text" name="hatyai_<?= $id ?>_detail" class="form-control form-control-sm" placeholder="Note..." value="<?= htmlspecialchars(${'hatyai_'.$id.'_detail'} ?? '') ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div id="hatyai_time_intervals_section" class="mt-3 pt-3 border-top" style="display: none;">
                    <h6 class="fw-bold text-success small mb-2">KPI: Time Intervals (Hatyai)</h6>
                    <div class="row g-2">
                        <div class="col-md-6"><div class="input-group input-group-sm"><span class="input-group-text">Door to Needle</span><input type="number" name="hatyai_door_to_needle" class="form-control fw-bold text-primary" readonly value="<?= htmlspecialchars($hatyai_door_to_needle ?? '') ?>"></div></div>
                        </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
                <h6 class="text-primary fw-bold mb-3"><i class="bi bi-exclamation-octagon-fill me-2"></i> 5. Complications (During Meds)</h6>
                <div class="row align-items-center mb-3">
                    <div class="col-md-5"><strong class="small text-secondary">Occurred?</strong></div>
                    <div class="col-md-7 d-flex gap-2">
                        <div class="flex-fill"><input type="checkbox" class="btn-check comp-main" id="comp_no" name="complication[]" value="No" <?= isChecked('No', $complication) ?> onchange="toggleComplication('no')"><label class="btn btn-outline-secondary w-100 rounded-pill" for="comp_no">NO</label></div>
                        <div class="flex-fill"><input type="checkbox" class="btn-check comp-main" id="comp_yes" name="complication[]" value="Yes" <?= isChecked('Yes', $complication) ?> onchange="toggleComplication('yes')"><label class="btn btn-outline-success w-100 rounded-pill" for="comp_yes">YES</label></div>
                    </div>
                </div>
                <div id="complication-options" class="bg-danger bg-opacity-10 p-3 rounded border border-danger border-opacity-25" style="display: <?= isChecked('Yes', $complication) ? 'block' : 'none' ?>;">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php 
                        $comps = [
                            'bp_drop'=>'BP Drop', 
                            'nv'=>'N/V', 
                            'arrhythmia'=>'Arrhythmia', 
                            'cardiac_arrest'=>'Cardiac Arrest', 
                            'bleeding'=>'Bleeding', 
                            'allergy_comp'=>'Allergy'
                        ];
                        foreach ($comps as $id => $label): ?>
                            <div><input type="checkbox" class="btn-check comp-sub" id="c_<?= $id ?>" name="comp_detail[]" value="<?= $label ?>" <?= isChecked($label, $comp_detail_arr ?? []) ?> onchange="toggleCompDetail('<?= $id ?>')"><label class="btn btn-outline-danger bg-white btn-sm rounded-pill shadow-sm px-3" for="c_<?= $id ?>"><?= $label ?></label></div>
                        <?php endforeach; ?>
                    </div>
                    <div id="arrhythmia-detail" style="display:none;" class="mb-2"><input type="text" class="form-control form-control-sm" name="arrhythmia_detail" placeholder="Type..." value="<?= htmlspecialchars($_POST['arrhythmia_detail'] ?? '') ?>"></div>
                    <div id="bleeding-detail" style="display:none;" class="mb-2"><input type="text" class="form-control form-control-sm" name="bleeding_detail" placeholder="Location..." value="<?= htmlspecialchars($_POST['bleeding_detail'] ?? '') ?>"></div>
                    <div id="allergy_comp-detail" style="display:none;" class="mb-2"><input type="text" class="form-control form-control-sm" name="allergy_detail" placeholder="Drug/Symptom..." value="<?= htmlspecialchars($_POST['allergy_detail'] ?? '') ?>"></div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
                <h6 class="text-primary fw-bold mb-3"><i class="bi bi-ambulance me-2"></i> 6. Refer Out</h6>
                <div class="d-flex gap-2 mb-3">
                    <div class="flex-fill"><input type="checkbox" class="btn-check refer-main" id="refer_no" name="refer_out[]" value="No" <?= isChecked('No', $refer_out) ?> onchange="toggleRefer('no')"><label class="btn btn-outline-secondary w-100 rounded-pill" for="refer_no">NO</label></div>
                    <div class="flex-fill"><input type="checkbox" class="btn-check refer-main" id="refer_yes" name="refer_out[]" value="Yes" <?= isChecked('Yes', $refer_out) ?> onchange="toggleRefer('yes')"><label class="btn btn-outline-success w-100 rounded-pill" for="refer_yes">YES</label></div>
                </div>
                
                <div id="referOutFields" style="display: <?= isChecked('Yes', $refer_out) ? 'block' : 'none' ?>;">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="card h-100 border shadow-sm">
                                <div class="card-header bg-light small fw-bold">Destination 1</div>
                                <div class="card-body">
                                    <input type="text" class="form-control form-control-sm mb-2" name="refer_hospital_1" placeholder="Hospital Name" value="<?= htmlspecialchars($ref1_hosp) ?>">
                                    <input type="text" class="form-control form-control-sm mb-2" name="refer_province_1" placeholder="Province" value="<?= htmlspecialchars($ref1_prov) ?>">
                                    <div class="row g-2">
                                        <div class="col-6"><input type="date" class="form-control form-control-sm" name="refer_date_1" value="<?= htmlspecialchars($ref1_date) ?>"></div>
                                        <div class="col-6"><input type="time" class="form-control form-control-sm" name="refer_time_1" value="<?= htmlspecialchars($ref1_time) ?>"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card h-100 border shadow-sm">
                                <div class="card-header bg-light small fw-bold">Destination 2 (Optional)</div>
                                <div class="card-body">
                                    <input type="text" class="form-control form-control-sm mb-2" name="refer_hospital_2" placeholder="Hospital Name" value="<?= htmlspecialchars($ref2_hosp) ?>">
                                    <input type="text" class="form-control form-control-sm mb-2" name="refer_province_2" placeholder="Province" value="<?= htmlspecialchars($ref2_prov) ?>">
                                    <div class="row g-2">
                                        <div class="col-6"><input type="date" class="form-control form-control-sm" name="refer_date_2" value="<?= htmlspecialchars($ref2_date) ?>"></div>
                                        <div class="col-6"><input type="time" class="form-control form-control-sm" name="refer_time_2" value="<?= htmlspecialchars($ref2_time) ?>"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
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
// Toggle Functions defined globally
function toggleAsaReason(show) {
    const d = document.getElementById('asa_reason_div');
    const no = document.getElementById('asa_null');
    const yes = document.getElementById('asa_yes');
    if (show) {
        if(yes) yes.checked = false;
        d.style.display = 'block';
        d.querySelector('input').focus();
    } else {
        if(no) no.checked = false;
        d.style.display = 'none';
    }
}

function toggleP2Y12(type) {
    const nullCheckbox = document.getElementById('p2y12_null');
    const drugCheckboxes = document.querySelectorAll('.p2y12-drug');
    const reasonDiv = document.getElementById('p2y12_reason_div');

    if (type === 'null') {
        if (nullCheckbox.checked) {
            drugCheckboxes.forEach(cb => cb.checked = false);
            reasonDiv.style.display = 'block';
            reasonDiv.querySelector('input').focus();
        } else {
            reasonDiv.style.display = 'none';
        }
    } else if (type === 'drug') {
        let anyChecked = Array.from(drugCheckboxes).some(cb => cb.checked);
        if (anyChecked) {
            nullCheckbox.checked = false;
            reasonDiv.style.display = 'none';
        }
    }
}

function toggleFibrinolytic(type) {
    const nullCheck = document.getElementById('fibr_null');
    const drugs = document.querySelectorAll('.fibr-drug');
    const reasonDiv = document.getElementById('fibr_reason_div');
    const detailsDiv = document.getElementById('fibrinolytic_details_section');

    if (type === 'null') {
        if (nullCheck.checked) {
            drugs.forEach(d => d.checked = false);
            reasonDiv.style.display = 'block';
            detailsDiv.style.display = 'none';
        } else {
            reasonDiv.style.display = 'none';
        }
    } else {
        let anyDrug = Array.from(drugs).some(d => d.checked);
        if (anyDrug) {
            nullCheck.checked = false;
            reasonDiv.style.display = 'none';
            detailsDiv.style.display = 'block';
        } else {
            detailsDiv.style.display = 'none';
        }
    }
}

function toggleSubReason(val, checked) {
    if (val === '6.Other') {
        const div = document.getElementById('subreason_div');
        if(div) div.style.display = checked ? 'block' : 'none';
    }
}

function toggleSkResult(val) {
    const noBtn = document.getElementById('sk_no_btn');
    const yesBtn = document.getElementById('sk_yes_btn');
    if (val === 'yes') {
        if(yesBtn.checked) noBtn.checked = false;
    } else {
        if(noBtn.checked) yesBtn.checked = false;
    }
}

function toggleHatyaiDisplay() {
    const toggles = document.querySelectorAll('.place-toggle');
    const section = document.getElementById('hatyai_time_intervals_section');
    let anyChecked = false;
    toggles.forEach(t => {
        const div = document.getElementById(t.id + '_div');
        if(div) div.style.display = t.checked ? 'block' : 'none';
        if(t.checked) anyChecked = true;
    });
    if(section) section.style.display = anyChecked ? 'block' : 'none';
    
    // Trigger calculation
    updateHatyaiIntervals();
}

function toggleComplication(val) {
    const yes = document.getElementById('comp_yes');
    const no = document.getElementById('comp_no');
    const div = document.getElementById('complication-options');
    
    if (val === 'yes') {
        if (yes.checked) {
            no.checked = false;
            div.style.display = 'block';
        } else {
            div.style.display = 'none';
        }
    } else {
        if (no.checked) {
            yes.checked = false;
            div.style.display = 'none';
             // Clear inputs
             div.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
             div.querySelectorAll('input[type="text"]').forEach(t => {t.value = ''; t.parentElement.style.display = 'none';});
        }
    }
}

function toggleCompDetail(id) {
    const cb = document.getElementById('c_' + id);
    const div = document.getElementById(id + '-detail');
    // Special handling for allergy_comp due to id naming
    let targetDiv = div;
    if (id === 'allergy_comp') targetDiv = document.getElementById('allergy_comp-detail');
    
    if (targetDiv) {
        targetDiv.style.display = (cb && cb.checked) ? 'block' : 'none';
        if(cb.checked) targetDiv.querySelector('input').focus();
    }
}

function toggleRefer(val) {
    const yes = document.getElementById('refer_yes');
    const no = document.getElementById('refer_no');
    const div = document.getElementById('referOutFields');
    
    if (val === 'yes') {
        if (yes.checked) {
            no.checked = false;
            div.style.display = 'block';
        } else {
            div.style.display = 'none';
        }
    } else {
        if (no.checked) {
            yes.checked = false;
            div.style.display = 'none';
        }
    }
}

// Calculation Logic
function calculateMins(start, end) {
    if (!start || !end) return "";
    const s = new Date("2026-01-01T" + start);
    const e = new Date("2026-01-01T" + end);
    let diff = (e - s) / 60000;
    if (diff < 0) diff += 1440;
    return Math.floor(diff);
}

function updateRpthIntervals() {
    const needle = document.getElementById('hospital_time_start_rpth').value;
    const door = document.getElementById('ref_door').value; 
    const onset = document.getElementById('ref_onset').value;
    const fmc = document.getElementById('ref_fmc').value;
    const diag = document.getElementById('ref_diag').value;

    if (onset && fmc) document.getElementById('rpth_onset_to_fmc').value = calculateMins(onset, fmc);
    if (needle) {
        if (door) document.getElementById('rpth_door_to_needle').value = calculateMins(door, needle);
        if (onset) document.getElementById('rpth_onset_to_needle').value = calculateMins(onset, needle);
        if (diag) document.getElementById('rpth_stemi_dx_to_needle').value = calculateMins(diag, needle);
        const fmcInput = document.querySelector('input[name="rpth_fmc_to_needle"]');
        if(fmcInput && fmc) fmcInput.value = calculateMins(fmc, needle);
    }
}

function updateHatyaiIntervals() {
    let needle = null;
    for (let place of ['er', 'ccu', 'icu', 'ward']) {
        const cb = document.getElementById('hatyai_' + place);
        const tm = document.querySelector(`input[name="hatyai_${place}_time_start"]`);
        if (cb && cb.checked && tm && tm.value) {
             needle = tm.value;
             break;
        }
    }

    if (needle) {
        const door = document.getElementById('ref_door').value; // Need logic for Hatyai door time if different
        // Assuming refer door time or same door time logic
        if(door) {
             const d2n = document.querySelector('input[name="hatyai_door_to_needle"]');
             if(d2n) d2n.value = calculateMins(door, needle);
        }
        // ... similar logic for other hatyai intervals
    }
}


document.addEventListener('DOMContentLoaded', function() {
    // Initial Run for Calculations
    updateRpthIntervals();
    // Attach listeners for RPTH time inputs
    const rpthStart = document.getElementById('hospital_time_start_rpth');
    if(rpthStart) rpthStart.addEventListener('change', updateRpthIntervals);
    
    // Attach listeners for Hatyai time inputs
    document.querySelectorAll('.hatyai-start-time').forEach(el => el.addEventListener('change', updateHatyaiIntervals));

    // Restore Complication Details state on load
    ['arrhythmia', 'bleeding', 'allergy_comp'].forEach(id => {
        const cb = document.getElementById('c_' + id);
        if (cb && cb.checked) toggleCompDetail(id);
    });
});
</script>
</body>
</html>