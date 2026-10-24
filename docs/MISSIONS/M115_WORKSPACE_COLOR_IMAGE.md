# M115 — Consistent customer appearance

Status: APPROVED by Ahmed's direct browser comments on localhost, 2026-10-24.

Customer workspaces use the same gallery components as General boards. Remove the green navigation strip and mark the active tab with the existing navy/blue palette. Add customer-container color/image settings inside the workspace and project the same saved appearance onto its customer card. Project cards continue to use their real board backgrounds; customer IDs must never be used as board IDs.

Shared entity/customer-scoped appearance storage, existing customer-edit/admin authorization, version CAS, CSRF, private authenticated raster reads, 5 MB and 40 MP limits, JPEG/PNG/WebP only. No native customer/project/task data or access grants are changed by appearance saves. Choose contrasting foreground for solid colors and an overlay for image legibility.

Verification: real authenticated color save and root/inner equality; later user-selected image persisted and its identical root/inner URL and reload verified. Automated synthetic file chooser failed with a stale backend node and was not retried; do not describe it as a successful automated upload. The user's later local image selection is distinct evidence and was not exported in screenshots.
