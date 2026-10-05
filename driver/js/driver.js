/**
 * Mombasa Mall Basement Parking — Driver Mobile Self Sign-In Logic
 * v4.0 — Full 48-Store Directory (Ground, 1st, 2nd, 3rd Floor & Basement)
 * Offline-first, instant search, floor tabs filter, remembers repeat visitors.
 */

let selectedDestination = '';
let activeFloor = 'ALL';
let storesList = [];

// Fallback catalog of all 48 stores across all levels of Mombasa Mall
const DEFAULT_STORES = [
    // Ground Floor (Level G)
    { id: 1, name: 'G-01 Naivas Supermarket', unit_code: 'G-01', floor_level: 'Ground Floor', category: 'Hypermarket' },
    { id: 2, name: 'G-02 Chicken Inn', unit_code: 'G-02', floor_level: 'Ground Floor', category: 'Fast Food' },
    { id: 3, name: 'G-03 Creamy Inn', unit_code: 'G-03', floor_level: 'Ground Floor', category: 'Fast Food' },
    { id: 4, name: 'G-04 Pizza Inn', unit_code: 'G-04', floor_level: 'Ground Floor', category: 'Fast Food' },
    { id: 47, name: 'Mall Administration & Management', unit_code: 'ADM', floor_level: 'Ground Floor', category: 'Management' },

    // 1st Floor (Level 1)
    { id: 5, name: 'F-01 Naivas First Floor', unit_code: 'F-01', floor_level: '1st Floor', category: 'Department Store' },
    { id: 6, name: 'F-02 Hallahulah Game Shop', unit_code: 'F-02', floor_level: '1st Floor', category: 'Gaming' },
    { id: 7, name: 'F-03 Daliah Human Wigs', unit_code: 'F-03', floor_level: '1st Floor', category: 'Beauty' },
    { id: 8, name: 'F-04 Payless', unit_code: 'F-04', floor_level: '1st Floor', category: 'Fashion & Shoes' },
    { id: 9, name: 'F-05 Grand Computer', unit_code: 'F-05', floor_level: '1st Floor', category: 'Tech & PC' },
    { id: 10, name: 'F-06 Tickles and Giggles', unit_code: 'F-06', floor_level: '1st Floor', category: 'Kids Apparel' },
    { id: 11, name: 'F-07 Namada Healthcare', unit_code: 'F-07', floor_level: '1st Floor', category: 'Healthcare' },
    { id: 12, name: 'F-08 Online Holidays', unit_code: 'F-08', floor_level: '1st Floor', category: 'Travel' },
    { id: 13, name: 'F-09 West 11', unit_code: 'F-09', floor_level: '1st Floor', category: 'Streetwear' },
    { id: 14, name: 'F-10 7day Mensware', unit_code: 'F-10', floor_level: '1st Floor', category: 'Menswear' },
    { id: 15, name: 'F-11 KG Cosmetics', unit_code: 'F-11', floor_level: '1st Floor', category: 'Beauty & Perfumes' },
    { id: 16, name: 'F-12 Lovisa', unit_code: 'F-12', floor_level: '1st Floor', category: 'Jewellery' },
    { id: 17, name: 'F-13 Fakri Timezone', unit_code: 'F-13', floor_level: '1st Floor', category: 'Watches & Eyewear' },
    { id: 18, name: 'F-14 Dendri (Denri Africa)', unit_code: 'F-14', floor_level: '1st Floor', category: 'Leather Goods' },
    { id: 19, name: 'F-15 NCBA Bank', unit_code: 'F-15', floor_level: '1st Floor', category: 'Banking' },
    { id: 20, name: 'F-16 Diamond Tech', unit_code: 'F-16', floor_level: '1st Floor', category: 'Security Tech' },
    { id: 21, name: 'F-17 Africa Collectives', unit_code: 'F-17', floor_level: '1st Floor', category: 'Curios & Artefacts' },

    // 2nd Floor (Level 2)
    { id: 22, name: 'S-01 Play On (Game Area)', unit_code: 'S-01', floor_level: '2nd Floor', category: 'Entertainment' },
    { id: 23, name: 'S-02 Creamy / Chicken / Pizza Inn Kiosk', unit_code: 'S-02', floor_level: '2nd Floor', category: 'Fast Food' },
    { id: 24, name: 'S-03 Jumia / Skyve Studio / Xtigi Service Center', unit_code: 'S-03', floor_level: '2nd Floor', category: 'Hub & Tech' },
    { id: 25, name: 'S-04 World Designers', unit_code: 'S-04', floor_level: '2nd Floor', category: 'Tailoring & Couture' },
    { id: 26, name: 'S-05 SAS Beauty', unit_code: 'S-05', floor_level: '2nd Floor', category: 'Salon & Nails' },
    { id: 27, name: 'S-06 Novum', unit_code: 'S-06', floor_level: '2nd Floor', category: 'Spa Sanctuary' },
    { id: 28, name: 'S-07 Ident Smile', unit_code: 'S-07', floor_level: '2nd Floor', category: 'Dental Clinic' },
    { id: 29, name: 'S-08 Tawal ICT Solution', unit_code: 'S-08', floor_level: '2nd Floor', category: 'Enterprise IT' },
    { id: 30, name: 'S-09 Mintos Salon Spa Barbershop', unit_code: 'S-09', floor_level: '2nd Floor', category: 'Grooming & Barber' },
    { id: 31, name: 'S-10 SSB', unit_code: 'S-10', floor_level: '2nd Floor', category: 'Womens Boutique' },
    { id: 32, name: 'S-11 Rudra', unit_code: 'S-11', floor_level: '2nd Floor', category: 'Ethnic Wear' },
    { id: 33, name: 'S-12 West 11 Sports', unit_code: 'S-12', floor_level: '2nd Floor', category: 'Sportswear' },
    { id: 34, name: 'S-13 Osona Yarns', unit_code: 'S-13', floor_level: '2nd Floor', category: 'Crafts & Yarns' },
    { id: 35, name: 'S-14 Afribot Robotics', unit_code: 'S-14', floor_level: '2nd Floor', category: 'STEM Academy' },
    { id: 36, name: 'S-15 Luxe Kaftan', unit_code: 'S-15', floor_level: '2nd Floor', category: 'Bridal & Modest' },
    { id: 37, name: 'S-16 Malkia', unit_code: 'S-16', floor_level: '2nd Floor', category: 'Modest Fashion' },
    { id: 38, name: 'S-17 Michael Boutique', unit_code: 'S-17', floor_level: '2nd Floor', category: 'Cocktail & Office' },
    { id: 39, name: 'S-18 Oraimo', unit_code: 'S-18', floor_level: '2nd Floor', category: 'Smart Accessories' },
    { id: 40, name: 'S-19 Keswick', unit_code: 'S-19', floor_level: '2nd Floor', category: 'Books & Gifts' },

    // 3rd Floor (Level 3)
    { id: 41, name: 'T-01 Gamers Vault', unit_code: 'T-01', floor_level: '3rd Floor', category: 'Esports Lounge' },
    { id: 42, name: 'T-02 Legacy Gym', unit_code: 'T-02', floor_level: '3rd Floor', category: 'Gym & Fitness' },
    { id: 43, name: 'T-03 Westerwelle Foundation / Startup Haus', unit_code: 'T-03', floor_level: '3rd Floor', category: 'Tech Incubator' },
    { id: 44, name: 'T-04 Equity Afia CBD', unit_code: 'T-04', floor_level: '3rd Floor', category: 'Medical Center' },

    // Basement (Level B1)
    { id: 45, name: 'B-01 Valet & Parking Services', unit_code: 'B-01', floor_level: 'Basement', category: 'Valet Concierge' },
    { id: 46, name: 'B-02 Secure Basement Parking Deck', unit_code: 'B-02', floor_level: 'Basement', category: 'Parking Deck' },
    { id: 48, name: 'Loading Bay & Deliveries', unit_code: 'LOG', floor_level: 'Basement', category: 'Logistics' }
];

