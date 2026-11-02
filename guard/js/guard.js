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

    // 10. Vehicle Intake Modal Listeners

    ['intakeDriverName', 'intakeDriverPhone'].forEach(id => {
        const inputEl = document.getElementById(id);
        if (inputEl) {
            inputEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitIntakeFromModal();
                }
            });
        }
    });

    const intakeModal = document.getElementById('vehicleIntakeModal');
    if (intakeModal) {
        intakeModal.addEventListener('click', (e) => {
            if (e.target === intakeModal) {
                closeVehicleIntakeModal();
            }
        });
    }

    const rcptModal = document.getElementById('receiptPreviewModal');
    if (rcptModal) {
        rcptModal.addEventListener('click', (e) => {
            if (e.target === rcptModal) {
                closeReceiptModal();
            }
        });
    }

    // 11. Basement Active Parking Roster Search & Filter Listeners
    const rosterSearch = document.getElementById('rosterSearchInput');
    if (rosterSearch) {
        rosterSearch.addEventListener('input', () => {
            applyRosterFilters();
        });
    }

    document.querySelectorAll('.roster-pill').forEach(pill => {
        pill.addEventListener('click', () => {
            document.querySelectorAll('.roster-pill').forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            applyRosterFilters(true);
        });
    });

    // 12. Right Sidebar Queue Search Listener & View Mode Init
    const qSearch = document.getElementById('queueSearchInput');
    if (qSearch) {
        qSearch.addEventListener('input', () => {
            applyQueueFilters();
        });
    }
    const savedQueueMode = localStorage.getItem('mm_queue_view_mode') || 'cards';
    setQueueViewMode(savedQueueMode);

    // Global ESC key listener to dismiss open modals
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (rcptModal && rcptModal.classList.contains('open')) {
                closeReceiptModal();
                return;
            }
            if (intakeModal && intakeModal.classList.contains('open')) {
                closeVehicleIntakeModal();
            }
        }
    });
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
// Tab & View Switching (Basement Roster, Exit Clearance, Camera Queue)
// --------------------------------------------------------------------
function switchCenterView(viewName) {
    const vRoster = document.getElementById('viewBasementRoster');
    const vExit = document.getElementById('viewExitClearance');
    const btnRoster = document.getElementById('btnToggleRoster');
    const btnExit = document.getElementById('btnToggleExit');
    const titleEl = document.getElementById('centerStageTitle');
    const subTitleEl = document.getElementById('centerStageSubtitle');

    if (viewName === 'exit') {
        if (vRoster) vRoster.style.display = 'none';
        if (vExit) {
            vExit.style.display = 'flex';
            if (window.gsap) gsap.from(vExit, { opacity: 0, y: 12, duration: 0.3, ease: 'power2.out' });
        }
        btnRoster?.classList.remove('active');
        btnExit?.classList.add('active');
        if (titleEl) titleEl.textContent = 'Vehicles Exiting — Gate Departure Clearance';
        if (subTitleEl) subTitleEl.textContent = 'Scan departing plate or search by ticket/mobile to clear parking departure';
        setTimeout(() => document.getElementById('exitSearchInput')?.focus(), 150);
    } else {
        if (vExit) vExit.style.display = 'none';
        if (vRoster) {
            vRoster.style.display = 'flex';
            if (window.gsap) gsap.from(vRoster, { opacity: 0, y: 12, duration: 0.3, ease: 'power2.out' });
        }
        btnRoster?.classList.add('active');
        btnExit?.classList.remove('active');
        if (titleEl) titleEl.textContent = 'Basement Parking — Active Vehicles Inside';
        if (subTitleEl) subTitleEl.textContent = 'Live real-time parking dwell & overstay monitoring • Free up to 2 Hours (120 Mins)';
        loadActiveSessions();
    }
}

function switchMainTab(tabName) {
    STATE.currentTab = tabName;
    document.querySelectorAll('.mode-tab').forEach(tab => {
        tab.classList.toggle('active', tab.getAttribute('data-tab') === tabName);
    });

    const qSidebar = document.getElementById('rightQueueSidebar');

    if (tabName === 'entering') {
        if (qSidebar) {
            qSidebar.classList.add('mobile-open');
            qSidebar.style.display = 'flex';
            if (window.gsap) gsap.from(qSidebar, { x: 80, opacity: 0, duration: 0.35, ease: 'power2.out' });
        }
    } else {
        if (qSidebar) {
            qSidebar.classList.remove('mobile-open');
            if (window.innerWidth <= 992) {
                qSidebar.style.display = 'none';
            } else {
                qSidebar.style.display = '';
            }
        }
        if (tabName === 'leaving') {
            switchCenterView('exit');
        } else {
            switchCenterView('roster');
        }
    }
}

// Window resize handler to maintain side panel visibility seamlessly
window.addEventListener('resize', () => {
    const qSidebar = document.getElementById('rightQueueSidebar');
    if (qSidebar) {
        if (window.innerWidth > 992) {
            qSidebar.classList.remove('mobile-open');
            qSidebar.style.display = '';
        } else if (STATE.currentTab !== 'entering') {
            qSidebar.style.display = 'none';
        }
    }
});

function switchTab(tabName) {
    switchMainTab(tabName);
}

// --------------------------------------------------------------------
// Short Polling (Every 2 seconds) + Cloud Firestore Real-time Sync
// --------------------------------------------------------------------
function startPolling() {
    if (STATE.pollTimer) clearInterval(STATE.pollTimer);
    runPoll();
    STATE.pollTimer = setInterval(runPoll, 1000);

    // Ultra-responsive queue sync every 1.5s for instant camera intake and zero drift
    if (STATE.queueSyncTimer) clearInterval(STATE.queueSyncTimer);
    STATE.queueSyncTimer = setInterval(() => {
        loadPendingRequests();
    }, 1500);

    initFirestoreSync();
}

function initFirestoreSync() {
    if (STATE.firestoreUnsubscribe) return;
    if (window.firebaseBridge && typeof window.firebaseBridge.listenPendingRequests === 'function') {
        STATE.firestoreUnsubscribe = window.firebaseBridge.listenPendingRequests((cloudRequests) => {
            if (Array.isArray(cloudRequests)) {
                handleCloudRequests(cloudRequests);
            }
        });
        console.log('[Guard] Real-time Cloud Firestore listener connected.');
    } else {
        setTimeout(initFirestoreSync, 1200);
    }
}

