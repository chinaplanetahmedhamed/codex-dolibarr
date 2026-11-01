# M119 — Native staging staff creation

2026-11-02. Ahmed requested linking to Dolibarr/checking staff and then explicitly requested new users Moaz and rina.

Target: `https://backup-erp.visiontrading.biz` only, authenticated native Dolibarr 24.0.1 administrator session. All-status native user list initially contained only admin, a QA worker, a customer QA login and the staging website gateway; neither requested login existed. No production access.

| Native ID | Display name | Login | Type | Status | Requested finance branch | Branch grant |
| --- | --- | --- | --- | --- | --- | --- |
| 5 | Moaz | moaz | Internal employee | Enabled | Egypt | Manager; activation pending |
| 6 | rina | rina | Internal employee | Enabled | China | Manager; activation pending |
| 7 | omar | omar | Internal employee | Enabled | Egypt | Worker; activation pending |
| 8 | shan shan | shanshan | Internal employee | Enabled | China | Worker; activation pending |

Created sequentially through native `/user/card.php?action=create` using normal CSRF-protected Create user forms, entity 1, administrator explicitly No and employee checked. Each successful POST redirected to the distinct native card and was read back. Strong independent temporary passwords were prepared in masked browser fields; their values were not returned to tools/chat, stored in project files or distributed. Force password change at next login was checked. Administrator must set/deliver usable credentials through the native password flow; no email address was invented, no API key was generated, no email was sent.

No existing account was edited or removed. Native default rights are to be read back before claiming access acceptance. Financial branch schema/module activation, grant selection, persisted branch assignment and cross-branch acceptance remain separate unfinished steps. No native posting is enabled. Account creation does not establish module deployment or isolation.

Restricted SSH identity was read back as host `srv1011043`, user `codex-deploy` UID 1002, using the dedicated key, strict host-key verification and bounded batch connection. The installed helper still exposes the historical pinned M119 five-action surface; it is not authority or capability to deploy a new arbitrary package. No remote helper, Docker, schema, config, rights, deployment or service operation was executed.

Preserve the new accounts and native audit; do not delete them as test cleanup. Rollback of a finance-code release must preserve them and all historical data.

Ahmed then specified manager roles for Moaz/Egypt and rina/China, and workers omar/Egypt and shan shan/China. The latter two were created using the same native workflow and read back as Enabled (IDs 7 and 8). Browser pointer automation timed out before clicking two controls; the existing native controls were then set/clicked through DOM dispatch, with successful native redirects verified. No direct database write or forged account API was substituted. Supported role intent: managers have branch read/create and delegated branch assignment access; workers have own-record read/create, without access management.

## Final native account/group readback

All four native edit forms were reopened and confirmed the expected login, administrator No and employee Yes. Initial post-create readback showed force-password-change false: native `User::setPassword()` resets that flag after account creation. The four new role groups were therefore granted only native right 343 (change own password), then the native account edit form was saved with its password field cleared (preserve password) and force-password-change checked. Fresh edit-form reads confirmed true for all four. Rights 255 (other users passwords) and 344 (own permissions) remain ungranted in these groups; no global administrator was enabled. No existing unrelated account/group was changed.

| Group ID | Native group | Sole new member |
| --- | --- | --- |
| 4 | Office Expenses - Egypt Managers | moaz, user 5 |
| 5 | Office Expenses - China Managers | rina, user 6 |
| 6 | Office Expenses - Egypt Workers | omar, user 7 |
| 7 | Office Expenses - China Workers | shanshan, user 8 |

The groups are real native memberships, currently carrying only self-password capability. Their names do not by themselves enforce finance-branch isolation. The exact reviewed finance role/branch intent is captured in [activation plan](M119_STAFF_BRANCH_ACTIVATION_PLAN.json).

The standalone `/custom/chinaplanetofficeexpenses/users.php` route returned authenticated 404 on the staging host. Restricted SSH freshly confirmed the installed deployment helper SHA256 `845e497f2828df927ce14e5431d180a747c62bae264fbcee244183696cb967d9`, the historical helper pinned to source `8b14b9e9c609ed296cb18368f576bbf86fffadbb` and the old Operations subdirectory. Its source allowlist cannot deploy the standalone module or access schema. No new package was applied or permission boundary bypassed. Local Docker is installed but its local default Unix daemon socket is absent; no daemon/service was started. Thus matching-engine release checks and a complete replacement deployment path remain required before finance activation.

A source packaging issue was also corrected: repository-wide SQL ignore rules excluded the two new access migration source files. Exact-path exceptions were added; database-dump exclusions remain. No runtime schema changed.

## Local role review updated

The original-style review now shows the four verified native IDs/logins and links to their staging Dolibarr cards: two rows per branch, manager branch visibility and worker own-record visibility. It is explicitly a verified identity snapshot with finance activation pending, not a live directory API. Save/revoke remain disabled. The preview matrix now advertises only implemented draft Create/Read; unsupported Update/Delete/other-section actions remain unavailable. The 4-role native-library scenario passes, bringing the staff suite to 21 passed cases.

Final native all-status list readback: moaz (5), rina (6), omar (7) and shanshan (8) all Enabled; no duplicate of any requested login. Original-design preview Arabic/English branch switching shows exactly moaz/omar for Egypt and rina/shanshan for China. Worker prefill selects native IDs 7/8, own-record scope, four Create/Read action controls and disabled Save. Effective 433px phone DOM has no page overflow. Python, JSON, generated JavaScript/PHP syntax and diff whitespace checks pass. No module deployment or finance permission activation is claimed.
