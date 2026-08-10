# M046-C01 — Translation policy and setup security completion report

## Report control

- **Mission:** M046-C01 — Translation enablement and setup CSRF boundary
- **Report status:** DEVELOPER_REPORTED — STAGING_PENDING
- **Implementation owner:** Agent 007
- **Branch:** `codex/m046-c01-translation-policy`
- **Base commit:** `2404ba03f10fadd503fc3dbbea67ad9f8a87e6fa`
- **Runtime result commit:** `15c19dda5842be992e6926db473878042a0a96d5`
- **Release-assets head before this report:** `e0af5ff16f235fb91226882428e4cc3afa02afba`
- **Environment used:** local development and a disposable local Dolibarr database
- **Staging touched:** No
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
| Restricted helper SHA-256 | `2b47bbb8e19a8a9869d5410fbe1122d30b089b66c41c0535e96e670b3df686fa` |
| Helper installer SHA-256 | `f2ba74352883d8ab8b8477e1dba927adc0e4d8c6831cb6f0884030ae21676884` |
| Artifact builder SHA-256 | `7b66b8da8fc6650a6525689462f47eb0b1854dbafb14f2543cca48edc02089b6` |
| Sudoers policy SHA-256 | `a44d291dcc7a3a417c98aff42421b88d51212f97b7ef26406a7adba14a98633b` |
| Helper test SHA-256 | `ac9e607043fa6005c4099db4b2e79c0ed6b0506b67c6a57623e044194a17c577` |
| Release-contract test SHA-256 | `125e2990537a5a2078a9ba4642ef77c267d5f056cece6cf77496d740bbf957d9` |

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
| `git diff --check` | PASS |

## Data and environment boundary

- No staging or production host was accessed.
- No production or staging file, database row, configuration, module state,
  business record, batch, quotation, order, payment, shipment, or document was
  changed.
- The real local integration test used synthetic fixture data in a disposable
  database. It verified cleanup and confirmed the temporary database was absent
  after completion.
- No carrier note was sent to any external translation provider.

## Rollback and staging boundary

The restricted staging helper is the only approved apply path for this
candidate. It creates request-scoped backups, verifies owner/group/mode and file
hashes, applies under a lock, automatically restores on apply or verification
failure, and supports explicit rollback. The helper installation remains a
separate privileged staging action.

No `backup-erp` mutation may occur until Ahmed approves the complete pinned
staging contract. Production remains forbidden until staging runtime and
authenticated acceptance pass and a separate exact production approval is
given.

## Architect disposition

- **Decision:** READY_FOR_GIT_REVIEW
- **Local implementation:** PASS
- **Staging:** PENDING_EXACT_APPROVAL
- **Production:** FORBIDDEN