function handleCloudRequests(cloudRequests) {
    if (!cloudRequests || cloudRequests.length === 0) return;
    STATE.pendingRequestsMap = STATE.pendingRequestsMap || {};

    cloudRequests.forEach(cr => {
        const key = `fb_${cr.firestore_id}`;
        // Check if vehicle plate is already queued
        let alreadyExists = false;
        for (const id in STATE.pendingRequestsMap) {
            const existing = STATE.pendingRequestsMap[id];
            if (existing && existing.plate_number === cr.plate_number) {
                alreadyExists = true;
                if (!existing.firestore_id) existing.firestore_id = cr.firestore_id;
                break;
            }
        }

        if (!alreadyExists) {
            const item = {
                id: key,
                request_id: key,
                firestore_id: cr.firestore_id,
                plate_number: cr.plate_number,
                formatted_plate: cr.formatted_plate || cr.plate_number,
                driver_name: cr.driver_name || 'Visitor',
                driver_phone: cr.driver_phone || '',
                destination: cr.destination || 'Mombasa Mall',
                source: 'cloud_mobile',
                alpr_verified: false,
                category: 'regular',
                wait_text: 'Mobile Check-In'
            };
            STATE.pendingRequestsMap[key] = item;
            prependPendingCard(item);
            playDingSound();
            logActivity(`☁️ Mobile Check-In: ${item.formatted_plate} (${item.destination})`, 'ok');
        }
    });
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

                // Sync occupancy telemetry to Google Cloud Firestore
                if (window.firebaseBridge && typeof window.firebaseBridge.updateOccupancyTelemetry === 'function') {
                    window.firebaseBridge.updateOccupancyTelemetry(data.stats);
                }
            }

            // Update Device Health Status (Cameras, Printer, ALPR Worker)
            if (data.devices) {
                updateDeviceStatusDisplay(data.devices);
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

function updateDeviceStatusDisplay(devices) {
    if (!devices) return;
    const updatePill = (id, info, defaultText = 'Online') => {
        const el = document.getElementById(id);
        if (!el) return;
        const stat = (info && info.status) ? info.status : 'OK';
        if (stat === 'OK') {
            el.textContent = defaultText;
            el.style.color = '#059669';
            el.style.fontWeight = '700';
        } else if (stat === 'WARNING') {
            el.textContent = 'Active';
            el.style.color = '#d97706';
            el.style.fontWeight = '700';
        } else {
            el.textContent = 'Offline';
            el.style.color = '#dc2626';
            el.style.fontWeight = '700';
        }
    };

    updatePill('pnlPrinterStatus', devices.printer, 'Ready');
    updatePill('pnlCam1Status', devices.entrance_cam, 'Online (192.168.1.230)');
    updatePill('pnlCam2Status', devices.exit_cam, 'Online (192.168.1.210)');
    updatePill('pnlAlprStatus', devices.alpr_worker, 'Active');

    const camLbl = document.getElementById('camEntranceStatusLabel');
    if (camLbl && devices.entrance_cam) {
        const isOk = (devices.entrance_cam.status === 'OK' || devices.entrance_cam.status === 'WARNING');
        camLbl.textContent = isOk ? 'Gate 1 (192.168.1.230): Online' : 'Gate 1 (192.168.1.230): Offline';
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

        case 'request_updated':
            playDingSound();
            updatePendingCardWithDriverDetails(p);
            logActivity(`📱 Driver Pre-Filled: ${p.driver_name || ''} (${p.formatted_plate || p.plate_number}) → ${p.destination || ''}`, 'ok');
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

        case 'exit_without_details':
            playAlertChime();
            removePendingCard(p.plate_number);
            showExitWithoutDetailsBanner(p);
            logActivity(`⚠️ ${p.formatted_plate || p.plate_number} exited WITHOUT details! In: ${p.entry_time_display || p.entry_time || '--'} | Out: ${p.exit_time_display || p.time || '--'} (${p.duration_text || ''})`, 'warn');
            break;

        case 'session_created':
            removePendingCard(p.plate_number);
            loadActiveSessions();
            logActivity(`Session created: ${p.plate_number || ''} — Ticket #${p.ticket_id || ''}`, 'ok');
            break;

        case 'session_completed':
            logActivity(`Exit cleared: ${p.formatted_plate || ''} — ${p.duration_text || ''}`, 'ok');
            removePendingCard(p.plate_number);
            loadActiveSessions();
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

        case 'request_dismissed':
            if (p.request_id) removePendingCardById(p.request_id);
            else if (p.plate_number) removePendingCard(p.plate_number);
            logActivity(`Record released: ${p.plate_number || ''}`, 'ok');
            break;

        case 'queue_cleared':
            loadPendingRequests();
            logActivity(`🧹 Queue released (${p.cleared_count || 0} stale records)`, 'ok');
            break;
    }
}

function showEntranceAlprBanner(p) {
    if (!p) return;
    STATE.latestEntrancePlate = p.formatted_plate || p.plate_number;
    const banner = document.getElementById('alprEntranceBanner');
    const plateText = document.getElementById('alprBannerPlate');
    const meta = document.getElementById('alprBannerMeta');

    if (banner && plateText) {
        plateText.textContent = p.formatted_plate || p.plate_number || 'UNKNOWN';
        const rawConf = Number(p.confidence);
        const conf = isNaN(rawConf) ? 95 : (rawConf > 1 ? Math.round(rawConf) : Math.round(rawConf * 100));
        const timeStr = p.time || new Date().toLocaleTimeString('en-GB');
        if (meta) meta.textContent = `Confidence: ${conf}% at ${timeStr}`;
        banner.style.display = 'flex';

        // Animate banner with GSAP
        if (window.gsap) {
            gsap.fromTo(banner, 
                { y: -15, opacity: 0, scale: 0.97 }, 
                { y: 0, opacity: 1, scale: 1, duration: 0.35, ease: 'back.out(1.4)' }
            );
        }
    }
}

function dismissAlprBanner() {
    const banner = document.getElementById('alprEntranceBanner');
    if (!banner) return;
    if (window.gsap) {
        gsap.to(banner, {
            y: -15,
            opacity: 0,
            scale: 0.97,
            duration: 0.2,
            ease: 'power2.in',
            onComplete: () => { banner.style.display = 'none'; }
        });
    } else {
        banner.style.display = 'none';
    }
}

function admitCurrentAlprBanner() {
    const plate = STATE.latestEntrancePlate;
    dismissAlprBanner();
    if (!plate) return;

    // Check if there is an existing pending request in STATE.pendingRequestsMap
    let foundReqId = null;
    if (STATE.pendingRequestsMap) {
        for (const [id, req] of Object.entries(STATE.pendingRequestsMap)) {
            const reqPlate = (req.plate_number || req.formatted_plate || '').replace(/\s+/g, '').toUpperCase();
            const targetPlate = plate.replace(/\s+/g, '').toUpperCase();
            if (reqPlate && targetPlate && reqPlate === targetPlate) {
                foundReqId = id;
                break;
            }
        }
    }

    if (foundReqId) {
        openVehicleIntakeModal(foundReqId);
    } else {
        // Open manual wizard with prefilled plate
        const btnManual = document.getElementById('btnOpenManualAdd');
        if (btnManual) btnManual.click();
        const plateInput = document.getElementById('wizPlateInput');
        if (plateInput) {
            plateInput.value = plate;
            const nameInput = document.getElementById('wizNameInput');
            if (nameInput) setTimeout(() => nameInput.focus(), 150);
        }
    }
}

function showExitWithoutDetailsBanner(p) {
    const area = document.getElementById('exitCandidateArea');
    const plate = escapeHtml(p.formatted_plate || p.plate_number || 'UNKNOWN');
    const inTime = escapeHtml(p.entry_time_display || p.entry_time || '--');
    const outTime = escapeHtml(p.exit_time_display || p.exit_time || p.time || '--');
    const durText = escapeHtml(p.duration_text || (p.duration_minutes ? p.duration_minutes + ' mins' : '--'));

    // Prominent toast notification
    showToast(`⚠️ Vehicle ${plate} left WITHOUT collection of details! In: ${inTime} • Out: ${outTime} (${durText})`, 8000);

    if (area) {
        area.innerHTML = `
            <div class="uncollected-exit-card" style="border: 1.5px solid #d97706; background: #fffbeb; border-radius: 8px; padding: 18px 20px; margin-bottom: 20px; box-shadow: 0 4px 14px rgba(217,119,6,0.12);">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; background:#fef3c7; border:1px solid #fcd34d; border-radius:50%; color:#b45309;">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2.5"><polygon points="12 2 2 22 22 22"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </span>
                        <div>
                            <div style="font-size:14px; font-weight:800; color:#92400e; text-transform:uppercase; letter-spacing:0.04em;">
                                Vehicle Exited Without Registration
                            </div>
                            <div style="font-size:12px; color:#78350f; font-weight:600;">
                                Gate cleared automatically &bull; Driver details were not registered upon entry
                            </div>
                        </div>
                    </div>
                    <span style="background:#059669; color:#fff; font-size:11px; font-weight:800; padding:4px 10px; border-radius:4px; letter-spacing:0.05em; text-transform:uppercase;">
                        Gate Cleared
                    </span>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap:12px; background:#fff; padding:12px 14px; border-radius:6px; border:1px solid #fde68a;">
                    <div>
                        <span class="info-label" style="font-size:10px; color:#64748b; font-weight:800; text-transform:uppercase;">Vehicle Plate</span>
                        <div style="font-size:20px; font-weight:900; color:#0f172a; font-family:monospace;">${plate}</div>
                    </div>
                    <div>
                        <span class="info-label" style="font-size:10px; color:#64748b; font-weight:800; text-transform:uppercase;">Entered At</span>
                        <div style="font-size:13.5px; font-weight:800; color:#1e293b; font-family:monospace;">${inTime}</div>
                    </div>
                    <div>
                        <span class="info-label" style="font-size:10px; color:#64748b; font-weight:800; text-transform:uppercase;">Exited At</span>
                        <div style="font-size:13.5px; font-weight:800; color:#1e293b; font-family:monospace;">${outTime}</div>
                    </div>
                    <div>
                        <span class="info-label" style="font-size:10px; color:#64748b; font-weight:800; text-transform:uppercase;">Total Duration</span>
                        <div style="font-size:14px; font-weight:800; color:#d97706; font-family:monospace;">${durText}</div>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; flex-wrap:wrap; gap:8px;">
                    <div style="font-size:11.5px; color:#78350f; font-weight:600;">
                        Vehicle departure recorded in database. Driver details were not collected upon entry.
                    </div>
                    <button class="btn-search-clear" onclick="document.getElementById('exitCandidateArea').innerHTML=''" style="background:#fef3c7; border:1px solid #fcd34d; color:#92400e; font-weight:800; padding:5px 12px; border-radius:4px; cursor:pointer;">
                        Dismiss Alert
                    </button>
                </div>
            </div>
        `;
    }
}

// --------------------------------------------------------------------
// Pending Queue (Cars Entered & Awaiting Data / Approval in Right Sidebar)
// High-Capacity Architecture: Non-destructive diffing, Cards & Compact Views
// --------------------------------------------------------------------
let activeQueueFilter = 'all';

async function loadPendingRequests() {
    try {
        const res = await fetch('../api/requests/pending.php');
        const json = await res.json();

        const grid = document.getElementById('pendingCardsGrid');
        if (!grid) return;

        STATE.pendingRequestsMap = STATE.pendingRequestsMap || {};

        const badge = document.getElementById('pendingCountBadge');
        const pnlPending = document.getElementById('pnlPending');

        if (!json.ok || !json.data || json.data.length === 0) {
            STATE.latestPendingItems = [];
            grid.innerHTML = `
                <div class="queue-empty-box">
                    <div class="queue-empty-radar">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10"/></svg>
                    </div>
                    <div class="queue-empty-text">Camera Active &amp; Ready.<br>Cars arriving at Gate 1 will appear here.</div>
                </div>`;
            if (badge) {
                badge.textContent = '0 Waiting';
                badge.classList.remove('has-items');
            }
            if (pnlPending) pnlPending.textContent = '0';
            updateQueueFilterCounts(0, 0, 0);
            return;
        }

        STATE.latestPendingItems = json.data;
        const count = json.data.length;
        if (badge) {
            badge.textContent = `${count} Waiting`;
            badge.classList.add('has-items');
        }
        if (pnlPending) pnlPending.textContent = String(count);

        // Smart DOM Reconciliation (Diffing) — Preserves Scroll Position & Eliminates Jumps
        renderPendingRequestsDiff(json.data);

    } catch (e) {
        console.error('Error loading pending requests:', e);
    }
}

function renderPendingRequestsDiff(items) {
    const grid = document.getElementById('pendingCardsGrid');
    if (!grid) return;

    // Remove empty placeholder if present
    const emptyBox = grid.querySelector('.queue-empty-box');
    if (emptyBox) emptyBox.remove();

    const mode = STATE.queueViewMode || 'cards';
    const incomingIds = new Set(items.map(it => String(it.id || it.request_id)));

    // 1. Remove DOM elements that are no longer in incoming list (e.g. admitted, exited, declined)
    const existingDomElements = grid.querySelectorAll('[data-req-id]');
    existingDomElements.forEach(domEl => {
        const domId = domEl.getAttribute('data-req-id');
        if (!incomingIds.has(domId)) {
            if (window.gsap) {
                gsap.to(domEl, {
                    x: 40,
                    opacity: 0,
                    height: 0,
                    marginBottom: 0,
                    duration: 0.22,
                    ease: 'power2.in',
                    onComplete: () => domEl.remove()
                });
            } else {
                domEl.remove();
            }
        }
    });

    // 2. Insert new items or update existing items
    let lastDomElement = null;
    items.forEach((item, index) => {
        const reqId = String(item.id || item.request_id);
        STATE.pendingRequestsMap[reqId] = item;
        let existingCard = grid.querySelector(`[data-req-id="${reqId}"]`);

        if (!existingCard) {
            // New item! Create in chosen view mode (compact or card)
            const newEl = createPendingItemElement(item, mode);
            if (index === 0) {
                grid.prepend(newEl);
                if (window.gsap) {
                    gsap.fromTo(newEl,
                        { opacity: 0, y: -25, scale: 0.97 },
                        { opacity: 1, y: 0, scale: 1, duration: 0.4, ease: 'power2.out' }
                    );
                }
            } else if (lastDomElement && lastDomElement.nextSibling) {
                grid.insertBefore(newEl, lastDomElement.nextSibling);
                if (window.gsap) {
                    gsap.from(newEl, { x: 30, opacity: 0, duration: 0.3, ease: 'power2.out' });
                }
            } else {
                grid.appendChild(newEl);
                if (window.gsap) {
                    gsap.from(newEl, { x: 30, opacity: 0, duration: 0.3, ease: 'power2.out' });
                }
            }
            existingCard = newEl;
        } else {
            // Update dynamic text (e.g. wait time ago) without re-rendering node
            updateExistingPendingItem(existingCard, item, mode);
        }
        lastDomElement = existingCard;
    });

    // Apply active search filter & category filter
    applyQueueFilters();
}

function updateExistingPendingItem(el, item, mode) {
    if (mode === 'compact') {
        const timeEl = el.querySelector('.cqr-time');
        if (timeEl && item.wait_text) timeEl.textContent = `• ${item.wait_text}`;
    } else {
        const timeEl = el.querySelector('.cqc-time-ago');
        if (timeEl && item.wait_text) timeEl.textContent = item.wait_text;
    }
}

function setQueueViewMode(mode) {
    STATE.queueViewMode = mode;
    localStorage.setItem('mm_queue_view_mode', mode);

    const btnCards = document.getElementById('btnViewCards');
    const btnCompact = document.getElementById('btnViewCompact');
    if (btnCards) btnCards.classList.toggle('active', mode === 'cards');
    if (btnCompact) btnCompact.classList.toggle('active', mode === 'compact');

    const grid = document.getElementById('pendingCardsGrid');
    if (!grid) return;

    grid.classList.toggle('view-compact', mode === 'compact');
    grid.classList.toggle('view-cards', mode === 'cards');

    // Re-render items in the selected layout mode
    if (STATE.latestPendingItems && STATE.latestPendingItems.length > 0) {
        grid.innerHTML = '';
        STATE.latestPendingItems.forEach(item => {
            grid.appendChild(createPendingItemElement(item, mode));
        });
        applyQueueFilters();
    }
}

function setQueueFilter(filterType) {
    activeQueueFilter = filterType;
    document.querySelectorAll('.q-filter-pill').forEach(pill => {
        pill.classList.toggle('active', pill.getAttribute('data-qfilter') === filterType);
    });
    applyQueueFilters();
}

function applyQueueFilters() {
    const query = (document.getElementById('queueSearchInput')?.value || '').trim().toLowerCase();
    const btnClear = document.getElementById('btnClearQueueSearch');
    if (btnClear) btnClear.style.display = query.length > 0 ? 'inline-block' : 'none';

    const grid = document.getElementById('pendingCardsGrid');
    if (!grid) return;

    const elements = grid.querySelectorAll('[data-req-id]');
    let totalCount = elements.length;
    let needsCount = 0;
    let prefilledCount = 0;

    elements.forEach(el => {
        const reqId = el.getAttribute('data-req-id');
        const data = STATE.pendingRequestsMap[reqId] || {};
        const plate = (data.plate_number || data.formatted_plate || '').toLowerCase();
        const driver = (data.driver_name || '').toLowerCase();
        const dest = (data.destination || '').toLowerCase();
        const isSelf = (data.source === 'self_signin' || data.source === 'cloud_mobile' || data.source === 'firebase_web');

        if (isSelf) prefilledCount++;
        else needsCount++;

        const matchesQuery = !query || plate.includes(query) || driver.includes(query) || dest.includes(query);
        let matchesFilter = true;
        if (activeQueueFilter === 'needs-data') matchesFilter = !isSelf;
        else if (activeQueueFilter === 'self-checkin') matchesFilter = isSelf;

        el.style.display = (matchesQuery && matchesFilter) ? '' : 'none';
    });

    updateQueueFilterCounts(totalCount, needsCount, prefilledCount);
}

function updateQueueFilterCounts(total, needs, prefilled) {
    const elAll = document.getElementById('qCountAll');
    const elNeeds = document.getElementById('qCountNeeds');
    const elPre = document.getElementById('qCountPre');
    const btnBatch = document.getElementById('btnBatchApprove');
    const batchCount = document.getElementById('qBatchCount');

    if (elAll) elAll.textContent = String(total);
    if (elNeeds) elNeeds.textContent = String(needs);
    if (elPre) elPre.textContent = String(prefilled);

    if (btnBatch && batchCount) {
        batchCount.textContent = String(prefilled);
        btnBatch.style.display = prefilled >= 2 ? 'inline-flex' : 'none';
    }
}

function clearQueueSearch() {
    const input = document.getElementById('queueSearchInput');
    if (input) input.value = '';
    applyQueueFilters();
    input?.focus();
}

let pendingBatchItems = [];

function openBatchApproveModal(items) {
    pendingBatchItems = items || [];
    const modal = document.getElementById('batchApproveModal');
    if (!modal) return;

    const prompt = document.getElementById('batchModalPrompt');
    if (prompt) {
        prompt.textContent = `Approve all ${pendingBatchItems.length} pre-filled vehicles in the queue?`;
    }

    modal.classList.add('open');
    if (window.gsap) {
        gsap.fromTo('#batchApproveModal .modal-box',
            { scale: 0.94, opacity: 0, y: 15 },
            { scale: 1, opacity: 1, y: 0, duration: 0.25, ease: 'back.out(1.4)' }
        );
    }
}

function closeBatchApproveModal() {
    const modal = document.getElementById('batchApproveModal');
    if (!modal) return;
    modal.classList.remove('open');
    pendingBatchItems = [];
}

async function executeBatchApproveConfirmed() {
    if (!pendingBatchItems || pendingBatchItems.length === 0) {
        closeBatchApproveModal();
        return;
    }

    const btn = document.getElementById('btnExecuteBatchApprove');
    if (btn) {
        btn.disabled = true;
        btn.classList.add('btn-loading');
        btn.textContent = 'Printing Tickets…';
    }

    const itemsToProcess = [...pendingBatchItems];
    closeBatchApproveModal();

    showToast(`⚡ Batch approving ${itemsToProcess.length} vehicles...`);
    for (const item of itemsToProcess) {
        try {
            await fetch('../api/requests/action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    request_id: item.id || item.request_id,
                    action: 'accept',
                    driver_name: item.driver_name || 'Visitor',
                    driver_phone: item.driver_phone || '',
                    destination: item.destination || 'Mombasa Mall',
                })
            });
        } catch (e) {
            console.warn('Batch approve error for:', item.plate_number, e);
        }
    }

    if (btn) {
        btn.disabled = false;
        btn.classList.remove('btn-loading');
        btn.textContent = 'Confirm & Print Tickets';
    }

    playDingSound();
    showToast(`✓ All ${itemsToProcess.length} pre-filled vehicles approved!`);
    await loadPendingRequests();
    await loadActiveSessions();
}

