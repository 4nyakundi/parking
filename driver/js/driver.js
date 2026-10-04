/**
 * Mombasa Mall Basement Parking — Driver Mobile Self Sign-In Logic
 * v2.0 — Flat single-form submit. Removed multi-step goToStep() flow.
 * Fast, offline-tolerant, remembers repeat visitors via localStorage.
 */

let selectedDestination = '';

document.addEventListener('DOMContentLoaded', () => {
    // Auto-fill from localStorage for repeat mall visitors
    const savedPlate = localStorage.getItem('mm_last_plate') || '';
    const savedName  = localStorage.getItem('mm_last_name')  || '';
    const savedPhone = localStorage.getItem('mm_last_phone') || '';

    const plateInput = document.getElementById('inputPlate');
    const nameInput  = document.getElementById('inputName');
    const phoneInput = document.getElementById('inputPhone');

    if (plateInput && savedPlate) plateInput.value = savedPlate;
    if (nameInput  && savedName)  nameInput.value  = savedName;
    if (phoneInput && savedPhone) phoneInput.value = savedPhone;

    // Plate: auto-uppercase, strip invalid chars, optional visual spacing
    if (plateInput) {
        plateInput.addEventListener('input', () => {
            let val = plateInput.value.toUpperCase().replace(/[^A-Z0-9 ]/g, '');
            plateInput.value = val;
            document.getElementById('plateError').style.display = 'none';
        });
    }

    if (phoneInput) {
        phoneInput.addEventListener('input', () => {
            document.getElementById('phoneError').style.display = 'none';
        });
    }

    // Allow pressing Enter in any input to submit
    document.getElementById('driverForm')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); submitDriverSignIn(); }
    });
});

/**
 * Destination tile selection
 */
function pickDest(name, el) {
    selectedDestination = name;
    document.querySelectorAll('.dest-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('destError').style.display = 'none';
}

/**
 * Validate and submit the flat single-form.
 */
async function submitDriverSignIn() {
    const plateInput = document.getElementById('inputPlate');
    const nameInput  = document.getElementById('inputName');
    const phoneInput = document.getElementById('inputPhone');
    const consent    = document.getElementById('chkConsent')?.checked;
    const hp         = document.getElementById('hpWebsite')?.value || '';

    const plate = (plateInput?.value || '').trim();
    const name  = (nameInput?.value  || '').trim();
    const phone = (phoneInput?.value || '').trim();

    let valid = true;

    // Validate plate (must have at least 5 alphanumeric chars)
    const cleanPlate = plate.replace(/[^A-Z0-9]/g, '');
    if (cleanPlate.length < 5) {
        document.getElementById('plateError').style.display = 'block';
        plateInput?.focus();
        valid = false;
    }

    // Validate name (at least 2 chars)
    if (!name || name.length < 2) {
        alert('Please enter your full name.');
        nameInput?.focus();
        valid = false;
    }

    // Validate phone (at least 9 digits)
    if (valid) {
        const cleanPhone = phone.replace(/[^0-9]/g, '');
        if (cleanPhone.length < 9) {
            document.getElementById('phoneError').style.display = 'block';
            phoneInput?.focus();
            valid = false;
        }
    }

    // Validate destination
    if (valid && !selectedDestination) {
        document.getElementById('destError').style.display = 'block';
        valid = false;
    }

    if (valid && !consent) {
        alert('Please check the Data Protection consent box to proceed.');
        valid = false;
    }

    if (!valid) return;

    const btn = document.getElementById('btnSubmitSignIn');
    if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }

    // Persist for repeat visits
    localStorage.setItem('mm_last_plate', plate);
    localStorage.setItem('mm_last_name',  name);
    localStorage.setItem('mm_last_phone', phone);

    const payload = {
        plate_number:  plate,
        driver_name:   name,
        driver_phone:  phone,
        destination:   selectedDestination,
        consent:       consent,
        hp_website:    hp,
    };

    try {
        const res  = await fetch('../api/driver/submit.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const json = await res.json();

        if (json.ok) {
            // Show the success screen
            const plate_display = json.data?.formatted_plate || plate;
            document.getElementById('successPlate').textContent = plate_display;
            document.getElementById('driverForm').style.display = 'none';
            const box = document.getElementById('successBox');
            if (box) {
                box.classList.add('show');
                if (window.gsap) {
                    gsap.from(box, { opacity: 0, y: 20, duration: 0.3, ease: 'power2.out' });
                }
            }
        } else {
            alert(json.error || 'Failed to submit check-in. Please try again.');
            if (btn) { btn.disabled = false; btn.textContent = 'Check In My Vehicle ✓'; }
        }
    } catch (err) {
        alert('Connection error. Please drive to the Guard booth for direct entry.');
        if (btn) { btn.disabled = false; btn.textContent = 'Check In My Vehicle ✓'; }
    }
}

/**
 * Reset form to allow a new sign-in.
 */
function resetForm() {
    selectedDestination = '';
    document.querySelectorAll('.dest-card').forEach(c => c.classList.remove('selected'));

    const form = document.getElementById('driverForm');
    const box  = document.getElementById('successBox');
    const btn  = document.getElementById('btnSubmitSignIn');

    if (form) { form.style.display = 'block'; form.reset(); }
    if (box)  { box.classList.remove('show'); }
    if (btn)  { btn.disabled = false; btn.textContent = 'Check In My Vehicle ✓'; }

    // Restore saved plate for convenience
    const savedPlate = localStorage.getItem('mm_last_plate') || '';
    const plateInput = document.getElementById('inputPlate');
    if (plateInput && savedPlate) plateInput.value = savedPlate;
}
