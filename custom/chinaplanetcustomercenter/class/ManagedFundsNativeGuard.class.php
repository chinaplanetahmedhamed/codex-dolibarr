<?php

/**
 * Database guard that prevents a Dolibarr deposit credit from being consumed
 * while the same source has active managed-funds reservations.
 *
 * This is intentionally conservative: staff must reverse the managed
 * reservation before applying that deposit credit directly to an invoice.
 */
class ManagedFundsNativeGuard
{
    public static function install($db)
    {
        $prefix = MAIN_DB_PREFIX;
        $condition = "EXISTS (SELECT 1 FROM ".$prefix."cpcc_customer_receipt cr"
            ." INNER JOIN ".$prefix."paiement_facture pf ON pf.fk_paiement=cr.native_source_id"
            ." INNER JOIN ".$prefix."cpcc_fund_allocation a ON a.entity=cr.entity AND a.fk_receipt=cr.rowid AND a.fk_soc=cr.fk_soc"
            ." WHERE cr.entity=NEW.entity AND cr.status='confirmed' AND cr.native_source_type='customer_payment'"
            ." AND pf.fk_facture=NEW.fk_facture_source AND a.status IN ('reserved','settled'))";
        $linked = "(NEW.fk_facture IS NOT NULL OR NEW.fk_facture_line IS NOT NULL)";
        $guards = array(
            $prefix."cpcc_m077_credit_insert" => "CREATE TRIGGER ".$prefix."cpcc_m077_credit_insert BEFORE INSERT ON ".$prefix."societe_remise_except FOR EACH ROW BEGIN IF ".$linked." AND ".$condition." THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Managed funds are reserved; reverse the reservation before using this deposit credit'; END IF; END",
            $prefix."cpcc_m077_credit_update" => "CREATE TRIGGER ".$prefix."cpcc_m077_credit_update BEFORE UPDATE ON ".$prefix."societe_remise_except FOR EACH ROW BEGIN IF ".$linked." AND (COALESCE(NEW.fk_facture,0)<>COALESCE(OLD.fk_facture,0) OR COALESCE(NEW.fk_facture_line,0)<>COALESCE(OLD.fk_facture_line,0)) AND ".$condition." THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Managed funds are reserved; reverse the reservation before using this deposit credit'; END IF; END",
        );
        foreach ($guards as $name => $sql) {
            $check = $db->query("SELECT TRIGGER_NAME FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='".$db->escape($name)."'");
            if (!$check) return false;
            $exists = method_exists($db, 'num_rows') ? $db->num_rows($check) > 0 : (bool) $db->fetch_object($check);
            if (!$exists && !$db->query($sql)) return false;
        }
        return true;
    }

    public static function remove($db)
    {
        foreach (array(MAIN_DB_PREFIX.'cpcc_m077_credit_insert',MAIN_DB_PREFIX.'cpcc_m077_credit_update') as $name) {
            if (!$db->query("DROP TRIGGER IF EXISTS ".$name)) return false;
        }
        return true;
    }
}
