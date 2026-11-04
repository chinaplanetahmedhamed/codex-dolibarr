<?php
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/InternalTaskBoardsService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
cpo_task_require($db, $user);
try {
    $svc = new InternalTaskBoardsService($db, $user);
} catch (Throwable $e) {
    accessforbidden();
}

$langs->load('chinaplanetoperations@chinaplanetoperations');
$langParam = GETPOST('lang', 'aZ09');
$ar = $langParam ? (strpos($langParam, 'ar') === 0) : (strpos($langs->defaultlang, 'ar') === 0);

// Classic native Dolibarr shell view fallback
if (GETPOST('view', 'aZ09') === 'classic') {
    $conf->dol_hide_leftmenu = 0;
    llxHeader('', $ar ? 'لوحات العمل' : 'Boards', '', '', 0, 0, [], [cpo_asset('/custom/chinaplanetoperations/css/boards.css')], '', 'cpo-board-canvas cpo-board-sidebar');
    $accountStatements = !empty($conf->global->CPO_ACCOUNT_STATEMENTS_ENABLED) && empty($user->socid) && empty($user->fk_soc) && (!empty($user->admin) || $user->hasRight('chinaplanetoperations', 'account_read'));
    $config = ['nativeSidebar' => true, 'labels' => ['dashboardShipments' => $langs->trans('BoardsWorkShipments'), 'shipmentAttentionScope' => $langs->trans('BoardsShipmentAttentionScope'), 'shipmentAttentionEmpty' => $langs->trans('BoardsShipmentAttentionEmpty'), 'shipmentAttentionPartial' => $langs->trans('BoardsShipmentAttentionPartial'), 'shipmentAttentionAsOf' => $langs->trans('BoardsShipmentAttentionAsOf'), 'shipmentAttentionLimit' => $langs->trans('BoardsShipmentAttentionLimit'), 'dashboardUpcoming' => $langs->trans('BoardsWorkUpcoming'), 'dashboardDue' => $langs->trans('BoardsWorkDue'), 'dashboardNoDue' => $langs->trans('BoardsWorkNoDue'), 'dashboardCustomer' => $langs->trans('BoardsWorkCustomer'), 'dashboardProject' => $langs->trans('BoardsWorkProject'), 'dashboardTitle' => $langs->trans('BoardsWorkDashboard'), 'dashboardOpen' => $langs->trans('BoardsWorkOpen'), 'dashboardUrgent' => $langs->trans('BoardsWorkUrgent'), 'dashboardOverdue' => $langs->trans('BoardsWorkOverdue'), 'dashboardEmpty' => $langs->trans('BoardsWorkEmpty'), 'dashboardError' => $langs->trans('BoardsWorkUnavailable'), 'dashboardNote' => $langs->trans('BoardsWorkNote'), 'dashboardLimit' => $langs->trans('BoardsWorkLimit'), 'dashboardMore' => $langs->trans('BoardsWorkMore'), 'density' => $langs->trans('BoardsDensity'), 'displayDensity' => $langs->trans('BoardsDisplayDensity'), 'compact' => $langs->trans('BoardsDensityCompact'), 'comfortable' => $langs->trans('BoardsDensityComfortable'), 'priority' => $langs->trans('Priority'), 'priority_urgent' => $langs->trans('Priority_urgent'), 'priority_high' => $langs->trans('Priority_high'), 'priority_normal' => $langs->trans('Priority_normal'), 'priority_low' => $langs->trans('Priority_low'), 'sidebarMenu' => $langs->trans('BoardsSidebarMenu'), 'sidebarClose' => $langs->trans('BoardsSidebarClose'), 'sidebarNavigation' => $langs->trans('BoardsSidebarNavigation'), 'sidebarActions' => $langs->trans('BoardsSidebarActions')], 'accountStatements' => $accountStatements, 'request' => bin2hex(random_bytes(24)), 'token' => newToken(), 'board' => GETPOSTINT('id'), 'lang' => $ar ? 'ar' : 'en', 'mode' => 'boards', 'entity' => (int)$conf->entity, 'user' => (int)$user->id, 'customer' => GETPOSTINT('customer'), 'workspaces' => InternalTaskWorkspaceService::enabled(), 'boardDelete' => InternalTaskBoardLifecycle::enabled(), 'completion' => InternalTaskCompletionService::enabled()];
    ?>
    <main id="cp-boards" dir="<?php echo $ar ? 'rtl' : 'ltr'; ?>"><header class="cb-top"><a href="boards.php" class="cb-brand">▦ China Planet</a><input type="search" id="cb-search" placeholder="<?php echo $ar ? 'بحث في اللوحات والبطاقات' : 'Search boards and cards'; ?>" aria-label="<?php echo $ar ? 'بحث' : 'Search'; ?>"><a href="boards.php?view=my"><?php echo $ar ? 'مهامي' : 'My Work'; ?></a><button id="cb-shared"><?php echo $ar ? 'مشترك معي' : 'Shared with me'; ?></button><a href="inbox.php"><?php echo $ar ? 'التنبيهات' : 'Notifications'; ?></a><button id="cb-theme" aria-label="<?php echo $ar ? 'المظهر' : 'Theme'; ?>">◐</button></header><div id="cb-content" aria-live="polite"></div><output id="cb-status" role="status"></output></main>
    <dialog id="cb-dialog"><header><h2></h2><button type="button" data-dialog-close aria-label="<?php echo $ar ? 'إغلاق' : 'Close'; ?>">×</button></header><div class="cb-dialog-body"></div></dialog>
    <dialog id="cb-card-dialog"><header><strong><?php echo $ar ? 'تفاصيل البطاقة' : 'Card details'; ?></strong><button type="button" data-card-close aria-label="<?php echo $ar ? 'إغلاق' : 'Close'; ?>">×</button></header><iframe title="<?php echo $ar ? 'تفاصيل البطاقة' : 'Card details'; ?>"></iframe></dialog>
    <script type="application/json" id="cb-config"><?php echo json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <script src="<?php echo cpo_asset('/custom/chinaplanetcustomercenter/js/kanban_pointer.js'); ?>"></script><script src="<?php echo cpo_asset('/custom/chinaplanetoperations/js/boards_live_runtime.js'); ?>"></script><script src="<?php echo cpo_asset('/custom/chinaplanetoperations/js/boards_sounds.js'); ?>"></script><script src="<?php echo cpo_asset('/custom/chinaplanetoperations/js/boards_geometry.js'); ?>"></script><script src="<?php echo cpo_asset('/custom/chinaplanetoperations/js/boards.js'); ?>"></script>
    <?php
    llxFooter();
    $db->close();
    exit;
}