document.addEventListener('DOMContentLoaded', () => {
    // 1. Auto-fill from localStorage for repeat mall visitors
    const savedPlate = localStorage.getItem('mm_last_plate') || '';
    const savedName  = localStorage.getItem('mm_last_name')  || '';
    const savedPhone = localStorage.getItem('mm_last_phone') || '';

    const plateInput = document.getElementById('inputPlate');
    const nameInput  = document.getElementById('inputName');
    const phoneInput = document.getElementById('inputPhone');

    if (plateInput && savedPlate) plateInput.value = savedPlate;
    if (nameInput  && savedName)  nameInput.value  = savedName;
    if (phoneInput && savedPhone) phoneInput.value = savedPhone;

    // 2. Input format listeners
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

    // 3. Enter key submits
    document.getElementById('driverForm')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); submitDriverSignIn(); }
    });

    // 4. Initialize stores catalog
    storesList = [...DEFAULT_STORES];
    renderStoresGrid();

    // 5. Fetch updated stores from backend API (live sync)
    loadDestinationsFromApi();

    // 6. Bind floor filter tabs
    document.querySelectorAll('#driverFloorTabs .floor-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('#driverFloorTabs .floor-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeFloor = tab.getAttribute('data-floor') || 'ALL';
            renderStoresGrid();
        });
    });

    // 7. Bind search filter input
    const searchInput = document.getElementById('destSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            renderStoresGrid();
        });
    }
});

