<?php
session_start();
require 'connect.php';

// 1. ตรวจสอบ Patient ID
$patient_id = $_GET['id'] ?? '';
if (empty($patient_id)) {
    die("<div style='text-align:center; margin-top:50px; color:red; font-family:sans-serif;'>ERROR: ไม่พบรหัสผู้ป่วย (Patient ID)</div>");
}

// --------------------------------------------------------------------------------
// 2. ดึงข้อมูลจากทุกตาราง (Query All Data)
// --------------------------------------------------------------------------------

// 2.1 ข้อมูลพื้นฐาน (Patients)
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$pt = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// 2.2 ปัจจัยเสี่ยง (Risk Factors)
$stmt = $pdo->prepare("SELECT * FROM patient_risk_factors WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$risk = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// 2.3 อาการและการวินิจฉัย (Symptoms & Diagnosis)
$stmt = $pdo->prepare("SELECT * FROM symptoms_diagnosis WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$symp = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// 2.4 สวนหัวใจ (Cath Lab)
$stmt = $pdo->prepare("SELECT * FROM cardiac_cath WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$cath = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// 2.5 ผลการรักษา (Treatment Results)
$stmt = $pdo->prepare("SELECT * FROM treatment_results WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$res = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// 2.6 ยา (Acute & Admit)
$stmt = $pdo->prepare("SELECT * FROM patient_medications WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$med = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// 2.7 ยากลับบ้าน (Discharge Meds)
$stmt = $pdo->prepare("SELECT * FROM medication_reconciliation WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$med_rec = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// 2.8 การจำหน่าย (Discharge)
$stmt = $pdo->prepare("SELECT * FROM patient_discharges WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$dis = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// --------------------------------------------------------------------------------
// 3. Helper Functions (ตัวช่วยป้องกัน Error และจัด Format)
// --------------------------------------------------------------------------------
function show($val, $suffix = '') {
    if (!isset($val) || $val === '' || $val === null) return '-';
    return htmlspecialchars($val) . $suffix;
}

function showCheck($val) {
    if (!isset($val) || $val === '') return '-';
    // แสดงเป็น Text เพื่อให้ Excel อ่านง่าย
    if ($val === 'Yes' || $val === '1' || $val === 'true') return 'Yes';
    if ($val === 'No' || $val === '0' || $val === 'false') return 'No';
    return '-';
}

function formatDateTime($date, $time) {
    $d = ($date && $date != '0000-00-00') ? date('d/m/Y', strtotime($date)) : '-';
    $t = ($time && $time != '00:00:00') ? substr($time, 0, 5) : '';
    if ($d == '-' && $t == '') return '-';
    return trim("$d $t");
}

// --------------------------------------------------------------------------------
// 4. Export Excel Logic
// --------------------------------------------------------------------------------
$is_export = isset($_GET['export']) && $_GET['export'] == 'excel';

if ($is_export) {
    $filename = "STEMI_Report_" . ($pt['hospital_code'] ?? 'Unknown') . ".xls";
    
    // Header สำหรับไฟล์ Excel (.xls)
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // BOM เพื่อให้ Excel รองรับภาษาไทย
    echo "\xEF\xBB\xBF"; 
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Report: <?= show($pt['firstname'] ?? '') ?></title>
    
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
        .table-report { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table-report th, .table-report td { border: 1px solid #000; padding: 5px; vertical-align: top; }
        .section-header { background-color: #cce5ff; font-weight: bold; text-align: left; padding: 10px; font-size: 16px; border: 1px solid #000; }
        .sub-header { background-color: #f2f2f2; font-weight: bold; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .text-success { color: #006400; font-weight: bold; }
        .text-danger { color: #8B0000; font-weight: bold; }
        
        /* ซ่อนปุ่มเมื่อ Export หรือ Print */
        @media print { .no-print { display: none; } }
    </style>
    
    <?php if (!$is_export): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body>

<?php if (!$is_export): ?>
<div class="container mt-4 mb-4 no-print">
    <div class="card shadow-sm">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h4 class="m-0 text-primary">📄 รายงานข้อมูลผู้ป่วย (Medical Record)</h4>
            <div>
                <a href="index.php" class="btn btn-secondary me-2">🔙 กลับหน้าหลัก</a>
                <a href="report_summary.php?id=<?= $patient_id ?>&export=excel" target="_blank" class="btn btn-success me-2">
                    📊 ดาวน์โหลด Excel
                </a>
                <button onclick="window.print()" class="btn btn-primary">🖨️ พิมพ์</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div style="padding: 20px;">

    <div style="text-align: center; margin-bottom: 20px;">
        <h2 style="margin:0;">STEMI Registry Case Record Form</h2>
        <p style="margin:0; color: #555;">Division of Adult Cardiology</p>
    </div>

    <table class="table-report">
        
        <tr>
            <td colspan="4" class="section-header">1. ข้อมูลทั่วไป (Demographics)</td>
        </tr>
        <tr>
            <td width="20%" class="sub-header">HN</td>
            <td width="30%"><?= show($pt['hospital_code'] ?? '') ?></td>
            <td width="20%" class="sub-header">AN</td>
            <td width="30%"><?= show($pt['admission_number'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="sub-header">ชื่อ-นามสกุล</td>
            <td><?= show($pt['firstname'] ?? '') ?> <?= show($pt['lastname'] ?? '') ?></td>
            <td class="sub-header">เลขบัตรประชาชน</td>
            <td>'<?= show($pt['citizen_id'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="sub-header">เพศ / อายุ</td>
            <td><?= show($pt['gender'] ?? '') ?> / <?= show($pt['age'] ?? '') ?> ปี</td>
            <td class="sub-header">สิทธิการรักษา</td>
            <td><?= show($pt['treatment_right'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="sub-header">ที่อยู่</td>
            <td><?= show($pt['province_code'] ?? '') ?> (Zone: <?= show($pt['health_zone'] ?? '') ?>)</td>
            <td class="sub-header">เบอร์โทรศัพท์</td>
            <td><?= show($pt['mobile_phone'] ?? '-') ?></td>
        </tr>

        <tr>
            <td colspan="4" class="section-header">2. ประวัติและปัจจัยเสี่ยง (History & Risks)</td>
        </tr>
        <tr>
            <td class="sub-header">รูปแบบการมา รพ.</td>
            <td colspan="3">
                <?= show($risk['referral_type'] ?? '') ?> 
                <?php if(($risk['referral_type']??'')=='Referral') echo "(Source: " . show($risk['referral_source'] ?? '-') . ")"; ?>
            </td>
        </tr>
        <tr>
            <td class="sub-header">ข้อมูลร่างกาย</td>
            <td colspan="3">
                BW: <?= show($risk['weight'] ?? '') ?> kg, 
                Height: <?= show($risk['height'] ?? '') ?> cm, 
                BMI: <?= show($risk['bmi'] ?? '') ?>
            </td>
        </tr>
        <tr>
            <td class="sub-header">โรคประจำตัว</td>
            <td colspan="3">
                DM: <?= showCheck($risk['diabetes'] ?? '') ?> | 
                HT: <?= showCheck($risk['hypertension'] ?? '') ?> | 
                DLP: <?= showCheck($risk['dyslipidemia'] ?? '') ?> | 
                Smoking: <?= showCheck($risk['smoker'] ?? '') ?>
            </td>
        </tr>
        <tr>
            <td class="sub-header">ประวัติโรคหัวใจ</td>
            <td colspan="3">
                Family Hx: <?= showCheck($risk['family_history'] ?? '') ?> | 
                Prior MI: <?= showCheck($risk['prior_mi'] ?? '') ?> | 
                Prior PCI: <?= showCheck($risk['prior_pci'] ?? '') ?> | 
                Prior CABG: <?= showCheck($risk['prior_cabg'] ?? '') ?>
            </td>
        </tr>
        <tr>
            <td class="sub-header">ภาวะแทรกซ้อนเดิม</td>
            <td colspan="3">
                HF: <?= showCheck($risk['prior_hf'] ?? '') ?> | 
                Stroke: <?= showCheck($risk['prior_stroke'] ?? '') ?> | 
                CKD: <?= showCheck($risk['ckd'] ?? '') ?> | 
                Dialysis: <?= showCheck($risk['on_dialysis'] ?? '') ?>
            </td>
        </tr>

        <tr>
            <td colspan="4" class="section-header">3. อาการและการวินิจฉัย (Clinical Presentation)</td>
        </tr>
        <tr>
            <td class="sub-header">Onset Date/Time</td>
            <td><?= formatDateTime($symp['onset_date']??'', $symp['onset_time']??'') ?></td>
            <td class="sub-header">Hospital Arrival</td>
            <td><?= formatDateTime($symp['hospital_date_hatyai']??'', $symp['hospital_time_hatyai']??'') ?></td>
        </tr>
        <tr>
            <td class="sub-header">First ECG Time</td>
            <td><?= formatDateTime($symp['diag_ekg_date']??'', $symp['diag_ekg_time']??'') ?></td>
            <td class="sub-header">Vital Signs (ER)</td>
            <td>
                BP: <?= show($symp['systolic_bp']??'') ?>/<?= show($symp['diastolic_bp']??'') ?>, 
                HR: <?= show($symp['heart_rate']??'') ?>, 
                O2 Sat: <?= show($symp['o2_sat']??'') ?>%
            </td>
        </tr>
        <tr>
            <td class="sub-header">Diagnosis</td>
            <td class="text-danger text-bold"><?= show($symp['initial_diagnosis_main']??'') ?></td>
            <td class="sub-header">Killip Class</td>
            <td><?= show($symp['killip_class']??'') ?></td>
        </tr>
        <tr>
            <td class="sub-header">EKG Findings</td>
            <td colspan="3">
                Anterior: <?= showCheck($symp['infarction_anterior']??'') ?>, 
                Inferior: <?= showCheck($symp['infarction_inferior']??'') ?>, 
                Lateral: <?= showCheck($symp['infarction_lateral']??'') ?>, 
                Posterior: <?= showCheck($symp['infarction_posterior']??'') ?>, 
                RV Infarct: <?= showCheck($symp['infarction_rv_infarction']??'') ?>
            </td>
        </tr>

        <tr>
            <td colspan="4" class="section-header">4. ห้องสวนหัวใจ (Cath Lab & Intervention)</td>
        </tr>
        <tr>
            <td class="sub-header">CAG Date/Time</td>
            <td><?= formatDateTime($cath['cag_date']??'', $cath['cag_time']??'') ?></td>
            <td class="sub-header">Access Site</td>
            <td><?= show($cath['access_site']??'') ?></td>
        </tr>
        <tr>
            <td class="sub-header">Coronary Findings</td>
            <td colspan="3">
                LMS: <?= show($cath['lm_stenosis']??'') ?>% | 
                LAD: <?= show($cath['lad_stenosis']??'') ?>% | 
                LCx: <?= show($cath['lcx_stenosis']??'') ?>% | 
                RCA: <?= show($cath['rca_stenosis']??'') ?>%
                (Dominance: <?= show($cath['coronary_dominance']??'') ?>)
            </td>
        </tr>
        <tr>
            <td class="sub-header">PCI Procedure</td>
            <td>
                Status: <?= show($cath['pci_status']??'No') ?><br>
                Indication: <?= show($cath['pci_indication']??'-') ?>
            </td>
            <td class="sub-header">KPI (Door to Device)</td>
            <td class="text-bold"><?= show($cath['door_to_device']??'-') ?> min</td>
        </tr>
        <tr>
            <td class="sub-header">Procedure Details</td>
            <td colspan="3">
                Contrast: <?= show($cath['contrast_volume']??'') ?> ml | 
                Fluoro Time: <?= show($cath['fluoro_time']??'') ?> min<br>
                Devices Used: <?= show($cath['devices_used']??'-') ?>
            </td>
        </tr>

        <tr>
            <td colspan="4" class="section-header">5. การให้ยา (Medications)</td>
        </tr>
        <tr>
            <td colspan="4">
                <table class="table-report" style="margin:0;">
                    <tr class="sub-header text-center">
                        <td>Drug Name</td>
                        <td>Acute / Loading</td>
                        <td>Hospital / Admit</td>
                        <td>Discharge</td>
                    </tr>
                    <tr>
                        <td>Aspirin</td>
                        <td class="text-center"><?= showCheck($med['loading_asa_status']??'') ?></td>
                        <td class="text-center"><?= showCheck($med['admit_asa_status']??'') ?></td>
                        <td class="text-center"><?= showCheck($med['disch_asa_status']??'') ?></td>
                    </tr>
                    <tr>
                        <td>P2Y12 Inhibitor</td>
                        <td class="text-center"><?= show($med['loading_p2y12_specific']??'-') ?></td>
                        <td class="text-center"><?= show($med['admit_p2y12_specific']??'-') ?></td>
                        <td class="text-center"><?= show($med_rec['p2y12_type']??'-') ?></td>
                    </tr>
                    <tr>
                        <td>Anticoagulant</td>
                        <td class="text-center">-</td>
                        <td class="text-center"><?= show($med['admit_anticoagulant_specific']??'-') ?></td>
                        <td class="text-center"><?= show($med_rec['disch_anticoagulant_type']??'-') ?></td>
                    </tr>
                    <tr>
                        <td>Statin</td>
                        <td class="text-center">-</td>
                        <td class="text-center"><?= showCheck($med['admit_statin_status']??'') ?></td>
                        <td class="text-center"><?= showCheck($med_rec['disch_statin_status']??'') ?></td>
                    </tr>
                    <tr>
                        <td>ACEI / ARB</td>
                        <td class="text-center">-</td>
                        <td class="text-center"><?= showCheck($med['admit_acei_status']??'') ?></td>
                        <td class="text-center"><?= showCheck($med_rec['disch_acei_status']??'') ?></td>
                    </tr>
                    <tr>
                        <td>Beta Blocker</td>
                        <td class="text-center">-</td>
                        <td class="text-center"><?= showCheck($med['admit_bb_status']??'') ?></td>
                        <td class="text-center"><?= showCheck($med_rec['disch_bb_status']??'') ?></td>
                    </tr>
                    <tr>
                        <td>Fibrinolytic</td>
                        <td colspan="3"><?= show($med['fibrinolytic_drug']??'-') ?> (Dose: <?= show($med['fibrinolytic_dose']??'-') ?>)</td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td colspan="4" class="section-header">6. ผลลัพธ์และภาวะแทรกซ้อน (Outcomes & Complications)</td>
        </tr>
        <tr>
            <td class="sub-header">Echocardiogram</td>
            <td colspan="3">LVEF: <b><?= show($res['ef_value']??'') ?>%</b> (Date: <?= formatDateTime($res['echo_date']??'', '') ?>)</td>
        </tr>
        <tr>
            <td class="sub-header">Complications</td>
            <td colspan="3">
                Heart Failure: <?= showCheck($res['heart_failure']??'') ?> | 
                Shock: <?= showCheck($res['cardiogenic_shock']??'') ?> | 
                Bleeding: <?= showCheck($res['bleeding']??'') ?> (Site: <?= show($res['bleeding_site']??'-') ?>) | 
                Stroke: <?= showCheck($res['stroke']??'') ?>
            </td>
        </tr>
        <tr>
            <td class="sub-header">Arrhythmias</td>
            <td colspan="3">
                VF/VT: <?= showCheck($res['arrhythmia_vf_vt']??'') ?> | 
                Atrial Fib: <?= showCheck($res['arrhythmia_af']??'') ?> | 
                Heart Block: <?= showCheck($res['arrhythmia_heart_block']??'') ?>
            </td>
        </tr>
        <tr>
            <td class="sub-header">Interventions</td>
            <td colspan="3">
                Ventilator: <?= showCheck($res['ventilator']??'') ?> | 
                IABP: <?= showCheck($res['iabp']??'') ?> | 
                Dialysis: <?= showCheck($res['dialysis_acute']??'') ?>
            </td>
        </tr>

        <tr>
            <td colspan="4" class="section-header">7. การจำหน่าย (Discharge)</td>
        </tr>
        <tr>
            <td class="sub-header">Discharge Date/Time</td>
            <td><?= formatDateTime($dis['discharge_date']??'', $dis['discharge_time']??'') ?></td>
            <td class="sub-header">Length of Stay (LOS)</td>
            <td><?= show($dis['length_days']??'0') ?> days <?= show($dis['length_hours']??'0') ?> hours</td>
        </tr>
        <tr>
            <td class="sub-header">Discharge Status</td>
            <td class="<?= ($dis['dis_status']??'')=='Dead' ? 'text-danger' : 'text-success' ?>">
                <?= show($dis['dis_status']??'') ?>
                <?php if(($dis['dis_status']??'')=='Dead') echo "<br>(Cause: ".show($dis['death_cause_list']??'-').")"; ?>
            </td>
            <td class="sub-header">Hospital Cost</td>
            <td><?= number_format((float)($dis['hospital_cost']??0), 2) ?> THB</td>
        </tr>
        <tr>
            <td class="sub-header">Final Diagnosis</td>
            <td colspan="3">
                <?= show($dis['final_diagnosis']??'') ?> 
                (ICD-10: <b><?= show($dis['icd_code']??'') ?></b>)
            </td>
        </tr>
        <tr>
            <td class="sub-header">Follow-up Plan</td>
            <td colspan="3">
                Date 1: <?= formatDateTime($dis['fup1_date']??'', '') ?> (<?= show($dis['fup1_detail']??'-') ?>)<br>
                Date 2: <?= formatDateTime($dis['fup2_date']??'', '') ?>
            </td>
        </tr>
        <tr>
            <td class="sub-header">Discharge Notes</td>
            <td colspan="3"><?= show($dis['dis_notes']??'') ?></td>
        </tr>

    </table>

    <div style="text-align:right; font-size:12px; color:#666; margin-top:20px;">
        Printed by: <?= $_SESSION['user'] ?? 'Guest' ?> | Date: <?= date('d/m/Y H:i') ?>
    </div>

</div>

</body>
</html>