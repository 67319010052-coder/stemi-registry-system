<?php
session_start();
require 'connect.php';

// ตรวจสอบการล็อกอิน
// if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header('Location: login.php'); exit; }

try {
    // =================================================================================
    // PART 1: DATA AGGREGATION (รวมคำสั่ง SQL จากทั้ง 2 ไฟล์)
    // =================================================================================

    // 1. Overview Stats (Total, Alive, Dead)
    $stmt_status = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN dis_status = 'Alive' THEN 1 ELSE 0 END) as alive,
        SUM(CASE WHEN dis_status = 'Dead' THEN 1 ELSE 0 END) as dead,
        SUM(CASE WHEN dis_status IS NULL OR dis_status = '' THEN 1 ELSE 0 END) as in_progress
        FROM patient_discharges");
    $status_stats = $stmt_status->fetch(PDO::FETCH_ASSOC);
    $total_patients = $stmt_status->fetchColumn(); // หรือใช้ $status_stats['total'] ถ้า query รองรับ
    // *หมายเหตุ: ถ้าตาราง discharges ไม่ครบทุกคน ให้ใช้ count จาก table patients แยกต่างหาก
    $total_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();

    // 2. Patient Flow (Referral Types)
    $sql_refer = "SELECT referral_type, COUNT(*) as total FROM patient_risk_factors GROUP BY referral_type";
    $stmt = $pdo->query($sql_refer);
    $refer_data = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $refer_data[$row['referral_type']] = $row['total'];
    }
    $wi_count = $refer_data['Walk in'] ?? 0;
    $ems_count = $refer_data['EMS'] ?? 0;
    $refer_count = $refer_data['Referral'] ?? 0;

    // 3. Diagnosis Stats
    $stmt_dx = $pdo->query("SELECT 
        SUM(CASE WHEN final_diagnosis = 'STEMI' THEN 1 ELSE 0 END) as stemi,
        SUM(CASE WHEN final_diagnosis = 'NSTEMI' THEN 1 ELSE 0 END) as nstemi,
        SUM(CASE WHEN final_diagnosis = 'UA' THEN 1 ELSE 0 END) as ua
        FROM patient_discharges");
    $dx_stats = $stmt_dx->fetch(PDO::FETCH_ASSOC);

    // 4. Risk Factors
    $stmt_risk = $pdo->query("SELECT 
        SUM(CASE WHEN diabetes = 'YES' THEN 1 ELSE 0 END) as dm,
        SUM(CASE WHEN hypertension = 'YES' THEN 1 ELSE 0 END) as ht,
        SUM(CASE WHEN dyslipidemia = 'YES' THEN 1 ELSE 0 END) as dlp,
        SUM(CASE WHEN smoker = 'YES' THEN 1 ELSE 0 END) as smoker,
        SUM(CASE WHEN ckd = 'YES' THEN 1 ELSE 0 END) as ckd
        FROM patient_risk_factors");
    $risk_stats = $stmt_risk->fetch(PDO::FETCH_ASSOC);

    // 5. Treatment: PCI Procedures
    $sql_pci = "SELECT pci_indication, COUNT(*) as total FROM cardiac_cath GROUP BY pci_indication";
    $stmt = $pdo->query($sql_pci);
    $pci_stats = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pci_stats[$row['pci_indication']] = $row['total'];
    }

    // 6. Treatment: Fibrinolytic Drugs
    $sql_drug = "SELECT fibrinolytic_drug, COUNT(*) as total FROM patient_medications WHERE fibrinolytic_drug IS NOT NULL GROUP BY fibrinolytic_drug";
    $stmt = $pdo->query($sql_drug);
    $drug_stats = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $drug_stats[$row['fibrinolytic_drug']] = $row['total'];
    }

   // 7. Time KPIs (Average & Pass Rate) - อัปเดตใหม่เพื่อรองรับอัตราการผ่านเกณฑ์ 30 และ 90 นาที
