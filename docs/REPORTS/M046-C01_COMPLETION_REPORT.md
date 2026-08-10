# M046-C01 — Translation policy and setup security completion report

## Report control

- **Mission:** M046-C01 — Translation enablement and setup CSRF boundary
- **Report status:** STAGING_ACCEPTED — PRODUCTION_PREFLIGHT_APPROVED
- **Implementation owner:** Agent 007
- **Branch:** `codex/m046-c01-translation-policy`
- **Base commit:** `2404ba03f10fadd503fc3dbbea67ad9f8a87e6fa`
- **Runtime result commit:** `15c19dda5842be992e6926db473878042a0a96d5`
- **Release-assets head before this report:** `e0af5ff16f235fb91226882428e4cc3afa02afba`
- **Environment used:** local development, a disposable local Dolibarr database, approved canonical `backup-erp`, and production-preflight asset review
- **Staging touched:** Yes — restricted deployment and authenticated acceptance passed
- **Production touched:** No

## Business outcome

Carrier/source-note automatic translation is now hard-disabled regardless of
the stored setting. Imported carrier notes stay private and ineffective, no
external translation provider is called, and no translation-state database
mutation is made. Authorized staff can still write or edit the separate manual
public note. The setup route remains a permission-gated, CSRF-protected POST
surface and no longer offers a control that can enable automatic translation.

## Runtime scope

The staging candidate contains exactly these six runtime files:

1. `custom/chinaplanetshippingprices/admin/batch.php`
2. `custom/chinaplanetshippingprices/admin/setup.php`
3. `custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php`
4. `custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php`
5. `custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php`
6. `custom/chinaplanetshippingprices/class/ShippingPriceService.class.php`

The artifact also carries 19 checksum-pinned support and test files. Only the
six runtime files are deployment targets.

## Release contract

| Item | Pinned value |
|---|---|
| Artifact | `m046-c01-15c19dd.tar.gz` |
| Artifact SHA-256 | `79982ab606c958da9c7193c08464156c4e7e18f07d4ce5b65ba7b5af39164596` |
| Members | 27 |
| Archive format | Deterministic USTAR; mtime 1980-01-01; uid/gid 0; mode 0644; no PAX/xattr |
| Runtime manifest SHA-256 | `69d754158a34b54dbf3186a2ba69c7a9a2c958a5f43b3e0605462cc470c7f798` |
| Support manifest SHA-256 | `6c64b2f9441a1a3fdbb8950f295209f973c34c7e788d05b7303a5c5c7e4ec47b` |
| Restricted helper SHA-256 | `2546a927e27c3b0fcf1d4957c0b8864fd1359a177250fcc980bdd2f0bc50ea1f` |
| Previous installed helper SHA-256 | `2b47bbb8e19a8a9869d5410fbe1122d30b089b66c41c0535e96e670b3df686fa` |
| Helper installer SHA-256 | `124eacc1316e11b96a428842bd71183dece8088dc9118c7f172286bb5c9a2704` |
| Artifact builder SHA-256 | `7b66b8da8fc6650a6525689462f47eb0b1854dbafb14f2543cca48edc02089b6` |
| Sudoers policy SHA-256 | `a44d291dcc7a3a417c98aff42421b88d51212f97b7ef26406a7adba14a98633b` |
| Helper test SHA-256 | `ac9e607043fa6005c4099db4b2e79c0ed6b0506b67c6a57623e044194a17c577` |
| Release-contract test SHA-256 | `c672c1a87b00703982a641a5b601ac9f9deede26ed988b359fd74e43e276e1d2` |

The production-preflight release pin uses the same runtime commit and artifact.
Its separate production helper is pinned to
`a351c5653d8a66e9f7a65db579bf9ce21b5a49d4c082d04dcc48cb0312475926`,
with the previously installed helper pinned to
`0324870d9bdcfe8659508cb702f3ddbcc7c8ca27d854504d738e3fb1d29bad58`,
the preflight-only sudoers policy to
`18a7dfd57efe44ea961ec45af13c589b090eb4c626145b11ea03bc5bb58fe17d`,
and the installer to
`98842a872bdbabf0e117c8a5b3921342fa04c36f0f4ecfd1e7847e3752bdcbe1`.
The production identity gate matches the observed Compose identity
`true|vision-os|dolibarr` for `vision_erp`.
The sudoers policy exposes only `status`, `compare`, and `preflight`. These
commands do not write to the ERP container; candidate execution remains behind
the separately gated apply command.

## Verification evidence

| Verification | Result |
|---|---|
| PHP lint on all six runtime files | PASS |
| M041 translation unit/source/runtime/provider regressions | PASS; live provider probe forbidden |
| M044-C03 and M044-C04 review/translation regressions | PASS |
| M046-C01 translation policy | PASS; effective enabled=0, provider calls=0, DB mutations=0 |
| M046-C01 setup route | PASS; POST, CSRF, and permission contracts enforced |
| M046-C01 call-site contract | PASS |
| Shipping publication-state regression | PASS |
| Upload CSRF and module regressions | PASS |
| Real local Dolibarr runtime with disposable database | PASS; prestate restored and database/fixtures removed |
| Release contract | PASS; 6 runtime, 19 support, 27 artifact members |
| Deterministic artifact build | PASS; SHA-256 matched the pinned value |
| Restricted staging-helper destructive harness | PASS; locks, replay, backups, fail-closed verification, auto/manual rollback |
| GitHub run `31391700862` helper/contract/artifact/compare/preflight | PASS |
| GitHub run `31391700862` apply | STOPPED before request creation with `REQUEST_ID_TIME_INVALID`; runtime remained `NOT_DEPLOYED` |
| Normalized request timestamp on backup-ERP GNU `date` | PASS with `YYYY-MM-DDTHH:MM:SSZ` |
| `git diff --check` | PASS |
| GitHub staging run `31393168404` | PASS; apply, verification, and acceptance completed on `backup-erp` |
| Production helper destructive local harness | PASS; production identity pins and rollback safety verified without production access |
| Production preflight sudo contract | PASS; exactly 3 commands and no apply/verify/rollback grant |

## Data and environment boundary

- Canonical `backup-erp` was deployed under Ahmed's exact staging approval.
  GitHub run `31393168404` passed deployment, verification, and authenticated
  staging acceptance for the six runtime files.
- The staging deployment did not change database rows, application
  configuration, module state, business records, batches, quotations, orders,
  payments, shipments, or documents.
- Production preparation is a separate gate. At this report revision,
  production had not yet been accessed or changed.
- The real local integration test used synthetic fixture data in a disposable
  database. It verified cleanup and confirmed the temporary database was absent
  after completion.
- No carrier note was sent to any external translation provider.

## Rollback and production boundary

The production helper retains request-scoped backup, lock, replay, verification,
automatic restore, and rollback logic for a later apply gate. During production
preparation, however, the installed sudoers rule exposes only `status`,
`compare`, and `preflight`. The workflow cannot invoke a runtime apply, verify,
or rollback command. Any production apply requires a separately approved helper
surface upgrade after compare/preflight evidence is reviewed.

## Architect disposition

- **Decision:** PRODUCTION_PREFLIGHT_ASSETS_READY_FOR_GIT_REVIEW
- **Local implementation:** PASS
- **Staging:** PASS; runtime commit `15c19dda5842be992e6926db473878042a0a96d5`
- **Production:** PREFLIGHT PREPARATION APPROVED; APPLY FORBIDDEN