async function batchApprovePreFilled() {
    const prefilledItems = Object.values(STATE.pendingRequestsMap || {}).filter(it => 
        it.source === 'self_signin' || it.source === 'cloud_mobile' || it.source === 'firebase_web'
    );

    if (prefilledItems.length === 0) {
        showToast('ℹ️ No pre-filled sign-ins waiting for approval.');
        return;
    }

    openBatchApproveModal(prefilledItems);
}

function prependPendingCard(item) {
    const grid = document.getElementById('pendingCardsGrid');
    if (!grid) return;

    // Check if empty message is present
    const emptyBox = grid.querySelector('.queue-empty-box');
    if (emptyBox) emptyBox.remove();

    STATE.pendingRequestsMap = STATE.pendingRequestsMap || {};
    const reqId = String(item.id || item.request_id);
    if (grid.querySelector(`[data-req-id="${reqId}"]`)) return;

    STATE.pendingRequestsMap[reqId] = item;

    const mode = STATE.queueViewMode || 'cards';
    const card = createPendingItemElement(item, mode);
    grid.prepend(card);

    updatePendingQueueBadge();
    applyQueueFilters();

    if (window.gsap) {
        gsap.fromTo(card,
            { opacity: 0, y: -28, scale: 0.96 },
            { opacity: 1, y: 0, scale: 1, duration: 0.45, ease: 'power2.out' }
        );
    }
    card.classList.add('just-arrived-pulse');
    setTimeout(() => card.classList.remove('just-arrived-pulse'), 3000);
}

function updatePendingQueueBadge() {
    const grid = document.getElementById('pendingCardsGrid');
    if (!grid) return;
    const cards = grid.querySelectorAll('[data-req-id]');
    const count = cards.length;
    const badge = document.getElementById('pendingCountBadge');
    const pnlPending = document.getElementById('pnlPending');

    if (badge) {
        badge.textContent = count > 0 ? `${count} Waiting` : '0 Waiting';
        badge.classList.toggle('has-items', count > 0);
    }
    if (pnlPending) pnlPending.textContent = String(count);

    if (count === 0 && !grid.querySelector('.queue-empty-box')) {
        grid.innerHTML = `
            <div class="queue-empty-box">
                <div class="queue-empty-radar">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10"/></svg>
                </div>
                <div class="queue-empty-text">Camera Active &amp; Ready.<br>Cars arriving at Gate 1 will appear here.</div>
            </div>`;
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

function createPendingItemElement(item, mode = 'cards') {
    return mode === 'compact' ? createPendingRowElement(item) : createPendingCardElement(item);
}

function createPendingRowElement(item) {
    const row = document.createElement('div');
    const reqId = String(item.id || item.request_id);
    const isSelf = (item.source === 'self_signin' || item.source === 'cloud_mobile' || item.source === 'firebase_web');

    row.className = `camera-queue-row ${isSelf ? 'self-checkin' : 'needs-data'}`;
    row.id = `req-row-${reqId}`;
    row.setAttribute('data-plate', item.plate_number);
    row.setAttribute('data-req-id', reqId);
    row.setAttribute('data-driver-name', item.driver_name || '');
    row.setAttribute('role', 'button');
    row.onclick = () => openVehicleIntakeModal(reqId);

    const plateDisplay = escapeHtml(item.formatted_plate || item.plate_number);
    const driverDisplay = escapeHtml(item.driver_name || (isSelf ? 'Visitor' : 'Awaiting Name'));
    const destDisplay = escapeHtml(item.destination || 'Mall Store');
    const waitDisplay = escapeHtml(item.wait_text || 'Just now');

    row.innerHTML = `
        <div class="cqr-plate-col">
            <span class="cqr-plate">${plateDisplay}</span>
            <span class="cqr-tag ${isSelf ? 'self' : 'intake'}">${isSelf ? 'Pre-Filled' : 'Needs Intake'}</span>
        </div>
        <div class="cqr-info-col">
            <span class="cqr-driver">${driverDisplay}</span>
            <span class="cqr-dest">• ${destDisplay}</span>
            <span class="cqr-time">• ${waitDisplay}</span>
        </div>
        <div class="cqr-action-col">
            <button class="btn-cqr-admit ${isSelf ? 'approve' : ''}" onclick="event.stopPropagation(); openVehicleIntakeModal('${reqId}');" title="${isSelf ? 'Approve & Print Pass' : 'Enter Details & Admit'}">
                ${isSelf ? 'Approve' : 'Admit'}
            </button>
            <button class="btn-cqr-del" onclick="event.stopPropagation(); releasePendingRecord('${reqId}');" title="Release vehicle from queue">
                ✕
            </button>
        </div>
    `;

    return row;
}

function createPendingCardElement(item) {
    const card = document.createElement('div');
    const reqId = String(item.id || item.request_id);
    STATE.pendingRequestsMap = STATE.pendingRequestsMap || {};
    STATE.pendingRequestsMap[reqId] = item;

    const isSelfCheckin = (item.source === 'self_signin' || item.source === 'cloud_mobile' || item.source === 'firebase_web');

    card.className = `camera-queue-card ${isSelfCheckin ? 'self-checkin' : 'needs-data'} ${item.category === 'blacklisted' ? 'blacklisted' : ''} ${item.category === 'vip' ? 'vip' : ''}`;
    card.id = `req-card-${reqId}`;
    card.setAttribute('data-plate', item.plate_number);
    card.setAttribute('data-req-id', reqId);
    card.setAttribute('data-driver-name', item.driver_name || '');
    card.setAttribute('tabindex', '0');
    card.setAttribute('role', 'button');
    card.setAttribute('aria-label', `Vehicle ${item.formatted_plate}, tap to admit`);
    card.style.cursor = 'pointer';

    // Direct tap on card immediately opens intake modal for the guard
    card.onclick = (e) => {
        if (e.target.closest('button')) return; // let buttons handle their own actions
        openVehicleIntakeModal(reqId);
    };
    card.onkeydown = (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openVehicleIntakeModal(reqId);
        }
    };

    // Snapshot preview thumbnail or placeholder
    let snapHtml = '';
    if (item.snapshot_url) {
        snapHtml = `<img src="../${item.snapshot_url}" class="cqc-thumb-img" alt="Camera Snapshot" loading="lazy" onclick="window.open(this.src, '_blank')">`;
    } else {
        snapHtml = `<svg class="i-icon" style="width:24px;height:24px;color:var(--blue-mid);" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>`;
    }

    // Wait & Entry Time
    const waitText = item.wait_text || 'Just now';

    // Driver & Destination Info
    let driverInfoHtml = '';
    if (isSelfCheckin || (item.driver_name && item.driver_name !== 'Visitor')) {
        driverInfoHtml = `
            <div class="cqc-driver-info">
                <span>👤</span>
                <span>${escapeHtml(item.driver_name || 'Driver')}</span>
                ${item.driver_phone ? `<span style="font-size:11px;color:var(--text-muted);">(${escapeHtml(item.driver_phone)})</span>` : ''}
            </div>
            <div class="cqc-dest-info">
                <span>📍</span>
                <span>${escapeHtml(item.destination || 'Mombasa Mall')}</span>
            </div>
        `;
    } else {
        driverInfoHtml = `
            <div class="cqc-driver-info" style="color:var(--amber); font-weight:800;">
                <span>⚠️</span>
                <span>Awaiting Details Input</span>
            </div>
            <div class="cqc-dest-info" style="color:var(--text-muted); font-size:11px;">
                <span>Tap below to assign store</span>
            </div>
        `;
    }

    // Tag badge
    const tagBadge = isSelfCheckin 
        ? `<span class="cqc-badge-tag self-checkin">📱 Pre-Filled</span>`
        : `<span class="cqc-badge-tag needs-data">⚠️ Needs Data</span>`;

    // Action button
    const actionBtn = isSelfCheckin
        ? `<button class="btn-cqc-primary approve" onclick="event.stopPropagation(); openVehicleIntakeModal('${reqId}');">
               ✓ Approve &amp; Print
           </button>`
        : `<button class="btn-cqc-primary" onclick="event.stopPropagation(); openVehicleIntakeModal('${reqId}');">
               ✎ Enter Details &amp; Admit
           </button>`;

    card.innerHTML = `
        <div class="cqc-header" onclick="openVehicleIntakeModal('${reqId}')">
            <div class="cqc-thumb-wrap">
                ${snapHtml}
            </div>
            <div class="cqc-plate-wrap">
                <div class="cqc-plate-badge">${escapeHtml(item.formatted_plate || item.plate_number)}</div>
                <div class="cqc-meta-row">
                    ${tagBadge}
                    <span class="cqc-time-ago">${escapeHtml(waitText)}</span>
                </div>
            </div>
        </div>
        <div class="cqc-body" onclick="openVehicleIntakeModal('${reqId}')">
            ${driverInfoHtml}
        </div>
        <div class="cqc-actions">
            ${actionBtn}
            <button class="btn-cqc-dismiss" onclick="event.stopPropagation(); releasePendingRecord('${reqId}');" title="Release record from queue (Dismiss drive-by)">
                ✕ Release
            </button>
        </div>
    `;

    return card;
}

function declineIntakeFromCard(reqId) {
    if (typeof openRejectDialog === 'function') {
        openRejectDialog(reqId);
    }
}

/**
 * 1-Tap Fast Record Release / Dismissal from Gate Intake Queue
 */
async function releasePendingRecord(reqId) {
    if (!reqId) return;

    // Smooth animated slide-out
    const card = document.querySelector(`[data-req-id="${reqId}"]`);
    if (card) {
        if (window.gsap) {
            gsap.to(card, {
                x: 60,
                opacity: 0,
                height: 0,
                marginBottom: 0,
                paddingTop: 0,
                paddingBottom: 0,
                duration: 0.22,
                ease: 'power2.in',
                onComplete: () => {
                    card.remove();
                    updatePendingQueueBadge();
                }
            });
        } else {
            card.remove();
            updatePendingQueueBadge();
        }
    }

    if (STATE.pendingRequestsMap) {
        delete STATE.pendingRequestsMap[reqId];
    }
    updatePendingQueueBadge();

    try {
        const res = await fetch('../api/requests/action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'dismiss', request_id: reqId })
        });
        const json = await res.json();
        if (json.ok) {
            showToast('Vehicle released from intake queue.', 'info');
        }
    } catch (e) {
        console.warn('Error dismissing record:', e);
    }
}

