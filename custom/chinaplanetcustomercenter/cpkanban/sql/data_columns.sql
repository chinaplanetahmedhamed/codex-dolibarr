-- ============================================================
-- Default Kanban columns for China Planet import/sourcing workflow
-- Run once after creating the tables (or via module init).
-- cust_visible: 0 = internal-only column (hidden from customer portal)
-- ============================================================
INSERT INTO llx_cpkanban_column (entity, code, label, label_en, color, position, cust_visible, auto_progress, active) VALUES
(1, 'new',    'طلبات جديدة',            'New Requests',          '#8e44ad', 10,  1, 5,   1),
(1, 'review', 'قيد المراجعة',           'Under Review',          '#2980b9', 20,  1, 15,  1),
(1, 'wait',   'بانتظار بيانات العميل',  'Waiting for Customer',  '#d4a017', 30,  1, 25,  1),
(1, 'prog',   'قيد التنفيذ',            'In Progress',           '#16a085', 40,  1, 55,  1),
(1, 'supp',   'متابعة المصنع/المورّد',  'Supplier Follow-up',    '#e67e22', 50,  0, 60,  1),
(1, 'insp',   'فحص الجودة',             'Inspection / QC',       '#1abc9c', 60,  1, 78,  1),
(1, 'ready',  'جاهز للشحن',             'Ready for Shipping',    '#27ae60', 70,  1, 90,  1),
(1, 'ship',   'الشحن / في الطريق',      'Shipping / In Transit', '#2c3e50', 80,  1, 95,  1),
(1, 'done',   'مكتمل',                  'Completed',             '#7f8c8d', 90,  1, 100, 1),
(1, 'hold',   'ملغي / معلّق',           'Cancelled / On Hold',   '#c0392b', 100, 0, NULL,1);
