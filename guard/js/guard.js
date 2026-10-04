/**
 * Mombasa Mall Basement Parking - Guard Tablet Controller
 * Fully responsive, high-contrast, offline-first, procedural Web Audio chimes, GSAP animations.
 */

// Procedural Web Audio Chime Generator (No external MP3 files needed)
let audioCtx = null;
let soundEnabled = true;

function initAudio() {
    if (!audioCtx) {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (AudioContext) {
            audioCtx = new AudioContext();
        }
    }
    if (audioCtx && audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
}

function playDingSound() {
    if (!soundEnabled) return;
    initAudio();
    if (!audioCtx) return;

    try {
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
        osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.15); // A5

        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.6);

        osc.connect(gain);
        gain.connect(audioCtx.destination);

        osc.start();
        osc.stop(audioCtx.currentTime + 0.6);
    } catch (e) {
        console.warn('Audio play error:', e);
    }
}

function playSuccessChime() {
    if (!soundEnabled) return;
    initAudio();
    if (!audioCtx) return;

    try {
        const now = audioCtx.currentTime;
        const notes = [523.25, 659.25, 783.99, 1046.50]; // C5, E5, G5, C6
        notes.forEach((freq, idx) => {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(freq, now + (idx * 0.08));

            gain.gain.setValueAtTime(0.25, now + (idx * 0.08));
            gain.gain.exponentialRampToValueAtTime(0.001, now + (idx * 0.08) + 0.4);

            osc.connect(gain);
            gain.connect(audioCtx.destination);

            osc.start(now + (idx * 0.08));
            osc.stop(now + (idx * 0.08) + 0.4);
        });
    } catch (e) {}
}

function playAlertChime() {
    if (!soundEnabled) return;
    initAudio();
    if (!audioCtx) return;

    try {
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(350, audioCtx.currentTime);
        osc.frequency.setValueAtTime(280, audioCtx.currentTime + 0.15);

        gain.gain.setValueAtTime(0.35, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.4);

        osc.connect(gain);
        gain.connect(audioCtx.destination);

        osc.start();
        osc.stop(audioCtx.currentTime + 0.4);
    } catch (e) {}
}

// Global State
const STATE = {
    user: null,
    csrfToken: '',
    currentTab: 'entering', // 'entering', 'leaving', 'inside'
    lastEventId: 0,
    pollTimer: null,
    clockTimer: null,
    enteredPin: '',
    latestEntrancePlate: '',
    activeExitSession: null,
    destinations: [],
    wizard: {
        step: 1,
        plate: '',
        name: '',
        phone: '',
        destination: '',
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // 1. Live Clock
    updateClock();
    STATE.clockTimer = setInterval(updateClock, 1000);

    // 2. Sound Toggle
    const btnSound = document.getElementById('btnSoundToggle');
    if (btnSound) {
        btnSound.addEventListener('click', () => {
            soundEnabled = !soundEnabled;
            btnSound.textContent = soundEnabled ? t('sound_on') : t('sound_muted');
            if (soundEnabled) playDingSound();
        });
    }

    // 3. Language Toggle
    const btnLang = document.getElementById('btnLangToggle');
    if (btnLang) {
        btnLang.addEventListener('click', toggleLanguage);
    }

    // 4. Tab Navigation
    document.querySelectorAll('.mode-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const tabName = tab.getAttribute('data-tab');
            switchTab(tabName);
        });
    });

    // 5. Wizard Navigation
    initWizard();

    // 6. Check Auth Session
    checkAuthSession();

    // 7. Load Destinations
    loadDestinations();

    // 8. Bind Keypad for PIN Login
    initPinKeypad();

    // 9. Leaving search input
    const exitSearch = document.getElementById('exitSearchInput');
    if (exitSearch) {
        exitSearch.addEventListener('input', debounce(() => {
            searchExitSessions(exitSearch.value.trim());
        }, 300));
    }
});

function updateClock() {
    const el = document.getElementById('liveClock');
    if (el) {
        const now = new Date();
        el.textContent = now.toLocaleTimeString('en-GB', { hour12: false });
    }
}

// --------------------------------------------------------------------
// Authentication Check & PIN Login Modal
// --------------------------------------------------------------------
async function checkAuthSession() {
    try {
        const res = await fetch('../api/auth/me.php');
        const data = await res.json();

        if (data.ok && data.authenticated && data.user) {
            setAuthenticatedUser(data.user);
            STATE.csrfToken = data.csrf_token;
            startPolling();
            loadPendingRequests();
            loadActiveSessions();
        } else {
            showPinModal();
        }
    } catch (err) {
        console.error('Auth verification error:', err);
        showPinModal();
    }
}

function showPinModal() {
    STATE.enteredPin = '';
    updatePinDots();
    const modal = document.getElementById('pinLoginModal');
    if (modal) modal.classList.add('open');
}

function hidePinModal() {
    const modal = document.getElementById('pinLoginModal');
    if (modal) modal.classList.remove('open');
}

