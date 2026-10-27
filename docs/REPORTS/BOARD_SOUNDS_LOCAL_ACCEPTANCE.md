# Boards sounds — local acceptance

Candidate branch: `codex/boards-sound-settings`, based on `0660e6ab3b849be3c7da5041e719df2c2c3e6872`. Controller introduced in `275b1a5`; final source candidate `16e105fcb0923d32860dc9abe246898c79d7700c`. No production operation or remote source push was performed for this feature.

## Behavior

Personal settings are scoped to the existing user/entity and browser origin. New preferences are muted. Four generated tones (Chime, Soft, Ping, Bell) support preview; each board can inherit the default, select a tone or stay silent. Normal browser audio activation is required on each loaded page. No autoplay policy, authentication or authorization is bypassed.

Successful own card moves/additions/copies, board creation, list creation and board completion/reopening can notify. Existing authorized selected-board snapshots notify for newly visible cards/lists, moves/order changes and board completion state. Failed mutations, initial load, unchanged polling, access loss and reconnect/long-gap catchup are silent. Short bursts are coalesced. Gallery events from other sessions, My Work personal-column mutations and generic card edits are outside this sound slice.

Changed production files only:

- `custom/chinaplanetoperations/js/boards_sounds.js` (new controller/player).
- `custom/chinaplanetoperations/js/boards.js` (successful-read/mutation hooks and native controls).
- `custom/chinaplanetoperations/css/boards.css` (scoped controls,44px targets).
- `custom/chinaplanetoperations/tasks/boards.php` (versioned script include).

No backend, schema, grant, credential, core, finance, polling interval, touch runtime or update-banner behavior changed. Local rollback is the four-file base revision; a future live release must first reconcile its actual deployed files and take a fresh feature-only backup.

## Passed verification

Fresh full `sh deploy/tests/m116/run.sh`;14 sound-controller behavior cases; API integration including real sync/audio idle/catchup regression;12 sync assertions;12 pointer assertions; JavaScript syntax; PHP8.2 lint of the changed PHP page; `git diff --check`.

Both review findings were reproduced before fixes: healthy unchanged polling for more than10seconds no longer suppresses the next current remote cue; rejected preview playback now reports failure. Native rendering exposed preview buttons inheriting36px height; scoped sound-dialog controls now measure44px.

Native24.0.2/PHP8.3.21 acceptance used existing accounts through normal sign-in. Arabic390x844 and English1536x1080 controls, all four previews, mute and reload, saved tone/override/silent/inherit, Escape, focus return and hidden update banner were checked. Independent IAB/Chrome sessions propagated list addition and card movement in both directions without manual reload. A read-only before/after comparison confirmed restored placement and unchanged native task/project/cash data.

The M116 fixture had no teams. The documented M097 synthetic fixture already had team1 and an administrator; native creation/replay/archive(board92) and normal browser creation/save/navigation/archive(board93) passed there. Access and team-membership fingerprints stayed identical. Test boards are recoverably archived; no native project/task was created or deleted.

Evidence stays in the separate local `boards-sounds-20261028/evidence` directory. Full final regression output is `final-regression.log`. Mobile screenshot is saved in Library as `libfile_15a6b3d7540c819194e21b3611cf4d8a` (`file_000000008e748207a58c36dd7437e91f`); no source bundle or credentials were uploaded.

## Limits and release boundary

Physical speaker audibility and real touchscreen interaction are unverified. Native acceptance used24.0.2; live24.0.1/PHP8.2.7 acceptance is not claimed. Existing transport remains two-second polling, not push. Browser navigation resets audio readiness and may require another normal Enable sound gesture. A production rollout requires scoped authorization, fresh source reconciliation/backup and live verification; none occurred for sounds.

## Final review and cleanup

Independent read-only review of exact source candidate16e105f against0660e6a found no important outstanding findings and independently reran the14 sound, API/real-sync integration,12 sync,12 pointer,JS/PHP8.2 syntax and diff checks. All passed. The primary completed native browser acceptance separately; review itself did not operate a browser or database. Temporary preview listeners8132/8133 were stopped; QA preferences returned to mute; temporary browser tabs closed. Shared MySQL and installed runtimes were not restarted or reconfigured.
