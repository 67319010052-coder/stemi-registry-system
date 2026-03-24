document.addEventListener('DOMContentLoaded', function () {

    /* ================= 1. Gender Buttons ================= */
    var genderBtns = document.querySelectorAll('#gender-group .btn-gender');
    var radioInputs = document.querySelectorAll('#gender-group .btn-check');

    function syncGenderButtons() {
        for (var i = 0; i < genderBtns.length; i++) {
            genderBtns[i].classList.remove('active');
        }

        for (var j = 0; j < radioInputs.length; j++) {
            if (radioInputs[j].checked) {
                var label = document.querySelector(
                    'label[for="' + radioInputs[j].id + '"]'
                );
                if (label) {
                    label.classList.add('active');
                }
            }
        }
    }

    if (radioInputs.length) {
        for (var k = 0; k < radioInputs.length; k++) {
            radioInputs[k].addEventListener('change', syncGenderButtons);
        }
        syncGenderButtons();
    }

    /* ================= 2. Treatment Right Logic ================= */
    var treatmentRight = document.getElementById('treatment_right');
    var healthZoneDiv = document.getElementById('health_zone_div');
    var healthZone = document.getElementById('health_zone');
    var outsideDetailDiv = document.getElementById('outside_detail_div');
    var outsideDetail = document.getElementById('outside_detail');

    function updateTreatmentUI() {
        if (!treatmentRight || !healthZoneDiv || !outsideDetailDiv) return;

        healthZoneDiv.style.display = 'none';
        outsideDetailDiv.style.display = 'none';

        if (
            treatmentRight.value === 'ประกันสุขภาพ' ||
            treatmentRight.value === 'ประกันสังคม'
        ) {
            healthZoneDiv.style.display = 'block';

            if (
                healthZone &&
                (healthZone.value === 'ในเขต' || healthZone.value === 'นอกเขต')
            ) {
                outsideDetailDiv.style.display = 'block';
            } else if (outsideDetail) {
                outsideDetail.value = '';
            }
        } else {
            if (healthZone) healthZone.value = '';
            if (outsideDetail) outsideDetail.value = '';
        }
    }

    if (treatmentRight) {
        treatmentRight.addEventListener('change', updateTreatmentUI);
    }
    if (healthZone) {
        healthZone.addEventListener('change', updateTreatmentUI);
    }
    updateTreatmentUI();

    /* ================= 3. ID Type Logic ================= */
    var idTypeSelect = document.getElementById('id_type');
    var citizenInput = document.getElementById('citizen_id');

    function updateCitizenInput() {
        if (!idTypeSelect || !citizenInput) return;

        citizenInput.value = '';
        citizenInput.removeAttribute('maxlength');
        citizenInput.removeAttribute('pattern');

        if (idTypeSelect.value === 'เลขบัตรประชาชน') {
            citizenInput.placeholder = 'เลขบัตรประชาชน 13 หลัก';
            citizenInput.maxLength = 13;
            citizenInput.pattern = '[0-9]{13}';
        } else if (idTypeSelect.value === 'passport') {
            citizenInput.placeholder = 'Passport Number';
            citizenInput.pattern = '[A-Za-z0-9]{6,20}';
        } else if (idTypeSelect.value === 'ต่างด้าว') {
            citizenInput.placeholder = 'หมายเลขบัตรต่างด้าว';
            citizenInput.pattern = '[A-Za-z0-9]{6,20}';
        } else {
            citizenInput.placeholder = '';
        }
    }

    if (idTypeSelect) {
        idTypeSelect.addEventListener('change', updateCitizenInput);
    }

    if (citizenInput) {
        citizenInput.addEventListener('input', function () {
            if (idTypeSelect && idTypeSelect.value === 'เลขบัตรประชาชน') {
                this.value = this.value.replace(/[^0-9]/g, '');
            }
        });
    }

    updateCitizenInput();

    /* ================= 4. Age Calculation ================= */
    var dobInput = document.getElementById('first_ekg_date');
    var ageInput = document.getElementById('age');

    function calculateAge() {
        if (!dobInput || !ageInput) return;

        if (!dobInput.value) {
            ageInput.value = '';
            return;
        }

        var dob = new Date(dobInput.value);
        var today = new Date();

        if (dob > today) {
            alert('วันเกิดห้ามเกินวันที่ปัจจุบัน');
            dobInput.value = '';
            ageInput.value = '';
            return;
        }

        var age = today.getFullYear() - dob.getFullYear();
        var m = today.getMonth() - dob.getMonth();

        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
            age--;
        }

        ageInput.value = age >= 0 ? age : '';
    }

    if (dobInput) {
        dobInput.addEventListener('change', calculateAge);
        calculateAge();
    }

   /* ================= 5. Sync API Button ================= */
var btnSync = document.getElementById('btn_sync');
var statusText = document.getElementById('sync_status');

if (btnSync) {
    btnSync.addEventListener('click', function () {

        var cid = citizenInput ? citizenInput.value : '';

        if (
            idTypeSelect &&
            idTypeSelect.value === 'เลขบัตรประชาชน' &&
            cid.length < 13
        ) {
            alert('กรุณากรอกเลขบัตรประชาชนให้ครบ 13 หลักก่อนซิงค์');
            return;
        }


            btnSync.disabled = true;
            btnSync.innerHTML =
                '<span class="spinner-border spinner-border-sm"></span>';

            if (statusText) {
                statusText.innerHTML = 'กำลังดึงข้อมูล...';
                statusText.className = 'form-text text-primary';
            }

            fetch('http://192.168.99.225/app/dss/api/api_card_pmk.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_card: cid })
            })
            .then(function (res) {
                return res.json();
            })
            .then(function (res) {

                if (res.status === 'success') {
                    var p = res.data || {};

                    var hnInput = document.getElementById('hn');
                    if (hnInput) hnInput.value = p.patient_hn || '';

                    var fullName = document.getElementById('full_name');
                    if (fullName) {
                        fullName.value =
                            (p.prename || '') +
                            (p.name || '') +
                            ' ' +
                            (p.surname || '');
                    }

                    var fn = document.getElementsByName('firstname');
                    if (fn.length) fn[0].value = p.name || '';

                    var ln = document.getElementsByName('lastname');
                    if (ln.length) ln[0].value = p.surname || '';

                    if (ageInput) ageInput.value = p.age_year || '';

                    var genderVal = '';
                    if (p.sex === 'M') genderVal = 'ชาย';
                    if (p.sex === 'F') genderVal = 'หญิง';

                    if (genderVal) {
                        var radio = document.querySelector(
                            'input[name="gender"][value="' + genderVal + '"]'
                        );
                        if (radio) {
                            radio.checked = true;
                            syncGenderButtons();
                        }
                    }
                    // Credit name (ชื่อสิทธิ)
                    var creditInput = document.getElementById('credit_name');
                    if (creditInput) {
                        creditInput.value = p.credit_name || '';
                    }

                    // Religion name (ศาสนา)
                    var religionInput = document.getElementById('religion_name');
                    if (religionInput) {
                        religionInput.value = p.religion_name || '';
                    }


                    if (statusText) {
                        statusText.innerHTML = 'ซิงค์สำเร็จ';
                        statusText.className = 'form-text text-success';
                    }
                } else {
                    if (statusText) {
                        statusText.innerHTML = 'ไม่พบข้อมูลในระบบ PMK';
                        statusText.className = 'form-text text-danger';
                    }
                }
            })
            .catch(function () {
                if (statusText) {
                    statusText.innerHTML = 'เชื่อมต่อ API ไม่สำเร็จ';
                    statusText.className = 'form-text text-danger';
                }
            })
            .finally(function () {
                btnSync.disabled = false;
                btnSync.innerHTML =
                    '<i class="bi bi-arrow-repeat"></i> ซิงค์ข้อมูล';
            });
        });
    }
});
function validateTimeOrder(startTime, endTime, message) {
    if (startTime && endTime) {
        if (new Date(endTime) < new Date(startTime)) {
            alert("⚠️ " + message);
            return false;
        }
    }
    return true;
}

// ตัวอย่างการใช้ในหน้า Cardiac Cath
// validateTimeOrder(hospital_arrival, puncture_time, "เวลา Puncture ต้องไม่เกิดก่อนเวลาถึง รพ.");
