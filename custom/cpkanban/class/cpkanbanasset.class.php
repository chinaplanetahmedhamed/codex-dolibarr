<?php

class CpKanbanAsset
{
    const MAX_COVER_SIZE = 5242880;

    public static function coverDirectory($cardId)
    {
        return DOL_DATA_ROOT.'/cpkanban/card-'.((int) $cardId);
    }

    public static function storeCover($db, $card, $user, $upload)
    {
        global $conf;
        if (empty($card->id) || empty($card->fk_soc) || empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            return array('ok' => false, 'error' => 'لم يتم استلام صورة صالحة.');
        }
        if (!empty($upload['error'])) return array('ok' => false, 'error' => 'فشل رفع الصورة.');
        if ((int) $upload['size'] <= 0 || (int) $upload['size'] > self::MAX_COVER_SIZE) {
            return array('ok' => false, 'error' => 'يجب ألا يتجاوز حجم الصورة 5MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($upload['tmp_name']);
        $allowed = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
        if (empty($allowed[$mime])) return array('ok' => false, 'error' => 'الأنواع المسموحة: JPG وPNG وWebP فقط.');
        if (@getimagesize($upload['tmp_name']) === false) return array('ok' => false, 'error' => 'محتوى الملف ليس صورة صالحة.');

        $dir = self::coverDirectory($card->id);
        if (!is_dir($dir) && !dol_mkdir($dir)) return array('ok' => false, 'error' => 'تعذر إنشاء مجلد الصورة الآمن.');
        $stored = 'cover-'.dol_now().'-'.bin2hex(random_bytes(8)).'.'.$allowed[$mime];
        if (!move_uploaded_file($upload['tmp_name'], $dir.'/'.$stored)) return array('ok' => false, 'error' => 'تعذر حفظ الصورة.');

        $entity = isset($conf->entity) ? (int) $conf->entity : 1;
        $version = 1;
        $versionRes = $db->query("SELECT MAX(version_no) as max_version FROM ".MAIN_DB_PREFIX."cpkanban_asset WHERE entity=".$entity." AND fk_card=".(int)$card->id." AND asset_type='image'");
        if ($versionRes && ($versionRow = $db->fetch_object($versionRes))) $version = ((int)$versionRow->max_version) + 1;
        $db->begin();
        if (!$db->query("UPDATE ".MAIN_DB_PREFIX."cpkanban_asset SET is_cover=0, status=0, fk_user_modif=".(int)$user->id." WHERE entity=".$entity." AND fk_card=".(int)$card->id." AND is_cover=1 AND status=1")) {
            $db->rollback(); @unlink($dir.'/'.$stored); return array('ok' => false, 'error' => 'تعذر استبدال صورة الغلاف.');
        }
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpkanban_asset (entity,fk_soc,fk_card,fk_shipment,asset_type,original_name,stored_name,mime_type,file_size,version_no,review_status,customer_visible,is_cover,status,fk_user_creat,date_creation) VALUES (";
        $sql .= $entity.",".(int)$card->fk_soc.",".(int)$card->id.",".(empty($card->fk_shipment)?'NULL':(int)$card->fk_shipment).",'image','".$db->escape(basename($upload['name']))."','".$db->escape($stored)."','".$db->escape($mime)."',".(int)$upload['size'].",".$version.",'approved',".(empty($card->cust_visible)?0:1).",1,1,".(int)$user->id.",'".$db->idate(dol_now())."')";
        if (!$db->query($sql)) {
            $db->rollback();
            @unlink($dir.'/'.$stored);
            return array('ok' => false, 'error' => 'تعذر تسجيل صورة الغلاف.');
        }
        $db->commit();
        return array('ok' => true, 'id' => $db->last_insert_id(MAIN_DB_PREFIX.'cpkanban_asset'));
    }

    public static function syncCard($db, $card, $user)
    {
        global $conf;
        return $db->query("UPDATE ".MAIN_DB_PREFIX."cpkanban_asset SET fk_soc=".(int)$card->fk_soc.", fk_shipment=".(empty($card->fk_shipment)?'NULL':(int)$card->fk_shipment).", customer_visible=".(empty($card->cust_visible)?0:1).", fk_user_modif=".(int)$user->id." WHERE entity=".(int)$conf->entity." AND fk_card=".(int)$card->id." AND status=1");
    }
}
