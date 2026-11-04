# M089 staging compatibility overlay

Production runtime files under `custom/` preserve the exact inspected live workspace and existing translations. Staging has the separately deployed M084 interface, including UI helper calls and translations absent on production. These six reviewed overlay files preserve those existing staging differences while adding the same M089 own-task policy and personal workspace.

Use the frozen M089 release builder only. It pins both inspected baselines and both resulting file sets. Shared authorization/service/JavaScript and board behavior have identical bytes in both environments. Neither variant promotes unrelated M084 files or replaces an entire module. Production promotion requires the recorded matching staging variant and authenticated acceptance; it does not claim all ten file hashes are identical across environments.

Before-state evidence lives privately in management `local-runtime/m089-qa/release/preflight/target-inspection.json`; do not commit server reports or credentials. Release helpers must reject any drift and preserve each target's own backup for rollback.