function removePendingCardById(reqId) {
    if (!reqId) return;
    const card = document.querySelector(`[data-req-id="${reqId}"]`);
    if (card) {
        if (window.gsap) {
            gsap.to(card, {
                x: 50,
                opacity: 0,
                height: 0,
                marginBottom: 0,
                duration: 0.22,
                ease: 'power2.in',
                onComplete: () => {
                    card.remove();
                    updatePendingQueueBadge();
                }
            });
        } else {
            card.remove();
            updatePendingQueueBadge();
        }
    }
    if (STATE.pendingRequestsMap) {
        delete STATE.pendingRequestsMap[reqId];
    }
}

/**
 * Rapidly Clear & Release Stale Queue Records (Older than 15 mins)
 */
async function releaseStaleQueue() {
    const btn = document.getElementById('btnReleaseStaleQueue');
    const btnText = document.getElementById('btnReleaseStaleText');
    if (btn) btn.disabled = true;
    if (btnText) btnText.textContent = 'Releasing...';

    try {
        const res = await fetch('../api/requests/action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'clear_stale', minutes: 15 })
        });
        const json = await res.json();
        if (json.ok) {
            showToast(`🧹 Released ${json.cleared_count || 0} stale records.`, 'ok');
            await loadPendingRequests();
        } else {
            showToast(json.error || 'Failed to release stale records.', 'error');
        }
    } catch (e) {
        console.error('Error clearing stale:', e);
        showToast('Network error while releasing stale queue.', 'error');
    } finally {
        if (btn) btn.disabled = false;
        if (btnText) btnText.textContent = 'Release Stale';
    }
}

// --------------------------------------------------------------------
// Vehicle Intake Popup Modal (Tap-to-Fill for Guards)
// --------------------------------------------------------------------
STATE.currentIntakeReqId = null;

function openVehicleIntakeModal(requestId) {
    let item = (STATE.pendingRequestsMap && STATE.pendingRequestsMap[requestId]) || null;
    if (!item && requestId) {
        item = (STATE.pendingRequests || []).find(r => (r.id == requestId || r.request_id == requestId)) || null;
    }
    if (!item) {
        if (!requestId || requestId === 0) {
            if (typeof openManualCheckinModal === 'function') {
                openManualCheckinModal();
                return;
            }
        }
        showToast('Vehicle request is no longer pending or has already been processed.', 'info');
        return;
    }

    STATE.currentIntakeReqId = requestId;
    const isSelfCheckin = (item.source === 'self_signin' || item.source === 'cloud_mobile' || item.source === 'firebase_web');

    // Set plate
    const plateEl = document.getElementById('intakePlateDisplay');
    if (plateEl) plateEl.textContent = item.formatted_plate || item.plate_number;

    // Set snapshot
    const snapImg = document.getElementById('intakeSnapImg');
    const snapWrap = document.getElementById('intakeSnapWrapper');
    if (snapImg && snapWrap) {
        if (item.snapshot_url) {
            snapImg.src = '../' + item.snapshot_url;
            snapWrap.style.display = 'block';
        } else {
            snapWrap.style.display = 'none';
        }
    }

    // Set Self-Check-in Banner
    const selfBanner = document.getElementById('intakeSelfCheckinBanner');
    if (selfBanner) {
        selfBanner.style.display = isSelfCheckin ? 'flex' : 'none';
    }

    // Set badges
    const srcBadge = document.getElementById('intakeSourceBadge');
    if (srcBadge) {
        if (isSelfCheckin) {
            srcBadge.style.display = 'inline-flex';
            srcBadge.style.background = '#059669';
            srcBadge.style.color = '#fff';
            srcBadge.textContent = '📱 DRIVER SELF CHECK-IN';
        } else if (item.source === 'alpr_camera') {
            srcBadge.style.display = 'inline-flex';
            srcBadge.style.background = '';
            srcBadge.style.color = '';
            srcBadge.textContent = '📷 CAMERA';
        } else {
            srcBadge.style.display = 'none';
        }
    }

    const typeBadge = document.getElementById('intakeTypeBadge');
    if (typeBadge) {
        if (item.vehicle_type === 'tuktuk') {
            typeBadge.textContent = '🛺 TUKTUK';
            typeBadge.style.background = '#f59e0b';
            typeBadge.style.color = '#000';
            typeBadge.style.display = 'inline-flex';
        } else if (item.vehicle_type === 'government') {
            typeBadge.textContent = '🏛️ GOVT';
            typeBadge.style.background = '#0284c7';
            typeBadge.style.color = '#fff';
            typeBadge.style.display = 'inline-flex';
        } else {
            typeBadge.style.display = 'none';
        }
    }

    const verBadge = document.getElementById('intakeVerifiedBadge');
    if (verBadge) verBadge.style.display = item.alpr_verified ? 'inline-block' : 'none';

    const entryTimeEl = document.getElementById('intakeEntryTime');
    if (entryTimeEl) {
        if (item.entry_time_display || item.entry_time) {
            entryTimeEl.textContent = `In: ${item.entry_time_display || item.entry_time.split(' ')[1]}`;
            entryTimeEl.style.display = 'inline-block';
        } else {
            entryTimeEl.style.display = 'none';
        }
    }

    const timeAgo = document.getElementById('intakeTimeAgo');
    if (timeAgo) timeAgo.textContent = item.wait_text || 'Just now';

    // Update Accept Button Text to emphasize approval & shopping
    const btnText = document.getElementById('btnIntakeAcceptText');
    if (btnText) {
        btnText.textContent = isSelfCheckin
            ? 'APPROVE & PRINT TICKET (PROCEED TO SHOPPING)'
            : 'ACCEPT & PRINT TICKET';
    }

    // Alerts
    const alertEl = document.getElementById('intakeAlertBanner');
    if (alertEl) {
        if (item.category === 'blacklisted') {
            alertEl.style.display = 'block';
            alertEl.className = 'badge-alert';
            alertEl.textContent = `⚠️ BLACKLISTED: ${item.category_notes || 'DO NOT ADMIT'}`;
        } else if (item.category === 'vip') {
            alertEl.style.display = 'block';
            alertEl.className = 'badge-alert';
            alertEl.style.background = '#7c3aed';
            alertEl.style.color = '#fff';
            alertEl.textContent = `⭐ VIP GUEST`;
        } else {
            alertEl.style.display = 'none';
        }
    }

    // Driver Name & Phone
    const nameIn  = document.getElementById('intakeDriverName');
    const phoneIn = document.getElementById('intakeDriverPhone');
    if (nameIn) {
        nameIn.value = (item.driver_name && item.driver_name !== 'Visitor') ? item.driver_name : 'Visitor';
    }
    if (phoneIn) {
        phoneIn.value = item.raw_phone || item.driver_phone || '';
    }

    // Destinations setup (Floor tabs & outlined shops)
    let initialDest = item.destination || 'G-01 Naivas Supermarket';
    if (STATE.destinations && STATE.destinations.length > 0) {
        const found = STATE.destinations.find(d => 
            d.name.toLowerCase() === initialDest.toLowerCase() ||
            d.name.toLowerCase().includes(initialDest.toLowerCase())
        );
        if (found) {
            initialDest = found.name;
            STATE.intakeActiveFloor = found.floor_level || 'Ground Floor';
        } else {
            STATE.intakeActiveFloor = 'Ground Floor';
        }
    } else {
        STATE.intakeActiveFloor = 'Ground Floor';
    }

    STATE.intakeSelectedDest = initialDest;
    const destValIn = document.getElementById('intakeDestinationValue');
    if (destValIn) destValIn.value = initialDest;

    const destLabelEl = document.getElementById('intakeSelectedDestLabel');
    if (destLabelEl) destLabelEl.textContent = initialDest;

    switchIntakeFloor(STATE.intakeActiveFloor);

    // Reset button state and customize text for self check-in
    const btnAccept = document.getElementById('btnIntakeAccept');
    if (btnAccept) {
        btnAccept.disabled = false;
    }
    if (btnText) {
        btnText.textContent = isSelfCheckin
            ? 'APPROVE & PRINT TICKET (PROCEED TO SHOPPING)'
            : 'ACCEPT & PRINT TICKET';
    }

    // Open Modal
    const modal = document.getElementById('vehicleIntakeModal');
    if (modal) {
        modal.classList.add('open');
        playDingSound();
        if (window.gsap) {
            gsap.fromTo('.intake-modal-box', { scale: 0.93, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.2, ease: 'power2.out' });
        }
        setTimeout(() => {
            if (nameIn) {
                nameIn.focus();
                nameIn.select();
            }
        }, 120);
    }
}

function normalizeFloorLevel(fl) {
    if (!fl) return 'Ground Floor';
    const s = String(fl).toLowerCase().trim();
    if (s.includes('ground') || s === 'g') return 'Ground Floor';
    if (s.includes('1') || s.includes('first')) return '1st Floor';
    if (s.includes('2') || s.includes('second')) return '2nd Floor';
    if (s.includes('3') || s.includes('third')) return '3rd Floor';
    if (s.includes('base') || s === 'b') return 'Basement';
    return 'Ground Floor';
}

function switchIntakeFloor(floorLevel, btnEl) {
    const norm = normalizeFloorLevel(floorLevel);
    STATE.intakeActiveFloor = norm;

    const tabsContainer = document.getElementById('intakeFloorTabs');
    if (tabsContainer) {
        tabsContainer.querySelectorAll('.intake-floor-tab').forEach(b => {
            const bFloor = normalizeFloorLevel(b.getAttribute('data-floor'));
            if (bFloor === norm) {
                b.classList.add('active');
            } else {
                b.classList.remove('active');
            }
        });
    }

    renderIntakeShops();
}

