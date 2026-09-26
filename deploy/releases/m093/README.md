# M093 direct production release

Source `26b48717f71b82a84c55ad5abd5052d9fda6e545`; fourteen runtime files, sixteen unchanged prerequisites. Predecessor is the deployed M092 inventory. No schema, hook, account-right or data migration.

Build with `python3 deploy/releases/m093/build.py /private/tmp/m093-artifact`; run `python3 deploy/tests/test_m093_release.py`.

Use the reviewed helper bytes from the authorized root console, checksum-pinned before execution, with `production preflight`, `production apply`, then `production verify`. The helper checks artifact/baseline/dependency hashes, target identity and metadata, and makes root-only code/database backups before writing.

Private state: `/root/chinaplanetos-m093-production/`. Automatic recovery and explicit `production rollback` restore only the 14 files (removing the newly introduced Trash page); task records and financial writes are preserved. Never restore the database over later user writes. Runtime verify includes the existing staff task/Agenda/Trash queries; authenticated UI acceptance is separately required.