function initPinKeypad() {
    document.querySelectorAll('.key-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            initAudio(); // Satisfy browser autoplay policy
            const val = btn.getAttribute('data-val');

            if (val === 'clear') {
                STATE.enteredPin = '';
                updatePinDots();
            } else if (val === 'enter') {
                submitPinLogin();
            } else if (val) {
                if (STATE.enteredPin.length < 6) {
                    STATE.enteredPin += val;
                    updatePinDots();
                    if (STATE.enteredPin.length === 4) {
                        // Auto-submit after 4 digits
                        submitPinLogin();
                    }
                }
            }
        });
    });

    // Also support keyboard typing for testing
    document.addEventListener('keydown', (e) => {
        const modal = document.getElementById('pinLoginModal');
        if (modal && modal.classList.contains('open')) {
            if (e.key >= '0' && e.key <= '9') {
                if (STATE.enteredPin.length < 6) {
                    STATE.enteredPin += e.key;
                    updatePinDots();
                    if (STATE.enteredPin.length === 4) submitPinLogin();
                }
            } else if (e.key === 'Backspace') {
                STATE.enteredPin = STATE.enteredPin.slice(0, -1);
                updatePinDots();
            } else if (e.key === 'Enter') {
                submitPinLogin();
            }
        }
    });
}

function updatePinDots() {
    const dots = document.querySelectorAll('.pin-dot');
    dots.forEach((dot, idx) => {
        if (idx < STATE.enteredPin.length) {
            dot.classList.add('filled');
        } else {
            dot.classList.remove('filled');
        }
    });
}

async function submitPinLogin() {
    if (STATE.enteredPin.length < 4) return;

    try {
        const res = await fetch('../api/auth/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'pin', pin: STATE.enteredPin }),
        });
        const json = await res.json();

        if (json.ok && json.data) {
            setAuthenticatedUser(json.data);
            hidePinModal();
            playDingSound();
            startPolling();
            loadPendingRequests();
            loadActiveSessions();
        } else {
            playAlertChime();
            showToast(json.error || 'Incorrect PIN. Try again.');
            STATE.enteredPin = '';
            updatePinDots();
        }
    } catch (e) {
        showToast('Login connection failed. Check XAMPP server.');
    }
}

function setAuthenticatedUser(user) {
    STATE.user = user;
    const nameEl = document.getElementById('guardNameDisplay');
    if (nameEl) nameEl.textContent = user.full_name;
}

// --------------------------------------------------------------------
// Tab Switching with Animations
// --------------------------------------------------------------------
function switchTab(tabName) {
    STATE.currentTab = tabName;
    document.querySelectorAll('.mode-tab').forEach(tab => {
        tab.classList.toggle('active', tab.getAttribute('data-tab') === tabName);
    });

    const stage = document.getElementById('mainStage');
    if (stage) {
        stage.classList.remove('leaving-theme', 'inside-theme');
        if (tabName === 'leaving') stage.classList.add('leaving-theme');
        if (tabName === 'inside') stage.classList.add('inside-theme');
    }

    document.getElementById('viewEntering').style.display = tabName === 'entering' ? 'block' : 'none';
    document.getElementById('viewLeaving').style.display = tabName === 'leaving' ? 'block' : 'none';
    document.getElementById('viewInside').style.display = tabName === 'inside' ? 'block' : 'none';

    if (tabName === 'inside') {
        loadActiveSessions();
    }
}

// --------------------------------------------------------------------
// Short Polling (Every 2 seconds)
// --------------------------------------------------------------------
function startPolling() {
    if (STATE.pollTimer) clearInterval(STATE.pollTimer);
    runPoll();
    STATE.pollTimer = setInterval(runPoll, 2000);
}

async function runPoll() {
    try {
        const res = await fetch(`../api/poll.php?since=${STATE.lastEventId}`);
        const data = await res.json();

        if (data.ok) {
            document.getElementById('offlineBanner').classList.remove('active');
            STATE.lastEventId = data.last_event_id;

            // Update Occupancy Counters
            if (data.stats) {
                updateOccupancyDisplay(data.stats);
            }

            // Process New Events
            if (data.events && data.events.length > 0) {
                data.events.forEach(evt => handleServerEvent(evt));
            }
        }
    } catch (err) {
        // Network drop: Show clear offline notice without crashing
        document.getElementById('offlineBanner').classList.add('active');
    }
}

function updateOccupancyDisplay(stats) {
    const totalEl   = document.getElementById('statTotal');
    const occEl     = document.getElementById('statOccupied');
    const availEl   = document.getElementById('statAvailable');
    const cardAvail = document.getElementById('cardAvailable');
    const fullBadge = document.getElementById('parkingFullBadge');

    if (totalEl)  totalEl.textContent  = stats.capacity;
    if (occEl)    occEl.textContent    = stats.occupied;
    if (availEl)  availEl.textContent  = stats.available;

    if (cardAvail) {
        cardAvail.classList.remove('amber', 'red');
        if (stats.available === 0) {
            cardAvail.classList.add('red');
            if (fullBadge) fullBadge.style.display = 'inline-block';
        } else if (stats.available <= 5) {
            cardAvail.classList.add('red');
            if (fullBadge) fullBadge.style.display = 'none';
        } else if (stats.available <= 15) {
            cardAvail.classList.add('amber');
            if (fullBadge) fullBadge.style.display = 'none';
        } else {
            if (fullBadge) fullBadge.style.display = 'none';
        }
    }

    const countBadge = document.getElementById('pendingCountBadge');
    if (countBadge) {
        countBadge.textContent = stats.pending_count || 0;
        countBadge.style.display = stats.pending_count > 0 ? 'inline-block' : 'none';
    }

    // Left-panel live stats
    const set = (id, val, fallback) => {
        const el = document.getElementById(id);
        if (el) el.textContent = (val !== undefined && val !== null) ? val : fallback;
    };
    set('pnlTodayIn',  stats.today_entries,   '--');
    set('pnlTodayOut', stats.today_exits,      '--');
    set('pnlOverstay', stats.overstays,        '0');
    set('pnlAvgDwell', stats.avg_dwell_minutes ? stats.avg_dwell_minutes + ' min' : '--');
    set('pnlPending',  stats.pending_count,    '0');
}

