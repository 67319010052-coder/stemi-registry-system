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

// รับ ID ผู้ป่วยจากหน้าแรก
$patient_id = $_GET['id'] ?? $_POST['patient_id'] ?? '';

// =================================================================================
// 1. DATA FETCHING & LINKING
// =================================================================================

// 1.1 ดึงข้อมูลพื้นฐานผู้ป่วย (อายุ/เพศ) เพื่อใช้คำนวณ GFR
$stmt_pt = $pdo->prepare("SELECT age, gender FROM patients WHERE id = ?");
$stmt_pt->execute([$patient_id]);
$patient_info = $stmt_pt->fetch(PDO::FETCH_ASSOC) ?: [];
$patient_age = $patient_info['age'] ?? 0;
$patient_gender = $patient_info['gender'] ?? 'ชาย';

// 1.2 ดึงข้อมูลจากหน้า Diagnosis (เพื่อเชื่อมโยง Lab: Creatinine)
$stmt_link = $pdo->prepare("SELECT creatinine FROM symptoms_diagnosis WHERE patient_id = ?");
$stmt_link->execute([$patient_id]);
$linked_data = $stmt_link->fetch(PDO::FETCH_ASSOC) ?: [];

// 1.3 ดึงข้อมูลเดิมของหน้านี้ (History Risk)
$stmt_curr = $pdo->prepare("SELECT * FROM patient_risk_factors WHERE patient_id = ?");
$stmt_curr->execute([$patient_id]);
$existing_data = $stmt_curr->fetch(PDO::FETCH_ASSOC) ?: [];

// =================================================================================
// 2. VARIABLE INITIALIZATION (Priority: POST > DB > Linked > Default)
// =================================================================================

$fields = [
    'referral_type', 'ward', 
    'refer_from_hosp1', 'refer_from_province1', 'refer_from_hosp2', 'refer_from_province2',
    'fibrinolytic_status', 'fibrinolytic_refer_option',
    'prior_mi', 'prior_hf', 'prior_pci', 'prior_cabg',
    'diabetes', 'fbs', 'hba1c',
    'dyslipidemia', 'chol_value', 'tg_value', 'hdl_value', 'ldl_value',
    'hypertension', 'smoker', 'family_history',
    'cerebrovascular', 'peripheral', 'cope', 'ckd',
    'dialysis', 'dialysis_type',
    'other_comorbidity', 'allergy', 'food_allergy',
    'hb', 'platelet', 'inr', 'pt', 'ptt', 'k', 'cr', 'gfr'
];

foreach ($fields as $f) {
    $$f = $_POST[$f] ?? $existing_data[$f] ?? '';
}

// กรณีพิเศษ: Checkbox Array (Dyslipidemia)
// ใน DB เก็บเป็น string คั่นด้วย comma (เช่น "CHOL,LDL") -> แปลงกลับเป็น Array เพื่อเช็คใน HTML
$chol_check_str = $_POST['chol_check'] ?? $existing_data['chol_check'] ?? ''; 
$chol_check_arr = is_array($chol_check_str) ? $chol_check_str : explode(',', $chol_check_str);

// --- SMART LINKING LOGIC ---
// ถ้าค่า Creatinine ในหน้านี้ว่าง ให้ดึงจาก Diagnosis มาใส่
if ($cr === '' && !empty($linked_data['creatinine'])) {
    $cr = $linked_data['creatinine'];
}

// คำนวณ GFR อัตโนมัติ (PHP Side) ถ้ามี Cr แต่ไม่มี GFR
if (is_numeric($cr) && $cr > 0 && ($gfr === '' || $gfr == 0)) {
    $isFemale = ($patient_gender === 'หญิง');
    $kappa = $isFemale ? 0.7 : 0.9;
    $alpha = $isFemale ? -0.329 : -0.411;
    $sexFactor = $isFemale ? 1.018 : 1.0;
    
    // สูตร CKD-EPI 2021
    $calc_gfr = 141 * pow(min($cr / $kappa, 1.0), $alpha) * pow(max($cr / $kappa, 1.0), -1.209) * pow(0.993, $patient_age) * $sexFactor;
    $gfr = number_format($calc_gfr, 2);
}

