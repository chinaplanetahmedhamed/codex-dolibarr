# Board Sounds Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Add controllable personal sound cues to current Boards without new data access.
**Architecture:** Separate tested JS sound controller/player; small hooks on successful mutations and selected-board snapshot application; existing dialog and navigation for settings.
**Tech Stack:** Vanilla JavaScript, Web Audio, native browser storage, existing PHP asset includes.
**Spec:** docs/superpowers/specs/2026-10-28-board-sounds-design.md

## Global Constraints
- Personal user/entity/browser preferences; default muted; four tones; per-board inherit/override/silent.
- Existing access/entity filtering, polling and hidden banner stay intact; no backend/schema/production changes.
- Audio never blocks business saves; only new-board navigation may await a cue for at most500ms.

## Review Focus
- Blocked/suspended audio must not stall or misreport saves.
- First load/reconnect/removed access must not emit backlog sounds.
- Failed storage or malformed/cross-user preferences must not leak or crash.
- Own moves followed by their refreshed snapshot must not double beep.
- A saved override must survive reload and keep another board's default intact.

### Task 1: Controller and short tone player
**Files:** new js/boards_sounds.js; deploy/tests/boards-sounds/controller.cjs.
**Interfaces:** createController({storage,key,play,unlock,now,onChange}) → settings(),configure(),setBoardTone(),toggle(),preview(),seed(),received(),observe(),disconnected(),committed(),settle(). createPlayer(window) → unlock(),play().
- [x] Write behavior tests for defaults, persisted isolation, choices/override/silent, failure and mute, successful events, rate-limit, seed/reconnect/dedup; run RED (missing controller export).
- [x] Implement controller and Web Audio synthesis; run GREEN plus prior sync/pointer tests.
- [x] Commit Task1.

### Task 2: Native controls and event hooks
**Files:** boards.js, boards.css, tasks/boards.php; new integration test and isolated native preview router.
**Interfaces:** API success calls committed(action,payload,result); manual load seeds snapshots; successful workspace reads maintain the heartbeat; remote apply observes; offline/denied reset baseline. Existing native dialog hosts labeled selectors and preview.
- [x] Write integration test proving failed requests silent, successful own/remote change once, first load silent, bounded board creation cue and cancel unchanged; run RED.
- [x] Implement Arabic/English mute/settings controls, four-tone preview, per-board inherit/silent and persistent choice, gesture unlock; avoid visible update banner.
- [x] Run full M116/workspace and prior realtime tests; native desktop/mobile/keyboard UI tests; inspect final screenshots.
- [x] Commit Task2 and complete fresh read-only final branch review; report exact readiness and audio/physical-device limits. Do not deploy.
