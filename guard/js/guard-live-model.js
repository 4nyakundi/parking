/* Pure dashboard reconciliation helpers, shared by the UI and regression checks. */
const GuardLiveModel = {
    plate(value) { return String(value || '').replace(/[^A-Z0-9]/gi, '').toUpperCase(); },
    merge(local, cloud, active = []) {
        const activePlates = new Set(active.map(item => this.plate(item.plate_number)));
        const merged = new Map();
        for (const item of local) {
            const plate = this.plate(item.plate_number);
            if (plate && !activePlates.has(plate)) merged.set(plate, { ...item });
        }
        for (const item of cloud) {
            const plate = this.plate(item.plate_number);
            if (!plate || activePlates.has(plate)) continue;
            const existing = merged.get(plate);
            if (existing) {
                existing.firestore_ids = [...(existing.firestore_ids || []), item.firestore_id];
                existing.firestore_id = existing.firestore_id || item.firestore_id;
            } else {
                merged.set(plate, { ...item, id: `fb_${item.firestore_id}`, source: 'cloud_mobile',
                    firestore_ids: [item.firestore_id], alpr_verified: false, wait_text: 'Mobile check-in' });
            }
        }
        return [...merged.values()];
    },
    cardSignature(item) {
        const { wait_text, wait_seconds, ...content } = item;
        return JSON.stringify(content);
    },
    level(entryMs, nowMs) {
        const seconds = Math.max(0, (nowMs - entryMs) / 1000);
        return seconds >= 7200 ? 'critical' : seconds >= 5400 ? 'warning' : 'safe';
    },
    retryDelay(failures) { return Math.min(30000, 2000 * 2 ** Math.min(failures, 4)); },
    device(info, disconnected = false) {
        if (disconnected || !info) return 'Unknown';
        if (['entrance_cam', 'exit_cam', 'alpr_worker'].includes(info.device) && Number(info.age_seconds) > 120) return 'Stale';
        return ({ OK: 'Online', WARNING: 'Check', ERROR: 'Failed', OFFLINE: 'Offline' })[info.status] || 'Unknown';
    }
};
if (typeof module !== 'undefined') module.exports = GuardLiveModel;
