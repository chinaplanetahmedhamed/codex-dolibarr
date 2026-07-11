# CP Kanban v2.1.0 — Customer Center

## Purpose

`chinaplanetlogistics` is now the central customer portal entry point for China Planet / Vision Trading.

Customers log in with their Dolibarr external user account and land on:

```text
/custom/chinaplanetlogistics/chinaplanetlogistics/board.php
```

They can check:

- their Kanban project cards
- linked shipment/container tracking
- invoices / balance shortcut
- orders shortcut
- Dolibarr project/task shortcut
- documents shortcut

## Install

Copy folder to:

```text
/var/www/html/custom/chinaplanetlogistics/chinaplanetlogistics/
```

Then enable/refresh the module from Dolibarr Setup → Modules.

## Onboarding

Open as admin:

```text
/custom/chinaplanetlogistics/chinaplanetlogistics/admin/onboarding.php
```

Run onboarding for all external users.

It creates/reuses group `Customers Portal`, grants read permission, assigns customers to the group, and sets their landing page to the Customer Center.

## Security rules

Never remove these rules:

- Customer cards are filtered by `fk_soc = $user->socid`.
- Customer cards must have `cust_visible = 1`.
- Customers cannot move cards.
- Internal notes/files are hidden from customers.
- Linked shipments are double-checked by `fk_soc` before being shown.

## Customer hidden-column folding

If a visible customer card sits in internal column `supp`, it is displayed to the customer under `prog` without changing the database.

Column `hold` remains hidden from customers.

## Files

- `board.php` — Customer Center + Kanban board
- `card.php` — card detail page
- `admin/columns.php` — manage columns
- `admin/onboarding.php` — customer onboarding helper
- `ajax/move.php` — secure admin drag/drop endpoint

