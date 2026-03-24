<?php
require 'connect.php';

// 1. รับค่า ID จาก URL หรือ POST
$patient_id = $_GET['id'] ?? $_POST['patient_id'] ?? '';

// 2. ส่วนการบันทึกข้อมูล (Integrated Logic)
// *** ต้องเช็คตรงนี้ว่ามีการกดปุ่มมาหรือไม่ (POST) ไม่งั้นโค้ดจะรันเองตอนเปิดหน้าเว็บ ***
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // เช็คความปลอดภัย Server-side อีกที (กันคนแอบยิง Request มาโดยไม่มี ID)
    if (empty($patient_id)) {
        echo "<script>alert('Error: Missing Patient ID'); window.history.back();</script>";
        exit;
    }

    $direction = $_POST['direction'] ?? 'next';

    try {
        $pdo->beginTransaction(); // เริ่ม Transaction เพื่อความปลอดภัย

        // ---------------------------------------------------------
        // A. บันทึกกลุ่มยาหลักลงตาราง `patient_medications`
        // ---------------------------------------------------------
        $asa_admin      = $_POST['admit_asa_status'] ?? null;
        $asa_dis        = $_POST['disch_asa_status'] ?? null;
        
        $p2y12_type_arr = $_POST['p2y12_type'] ?? [];
        $p2y12_specific = is_array($p2y12_type_arr) ? implode(',', $p2y12_type_arr) : $p2y12_type_arr;
        $p2y12_admin    = $_POST['admit_p2y12_status'] ?? null;
        $p2y12_dis      = $_POST['disch_p2y12_status'] ?? null;
        $bb_admin       = $_POST['admit_bb_status'] ?? null;
        $bb_dis         = $_POST['disch_bb_status'] ?? null;
        $acei_type_arr  = $_POST['acei_arb_type'] ?? [];
        $acei_admin     = $_POST['admit_acei_status'] ?? null;
        $acei_dis       = $_POST['disch_acei_status'] ?? null;
        $statin_admin   = $_POST['admit_statin_status'] ?? null;
        $statin_dis     = $_POST['disch_statin_status'] ?? null;

        $sql_main = "INSERT INTO patient_medications (
                        patient_id, asa_admin, asa_dis,
                        p2y12_specific, p2y12_admin, p2y12_dis,
                        bb_admin, bb_dis, acei_admin, acei_dis,
                        statin_admin, statin_dis
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        asa_admin = VALUES(asa_admin), asa_dis = VALUES(asa_dis),
                        p2y12_specific = VALUES(p2y12_specific), p2y12_admin = VALUES(p2y12_admin), p2y12_dis = VALUES(p2y12_dis),
                        bb_admin = VALUES(bb_admin), bb_dis = VALUES(bb_dis),
                        acei_admin = VALUES(acei_admin), acei_dis = VALUES(acei_dis),
                        statin_admin = VALUES(statin_admin), statin_dis = VALUES(statin_dis)";
        
        $stmt_main = $pdo->prepare($sql_main);
        $stmt_main->execute([
            $patient_id, $asa_admin, $asa_dis,
            $p2y12_specific, $p2y12_admin, $p2y12_dis,
            $bb_admin, $bb_dis, $acei_admin, $acei_dis,
            $statin_admin, $statin_dis
        ]);

        // ---------------------------------------------------------
        // B. บันทึก Medication Reconciliation
        // ---------------------------------------------------------
        $fields_recon = ['patient_id' => $patient_id];
        $med_keys = ['asa', 'p2y12', 'bb', 'acei', 'statin'];
        
        foreach ($med_keys as $key) {
            $type_key = ($key === 'acei') ? 'acei_arb_type' : "{$key}_type";
            $fields_recon["{$key}_type"] = !empty($_POST[$type_key]) ? implode(',', (array)$_POST[$type_key]) : null;
            $fields_recon["admit_{$key}_status"] = $_POST["admit_{$key}_status"] ?? $_POST['admit_p2y12'] ?? null;
            $fields_recon["disch_{$key}_status"] = $_POST["disch_{$key}_status"] ?? $_POST['disch_p2y12'] ?? null;
            $fields_recon["admit_{$key}_remark"] = $_POST["admit_{$key}_remark"] ?? null;
            $fields_recon["disch_{$key}_remark"] = $_POST["disch_{$key}_remark"] ?? null;
        }

        for ($i = 6; $i <= 18; $i++) {
            $fields_recon["admit_m{$i}_status"] = $_POST["admit_m{$i}_status"] ?? null;
            $fields_recon["admit_m{$i}_remark"] = $_POST["admit_m{$i}_remark"] ?? null;
            $fields_recon["disch_m{$i}_status"] = $_POST["disch_m{$i}_status"] ?? null;
            $fields_recon["disch_m{$i}_remark"] = $_POST["disch_m{$i}_remark"] ?? null;
            if ($i >= 14) { 
                $fields_recon["extra_m{$i}_name"] = $_POST["extra_m{$i}_name"] ?? null; 
            }
        }

        $cols = "`" . implode("`, `", array_keys($fields_recon)) . "`";
        $vals = ":" . implode(", :", array_keys($fields_recon));
        $sql_recon = "REPLACE INTO medication_reconciliation ($cols) VALUES ($vals)";
        $stmt_recon = $pdo->prepare($sql_recon);
        $stmt_recon->execute($fields_recon);

        $pdo->commit(); // ยืนยันการบันทึก

        // C. Redirect
        if ($direction === 'back') {
            header("Location: discharge.php?id=" . urlencode($patient_id));
        } else {
            header("Location: discharge.php?id=" . urlencode($patient_id));
        }
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<script>alert('Database Error: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// 3. ดึงข้อมูลมาแสดงผล (Sticky Form)
$med_data = []; // กำหนดค่าเริ่มต้นเป็น array ว่างเสมอ
if (!empty($patient_id)) {
    // ดึงข้อมูลเฉพาะตอนที่มี ID เท่านั้น
    $stmt = $pdo->prepare("SELECT * FROM medication_reconciliation WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    $med_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
// ฟังก์ชันช่วยเช็คค่า (Helper Functions)
function isChecked($data, $field, $value) {
    return (isset($data[$field]) && $data[$field] === $value) ? 'checked' : '';
}
function isMultiChecked($data, $field, $value) {
    $field_value = $data[$field] ?? [];
    $selected_values = is_array($field_value) ? $field_value : explode(',', $field_value);
    return in_array($value, $selected_values) ? 'checked' : '';
}

function getValue($data, $field) {
    return htmlspecialchars($data[$field] ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medication Reconciliation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f6f8f9; margin: 24px; }
        .med-table-container { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .section-title { font-weight: 600; margin-bottom: 16px; }
        .med-table { min-width: 900px; border-collapse: collapse; font-size: 14px; width: 100%; }
        .med-table th, .med-table td { border: 1px solid #dee2e6; padding: 8px; vertical-align: top; }
        .med-table thead th { background-color: #f0f3f5; text-align: center; }
        .med-list label { display: block; margin-bottom: 4px; font-size: 13px; }
        .med-list.flex-wrap label { display: inline-block; margin-right: 10px; }
        .input-sm-table { height: 28px; font-size: 13px; padding: 2px 6px; }
        .check-cell { text-align: center; width: 12.5%; }
        .med-table td:nth-child(3), .med-table td:nth-child(5) { white-space: nowrap; text-align: center; padding: 8px 4px; }
    </style>
</head>
<body>
<div class="container">
    <div class="med-table-container">
        <h6 class="section-title">💊 ยาที่ได้รับขณะ Admit / เมื่อ discharge</h6>
        
        <form action="" method="POST">
            <input type="hidden" name="patient_id" value="<?= htmlspecialchars($patient_id) ?>">
            
            <div class="table-responsive">
                <table class="med-table">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width:5%;">ลำดับ</th>
                            <th rowspan="2" style="width:45%;">รายการยา</th>
                            <th colspan="2" style="width:25%;">ขณะ Admit</th>
                            <th colspan="2" style="width:25%;">เมื่อ discharge</th>
                        </tr>
                        <tr>
                            <th class="check-cell">☐Yes/No</th>
                            <th class="check-cell">ระบุ</th>
                            <th class="check-cell">☐Yes/No</th>
                            <th class="check-cell">ระบุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-center">1</td>
                            <td>
                                <label class="fw-bold">ASA</label>
                                <div class="med-list">
                                    <label><input type="checkbox" name="asa_type[]" value="Aspirin" <?= isMultiChecked($med_data, 'asa_type', 'Aspirin') ?>> Aspirin</label>
                                    <label><input type="checkbox" name="asa_type[]" value="Aspent" <?= isMultiChecked($med_data, 'asa_type', 'Aspent') ?>> Aspent</label>
                                    <label><input type="checkbox" name="asa_type[]" value="Cardiprin" <?= isMultiChecked($med_data, 'asa_type', 'Cardiprin') ?>> Cardiprin</label>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <label class="m-0"><input type="radio" name="admit_asa_status" value="Yes" <?= isChecked($med_data, 'admit_asa_status', 'Yes') ?>> Yes</label>
                                    <span>/</span>
                                    <label class="m-0"><input type="radio" name="admit_asa_status" value="No" <?= isChecked($med_data, 'admit_asa_status', 'No') ?>> No</label>
                                </div>
                            </td>
                            <td><input type="text" name="admit_asa_remark" value="<?= getValue($med_data, 'admit_asa_remark') ?>" class="form-control input-sm-table" placeholder="ระบุ......."></td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <label class="m-0"><input type="radio" name="disch_asa_status" value="Yes" <?= isChecked($med_data, 'disch_asa_status', 'Yes') ?>> Yes</label>
                                    <span>/</span>
                                    <label class="m-0"><input type="radio" name="disch_asa_status" value="No" <?= isChecked($med_data, 'disch_asa_status', 'No') ?>> No</label>
                                </div>
                            </td>
                            <td><input type="text" name="disch_asa_remark" value="<?= getValue($med_data, 'disch_asa_remark') ?>" class="form-control input-sm-table" placeholder="ระบุ......."></td>
                        </tr>

                        <tr>
                            <td class="text-center">2</td>
                            <td>
                                <label class="fw-bold">P2Y12 inhibitors</label>
                                <div class="med-list">
                                    <label><input type="checkbox" name="p2y12_type[]" value="Clopidogrel" <?= isMultiChecked($med_data, 'p2y12_type', 'Clopidogrel') ?>> Clopidogrel</label>
                                    <label><input type="checkbox" name="p2y12_type[]" value="Ticagrelor" <?= isMultiChecked($med_data, 'p2y12_type', 'Ticagrelor') ?>> Ticagrelor (Brilinta®)</label>
                                    <label><input type="checkbox" name="p2y12_type[]" value="Prasugrel" <?= isMultiChecked($med_data, 'p2y12_type', 'Prasugrel') ?>> Prasugrel (Effient®)</label>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <label class="m-0"><input type="radio" name="admit_p2y12" value="Yes" <?= isChecked($med_data, 'admit_p2y12', 'Yes') ?>> Yes</label>
                                    <span>/</span>
                                    <label class="m-0"><input type="radio" name="admit_p2y12" value="No" <?= isChecked($med_data, 'admit_p2y12', 'No') ?>> No</label>
                                </div>
                            </td>
                            <td><input type="text" name="admit_p2y12_remark" value="<?= getValue($med_data, 'admit_p2y12_remark') ?>" class="form-control input-sm-table" placeholder="ระบุ......."></td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <label class="m-0"><input type="radio" name="disch_p2y12" value="Yes" <?= isChecked($med_data, 'disch_p2y12', 'Yes') ?>> Yes</label>
                                    <span>/</span>
                                    <label class="m-0"><input type="radio" name="disch_p2y12" value="No" <?= isChecked($med_data, 'disch_p2y12', 'No') ?>> No</label>
                                </div>
                            </td>
                            <td><input type="text" name="disch_p2y12_remark" value="<?= getValue($med_data, 'disch_p2y12_remark') ?>" class="form-control input-sm-table" placeholder="ระบุ......."></td>
                        </tr>

                        <tr>
                            <td class="text-center">3</td>
                            <td>
                                <label class="fw-bold">Beta blocker</label>
                                <div class="med-list d-flex flex-wrap gap-2">
                                    <label><input type="checkbox" name="bb_type[]" value="Carvedilol" <?= isMultiChecked($med_data, 'bb_type', 'Carvedilol') ?>> Carvedilol</label>
                                    <label><input type="checkbox" name="bb_type[]" value="Metoprolol" <?= isMultiChecked($med_data, 'bb_type', 'Metoprolol') ?>> Metoprolol</label>
                                    <label><input type="checkbox" name="bb_type[]" value="Bisoprolol" <?= isMultiChecked($med_data, 'bb_type', 'Bisoprolol') ?>> Bisoprolol</label>
                                    <label><input type="checkbox" name="bb_type[]" value="Atenolol" <?= isMultiChecked($med_data, 'bb_type', 'Atenolol') ?>> Atenolol</label>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <label class="m-0"><input type="radio" name="admit_bb_status" value="Yes" <?= isChecked($med_data, 'admit_bb_status', 'Yes') ?>> Yes</label>
                                    <span>/</span>
                                    <label class="m-0"><input type="radio" name="admit_bb_status" value="No" <?= isChecked($med_data, 'admit_bb_status', 'No') ?>> No</label>
                                </div>
                            </td>
                            <td><input type="text" name="admit_bb_remark" value="<?= getValue($med_data, 'admit_bb_remark') ?>" class="form-control input-sm-table" placeholder="ระบุ......."></td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <label class="m-0"><input type="radio" name="disch_bb_status" value="Yes" <?= isChecked($med_data, 'disch_bb_status', 'Yes') ?>> Yes</label>
                                    <span>/</span>
                                    <label class="m-0"><input type="radio" name="disch_bb_status" value="No" <?= isChecked($med_data, 'disch_bb_status', 'No') ?>> No</label>
                                </div>
                            </td>
                            <td><input type="text" name="disch_bb_remark" value="<?= getValue($med_data, 'disch_bb_remark') ?>" class="form-control input-sm-table" placeholder="ระบุ......."></td>
                        </tr>

                        <tr>
                            <td class="text-center">4</td>
                            <td>
                                <label class="fw-bold">ACEI / ARB</label>
                                <div class="med-list">
                                    <label><input type="checkbox" name="acei_arb_type[]" value="ACEI" <?= isMultiChecked($med_data, 'acei_arb_type', 'ACEI') ?>> ACEI (เช่น Enalapril)</label>
                                    <label><input type="checkbox" name="acei_arb_type[]" value="ARB" <?= isMultiChecked($med_data, 'acei_arb_type', 'ARB') ?>> ARB (เช่น Losartan)</label>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <label class="m-0"><input type="radio" name="admit_acei_status" value="Yes" <?= isChecked($med_data, 'admit_acei_status', 'Yes') ?>> Yes</label>
                                    <span>/</span>
                                    <label class="m-0"><input type="radio" name="admit_acei_status" value="No" <?= isChecked($med_data, 'admit_acei_status', 'No') ?>> No</label>
                                </div>
                            </td>
                            <td><input type="text" name="admit_acei_remark" value="<?= getValue($med_data, 'admit_acei_remark') ?>" class="form-control input-sm-table" placeholder="ระบุ......."></td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <label class="m-0"><input type="radio" name="disch_acei_status" value="Yes" <?= isChecked($med_data, 'disch_acei_status', 'Yes') ?>> Yes</label>
                                    <span>/</span>
                                    <label class="m-0"><input type="radio" name="disch_acei_status" value="No" <?= isChecked($med_data, 'disch_acei_status', 'No') ?>> No</label>
                                </div>
                            </td>
                            <td><input type="text" name="disch_acei_remark" value="<?= getValue($med_data, 'disch_acei_remark') ?>" class="form-control input-sm-table" placeholder="ระบุ......."></td>
                        </tr>

                        <tr>
                            <td class="text-center">5</td>
                            <td>
                                <label class="fw-bold">Statins</label>
                                <div class="med-list d-flex flex-wrap gap-2">
                                    <label><input type="checkbox" name="statin_type[]" value="Simvastatin" <?= isMultiChecked($med_data, 'statin_type', 'Simvastatin') ?>> Simvastatin</label>
                                    <label><input type="checkbox" name="statin_type[]" value="Atorvastatin" <?= isMultiChecked($med_data, 'statin_type', 'Atorvastatin') ?>> Atorvastatin</label>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <label class="m-0"><input type="radio" name="admit_statin_status" value="Yes" <?= isChecked($med_data, 'admit_statin_status', 'Yes') ?>> Yes</label>
                                    <span>/</span>
                                    <label class="m-0"><input type="radio" name="admit_statin_status" value="No" <?= isChecked($med_data, 'admit_statin_status', 'No') ?>> No</label>
                                </div>
                            </td>
                            <td><input type="text" name="admit_statin_remark" value="<?= getValue($med_data, 'admit_statin_remark') ?>" class="form-control input-sm-table" placeholder="ระบุ......."></td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <label class="m-0"><input type="radio" name="disch_statin_status" value="Yes" <?= isChecked($med_data, 'disch_statin_status', 'Yes') ?>> Yes</label>
                                    <span>/</span>
                                    <label class="m-0"><input type="radio" name="disch_statin_status" value="No" <?= isChecked($med_data, 'disch_statin_status', 'No') ?>> No</label>
                                </div>
                            </td>
                            <td><input type="text" name="disch_statin_remark" value="<?= getValue($med_data, 'disch_statin_remark') ?>" class="form-control input-sm-table" placeholder="ระบุ......."></td>
                        </tr>

                        <?php
                        $standard_meds = [
                            6  => "ยาอื่นลดระดับไขมัน (Ezetimibe)",
                            7  => "Warfarin",
                            8  => "NOAC",
                            9  => "Nitrates",
                            10 => "Heparin/LMWH",
                            11 => "Omeprazole",
                            12 => "Lorazepam",
                            13 => "Senokot"
                        ];

                        foreach ($standard_meds as $i => $label): ?>
                        <tr>
                            <td class="text-center"><?= $i ?></td>
                            <td><label class="fw-bold"><?= $label ?></label></td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <label class="m-0"><input type="radio" name="admit_m<?= $i ?>_status" value="Yes" <?= isChecked($med_data, "admit_m$i" . "_status", 'Yes') ?>> Yes</label>
                                    <span>/</span>
                                    <label class="m-0"><input type="radio" name="admit_m<?= $i ?>_status" value="No" <?= isChecked($med_data, "admit_m$i" . "_status", 'No') ?>> No</label>
                                </div>
                            </td>
                            <td><input type="text" name="admit_m<?= $i ?>_remark" value="<?= getValue($med_data, "admit_m$i" . "_remark") ?>" class="form-control input-sm-table" placeholder="ระบุ..."></td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <label class="m-0"><input type="radio" name="disch_m<?= $i ?>_status" value="Yes" <?= isChecked($med_data, "disch_m$i" . "_status", 'Yes') ?>> Yes</label>
                                    <span>/</span>
                                    <label class="m-0"><input type="radio" name="disch_m<?= $i ?>_status" value="No" <?= isChecked($med_data, "disch_m$i" . "_status", 'No') ?>> No</label>
                                </div>
                            </td>
                            <td><input type="text" name="disch_m<?= $i ?>_remark" value="<?= getValue($med_data, "disch_m$i" . "_remark") ?>" class="form-control input-sm-table" placeholder="ระบุ..."></td>
                        </tr>
                        <?php endforeach; ?>

                        <?php for ($i = 14; $i <= 18; $i++): ?>
                        <tr>
                            <td class="text-center"><?= $i ?></td>
                            <td>
                                <?php if($i == 14): ?><label class="fw-bold d-block">ยาอื่น ๆ</label><?php endif; ?>
                                <input type="text" name="extra_m<?= $i ?>_name" value="<?= getValue($med_data, "extra_m$i" . "_name") ?>" class="form-control input-sm-table" placeholder="ชื่อยา...">
                            </td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <label class="m-0"><input type="radio" name="admit_m<?= $i ?>_status" value="Yes" <?= isChecked($med_data, "admit_m$i" . "_status", 'Yes') ?>> Yes</label>
                                    <span>/</span>
                                    <label class="m-0"><input type="radio" name="admit_m<?= $i ?>_status" value="No" <?= isChecked($med_data, "admit_m$i" . "_status", 'No') ?>> No</label>
                                </div>
                            </td>
                            <td><input type="text" name="admit_m<?= $i ?>_remark" value="<?= getValue($med_data, "admit_m$i" . "_remark") ?>" class="form-control input-sm-table" placeholder="ระบุ..."></td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <label class="m-0"><input type="radio" name="disch_m<?= $i ?>_status" value="Yes" <?= isChecked($med_data, "disch_m$i" . "_status", 'Yes') ?>> Yes</label>
                                    <span>/</span>
                                    <label class="m-0"><input type="radio" name="disch_m<?= $i ?>_status" value="No" <?= isChecked($med_data, "disch_m$i" . "_status", 'No') ?>> No</label>
                                </div>
                            </td>
                            <td><input type="text" name="disch_m<?= $i ?>_remark" value="<?= getValue($med_data, "disch_m$i" . "_remark") ?>" class="form-control input-sm-table" placeholder="ระบุ..."></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            
           <div class="d-flex justify-content-between my-4">
    <button type="submit" name="direction" value="back" class="btn btn-secondary px-4"> BACK</button>
    
    <?php if(!empty($patient_id)): ?>
        <button type="submit" name="direction" value="next" class="btn btn-success px-5">SAVE & NEXT</button>
    <?php else: ?>
        <button type="button" class="btn btn-secondary px-5" disabled>
            SAVE & NEXT (กรุณาระบุ ID)
        </button>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const textInput = this.closest('tr').querySelector('input[type="text"]');
            if (this.value === 'Yes' && textInput) {
                textInput.focus();
            }
        });
    });
</script>
</body>
</html>