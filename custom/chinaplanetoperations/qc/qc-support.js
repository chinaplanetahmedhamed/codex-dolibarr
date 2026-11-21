(function (root, factory) {
    const support = factory();
    if (typeof module === 'object' && module.exports) module.exports = support;
    else root.CP_QC_SUPPORT = support;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    function classifySessionResponse(status, redirected, isJson, errorCode) {
        const definitive = status === 401 || (status === 403 && [
            'session_expired',
            'access_denied',
            'authentication_required',
            'not_authenticated',
        ].includes(String(errorCode || '')));
        if (definitive) return 'definitive';
        if (redirected || (status === 403 && !isJson) || (status >= 200 && status < 300 && !isJson)) return 'ambiguous';
        return 'normal';
    }

    function mergeQueuedPackage(serverPackage, mutations, findings) {
        const merged = Object.assign({}, serverPackage, {checkpoints: Array.isArray(serverPackage.checkpoints) ? serverPackage.checkpoints.slice() : []});
        (mutations || []).forEach(mutation => {
            const at = merged.checkpoints.findIndex(checkpoint => Number(checkpoint.rowid) === Number(mutation.checkpoint_id));
            if (at >= 0) merged.checkpoints[at] = Object.assign({}, merged.checkpoints[at], mutation);
        });
        (findings || []).forEach(finding => {
            const at = merged.checkpoints.findIndex(checkpoint => checkpoint.client_uuid === finding.client_uuid);
            const local = Object.assign({}, at >= 0 ? merged.checkpoints[at] : {}, finding, {rowid: 'local:' + finding.client_uuid});
            if (at >= 0) merged.checkpoints[at] = local;
            else merged.checkpoints.push(local);
        });
        return merged;
    }

    function evidenceBelongsToCheckpoint(metadata, checkpoint) {
        if (String(checkpoint.rowid).startsWith('local:')) return metadata.finding_uuid === checkpoint.client_uuid;
        return Number(metadata.checkpoint_id) === Number(checkpoint.rowid);
    }

    function evidenceSyncOutcome(payload) {
        const evidence = payload && payload.evidence ? payload.evidence : (payload || {});
        if (evidence.upload_status === 'internal_only') {
            return {state: 'internal_only', evidenceId: Number(evidence.evidence_id || 0), reason: String(evidence.customer_safe_reason || 'media_tools_unavailable')};
        }
        if (evidence.upload_status === 'finalized' && evidence.customer_safe_available === true) {
            return {state: 'ready', evidenceId: Number(evidence.evidence_id || 0), reason: ''};
        }
        return {state: 'pending', evidenceId: Number(evidence.evidence_id || 0), reason: ''};
    }

    return Object.freeze({classifySessionResponse, mergeQueuedPackage, evidenceBelongsToCheckpoint, evidenceSyncOutcome});
}));
