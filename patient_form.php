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
$patient_data = [];

// ดึงข้อมูลเดิมถ้ามี ID ส่งมา
if (!empty($patient_id)) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

// --- Variable Definitions (Cleaned Logic) ---
$hospital_code   = $patient_data['hospital_code'] ?? $_POST['hospital_code'] ?? '';
$hn              = $patient_data['hn'] ?? $_POST['hn'] ?? ''; // แก้ไข Logic การดึง HN
$citizen_id      = $patient_data['citizen_id'] ?? $_POST['citizen_id'] ?? '';
$id_type         = $patient_data['id_type'] ?? $_POST['id_type'] ?? '';
$firstname       = $patient_data['firstname'] ?? $_POST['firstname'] ?? '';
$lastname        = $patient_data['lastname'] ?? $_POST['lastname'] ?? '';
$first_ekg_date  = $patient_data['first_ekg_date'] ?? $_POST['first_ekg_date'] ?? '';
$age             = $patient_data['age'] ?? $_POST['age'] ?? '';
$gender          = $patient_data['gender'] ?? $_POST['gender'] ?? '';
$weight          = $patient_data['weight'] ?? $_POST['weight'] ?? '';
$height          = $patient_data['height'] ?? $_POST['height'] ?? '';
$occupation      = $patient_data['occupation'] ?? $_POST['occupation'] ?? '';
$religion        = $patient_data['religion'] ?? $_POST['religion'] ?? '';
$treatment_right = $patient_data['treatment_right'] ?? $_POST['treatment_right'] ?? '';
$credit_name     = $patient_data['credit_name'] ?? $_POST['credit_name'] ?? '';
$health_zone     = $patient_data['health_zone'] ?? $_POST['health_zone'] ?? '';
$outside_detail  = $patient_data['outside_detail'] ?? $_POST['outside_detail'] ?? '';
$phone           = $patient_data['phone'] ?? $_POST['phone'] ?? '';
$phone_alt       = $patient_data['phone_alt'] ?? $_POST['phone_alt'] ?? '';

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_id = $_POST['patient_id'] ?? '';
    
    // Prepare data array
    $data_to_save = [
        ':hospital_code' => $hospital_code,
        ':hn'            => $hn,
        ':citizen_id'    => $citizen_id,
        ':id_type'       => $id_type,
        ':firstname'     => $firstname,
        ':lastname'      => $lastname,
        ':first_ekg_date'=> $first_ekg_date ?: null,
        ':age'           => $age ?: 0,
        ':gender'        => $gender,
        ':weight'        => $weight ?: null,
        ':height'        => $height ?: null,
        ':occupation'    => $occupation,
        ':religion'      => $religion,
        ':treatment_right'=> $treatment_right,
        ':credit_name'   => $credit_name,
        ':health_zone'   => $health_zone,
        ':outside_detail' => $outside_detail,
        ':phone'         => $phone,
        ':phone_alt'     => $phone_alt
    ];

    try {
        if (!empty($current_id)) {
            // --- UPDATE Logic ---
            $sql = "UPDATE patients SET
                        hospital_code = :hospital_code,
                        hn = :hn,
                        citizen_id = :citizen_id,
                        id_type = :id_type,
                        firstname = :firstname,
                        lastname = :lastname,
                        first_ekg_date = :first_ekg_date,
                        age = :age,
                        gender = :gender,
                        weight = :weight,
                        height = :height,
                        occupation = :occupation,
                        religion = :religion,
                        treatment_right = :treatment_right,
                        credit_name = :credit_name,
                        health_zone = :health_zone,
                        outside_detail = :outside_detail,
                        phone = :phone,
                        phone_alt = :phone_alt
                    WHERE id = :patient_id";

            $data_to_save[':patient_id'] = $current_id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data_to_save);

            // Redirect หลัง Update สำเร็จ
            header("Location: history_risk_factor.php?id=" . $current_id . "&status=updated");
            exit();

        } else {
            // --- INSERT Logic ---
            $sql = "INSERT INTO patients (
                        hospital_code, hn, citizen_id, id_type, firstname, lastname, 
                        first_ekg_date, age, gender, weight, height, 
                        occupation, religion, treatment_right, credit_name, health_zone, 
                        outside_detail, phone, phone_alt
                    ) VALUES (
                        :hospital_code, :hn, :citizen_id, :id_type, :firstname, :lastname, 
                        :first_ekg_date, :age, :gender, :weight, :height, 
                        :occupation, :religion, :treatment_right, :credit_name, :health_zone, 
                        :outside_detail, :phone, :phone_alt
                    )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($data_to_save);
            
            $last_id = $pdo->lastInsertId();
            
            // Redirect หลัง Insert สำเร็จ
            header("Location: history_risk_factor.php?id=" . $last_id . "&status=success");
            exit();
        }
    } catch (PDOException $e) {
        $error_msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
        $msg_js = json_encode($error_msg);
        echo "<script>alert({$msg_js});</script>";
    }
}

