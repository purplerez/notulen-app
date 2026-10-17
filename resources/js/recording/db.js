/**
 * IndexedDB helper for local audio segment storage & upload queue.
 * Guarantees recording persistence against network dropouts, page reloads, and mobile backgrounding.
 */

const DB_NAME = 'notulen_recorder_db';
const DB_VERSION = 1;
const STORE_NAME = 'segments';

export function openDatabase() {
    return new Promise((resolve, reject) => {
        if (!('indexedDB' in window)) {
            return reject(new Error('IndexedDB is not supported on this browser.'));
        }

        const request = window.indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                const store = db.createObjectStore(STORE_NAME, { keyPath: 'id' });
                store.createIndex('meetingId', 'meetingId', { unique: false });
                store.createIndex('status', 'status', { unique: false });
                store.createIndex('meetingId_seq', ['meetingId', 'seq'], { unique: true });
            }
        };

        request.onsuccess = (event) => resolve(event.target.result);
        request.onerror = (event) => reject(event.target.error);
    });
}

/**
 * Enqueue a new recorded audio segment into IndexedDB.
 */
export async function enqueueSegment({ meetingId, seq, blob, checksum, durationSec, mimeType }) {
    const db = await openDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction([STORE_NAME], 'readwrite');
        const store = tx.objectStore(STORE_NAME);

        const record = {
            id: `${meetingId}_${seq}`,
            meetingId: Number(meetingId),
            seq: Number(seq),
            blob,
            checksum,
            durationSec: Number(durationSec || 0),
            mimeType,
            status: 'pending',
            retryCount: 0,
            lastAttemptAt: null,
            createdAt: Date.now(),
        };

        const req = store.put(record);
        req.onsuccess = () => resolve(record);
        req.onerror = (e) => reject(e.target.error);
    });
}

/**
 * Get all segments for a meeting currently pending upload or failed.
 */
export async function getPendingSegments(meetingId) {
    const db = await openDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction([STORE_NAME], 'readonly');
        const store = tx.objectStore(STORE_NAME);
        const index = store.index('meetingId');
        const req = index.getAll(Number(meetingId));

        req.onsuccess = () => {
            const list = req.result || [];
            // Sort by sequence number ascending
            list.sort((a, b) => a.seq - b.seq);
            resolve(list);
        };
        req.onerror = (e) => reject(e.target.error);
    });
}

/**
 * Remove confirmed segment from IndexedDB once server confirms receipt.
 */
export async function removeSegment(meetingId, seq) {
    const db = await openDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction([STORE_NAME], 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        const id = `${meetingId}_${seq}`;
        const req = store.delete(id);

        req.onsuccess = () => resolve(true);
        req.onerror = (e) => reject(e.target.error);
    });
}

/**
 * Update retry metadata when upload fails.
 */
export async function updateSegmentRetry(meetingId, seq, errorMsg) {
    const db = await openDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction([STORE_NAME], 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        const id = `${meetingId}_${seq}`;
        const getReq = store.get(id);

        getReq.onsuccess = () => {
            const record = getReq.result;
            if (record) {
                record.status = 'failed';
                record.retryCount = (record.retryCount || 0) + 1;
                record.lastAttemptAt = Date.now();
                record.lastError = errorMsg || 'Network error';
                store.put(record);
            }
            resolve();
        };
        getReq.onerror = (e) => reject(e.target.error);
    });
}

/**
 * Count segments in queue for a meeting.
 */
export async function countPendingSegments(meetingId) {
    const items = await getPendingSegments(meetingId);
    return items.length;
}
