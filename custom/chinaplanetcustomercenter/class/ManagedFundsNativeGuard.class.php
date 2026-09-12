<?php

/** Fail-closed lifecycle and database guard for managed customer funds. */
class ManagedFundsNativeGuard
{
    const LIFECYCLE_LOCK = 'cpcc_m077_native_guard_lifecycle';

    private static function definitions()
    {
        $prefix = MAIN_DB_PREFIX;
        $base = "EXISTS (SELECT 1 FROM ".$prefix."cpcc_customer_receipt cr"
            ." INNER JOIN ".$prefix."paiement_facture pf ON pf.fk_paiement=cr.native_source_id"
            ." INNER JOIN ".$prefix."cpcc_fund_allocation a ON a.entity=cr.entity AND a.fk_receipt=cr.rowid AND a.fk_soc=cr.fk_soc"
            ." WHERE cr.entity=NEW.entity AND cr.status='confirmed' AND cr.native_source_type='customer_payment' AND %s"
            ." AND a.status IN ('reserved','settled'))";
        $linked = "(NEW.fk_facture IS NOT NULL OR NEW.fk_facture_line IS NOT NULL)";
        $changed = "(COALESCE(NEW.fk_facture,0)<>COALESCE(OLD.fk_facture,0)"
            ." OR COALESCE(NEW.fk_facture_line,0)<>COALESCE(OLD.fk_facture_line,0)"
            ." OR COALESCE(NEW.fk_facture_source,0)<>COALESCE(OLD.fk_facture_source,0)"
            ." OR COALESCE(NEW.amount_ht,0)<>COALESCE(OLD.amount_ht,0)"
            ." OR COALESCE(NEW.amount_tva,0)<>COALESCE(OLD.amount_tva,0)"
            ." OR COALESCE(NEW.amount_ttc,0)<>COALESCE(OLD.amount_ttc,0)"
            ." OR COALESCE(NEW.multicurrency_amount_ht,0)<>COALESCE(OLD.multicurrency_amount_ht,0)"
            ." OR COALESCE(NEW.multicurrency_amount_tva,0)<>COALESCE(OLD.multicurrency_amount_tva,0)"
            ." OR COALESCE(NEW.multicurrency_amount_ttc,0)<>COALESCE(OLD.multicurrency_amount_ttc,0)"
            ." OR COALESCE(NEW.multicurrency_code,'')<>COALESCE(OLD.multicurrency_code,''))";
        $message = "Managed funds are active; correct or release the managed allocation before using this deposit credit";
        $guidedTarget="EXISTS (SELECT 1 FROM ".$prefix."cpcc_guided_receipt gt WHERE gt.entity=NEW.entity AND gt.fk_invoice=COALESCE(NEW.fk_facture,(SELECT fd2.fk_facture FROM ".$prefix."facturedet fd2 WHERE fd2.rowid=NEW.fk_facture_line)) AND gt.status IN ('invoice_ready','payment_preparing','bank_posted','confirmed'))";
        $guidedOldTarget="EXISTS (SELECT 1 FROM ".$prefix."cpcc_guided_receipt gt WHERE gt.entity=NEW.entity AND gt.fk_invoice=COALESCE(OLD.fk_facture,(SELECT fd2.fk_facture FROM ".$prefix."facturedet fd2 WHERE fd2.rowid=OLD.fk_facture_line)) AND gt.status IN ('invoice_ready','payment_preparing','bank_posted','confirmed'))";
        $insertBody="BEGIN IF ".$linked." AND (".sprintf($base,"pf.fk_facture=NEW.fk_facture_source")." OR ".$guidedTarget.") THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='".$message."'; END IF; END";
        $updateBody="BEGIN IF ".$linked." AND ".$changed." AND (".sprintf($base,"pf.fk_facture IN (NEW.fk_facture_source,OLD.fk_facture_source)")." OR ".$guidedTarget." OR ".$guidedOldTarget.") THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='".$message."'; END IF; END";
        $guidedMessage="Guided deposit receipt is protected; correct it from Managed Customer Funds";
        $guidedInsert="EXISTS (SELECT 1 FROM ".$prefix."cpcc_guided_receipt g INNER JOIN ".$prefix."facture f ON f.rowid=g.fk_invoice AND f.entity=g.entity WHERE g.fk_invoice=NEW.fk_facture AND g.status IN ('invoice_ready','payment_preparing','bank_posted','confirmed') AND COALESCE(@cpcc_guided_receipt_token,'')<>g.request_token)";
        $guidedUpdate="EXISTS (SELECT 1 FROM ".$prefix."cpcc_guided_receipt g INNER JOIN ".$prefix."facture f ON f.rowid=g.fk_invoice AND f.entity=g.entity WHERE g.fk_invoice IN (NEW.fk_facture,OLD.fk_facture) AND g.status IN ('invoice_ready','payment_preparing','bank_posted','confirmed') AND COALESCE(@cpcc_guided_receipt_token,'')<>g.request_token)";
        $paymentInsertBody="BEGIN IF ".$guidedInsert." THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='".$guidedMessage."'; END IF; END";
        $paymentUpdateBody="BEGIN IF ".$guidedUpdate." THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='".$guidedMessage."'; END IF; END";
        return array(
            $prefix.'cpcc_m077_credit_insert' => array(
                'event'=>'INSERT',
                'table'=>$prefix.'societe_remise_except',
                'body'=>$insertBody,
                'sql'=>"CREATE TRIGGER ".$prefix."cpcc_m077_credit_insert BEFORE INSERT ON ".$prefix."societe_remise_except FOR EACH ROW ".$insertBody,
            ),
            $prefix.'cpcc_m077_credit_update' => array(
                'event'=>'UPDATE',
                'table'=>$prefix.'societe_remise_except',
                'body'=>$updateBody,
                'sql'=>"CREATE TRIGGER ".$prefix."cpcc_m077_credit_update BEFORE UPDATE ON ".$prefix."societe_remise_except FOR EACH ROW ".$updateBody,
            ),
            $prefix.'cpcc_m077_guided_payment_insert' => array(
                'event'=>'INSERT',
                'table'=>$prefix.'paiement_facture',
                'body'=>$paymentInsertBody,
                'sql'=>"CREATE TRIGGER ".$prefix."cpcc_m077_guided_payment_insert BEFORE INSERT ON ".$prefix."paiement_facture FOR EACH ROW ".$paymentInsertBody,
            ),
            $prefix.'cpcc_m077_guided_payment_update' => array(
                'event'=>'UPDATE',
                'table'=>$prefix.'paiement_facture',
                'body'=>$paymentUpdateBody,
                'sql'=>"CREATE TRIGGER ".$prefix."cpcc_m077_guided_payment_update BEFORE UPDATE ON ".$prefix."paiement_facture FOR EACH ROW ".$paymentUpdateBody,
            ),
        );
    }

