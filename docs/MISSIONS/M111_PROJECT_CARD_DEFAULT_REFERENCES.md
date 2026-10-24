# M111 — Automatic ERP references for project cards

APPROVED / IN_PROGRESS. Ahmed approved forwarding automatic customer/project inheritance from the project board and shipment inheritance only for an unambiguous valid permitted relation. If multiple shipments exist show a choice rather than guessing. Allow deliberate compatible manual changes, preserve permissions/native task linkage and never rewrite old records silently. Reproduction: live board12/PJ2606-0002, card CP-TK-2026-000075 shipping has empty Link ERP fields despite its breadcrumb.

Implementation: persist defaults atomically on new card creation; leave manual saved choices intact on replay. For old cards lacking a saved context derive read-only board defaults without a DB backfill. Return shipment-choice hint using authorized choices, no hidden shipment identity/count disclosure. Existing saved context is never overwritten on read. Native task remains attached to its structural project. Clarification pending on whether changing project means moving the card to another project board or keeping customer/project fixed while shipment remains editable.

No production writes or external publication approved by this addition.

User clarified: changing the project explicitly moves the same card and native task to the destination project board, first active list. Both boards and native projects must be editable; dependent native time/contact/document/child/linked records block implicit movement for reviewed handling. No backfill or new grants.