/** Append an entry to the left-panel activity log */
function logActivity(message, type = 'ok') {
    const log = document.getElementById('activityLog');
    if (!log) return;
    const now  = new Date();
    const time = now.toLocaleTimeString('en-GB', { hour12: false });
    const entry = document.createElement('div');
    entry.className = `panel-log-entry log-${type}`;
    entry.innerHTML = `<span class="log-time">${time}</span> ${message}`;
    log.insertBefore(entry, log.firstChild);
    while (log.children.length > 40) log.removeChild(log.lastChild);
}

// --------------------------------------------------------------------
// Event Router
// --------------------------------------------------------------------
function handleServerEvent(event) {
    const p = event.payload;

    switch (event.type) {
        case 'new_request':
            playDingSound();
            prependPendingCard(p);
            logActivity(`New request: ${p.plate_number || 'Unknown'}`, 'ok');
            break;

        case 'plate_verified_entrance':
            markCardVerified(p.request_id);
            logActivity(`ALPR verified: ${p.plate_number || ''}`, 'ok');
            break;

        case 'alpr_banner_entrance':
            showEntranceAlprBanner(p);
            logActivity(`Camera detected: ${p.formatted_plate || p.plate_number}`, 'ok');
            break;

        case 'exit_candidate':
            playDingSound();
            showExitCandidateCard(p);
            logActivity(`Exit candidate: ${p.formatted_plate || p.plate_number}`, 'warn');
            break;

        case 'session_created':
            removePendingCard(p.plate_number);
            logActivity(`Session created: ${p.plate_number || ''} — Ticket #${p.ticket_id || ''}`, 'ok');
            break;

        case 'session_completed':
            logActivity(`Exit cleared: ${p.formatted_plate || ''} — ${p.duration_text || ''}`, 'ok');
            if (STATE.activeExitSession && STATE.activeExitSession.ticket_id === p.ticket_id) {
                document.getElementById('exitCandidateArea').innerHTML = `
                    <div style="text-align:center; padding:28px; color:var(--green-lt); border:1px solid var(--border); border-radius:8px;">
                        <div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;">Session Cleared</div>
                        <div style="font-size:26px;font-weight:900;font-family:monospace;margin:8px 0;">${p.formatted_plate}</div>
                        <div style="font-size:13px;color:var(--text-muted);">Duration: ${p.duration_text}</div>
                    </div>
                `;
            }
            break;

        case 'request_rejected':
            removePendingCard(p.plate_number);
            logActivity(`Request declined: ${p.plate_number || ''}`, 'warn');
            break;
    }
}

function showEntranceAlprBanner(p) {
    STATE.latestEntrancePlate = p.plate_number;
    const banner = document.getElementById('alprEntranceBanner');
    const plateText = document.getElementById('alprBannerPlate');
    const meta = document.getElementById('alprBannerMeta');

    if (banner && plateText) {
        plateText.textContent = p.formatted_plate;
        if (meta) meta.textContent = `Confidence: ${(p.confidence * 100).toFixed(0)}% at ${p.time}`;
        banner.style.display = 'flex';

        // Animate banner with GSAP
        if (window.gsap) {
            gsap.fromTo(banner, { y: -20, opacity: 0 }, { y: 0, opacity: 1, duration: 0.4 });
        }
    }
}

