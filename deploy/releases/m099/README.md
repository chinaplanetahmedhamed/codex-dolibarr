# M099: operation cards and canonical shipment documents

Local acceptance uses the isolated M097 QA runtime. Synthetic local data is never packaged.
The runtime allowlist contains 20 custom files. Core files, configuration, documents and other modules are excluded.

Build only committed source:

```
python3 deploy/releases/m099/build.py <source-commit> <verified-baseline-commit> <private-artifact-directory>
```

The builder emits the payload, manifest, SHA-256 evidence and checksum-pinned staging-only helper. Existing dependencies and previous bytes must match the baseline. A drift failure requires review and a new manifest; never disable comparison or overwrite drift.

After exact staging root authorization, copy the reviewed helper to a root-owned fixed path, verify its hash and run:

```
python3 -I /root/m099-deploy.py staging preflight
python3 -I /root/m099-deploy.py staging apply
python3 -I /root/m099-deploy.py staging verify
```

The helper validates the canonical staging container/project/volume/port, PHP syntax and baseline hashes, backs up code and the staging database privately, creates additive InnoDB tables, registers existing files as metadata and enables the two flags. Registration rejects ambiguous folders and never moves, deletes or deduplicates bytes. Native business records and rights must retain their digests. Functional browser and authenticated permission acceptance remain mandatory after helper success. No production target exists.

## Compatible rollback

```
python3 -I /root/m099-deploy.py staging rollback
```

`CPO_SHIPMENT_CARDS_ENABLED` controls the new card and My Work experience. `CPL_SHARED_DOCUMENTS_ENABLED` independently keeps canonical native storage adapters active.

Once canonical writes are possible, rollback disables the UI and restores previous operations UI files while retaining canonical document services and native shipment/Customer Center adapters, registry tables and all document writes. It returns `UI_ROLLED_BACK_CANONICAL_STORAGE_RETAINED`. It never restores the database dump or removes documents. Reverting all native adapters to pre-M099 raw directory scanning would expose archived/history files and is unsafe. The code rollback therefore intentionally preserves this compatibility layer; a future complete rollback must include an equally compatible document adapter.

Before canonical activation, automatic failure recovery may restore all previous code/configuration. Root state and backup checksums are preserved. Never use local-only tests against staging by removing their guards.
