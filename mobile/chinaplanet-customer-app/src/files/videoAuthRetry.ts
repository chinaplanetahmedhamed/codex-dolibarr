export function createVideoAuthRetryGuard(maxFailures = 3) {
  let failures = 0;
  return {
    reset() { failures = 0; },
    nextDelay() { if (failures >= maxFailures) return null; const delay = Math.min(4000, 500 * (2 ** failures)); failures += 1; return delay; },
  };
}