// --------------------------------------------------------------------
// Pending Queue (Cars Entering)
// --------------------------------------------------------------------
async function loadPendingRequests() {
    try {
        const res = await fetch('../api/requests/pending.php');
        const json = await res.json();

        const grid = document.getElementById('pendingCardsGrid');
        if (!grid) return;

        if (!json.ok || !json.data || json.data.length === 0) {
            grid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding:60px; color:#64748b; font-size:22px;">
                ${t('no_pending')}
            </div>`;
            return;
        }

        grid.innerHTML = '';
        json.data.forEach(item => {
            grid.appendChild(createPendingCardElement(item));
        });

        // Stagger animation with GSAP
        if (window.gsap) {
            gsap.from('.car-card', { opacity: 0, y: 30, duration: 0.4, stagger: 0.08 });
        }
    } catch (e) {
        console.error('Error loading pending requests:', e);
    }
}

function prependPendingCard(item) {
    const grid = document.getElementById('pendingCardsGrid');
    if (!grid) return;

    // Check if empty message is present
    if (grid.children.length === 1 && grid.children[0].textContent.includes('No cars waiting')) {
        grid.innerHTML = '';
    }

    const card = createPendingCardElement(item);
    grid.prepend(card);

    if (window.gsap) {
        gsap.from(card, { scale: 0.8, opacity: 0, duration: 0.4, ease: "back.out(1.7)" });
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function createPendingCardElement(item) {
    const card = document.createElement('div');
    const reqId = item.id || item.request_id;
    card.className = `car-card ${item.alpr_verified ? 'verified' : ''} ${item.category === 'blacklisted' ? 'blacklisted' : ''} ${item.category === 'vip' ? 'vip' : ''}`;
    card.id = `req-card-${reqId}`;
    card.setAttribute('data-plate', item.plate_number);

    let alertBadge = '';
    if (item.category === 'blacklisted') {
        alertBadge = `<div class="badge-alert">⚠️ ${t('blacklisted_alert')}: ${escapeHtml(item.category_notes || 'DO NOT ADMIT')}</div>`;
    } else if (item.category === 'vip') {
        alertBadge = `<div class="badge-alert" style="background:#7c3aed;">⭐ ${t('vip_alert')}</div>`;
    }

    const currentDriver = (item.driver_name && item.driver_name !== 'Visitor') ? item.driver_name : 'Visitor';
    const currentPhone  = item.raw_phone || item.driver_phone || '';
    const currentDest   = item.destination || 'Mombasa Mall';

    // Snapshot preview thumbnail if available
    let snapHtml = '';
    if (item.snapshot_url) {
        snapHtml = `<img src="../${item.snapshot_url}" class="card-snap-thumb" alt="Camera Snapshot" title="Click to view full photo" onclick="window.open('../${item.snapshot_url}', '_blank')">`;
    }

    // Prepare destination options from loaded mall store directory
    const allDests = (STATE.destinations && STATE.destinations.length > 0) ? STATE.destinations : [
        { name: 'Naivas Supermarket', floor_level: 'Ground Floor' },
        { name: 'NCBA Bank', floor_level: 'Ground Floor' },
        { name: 'Java House', floor_level: '1st Floor' },
        { name: 'Food Court', floor_level: '2nd Floor' },
        { name: 'Level 1 Retail', floor_level: '1st Floor' },
        { name: 'Level 2 Retail', floor_level: '2nd Floor' },
        { name: 'Basement Parking', floor_level: 'Basement' }
    ];

    let destOptionsHtml = `<option value="Mombasa Mall">Mombasa Mall (General)</option>`;
    allDests.forEach(d => {
        const isSel = (currentDest.toLowerCase() === d.name.toLowerCase());
        destOptionsHtml += `<option value="${escapeHtml(d.name)}" ${isSel ? 'selected' : ''}>${escapeHtml(d.name)}</option>`;
    });

    card.innerHTML = `
        <div class="card-header">
            <div style="display:flex; align-items:center; gap:10px;">
                ${snapHtml}
                <div>
                    <div class="card-plate">${item.formatted_plate}</div>
                    ${alertBadge}
                </div>
            </div>
            <div style="text-align:right;">
                ${item.source === 'alpr_camera' ? `<div class="cam-source-badge">📷 CAMERA</div>` : ''}
                ${item.alpr_verified ? `<div class="badge-verified" style="margin-top:4px;">✓ ${t('verified_badge')}</div>` : `<div style="font-size:12px; color:var(--text-muted); margin-top:4px;">${item.wait_text || 'Just now'}</div>`}
            </div>
        </div>

        <div class="card-quick-fill">
            <div class="card-input-row">
                <div class="card-field-group">
                    <span class="card-field-label">Driver Name</span>
                    <input type="text" class="card-quick-input" id="cardName_${reqId}" 
                           value="${escapeHtml(currentDriver)}" placeholder="Driver Name (e.g. John)" autocomplete="off">
                </div>
                <div class="card-field-group">
                    <span class="card-field-label">Mobile Number</span>
                    <input type="tel" class="card-quick-input" id="cardPhone_${reqId}" 
                           value="${escapeHtml(currentPhone)}" placeholder="07XX XXX XXX (Optional)" autocomplete="off">
                </div>
            </div>

            <div class="card-field-group">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="card-field-label">Destination</span>
                    <span style="font-size:10px; color:var(--text-muted); font-weight:700;">Tap quick pill or select:</span>
                </div>
                <div class="dest-pills-row">
                    <button type="button" class="dest-pill-btn ${currentDest.includes('Naivas') ? 'active' : ''}" 
                            onclick="selectCardPill(${reqId}, 'Naivas Supermarket', this)">🛒 Naivas</button>
                    <button type="button" class="dest-pill-btn ${currentDest.includes('Bank') || currentDest.includes('NCBA') ? 'active' : ''}" 
                            onclick="selectCardPill(${reqId}, 'NCBA Bank', this)">🏦 Bank / ATM</button>
                    <button type="button" class="dest-pill-btn ${currentDest.includes('Food') ? 'active' : ''}" 
                            onclick="selectCardPill(${reqId}, 'Food Court', this)">🍔 Food Court</button>
                    <button type="button" class="dest-pill-btn" 
                            onclick="selectCardPill(${reqId}, 'Level 1 Retail', this)">Level 1</button>
                    <button type="button" class="dest-pill-btn" 
                            onclick="selectCardPill(${reqId}, 'Level 2 Retail', this)">Level 2</button>
                    <button type="button" class="dest-pill-btn ${currentDest === 'Mombasa Mall' ? 'active' : ''}" 
                            onclick="selectCardPill(${reqId}, 'Mombasa Mall', this)">Other</button>
                </div>
                <select id="cardDest_${reqId}" class="card-dest-select" style="margin-top:5px;">
                    ${destOptionsHtml}
                </select>
            </div>
        </div>

        <div class="card-actions">
            <button class="btn-card-accept" onclick="acceptWithQuickDetails(${reqId})">
                <svg class="i-icon" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                ACCEPT &amp; PRINT TICKET
            </button>
            <button class="btn-card-reject" onclick="openRejectDialog(${reqId})">
                ✕ Decline
            </button>
        </div>
    `;

    return card;
}

function selectCardPill(reqId, destName, btnEl) {
    const card = document.getElementById(`req-card-${reqId}`);
    if (card) {
        card.querySelectorAll('.dest-pill-btn').forEach(b => b.classList.remove('active'));
        if (btnEl) btnEl.classList.add('active');

        const sel = document.getElementById(`cardDest_${reqId}`);
        if (sel) {
            let found = false;
            for (let i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value === destName) {
                    sel.selectedIndex = i;
                    found = true;
                    break;
                }
            }
            if (!found) {
                const opt = new Option(destName, destName, true, true);
                sel.add(opt);
            }
        }
    }
}

function markCardVerified(reqId) {
    const card = document.getElementById(`req-card-${reqId}`);
    if (card) {
        card.classList.add('verified');
        const badge = card.querySelector('.badge-verified');
        if (!badge) {
            const header = card.querySelector('.card-header');
            const newBadge = document.createElement('div');
            newBadge.className = 'badge-verified';
            newBadge.innerHTML = `✓ ${t('verified_badge')}`;
            header.appendChild(newBadge);
        }
    }
}

function removePendingCard(plate) {
    document.querySelectorAll('.car-card').forEach(card => {
        if (card.getAttribute('data-plate') === plate) {
            if (window.gsap) {
                gsap.to(card, {
                    scale: 0.8,
                    opacity: 0,
                    duration: 0.3,
                    onComplete: () => card.remove()
                });
            } else {
                card.remove();
            }
        }
    });
}

// --------------------------------------------------------------------
// Accept & Print Action with Guard Details
// --------------------------------------------------------------------
async function acceptWithQuickDetails(requestId) {
    const nameIn  = document.getElementById(`cardName_${requestId}`);
    const phoneIn = document.getElementById(`cardPhone_${requestId}`);
    const destIn  = document.getElementById(`cardDest_${requestId}`);

    const name  = nameIn ? nameIn.value.trim() : '';
    const phone = phoneIn ? phoneIn.value.trim() : '';
    const dest  = destIn ? destIn.value.trim() : 'Mombasa Mall';

    const card = document.getElementById(`req-card-${requestId}`);
    const acceptBtn = card ? card.querySelector('.btn-card-accept') : null;
    if (acceptBtn) {
        acceptBtn.disabled = true;
        acceptBtn.innerHTML = 'Printing Ticket...';
    }

    try {
        const res = await fetch('../api/gate/approve-session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                request_id: requestId,
                driver_name: name || 'Visitor',
                driver_phone: phone,
                destination: dest || 'Mombasa Mall',
            }),
        });
        const json = await res.json();

        if (json.ok) {
            playSuccessChime();

            if (window.confetti) {
                confetti({
                    particleCount: 60,
                    spread: 70,
                    origin: { y: 0.6 }
                });
            }

            if (json.data.print_status === 'printed') {
                showToast(`✓ Ticket #${json.data.ticket_id} Printed for ${json.data.formatted_plate}`);
            } else {
                showToast(`✓ Ticket #${json.data.ticket_id} Saved (${json.data.formatted_plate})`, true, json.data.session_id);
            }

            removePendingCard(json.data.plate_number);
        } else {
            if (acceptBtn) {
                acceptBtn.disabled = false;
                acceptBtn.innerHTML = `<svg class="i-icon" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> ACCEPT &amp; PRINT TICKET`;
            }
            showToast(json.error || 'Failed to approve session.');
        }
    } catch (e) {
        if (acceptBtn) {
            acceptBtn.disabled = false;
            acceptBtn.innerHTML = `<svg class="i-icon" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> ACCEPT &amp; PRINT TICKET`;
        }
        showToast('Network error while approving ticket.');
    }
}