    public static function names(){return array_keys(self::definitions());}

    private static function normalized($sql)
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', str_replace('`', '', (string)$sql))));
    }

    public static function acquireLifecycle($db)
    {
        $res=$db->query("SELECT GET_LOCK('".self::LIFECYCLE_LOCK."',10) acquired");
        $row=$res?$db->fetch_object($res):null;
        return $row && (int)$row->acquired===1;
    }

    public static function releaseLifecycle($db)
    {
        return (bool)$db->query("SELECT RELEASE_LOCK('".self::LIFECYCLE_LOCK."')");
    }

    public static function verify($db)
    {
        foreach(self::definitions() as $name=>$definition){
            $sql="SELECT ACTION_TIMING,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_STATEMENT FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='".$db->escape($name)."'";
            $res=$db->query($sql);$row=$res?$db->fetch_object($res):null;
            if(!$row||strtoupper((string)$row->ACTION_TIMING)!=='BEFORE'||strtoupper((string)$row->EVENT_MANIPULATION)!==$definition['event']||(string)$row->EVENT_OBJECT_TABLE!==$definition['table']) return false;
            if(self::normalized($row->ACTION_STATEMENT)!==self::normalized($definition['body'])) return false;
        }
        return true;
    }

    public static function install($db, $lifecycleHeld = false)
    {
        $ownsLock=!$lifecycleHeld;
        if($ownsLock&&!self::acquireLifecycle($db)) return false;
        try{
            foreach(self::definitions() as $name=>$definition){
                $res=$db->query("SELECT TRIGGER_NAME FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='".$db->escape($name)."'");
                if(!$res) return false;
                if(!$db->fetch_object($res)&&!$db->query($definition['sql'])) return false;
            }
            return self::verify($db);
        }finally{if($ownsLock) self::releaseLifecycle($db);}
    }

    public static function repair($db)
    {
        if(!self::acquireLifecycle($db)) return false;
        try{
            $active=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE status IN ('reserved','settled') LIMIT 1");
            if(!$active||$db->fetch_object($active)) return false;
            $guided=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpcc_guided_receipt LIMIT 1");
            if(!$guided||$db->fetch_object($guided)) return false;
            foreach(array_keys(self::definitions()) as $name) if(!$db->query("DROP TRIGGER IF EXISTS ".$name)) return false;
            foreach(self::definitions() as $definition) if(!$db->query($definition['sql'])) return false;
            return self::verify($db);
        }finally{self::releaseLifecycle($db);}
    }

    public static function remove($db)
    {
        if(!self::acquireLifecycle($db)) return false;
        try{
            // Fail closed before the first DROP if the installed protection set
            // is missing or differs from the reviewed definitions.
            if(!self::verify($db)) return false;
            $active=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE status IN ('reserved','settled') LIMIT 1");
            if(!$active||$db->fetch_object($active)) return false;
            $guided=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpcc_guided_receipt LIMIT 1");
            if(!$guided||$db->fetch_object($guided)) return false;
            foreach(array_keys(self::definitions()) as $name) if(!$db->query("DROP TRIGGER IF EXISTS ".$name)) return false;
            return !self::verify($db);
        }finally{self::releaseLifecycle($db);}
    }
}