// Helper function
function isGenderChecked($value, $current_gender) {
    return ($value === $current_gender) ? 'checked' : '';
}
?>

<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบฟอร์มข้อมูลผู้ป่วย</title>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
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
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }
        .required::after {
            content: " *";
            color: red;
        }
        /* Style for Gender Buttons */
        .btn-check:checked+.btn-gender {
            background-color: #28a745 !important;
            color: white !important;
            border-color: #28a745 !important;
        }
        /* Select2 Customization */
        .select2-container--default .select2-selection--single {
            height: 38px !important;
            border: 1px solid #ced4da !important;
            border-radius: 0.375rem !important;
            padding-top: 4px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #19a974 !important;
        }
        .form-label { font-weight: 600; color: #495057; font-size: 0.95rem; }
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
        
            <form method="POST">
                <input type="hidden" name="patient_id" value="<?= htmlspecialchars($patient_id) ?>">
                
                <div class="card shadow-sm border-0 mb-4 rounded-4 bg-light-subtle">
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">โรงพยาบาล</label>
                                <select id="hospital_select" name="hospital_code" class="form-select" required style="width:100%">
                                    <?php if (!empty($hospital_code)): ?>
                                        <option value="<?= htmlspecialchars($hospital_code) ?>" selected>
                                            <?= htmlspecialchars($hospital_code) ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required text-primary fw-bold">HN</label>
                                <input type="text" name="hn" id="hn" class="form-control border-primary"
                                    value="<?= htmlspecialchars($hn) ?>" required placeholder="ระบุ HN หรือซิงค์จากบัตร...">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">ประเภทบัตร</label>
                                <select name="id_type" id="id_type" class="form-select" required>
                                    <option value="">-- เลือกประเภทบัตร --</option>
                                    <?php
                                    $id_types = ['เลขบัตรประชาชน', 'passport', 'ต่างด้าว'];
                                    foreach ($id_types as $type) {
                                        $selected = ($id_type === $type) ? 'selected' : '';
                                        echo "<option value=\"$type\" $selected>$type</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">เลขบัตร</label>
                                <div class="input-group">
                                    <input type="text" name="citizen_id" id="citizen_id" class="form-control"
                                        placeholder="ระบุเลขบัตร 13 หลัก" autocomplete="off" required value="<?= htmlspecialchars($citizen_id) ?>">
                                    <button class="btn btn-outline-primary" type="button" id="btn_sync">
                                        <i class="bi bi-arrow-repeat"></i> ซิงค์ข้อมูล
                                    </button>
                                </div>
                                <small id="sync_status" class="form-text text-muted"></small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">ชื่อ</label>
                                <input type="text" name="firstname" class="form-control" required
                                    placeholder="ไม่ต้องมีคำนำหน้าชื่อ" value="<?= htmlspecialchars($firstname) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">นามสกุล</label>
                                <input type="text" name="lastname" class="form-control" required
                                    value="<?= htmlspecialchars($lastname) ?>">
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label required">น้ำหนัก (กก.)</label>
                                <input type="number" name="weight" step="0.1" class="form-control" required
                                    value="<?= htmlspecialchars($weight) ?>">
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label required">ส่วนสูง (ซม.)</label>
                                <input type="number" name="height" class="form-control" required
                                    value="<?= htmlspecialchars($height) ?>">
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label required">อายุ (ปี)</label>
                                <input type="number" name="age" id="age" class="form-control" required
                                    value="<?= htmlspecialchars($age ?? '') ?>">
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">เพศ</label>
                                <div class="btn-group w-100" role="group" id="gender-group">
                                    <input type="radio" class="btn-check" name="gender" id="male" value="ชาย"
                                        <?= isGenderChecked('ชาย', $gender) ?>>
                                    <label class="btn btn-outline-secondary btn-gender" for="male">ชาย♂</label>

                                    <input type="radio" class="btn-check" name="gender" id="female" value="หญิง"
                                        <?= isGenderChecked('หญิง', $gender) ?>>
                                    <label class="btn btn-outline-secondary btn-gender" for="female">หญิง♀</label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">อาชีพ</label>
                                <input type="text" name="occupation" class="form-control"
                                    value="<?= htmlspecialchars($occupation) ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">ศาสนา</label>
                                <input type="text" id="religion_name" name="religion" class="form-control"
                                    value="<?= htmlspecialchars($religion ?? '') ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">ชื่อสิทธิ / หน่วยงาน</label>
                                <input type="text" name="credit_name" id="credit_name" class="form-control"
                                    value="<?= $credit_name ?? '' ?>">
                            </div>

                            <div class="col-12" id="outside_detail_div" style="display:none;">
                                <label class="form-label">ระบุจังหวัด/รายละเอียด</label>
                                <input type="text" name="outside_detail" id="outside_detail" class="form-control"
                                    value="<?= htmlspecialchars($outside_detail ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">โทรศัพท์ (หลัก)</label>
                                <input type="text" name="phone" class="form-control" required
                                    placeholder="กรอกเบอร์โทรศัพท์หลัก" value="<?= htmlspecialchars($phone) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">โทรศัพท์ (รอง)</label>
                                <input type="text" name="phone_alt" class="form-control"
                                    placeholder="เบอร์โทรศัพท์อื่น ๆ (ถ้ามี)" value="<?= htmlspecialchars($phone_alt) ?>">
                            </div>

                        </div> 
                    </div> 
                </div> 
                
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-success px-5 py-2 shadow-sm fw-bold">
                        SAVE & NEXT <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="patient_form.js"></script>
    <script>
        $(document).ready(function () {
            $('#hospital_select').select2({
                placeholder: '-- เลือกโรงพยาบาล --',
                allowClear: true,
                minimumInputLength: 1,
                ajax: {
                    url: 'http://192.168.99.225/app/dss/api/api_card_hospital.php',
                    type: 'POST',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            fnc: 'hospital',
                            search: params.term || ''
                        };
                    },
                    processResults: function (response) {
                        console.log('RAW RESPONSE =>', response);
                        let items = [];
                        
                        // รองรับ Response หลายรูปแบบเพื่อป้องกัน Error
                        if (Array.isArray(response)) {
                            items = response;
                        } else if (Array.isArray(response.data)) {
                            items = response.data;
                        } else if (Array.isArray(response.rows)) {
                            items = response.rows;
                        } else if (Array.isArray(response.result)) {
                            items = response.result;
                        }

                        return {
                            results: items.map(item => ({
                                id: item.hospital,
                                text: item.hospital
                            }))
                        };
                    },
                    cache: true
                }
            });
        });
    </script>
</body>
</html>