// Legacy alias
function acceptAndPrint(requestId) {
    acceptWithQuickDetails(requestId);
}

// --------------------------------------------------------------------
// On-Demand Camera Plate Fetcher
// --------------------------------------------------------------------
async function triggerCameraPlateFetch() {
    const btn = document.getElementById('btnFetchCameraPlate');
    const btnText = document.getElementById('btnFetchCamText');
    const origText = btnText ? btnText.textContent : 'Fetch Camera Plate Now';

    if (btn) {
        btn.disabled = true;
        if (btnText) btnText.textContent = 'Scanning Camera...';
    }

    try {
        const res = await fetch('../api/gate/fetch-camera-plate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({}),
        });
        const json = await res.json();

        if (json.ok) {
            if (json.plate_found && json.data) {
                playDingSound();
                showToast(`✓ Camera detected plate: ${json.data.formatted_plate}`);
                await loadPendingRequests();
                setTimeout(() => {
                    const newCardInput = document.getElementById(`cardName_${json.data.request_id}`);
                    if (newCardInput) {
                        newCardInput.focus();
                        newCardInput.select();
                    }
                }, 300);
            } else if (json.is_duplicate) {
                showToast(`⚠️ Vehicle ${json.formatted_plate} is already inside.`);
            } else {
                showToast(json.message || 'Camera is live. Ready for approaching vehicle.');
            }

            const statusLabel = document.getElementById('camEntranceStatusLabel');
            if (statusLabel) {
                statusLabel.textContent = json.camera_online 
                    ? 'Entrance Camera (192.168.1.230): Online' 
                    : 'Entrance Camera: Connecting...';
            }
        } else {
            showToast(json.error || 'Failed to fetch camera plate.');
        }
    } catch (e) {
        showToast('Could not reach entrance camera endpoint.');
    } finally {
        if (btn) {
            btn.disabled = false;
            if (btnText) btnText.textContent = origText;
        }
    }
}

