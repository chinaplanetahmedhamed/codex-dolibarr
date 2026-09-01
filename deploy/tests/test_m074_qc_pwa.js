'use strict';

const assert = require('node:assert/strict');
const support = require('../../custom/chinaplanetoperations/qc/qc-support.js');

assert.equal(support.classifySessionResponse(401, false, false, ''), 'definitive');
assert.equal(support.classifySessionResponse(403, false, true, 'session_expired'), 'definitive');
assert.equal(support.classifySessionResponse(403, false, false, ''), 'ambiguous');
assert.equal(support.classifySessionResponse(200, true, false, ''), 'ambiguous');
assert.equal(support.classifySessionResponse(200, false, false, ''), 'ambiguous');
assert.equal(support.classifySessionResponse(409, false, true, 'conflict'), 'normal');

const server = {operation: {ref: 'QC-1'}, checkpoints: [{rowid: 7, client_uuid: 'server-7', observation: 'server'}]};
const mutation = {checkpoint_id: 7, revision: 1, mutation_id: 'queued-mutation', observation: 'offline result'};
const finding = {client_uuid: 'finding-1', revision: 1, observation: 'offline finding'};
const merged = support.mergeQueuedPackage(server, [mutation], [finding]);
assert.equal(merged.checkpoints[0].observation, 'offline result', 'a successful package GET must not hide a queued result when later writes fail');
assert.equal(merged.checkpoints[1].rowid, 'local:finding-1', 'a queued finding must remain navigable after reopen/reconnect');
assert.equal(support.evidenceBelongsToCheckpoint({finding_uuid: 'finding-1'}, merged.checkpoints[1]), true, 'queued finding evidence must retain its association');

const partialServer = {checkpoints: [{rowid: 99, client_uuid: 'finding-1', observation: 'created before write failure'}]};
const partialMerged = support.mergeQueuedPackage(partialServer, [], [finding]);
assert.equal(partialMerged.checkpoints.length, 1, 'an idempotently created finding must not be duplicated after reconnect');
assert.equal(partialMerged.checkpoints[0].rowid, 'local:finding-1');
assert.equal(partialMerged.checkpoints[0].observation, 'offline finding');

console.log('M074_QC_PWA=PASS');