function renderIntakeShops() {
    const grid = document.getElementById('intakeShopsGrid');
    if (!grid) return;

    grid.innerHTML = '';
    const activeFloor = STATE.intakeActiveFloor || 'Ground Floor';
    const normActive = normalizeFloorLevel(activeFloor);

    const allDests = (STATE.destinations && STATE.destinations.length > 0) ? STATE.destinations : [
        { name: 'G-01 Naivas Supermarket', unit_code: 'G-01', floor_level: 'Ground Floor', category: 'Hypermarket' },
        { name: 'G-02 Chicken Inn', unit_code: 'G-02', floor_level: 'Ground Floor', category: 'Fast Food' },
        { name: 'G-03 Creamy Inn', unit_code: 'G-03', floor_level: 'Ground Floor', category: 'Fast Food' },
        { name: 'G-04 Pizza Inn', unit_code: 'G-04', floor_level: 'Ground Floor', category: 'Fast Food' },
        { name: 'Mall Administration', unit_code: 'ADM', floor_level: 'Ground Floor', category: 'Management' },
    ];

    const shops = allDests.filter(d => normalizeFloorLevel(d.floor_level) === normActive);

    if (shops.length === 0) {
        grid.innerHTML = `<div style="grid-column:1/-1; padding:20px; text-align:center; color:var(--text-muted); font-size:12px;">No shops found on ${escapeHtml(activeFloor)}.</div>`;
        return;
    }

    shops.forEach(d => {
        const btn = document.createElement('button');
        btn.type = 'button';
        const isSel = (STATE.intakeSelectedDest && (STATE.intakeSelectedDest.toLowerCase() === d.name.toLowerCase() || STATE.intakeSelectedDest.toLowerCase().includes(d.name.toLowerCase())));
        btn.className = `intake-shop-btn ${isSel ? 'active' : ''}`;
        btn.setAttribute('data-name', d.name);
        btn.onclick = () => selectIntakeShop(d.name, btn);

        const unitTag = d.unit_code ? `<span class="shop-unit-tag">${escapeHtml(d.unit_code)}</span>` : '';
        const catTag  = d.category  ? `<span class="shop-cat-text">${escapeHtml(d.category)}</span>` : '';

        btn.innerHTML = `
            ${unitTag}
            <span class="shop-name-text">${escapeHtml(d.name)}</span>
            ${catTag}
        `;
        grid.appendChild(btn);
    });
}

function selectIntakeShop(storeName, btnEl) {
    STATE.intakeSelectedDest = storeName;

    const destValIn = document.getElementById('intakeDestinationValue');
    if (destValIn) destValIn.value = storeName;

    const destLabelEl = document.getElementById('intakeSelectedDestLabel');
    if (destLabelEl) destLabelEl.textContent = storeName;

    const grid = document.getElementById('intakeShopsGrid');
    if (grid) {
        grid.querySelectorAll('.intake-shop-btn').forEach(b => b.classList.remove('active'));
    }
    if (btnEl) btnEl.classList.add('active');
}

function closeVehicleIntakeModal() {
    const modal = document.getElementById('vehicleIntakeModal');
    if (!modal) return;
    const box = modal.querySelector('.intake-modal-box');
    if (box && window.gsap) {
        gsap.to(box, {
            scale: 0.93,
            opacity: 0,
            duration: 0.16,
            ease: 'power2.in',
            onComplete: () => {
                modal.classList.remove('open');
                STATE.currentIntakeReqId = null;
            }
        });
    } else {
        modal.classList.remove('open');
        STATE.currentIntakeReqId = null;
    }
}

function declineCurrentIntake() {
    const reqId = STATE.currentIntakeReqId;
    closeVehicleIntakeModal();
    if (reqId) {
        openRejectDialog(reqId);
    }
}

async function submitIntakeFromModal() {
    const requestId = STATE.currentIntakeReqId;
    if (!requestId) {
        showToast('No vehicle selected for intake.');
        return;
    }

    const nameIn  = document.getElementById('intakeDriverName');
    const phoneIn = document.getElementById('intakeDriverPhone');
    const destIn  = document.getElementById('intakeDestinationValue');

    const name  = nameIn ? nameIn.value.trim() : 'Visitor';
    const phone = phoneIn ? phoneIn.value.trim() : '';
    const dest  = (destIn && destIn.value.trim()) ? destIn.value.trim() : (STATE.intakeSelectedDest || 'G-01 Naivas Supermarket');

    const btnAccept = document.getElementById('btnIntakeAccept');
    const btnText   = document.getElementById('btnIntakeAcceptText');
    if (btnAccept) btnAccept.disabled = true;
    if (btnText) btnText.textContent = 'Printing Ticket...';

    const pendingItem = (STATE.pendingRequestsMap && STATE.pendingRequestsMap[requestId]) || null;
    const isNumericId = typeof requestId === 'number' || (/^\d+$/.test(String(requestId)));
    const plateEl = document.getElementById('intakePlateDisplay');
    const plateVal = pendingItem ? (pendingItem.plate_number || pendingItem.formatted_plate) : (plateEl ? plateEl.textContent : '');

    try {
        const res = await fetch('../api/gate/approve-session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                request_id: isNumericId ? parseInt(requestId, 10) : 0,
                plate_number: plateVal,
                driver_name: name || 'Visitor',
                driver_phone: phone,
                destination: dest || 'Mombasa Mall',
            }),
        });
        const json = await res.json();

        if (json.ok) {
            playSuccessChime();

            // Notify Cloud Firestore if this was a remote cloud sign-in
            if (pendingItem && pendingItem.firestore_id && window.firebaseBridge && typeof window.firebaseBridge.admitDriverRequest === 'function') {
                window.firebaseBridge.admitDriverRequest(pendingItem.firestore_id, json.data.ticket_id);
            }

            closeVehicleIntakeModal();

            if (json.data.print_status === 'printed') {
                showToast(`✓ Ticket #${json.data.ticket_id} Printed for ${json.data.formatted_plate}`);
            } else {
                showToast(`✓ Ticket #${json.data.ticket_id} Saved (${json.data.formatted_plate})`, true, json.data.session_id);
            }

            removePendingCard(json.data.plate_number);
            if (STATE.pendingRequestsMap) {
                delete STATE.pendingRequestsMap[requestId];
            }

            // Immediately refresh basement roster to show the newly parked car!
            loadActiveSessions();

            // Update stats
            runPoll();

            // Display on-screen printable thermal receipt
            showReceiptModal(json.data);
        } else {
            if (btnAccept) btnAccept.disabled = false;
            if (btnText) btnText.textContent = 'ACCEPT & PRINT TICKET';
            showToast(json.error || 'Failed to approve session.');
        }
    } catch (e) {
        if (btnAccept) btnAccept.disabled = false;
        if (btnText) btnText.textContent = 'ACCEPT & PRINT TICKET';
        showToast('Network error while approving ticket.');
    }
}

function removePendingCard(plate) {
    if (!plate) return;
    const clean = plate.replace(/[^A-Z0-9]/gi, '').toUpperCase();
    document.querySelectorAll('.camera-queue-card, .car-card').forEach(card => {
        const cPlate = (card.getAttribute('data-plate') || '').replace(/[^A-Z0-9]/gi, '').toUpperCase();
        if (cPlate === clean) {
            if (window.gsap) {
                gsap.to(card, {
                    x: 40,
                    opacity: 0,
                    height: 0,
                    marginBottom: 0,
                    duration: 0.3,
                    onComplete: () => {
                        card.remove();
                        if (typeof updatePendingQueueBadge === 'function') updatePendingQueueBadge();
                    }
                });
            } else {
                card.remove();
                if (typeof updatePendingQueueBadge === 'function') updatePendingQueueBadge();
            }
        }
    });
}

function markCardVerified(reqId) {
    const card = document.getElementById(`req-card-${reqId}`);
    if (card) {
        card.classList.add('verified');
        const badge = card.querySelector('.badge-verified');
        if (!badge) {
            const row = card.querySelector('.card-meta-row');
            if (row) {
                const newBadge = document.createElement('span');
                newBadge.className = 'badge-verified';
                newBadge.innerHTML = `✓ ${t('verified_badge')}`;
                row.appendChild(newBadge);
            }
        }
    }
}

function updatePendingCardWithDriverDetails(p) {
    if (!p) return;
    const reqId = String(p.request_id || p.id);
    const grid = document.getElementById('pendingCardsGrid');
    if (!grid) return;

    STATE.pendingRequestsMap = STATE.pendingRequestsMap || {};
    const existing = STATE.pendingRequestsMap[reqId] || {};
    const updated = {
        ...existing,
        ...p,
        source: 'self_signin',
        driver_name: p.driver_name || existing.driver_name || 'Visitor',
        driver_phone: p.driver_phone || p.raw_phone || existing.driver_phone || '',
        destination: p.destination || existing.destination || 'Mombasa Mall',
        wait_text: existing.wait_text || 'Just now',
    };
    STATE.pendingRequestsMap[reqId] = updated;

    let card = document.getElementById(`req-card-${reqId}`) || 
               grid.querySelector(`[data-plate="${p.plate_number}"]`) || 
               grid.querySelector(`[data-req-id="${reqId}"]`);

    const mode = STATE.queueViewMode || 'cards';
    const newCard = createPendingItemElement(updated, mode);

    if (card) {
        grid.replaceChild(newCard, card);
        if (window.gsap) {
            gsap.fromTo(newCard, 
                { scale: 0.95, backgroundColor: '#dcfce7' },
                { scale: 1, backgroundColor: '#ffffff', duration: 0.5, ease: 'power2.out' }
            );
        }
    } else {
        grid.prepend(newCard);
        if (window.gsap) {
            gsap.from(newCard, { x: 40, opacity: 0, duration: 0.35, ease: 'power2.out' });
        }
    }

    applyQueueFilters();
    updatePendingQueueBadge();
    showToast(`📱 Driver ${p.driver_name || 'Visitor'} (${p.formatted_plate || p.plate_number}) self-signed in!`);
}

// Legacy alias
function acceptWithQuickDetails(requestId) {
    if (requestId && STATE.pendingRequestsMap && STATE.pendingRequestsMap[requestId]) {
        openVehicleIntakeModal(requestId);
    }
}

function acceptAndPrint(requestId) {
    openVehicleIntakeModal(requestId);
}

// --------------------------------------------------------------------
// Thermal Receipt Modal & Printing (2.5 Hours Free Stay)
// --------------------------------------------------------------------
function showReceiptModal(data) {
    if (!data) return;

    const rcptTicketId    = document.getElementById('rcptTicketId');
    const rcptBarcodeNum  = document.getElementById('rcptBarcodeNum');
    const rcptPlate       = document.getElementById('rcptPlate');
    const rcptDriver      = document.getElementById('rcptDriver');
    const rcptPhone       = document.getElementById('rcptPhone');
    const rcptPhoneRow    = document.getElementById('rcptPhoneRow');
    const rcptDestination = document.getElementById('rcptDestination');
    const rcptGuard       = document.getElementById('rcptGuard');
    const rcptTimeIn      = document.getElementById('rcptTimeIn');
    const rcptTimeOut     = document.getElementById('rcptTimeOut');

    const ticketId = data.ticket_id || 'MM-TICKET';
    const plate    = data.formatted_plate || data.plate_number || 'KDM 687V';
    const driver   = data.driver_name || 'Visitor';
    const phone    = data.driver_phone || '';
    const dest     = data.destination || 'Mombasa Mall';
    const guard    = data.guard_name ? `${data.guard_name} (Gate 1)` : (STATE.user ? `${STATE.user.full_name} (Gate 1)` : 'Security Officer (Gate 1)');

    // Format times
    let timeInText = data.entry_time_fmt;
    if (!timeInText) {
        const inDate = data.entry_time ? new Date(data.entry_time.replace(' ', 'T')) : new Date();
        timeInText = formatReceiptTime(inDate);
    }

    let timeOutText = data.expected_exit_fmt;
    if (!timeOutText) {
        if (data.expected_exit_time) {
            const outDate = new Date(data.expected_exit_time.replace(' ', 'T'));
            timeOutText = formatReceiptTime(outDate);
        } else {
            const inDate = data.entry_time ? new Date(data.entry_time.replace(' ', 'T')) : new Date();
            const outDate = new Date(inDate.getTime() + (120 * 60 * 1000)); // 2 hours (120 mins)
            timeOutText = formatReceiptTime(outDate);
        }
    }

    if (rcptTicketId)    rcptTicketId.textContent   = ticketId;
    if (rcptBarcodeNum)  rcptBarcodeNum.textContent = ticketId;
    if (rcptPlate)       rcptPlate.textContent      = plate;
    if (rcptDriver)      rcptDriver.textContent     = driver;
    if (rcptDestination) rcptDestination.textContent= dest;
    if (rcptGuard)       rcptGuard.textContent      = guard;
    if (rcptTimeIn)      rcptTimeIn.textContent     = timeInText;
    if (rcptTimeOut)     rcptTimeOut.textContent    = timeOutText;

    if (rcptPhoneRow) {
        if (phone && phone.trim() !== '') {
            rcptPhoneRow.style.display = 'flex';
            if (rcptPhone) rcptPhone.textContent = phone;
        } else {
            rcptPhoneRow.style.display = 'none';
        }
    }

    const modal = document.getElementById('receiptPreviewModal');
    if (modal) {
        modal.classList.add('open');
    }
}