async function scanCameraIntoWizard() {
    const plateInput = document.getElementById('wizPlateInput');
    const nameInput  = document.getElementById('wizNameInput');

    try {
        const res = await fetch('../api/gate/fetch-camera-plate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({}),
        });
        const json = await res.json();

        if (json.ok && json.data && json.data.formatted_plate) {
            playDingSound();
            if (plateInput) plateInput.value = json.data.formatted_plate;
            if (nameInput) nameInput.focus();
            showToast(`✓ Camera scanned: ${json.data.formatted_plate}`);
        } else {
            showToast('Camera live snapshot captured. Enter plate to register.');
        }
    } catch (e) {
        showToast('Camera scan error.');
    }
}

// --------------------------------------------------------------------
// Reject Dialog
// --------------------------------------------------------------------
let pendingRejectId = null;

function openRejectDialog(requestId) {
    pendingRejectId = requestId;
    const modal = document.getElementById('rejectModal');
    if (modal) modal.classList.add('open');
}

function closeRejectDialog() {
    pendingRejectId = null;
    const modal = document.getElementById('rejectModal');
    if (modal) modal.classList.remove('open');
}

async function confirmReject(reason) {
    if (!pendingRejectId) return;

    try {
        const res = await fetch('../api/gate/reject.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: pendingRejectId, reason: reason }),
        });
        const json = await res.json();

        if (json.ok) {
            showToast('Request declined.');
            const card = document.getElementById(`req-card-${pendingRejectId}`);
            if (card) card.remove();
            closeRejectDialog();
        } else {
            showToast(json.error || 'Failed to reject.');
        }
    } catch (e) {
        showToast('Error sending rejection.');
    }
}

// --------------------------------------------------------------------
// Leaving Tab (Clear Exit & Search)
// --------------------------------------------------------------------
function showExitCandidateCard(session) {
    STATE.activeExitSession = session;
    const area = document.getElementById('exitCandidateArea');
    if (!area) return;

    area.innerHTML = `
        <div class="exit-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <span style="font-size:14px; font-weight:800; color:#f97316;">${t('cam_sees_out')}</span>
                    <div style="font-size:42px; font-weight:900; color:#fff; background:#000; padding:6px 20px; border-radius:8px; border:3px solid #f97316; display:inline-block; margin-top:6px;">
                        ${session.formatted_plate}
                    </div>
                </div>
                <div style="text-align:right;">
                    <span class="info-label">Ticket ID</span>
                    <div style="font-size:20px; font-weight:800; color:#38bdf8;">${session.ticket_id}</div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; background:#0f172a; padding:16px; border-radius:12px;">
                <div>
                    <span class="info-label">${t('driver')}</span>
                    <div class="info-val">${session.driver_name}</div>
                </div>
                <div>
                    <span class="info-label">${t('destination')}</span>
                    <div class="info-val">${session.destination}</div>
                </div>
                <div>
                    <span class="info-label">${t('parked_time')}</span>
                    <div class="info-val" style="color:#fbbf24; font-size:22px;">${session.duration_minutes} mins</div>
                </div>
            </div>

            <button class="btn-clear-exit-giant" onclick="executeClearExit(${session.session_id})">
                🚪 ${t('btn_clear_exit')} (${session.formatted_plate})
            </button>
        </div>
    `;

    // Automatically flip to Leaving tab if on another tab
    switchTab('leaving');
}

async function searchExitSessions(term) {
    if (!term || term.length < 2) return;

    try {
        const res = await fetch(`../api/gate/find-session.php?q=${encodeURIComponent(term)}`);
        const json = await res.json();
        const area = document.getElementById('exitSearchResults');
        if (!area) return;

        if (!json.ok || json.data.length === 0) {
            area.innerHTML = `
                <div style="padding:24px; text-align:center; color:#94a3b8;">
                    <p style="font-size:20px;">${t('no_car_found')}</p>
                    <button class="btn-lang" style="margin-top:12px; background:#ef4444; color:#fff;" onclick="openSupervisorOverrideModal('${term}')">
                        ⚠️ Ask Supervisor for Exit Override
                    </button>
                </div>
            `;
            return;
        }

        area.innerHTML = '';
        json.data.forEach(item => {
            const card = document.createElement('div');
            card.className = 'car-card';
            card.style.borderColor = '#f97316';
            card.innerHTML = `
                <div class="card-header">
                    <div class="card-plate">${item.formatted_plate}</div>
                    <span style="font-weight:800; color:#fbbf24;">Parked: ${item.dwell_string}</span>
                </div>
                <div class="card-body">
                    <div><span class="info-label">${t('driver')}</span><span class="info-val">${item.driver_name}</span></div>
                    <div><span class="info-label">${t('destination')}</span><span class="info-val">${item.destination}</span></div>
                </div>
                <button class="btn-card-accept" style="background:#f97316;" onclick="executeClearExit(${item.id})">
                    🚪 ${t('btn_clear_exit')}
                </button>
            `;
            area.appendChild(card);
        });
    } catch (e) {
        console.error('Search exit session error:', e);
    }
}

async function executeClearExit(sessionId) {
    if (!confirm('Are you sure you want to clear this vehicle for exit?')) return;

    try {
        const res = await fetch('../api/gate/clear-exit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: sessionId }),
        });
        const json = await res.json();

        if (json.ok) {
            playSuccessChime();
            showToast(`✓ Vehicle ${json.data.formatted_plate} cleared. Total time: ${json.data.duration_text}`);
            document.getElementById('exitCandidateArea').innerHTML = '';
            document.getElementById('exitSearchResults').innerHTML = '';
            const searchInput = document.getElementById('exitSearchInput');
            if (searchInput) searchInput.value = '';
        } else {
            showToast(json.error || 'Failed to clear exit.');
        }
    } catch (e) {
        showToast('Network error while clearing exit.');
    }
}

