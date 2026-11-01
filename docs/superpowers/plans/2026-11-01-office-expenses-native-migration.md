# Native operational storage and migration adapter plan

Goal: implement the reviewed native draft-schema migration and a shared native connection guard, with owned fresh synthetic databases only. No existing database, service, browser workaround, accounting writer, runtime factory, commit/push or deployment is authorized.

Design: retain immutable draft/audit tables and add an inactive schema-version registry. Pin reviewed SQL bytes in a manifest. A trusted migration port inspects full owned-table descriptors, holds an exclusive migration lock, creates only absent owned tables/indexes and records installing/ready states with confirmed native transactions. Exact installed reruns are read-only; partial, unmarked or drifted states stop for reconciliation. No destructive down migration or automatic recovery. MySQL/MariaDB DDL is not treated as transactional. Runtime installation remains unwired.

- [x] TDD shared native connection guard, including quarantine across draft and migration adapter instances; storage22/0 preserves all previous21 cases.
- [x] TDD migration artifact validation, fresh install, exact rerun, stale hash, unmarked/partial/drifted schema refusal, lock contention, DDL failure, uncertain marker commit and data-preserving reversal plan. Migration17/0 after lock/context RED→GREEN corrections.
- [x] Implement native mysqli metadata/locking/DDL/version-marker backend with no connection creation. Synthetic fixture uses only newly created owned SQLite and explicitly documents collation/engine/advisory-lock limitations. Independent review found no remaining material issue within this tested contract.
- [x] Prove an own native User→migrated native source→receipt-service flow remains scoped, using existing synthetic receipt adapter only. No production receipt adapter/ECM mapping is implied.
- [x] Run bounded credential-free regression143/0 and six affected PHP syntax checks. Final whitespace/cleanup evidence is recorded outside source. Matching-engine and remaining receipt-adapter gates remain explicit; prior source/index work is preserved.