// Approved Modern Boards UI Prototype
?>
<!DOCTYPE html>
<html lang="<?php echo $ar ? 'ar' : 'en'; ?>" dir="<?php echo $ar ? 'rtl' : 'ltr'; ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>China Planet OS - Boards</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    if (!window.tailwind) {
      document.write('<script src="https://www.gstatic.com/antigravity/web/dev/tailwindcss.min.js"><\/script>');
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Cairo', 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    }
    [dir="ltr"] body {
      font-family: 'Plus Jakarta Sans', 'Cairo', system-ui, -apple-system, sans-serif;
    }
    .custom-scrollbar::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
      background: rgba(0, 0, 0, 0.03);
      border-radius: 999px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
      background: rgba(100, 116, 139, 0.25);
      border-radius: 999px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
      background: rgba(100, 116, 139, 0.45);
    }
    .touch-target {
      min-height: 44px;
      min-width: 44px;
    }
    .board-card {
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .board-card:hover {
      transform: translateY(-2px);
    }
  </style>
  <script src="<?php echo cpo_asset('/custom/chinaplanetoperations/js/boards_geometry.js'); ?>"></script>
</head>
<body class="bg-slate-50 text-slate-900 h-full flex flex-col overflow-hidden select-none">

  <!-- Top Global Header -->
  <header class="bg-white border-b border-slate-200/90 px-4 py-2.5 flex items-center justify-between gap-3 shadow-xs shrink-0 z-20">
    <!-- Brand & Workspace -->
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 flex items-center justify-center text-white font-bold text-lg shadow-sm shadow-blue-500/20">
        ▦
      </div>
      <div>
        <div class="flex items-center gap-2">
          <span class="font-extrabold text-base tracking-tight text-slate-900" id="brand-title">China Planet OS</span>
          <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200" id="badge-version">v24.0 Staging</span>
        </div>
        <p class="text-xs text-slate-500 font-medium" id="sub-title"><?php echo $ar ? 'إدارة العمليات واللوحات الذكية (Boards)' : 'Operations & Intelligent Boards Management'; ?></p>
      </div>
    </div>

    <!-- Quick Navigation to Dolibarr Native -->
    <div class="hidden md:flex items-center gap-1.5 text-xs text-slate-600">
      <a href="/index.php" class="px-2.5 py-1.5 rounded-lg font-medium hover:bg-slate-100 hover:text-slate-900 transition flex items-center gap-1">
        <span>🏠</span>
        <span id="nav-erp"><?php echo $ar ? 'الرئيسية' : 'ERP Home'; ?></span>
      </a>
      <a href="boards.php?view=classic" class="px-2.5 py-1.5 rounded-lg font-medium hover:bg-slate-100 hover:text-slate-900 transition flex items-center gap-1">
        <span>📋</span>
        <span id="nav-classic"><?php echo $ar ? 'العرض التقليدي' : 'Classic View'; ?></span>
      </a>
    </div>

    <!-- Interactive Simulator & View Controls -->
    <div class="flex items-center gap-2 bg-slate-100/90 p-1 rounded-xl border border-slate-200">
      <!-- Device Selector -->
      <div class="flex items-center gap-1">
        <button onclick="setDevice('desktop')" id="btn-desktop" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-white text-slate-900 shadow-xs flex items-center gap-1.5 transition">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          <span class="hidden sm:inline" id="btn-desktop-text"><?php echo $ar ? 'كمبيوتر' : 'Desktop'; ?></span>
        </button>
        <button onclick="setDevice('tablet')" id="btn-tablet" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:text-slate-900 flex items-center gap-1.5 transition">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          <span class="hidden sm:inline" id="btn-tablet-text"><?php echo $ar ? 'لمس / آيباد' : 'Tablet / Touch'; ?></span>
        </button>
        <button onclick="setDevice('mobile')" id="btn-mobile" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:text-slate-900 flex items-center gap-1.5 transition">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          <span class="hidden sm:inline" id="btn-mobile-text"><?php echo $ar ? 'هاتف' : 'Mobile'; ?></span>
        </button>
      </div>

      <div class="h-4 w-px bg-slate-300"></div>

      <!-- Density Toggle -->
      <button onclick="toggleDensity()" id="btn-density" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-700 hover:bg-white hover:shadow-xs transition flex items-center gap-1.5">
        <span id="density-label"><?php echo $ar ? 'عرض مريح' : 'Comfortable View'; ?></span>
      </button>

      <div class="h-4 w-px bg-slate-300"></div>

      <!-- Lang Toggle -->
      <button onclick="toggleLanguage()" class="px-2.5 py-1.5 rounded-lg text-xs font-bold text-blue-700 bg-blue-50/80 hover:bg-blue-100 transition">
        <span id="lang-label"><?php echo $ar ? 'EN' : 'العربية'; ?></span>
      </button>
    </div>
  </header>

  <!-- Filter & Action Sub-Bar -->
  <div class="bg-white/80 backdrop-blur-md border-b border-slate-200 px-4 py-2 flex flex-wrap items-center justify-between gap-3 shrink-0">
    <div class="flex items-center gap-2 flex-wrap">
      <!-- Active Board Tabs -->
      <div class="flex items-center bg-slate-100 p-0.5 rounded-lg text-xs font-medium border border-slate-200">
        <button class="px-3 py-1.5 rounded-md bg-white text-slate-900 font-semibold shadow-xs" id="tab-mywork"><?php echo $ar ? 'مهامي اليومية (7)' : 'My Work (7)'; ?></button>
        <button class="px-3 py-1.5 rounded-md text-slate-600 hover:text-slate-900" id="tab-operations"><?php echo $ar ? 'عمليات الموردين' : 'Supplier Operations'; ?></button>
        <button class="px-3 py-1.5 rounded-md text-slate-600 hover:text-slate-900" id="tab-logistics"><?php echo $ar ? 'متابعة الشحنات' : 'Shipment Tracking'; ?></button>
      </div>

      <!-- Quick Search Input -->
      <div class="relative min-w-[200px] sm:min-w-[260px]">
        <input type="text" id="search-input" oninput="filterCards(this.value)" placeholder="<?php echo $ar ? 'بحث باسم العميل، المورد، أو المهمة...' : 'Search by customer, supplier or task...'; ?>" 
               class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
        <span class="absolute left-2.5 top-2 text-slate-400 text-xs">🔍</span>
      </div>
    </div>

    <!-- Quick Stats Summary Badge -->
    <div class="flex items-center gap-2 text-xs font-medium">
      <span class="px-2.5 py-1 rounded-md bg-rose-50 text-rose-700 border border-rose-200 font-bold flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
        <span id="urgent-count"><?php echo $ar ? '2 عاجل جداً' : '2 Urgent Tasks'; ?></span>
      </span>
      <span class="px-2.5 py-1 rounded-md bg-amber-50 text-amber-700 border border-amber-200 flex items-center gap-1">
        <span id="shipping-attention-count"><?php echo $ar ? '⚠️ 1 مهام شحن حرجة' : '⚠️ 1 Critical Shipment'; ?></span>
      </span>
      <span class="px-2.5 py-1 rounded-md bg-blue-50 text-blue-700 border border-blue-200">
        <span id="claims-count"><?php echo $ar ? '💰 3 مطالبات معلقة' : '💰 3 Pending Claims'; ?></span>
      </span>
    </div>
  </div>

  <!-- Main Board Canvas Container (Simulating Responsive Devices) -->
  <div id="viewport-wrapper" class="flex-1 bg-slate-100/70 p-4 overflow-hidden flex justify-center items-stretch transition-all duration-300">
    <div id="board-container" class="w-full h-full flex flex-col bg-slate-50 rounded-2xl border border-slate-200 shadow-sm overflow-hidden transition-all duration-300">
      
      <!-- Board Columns Lane -->
      <div id="lanes-track" class="flex-1 flex gap-4 p-4 overflow-x-auto overflow-y-hidden custom-scrollbar">

        <!-- Column 1: New / Backlog -->
        <div class="board-column w-80 shrink-0 bg-slate-100/90 rounded-xl p-3 flex flex-col border border-slate-200/80 max-h-full">
          <div class="flex items-center justify-between pb-2.5 mb-2 border-b border-slate-200">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
              <h2 class="font-bold text-sm text-slate-800 col-title" id="col-title-1"><?php echo $ar ? 'طلبات جديدة / واردة' : 'New / Incoming Requests'; ?></h2>
              <span class="px-1.5 py-0.5 rounded-md bg-slate-200 text-slate-600 text-xs font-semibold">2</span>
            </div>
            <button class="text-slate-400 hover:text-slate-700 p-1 rounded hover:bg-slate-200">⋯</button>
          </div>

          <div class="column-cards flex-1 overflow-y-auto space-y-3 custom-scrollbar pr-0.5">
            <!-- Card 1 -->
            <div class="board-card bg-white rounded-xl border border-slate-200/90 shadow-xs hover:shadow-md transition-all duration-200 overflow-hidden cursor-pointer" data-keywords="شركة الفهد فلاتر صناعية urgent عاجل TSK-1042">
              <!-- Full-width Priority Bar -->
              <div class="h-1.5 w-full bg-rose-500"></div>
              <div class="p-3.5 space-y-2.5">
                <div class="flex items-start justify-between gap-2">
                  <span class="font-bold text-xs text-rose-600 bg-rose-50 px-2 py-0.5 rounded border border-rose-200/60 uppercase tracking-wide"><?php echo $ar ? 'عاجل • URGENT' : 'URGENT • عاجل'; ?></span>
                  <span class="text-[11px] text-slate-400 font-mono">#TSK-1042</span>
                </div>
                <!-- Customer Name Prominent -->
                <div>
                  <span class="text-xs font-semibold text-blue-600 block"><?php echo $ar ? 'شركة الفهد للتجارة الدولية' : 'Al-Fahd Int. Trading Co.'; ?></span>
                  <h3 class="font-bold text-sm text-slate-900 leading-snug mt-0.5"><?php echo $ar ? 'مراجعة عرض أسعار توريد 500 كرتونة فلاتر صناعية' : 'Review quotation for 500 cartons industrial filters'; ?></h3>
                </div>
                <!-- M121 Supplier State Badge -->
                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                  <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200 font-medium text-[11px]"><?php echo $ar ? 'لم يبدأ التوريد (Not started)' : 'Sourcing: Not started'; ?></span>
                  <span class="text-slate-400 text-[11px]"><?php echo $ar ? 'اليوم 11:30 ص' : 'Today 11:30 AM'; ?></span>
                </div>
              </div>
            </div>

            <!-- Card 2 -->
            <div class="board-card bg-white rounded-xl border border-slate-200/90 shadow-xs hover:shadow-md transition-all duration-200 overflow-hidden cursor-pointer" data-keywords="الأندلس أقمشة كتان normal عادي TSK-1039">
              <div class="h-1.5 w-full bg-blue-500"></div>
              <div class="p-3.5 space-y-2.5">
                <div class="flex items-start justify-between gap-2">
                  <span class="font-bold text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-200/60"><?php echo $ar ? 'عادي • NORMAL' : 'NORMAL • عادي'; ?></span>
                  <span class="text-[11px] text-slate-400 font-mono">#TSK-1039</span>
                </div>
                <div>
                  <span class="text-xs font-semibold text-blue-600 block"><?php echo $ar ? 'مؤسسة الأندلس للاستيراد' : 'Al-Andalus Import Est.'; ?></span>
                  <h3 class="font-bold text-sm text-slate-900 leading-snug mt-0.5"><?php echo $ar ? 'مطابقة مواصفات عينات أقمشة الكتان' : 'Match linen fabric samples specifications'; ?></h3>
                </div>
                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                  <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200 font-medium text-[11px]"><?php echo $ar ? 'بانتظار موافقة العميل' : 'Awaiting client approval'; ?></span>
                  <span class="text-slate-400 text-[11px]"><?php echo $ar ? 'أمس' : 'Yesterday'; ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Column 2: Sourcing & Financial Obligations -->
        <div class="board-column w-80 shrink-0 bg-slate-100/90 rounded-xl p-3 flex flex-col border border-slate-200/80 max-h-full">
          <div class="flex items-center justify-between pb-2.5 mb-2 border-b border-slate-200">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
              <h2 class="font-bold text-sm text-slate-800 col-title" id="col-title-2"><?php echo $ar ? 'التوريد والالتزامات المالية' : 'Sourcing & Financial Commitments'; ?></h2>
              <span class="px-1.5 py-0.5 rounded-md bg-slate-200 text-slate-600 text-xs font-semibold">2</span>
            </div>
            <button class="text-slate-400 hover:text-slate-700 p-1 rounded hover:bg-slate-200">⋯</button>
          </div>

          <div class="column-cards flex-1 overflow-y-auto space-y-3 custom-scrollbar pr-0.5">
            <!-- Card 3 (M121 Supplier Payment Residual) -->
            <div class="board-card bg-white rounded-xl border border-slate-200/90 shadow-xs hover:shadow-md transition-all duration-200 overflow-hidden cursor-pointer ring-1 ring-amber-300/40" data-keywords="الشرق الأوسط فوشان معدات high مرتفع TSK-1025 financial">
              <div class="h-1.5 w-full bg-amber-500"></div>
              <div class="p-3.5 space-y-2.5">
                <div class="flex items-start justify-between gap-2">
                  <span class="font-bold text-xs text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-200/60 uppercase"><?php echo $ar ? 'مرتفع • HIGH' : 'HIGH • مرتفع'; ?></span>
                  <span class="text-[11px] text-slate-400 font-mono">#TSK-1025</span>
                </div>
                <div>
                  <span class="text-xs font-semibold text-indigo-600 block"><?php echo $ar ? 'مجموعة الشرق الأوسط' : 'Middle East Group'; ?></span>
                  <h3 class="font-bold text-sm text-slate-900 leading-snug mt-0.5"><?php echo $ar ? 'دفعة التوريد الثانية: مصنع فوشان للمعدات' : '2nd Sourcing Payment: Foshan Machinery'; ?></h3>
                </div>
                <!-- Supplier Financial Chip -->
                <div class="bg-slate-50 p-2 rounded-lg border border-slate-200/70 text-xs space-y-1">
                  <div class="flex justify-between text-slate-600">
                    <span><?php echo $ar ? 'إجمالي الالتزام:' : 'Total Commitment:'; ?></span>
                    <span class="font-bold text-slate-800">$45,000 USD</span>
                  </div>
                  <div class="flex justify-between text-emerald-600 font-medium">
                    <span><?php echo $ar ? 'المدفوع المسجل:' : 'Recorded Paid:'; ?></span>
                    <span>$30,000 USD</span>
                  </div>
                  <div class="flex justify-between text-rose-600 font-bold border-t border-slate-200 pt-1">
                    <span><?php echo $ar ? 'المتبقي المستحق:' : 'Outstanding Residual:'; ?></span>
                    <span>$15,000 USD</span>
                  </div>
                </div>
                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                  <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium text-[11px]"><?php echo $ar ? 'مورد موثق (Verified)' : 'Verified Supplier'; ?></span>
                  <button class="text-xs font-bold text-blue-600 hover:text-blue-800"><?php echo $ar ? 'إثبات التحويل ←' : 'Transfer Proof →'; ?></button>
                </div>
              </div>
            </div>

            <!-- Card 4 (Unavailable state test) -->
            <div class="board-card bg-white rounded-xl border border-slate-200/90 shadow-xs hover:shadow-md transition-all duration-200 overflow-hidden cursor-pointer" data-keywords="البركة غوانزو شحن normal عادي TSK-1018">
              <div class="h-1.5 w-full bg-slate-400"></div>
              <div class="p-3.5 space-y-2.5">
                <div class="flex items-start justify-between gap-2">
                  <span class="font-bold text-xs text-slate-600 bg-slate-100 px-2 py-0.5 rounded border border-slate-200"><?php echo $ar ? 'عادي • NORMAL' : 'NORMAL • عادي'; ?></span>
                  <span class="text-[11px] text-slate-400 font-mono">#TSK-1018</span>
                </div>
                <div>
                  <span class="text-xs font-semibold text-blue-600 block"><?php echo $ar ? 'شركة البركة للتوريدات' : 'Al-Baraka Supplies Co.'; ?></span>
                  <h3 class="font-bold text-sm text-slate-900 leading-snug mt-0.5"><?php echo $ar ? 'مطابقة فواتير الشحن المحلي غوانزو' : 'Reconciliation of local Guangzhou freight invoices'; ?></h3>
                </div>
                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                  <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-500 border border-slate-200 font-medium text-[11px]"><?php echo $ar ? 'غير متاح مؤقتاً (Unavailable)' : 'Temporarily Unavailable'; ?></span>
                  <span class="text-slate-400 text-[11px]"><?php echo $ar ? 'مغلق' : 'Closed'; ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Column 3: Logistics & Shipment Attention -->
        <div class="board-column w-80 shrink-0 bg-slate-100/90 rounded-xl p-3 flex flex-col border border-slate-200/80 max-h-full">
          <div class="flex items-center justify-between pb-2.5 mb-2 border-b border-slate-200">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-ping"></span>
              <h2 class="font-bold text-sm text-slate-800 col-title" id="col-title-3"><?php echo $ar ? 'الشحن والمستندات الحية' : 'Logistics & Live Documents'; ?></h2>
              <span class="px-1.5 py-0.5 rounded-md bg-rose-100 text-rose-700 text-xs font-bold">2</span>
            </div>
            <button class="text-slate-400 hover:text-slate-700 p-1 rounded hover:bg-slate-200">⋯</button>
          </div>

          <div class="column-cards flex-1 overflow-y-auto space-y-3 custom-scrollbar pr-0.5">
            <!-- Card 5 (Shipment Attention Alert) -->
            <div class="board-card bg-white rounded-xl border border-rose-300 shadow-xs hover:shadow-md transition-all duration-200 overflow-hidden cursor-pointer ring-1 ring-rose-400/30" data-keywords="التاج أجهزة كهربائية جدة container حاوية attention SHP-8802">
              <div class="h-1.5 w-full bg-rose-600"></div>
              <div class="p-3.5 space-y-2.5">
                <div class="flex items-start justify-between gap-2">
                  <span class="font-bold text-xs text-rose-700 bg-rose-100 px-2 py-0.5 rounded border border-rose-300 uppercase"><?php echo $ar ? 'انتباه للشحنة • ATTENTION' : 'ATTENTION • انتباه للشحنة'; ?></span>
                  <span class="text-[11px] text-slate-400 font-mono">#SHP-8802</span>
                </div>
                <div>
                  <span class="text-xs font-semibold text-rose-700 block"><?php echo $ar ? 'شركة التاج للأجهزة الكهربائية' : 'Al-Taj Electronics Co.'; ?></span>
                  <h3 class="font-bold text-sm text-slate-900 leading-snug mt-0.5"><?php echo $ar ? 'حاوية 40HQ - ميناء جدة الإسلامي' : '40HQ Container - Jeddah Islamic Port'; ?></h3>
                </div>
                <!-- Attention Warning Box -->
                <div class="bg-rose-50 border border-rose-200 p-2 rounded-lg text-xs space-y-1">
                  <div class="flex items-center gap-1.5 text-rose-800 font-bold">
                    <span>⚠️ <?php echo $ar ? 'فترة السماح تنتهي خلال 3 أيام' : 'Free time expires in 3 days'; ?></span>
                  </div>
                  <p class="text-rose-600 text-[11px]"><?php echo $ar ? 'مطلوب إرسال أصل البوليصة (BL) وشهادة المنشأ (COO) فوراً لتفادي الغرامات.' : 'Original BL and COO required immediately to avoid demurrage.'; ?></p>
                </div>
                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                  <span class="text-slate-500 font-medium text-[11px]">ETA: 2026-11-08</span>
                  <button class="text-xs font-bold text-blue-600 hover:text-blue-800"><?php echo $ar ? 'فتح المستندات ←' : 'Open Documents →'; ?></button>
                </div>
              </div>
            </div>

            <!-- Card 6 (Documents Integration) -->
            <div class="board-card bg-white rounded-xl border border-slate-200/90 shadow-xs hover:shadow-md transition-all duration-200 overflow-hidden cursor-pointer" data-keywords="النيل أدوات صحية السخنة CargoX SHP-8799">
              <div class="h-1.5 w-full bg-emerald-500"></div>
              <div class="p-3.5 space-y-2.5">
                <div class="flex items-start justify-between gap-2">
                  <span class="font-bold text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200"><?php echo $ar ? 'مكتمل المستندات' : 'Documents Complete'; ?></span>
                  <span class="text-[11px] text-slate-400 font-mono">#SHP-8799</span>
                </div>
                <div>
                  <span class="text-xs font-semibold text-blue-600 block"><?php echo $ar ? 'مؤسسة النيل الحديثة' : 'Modern Nile Est.'; ?></span>
                  <h3 class="font-bold text-sm text-slate-900 leading-snug mt-0.5"><?php echo $ar ? 'شحنة أدوات صحية - ميناء السخنة' : 'Sanitary Ware Shipment - Sokhna Port'; ?></h3>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-600">
                  <span class="px-2 py-0.5 bg-slate-100 rounded text-[11px]">📄 CI</span>
                  <span class="px-2 py-0.5 bg-slate-100 rounded text-[11px]">📦 PL</span>
                  <span class="px-2 py-0.5 bg-slate-100 rounded text-[11px]">🚢 BL</span>
                  <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 font-bold rounded text-[11px]">CargoX ✓</span>
                </div>
                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                  <span class="text-slate-400 text-[11px]"><?php echo $ar ? 'أبحر في: 01 نوفمبر' : 'Sailed: Nov 01'; ?></span>
                  <span class="text-emerald-600 font-bold text-xs"><?php echo $ar ? 'في الطريق' : 'In Transit'; ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Column 4: Completed / Settled -->
        <div class="board-column w-80 shrink-0 bg-slate-100/90 rounded-xl p-3 flex flex-col border border-slate-200/80 max-h-full">
          <div class="flex items-center justify-between pb-2.5 mb-2 border-b border-slate-200">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
              <h2 class="font-bold text-sm text-slate-800 col-title" id="col-title-4"><?php echo $ar ? 'منجز ومسوى مالياً' : 'Completed & Settled'; ?></h2>
              <span class="px-1.5 py-0.5 rounded-md bg-slate-200 text-slate-600 text-xs font-semibold">1</span>
            </div>
            <button class="text-slate-400 hover:text-slate-700 p-1 rounded hover:bg-slate-200">⋯</button>
          </div>

          <div class="column-cards flex-1 overflow-y-auto space-y-3 custom-scrollbar pr-0.5">
            <!-- Card 7 -->
            <div class="board-card bg-white rounded-xl border border-slate-200/90 shadow-xs opacity-90 hover:opacity-100 transition-all duration-200 overflow-hidden cursor-pointer" data-keywords="الرائد مواد بناء فحص settled TSK-0994">
              <div class="h-1.5 w-full bg-slate-300"></div>
              <div class="p-3.5 space-y-2.5">
                <div class="flex items-start justify-between gap-2">
                  <span class="font-bold text-xs text-slate-600 bg-slate-100 px-2 py-0.5 rounded border border-slate-200"><?php echo $ar ? 'منتهي • SETTLED' : 'SETTLED • منتهي'; ?></span>
                  <span class="text-[11px] text-slate-400 font-mono">#TSK-0994</span>
                </div>
                <div>
                  <span class="text-xs font-semibold text-slate-500 block"><?php echo $ar ? 'شركة الرائد لمواد البناء' : 'Al-Raed Building Materials'; ?></span>
                  <h3 class="font-bold text-sm text-slate-800 line-through leading-snug mt-0.5"><?php echo $ar ? 'تسليم شهادة الفحص الميداني والمطابقة' : 'Delivery of site inspection certificate'; ?></h3>
                </div>
                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                  <span class="text-emerald-700 font-semibold text-[11px]"><?php echo $ar ? 'تم الإيداع والتسوية بالكامل' : 'Fully deposited and settled'; ?></span>
                  <span class="text-slate-400 text-[11px]"><?php echo $ar ? 'أرشفة' : 'Archive'; ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Touch-friendly Mobile/Tablet Action Footer -->
      <div id="touch-actions-bar" class="hidden bg-white border-t border-slate-200 px-4 py-2.5 flex items-center justify-between gap-2 shrink-0">
        <button class="flex-1 py-2 rounded-xl bg-blue-600 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm active:scale-95 transition">
          <span id="touch-btn-add"><?php echo $ar ? '+ إضافة مهمة أو بطاقة جديدة' : '+ New Task or Card'; ?></span>
        </button>
        <button class="px-3 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-50 transition">
          <span id="touch-btn-filter"><?php echo $ar ? 'تصفية الفئات' : 'Filter'; ?></span>
        </button>
      </div>

    </div>
  </div>

  <script>
    let currentDensity = 'comfortable';
    let currentLang = '<?php echo $ar ? 'ar' : 'en'; ?>';
    let currentDevice = 'desktop';

    function setDevice(device) {
      currentDevice = device;
      const container = document.getElementById('board-container');
      const touchBar = document.getElementById('touch-actions-bar');
      
      // Update button styles
      ['desktop', 'tablet', 'mobile'].forEach(d => {
        const btn = document.getElementById(`btn-${d}`);
        if (d === device) {
          btn.className = "px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-white text-slate-900 shadow-xs flex items-center gap-1.5 transition";
        } else {
          btn.className = "px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:text-slate-900 flex items-center gap-1.5 transition";
        }
      });

      if (device === 'mobile') {
        container.style.maxWidth = '390px';
        container.style.boxShadow = '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)';
        touchBar.classList.remove('hidden');
      } else if (device === 'tablet') {
        container.style.maxWidth = '840px';
        container.style.boxShadow = '0 10px 15px -3px rgb(0 0 0 / 0.1)';
        touchBar.classList.remove('hidden');
      } else {
        container.style.maxWidth = '100%';
        container.style.boxShadow = 'none';
        touchBar.classList.add('hidden');
      }
    }

    function toggleDensity() {
      const cards = document.querySelectorAll('.board-card');
      const label = document.getElementById('density-label');
      
      if (currentDensity === 'comfortable') {
        currentDensity = 'compact';
        label.innerText = currentLang === 'ar' ? 'عرض مدمج (سريع)' : 'Compact View';
        cards.forEach(c => {
          c.classList.add('scale-[0.98]', 'text-xs');
          const inner = c.querySelector('div:nth-child(2)');
          if (inner) inner.classList.replace('p-3.5', 'p-2.5');
        });
      } else {
        currentDensity = 'comfortable';
        label.innerText = currentLang === 'ar' ? 'عرض مريح' : 'Comfortable View';
        cards.forEach(c => {
          c.classList.remove('scale-[0.98]', 'text-xs');
          const inner = c.querySelector('div:nth-child(2)');
          if (inner) inner.classList.replace('p-2.5', 'p-3.5');
        });
      }
    }

    function toggleLanguage() {
      const html = document.documentElement;
      const langLabel = document.getElementById('lang-label');
      
      if (currentLang === 'ar') {
        currentLang = 'en';
        html.setAttribute('dir', 'ltr');
        html.setAttribute('lang', 'en');
        langLabel.innerText = 'العربية';
        document.getElementById('sub-title').innerText = 'Operations & Intelligent Boards Management';
        document.getElementById('search-input').placeholder = 'Search by customer, supplier or task...';
        document.getElementById('urgent-count').innerText = '2 Urgent Tasks';
        document.getElementById('shipping-attention-count').innerText = '⚠️ 1 Critical Shipment';
        document.getElementById('claims-count').innerText = '💰 3 Pending Claims';
        document.getElementById('density-label').innerText = currentDensity === 'comfortable' ? 'Comfortable View' : 'Compact View';
        document.getElementById('tab-mywork').innerText = 'My Work (7)';
        document.getElementById('tab-operations').innerText = 'Supplier Operations';
        document.getElementById('tab-logistics').innerText = 'Shipment Tracking';
        document.getElementById('col-title-1').innerText = 'New / Incoming Requests';
        document.getElementById('col-title-2').innerText = 'Sourcing & Financial Commitments';
        document.getElementById('col-title-3').innerText = 'Logistics & Live Documents';
        document.getElementById('col-title-4').innerText = 'Completed & Settled';
        document.getElementById('btn-desktop-text').innerText = 'Desktop';
        document.getElementById('btn-tablet-text').innerText = 'Tablet / Touch';
        document.getElementById('btn-mobile-text').innerText = 'Mobile';
        document.getElementById('nav-erp').innerText = 'ERP Home';
        document.getElementById('nav-classic').innerText = 'Classic View';
      } else {
        currentLang = 'ar';
        html.setAttribute('dir', 'rtl');
        html.setAttribute('lang', 'ar');
        langLabel.innerText = 'EN';
        document.getElementById('sub-title').innerText = 'إدارة العمليات واللوحات الذكية (Boards)';
        document.getElementById('search-input').placeholder = 'بحث باسم العميل، المورد، أو المهمة...';
        document.getElementById('urgent-count').innerText = '2 عاجل جداً';
        document.getElementById('shipping-attention-count').innerText = '⚠️ 1 مهام شحن حرجة';
        document.getElementById('claims-count').innerText = '💰 3 مطالبات معلقة';
        document.getElementById('density-label').innerText = currentDensity === 'comfortable' ? 'عرض مريح' : 'عرض مدمج (سريع)';
        document.getElementById('tab-mywork').innerText = 'مهامي اليومية (7)';
        document.getElementById('tab-operations').innerText = 'عمليات الموردين';
        document.getElementById('tab-logistics').innerText = 'متابعة الشحنات';
        document.getElementById('col-title-1').innerText = 'طلبات جديدة / واردة';
        document.getElementById('col-title-2').innerText = 'التوريد والالتزامات المالية';
        document.getElementById('col-title-3').innerText = 'الشحن والمستندات الحية';
        document.getElementById('col-title-4').innerText = 'منجز ومسوى مالياً';
        document.getElementById('btn-desktop-text').innerText = 'كمبيوتر';
        document.getElementById('btn-tablet-text').innerText = 'لمس / آيباد';
        document.getElementById('btn-mobile-text').innerText = 'هاتف';
        document.getElementById('nav-erp').innerText = 'الرئيسية';
        document.getElementById('nav-classic').innerText = 'العرض التقليدي';
      }
    }

    function filterCards(query) {
      const term = (query || '').trim().toLowerCase();
      const cards = document.querySelectorAll('.board-card');
      cards.forEach(card => {
        const text = (card.textContent + ' ' + (card.dataset.keywords || '')).toLowerCase();
        if (!term || text.includes(term)) {
          card.style.display = '';
        } else {
          card.style.display = 'none';
        }
      });
    }
  </script>
</body>
</html>
<?php
$db->close();
?>