// --------------------------------------------------------------------
// Supervisor Override Modal
// --------------------------------------------------------------------
let overridePlate = '';

function openSupervisorOverrideModal(plate) {
    overridePlate = plate;
    const modal = document.getElementById('supervisorOverrideModal');
    if (modal) modal.classList.add('open');
}

function closeSupervisorOverrideModal() {
    const modal = document.getElementById('supervisorOverrideModal');
    if (modal) modal.classList.remove('open');
}

async function submitSupervisorOverride() {
    const pin = document.getElementById('supOverridePin').value;
    const reason = document.getElementById('supOverrideReason').value;

    if (!pin) {
        alert('Please enter Supervisor PIN');
        return;
    }

    try {
        const res = await fetch('../api/gate/clear-exit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                plate_number: overridePlate,
                supervisor_pin: pin,
                override_reason: reason || 'Manual Guard Exit Without Ticket',
            }),
        });
        const json = await res.json();

        if (json.ok) {
            playSuccessChime();
            showToast(`✓ Exit authorized by Supervisor (${json.data.cleared_by})`);
            closeSupervisorOverrideModal();
            document.getElementById('exitSearchResults').innerHTML = '';
        } else {
            alert(json.error || 'Supervisor authorization failed.');
        }
    } catch (e) {
        alert('Failed to contact server for override.');
    }
}

