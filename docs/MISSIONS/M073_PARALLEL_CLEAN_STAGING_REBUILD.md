# M073 — Parallel clean Staging rebuild

## Control

- **Approval:** Ahmed Hamed, 2026-09-02 — local preparation and isolated `backup-erp-rebuild` stack creation only.
- **Code-writing owner:** Agent 007 Central only.
- **Independent reviewers:** Security and QA, read-only against one frozen checksum set before any server mutation.
- **Accepted component release:** `M072-R01` from Dolibarr package commit `084ec05beaed8c85ec907b465eebcb862b0c7603`, React commit `01c9d872ccdf5664cf470019194156b5503dc74b`, WordPress commit `ae273e2d15fa89111336ef74f746a86cc9ac57bd`, and composite Dolibarr/mobile source commit `a6f6f88e2702686c6cd2e99544074804f644a837`.
- **Environment release:** `M073-R01`.

## Business outcome

Create a parallel, private ChinaPlanetOS rebuild environment without changing canonical Staging or Production. The candidate must start from the exact M072 source artifacts, use an empty technical data state, and provide an auditable base for later sanitized-data import and full acceptance.

## In scope

- One Docker Compose project named `backup-erp-rebuild` with six uniquely named containers.
- Separate Dolibarr and WordPress MariaDB databases and volumes, separate Dolibarr documents, WordPress content, React data, and Mailpit data volumes.
- One Docker network named `backup-erp-rebuild_internal`, marked `internal: true`; every candidate container attaches only to it.
- Four localhost-only ports: Mailpit UI `28025`, Dolibarr `28088`, WordPress `28089`, and React `28180`.
- Server-generated root-only credentials. Values are never printed, committed, uploaded, or recorded in the ledger. Verification reports only fixed expected lengths.
- Deterministic metadata-free bundle carrying the complete M072 artifacts plus the M073 target definition and immutable image digests.
- A restricted helper exposing only `preflight`, `create`, `verify`, `status`, and non-data-destructive `rollback`.
- A root-owned ledger recording code/environment state separately from data presence and retained volumes.
- Dolibarr custom runtime plus the two reviewed Customer Center compatibility symlinks. Migration and test support files remain available inside the image but are not silently executed by the image build.
- Canonical WordPress planner source copied into the isolated WordPress image. It remains inactive until its checksum-pinned WooCommerce/B2BKing dependencies and controlled fixtures are separately available.
- React/Node runs the accepted M072 code release with indexing disabled. WordPress and Dolibarr API credentials/endpoints remain unconfigured at this gate.
- Mobile remains a checksum-pinned source artifact only; no mobile runtime container is created.

## Explicitly out of scope

- Production access, export, database copy, documents, uploads, credentials, secrets, or business data.
- Any change to `erp-staging`, current `backup-erp.visiontrading.biz`, old `codex_staging_*` resources, Production, DNS, proxy, or public hostname routing.
- Enabling outbound email, webhooks, scheduled business actions, external writes, or search indexing.
- Sending email, creating a lead, quotation, customer, order, shipment, or any other real business record.
- Activating WordPress dependencies from an unpinned network download.
- Applying the M071 business acceptance fixture or importing a Production snapshot.
- Deleting images, volumes, historical helpers, worktrees, artifacts, or duplicated plugin folders.
- Canonical Staging cutover or Production deployment.

## Ownership and security boundaries

- WordPress/WooCommerce remains the catalog, packing, and B2BKing price-policy authority.
- React owns the public UI and secure Node gateway. The browser never calls Dolibarr directly.
- Dolibarr owns operational leads, quotations, customers, files, shipments, and authenticated Customer Center activity.
- The initial M073 runtime deliberately has no React-to-WordPress or React-to-Dolibarr credential binding. That lifecycle is enabled only after isolated dependencies, shared secrets, and acceptance fixtures are separately approved.
- Runtime containers have no external Docker network. No candidate port binds beyond `127.0.0.1`, and no DNS or reverse-proxy rule points to the candidate.
- Docker base images are pinned by the reviewed linux/amd64 content digest.
- The helper is root-owned, checksum-pinned, no-shell, globally locked, exact-target only, and fails closed on path, owner, mode, checksum, architecture, memory, port, container, network, volume, health, or ledger mismatch.

## Automated acceptance before server mutation

- Deterministic bundle reconstruction is byte-identical and every archive member matches SHA-256, size, mode, and safe-path rules.
- Accepted M072 commits and artifact pins match exactly.
- Compose parses, uses only digest-pinned images, has an internal-only network, four localhost bindings, disabled Dolibarr/WordPress cron, disabled WordPress external HTTP, Mailpit, and no canonical Staging/Production identities.
- Helper tests reject traversal, absolute paths, duplicate members, missing active state, target collisions, unpinned sources, unsafe locks, non-local port mappings, extra network members, and altered release files.
- Rollback removes only the candidate containers and network. It never passes `--volumes`; all six candidate volumes remain retained.
- M072 artifact reproducibility and release-controller/state tests remain passing.
- Bash/Python syntax, YAML parsing, manifest verification, and `git diff --check` pass.
- Security and QA independently approve the identical frozen pins.

## Server preflight and creation acceptance

- Restricted read-only preflight confirms linux/amd64, Docker/Compose availability, at least 3 GB available memory, the four candidate ports free, and no existing resource with the exact M073 project label.
- Uploads use only `/home/codex-deploy/m073-helper-install/` and `/home/codex-deploy/m073-rebuild-inbox/`, both private to `codex-deploy`.
- Root installation requires an explicit human root-admin confirmation and installs only the frozen helper and sudoers; it does not execute the helper.
- `preflight` must return `READY` before `create` is executed.
- `create` must end with all six exact containers running/healthy, the exact six volumes, one internal network containing only those containers, four localhost-only bindings, secrets present at fixed lengths, and ledger state `ACTIVE` with `productionData=ABSENT`.
- No email or business transaction is used as acceptance at this gate.

## Arabic, English, French, RTL/LTR, accessibility, and mobile

The stack-creation gate proves source and runtime availability only. Full language, direction, accessibility, 360/390px, tablet, desktop, Customer Center, shipping/Alexandria, and mixed-quotation browser acceptance remains required after a separately approved sanitized fixture/import gate. No HTTP health result can waive that later acceptance.

## Rollback and stopping gates

- Before server mutation, stop on any test failure, pin change after review, reviewer disagreement, unavailable candidate port, insufficient memory, or pre-existing M073 resource.
- During creation, a failure runs candidate-scoped `compose down --remove-orphans` without deleting volumes and writes terminal `FAILED` state when possible.
- Approved rollback removes only the exact project containers and internal network; it preserves secrets, release files, ledger, images, and all data volumes for evidence.
- Stop after isolated stack creation/verification. Production export, data sanitization/import, DNS/canonical Staging cutover, and Production each require separate exact approvals.

## Risk

**High.** The target is isolated, but the helper creates privileged Docker resources. Isolation, immutable pins, retained-volume rollback, and independent frozen reviews are mandatory.
