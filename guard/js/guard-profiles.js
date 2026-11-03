/* Returning visitor suggestions require a guard decision; a plate is not proof of driver identity. */
const GuardProfiles = (() => {
    const states = { intake: {}, wiz: {} };
    const fields = scope => scope === 'intake' ? ['intakeDriverName','intakeDriverPhone'] : ['wizNameInput','wizPhoneInput'];
    const plate = value => String(value || '').replace(/[^a-z0-9]/gi, '').toUpperCase();
    function reset(scope) {
        states[scope] = { token: (states[scope].token || 0) + 1, mode: 'manual' };
        const box = document.getElementById(`${scope}ReturningProfile`); if (box) box.hidden = true;
        const remember = document.getElementById(`${scope}RememberDetails`); if (remember) remember.checked = true;
        for (const id of fields(scope)) { const el = document.getElementById(id); if (el) { el.readOnly = false; el.style.backgroundColor = ''; el.style.cursor = ''; el.title = ''; } }
    }
    async function lookup(scope, rawPlate) {
        const key = plate(rawPlate);
        if (states[scope].plate === key && (states[scope].loading || states[scope].loaded)) return;
        reset(scope);
        const token = states[scope].token;
        states[scope].loading = true;
        states[scope].plate = key;
        if (key.length < 5) return;
        try {
            const response = await fetch('../api/gate/vehicle-lookup.php?plate=' + encodeURIComponent(key));
            const result = await response.json();
            const currentPlate = scope === 'wiz' ? plate(document.getElementById('wizPlateInput').value) : plate(document.getElementById('intakePlateDisplay').textContent);
            if (token !== states[scope].token || currentPlate !== key || !STATE.user) return;
            if (!result.ok) throw new Error(result.error || 'Previous details unavailable');
            states[scope].loading = false; states[scope].loaded = true;
            const profile = result.data;
            states[scope].profile = profile;
            if (!profile.has_bank_profile || profile.profile_archived) return;
            const box = document.getElementById(`${scope}ReturningProfile`); if (!box) return;
            box.hidden = false;
            box.querySelector('.returning-summary').textContent = `Returning vehicle · ${profile.visit_count} visits${profile.is_frequent ? ' · Frequent visitor' : ''}`;
            box.querySelector('.returning-details').textContent = `Saved driver: ${profile.driver_name || 'Not recorded'} · ${profile.masked_phone || 'No saved phone'} · Vehicle last seen: ${profile.last_seen || 'Not recorded'}`;
            box.querySelector('.returning-destination').textContent = `Suggested destination: ${profile.default_destination || 'Choose a destination'}`;
        } catch (error) {
            if (token === states[scope].token) { states[scope].loading = false; }
            if (token === states[scope].token) showToast('Previous details unavailable. Enter details for this visit.');
        }
    }
    function choose(scope, mode) {
        const profile = states[scope].profile;
        if (!profile) return;
        const [nameId, phoneId] = fields(scope);
        document.getElementById(nameId).value = mode === 'different' ? '' : (profile.driver_name || '');
        document.getElementById(phoneId).value = mode === 'different' ? '' : (profile.driver_phone || '');
        const dest = mode === 'different' ? '' : (profile.default_destination || '');
        if (scope === 'wiz') { STATE.wizard.destination = dest; renderGuardDestGrid(); const label = document.getElementById('wizSelectedDestLabel'); if (label) label.textContent = dest || 'None'; }
        else {
            STATE.intakeSelectedDest = dest;
            document.getElementById('intakeDestinationValue').value = dest;
            document.getElementById('intakeSelectedDestLabel').textContent = dest || 'Choose destination';
            renderIntakeShops();
        }
        states[scope].mode = mode;
        document.getElementById(`${scope}RememberDetails`).checked = mode !== 'different';
        const notice = document.querySelector(`#${scope}ReturningProfile .returning-choice`);
        notice.textContent = mode === 'different' ? 'Enter today’s driver. The saved profile stays unchanged unless you choose to remember the new details.' : 'Check the driver details and destination before approving this visit.';
        document.getElementById(nameId).focus();
    }
    function plateChanged(scope, raw) {
        if (states[scope].plate && states[scope].plate !== plate(raw)) {
            if (['use','edit'].includes(states[scope].mode)) {
                for (const id of fields(scope)) document.getElementById(id).value = '';
                if (scope === 'wiz') { STATE.wizard.destination = ''; renderGuardDestGrid(); const label = document.getElementById('wizSelectedDestLabel'); if (label) label.textContent = 'None'; }
            }
            reset(scope);
        }
    }
    function payload(scope) { return { remember_details: !!document.getElementById(`${scope}RememberDetails`)?.checked, profile_mode: states[scope].mode || 'manual' }; }
    return { reset, lookup, choose, payload, plateChanged };
})();
