# WEB-QUANTITY-20261004 bounded release

Source commit: `6e35e6f9224b621660a72178b365a8875314a915`.
Actual stage/live five-file baselines were exported narrowly and verified against the read-only inspector. Separate environment-specific candidates preserve existing features and all translation bytes; the shared quantity normalization regression passes both variants. No staging upgrade to unrelated M085 functionality.

Stage helper SHA256: `ea4e4abe813e0ab2f7d2662aadf52937f70ddd4c4ed84c9b64f4de24010f0375`.
Production helper SHA256: `29ff0adfa6695ab382ebc2c0e6d9f8a7cb32e972693d0dcec5813ef08876e665`.

Seven release guard tests passed, including failed post-apply validation restoring the exact backup. Both embedded PHP stdin regression tests passed locally; runtime preflight repeats them on the pinned PHP8.2 container. Each helper checks exact immutable image identity, mount, published ports, and five old/new SHA256 values. No database queries/writes, schema changes, persistent privileged helpers/sudo grants or container restarts.

After exact upload into the existing restricted inbox, run staging through the already authorized administrative session:

```sh
cp_quantity_stage_dir=$(mktemp -d /root/cp-quantity-stage.XXXXXX) &&
install -m 0600 /home/codex-deploy/staging-inbox/web-quantity-staging-ea4e4abe.py "$cp_quantity_stage_dir/release.py" &&
printf '%s  %s\n' 'ea4e4abe813e0ab2f7d2662aadf52937f70ddd4c4ed84c9b64f4de24010f0375' "$cp_quantity_stage_dir/release.py" | sha256sum -c - &&
python3 -I "$cp_quantity_stage_dir/release.py" preflight &&
python3 -I "$cp_quantity_stage_dir/release.py" apply &&
python3 -I "$cp_quantity_stage_dir/release.py" verify
```

Private stage backup/state: `/root/chinaplanetos-web-quantity-staging` (0700; backup JSON0600). Exact code-only rollback uses the same frozen script `rollback`, preserving enquiry/business records. A failed apply automatically restores original five files. Never bypass drift or replay guards.

Only after native staging acceptance and reload, production uses its separately pinned script; its preflight requires the same committed release successfully deployed on staging and all five staged candidate hashes unchanged:

```sh
cp_quantity_prod_dir=$(mktemp -d /root/cp-quantity-prod.XXXXXX) &&
install -m 0600 /home/codex-deploy/staging-inbox/web-quantity-production-29ff0adf.py "$cp_quantity_prod_dir/release.py" &&
printf '%s  %s\n' '29ff0adfa6695ab382ebc2c0e6d9f8a7cb32e972693d0dcec5813ef08876e665' "$cp_quantity_prod_dir/release.py" | sha256sum -c - &&
python3 -I "$cp_quantity_prod_dir/release.py" preflight &&
python3 -I "$cp_quantity_prod_dir/release.py" apply &&
python3 -I "$cp_quantity_prod_dir/release.py" verify
```

Private production backup/state: `/root/chinaplanetos-web-quantity-production`, same code-only rollback mechanism. Record native acceptance separately; a PHP/health pass does not prove authenticated staff browser acceptance.
