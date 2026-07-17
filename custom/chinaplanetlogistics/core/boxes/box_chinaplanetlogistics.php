<?php
/**
 * China Planet Logistics - Dashboard Widget (Box)
 * Shows container status summary on the Dolibarr main dashboard.
 * Fixed: Filters by fk_soc for external users (Dolibarr v23 compatible)
 */
include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
require_once __DIR__.'/../../lib/chinaplanetlogistics.lib.php';

class box_chinaplanetlogistics extends ModeleBoxes
{
    public $boxcode  = "chinaplanetlogistics";
    public $boximg   = "generic";
    public $boxlabel = "China Planet Logistics";
    public $depends  = array("chinaplanetlogistics");
    public $info_box_head     = array();
    public $info_box_contents = array();

    public function __construct($db, $param = '')
    {
        $this->db = $db;
    }

    /**
     * Returns a colored badge HTML based on days remaining.
     */
    private function colorBadge($days, $overrideLabel = null)
    {
        if ($days === null || $days === '') {
            return '<span style="color:#aaa;">-</span>';
        }
        $d = (int)$days;
        if ($d < 0)       $color = '#2c3e50';
        elseif ($d <= 3)  $color = '#c0392b';
        elseif ($d <= 7)  $color = '#e74c3c';
        elseif ($d <= 10) $color = '#e67e22';
        elseif ($d <= 14) $color = '#f39c12';
        else              $color = '#27ae60';
        $label = ($overrideLabel !== null) ? $overrideLabel : ($d < 0 ? 'Expired' : $d.'d');
        return '<span style="background:'.$color.';color:#fff;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:bold;">'.$label.'</span>';
    }

    /**
     * Detect if current user is external (Dolibarr standard)
     * In Dolibarr: $user->socid > 0 = external/portal user
     * Returns the socid if external, 0 if internal/admin
     */
    private function getExternalSocid()
    {
        global $user;
        
        // Admin always sees all data
        if ($user->admin) {
            return 0;
        }
        
        // Dolibarr standard: socid > 0 = external user
        return (int) $user->socid;
    }

