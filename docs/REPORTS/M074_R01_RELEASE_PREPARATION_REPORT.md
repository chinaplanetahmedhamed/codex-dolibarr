# M074-R01 Local Release Preparation Report

## Control record

- **Mission/release:** M074 / M074-R01
- **Status:** `CHECKSUM_PINNED_LOCAL / INDEPENDENT_ARTIFACT_REVIEW_PENDING / NOT_DEPLOYED`
- **Source branch:** `codex/m074-qc-field-report-composite`
- **Pinned Dolibarr/mobile source commit:** `7d1cdcd2f6c1343001449f35a95f4901bf3e0fb7`
- **React source commit:** `01c9d872ccdf5664cf470019194156b5503dc74b`
- **WordPress source commit:** `ae273e2d15fa89111336ef74f746a86cc9ac57bd`
- **Artifact directory:** `deploy/releases/m074-r01`
- **Staging/production:** not accessed or changed

## Artifact identity

| File | SHA-256 | Bytes |
|---|---|---:|
| `dolibarr.tar.gz` | `2f7e368dc5c4c1455166a5b6c56c55b33bb32c0ec0a3c14f5d55023f71e68830` | 1,171,904 |
| `mobile.tar.gz` | `def53d88bbeaf756a8ba6186f85b9c6a2c8d408732ba10ad7af7a099b0f9bccd` | 872,207 |
| `react.tar.gz` | `44c3bd853e681f12dbdc2579de48f018aa2a739a962e38bceb9389742d5a9c9a` | 3,394,016 |
| `wordpress.tar.gz` | `3a91e08a96562925881187ba5c3719188781e3edf74066284fec1301e1bfb332` | 102,341 |
| `release-bundle.json` | `ffc8a3d00c50a90be0dc7d2e2a64d4eccc14ac9da8aec1c46082b436a2313bfb` | see repository |
| `release-pins.sha256` | `09fbb1f3ad18f43596ebfc09a1e4970932969678949f37839e168ebefe78dd77` | see repository |

The bundle is deliberately marked `UNBOUND_REQUIRES_INDEPENDENT_REVIEW_AND_STAGING_APPROVAL`. It is not a deployment authorization.

## Completeness and reproducibility

- `docs/REPORTS/M074_SOURCE_TO_RUNTIME_MANIFEST.tsv` identifies 42 M074 Dolibarr runtime/schema files in addition to the accepted M070/M071 composite runtime surface.
- The Dolibarr artifact contains 514 members; the mobile artifact contains 95 members.
- The existing M072 builder was extended backward-compatibly. Rebuilding M072-R01 with the updated builder remains byte-identical to the frozen M072 release.
- M074-R01 was built independently three times from the same pinned commits. Both temporary builds and the frozen repository copy were byte-identical.
- Every pinned file passes SHA-256 verification. Archive members are sorted, metadata-free (`uid=0`, `gid=0`, `mtime=0`) and checked against their manifests.
- The QC runtime manifest includes the manual-checkpoint workflow, private evidence/media routes, additive schema, customer publication projection, generic-document QC exclusion, PWA and localized portal surfaces.

## Release boundary

No upload, helper installation, `compare`, `preflight`, schema migration, staging apply or production action is included in this preparation. After independent artifact review, the next possible gate is a separately approved staging upload plus remote checksum verification and read-only `compare`/`preflight`. Apply remains a later explicit approval.

## Independent review

Pending review of the frozen bytes, manifest completeness, source ancestry, M074-only publication boundary, M072 reproducibility and absence of credentials/business data.
