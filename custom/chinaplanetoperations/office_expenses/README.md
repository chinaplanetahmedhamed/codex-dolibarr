# Legacy Office Expenses compatibility path

Canonical source now lives in `custom/chinaplanetofficeexpenses/`.

The old entry and PHP class paths delegate to that implementation; they preserve request URLs/class includes and do not require Operations enablement. The standalone gate, native authentication, pilot restrictions and ownership checks still apply.

Schema/static assets remain for compatibility and historical packages. No record, table or private path was renamed/deleted. Develop only in the canonical module. See its README and `docs/REPORTS/M119_STANDALONE_MODULE.md` for activation/release prerequisites.
