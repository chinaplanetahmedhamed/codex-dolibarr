# Technical access revocation plan — 2026-11-03

Governance authority is revoked now under AI_GOVERNANCE_2026-11-03.md. Technical access revocation is a separate dependency-sensitive operation. No credential values are stored here.

## Observed surfaces
- ERP workflow definitions refer to a shared restricted deployment account named `codex-deploy`, mission-scoped helpers and GitHub-managed secrets. This is source evidence only; current server grants/key ownership/last use are not verified. The name does not establish that an identity belongs exclusively to Agent 007.
- React workflows use GitHub Actions, registry authentication and Portainer service configuration. Their service identities are not evidence of legacy-agent ownership.
- Local/root Codex configuration in this cloud runtime contains no Agent 007/ROLE_REGISTRY/WORKFLOW_RULES/dolibarr-Dev reference. This says nothing about Mac configuration.
- Three hosted automations were visible: daily Chinese training (enabled), daily trade news (enabled), Email Monitor (paused). None included legacy China Planet OS agent delegation. No schedule or prompt was changed.
- Eight open ERP issue/PR records were inspected; no named legacy-agent assignee or explicit Agent 007 reference was found in their bodies. Unmerged work and historical mission authorship remain preserved.
- Actual GitHub app grants, repository bot memberships/deploy keys, server authorized_keys, token inventories, runners, external webhooks, Mac launchd/cron, n8n and Dot's task registry were not available for authoritative inspection. No negative finding is claimed for those surfaces.

## Per-identity revocation procedure
1. Obtain non-secret metadata through an authorized owner/admin read: identity ID/name; credential fingerprint or token ID; purpose; permissions; owner; last use; expiry; scheduler/runner linkage.
2. Map dependent production/staging services, integrations and jobs. Classify legacy-exclusive, shared-service, or unknown. Do not classify by a display name.
3. Preserve audit logs and current non-secret access configuration. For an exclusive obsolete agent identity, prepare scoped removal/disablement. For shared/unknown credentials, establish replacement identities and a tested dependency migration before any removal.
4. Obtain Ahmed's exact approval for service-impacting actions and perform them in a controlled window. Keep production operational and preserve financial audit trails.
5. Verify the obsolete identity is denied; verify replacement/required service health and scheduled workflows; record actual outcomes, operator, time and rollback.
6. Retain identity-level evidence and close only the permissions actually verified/revoked. Never claim global revocation from Markdown changes.

## Current result
No technical credential, token, account, runner, job or access grant was revoked in this migration. No shared service credential was changed. Missing read/admin access and dependency evidence are recorded blockers.