// =================================================================================
// 3. SAVE LOGIC
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($patient_id)) {
        echo "<script>alert('Error: ไม่พบ Patient ID'); window.location.href = 'history_risk_factor.php';</script>";
        exit;
    }
    $direction = $_POST['direction'] ?? 'next';

    try {
        // เตรียมค่า Checkbox array ให้เป็น string ก่อนบันทึก
        $chol_check_save = isset($_POST['chol_check']) ? implode(',', $_POST['chol_check']) : '';

        $sql = "REPLACE INTO patient_risk_factors (
            patient_id, referral_type, ward, refer_from_hosp1, refer_from_province1,
            refer_from_hosp2, refer_from_province2, fibrinolytic_status, fibrinolytic_refer_option,
            prior_mi, prior_hf, prior_pci, prior_cabg, diabetes, fbs, hba1c, 
            dyslipidemia, chol_check, chol_value, tg_value, hdl_value, ldl_value,
            hypertension, smoker, family_history, cerebrovascular, peripheral, 
            cope, ckd, dialysis, dialysis_type, other_comorbidity, allergy, food_allergy,
            hb, platelet, inr, pt, ptt, k, cr, gfr
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";

        $params = [
            $patient_id, $referral_type, $ward, $refer_from_hosp1, $refer_from_province1,
            $refer_from_hosp2, $refer_from_province2, $fibrinolytic_status, $fibrinolytic_refer_option,
            $prior_mi, $prior_hf, $prior_pci, $prior_cabg, $diabetes, 
            ($fbs !== '') ? $fbs : null, 
            ($hba1c !== '') ? $hba1c : null, 
            $dyslipidemia, $chol_check_save, 
            ($chol_value !== '') ? $chol_value : null,
            ($tg_value !== '') ? $tg_value : null,
            ($hdl_value !== '') ? $hdl_value : null,
            ($ldl_value !== '') ? $ldl_value : null,
            $hypertension, $smoker, $family_history, $cerebrovascular, $peripheral, 
            $cope, $ckd, $dialysis, $dialysis_type, $other_comorbidity, $allergy, $food_allergy,
            ($hb !== '') ? $hb : null,
            ($platelet !== '') ? $platelet : null,
            ($inr !== '') ? $inr : null,
            ($pt !== '') ? $pt : null,
            ($ptt !== '') ? $ptt : null,
            ($k !== '') ? $k : null,
            ($cr !== '') ? $cr : null,
            ($gfr !== '') ? $gfr : null
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($direction === 'back') {
            header("Location: patient_form.php?id=" . $patient_id);
        } else {
            header("Location: Symptoms_diagnosis.php?id=" . $patient_id);
        }
        exit();

    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// Helper Functions
function isChecked($currentValue, $targetValue) {
    return ($currentValue === $targetValue) ? 'checked' : '';
}
function isCheckedArr($value, $array) {
    return (is_array($array) && in_array($value, $array)) ? 'checked' : '';
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>History & Risk Factors</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<style>
/* --- Layout & General Styles (เหมือนไฟล์ก่อนหน้า) --- */
 body { 
    font-family: 'Sarabun', sans-serif;
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

/* Custom Radio/Checkbox Buttons */
.btn-check:checked + .btn-outline-primary { background-color: #0d6efd; color: white; }
.btn-check:checked + .btn-outline-success { background-color: #198754; color: white; }
.btn-check:checked + .btn-outline-danger { background-color: #dc3545; color: white; }
.btn-check:checked + .btn-outline-warning { background-color: #ffc107; color: black; }
.btn-check:checked + .btn-outline-info { background-color: #0dcaf0; color: black; }
.btn-check:checked + .btn-outline-secondary { background-color: #6c757d; color: white; }

/* Sticky Nav */
.nav-pills .nav-link.active {
    background-color: #0d6efd;
    color: white !important;
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
      <?php
        $nav_items = [
            'patient_form.php'        => ['label' => 'ข้อมูลผู้ป่วย',       'icon' => 'bi-person-vcard'],
            'history_risk_factor.php' => ['label' => 'History & Risk',   'icon' => 'bi-clipboard-pulse'],
            'Symptoms_diagnosis.php'  => ['label' => 'Diagnosis',        'icon' => 'bi-heart-pulse'],
            'Medication.php'          => ['label' => 'Medication',       'icon' => 'bi-capsule'],
            'cardiac_cath.php'        => ['label' => 'Cardiac Cath',     'icon' => 'bi-activity'],
            'treatment_results.php'   => ['label' => 'Result',           'icon' => 'bi-clipboard-check'],
            'discharge.php'           => ['label' => 'Discharge',        'icon' => 'bi-door-open']
        ];
        $current_page = basename($_SERVER['PHP_SELF']);
        $patient_id_query = !empty($patient_id) ? '?id=' . htmlspecialchars($patient_id) : '';
        ?>

        <div class="card shadow-sm border-0 mb-4 overflow-hidden rounded-4">
            <div class="card-body p-2 bg-white">
                <ul class="nav nav-pills nav-fill flex-nowrap overflow-auto pb-1" style="scrollbar-width: none;">
                    <?php foreach ($nav_items as $file => $item): 
                        $isActive = ($current_page == $file);
                        $activeClass = $isActive ? 'active shadow-sm' : 'text-secondary';
                    ?>
                    <li class="nav-item">
                        <a class="nav-link d-flex flex-column align-items-center gap-1 py-2 mx-1 <?= $activeClass ?>" 
                           href="<?= $file . $patient_id_query ?>">
                            <i class="bi <?= $item['icon'] ?> fs-5"></i>
                            <span class="small fw-bold"><?= $item['label'] ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="patient_id" value="<?= htmlspecialchars($patient_id) ?>">
            <input type="hidden" id="age_from_db" value="<?= htmlspecialchars($patient_age) ?>">
            <input type="hidden" id="gender_from_db" value="<?= htmlspecialchars($patient_gender) ?>">
            
            <div class="card shadow-sm border-0 mb-4 rounded-4 bg-light-subtle">
                <div class="card-body p-4">
                    <h6 class="text-primary fw-bold mb-3"><i class="bi bi-hospital me-2 fs-5"></i> Refer Status</h6>
                    
                    <div class="mb-3">
                        <div class="d-flex flex-wrap gap-2">
                            <?php $refTypes = ['Referral', 'EMS', 'Walk in', 'IPD']; 
                            foreach($refTypes as $rt) {
                                $id = 'chk'.str_replace(' ', '', $rt);
                                $color = ($rt=='EMS')?'danger':(($rt=='Referral')?'primary':(($rt=='Walk in')?'success':'info'));
                                echo "<input class='btn-check' type='radio' name='referral_type' value='$rt' id='$id' ".isChecked($referral_type, $rt).">
                                      <label class='btn btn-outline-$color rounded-pill px-4' for='$id'>$rt</label>";
                            } ?>
                            
                            <div id="wardInputWrapper" class="animate__animated animate__fadeIn" style="display: <?= ($referral_type=='IPD')?'inline-block':'none' ?>;">
                                <input type="text" name="ward" class="form-control rounded-pill border-info" placeholder="ระบุ Ward..." value="<?= htmlspecialchars($ward) ?>" style="width: 180px;">
                            </div>
                        </div>
                    </div>

                    <div id="referInputsContainer" class="bg-white p-3 rounded-3 border border-dashed animate__animated animate__fadeIn" style="display: <?= ($referral_type=='Referral')?'block':'none' ?>;">
                        <small class="text-muted fw-bold"><i class="bi bi-arrow-return-right"></i> รายละเอียดการส่งต่อ</small>
                        <div class="row g-2 mt-2">
                            <div class="col-md-6">
                                <label class="form-label small">รพ. ต้นทาง 1</label>
                                <input type="text" name="refer_from_hosp1" class="form-control bg-light" value="<?= htmlspecialchars($refer_from_hosp1) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">จังหวัด</label>
                                <input type="text" name="refer_from_province1" class="form-control bg-light" value="<?= htmlspecialchars($refer_from_province1) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">รพ. ต้นทาง 2 (ถ้ามี)</label>
                                <input type="text" name="refer_from_hosp2" class="form-control bg-light" value="<?= htmlspecialchars($refer_from_hosp2) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">จังหวัด</label>
                                <input type="text" name="refer_from_province2" class="form-control bg-light" value="<?= htmlspecialchars($refer_from_province2) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4 rounded-4">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h6 class="text-primary fw-bold mb-0"><i class="bi bi-capsule me-2"></i> Fibrinolytics</h6>
                        </div>
                        <div class="col-md-8">
                            <div class="d-flex gap-2">
                                <input type="radio" class="btn-check" name="fibrinolytic_status" id="fib_no" value="NO" <?= isChecked($fibrinolytic_status, 'NO') ?>>
                                <label class="btn btn-outline-secondary rounded-pill px-4" for="fib_no">NO</label>

                                <input type="radio" class="btn-check" name="fibrinolytic_status" id="fib_yes" value="YES" <?= isChecked($fibrinolytic_status, 'YES') ?>>
                                <label class="btn btn-outline-success rounded-pill px-4" for="fib_yes">YES</label>
                            </div>
                            
                            <div id="fibrinolyticOptionsContainer" class="mt-3 animate__animated animate__fadeIn" style="display: <?= ($fibrinolytic_status=='YES')?'block':'none' ?>;">
                                <select name="fibrinolytic_refer_option" class="form-select bg-light">
                                    <option value="">-- ระบุสถานะ --</option>
                                    <?php $fib_ops = ['รพช.ในสงขลา-ไม่ได้Fibrinolytics', 'รพช.ในสงขลา-PostFibrinolytics', 'รพท.ในสงขลา-ไม่ได้Fibrinolytics', 'รพท.ในสงขลา-PostFibrinolytics', 'รพช.นอกสงขลา-ไม่ได้Fibrinolytics', 'รพช.นอกสงขลา-PostFibrinolytics', 'รพท.นอกสงขลา-ไม่ได้Fibrinolytics', 'รพท.นอกสงขลา-PostFibrinolytics', 'รพ.เอกชน', 'สงขลานครินทร์ (ม.อ.)'];
                                    foreach($fib_ops as $op) echo "<option value='$op' ".($fibrinolytic_refer_option==$op?'selected':'').">$op</option>"; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
                <h6 class="text-primary fw-bold mb-4 border-bottom pb-2"><i class="bi bi-clock-history me-2"></i> History</h6>
                <?php $histories = ['prior_mi'=>'Prior MI', 'prior_hf'=>'Prior HF', 'prior_pci'=>'Prior PCI', 'prior_cabg'=>'Prior CABG'];
                foreach($histories as $key=>$label): ?>
                <div class="row mb-3 align-items-center">
                    <div class="col-md-4"><strong class="text-secondary"><?= $label ?></strong></div>
                    <div class="col-md-8">
                        <div class="d-flex gap-2">
                            <input type="radio" class="btn-check" name="<?= $key ?>" id="<?= $key ?>_no" value="NO" <?= isChecked($$key, 'NO') ?>>
                            <label class="btn btn-outline-secondary rounded-pill px-3" for="<?= $key ?>_no">NO</label>
                            
                            <input type="radio" class="btn-check" name="<?= $key ?>" id="<?= $key ?>_yes" value="YES" <?= isChecked($$key, 'YES') ?>>
                            <label class="btn btn-outline-success rounded-pill px-3" for="<?= $key ?>_yes">YES</label>
                            
                            <input type="radio" class="btn-check" name="<?= $key ?>" id="<?= $key ?>_unk" value="Not known" <?= isChecked($$key, 'Not known') ?>>
                            <label class="btn btn-outline-warning rounded-pill px-3" for="<?= $key ?>_unk">Unknown</label>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
                <h6 class="text-primary fw-bold mb-4 border-bottom pb-2"><i class="bi bi-exclamation-triangle me-2"></i> Risk Factors</h6>
                
                <div class="row mb-4">
                    <div class="col-md-4"><strong class="text-secondary"><i class="bi bi-droplet me-2"></i> Diabetes Mellitus</strong></div>
                    <div class="col-md-8">
                        <div class="d-flex gap-2">
                            <input type="radio" class="btn-check" name="diabetes" id="dm_no" value="NO" <?= isChecked($diabetes, 'NO') ?>>
                            <label class="btn btn-outline-secondary rounded-pill px-3" for="dm_no">NO</label>
                            
                            <input type="radio" class="btn-check" name="diabetes" id="dm_yes" value="YES" <?= isChecked($diabetes, 'YES') ?>>
                            <label class="btn btn-outline-success rounded-pill px-3" for="dm_yes">YES</label>
                            
                            <input type="radio" class="btn-check" name="diabetes" id="dm_unk" value="Not known" <?= isChecked($diabetes, 'Not known') ?>>
                            <label class="btn btn-outline-warning rounded-pill px-3" for="dm_unk">Unknown</label>
                        </div>
                        <div id="diabetes-extra" class="mt-3 p-3 bg-light rounded animate__animated animate__fadeIn" style="display: <?= ($diabetes=='YES')?'block':'none' ?>;">
                            <div class="row g-2">
                                <div class="col-6"><input type="number" step="0.1" name="fbs" class="form-control" placeholder="FBS (mg/dL)" value="<?= htmlspecialchars($fbs) ?>"></div>
                                <div class="col-6"><input type="number" step="0.1" name="hba1c" class="form-control" placeholder="HbA1c (%)" value="<?= htmlspecialchars($hba1c) ?>"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4"><strong class="text-secondary"><i class="bi bi-egg-fried me-2"></i> Dyslipidemia</strong></div>
                    <div class="col-md-8">
                        <div class="d-flex gap-2">
                            <input type="radio" class="btn-check" name="dyslipidemia" id="dlp_no" value="NO" <?= isChecked($dyslipidemia, 'NO') ?>>
                            <label class="btn btn-outline-secondary rounded-pill px-3" for="dlp_no">NO</label>
                            <input type="radio" class="btn-check" name="dyslipidemia" id="dlp_yes" value="YES" <?= isChecked($dyslipidemia, 'YES') ?>>
                            <label class="btn btn-outline-success rounded-pill px-3" for="dlp_yes">YES</label>
                            <input type="radio" class="btn-check" name="dyslipidemia" id="dlp_unk" value="Not known" <?= isChecked($dyslipidemia, 'Not known') ?>>
                            <label class="btn btn-outline-warning rounded-pill px-3" for="dlp_unk">Unknown</label>
                        </div>
                        <div id="dyslipidemia-extra" class="mt-3 p-3 bg-light rounded animate__animated animate__fadeIn" style="display: <?= ($dyslipidemia=='YES')?'block':'none' ?>;">
                            <div class="row g-2">
                                <?php $lipids=['CHOL','TG','HDL','LDL']; 
                                foreach($lipids as $l): 
                                    $lv = strtolower($l); $valVar = $lv.'_value'; $val = $$valVar;
                                    $isChecked = isCheckedArr($l, $chol_check_arr);
                                ?>
                                <div class="col-6 col-md-3">
                                    <div class="card p-2 border-0 shadow-sm">
                                        <div class="form-check">
                                            <input class="form-check-input lipid-check" type="checkbox" name="chol_check[]" value="<?= $l ?>" id="<?= $lv ?>" <?= $isChecked ?>>
                                            <label class="form-check-label small fw-bold" for="<?= $lv ?>"><?= $l ?></label>
                                        </div>
                                        <input type="number" step="0.1" name="<?= $lv ?>_value" id="<?= $lv ?>_input" class="form-control form-control-sm mt-1" 
                                               placeholder="mg/dL" value="<?= htmlspecialchars($val) ?>" style="display: <?= ($isChecked)?'block':'none' ?>;">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php $risks = ['hypertension'=>'Hypertension', 'smoker'=>'Smoker (<3 mo)', 'family_history'=>'Family History'];
                foreach($risks as $k=>$l): ?>
                <div class="row mb-3">
                    <div class="col-md-4"><strong class="text-secondary"><?= $l ?></strong></div>
                    <div class="col-md-8">
                        <div class="d-flex gap-2">
                            <input type="radio" class="btn-check" name="<?= $k ?>" id="<?= $k ?>_no" value="NO" <?= isChecked($$k, 'NO') ?>>
                            <label class="btn btn-outline-secondary rounded-pill px-3" for="<?= $k ?>_no">NO</label>
                            <input type="radio" class="btn-check" name="<?= $k ?>" id="<?= $k ?>_yes" value="YES" <?= isChecked($$k, 'YES') ?>>
                            <label class="btn btn-outline-success rounded-pill px-3" for="<?= $k ?>_yes">YES</label>
                            <input type="radio" class="btn-check" name="<?= $k ?>" id="<?= $k ?>_unk" value="Not known" <?= isChecked($$k, 'Not known') ?>>
                            <label class="btn btn-outline-warning rounded-pill px-3" for="<?= $k ?>_unk">Unk</label>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="row mb-3 border-top pt-3">
                    <div class="col-md-4"><strong class="text-secondary">On Dialysis</strong></div>
                    <div class="col-md-8">
                        <div class="d-flex gap-2">
                            <input type="radio" class="btn-check" name="dialysis" id="dia_no" value="NO" <?= isChecked($dialysis, 'NO') ?>>
                            <label class="btn btn-outline-secondary rounded-pill px-3" for="dia_no">NO</label>
                            <input type="radio" class="btn-check" name="dialysis" id="dia_yes" value="YES" <?= isChecked($dialysis, 'YES') ?>>
                            <label class="btn btn-outline-success rounded-pill px-3" for="dia_yes">YES</label>
                            <input type="radio" class="btn-check" name="dialysis" id="dia_unk" value="Not known" <?= isChecked($dialysis, 'Not known') ?>>
                            <label class="btn btn-outline-warning rounded-pill px-3" for="dia_unk">Unk</label>
                        </div>
                        <div id="dialysis_options" class="mt-2 animate__animated animate__fadeIn" style="display: <?= ($dialysis=='YES')?'block':'none' ?>;">
                            <div class="d-flex gap-2">
                                <?php foreach(['H/D','CAPD','CRRT'] as $dt): ?>
                                <input type="radio" class="btn-check" name="dialysis_type" id="dt_<?= $dt ?>" value="<?= $dt ?>" <?= isChecked($dialysis_type, $dt) ?>>
                                <label class="btn btn-outline-primary rounded-pill btn-sm px-3" for="dt_<?= $dt ?>"><?= $dt ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
                <h6 class="text-primary fw-bold mb-4 border-bottom pb-2"><i class="bi bi-journal-medical me-2"></i> Comorbidities & Allergy</h6>
                
                <?php foreach(['cerebrovascular'=>'CVA', 'peripheral'=>'PAD', 'cope'=>'COPD', 'ckd'=>'CKD (>Stage 3)'] as $k=>$l): ?>
                <div class="row mb-2">
                    <div class="col-md-4 text-secondary"><?= $l ?></div>
                    <div class="col-md-8 d-flex gap-2">
                        <input type="radio" class="btn-check" name="<?= $k ?>" id="<?= $k ?>_no" value="NO" <?= isChecked($$k, 'NO') ?>>
                        <label class="btn btn-outline-secondary btn-sm px-3 rounded-pill" for="<?= $k ?>_no">NO</label>
                        <input type="radio" class="btn-check" name="<?= $k ?>" id="<?= $k ?>_yes" value="YES" <?= isChecked($$k, 'YES') ?>>
                        <label class="btn btn-outline-success btn-sm px-3 rounded-pill" for="<?= $k ?>_yes">YES</label>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="mt-3">
                    <label class="form-label text-muted">โรคร่วมอื่น ๆ</label>
                    <textarea name="other_comorbidity" class="form-control bg-light" rows="2"><?= htmlspecialchars($other_comorbidity) ?></textarea>
                </div>
                <div class="mt-3">
                    <label class="form-label text-danger">ประวัติแพ้ยา</label>
                    <input type="text" name="allergy" class="form-control bg-light" placeholder="ปฏิเสธ หรือ ระบุชื่อยา" value="<?= htmlspecialchars($allergy) ?>">
                </div>
                <div class="mt-3">
                    <label class="form-label text-warning">ประวัติแพ้อาหาร</label>
                    <input type="text" name="food_allergy" class="form-control bg-light" placeholder="ปฏิเสธ หรือ ระบุชื่ออาหาร" value="<?= htmlspecialchars($food_allergy) ?>">
                </div>
            </div>

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4">
                <h6 class="text-primary fw-bold mb-4 border-bottom pb-2"><i class="bi bi-eyedropper me-2"></i> Initial Lab</h6>
                <div class="row g-3">
                    <?php 
                    $labs = [
                        'hb'=>['label'=>'HB', 'unit'=>'g/dL'], 'platelet'=>['label'=>'Plt', 'unit'=>'x10^3'],
                        'inr'=>['label'=>'INR', 'unit'=>''], 'k'=>['label'=>'K', 'unit'=>'mEq/L'],
                        'cr'=>['label'=>'Creatinine', 'unit'=>'mg/dL'], 'gfr'=>['label'=>'eGFR', 'unit'=>'mL/min']
                    ];
                    foreach($labs as $k=>$v): ?>
                    <div class="col-md-4 col-6">
                        <label class="form-label small fw-bold text-secondary"><?= $v['label'] ?></label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.01" name="<?= $k ?>" id="<?= $k ?>_input" class="form-control" value="<?= htmlspecialchars($$k) ?>">
                            <span class="input-group-text bg-light"><?= $v['unit'] ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="col-md-4 col-6">
                        <label class="form-label small fw-bold text-secondary">PT</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.1" name="pt" class="form-control" value="<?= htmlspecialchars($pt) ?>">
                            <span class="input-group-text bg-light">sec</span>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <label class="form-label small fw-bold text-secondary">PTT</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.1" name="ptt" class="form-control" value="<?= htmlspecialchars($ptt) ?>">
                            <span class="input-group-text bg-light">sec</span>
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
// --- Core Logic & Event Listeners ---
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. GFR Calculation
    const crInput = document.getElementById('cr_input');
    const gfrInput = document.getElementById('gfr_input');
    const age = parseFloat(document.getElementById('age_from_db').value) || 0;
    const gender = document.getElementById('gender_from_db').value;

    function calcGFR() {
        const cr = parseFloat(crInput.value);
        if(cr > 0 && age > 0) {
            const isFemale = (gender === 'หญิง');
            const kappa = isFemale ? 0.7 : 0.9;
            const alpha = isFemale ? -0.329 : -0.411;
            const sexFactor = isFemale ? 1.018 : 1.0;
            const gfr = 141 * Math.pow(Math.min(cr / kappa, 1.0), alpha) * Math.pow(Math.max(cr / kappa, 1.0), -1.209) * Math.pow(0.993, age) * sexFactor;
            gfrInput.value = gfr.toFixed(2);
        } else {
            gfrInput.value = '';
        }
    }
    if(crInput) crInput.addEventListener('input', calcGFR);
document.getElementById('creatinine').addEventListener('change', function() {
    let cr = parseFloat(this.value);
    let age = parseInt(document.getElementById('age').value);
    let genderInput = document.querySelector('input[name="gender"]:checked');
    
    if (!cr || !age || !genderInput) return; // กัน Error กรณีข้อมูลไม่ครบ

    let gender = genderInput.value;
    let egfr = 0;

    // --- สูตร CKD-EPI 2021 ---
    let kappa = (gender === 'Female') ? 0.7 : 0.9;
    let alpha = (gender === 'Female') ? -0.241 : -0.302;
    let constant = (gender === 'Female') ? 1.012 : 1.0;

    egfr = 142 * Math.pow(Math.min(cr / kappa, 1), alpha) 
               * Math.pow(Math.max(cr / kappa, 1), -1.2) 
               * Math.pow(0.9938, age) 
               * constant;

    // ส่งค่าไปแสดงในช่อง gfr_input
    const gfrField = document.getElementById('gfr_input');
    if (gfrField) {
        gfrField.value = egfr.toFixed(2);
    }
});
    // 2. Toggle Sections Helper
    function setupToggle(radioName, targetId, showValue = 'YES') {
        const radios = document.querySelectorAll(`input[name="${radioName}"]`);
        const target = document.getElementById(targetId);
        
        const update = () => {
            const selected = document.querySelector(`input[name="${radioName}"]:checked`);
            if(target && selected) {
                target.style.display = (selected.value === showValue) ? 'block' : 'none';
            }
        };

        radios.forEach(r => r.addEventListener('change', update));
        update(); // Run on load
    }

    // 3. Setup Specific Toggles
    setupToggle('referral_type', 'referInputsContainer', 'Referral');
    setupToggle('fibrinolytic_status', 'fibrinolyticOptionsContainer', 'YES');
    setupToggle('diabetes', 'diabetes-extra', 'YES');
    setupToggle('dyslipidemia', 'dyslipidemia-extra', 'YES');
    setupToggle('dialysis', 'dialysis_options', 'YES');

    // 4. IPD Ward Toggle (Custom logic)
    const refRadios = document.querySelectorAll('input[name="referral_type"]');
    const wardDiv = document.getElementById('wardInputWrapper');
    const updateWard = () => {
        const sel = document.querySelector('input[name="referral_type"]:checked');
        wardDiv.style.display = (sel && sel.value === 'IPD') ? 'inline-block' : 'none';
    };
    refRadios.forEach(r => r.addEventListener('change', updateWard));
    updateWard(); // Run on load

    // 5. Lipid Checkbox Toggles
    document.querySelectorAll('.lipid-check').forEach(cb => {
        cb.addEventListener('change', function() {
            const input = document.getElementById(this.value.toLowerCase() + '_input');
            if(input) input.style.display = this.checked ? 'block' : 'none';
        });
    });

});

</script>
</body>
</html>