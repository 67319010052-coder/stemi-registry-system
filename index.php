<?php
require 'connect.php';

try {
    // ดึงข้อมูลผู้ป่วย พร้อมสถานะจำหน่าย และเวลา KPI
    $sql = "SELECT p.id, p.firstname, p.lastname, p.hospital_code, p.citizen_id, p.created_at,
                   d.dis_status, 
                   m.rpth_door_to_needle, 
                   c.door_to_device
            FROM patients p
            LEFT JOIN patient_discharges d ON p.id = d.patient_id
            LEFT JOIN patient_medications m ON p.id = m.patient_id
            LEFT JOIN cardiac_cath c ON p.id = c.patient_id
            ORDER BY p.created_at DESC";
    $stmt = $pdo->query($sql);
    $patients = $stmt->fetchAll();

    // นับยอดสรุป
    $total = count($patients);
    $alive = 0; $dead = 0;
    foreach($patients as $p) {
        if($p['dis_status'] == 'Alive') $alive++;
        if($p['dis_status'] == 'Dead') $dead++;
    }
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}
?>

<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>STEMI Registry Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --danger-color: #dc3545;
            --bg-body: #f4f7f9;
        }

         body { 
    font-family: 'Sarabun', sans-serif;
    
    /* ฟ้าอ่อนพาสเทล -> ขาว */
    background: linear-gradient(180deg, #e3f2fd 0%, #ffffff 100%);
    
    min-height: 100vh;
    margin: 0;
    background-repeat: no-repeat;
    background-attachment: fixed;
}

        /* Dashboard Cards */
        .stat-card {
            border: none;
            border-radius: 16px;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .icon-shape {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }

        /* Table Style */
        .table-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .table thead th {
            background: #f8fafc;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.025em;
            padding: 1rem;
            border-bottom: 2px solid #e2e8f0;
        }
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        /* KPI & Badge */
        .kpi-dot {
            width: 10px;
            height: 10px;
            display: inline-block;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-badge {
            font-weight: 500;
            padding: 0.5rem 0.8rem;
            border-radius: 8px;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Action Buttons */
        .btn-action {
            width: 34px;
            height: 34px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #e2e8f0;
            transition: 0.2s;
        }
        .btn-action:hover { background: #f8fafc; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }

        .search-box {
            border-radius: 12px;
            padding-left: 45px;
            height: 45px;
            border: 1px solid #e2e8f0;
        }
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row align-items-center mb-5">
        <div class="col-md-7">
            <h2 class="fw-bold mb-1"><i class="bi bi-heart-pulse-fill text-danger"></i> STEMI Registry</h2>
            <p class="text-muted">ระบบจัดเก็บและติดตามตัวชี้วัดคุณภาพการดูแลผู้ป่วยโรคหัวใจขาดเลือด</p>
        </div>
        <div class="col-md-5 text-md-end">
            <a href="patient_form.php" class="btn btn-primary px-4 py-2 shadow-sm rounded-pill">
                <i class="bi bi-plus-lg me-2"></i> ลงทะเบียนผู้ป่วยใหม่
            </a>
        </div>
    </div>


    <div class="table-container shadow-sm p-4 bg-white">
        <div class="row mb-4 align-items-center">
            <div class="col-md-4">
                <div class="position-relative">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="searchInput" class="form-control search-box" placeholder="ค้นหา HN, ชื่อ หรือเลขบัตร...">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table" id="patientTable">
                <thead>
                    <tr>
                        <th>วันที่ลงทะเบียน</th>
                        <th>ข้อมูลผู้ป่วย (KPI Status)</th>
                        <th>เลขบัตรประชาชน</th>
                        <th>โรงพยาบาล</th>
                        <th>สถานะ</th>
                        <th class="text-end">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($patients): foreach ($patients as $row): 
                        // Logic KPI
                        $d2n = $row['rpth_door_to_needle'];
                        $d2d = $row['door_to_device'];
                        $dot_color = '#10b981'; // Success Green
                        $kpi_text = "ในเกณฑ์";

                        if (($d2n > 30) || ($d2d > 90)) {
                            $dot_color = '#ef4444'; // Danger Red
                            $kpi_text = "ล่าช้า";
                        } elseif (empty($d2n) && empty($d2d)) {
                            $dot_color = '#94a3b8'; // Muted Grey
                            $kpi_text = "ไม่มีข้อมูล";
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="small fw-bold"><?= date('d/m/Y', strtotime($row['created_at'])) ?></div>
                            <div class="text-muted" style="font-size: 0.8rem;"><?= date('H:i', strtotime($row['created_at'])) ?> น.</div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="kpi-dot shadow-sm" style="background-color: <?= $dot_color ?>;" title="<?= $kpi_text ?>"></span>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></span>
                            </div>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($row['citizen_id']) ?></td>
                        <td><span class="badge rounded-pill bg-light text-primary border border-primary-subtle px-3"><?= htmlspecialchars($row['hospital_code']) ?></span></td>
                        <td>
                            <?php if ($row['dis_status'] == 'Alive'): ?>
                                <span class="status-badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-check-lg"></i> Alive
                                </span>
                            <?php elseif ($row['dis_status'] == 'Dead'): ?>
                                <span class="status-badge bg-danger-subtle text-danger border border-danger-subtle">
                                    <i class="bi bi-heartbreak"></i> Deceased
                                </span>
                            <?php else: ?>
                                <button class="status-badge bg-warning-subtle text-warning border border-warning-subtle btn" 
                                        onclick="openDischargeModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['firstname'].' '.$row['lastname']) ?>')">
                                    <i class="bi bi-clock"></i> In-Progress
                                </button>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="patient_form.php?id=<?= $row['id'] ?>" class="btn-action" title="แก้ไข"><i class="bi bi-pencil text-primary"></i></a>
                                <a href="discharge.php?id=<?= $row['id'] ?>" class="btn-action" title="จำหน่าย"><i class="bi bi-box-arrow-right text-warning"></i></a>
                                <a href="report_summary.php?id=<?= $row['id'] ?>" class="btn-action" title="สรุปผล"><i class="bi bi-file-bar-graph text-success"></i></a>
                                <button onclick="confirmDelete(<?= $row['id'] ?>)" class="btn-action" title="ลบ"><i class="bi bi-trash text-danger"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="6" class="text-center py-5">
                        <img src="https://illustrations.popsy.co/gray/not-found.svg" style="width: 150px;" class="mb-3 d-block mx-auto">
                        <span class="text-muted">ไม่พบข้อมูลผู้ป่วยในระบบ</span>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="dischargeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="discharge.php" method="POST" id="dischargeForm" class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">จำหน่ายผู้ป่วย</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="patient_id" id="modal_patient_id">
                <p class="text-muted mb-4">ผู้ป่วย: <span id="modal_patient_name" class="fw-bold text-dark"></span></p>
                
                <div class="mb-4">
                    <label class="form-label fw-bold">สถานะจำหน่าย</label>
                    <select name="dis_status" id="dis_status_select" class="form-select form-select-lg" required onchange="toggleDeathReason()">
                        <option value="">เลือก...</option>
                        <option value="Alive">Alive (มีชีวิต)</option>
                        <option value="Dead">Dead (เสียชีวิต)</option>
                    </select>
                </div>

                <div id="death_reason_div" style="display:none;" class="p-3 bg-danger-subtle rounded-4 border border-danger-subtle mb-3">
                    <label class="form-label fw-bold text-danger mb-2">สาเหตุการเสียชีวิต (เลือกได้อย่างน้อย 1)</label>
                    <div class="form-check mb-2">
                        <input class="form-check-input death-check" type="checkbox" name="death_cause[]" value="Pump Failure" id="dc1">
                        <label class="form-check-label small" for="dc1">Pump Failure / Cardiogenic shock</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input death-check" type="checkbox" name="death_cause[]" value="Arrhythmia" id="dc2">
                        <label class="form-check-label small" for="dc2">Arrhythmia (VT/VF/Asystole)</label>
                    </div>
                    <textarea name="ds_dead_cause" class="form-control form-control-sm" placeholder="หมายเหตุเพิ่มเติม..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4">ยืนยันการจำหน่าย</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDischargeModal(id, name) {
    document.getElementById('modal_patient_id').value = id;
    document.getElementById('modal_patient_name').innerText = name;
    new bootstrap.Modal(document.getElementById('dischargeModal')).show();
}

function toggleDeathReason() {
    const status = document.getElementById('dis_status_select').value;
    document.getElementById('death_reason_div').style.display = (status === 'Dead') ? 'block' : 'none';
}

document.getElementById('dischargeForm').addEventListener('submit', function(e) {
    const status = document.getElementById('dis_status_select').value;
    if (status === 'Dead' && document.querySelectorAll('.death-check:checked').length === 0) {
        e.preventDefault();
        alert('กรุณาระบุสาเหตุการเสียชีวิต');
    }
});

document.getElementById('searchInput').addEventListener('keyup', function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll('#patientTable tbody tr');
    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(value) ? '' : 'none';
    });
});

function confirmDelete(id) {
    if (confirm('ยืนยันการลบข้อมูลนี้หรือไม่?')) {
        window.location.href = 'delete_patient.php?id=' + id;
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>