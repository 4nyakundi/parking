/* One request at a time. Superseded reads and duplicate pending changes are coalesced. */
const AdminTransport = (() => {
    let queue = Promise.resolve(), nextId = 0;
    let sessionExpired = false;
    const latest = new Map(), changes = new Map();
    function notice(message, state = '') {
        const el = document.getElementById('adminConnectionNotice');
        if (el) { el.textContent = message; el.dataset.state = state; }
    }
    function request(url, options = {}) {
        const method = (options.method || 'GET').toUpperCase();
        const read = method === 'GET';
        const parsed = new URL(url, window.location.href);
        const scope = parsed.pathname + ':' + (parsed.searchParams.get('action') || parsed.searchParams.get('type') || '');
        const id = ++nextId;
        if (read) latest.set(scope, id);
        const changeKey = read ? null : method + ':' + url + ':' + (options.body || '');
        if (changeKey && changes.has(changeKey)) return changes.get(changeKey);
        const run = async () => {
            if (sessionExpired) return {ok:false,stale:true};
            if (read && latest.get(scope) !== id) return { ok:false, stale:true };
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), read ? 12000 : 30000);
            try {
                const headers = new Headers(options.headers || {});
                if (!read) headers.set('X-CSRF-Token', document.querySelector('meta[name="csrf-token"]')?.content || '');
                const response = await fetch(url, { ...options, method, headers, cache:'no-store', signal:controller.signal });
                const json = await response.json();
                if (read && latest.get(scope) !== id) return { ok:false, stale:true };
                if (response.status === 401 || json.need_login) {
                    sessionExpired = true;
                    notice('Session ended. Please sign in.', 'expired');
                    window.location.href = 'login.php?notice=session_expired';
                    return {ok:false,error:'Session expired'};
                }
                if (!response.ok || !json.ok) {
                    notice(json.error || 'Request failed. Please retry.', 'error');
                    return {...json, ok:false, error:json.error || 'Request failed.'};
                }
                notice('Connected · Updated ' + new Intl.DateTimeFormat('en-GB', {timeZone:'Africa/Nairobi',hour:'2-digit',minute:'2-digit',second:'2-digit'}).format(new Date()) + ' Nairobi', 'connected');
                return json;
            } catch (error) {
                if (read && latest.get(scope) !== id) return {ok:false,stale:true};
                const message = read ? 'Could not refresh. Previous totals may be out of date.' : 'Could not confirm this change. Check the record before trying again.';
                notice(message, 'error'); return {ok:false,error:message};
            } finally { clearTimeout(timeout); }
        };
        const result = queue.then(run,run);
        queue = result.catch(() => {});
        if (changeKey) {
            changes.set(changeKey,result);
            result.finally(() => { if (changes.get(changeKey) === result) changes.delete(changeKey); });
        }
        return result;
    }
    function schedule(callback) {
        let busy = false, failures = 0;
        const tick = async () => {
            if (!busy && !document.hidden) {
                busy = true;
                try { const ok = await callback(); failures = ok === false ? Math.min(4,failures+1) : 0; }
                finally { busy = false; }
            }
            setTimeout(tick, Math.min(30000,5000 * 2 ** failures));
        };
        setTimeout(tick,5000);
    }
    return {request,schedule,notice};
})();
