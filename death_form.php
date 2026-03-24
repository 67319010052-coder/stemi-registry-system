<?php
require 'connect.php';

// 1. รับค่า HN (Patient ID)
// ใช้ชื่อตัวแปร $patient_id เพื่อให้ HTML ด้านล่าง (บรรทัด 123, 134) เรียกใช้ได้ไม่ Error
$patient_id = $_GET['id'] ?? $_POST['patient_id'] ?? '';

// ตัวแปรสำหรับแสดงผล Default
$patient_name = 'ไม่ระบุชื่อ';
$old_data = [];

// --- ส่วนที่ 2: ดึงชื่อและข้อมูลเก่ามาโชว์ (Load Data) ---
if ($patient_id) {
    // หา ID จริงจาก HN ก่อน
    $stmt_pt = $pdo->prepare("SELECT id, firstname, lastname FROM patients WHERE hn = ?");
    $stmt_pt->execute([$patient_id]);
    $patient = $stmt_pt->fetch(PDO::FETCH_ASSOC);

    if ($patient) {
        $patient_name = $patient['firstname'] . ' ' . $patient['lastname'];
        $real_id = $patient['id']; // ได้ ID จริงมาแล้ว (เช่น 51)

        // ดึงข้อมูลการตายโดยใช้ ID จริง
        $stmt_old = $pdo->prepare("SELECT * FROM patient_discharges WHERE patient_id = ?");
        $stmt_old->execute([$real_id]);
        $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}

// เตรียมตัวแปรลงฟอร์ม (ถ้าไม่มีให้เป็นค่าว่าง หรือค่าปัจจุบัน)
$discharge_date = $old_data['discharge_date'] ?? date('Y-m-d');
$discharge_time = $old_data['discharge_time'] ?? date('H:i');
$ds_dead_cause  = $old_data['ds_dead_cause'] ?? '';
$dis_notes      = $old_data['dis_notes'] ?? '';
$death_cause_list = !empty($old_data['death_cause_list']) ? explode(',', $old_data['death_cause_list']) : [];

// --- ส่วนที่ 3: บันทึกข้อมูล (Save Logic) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่า HN จากฟอร์ม (ใส่ ?? '' กัน Error กรณีไม่มีค่าส่งมา)
    $hn_post = $_POST['patient_id'] ?? '';
    
    if ($hn_post) {
        // 1. แปลง HN เป็น ID ก่อนบันทึก
        $stmt_check = $pdo->prepare("SELECT id FROM patients WHERE hn = ?");
        $stmt_check->execute([$hn_post]);
        $row_check = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($row_check) {
            $save_id = $row_check['id']; // ใช้ ID นี้บันทึก (เช่น 51)

            try {
                $cause_arr = $_POST['death_cause'] ?? [];
                $cause_str = implode(',', $cause_arr);

                $sql = "INSERT INTO patient_discharges (
                            patient_id, discharge_date, discharge_time, 
                            dis_status, ds_dead_cause, death_cause_list, dis_notes
                        ) VALUES (
                            :id, :ddate, :dtime, 'Dead', :dcause, :dlist, :dnote
                        ) ON DUPLICATE KEY UPDATE 
                            discharge_date = VALUES(discharge_date),
                            discharge_time = VALUES(discharge_time),
                            dis_status = 'Dead',
                            ds_dead_cause = VALUES(ds_dead_cause),
                            death_cause_list = VALUES(death_cause_list),
                            dis_notes = VALUES(dis_notes)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id'     => $save_id, // บันทึกเป็น ID (51)
                    ':ddate'  => $_POST['discharge_date'],
                    ':dtime'  => $_POST['discharge_time'],
                    ':dcause' => $_POST['ds_dead_cause'],
                    ':dlist'  => $cause_str,
                    ':dnote'  => $_POST['dis_notes']
                ]);

                $success_msg = "บันทึกข้อมูลการเสียชีวิตเรียบร้อยแล้ว";

            } catch (PDOException $e) {
                $error_msg = "Database Error: " . $e->getMessage();
            }
        } else {
            $error_msg = "ไม่พบเลข HN นี้ในระบบทะเบียนคนไข้ (กรุณาเพิ่มคนไข้ก่อน)";
        }
    } else {
        $error_msg = "ไม่พบข้อมูล HN กรุณาลองใหม่อีกครั้ง";
    }
}