$sql_times = "SELECT 
                AVG(c.door_to_device) as avg_d2d,
                AVG(c.onset_to_device) as avg_o2d,
                AVG(m.rpth_door_to_needle) as avg_d2n,
                -- คำนวณจำนวนที่ผ่านเกณฑ์ PPCI ภายใน 90 นาที
                COUNT(CASE WHEN c.door_to_device <= 90 THEN 1 END) as d2d_pass,
                -- คำนวณจำนวนที่ผ่านเกณฑ์ Fibrinolysis ภายใน 30 นาที
                COUNT(CASE WHEN m.rpth_door_to_needle <= 30 THEN 1 END) as d2n_pass,
                COUNT(c.door_to_device) as d2d_total,
                COUNT(m.rpth_door_to_needle) as d2n_total
              FROM patients p
              LEFT JOIN cardiac_cath c ON p.id = c.patient_id
              LEFT JOIN patient_medications m ON p.id = m.patient_id";
$time_stats = $pdo->query($sql_times)->fetch(PDO::FETCH_ASSOC);

// คำนวณร้อยละการผ่านเกณฑ์สำหรับแสดงผลบน Dashboard
$avg_d2d = round($time_stats['avg_d2d'] ?? 0);
$d2d_pass_rate = ($time_stats['d2d_total'] > 0) ? round(($time_stats['d2d_pass'] / $time_stats['d2d_total']) * 100) : 0;
$d2n_pass_rate = ($time_stats['d2n_total'] > 0) ? round(($time_stats['d2n_pass'] / $time_stats['d2n_total']) * 100) : 0;

    // 8. Mortality Rate
    $mortality_rate = ($total_patients > 0) ? round(($status_stats['dead'] / $total_patients) * 100, 1) : 0;

    // 9. Recent Patients Table
    $stmt_recent = $pdo->query("SELECT p.id, p.firstname, p.lastname, p.hospital_code, 
                                       d.dis_status, d.final_diagnosis, c.door_to_device
                                FROM patients p
                                LEFT JOIN patient_discharges d ON p.id = d.patient_id
                                LEFT JOIN cardiac_cath c ON p.id = c.patient_id
                                ORDER BY p.created_at DESC LIMIT 5");
    $recent_patients = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

    // 10. Monthly Trend (D2D)
$sql_monthly = "SELECT MONTH(c.first_device_date) as m, 
                AVG(c.door_to_device) as avg_d2d 
                FROM cardiac_cath c 
                WHERE YEAR(c.first_device_date) = 2026 
                GROUP BY MONTH(c.first_device_date)
                ORDER BY m ASC";
$monthly_stats = $pdo->query($sql_monthly)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STEMI Integrated Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">

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
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 1.5rem; transition: transform 0.2s; }
        .card:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .icon-box { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        
        /* Gradients */
        .bg-gradient-primary { background: linear-gradient(45deg, #0d6efd, #0a58ca); color: white; }
        .bg-gradient-success { background: linear-gradient(45deg, #198754, #146c43); color: white; }
        .bg-gradient-danger { background: linear-gradient(45deg, #dc3545, #b02a37); color: white; }
        .bg-gradient-warning { background: linear-gradient(45deg, #ffc107, #ffca2c); color: #000; }
        
        .kpi-circle {
            width: 120px; height: 120px; border-radius: 50%; 
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            border: 8px solid #e9ecef; margin: 0 auto;
        }
        
        .text-kpi { font-size: 1.8rem; font-weight: 800; }
        .section-title { font-weight: 700; color: #495057; display: flex; align-items: center; gap: 8px; margin-bottom: 1rem; }
        .progress-thin { height: 6px; border-radius: 3px; }
    </style>
</head>
<body class="pb-5">

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <i class="bi bi-activity text-danger fs-3"></i>
            <div>
                <span class="fw-bold text-dark d-block" style="line-height: 1;">STEMI Registry</span>
                <span class="text-muted fw-normal small">Analytics & Report</span>
            </div>
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm d-none d-lg-block"><i class="bi bi-printer me-1"></i> Print</button>
            <a href="index.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-table me-1"></i> รายชื่อผู้ป่วย</a>
            <a href="patient_form.php" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i> เพิ่มผู้ป่วยใหม่</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">

    <div class="row g-3 mb-2">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box bg-gradient-primary me-3"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <h6 class="text-muted mb-0">Total Patients</h6>
                        <h3 class="fw-bold mb-0"><?= number_format($total_patients) ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box bg-gradient-danger me-3"><i class="bi bi-heart-pulse-fill"></i></div>
                    <div>
                        <h6 class="text-muted mb-0">STEMI Cases</h6>
                        <h3 class="fw-bold mb-0"><?= number_format($dx_stats['stemi']) ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box bg-dark text-white me-3"><i class="bi bi-heartbreak-fill"></i></div>
                    <div>
                        <h6 class="text-muted mb-0">Mortality</h6>
                        <h3 class="fw-bold mb-0"><?= number_format($status_stats['dead']) ?> <span class="fs-6 text-danger">(<?= $mortality_rate ?>%)</span></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box bg-gradient-success me-3"><i class="bi bi-stopwatch-fill"></i></div>
                    <div>
                        <h6 class="text-muted mb-0">Avg. D2D</h6>
                        <h3 class="fw-bold mb-0"><?= $avg_d2d ?> <span class="fs-6 fw-normal">min</span></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="section-title"><i class="bi bi-people"></i> Patient Access & Risk Factors</div>
    <div class="row g-3 mb-2">
        <div class="col-lg-4">
            <div class="card h-100 p-3">
                <h6 class="fw-bold text-muted mb-3">Access Mode</h6>
                
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div><i class="bi bi-hospital text-primary me-2"></i> Referral (เครือข่าย)</div>
                    <span class="fw-bold fs-5"><?= $refer_count ?></span>
                </div>
                <div class="progress progress-thin mb-3">
                    <div class="progress-bar bg-primary" style="width: <?= ($total_patients>0)?($refer_count/$total_patients)*100:0 ?>%"></div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div><i class="bi bi-person-walking text-success me-2"></i> Walk-in</div>
                    <span class="fw-bold fs-5"><?= $wi_count ?></span>
                </div>
                <div class="progress progress-thin mb-3">
                    <div class="progress-bar bg-success" style="width: <?= ($total_patients>0)?($wi_count/$total_patients)*100:0 ?>%"></div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div><i class="bi bi-ambulance text-danger me-2"></i> EMS</div>
                    <span class="fw-bold fs-5"><?= $ems_count ?></span>
                </div>
                <div class="progress progress-thin">
                    <div class="progress-bar bg-danger" style="width: <?= ($total_patients>0)?($ems_count/$total_patients)*100:0 ?>%"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100 p-3">
                <h6 class="fw-bold text-muted mb-2">Risk Factors Prevalence</h6>
                <div style="height: 200px;">
                    <canvas id="riskChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="section-title"><i class="bi bi-speedometer2"></i> Clinical Performance & Time KPIs</div>
    <div class="row g-3 mb-2">
        <div class="col-md-3">
            <div class="card h-100 p-3 text-center">
                <h6 class="fw-bold text-muted mb-3">KPI: D2D < 90 min</h6>
                <div class="kpi-circle mb-3" style="border-color: <?= $d2d_pass_rate >= 75 ? '#198754' : '#dc3545' ?>;">
                    <span class="fs-2 fw-bold"><?= $d2d_pass_rate ?>%</span>
                    <span class="small text-muted">Success</span>
                </div>
                <div class="small text-muted">Passed: <b><?= $time_stats['d2d_pass'] ?></b> / <?= $time_stats['d2d_total'] ?> Cases</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100 p-3 text-center bg-primary text-white">
                <div class="fs-5 opacity-75">Door to Balloon</div>
                <div class="display-4 fw-bold"><?= round($time_stats['avg_d2d']) ?></div>
                <div class="small">Minutes (Avg)</div>
                <div class="badge bg-white text-primary mt-2">Target < 90 min</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 p-3 text-center bg-success text-white">
                <div class="fs-5 opacity-75">Door to Needle</div>
                <div class="display-4 fw-bold"><?= round($time_stats['avg_d2n']) ?></div>
                <div class="small">Minutes (Avg)</div>
                <div class="badge bg-white text-success mt-2">Target < 30 min</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 p-3 text-center bg-dark text-white">
                <div class="fs-5 opacity-75">Total Ischemic</div>
                <div class="display-4 fw-bold"><?= round($time_stats['avg_o2d']) ?></div>
                <div class="small">Onset to Device (Min)</div>
                <div class="badge bg-white text-dark mt-2">Time is Muscle</div>
            </div>
        </div>
    </div>

    <div class="section-title"><i class="bi bi-heart-pulse"></i> Treatment & Outcomes</div>
    <div class="row g-3 mb-2">
        <div class="col-md-4">
            <div class="card h-100 p-3">
                <h6 class="fw-bold text-muted mb-2">Diagnosis Distribution</h6>
                <div style="height: 200px;">
                    <canvas id="diagnosisChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 p-3">
                <h6 class="fw-bold text-muted mb-3">Cath Lab Procedures</h6>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Primary PCI</span>
                        <span class="badge bg-primary rounded-pill"><?= $pci_stats['Primary PCI'] ?? 0 ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Rescue PCI</span>
                        <span class="badge bg-warning text-dark rounded-pill"><?= $pci_stats['Rescue PCI'] ?? 0 ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Pharmaco-Invasive</span>
                        <span class="badge bg-info text-dark rounded-pill"><?= $pci_stats['Pharmacoinvasive'] ?? 0 ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Urgent / Elective</span>
                        <span class="badge bg-secondary rounded-pill"><?= ($pci_stats['Urgent'] ?? 0) + ($pci_stats['Elective'] ?? 0) ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-md-4">
             <div class="card h-100 p-3">
                <h6 class="fw-bold text-muted mb-2">Discharge Outcome</h6>
                <div style="height: 180px;">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="text-center mt-2 small text-muted">
                    In-Progress: <?= $status_stats['in_progress'] ?> Cases
                </div>
            </div>
        </div>
    </div>

   

</div>

<script>
    // 1. Diagnosis Chart (Doughnut)
    new Chart(document.getElementById('diagnosisChart'), {
        type: 'doughnut',
        data: {
            labels: ['STEMI', 'NSTEMI', 'UA'],
            datasets: [{
                data: [<?= $dx_stats['stemi'] ?>, <?= $dx_stats['nstemi'] ?>, <?= $dx_stats['ua'] ?>],
                backgroundColor: ['#dc3545', '#ffc107', '#0dcaf0'],
                borderWidth: 0
            }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
    });

    // 2. Risk Factors Chart (Bar)
    new Chart(document.getElementById('riskChart'), {
        type: 'bar',
        data: {
            labels: ['DM', 'HT', 'DLP', 'Smoking', 'CKD'],
            datasets: [{
                label: 'Patients',
                data: [<?= $risk_stats['dm'] ?>, <?= $risk_stats['ht'] ?>, <?= $risk_stats['dlp'] ?>, <?= $risk_stats['smoker'] ?>, <?= $risk_stats['ckd'] ?>],
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
    });

    // 3. Outcome Chart (Pie)
    new Chart(document.getElementById('statusChart'), {
        type: 'pie',
        data: {
            labels: ['Alive', 'Dead'],
            datasets: [{
                data: [<?= $status_stats['alive'] ?>, <?= $status_stats['dead'] ?>],
                backgroundColor: ['#198754', '#212529'],
                borderWidth: 0
            }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>