    public function loadBox($max = 8)
    {
        global $user, $langs;
        
        $prefix = MAIN_DB_PREFIX;
        
        // Get socid for external user filtering
        $socid = $this->getExternalSocid();
        $where_soc = ($socid > 0) ? " AND fk_soc = " . (int)$socid : "";
        
        // ── 1. Count by status ──────────────────────────────────────────────────
        $sql_counts = "SELECT fk_statut, COUNT(*) as cnt
                       FROM {$prefix}chinaplanetlogistics
                       WHERE fk_statut IN (".chinaplanetlogistics_status_sql_list('active').")".$where_soc."
                       GROUP BY fk_statut";
        $res = $this->db->query($sql_counts);
        $status_counts = array();
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $status_counts[(int)$obj->fk_statut] = (int)$obj->cnt;
            }
        }
        $total_active  = array_sum($status_counts);
        $on_vessel     = isset($status_counts[2]) ? $status_counts[2] : 0;
        $arrived_port  = isset($status_counts[3]) ? $status_counts[3] : 0;
        $under_customs = isset($status_counts[4]) ? $status_counts[4] : 0;

        // ── 2. ETA urgent: arriving within 7 days ──────────────────────────────
        $sql_urgent = "SELECT COUNT(*) as cnt
                       FROM {$prefix}chinaplanetlogistics
                       WHERE fk_statut IN (".chinaplanetlogistics_status_sql_list('active').")".$where_soc."
                         AND date_eta IS NOT NULL
                         AND date_eta >= CURDATE()
                         AND date_eta <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $res2 = $this->db->query($sql_urgent);
        $urgent_eta = 0;
        if ($res2 && ($obj2 = $this->db->fetch_object($res2))) {
            $urgent_eta = (int)$obj2->cnt;
        }

        // ── 3. Free time critical: freetime_end within 5 days ──────────────────
        $sql_ft = "SELECT COUNT(*) as cnt
                   FROM {$prefix}chinaplanetlogistics
                   WHERE fk_statut IN (".chinaplanetlogistics_status_sql_list('active').")".$where_soc."
                     AND freetime_end IS NOT NULL
                     AND freetime_end >= CURDATE()
                     AND freetime_end <= DATE_ADD(CURDATE(), INTERVAL 5 DAY)";
        $res3 = $this->db->query($sql_ft);
        $ft_critical = 0;
        if ($res3 && ($obj3 = $this->db->fetch_object($res3))) {
            $ft_critical = (int)$obj3->cnt;
        }

        // ── 4. Free time overdue ────────────────────────────────────────────────
        $sql_overdue = "SELECT COUNT(*) as cnt
                        FROM {$prefix}chinaplanetlogistics
                        WHERE fk_statut IN (".chinaplanetlogistics_status_sql_list('active').")".$where_soc."
                          AND freetime_end IS NOT NULL
                          AND freetime_end < CURDATE()";
        $res4 = $this->db->query($sql_overdue);
        $ft_overdue = 0;
        if ($res4 && ($obj4 = $this->db->fetch_object($res4))) {
            $ft_overdue = (int)$obj4->cnt;
        }

        // ── 5. Shipment list (active, sorted by ETA) ───────────────────────────
        $sql_list = "SELECT rowid, ref, container_no, bl_no, shipping_line,
                            date_eta, freetime_end, fk_statut,
                            DATEDIFF(date_eta, CURDATE())      AS eta_days_left,
                            DATEDIFF(freetime_end, CURDATE())  AS ft_days_left
                     FROM {$prefix}chinaplanetlogistics
                     WHERE fk_statut IN (".chinaplanetlogistics_status_sql_list('active').")".$where_soc."
                     ORDER BY date_eta ASC
                     LIMIT ".(int)$max;
        $res5 = $this->db->query($sql_list);
        $shipments = array();
        if ($res5) {
            while ($obj5 = $this->db->fetch_object($res5)) {
                $shipments[] = $obj5;
            }
        }

        // ── Build box head ──────────────────────────────────────────────────────
        $this->info_box_head = array(
            'text'  => '🚢 China Planet Logistics — Containers',
            'limit' => 0,
        );

        // ── Summary cards ───────────────────────────────────────────────────────
        $summary_html  = '<div style="display:flex;flex-wrap:wrap;gap:8px;padding:8px 0;">';
        $summary_html .= '<div style="flex:1;min-width:100px;background:#2c3e50;color:#fff;border-radius:8px;padding:10px;text-align:center;"><div style="font-size:22px;font-weight:bold;">'.$total_active.'</div><div style="font-size:11px;margin-top:2px;">Total Active</div></div>';
        $summary_html .= '<div style="flex:1;min-width:100px;background:#2980b9;color:#fff;border-radius:8px;padding:10px;text-align:center;"><div style="font-size:22px;font-weight:bold;">'.$on_vessel.'</div><div style="font-size:11px;margin-top:2px;">On Vessel 🚢</div></div>';
        $summary_html .= '<div style="flex:1;min-width:100px;background:#27ae60;color:#fff;border-radius:8px;padding:10px;text-align:center;"><div style="font-size:22px;font-weight:bold;">'.$arrived_port.'</div><div style="font-size:11px;margin-top:2px;">Arrived Port ⚓</div></div>';
        $summary_html .= '<div style="flex:1;min-width:100px;background:#8e44ad;color:#fff;border-radius:8px;padding:10px;text-align:center;"><div style="font-size:22px;font-weight:bold;">'.$under_customs.'</div><div style="font-size:11px;margin-top:2px;">Customs 📋</div></div>';
        if ($urgent_eta > 0) {
            $summary_html .= '<div style="flex:1;min-width:100px;background:#e74c3c;color:#fff;border-radius:8px;padding:10px;text-align:center;"><div style="font-size:22px;font-weight:bold;">'.$urgent_eta.'</div><div style="font-size:11px;margin-top:2px;">⚠️ ETA &lt;7d</div></div>';
        }
        if ($ft_critical > 0 || $ft_overdue > 0) {
            $ft_total = $ft_critical + $ft_overdue;
            $summary_html .= '<div style="flex:1;min-width:100px;background:#c0392b;color:#fff;border-radius:8px;padding:10px;text-align:center;"><div style="font-size:22px;font-weight:bold;">'.$ft_total.'</div><div style="font-size:11px;margin-top:2px;">🔴 FT Critical</div></div>';
        }
        $summary_html .= '</div>';

        $this->info_box_contents[] = array(
            array('td' => 'colspan="6"', 'text' => $summary_html, 'asis' => 1)
        );

        // ── Table header ───────────────────────────────────────────────────────
        $this->info_box_contents[] = array(
            array('td' => 'class="liste_titre"',                            'text' => 'Container No'),
            array('td' => 'class="liste_titre"',                            'text' => 'BL No'),
            array('td' => 'class="liste_titre"',                            'text' => 'Line'),
            array('td' => 'class="liste_titre"',                            'text' => 'ETA'),
            array('td' => 'class="liste_titre" style="text-align:center;"', 'text' => 'Days Left'),
            array('td' => 'class="liste_titre" style="text-align:center;"', 'text' => 'Free Time'),
        );

        // ── Shipment rows ──────────────────────────────────────────────────────
        if (!empty($shipments)) {
            foreach ($shipments as $s) {
                $eta_days = ($s->date_eta !== null) ? $s->eta_days_left : null;
                $ft_days  = ($s->freetime_end !== null) ? $s->ft_days_left : null;
                $eta_label = ($eta_days !== null && (int)$eta_days < 0) ? 'Arrived' : null;
                $eta_badge = $this->colorBadge($eta_days, $eta_label);
                $ft_badge  = $this->colorBadge($ft_days);
                $link        = DOL_URL_ROOT.'/custom/chinaplanetlogistics/card.php?id='.(int)$s->rowid;
                $eta_display = $s->date_eta ? dol_print_date($this->db->jdate($s->date_eta), 'day') : '-';
                $this->info_box_contents[] = array(
                    array('td' => '',                                        'text' => '<a href="'.$link.'" style="font-weight:bold;">'.dol_escape_htmltag($s->container_no).'</a>', 'asis' => 1),
                    array('td' => 'style="color:#666;font-size:12px;"',      'text' => dol_escape_htmltag($s->bl_no)),
                    array('td' => 'style="color:#666;font-size:12px;"',      'text' => dol_escape_htmltag($s->shipping_line)),
                    array('td' => 'style="font-size:12px;"',                 'text' => $eta_display),
                    array('td' => 'style="text-align:center;"',              'text' => $eta_badge, 'asis' => 1),
                    array('td' => 'style="text-align:center;"',              'text' => $ft_badge,  'asis' => 1),
                );
            }
        } else {
            $this->info_box_contents[] = array(
                array('td' => 'colspan="6" class="opacitymedium" style="text-align:center;padding:16px;"',
                      'text' => 'No active shipments.',
                      'asis' => 1)
            );
        }

        // ── Footer ─────────────────────────────────────────────────────────────
        $this->info_box_contents[] = array(
            array(
                'td'   => 'colspan="6" style="text-align:right;padding:6px;"',
                'text' => '<a href="'.DOL_URL_ROOT.'/custom/chinaplanetlogistics/list.php">View All Shipments →</a>',
                'asis' => 1,
            )
        );
    }

    public function showBox($head = null, $contents = null, $nooutput = 0)
    {
        return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
    }
}
?>