function formatReceiptTime(d) {
    if (!d || isNaN(d.getTime())) d = new Date();
    const pad = n => String(n).padStart(2, '0');
    const time = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    const date = `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
    return `${time} • ${date}`;
}

function closeReceiptModal() {
    const modal = document.getElementById('receiptPreviewModal');
    if (modal) {
        modal.classList.remove('open');
    }
}

function printReceiptDirect() {
    window.print();
}

// --------------------------------------------------------------------
// On-Demand Camera Plate Fetcher (Entrance & Exit Cameras)
// --------------------------------------------------------------------
async function triggerCameraPlateFetch(targetCam = 'entrance') {
    const isExit = targetCam === 'exit';
    const btn = document.getElementById(isExit ? 'btnFetchExitCameraPlate' : 'btnFetchCameraPlate');
    const btnText = document.getElementById(isExit ? 'btnFetchExitCamText' : 'btnFetchCamText');
    const origText = btnText ? btnText.textContent : (isExit ? 'Fetch Exit Camera Plate Now' : 'Fetch Camera Plate Now');

    if (btn) {
        btn.disabled = true;
        if (btnText) btnText.textContent = isExit ? 'Scanning Dahua NVR (192.168.0.3 Ch 2)...' : 'Scanning Dahua NVR (192.168.0.3 Ch 1)...';
    }

    try {
        const res = await fetch('../api/gate/fetch-camera-plate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ camera: isExit ? 'exit' : 'entrance' }),
        });
        const json = await res.json();

        if (json.ok) {
            if (json.plate_found && json.data) {
                playDingSound();
                showToast(`✓ ${isExit ? 'Exit' : 'Entrance'} camera detected: ${json.data.formatted_plate}`);
                
                if (isExit) {
                    const searchInput = document.getElementById('exitSearchInput');
                    if (searchInput) {
                        searchInput.value = json.data.formatted_plate || json.data.plate_number;
                    }
                    searchExitSessions(json.data.plate_number);
                } else {
                    showEntranceAlprBanner({
                        formatted_plate: json.data.formatted_plate,
                        plate_number: json.data.plate_number,
                        confidence: json.data.confidence || 0.95,
                        time: new Date().toLocaleTimeString('en-GB')
                    });
                    await loadPendingRequests();
                    setTimeout(() => {
                        openVehicleIntakeModal(json.data.request_id);
                    }, 200);
                }
            } else if (json.is_duplicate && !isExit) {
                showToast(`⚠️ Vehicle ${json.formatted_plate} is already inside.`);
            } else {
                showToast(json.message || `Dahua NVR (${isExit ? '192.168.0.3 Ch 2' : '192.168.0.3 Ch 1'}) is live.`);
            }

            const statusLabel = document.getElementById(isExit ? 'camExitStatusLabel' : 'camEntranceStatusLabel');
            if (statusLabel) {
                statusLabel.textContent = json.camera_online 
                    ? (isExit ? 'Dahua NVR Exit (192.168.0.3 Ch 2): Online' : 'Dahua NVR (192.168.0.3 Ch 1): Online')
                    : (isExit ? 'Dahua NVR Exit (192.168.0.3 Ch 2): Connecting...' : 'Dahua NVR (192.168.0.3 Ch 1): Connecting...');
            }
        } else {
            showToast(json.error || 'Failed to fetch camera plate.');
        }
    } catch (e) {
        showToast(`Could not reach Dahua NVR ${isExit ? 'Exit (192.168.0.3 Ch 2)' : 'Entrance (192.168.0.3 Ch 1)'} endpoint.`);
    } finally {
        if (btn) {
            btn.disabled = false;
            if (btnText) btnText.textContent = origText;
        }
    }
}

// --------------------------------------------------------------------
// Fast Queue & Gate Synchronization Capability
// --------------------------------------------------------------------
let isFastSyncing = false;

async function fastRefreshEntryQueue(userFeedback = false) {
    if (isFastSyncing) return;
    isFastSyncing = true;

    const btn = document.getElementById('btnFastRefreshQueue');
    const icon = btn ? btn.querySelector('.i-icon-refresh') : null;
    if (btn) btn.classList.add('syncing');
    if (icon) icon.classList.add('spinning');

    try {
        await Promise.allSettled([
            loadPendingRequests(),
            loadActiveSessions(),
            runPoll()
        ]);

        if (userFeedback) {
            playDingSound();
            const count = (STATE.latestPendingItems && Array.isArray(STATE.latestPendingItems)) ? STATE.latestPendingItems.length : 0;
            showToast(`✓ Gate queue synced with live camera (${count} waiting)`);
        }
    } catch (e) {
        console.error('Fast refresh error:', e);
        if (userFeedback) {
            showToast('⚠️ Sync error connecting to gate services.');
        }
    } finally {
        setTimeout(() => {
            if (btn) btn.classList.remove('syncing');
            if (icon) icon.classList.remove('spinning');
            isFastSyncing = false;
        }, 500);
    }
}

async function simulateDemoArrival() {
    showToast('ℹ️ Simulation disabled. Live Dahua ANPR camera feed is active.');
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
    if (!session) return;
    STATE.activeExitSession = session;
    const area = document.getElementById('exitCandidateArea');
    if (!area) return;

    // Clean up plate string (strip out [ KE | ... ] if already wrapped)
    let rawPlate = session.formatted_plate || session.plate_number || 'UNKNOWN';
    let cleanPlateStr = rawPlate.replace(/\[\s*KE\s*\|\s*/i, '').replace(/\s*\]/, '').trim();

    const durationMins = Number(session.duration_minutes) || 0;
    let dwellClass = 'chip-ok';
    let dwellTag = 'Regular Stay';
    if (durationMins > 480) {
        dwellClass = 'chip-alert';
        dwellTag = 'Overstay (>8h)';
    } else if (durationMins > 120) {
        dwellClass = 'chip-warning';
        dwellTag = 'Extended (>2h)';
    }

    const driverName = session.driver_name || 'Visitor';
    const driverPhone = session.driver_phone || '';
    const destination = session.destination || 'Mall Store';
    const entryTime = session.entry_time_display || session.entry_time || '--';
    const ticketId = session.ticket_id || 'MM-EXIT';

    const clearExitLabel = (typeof CURRENT_LANG !== 'undefined' && CURRENT_LANG === 'sw') 
        ? `RUHUSU KUTOKA &bull; FUNGUA LANGO (${cleanPlateStr})` 
        : `AUTHORIZE EXIT &bull; OPEN GATE BARRIER (${cleanPlateStr})`;

    area.innerHTML = `
        <div class="exit-card">
            <div class="exit-card-header">
                <div class="exit-cam-badge">
                    <span class="pulse-dot"></span>
                    <span>GATE 2 EXIT CAMERA (192.168.1.210) &bull; ${t('cam_sees_out')}</span>
                </div>
                <button type="button" class="btn-exit-card-dismiss" onclick="document.getElementById('exitCandidateArea').innerHTML=''" title="Dismiss Alert" aria-label="Dismiss">&times;</button>
            </div>

            <div class="exit-card-banner">
                <div class="exit-plate-wrapper">
                    <span class="plate-flag-ke">KE</span>
                    <span class="exit-plate-val">${escapeHtml(cleanPlateStr)}</span>
                </div>
                <div class="exit-ticket-wrapper">
                    <span class="exit-ticket-lbl">TICKET ID</span>
                    <span class="exit-ticket-num">${escapeHtml(ticketId)}</span>
                </div>
            </div>

            <div class="exit-meta-grid">
                <div class="exit-meta-item">
                    <span class="exit-meta-lbl">${t('driver')}</span>
                    <strong class="exit-meta-main">${escapeHtml(driverName)}</strong>
                    <span class="exit-meta-sub">${driverPhone ? escapeHtml(driverPhone) : 'Direct Plate Entry'}</span>
                </div>
                <div class="exit-meta-item">
                    <span class="exit-meta-lbl">${t('destination')}</span>
                    <strong class="exit-meta-main dest-accent">${escapeHtml(destination)}</strong>
                    <span class="exit-meta-sub">Validated Destination</span>
                </div>
                <div class="exit-meta-item">
                    <span class="exit-meta-lbl">${t('parked_time')}</span>
                    <div class="exit-dwell-chip ${dwellClass}">
                        <span class="dwell-val">${durationMins} mins</span>
                        <span class="dwell-tag">${dwellTag}</span>
                    </div>
                </div>
                <div class="exit-meta-item">
                    <span class="exit-meta-lbl">${t('entry_time') || 'Entry Time'}</span>
                    <strong class="exit-meta-main mono-val">${escapeHtml(entryTime)}</strong>
                    <span class="exit-meta-sub">Gate 1 Recorded Influx</span>
                </div>
            </div>

            <button type="button" class="btn-clear-exit-giant" onclick="executeClearExit(${session.session_id || session.id || 0}, '${escapeHtml(session.plate_number || cleanPlateStr)}')">
                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/><polyline points="15 18 21 12 15 6"/></svg>
                <span>${clearExitLabel}</span>
            </button>
        </div>
    `;

    // Automatically flip to Leaving tab if on another tab
    switchTab('leaving');

    if (window.gsap) {
        gsap.fromTo('.exit-card',
            { opacity: 0, y: -12, scale: 0.98 },
            { opacity: 1, y: 0, scale: 1, duration: 0.28, ease: 'power2.out' }
        );
    }
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
            if (item.is_uncollected) {
                card.style.borderColor = '#f59e0b';
                card.style.background = '#fffbeb';
                card.innerHTML = `
                    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <div class="card-plate" style="color:#b45309;">${escapeHtml(item.formatted_plate || item.plate_number)}</div>
                        <span style="font-weight:800; color:#d97706; font-family:monospace;">In: ${escapeHtml(item.entry_time_display || item.entry_time || item.dwell_string)}</span>
                    </div>
                    <div class="card-body" style="padding:10px 0;">
                        <div style="color:#b45309; font-weight:800; font-size:12.5px;">Details Not Collected</div>
                        <div style="color:#78350f; font-size:11.5px; margin-top:2px;">Wait: ${escapeHtml(item.dwell_string)} &bull; Entrance: ${escapeHtml(item.source || 'Camera')}</div>
                    </div>
                    <button class="btn-card-accept" style="background:#d97706; color:#fff;" onclick="executeClearExit(0, '${escapeHtml(item.plate_number)}')">
                        Authorize Exit (Uncollected Details)
                    </button>
                `;
            } else {
                card.style.borderColor = '#b0cfec';
                card.innerHTML = `
                    <div class="card-header">
                        <div class="card-plate">${escapeHtml(item.formatted_plate || item.plate_number)}</div>
                        <span style="font-weight:800; color:#0284c7; font-family:monospace;">Parked: ${escapeHtml(item.dwell_string)}</span>
                    </div>
                    <div class="card-body">
                        <div><span class="info-label">${t('driver')}</span><span class="info-val">${escapeHtml(item.driver_name)}</span></div>
                        <div><span class="info-label">${t('destination')}</span><span class="info-val" style="color:#0284c7;">${escapeHtml(item.destination)}</span></div>
                    </div>
                    <button class="btn-card-accept" style="background:#059669;" onclick="executeClearExit(${item.id})">
                        ${t('btn_clear_exit')} &bull; Open Barrier
                    </button>
                `;
            }
            area.appendChild(card);
        });
    } catch (e) {
        console.error('Search exit session error:', e);
    }
}

// --------------------------------------------------------------------
// Soft Exit Clearance Confirmation Modal
// --------------------------------------------------------------------
let pendingExitData = null;

function openExitConfirmModal(sessionId, plateNumber) {
    const modal = document.getElementById('exitConfirmModal');
    if (!modal) return;

    let session = null;
    if (STATE.activeSessionsList && Array.isArray(STATE.activeSessionsList)) {
        session = STATE.activeSessionsList.find(s => 
            (sessionId && s.session_id == sessionId) || 
            (plateNumber && s.plate_number == plateNumber)
        );
    }

    const plate = session ? (session.formatted_plate || session.plate_number) : (plateNumber || 'UNKNOWN');
    const bay = session ? (session.space_code || session.parking_space_code || 'General') : 'General Bay';
    const entryTime = session ? (session.entry_time_display || '--') : new Date().toLocaleTimeString('en-GB');
    
    let dwell = '--';
    if (session && session.entry_timestamp) {
        const mins = Math.max(0, Math.floor((Date.now() - session.entry_timestamp) / 60000));
        dwell = mins < 60 ? `${mins}m` : `${Math.floor(mins / 60)}h ${mins % 60}m`;
    }

    const plateEl = document.getElementById('exitConfirmPlate');
    const bayEl = document.getElementById('exitConfirmBay');
    const dwellEl = document.getElementById('exitConfirmDwell');
    const timeEl = document.getElementById('exitConfirmEntryTime');

    if (plateEl) plateEl.textContent = plate;
    if (bayEl) bayEl.textContent = bay;
    if (dwellEl) dwellEl.textContent = dwell;
    if (timeEl) timeEl.textContent = entryTime;

    pendingExitData = { sessionId, plateNumber, plate };

    const btn = document.getElementById('btnConfirmClearExit');
    const lbl = document.getElementById('exitBtnLabel');
    if (btn) {
        btn.disabled = false;
        btn.classList.remove('btn-loading');
    }
    if (lbl) lbl.textContent = 'Clear Exit & Lift Barrier';

    modal.classList.add('open');
    if (window.gsap) {
        gsap.fromTo('.exit-confirm-modal-box',
            { scale: 0.94, opacity: 0, y: 15 },
            { scale: 1, opacity: 1, y: 0, duration: 0.25, ease: 'back.out(1.4)' }
        );
    }
}

function closeExitConfirmModal() {
    const modal = document.getElementById('exitConfirmModal');
    if (!modal) return;
    if (window.gsap) {
        gsap.to('.exit-confirm-modal-box', {
            scale: 0.96, opacity: 0, y: 10, duration: 0.18, ease: 'power2.in',
            onComplete: () => {
                modal.classList.remove('open');
                pendingExitData = null;
            }
        });
    } else {
        modal.classList.remove('open');
        pendingExitData = null;
    }
}

async function commitClearExit() {
    if (!pendingExitData) return;

    const { sessionId, plateNumber, plate } = pendingExitData;
    const btn = document.getElementById('btnConfirmClearExit');
    const lbl = document.getElementById('exitBtnLabel');
    if (btn) {
        btn.disabled = true;
        btn.classList.add('btn-loading');
    }
    if (lbl) lbl.textContent = 'Lifting Barrier…';

    try {
        const payload = sessionId ? { session_id: sessionId } : { plate_number: plateNumber };
        const res = await fetch('../api/gate/clear-exit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();

        if (json.ok) {
            playSuccessChime();
            const confirmedPlate = json.data.formatted_plate || json.data.plate_number || plate;
            removePendingCard(json.data.plate_number || plateNumber);
            closeExitConfirmModal();
            loadActiveSessions();

            if (json.data.details_uncollected) {
                showToast(`⚠️ Vehicle ${confirmedPlate} cleared without details. Gate barrier opened.`);
                logActivity(`⚠️ Vehicle ${confirmedPlate} exited without details. Gate cleared.`, 'warn');
            } else {
                showToast(`✓ Barrier Lifted: Vehicle ${confirmedPlate} cleared (Stay: ${json.data.duration_text || 'Completed'})`);
            }

            const candidateArea = document.getElementById('exitCandidateArea');
            if (candidateArea) candidateArea.innerHTML = '';
            const searchResults = document.getElementById('exitSearchResults');
            if (searchResults) searchResults.innerHTML = '';
            const searchInput = document.getElementById('exitSearchInput');
            if (searchInput) searchInput.value = '';
        } else {
            showToast('✕ ' + (json.error || 'Failed to clear exit.'));
        }
    } catch (e) {
        showToast('✕ Network error while clearing vehicle exit.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.classList.remove('btn-loading');
        }
        if (lbl) lbl.textContent = 'Clear Exit & Lift Barrier';
    }
}

async function executeClearExit(sessionId, plateNumber = '') {
    // Replaced harsh browser confirm with soft exit modal
    openExitConfirmModal(sessionId, plateNumber);
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
        showToast('⚠️ Please enter the 4-digit Supervisor PIN.');
        const pinInput = document.getElementById('supOverridePin');
        if (pinInput) {
            pinInput.classList.add('input-shake');
            setTimeout(() => pinInput.classList.remove('input-shake'), 600);
            pinInput.focus();
        }
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
            showToast('✕ ' + (json.error || 'Supervisor authorization failed.'));
        }
    } catch (e) {
        showToast('✕ Failed to contact server for override.');
    }
}

// --------------------------------------------------------------------
// BASEMENT ACTIVE PARKING ROSTER & LIVE DURATION MONITORING
// --------------------------------------------------------------------
STATE.rosterTimerInterval = null;
STATE.activeSessionsList = [];

async function loadActiveSessions(manualTrigger = false) {
    try {
        const btnRefresh = document.getElementById('btnRefreshRoster');
        if (manualTrigger && btnRefresh) {
            btnRefresh.classList.add('spinning');
            setTimeout(() => btnRefresh.classList.remove('spinning'), 600);
        }

        const res = await fetch('../api/gate/active-sessions.php');
        const json = await res.json();
        const tbody = document.getElementById('insideTableBody');
        if (!tbody) return;

        if (!json.ok || !json.data || !json.data.sessions || json.data.sessions.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align:center; padding:60px 20px; color:var(--text-muted);">
                        <div style="font-size:32px; margin-bottom:10px;">🅿️</div>
                        <div style="font-size:16px; font-weight:800; color:var(--text-primary);">No vehicles currently parked in the basement.</div>
                        <div style="font-size:12px; margin-top:4px; color:var(--text-secondary);">When vehicles enter via Gate 1, they will be listed here with real-time stay duration.</div>
                    </td>
                </tr>`;
            updateRosterFilterCounters(0, 0, 0, 0);
            return;
        }

        STATE.activeSessionsList = json.data.sessions;
        renderActiveSessionsTable(STATE.activeSessionsList);

        // Start real-time ticking stay duration timers
        startRosterLiveTimers();

    } catch (e) {
        console.error('Error loading active sessions:', e);
    }
}