/**
 * Fetch stores from backend API and update grid
 */
async function loadDestinationsFromApi() {
    try {
        const res = await fetch('../api/driver/destinations.php');
        const json = await res.json();
        if (json.ok && Array.isArray(json.data) && json.data.length > 0) {
            storesList = json.data;
            renderStoresGrid();
        }
    } catch (err) {
        // Silently use DEFAULT_STORES if offline
    }
}

/**
 * Render the stores grid based on active floor filter and search query
 */
function renderStoresGrid() {
    const grid = document.getElementById('driverDestGrid');
    if (!grid) return;

    const query = (document.getElementById('destSearchInput')?.value || '').trim().toLowerCase();

    const filtered = storesList.filter(s => {
        // Floor filter
        if (activeFloor !== 'ALL') {
            if ((s.floor_level || '').toLowerCase() !== activeFloor.toLowerCase()) {
                return false;
            }
        }
        // Search query
        if (query.length > 0) {
            const name = (s.name || '').toLowerCase();
            const code = (s.unit_code || '').toLowerCase();
            const cat  = (s.category || '').toLowerCase();
            const desc = (s.description || '').toLowerCase();
            return name.includes(query) || code.includes(query) || cat.includes(query) || desc.includes(query);
        }
        return true;
    });

    grid.innerHTML = '';

    if (filtered.length === 0) {
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:16px; color:var(--text-mut); font-size:12px;">No matching stores found.</div>';
        return;
    }

    filtered.forEach(s => {
        const card = document.createElement('div');
        const isSelected = selectedDestination === s.name;
        card.className = 'dest-card' + (isSelected ? ' selected' : '');
        card.setAttribute('data-name', s.name);
        card.onclick = () => pickDest(s.name, card, s);

        const badgeHtml = s.unit_code ? `<span class="unit-badge">${s.unit_code}</span>` : '';
        const catHtml   = s.category ? `<span class="dest-category">${s.category}</span>` : '';

        card.innerHTML = `
            ${badgeHtml}
            <span>${s.name}</span>
            ${catHtml}
        `;
        grid.appendChild(card);
    });
}

/**
 * Destination tile selection
 */
function pickDest(name, el, storeObj) {
    selectedDestination = name;
    document.querySelectorAll('.dest-card').forEach(c => c.classList.remove('selected'));
    if (el) el.classList.add('selected');

    document.getElementById('destError').style.display = 'none';

    // Update confirmation banner
    const banner = document.getElementById('selectedDestBanner');
    const label = document.getElementById('selectedDestText');
    if (banner && label) {
        label.textContent = name;
        banner.style.display = 'block';
    }
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
        document.getElementById('destSearchInput')?.focus();
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

            // Log Firebase Analytics telemetry
            if (typeof window.logParkingEvent === 'function') {
                window.logParkingEvent('driver_signin_success', {
                    plate_number: plate_display,
                    destination: selectedDestination
                });
            }
        } else {
            alert(json.error || 'Failed to submit check-in. Please try again.');
            if (btn) { btn.disabled = false; btn.textContent = 'Submit Sign-In Request'; }
        }
    } catch (err) {
        alert('Connection error. Please drive to the Guard booth for direct entry.');
        if (btn) { btn.disabled = false; btn.textContent = 'Submit Sign-In Request'; }
    }
}

/**
 * Reset form to allow a new sign-in.
 */
function resetForm() {
    selectedDestination = '';
    activeFloor = 'ALL';
    document.querySelectorAll('.dest-card').forEach(c => c.classList.remove('selected'));

    const banner = document.getElementById('selectedDestBanner');
    if (banner) banner.style.display = 'none';

    const searchInput = document.getElementById('destSearchInput');
    if (searchInput) searchInput.value = '';

    document.querySelectorAll('#driverFloorTabs .floor-tab').forEach(t => {
        t.classList.toggle('active', t.getAttribute('data-floor') === 'ALL');
    });
    renderStoresGrid();

    const form = document.getElementById('driverForm');
    const box  = document.getElementById('successBox');
    const btn  = document.getElementById('btnSubmitSignIn');

    if (form) { form.style.display = 'block'; form.reset(); }
    if (box)  { box.classList.remove('show'); }
    if (btn)  { btn.disabled = false; btn.textContent = 'Submit Sign-In Request'; }

    // Restore saved plate for convenience
    const savedPlate = localStorage.getItem('mm_last_plate') || '';
    const plateInput = document.getElementById('inputPlate');
    if (plateInput && savedPlate) plateInput.value = savedPlate;
}
