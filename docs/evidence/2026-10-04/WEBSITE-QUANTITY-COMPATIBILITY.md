# Website quantity intake compatibility

Ahmed authorized the bounded ERP compatibility correction after website staging intake displayed two requested machines as one carton. Website accepted staging SHA: 17590abffcc977de769af523c503d9c33e222cea; request WEB-20261003-214731-7E6924, record38.

This patch is based on the deployed M085 source 7d0ca969a164867ff11fb0e8c034ffff02bfcf5b and preserves qualification, staff packing verification, transactions, customer/contact checks and standard proposal handling. Five runtime files: quotation normalization class, public request page, English/Arabic/Chinese language labels. No schema, prices, permissions, orders, stock or contact changes.

Modern piece/machine/set quantities retain requested units. Unknown packing produces no carton count, volume or weight. Modern confirmed cartons and legacy numeric carton quantities retain their multiplication. Both server proposal guard and UI require a positive carton count and packing before creating a quotation. Read-only staff page shows saved privacy acknowledgement/version independently of freight price evidence.

Local acceptance: PHP lint both PHP files; normalization runtime regression; M045 quotation security contract; M071 catalog v2 contract; M055 contact safety; M043 intake contract; git diff check, all PASS on reconciled source.

Deployment remains gated: existing ERP helpers are pinned to older payloads. Exact one-time root exception is required before executing a new scoped helper. Candidate must verify actual deployed before hashes, exact canonical container/mount, candidate PHP syntax, backup five files, apply/verify and code-only rollback on error. Stage first; then native selected request38 must show two machines, packing pending, saved consent, persistence/reload and idempotent retry. Production remains untouched until stage acceptance and approved release execution. Website production promotion remains stopped.