function renderActiveSessionsTable(sessions) {
    const tbody = document.getElementById('insideTableBody');
    if (!tbody) return;

    let countSafe = 0, countWarn = 0, countCrit = 0;
    const now = Date.now();

    tbody.innerHTML = '';
    sessions.forEach(sess => {
        const tr = document.createElement('tr');
        tr.className = 'roster-car-row';
        tr.id = `roster-row-${sess.id}`;

        const entryTs = sess.entry_timestamp ? (sess.entry_timestamp * 1000) : new Date(sess.entry_time.replace(/-/g, '/')).getTime();
        const elapsedSecs = Math.max(0, Math.floor((now - entryTs) / 1000));

        // 2-hour policy: 7200 seconds = 120 mins
        // Warning threshold: 5400 seconds = 90 mins
        const isCritical = sess.overstay_level === 'critical' || elapsedSecs >= 7200;
        const isWarning = !isCritical && (sess.overstay_level === 'warning' || elapsedSecs >= 5400);

        if (isCritical) {
            tr.classList.add('overstay-critical');
            countCrit++;
        } else if (isWarning) {
            tr.classList.add('overstay-warning');
            countWarn++;
        } else {
            countSafe++;
        }

        tr.setAttribute('data-session-id', sess.id);
        tr.setAttribute('data-entry-ts', entryTs);
        tr.setAttribute('data-plate', (sess.plate_number || '').toUpperCase());
        tr.setAttribute('data-driver', (sess.driver_name || '').toLowerCase());
        tr.setAttribute('data-dest', (sess.destination || '').toLowerCase());
        tr.setAttribute('data-overstay-level', isCritical ? 'critical' : (isWarning ? 'warning' : 'safe'));

        // Format live duration
        const hrs = Math.floor(elapsedSecs / 3600);
        const mins = Math.floor((elapsedSecs % 3600) / 60);
        const secs = elapsedSecs % 60;
        const pad = (n) => String(n).padStart(2, '0');
        const durationStr = `${pad(hrs)}:${pad(mins)}:${pad(secs)}`;

        // Expected leave time display
        const expectedExitDisplay = sess.expected_exit_display || (new Date(entryTs + (120 * 60 * 1000)).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));

        // Category badge
        let catBadge = '';
        if (sess.category === 'vip') catBadge = '<span class="category-badge-pill vip">⭐ VIP</span>';
        else if (sess.category === 'staff') catBadge = '<span class="category-badge-pill staff">👔 STAFF</span>';

        // Status badge
        let statusBadgeHtml = '';
        if (isCritical) {
            const overdueMins = Math.floor((elapsedSecs - 7200) / 60);
            statusBadgeHtml = `<span class="roster-status-badge critical pulse-badge"><span class="badge-dot red"></span> OVERSTAY (+${overdueMins}m)</span>`;
        } else if (isWarning) {
            const remMins = Math.max(1, Math.ceil((7200 - elapsedSecs) / 60));
            statusBadgeHtml = `<span class="roster-status-badge warning"><span class="badge-dot amber"></span> Approaching (${remMins}m left)</span>`;
        } else {
            const remMins = Math.max(1, Math.ceil((7200 - elapsedSecs) / 60));
            statusBadgeHtml = `<span class="roster-status-badge safe"><span class="badge-dot green"></span> Free (${remMins}m left)</span>`;
        }

        tr.innerHTML = `
            <td>
                <div class="roster-plate-badge" title="${escapeHtml(sess.formatted_plate)}">
                    <span class="roster-plate-flag">KE</span>
                    <span>${escapeHtml(sess.formatted_plate)}</span>
                </div>
            </td>
            <td>
                <div class="roster-driver-meta">
                    <div class="roster-driver-name">
                        <span>${escapeHtml(sess.driver_name)}</span>
                        ${catBadge}
                    </div>
                    ${sess.driver_phone ? `<div class="roster-driver-phone">${escapeHtml(sess.driver_phone)}</div>` : ''}
                </div>
            </td>
            <td>
                <span class="roster-dest-badge" title="${escapeHtml(sess.destination)}">
                    <span>📍</span>
                    <span>${escapeHtml(sess.destination)}</span>
                </span>
            </td>
            <td>
                <span class="roster-time-in">${escapeHtml(sess.entry_time_display || sess.entry_time.split(' ')[1])}</span>
            </td>
            <td>
                <span class="roster-time-expected" title="Expected departure deadline under 2-hour policy">
                    ⏱️ ${escapeHtml(expectedExitDisplay)}
                </span>
            </td>
            <td>
                <div class="roster-live-timer">
                    <span class="timer-pulse-icon"></span>
                    <span class="timer-text">${durationStr}</span>
                </div>
            </td>
            <td>
                ${statusBadgeHtml}
            </td>
            <td>
                <div class="roster-actions-cell">
                    <button class="btn-roster-action reprint" onclick="reprintTicket(${sess.id})" title="Reprint Parking Ticket">
                        🖨️ Reprint
                    </button>
                    <button class="btn-roster-action exit" onclick="executeClearExit(${sess.id})" title="Clear Departure">
                        Exit ➔
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    updateRosterFilterCounters(sessions.length, countSafe, countWarn, countCrit);

    // Apply any active search or filter
    applyRosterFilters();

    // GSAP smooth row stagger entrance
    if (window.gsap) {
        gsap.from('#insideTableBody tr', {
            opacity: 0,
            y: 10,
            duration: 0.25,
            stagger: 0.03,
            ease: 'power1.out'
        });
    }
}

function startRosterLiveTimers() {
    if (STATE.rosterTimerInterval) clearInterval(STATE.rosterTimerInterval);
    STATE.rosterTimerInterval = setInterval(() => {
        const rows = document.querySelectorAll('.roster-car-row');
        if (rows.length === 0) return;
        const now = Date.now();
        const pad = (n) => String(n).padStart(2, '0');

        rows.forEach(row => {
            const entryTs = parseInt(row.getAttribute('data-entry-ts'), 10);
            if (!entryTs) return;

            const elapsedSecs = Math.max(0, Math.floor((now - entryTs) / 1000));
            const hrs = Math.floor(elapsedSecs / 3600);
            const mins = Math.floor((elapsedSecs % 3600) / 60);
            const secs = elapsedSecs % 60;
            const durationStr = `${pad(hrs)}:${pad(mins)}:${pad(secs)}`;

            const timerText = row.querySelector('.timer-text');
            if (timerText) timerText.textContent = durationStr;

            const isCritical = elapsedSecs >= 7200; // 2 hours
            const isWarning = !isCritical && elapsedSecs >= 5400; // 1.5 hours
            const prevLevel = row.getAttribute('data-overstay-level');
            const newLevel = isCritical ? 'critical' : (isWarning ? 'warning' : 'safe');

            if (prevLevel !== newLevel) {
                row.setAttribute('data-overstay-level', newLevel);
                row.classList.toggle('overstay-critical', isCritical);
                row.classList.toggle('overstay-warning', isWarning);

                const statusCell = row.children[6];
                if (statusCell) {
                    if (isCritical) {
                        const overdueMins = Math.floor((elapsedSecs - 7200) / 60);
                        statusCell.innerHTML = `<span class="roster-status-badge critical pulse-badge"><span class="badge-dot red"></span> OVERSTAY (+${overdueMins}m)</span>`;
                        if (window.gsap) gsap.from(statusCell.firstElementChild, { scale: 1.15, duration: 0.3 });
                    } else if (isWarning) {
                        const remMins = Math.max(1, Math.ceil((7200 - elapsedSecs) / 60));
                        statusCell.innerHTML = `<span class="roster-status-badge warning"><span class="badge-dot amber"></span> Approaching (${remMins}m left)</span>`;
                    } else {
                        const remMins = Math.max(1, Math.ceil((7200 - elapsedSecs) / 60));
                        statusCell.innerHTML = `<span class="roster-status-badge safe"><span class="badge-dot green"></span> Free (${remMins}m left)</span>`;
                    }
                }
            } else if (isCritical) {
                // Update overdue minutes count dynamically
                const overdueMins = Math.floor((elapsedSecs - 7200) / 60);
                const badge = row.querySelector('.roster-status-badge.critical');
                if (badge) badge.innerHTML = `<span class="badge-dot red"></span> OVERSTAY (+${overdueMins}m)`;
            }
        });
    }, 1000);
}

function updateRosterFilterCounters(total, safe, warn, crit) {
    const elAll = document.getElementById('countPillAll');
    const elSafe = document.getElementById('countPillSafe');
    const elWarn = document.getElementById('countPillWarn');
    const elCrit = document.getElementById('countPillCrit');
    const elBadge = document.getElementById('rosterCountBadge');

    if (elAll) elAll.textContent = String(total);
    if (elSafe) elSafe.textContent = String(safe);
    if (elWarn) elWarn.textContent = String(warn);
    if (elCrit) elCrit.textContent = String(crit);
    if (elBadge) elBadge.textContent = String(total);

    const statOccupied = document.getElementById('statOccupied');
    if (statOccupied && total > 0 && statOccupied.textContent === '--') {
        statOccupied.textContent = String(total);
    }
}

function applyRosterFilters(shouldAnimate = false) {
    const query = (document.getElementById('rosterSearchInput')?.value || '').trim().toLowerCase();
    const activePill = document.querySelector('.roster-pill.active');
    const filterType = activePill ? activePill.getAttribute('data-filter') : 'all';

    const btnClear = document.getElementById('btnClearRosterSearch');
    if (btnClear) btnClear.style.display = query.length > 0 ? 'block' : 'none';

    const rows = document.querySelectorAll('.roster-car-row');
    const visibleRows = [];
    rows.forEach(row => {
        const plate = row.getAttribute('data-plate') || '';
        const driver = row.getAttribute('data-driver') || '';
        const dest = row.getAttribute('data-dest') || '';
        const level = row.getAttribute('data-overstay-level') || 'safe';

        const matchesQuery = !query || plate.includes(query) || driver.includes(query) || dest.includes(query);
        const matchesFilter = (filterType === 'all') || (filterType === level);

        if (matchesQuery && matchesFilter) {
            row.style.display = '';
            visibleRows.push(row);
        } else {
            row.style.display = 'none';
        }
    });

    if (shouldAnimate && window.gsap && visibleRows.length > 0 && visibleRows.length <= 40) {
        gsap.fromTo(visibleRows, { opacity: 0.35, y: 3 }, { opacity: 1, y: 0, duration: 0.18, stagger: 0.02, ease: 'power1.out' });
    }
}

function clearRosterSearch() {
    const input = document.getElementById('rosterSearchInput');
    if (input) input.value = '';
    applyRosterFilters();
    input?.focus();
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
            const box = modal.querySelector('.modal-box');
            if (box && window.gsap) {
                gsap.fromTo(box, { scale: 0.94, opacity: 0, y: 12 }, { scale: 1, opacity: 1, y: 0, duration: 0.22, ease: 'power2.out' });
            }
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
    if (!modal) return;
    const box = modal.querySelector('.modal-box');
    if (box && window.gsap) {
        gsap.to(box, {
            scale: 0.94,
            opacity: 0,
            y: 10,
            duration: 0.16,
            ease: 'power2.in',
            onComplete: () => modal.classList.remove('open')
        });
    } else {
        modal.classList.remove('open');
    }
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
            if (normalizeFloorLevel(d.floor_level) !== normalizeFloorLevel(guardActiveFloor)) {
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
        showToast('⚠️ Please enter a valid vehicle number plate.');
        const plateInput = document.getElementById('wizPlateInput');
        if (plateInput) {
            plateInput.classList.add('input-shake');
            setTimeout(() => plateInput.classList.remove('input-shake'), 600);
            plateInput.focus();
        }
        return;
    }
    if (!dest) {
        showToast('⚠️ Please select a destination store or office.');
        return;
    }

    const btn = document.querySelector('.btn-submit-checkin');
    if (btn) {
        btn.disabled = true;
        btn.classList.add('btn-loading');
        btn.textContent = 'Registering…';
    }

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
            loadActiveSessions();
            showReceiptModal(json.data);
        } else {
            showToast('✕ ' + (json.error || 'Failed to register vehicle.'));
        }
    } catch (e) {
        showToast('✕ Network error registering vehicle.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.classList.remove('btn-loading');
            btn.textContent = 'Register & Print Ticket';
        }
    }
}

async function loadDestinations() {
    try {
        const res  = await fetch('../api/driver/destinations.php');
        const json = await res.json();
        if (json.ok && json.data) {
            STATE.destinations = json.data;
            renderGuardDestGrid();
            renderIntakeShops();
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
    if (window.gsap) {
        gsap.fromTo(toast, { y: 25, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, ease: 'back.out(1.4)' });
    }
    setTimeout(() => {
        if (window.gsap) {
            gsap.to(toast, {
                y: 20,
                opacity: 0,
                duration: 0.25,
                ease: 'power2.in',
                onComplete: () => toast.classList.remove('show')
            });
        } else {
            toast.classList.remove('show');
        }
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
            if (json.session) {
                showReceiptModal(json.session);
            }
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

// --------------------------------------------------------------------
// Soft Logout Confirmation Modal Handlers
// --------------------------------------------------------------------
function openLogoutConfirmModal() {
    const modal = document.getElementById('logoutConfirmModal');
    if (!modal) return;
    modal.classList.add('open');
    if (window.gsap) {
        gsap.fromTo('.logout-modal-box', 
            { scale: 0.94, opacity: 0, y: 15 }, 
            { scale: 1, opacity: 1, y: 0, duration: 0.25, ease: 'back.out(1.4)' }
        );
    }
}

function closeLogoutConfirmModal() {
    const modal = document.getElementById('logoutConfirmModal');
    if (!modal) return;
    if (window.gsap) {
        gsap.to('.logout-modal-box', {
            scale: 0.96, opacity: 0, y: 10, duration: 0.18, ease: 'power2.in',
            onComplete: () => {
                modal.classList.remove('open');
            }
        });
    } else {
        modal.classList.remove('open');
    }
}

function executeLogout() {
    const btn = document.getElementById('btnConfirmLogout');
    const lbl = document.getElementById('logoutBtnLabel');
    if (btn) {
        btn.disabled = true;
        btn.classList.add('btn-loading');
    }
    if (lbl) lbl.textContent = 'Signing Out…';
    showToast('👋 Ending shift. Signing out…');
    setTimeout(() => {
        window.location.href = '../api/auth/logout.php';
    }, 450);
}

// --------------------------------------------------------------------
// Global Keyboard Shortcuts (R: Fast Sync, Escape: Close Modals)
// --------------------------------------------------------------------
document.addEventListener('keydown', (e) => {
    // 1. Escape key: closes topmost modal cleanly
    if (e.key === 'Escape') {
        if (document.getElementById('logoutConfirmModal')?.classList.contains('open')) {
            closeLogoutConfirmModal();
            return;
        }
        if (document.getElementById('exitConfirmModal')?.classList.contains('open')) {
            closeExitConfirmModal();
            return;
        }
        if (document.getElementById('batchApproveModal')?.classList.contains('open')) {
            closeBatchApproveModal();
            return;
        }
        if (document.getElementById('vehicleIntakeModal')?.classList.contains('open')) {
            closeVehicleIntakeModal();
            return;
        }
        if (document.getElementById('helpModal')?.classList.contains('open')) {
            closeHelpModal();
            return;
        }
        if (document.getElementById('supervisorOverrideModal')?.classList.contains('open')) {
            closeSupervisorOverrideModal();
            return;
        }
        if (document.getElementById('manualWizardModal')?.classList.contains('open')) {
            closeWizard();
            return;
        }
        if (document.getElementById('rejectModal')?.classList.contains('open')) {
            closeRejectDialog();
            return;
        }
        if (document.getElementById('receiptPreviewModal')?.classList.contains('open')) {
            closeReceiptModal();
            return;
        }
    }

    // 2. 'R' key: Fast Refresh Queue (when not inside form inputs)
    if (e.key === 'r' || e.key === 'R') {
        const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
        if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable) {
            return;
        }
        if (document.querySelector('.modal-overlay.open')) {
            return;
        }
        e.preventDefault();
        fastRefreshEntryQueue(true);
    }
});
