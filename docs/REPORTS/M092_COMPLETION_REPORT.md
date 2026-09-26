# M092 — Deadline and cancel/archive controls

Deadline is now directly editable beside start date/time. A combined save validates and persists both dates atomically, rejecting stale edits and leaving all records unchanged on failure.

Cancel task asks for a reason and moves the task to Archive, removes it from active work/Agenda, and preserves its history, files and prior stage. Authorized Restore returns it to active work. Staff can cancel self-created standalone tasks; existing manager/archive rights control team cancellation. Own Archive is strictly scoped to readable tasks. Existing instruction-edit rights remain intact.

Production code deployed; pinned preflight/apply/verify PASS. Local authenticated save/reload/cancel/archive/restore and bilingual responsive checks PASS. Final native live browser acceptance is pending stable control of the staff ERP window. See dated evidence for exact boundaries, pins and recovery details.