// Helper เช็คค่า Checkbox
function isChecked($arr, $val) {
    return in_array($val, $arr) ? 'checked' : '';
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Death Record - <?= htmlspecialchars($patient_id) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { background-color: #fff5f5; font-family: 'Sarabun', sans-serif; margin-top: 24px; }
        .top-bar { background: #fff; padding: 18px; border-radius: 8px; margin-bottom: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .form-section { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 20px; border-top: 5px solid #dc3545; }
        .section-header { font-weight: bold; color: #dc3545; border-bottom: 1px solid #ffccd0; padding-bottom: 10px; margin-bottom: 20px; }
        .cause-group-title { font-size: 0.95rem; font-weight: bold; color: #495057; background-color: #f8f9fa; padding: 8px 12px; border-radius: 6px; margin-top: 15px; margin-bottom: 10px; }
        
        /* Custom Checkbox Style */
        .form-check-input:checked { background-color: #dc3545; border-color: #dc3545; }
    </style>
</head>
<body>

<div class="container pb-5">
    
   <div class="top-bar d-flex justify-content-between align-items-center">
    <div class="fw-bold text-danger fs-5">
         <i class="bi bi-heartbreak-fill"></i> บันทึกข้อมูลการเสียชีวิต (Death Record)
    </div>
    <div>
        <span class="badge bg-danger fs-6 me-2">STATUS: DECEASED</span>
        <span class="badge bg-secondary fs-6">HN: <span id="display_hn"><?= htmlspecialchars($patient_id) ?></span></span>
        <span class="ms-2 fw-bold text-secondary" id="display_patient_name">
            <?= htmlspecialchars($patient_name) ?>
        </span>
    </div>
</div>

<div class="mb-3">
    <label class="form-label fw-bold">ระบุ HN (Patient ID)</label>
    <div class="input-group">
        <input type="text" name="patient_id" id="patient_id" class="form-control" 
               value="<?= htmlspecialchars($patient_id) ?>" placeholder="กรอกเลข HN..." required>
        <button class="btn btn-primary" type="button" id="btn-search-patient">
            <i class="bi bi-search"></i> ค้นหา
        </button>
    </div>
</div>

    

    <form method="post">
        
        <div class="row">
            <div class="col-lg-5">
                <div class="form-section h-100">
                    <h5 class="section-header"><i class="bi bi-clock-history"></i> วันและเวลาที่เสียชีวิต</h5>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">วันที่เสียชีวิต (Date of Death)</label>
                        <input type="date" name="discharge_date" class="form-control form-control-lg border-danger" required value="<?= $discharge_date ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">เวลาที่เสียชีวิต (Time of Death)</label>
                        <input type="time" name="discharge_time" class="form-control form-control-lg border-danger" required value="<?= $discharge_time ?>">
                    </div>

                    <h5 class="section-header mt-4"><i class="bi bi-journal-text"></i> สรุป/หมายเหตุ</h5>
                    <div class="mb-3">
                        <label class="form-label">สาเหตุการเสียชีวิต (ระบุรายละเอียด)</label>
                        <textarea name="ds_dead_cause" class="form-control" rows="4" placeholder="ระบุสาเหตุหลัก เช่น Cardiopulmonary arrest due to..."><?= htmlspecialchars($ds_dead_cause) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">หมายเหตุเพิ่มเติม (Note)</label>
                        <textarea name="dis_notes" class="form-control" rows="3"><?= htmlspecialchars($dis_notes) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="form-section h-100">
                    <h5 class="section-header"><i class="bi bi-list-check"></i> ระบุสาเหตุการเสียชีวิต (Specific Causes)</h5>
                    <div class="alert alert-light border small text-muted">
                        <i class="bi bi-info-circle"></i> เลือกสาเหตุที่เกี่ยวข้องทั้งหมด (Multiple Choice)
                    </div>

                    <div class="cause-group-title text-danger"><i class="bi bi-heart-pulse"></i> 1. Cardiac Causes (สาเหตุทางหัวใจ)</div>
                    <div class="card p-3 mb-3 border-0 bg-light">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="death_cause[]" id="dc_pump" value="Pump Failure" <?= isChecked($death_cause_list, 'Pump Failure') ?>>
                                    <label class="form-check-label" for="dc_pump">Pump Failure / Cardiogenic Shock</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="death_cause[]" id="dc_arr" value="Arrhythmia" <?= isChecked($death_cause_list, 'Arrhythmia') ?>>
                                    <label class="form-check-label" for="dc_arr">Arrhythmia (VT/VF/Asystole)</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="death_cause[]" id="dc_chf" value="Intractable CHF" <?= isChecked($death_cause_list, 'Intractable CHF') ?>>
                                    <label class="form-check-label" for="dc_chf">Intractable CHF</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="death_cause[]" id="dc_mech" value="Mechanical" <?= isChecked($death_cause_list, 'Mechanical') ?>>
                                    <label class="form-check-label" for="dc_mech">Mechanical Complication (Rupture)</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="death_cause[]" id="dc_scd" value="SCD" <?= isChecked($death_cause_list, 'SCD') ?>>
                                    <label class="form-check-label" for="dc_scd">Sudden Cardiac Death (SCD)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="cause-group-title"><i class="bi bi-lungs"></i> 2. Vascular & Non-Cardiac (อื่นๆ)</div>
                    <div class="card p-3 border-0 bg-light">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="death_cause[]" id="dc_stroke" value="Stroke" <?= isChecked($death_cause_list, 'Stroke') ?>>
                                    <label class="form-check-label" for="dc_stroke">Stroke / Intracranial Bleed</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="death_cause[]" id="dc_pe" value="PE" <?= isChecked($death_cause_list, 'PE') ?>>
                                    <label class="form-check-label" for="dc_pe">Pulmonary Embolism (PE)</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="death_cause[]" id="dc_aortic" value="Aortic Dissection" <?= isChecked($death_cause_list, 'Aortic Dissection') ?>>
                                    <label class="form-check-label" for="dc_aortic">Aortic Dissection</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="death_cause[]" id="dc_infect" value="Sepsis" <?= isChecked($death_cause_list, 'Sepsis') ?>>
                                    <label class="form-check-label" for="dc_infect">Sepsis / Infection</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="death_cause[]" id="dc_renal" value="Renal Failure" <?= isChecked($death_cause_list, 'Renal Failure') ?>>
                                    <label class="form-check-label" for="dc_renal">Renal Failure</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="death_cause[]" id="dc_other" value="Non-cardiac" <?= isChecked($death_cause_list, 'Non-cardiac') ?>>
                                    <label class="form-check-label" for="dc_other">Other Non-Cardiac Cause</label>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="patient_form.php?id=<?= $patient_id ?>" class="btn btn-outline-secondary px-4">
                <i class="bi bi-arrow-left"></i> ย้อนกลับ
            </a>
            <button type="submit" class="btn btn-danger px-5 shadow fw-bold">
                <i class="bi bi-save"></i> ยืนยันข้อมูลการเสียชีวิต
            </button>
        </div>

    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ฟังก์ชันเช็คก่อนบันทึก (Validation)
    document.querySelector('form').addEventListener('submit', function(e) {
        const hn = document.getElementById('patient_id').value.trim();
        const nameDisplay = document.getElementById('display_patient_name').innerText;

        // 1. ถ้าไม่มี HN
        if (!hn) {
            e.preventDefault();
            Swal.fire('ข้อผิดพลาด', 'กรุณากรอกเลข HN ก่อนบันทึก', 'warning');
            return;
        }

        // 2. ถ้าค้นหาไม่เจอคนไข้ (ชื่อยังเป็นค่า default หรือ ไม่พบ)
        if (nameDisplay === 'ไม่ระบุชื่อ' || nameDisplay === 'ไม่พบข้อมูลคนไข้') {
            e.preventDefault();
            Swal.fire('ข้อผิดพลาด', 'กรุณากดค้นหาและตรวจสอบชื่อคนไข้ให้ถูกต้องก่อน', 'warning');
            return;
        }
        
        // ถ้าผ่านหมด ให้ฟอร์ม submit ตามปกติ
    });

    // ฟังก์ชันค้นหาและดึงข้อมูล (Auto Link Data)
    document.getElementById('btn-search-patient').addEventListener('click', function() {
        const hn = document.getElementById('patient_id').value.trim();
        
        if (hn === '') {
            Swal.fire('แจ้งเตือน', 'กรุณากรอกเลข HN', 'warning');
            return;
        }

        // เริ่มโหลด
        Swal.fire({ title: 'กำลังค้นหา...', didOpen: () => Swal.showLoading() });

        fetch('get_patient.php?id=' + hn)
            .then(response => response.json())
            .then(data => {
                Swal.close(); // ปิด Loading

                if (data.status === 'success') {
                    // 1. แสดงชื่อคนไข้
                    document.getElementById('display_patient_name').innerText = 
                        data.patient.firstname + ' ' + data.patient.lastname;
                    document.getElementById('display_hn').innerText = hn;

                    // 2. เช็คว่ามีประวัติการตายเดิมไหม (Link Data)
                    if (data.death_data) {
                        Swal.fire({
                            icon: 'info',
                            title: 'พบข้อมูลเดิม',
                            text: 'คนไข้รายนี้มีการบันทึกข้อมูลเสียชีวิตไว้แล้ว ระบบจะดึงข้อมูลมาแสดง',
                            timer: 2500,
                            showConfirmButton: false
                        });

                        // --- เติมข้อมูลลงฟอร์ม (Fill Form) ---
                        const d = data.death_data;
                        
                        // วันที่/เวลา
                        if(d.discharge_date) document.querySelector('[name="discharge_date"]').value = d.discharge_date;
                        if(d.discharge_time) document.querySelector('[name="discharge_time"]').value = d.discharge_time;
                        
                        // Textarea
                        if(d.ds_dead_cause) document.querySelector('[name="ds_dead_cause"]').value = d.ds_dead_cause;
                        if(d.dis_notes) document.querySelector('[name="dis_notes"]').value = d.dis_notes;

                        // Checkbox (ยากหน่อย ต้องระเบิด string มาติ๊กถูก)
                        // เคลียร์ค่าเก่าก่อน
                        document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
                        
                        if (d.death_cause_list) {
                            const causes = d.death_cause_list.split(','); // แยกคำด้วยลูกน้ำ
                            causes.forEach(val => {
                                // หา checkbox ที่มี value ตรงกันแล้วติ๊กถูก
                                let cb = document.querySelector(`input[value="${val.trim()}"]`);
                                if (cb) cb.checked = true;
                            });
                        }

                    } else {
                        // ถ้าเจอคนไข้ แต่ยังไม่มีประวัติการตาย (เคสใหม่)
                        Swal.fire({
                            icon: 'success',
                            title: 'พบข้อมูลคนไข้',
                            text: 'สามารถบันทึกข้อมูลการเสียชีวิตได้',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        
                        // ล้างฟอร์มให้สะอาด เผื่อมีค่าค้างจากคนก่อนหน้า
                        document.querySelector('form').reset();
                        // เติมค่า HN กลับเข้าไปเพราะ reset จะลบหายไป
                        document.getElementById('patient_id').value = hn; 
                    }

                } else if (data.status === 'not_found') {
                    document.getElementById('display_patient_name').innerText = 'ไม่พบข้อมูลคนไข้';
                    Swal.fire('ไม่พบข้อมูล', 'ไม่พบเลข HN นี้ในระบบทะเบียนราษฎร์', 'error');
                } else {
                    Swal.fire('Error', 'เกิดข้อผิดพลาด: ' + (data.message || 'Unknown'), 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'error');
            });
    });
</script>
</body>
</html>