// --------------------------------------------------------------------
// Cars Inside Tab
// --------------------------------------------------------------------
async function loadActiveSessions() {
    try {
        const res = await fetch('../api/gate/active-sessions.php');
        const json = await res.json();
        const tbody = document.getElementById('insideTableBody');
        if (!tbody) return;

        if (!json.ok || json.data.sessions.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:40px; color:#64748b;">No cars currently parked in basement.</td></tr>`;
            return;
        }

        tbody.innerHTML = '';
        json.data.sessions.forEach(sess => {
            const tr = document.createElement('tr');
            if (sess.overstay_level === 'critical') tr.className = 'overstay-critical';
            else if (sess.overstay_level === 'warning') tr.className = 'overstay-warning';

            tr.innerHTML = `
                <td>
                    <span style="font-weight:900; font-size:20px;">${sess.formatted_plate}</span>
                    ${sess.overstay_level === 'critical' ? `<span style="color:#ef4444; font-weight:900; margin-left:8px;">⚠️ OVERSTAY</span>` : ''}
                </td>
                <td>${sess.driver_name}</td>
                <td>${sess.destination}</td>
                <td>${sess.entry_time.split(' ')[1]}</td>
                <td style="font-weight:800; color:#38bdf8;">${sess.dwell_string}</td>
                <td>
                    <button class="btn-search-clear" onclick="reprintTicket(${sess.id})">🖨️ Reprint</button>
                    <button class="btn-search-clear" style="background:#ea580c; margin-left:6px;" onclick="executeClearExit(${sess.id})">Exit</button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error('Error loading active sessions:', e);
    }
}

// --------------------------------------------------------------------
// Manual Registration — Flat Single-Popup Form (v2.0)
// Replaces the old 5-step wizard. All fields visible simultaneously.
// --------------------------------------------------------------------
function initWizard() {
    const btnOpen = document.getElementById('btnOpenManualAdd');
    const modal   = document.getElementById('manualWizardModal');

    if (btnOpen && modal) {
        btnOpen.addEventListener('click', () => {
            // Reset fields
            const plateIn = document.getElementById('wizPlateInput');
            const nameIn  = document.getElementById('wizNameInput');
            const phoneIn = document.getElementById('wizPhoneInput');
            if (plateIn) plateIn.value = '';
            if (nameIn)  nameIn.value  = '';
            if (phoneIn) phoneIn.value = '';
            STATE.wizard.destination = '';
            document.querySelectorAll('.dest-tile').forEach(t => t.classList.remove('selected'));

            // Offer camera-detected plate if available
            const camSuggestion = document.getElementById('wizCamSuggestion');
            if (camSuggestion && STATE.latestEntrancePlate) {
                camSuggestion.style.display = 'block';
                document.getElementById('wizCamPlateVal').textContent = STATE.latestEntrancePlate;
            } else if (camSuggestion) {
                camSuggestion.style.display = 'none';
            }

            modal.classList.add('open');
            if (plateIn) plateIn.focus();
        });
    }

    // Use camera plate suggestion
    const btnUseCam = document.getElementById('btnUseCamPlate');
    if (btnUseCam) {
        btnUseCam.addEventListener('click', () => {
            const plateIn = document.getElementById('wizPlateInput');
            if (plateIn) plateIn.value = STATE.latestEntrancePlate;
        });
    }

    // Plate auto-uppercase
    const plateIn = document.getElementById('wizPlateInput');
    if (plateIn) {
        plateIn.addEventListener('input', () => {
            plateIn.value = plateIn.value.toUpperCase();
        });
    }
}

function closeWizard() {
    const modal = document.getElementById('manualWizardModal');
    if (modal) modal.classList.remove('open');
}

// Stubs — no longer used but kept to avoid JS errors if called from elsewhere
function nextWizardStep() {}
function prevWizardStep() {}
function updateWizardStep() {}

let guardActiveFloor = 'ALL';

function selectDestination(name) {
    STATE.wizard.destination = name;
    document.querySelectorAll('.dest-tile').forEach(tile => {
        tile.classList.toggle('selected', tile.getAttribute('data-name') === name);
    });
    const label = document.getElementById('wizSelectedDestLabel');
    if (label) label.textContent = name || 'None';
}

function renderGuardDestGrid() {
    const grid = document.getElementById('wizDestinationsGrid');
    if (!grid) return;

    const query = (document.getElementById('wizDestSearch')?.value || '').trim().toLowerCase();
    const list = STATE.destinations || [];

    const filtered = list.filter(d => {
        if (guardActiveFloor !== 'ALL') {
            if ((d.floor_level || '').toLowerCase() !== guardActiveFloor.toLowerCase()) {
                return false;
            }
        }
        if (query.length > 0) {
            const name = (d.name || '').toLowerCase();
            const code = (d.unit_code || '').toLowerCase();
            const cat  = (d.category || '').toLowerCase();
            return name.includes(query) || code.includes(query) || cat.includes(query);
        }
        return true;
    });

    grid.innerHTML = '';
    if (filtered.length === 0) {
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:12px; color:var(--text-muted); font-size:11px;">No matching stores</div>';
        return;
    }

    filtered.forEach(d => {
        const tile = document.createElement('div');
        const isSelected = STATE.wizard.destination === d.name;
        tile.className = 'dest-tile' + (isSelected ? ' selected' : '');
        tile.setAttribute('data-name', d.name);
        tile.onclick = () => selectDestination(d.name);

        const codeTag = d.unit_code ? `<span class="unit-code-tag">${d.unit_code}</span>` : '';
        const catTag  = d.category ? `<span class="dest-cat-text">${d.category}</span>` : '';

        tile.innerHTML = `
            ${codeTag}
            <span>${d.name}</span>
            ${catTag}
        `;
        grid.appendChild(tile);
    });
}

async function submitManualWizard() {
    // Read directly from the flat form fields
    const plate = (document.getElementById('wizPlateInput')?.value || '').trim();
    const name  = (document.getElementById('wizNameInput')?.value  || '').trim() || 'Visitor';
    const phone = (document.getElementById('wizPhoneInput')?.value || '').trim();
    const dest  = STATE.wizard.destination;

    if (plate.length < 4) {
        alert('Please enter a valid number plate.');
        document.getElementById('wizPlateInput')?.focus();
        return;
    }
    if (!dest) {
        alert('Please select a destination store.');
        return;
    }

    const btn = document.querySelector('.btn-submit-checkin');
    if (btn) { btn.disabled = true; btn.textContent = 'Registering…'; }

    try {
        const res = await fetch('../api/gate/manual-checkin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                plate_number: plate,
                driver_name:  name,
                driver_phone: phone,
                destination:  dest,
            }),
        });
        const json = await res.json();

        if (json.ok) {
            playSuccessChime();
            showToast(`✓ Ticket #${json.data.ticket_id} Printed — ${json.data.formatted_plate}`);
            closeWizard();
        } else {
            alert(json.error || 'Failed to register vehicle.');
        }
    } catch (e) {
        alert('Network error registering vehicle.');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Register & Print Ticket'; }
    }
}

async function loadDestinations() {
    try {
        const res  = await fetch('../api/driver/destinations.php');
        const json = await res.json();
        if (json.ok && json.data) {
            STATE.destinations = json.data;
            renderGuardDestGrid();
        }

        // Setup guard floor filter tabs
        document.querySelectorAll('#wizFloorTabs .floor-pill').forEach(btn => {
            btn.onclick = () => {
                document.querySelectorAll('#wizFloorTabs .floor-pill').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                guardActiveFloor = btn.getAttribute('data-floor') || 'ALL';
                renderGuardDestGrid();
            };
        });

        // Setup guard search input
        const searchInput = document.getElementById('wizDestSearch');
        if (searchInput && !searchInput._bound) {
            searchInput._bound = true;
            searchInput.addEventListener('input', () => {
                renderGuardDestGrid();
            });
        }
    } catch (e) { console.warn('Failed to load destinations:', e); }
}

// --------------------------------------------------------------------
// Toast & Reprint Helper
// --------------------------------------------------------------------
let lastFailedSessionId = null;

function showToast(msg, showReprint = false, failedSessionId = null) {
    const toast = document.getElementById('toastMsg');
    const text = document.getElementById('toastText');
    const reprintBtn = document.getElementById('btnToastReprint');

    if (!toast || !text) return;

    text.textContent = msg;
    lastFailedSessionId = failedSessionId;

    if (reprintBtn) {
        reprintBtn.style.display = showReprint ? 'inline-block' : 'none';
        reprintBtn.onclick = () => {
            if (lastFailedSessionId) reprintTicket(lastFailedSessionId);
        };
    }

    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, 6000);
}

async function reprintTicket(sessionId) {
    try {
        const res = await fetch('../api/gate/reprint-ticket.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: sessionId }),
        });
        const json = await res.json();

        if (json.ok) {
            playDingSound();
            showToast('✓ Ticket reprint sent to printer');
        } else {
            showToast('Reprint failed. Check printer power & paper spool.', true, sessionId);
        }
    } catch (e) {
        showToast('Network error on reprint.');
    }
}

// Utility debounce
function debounce(fn, ms) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), ms);
    };
}
