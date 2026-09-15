# M083 Staff-First Production Release Completion Report

- **Mission:** M083 Staff-First Production Release
- **Release:** M083-R08
- **Runtime Commit:** `d21c0ca30150e1b885586f1c337773b6c71f7802`
- **Target:** Production `https://erp.visiontrading.biz` (`31.97.158.178`)
- **Status:** `DEPLOYED` (Verified live)
- **Deployment Timestamp:** `2026-09-15T06:00:31Z`

---

## 1. Frozen Pins & Release Artifacts

| Component | Pinned SHA-256 Hash | Path |
|---|---|---|
| Artifact | `ab1309b48f16b36ea10cc8b463f8c6ba170eb5787169d63acf458d94b9ed5aa0` | `/var/lib/chinaplanetos-m083-production-r07/m083-r08-staff-first-d21c0ca.tar.gz` |
| Restricted Helper | `2d11f1e5183c6ff4dcc160a949710b31348786810b421f12d333ca7002702e8a` | `/usr/local/sbin/chinaplanetos-production-m083-staff-first` |
| Root Installer | `ba34ec9e8f41d91b27f0fab752a3de306956ea009f4fbefd414f1eb7d7aff58e` | `/home/codex-deploy/staging-inbox/install_production_m083_staff_first_helper.sh` |
| Sudoers Config | `03b11843b7f0e5780e02c4cf68b356c2c439cb2110c72d7b71cac8c4f7e76a91` | `/etc/sudoers.d/codex-deploy-production-m083-staff-first` |

---

## 2. Pre-Deployment Backups

All state before mutation was captured and cryptographically recorded:

- **Code Backup:**
  - Path: `/root/chinaplanetos-production-m083-code-backups/m083-code-before-d21c0ca-20260915T060015Z.tar.gz`
  - SHA-256: `87c4b0557e1fd16e3d9f902275aa2949e1854b11e7c35ac770ec0febec5159d0`
- **Absent Files Manifest:**
  - Path: `/root/chinaplanetos-production-m083-code-backups/m083-absent-before-d21c0ca-20260915T060015Z.txt`
  - SHA-256: `4f4aa4b436194d7bc1a817fd0646c4ff00883273c278786f23bade649ae1e941`
- **Database Backup:**
  - Path: `/root/chinaplanetos-production-db-backups/m083-before-d21c0ca-20260915T060015Z.sql`
  - SHA-256: `3d0bd976af7cb6b313cd23072dc251dffc4f6fe92c37eefee11ed57b130fb2b4`

---

## 3. Deployment & Schema Execution

1. **Prerequisite & Schema Migration:**
   - Evaluated 15 core prerequisite tables: all present.
   - Added column `customer_visible` to `llx_chinaplanetlogistics`.
   - Created 6 new production tables:
     - `llx_cpcc_customer_receipt`
     - `llx_cpcc_fund_allocation`
     - `llx_cpcc_fund_event`
     - `llx_cpcc_guided_receipt`
     - `llx_cpo_billing_action`
     - `llx_cpo_supplier_action`
   - Installed 4 native `ManagedFundsNativeGuard` MariaDB triggers.
2. **File Deployment:**
   - 58 runtime files safely installed into `/var/lib/docker/volumes/vision-os_dolibarr_custom/_data`.
   - Preserved file ownership and `0644`/`0755` permissions.
3. **Execution Evidence:**
   - `M083_PRODUCTION_APPLY=PASS`
   - `M083_PRODUCTION_VERIFY=PASS STATE=DEPLOYED SCHEMA=READY UNCHANGED=1 DATABASE_MUTATED_BY_COMMAND=NO`

---

## 4. Live Verification & Route Health

- **HTTPS Status:** `200 OK` on `https://erp.visiontrading.biz/`
- **Key Routes Verified:**
  - `/custom/chinaplanetoperations/workspace.php`: `HTTP 200`
  - `/custom/chinaplanetcustomercenter/board.php`: `HTTP 200`
  - `/custom/chinaplanetlogistics/list.php`: `HTTP 200`
- **State File:** `/var/lib/chinaplanetos-m083-production-r07/m083.state` records `STATE_STATUS=DEPLOYED`.
