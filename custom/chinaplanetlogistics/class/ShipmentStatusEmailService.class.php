<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once __DIR__.'/../lib/shipment_customer.lib.php';

class ShipmentStatusEmailException extends RuntimeException
{
}

/** Customer-safe, post-commit shipment status email boundary. */
class ShipmentStatusEmailService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function recipientForShipment($shipment)
    {
        global $conf;

        if (empty($shipment->id) || empty($shipment->fk_soc)) return array();
        $entity = !empty($shipment->entity) ? (int) $shipment->entity : (int) $conf->entity;
        if ($entity !== (int) $conf->entity) return array();

        $sql = "SELECT s.nom,s.email,s.default_lang FROM ".MAIN_DB_PREFIX."societe s";
        $sql .= " WHERE s.rowid=".(int) $shipment->fk_soc." AND s.entity=".$entity." AND s.status=1";
        $resql = $this->db->query($sql);
        $customer = $resql ? $this->db->fetch_object($resql) : null;
        if (!$customer) return array();

        $recipient = strtolower(trim((string) $customer->email));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) return array();
        return array(
            'email'=>$recipient,
            'name'=>trim((string) $customer->nom),
            'language'=>$this->normalizeLanguage($customer->default_lang),
        );
    }

    public function send($shipment, $statusFrom, $statusTo, $publicReason, $user)
    {
        $recipient = $this->recipientForShipment($shipment);
        if (empty($recipient['email'])) throw new ShipmentStatusEmailException('CPLStatusEmailRecipientMissing');

        $from = trim((string) getDolGlobalString('MAIN_MAIL_EMAIL_FROM'));
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) throw new ShipmentStatusEmailException('CPLStatusEmailSenderMissing');

        $message = $this->message($shipment, $statusFrom, $statusTo, $publicReason, $recipient);
        $mail = new CMailFile($message['subject'], $recipient['email'], $from, $message['body'], array(), array(), array(), '', '', 0, 0, $from, '', '', '', 'standard', $from);
        if (!$mail->sendfile()) {
            dol_syslog(__METHOD__.' failed for shipment '.((int) $shipment->id).': '.$mail->error, LOG_ERR);
            throw new ShipmentStatusEmailException('CPLStatusEmailSendFailed');
        }

        $eventId = $this->recordOutgoingEvent($shipment, $message, $recipient['email'], $from, $mail->msgid, $user);
        return array('recipient'=>$recipient['email'], 'event_id'=>$eventId, 'history_recorded'=>$eventId > 0);
    }

    public function resendLatest($shipment, $user)
    {
        global $conf;

        if (empty($shipment->id) || empty($shipment->fk_soc) || (int) $shipment->entity !== (int) $conf->entity) {
            throw new ShipmentStatusEmailException('CPLStatusEmailNoPublishedStatus');
        }
        $sql = "SELECT status_from,status_to,reason_public FROM ".MAIN_DB_PREFIX."cpl_shipment_event";
        $sql .= " WHERE entity=".(int) $shipment->entity." AND fk_shipment=".(int) $shipment->id." AND fk_soc=".(int) $shipment->fk_soc;
        $sql .= " AND event_type='status_changed' AND customer_visible=1 ORDER BY rowid DESC LIMIT 1";
        $resql = $this->db->query($sql);
        $event = $resql ? $this->db->fetch_object($resql) : null;
        if (!$event || $event->status_from === null || $event->status_to === null) {
            throw new ShipmentStatusEmailException('CPLStatusEmailNoPublishedStatus');
        }
        return $this->send($shipment, (int) $event->status_from, (int) $event->status_to, (string) $event->reason_public, $user);
    }

    public function message($shipment, $statusFrom, $statusTo, $publicReason, array $recipient)
    {
        $language = $this->normalizeLanguage(isset($recipient['language']) ? $recipient['language'] : 'en_US');
        $customerLangs = new Translate('', $GLOBALS['conf']);
        $customerLangs->setDefaultLang($language);
        $customerLangs->loadLangs(array('main', 'chinaplanetlogistics@chinaplanetlogistics'));

        $name = trim(dol_string_nohtmltag(cpl_text_limit(isset($recipient['name']) ? $recipient['name'] : '', 255), 0));
        $ref = $this->headerText($shipment->ref, 30);
        $fromLabel = cpl_customer_status_label($customerLangs, $statusFrom);
        $toLabel = cpl_customer_status_label($customerLangs, $statusTo);
        $reason = trim(dol_string_nohtmltag(cpl_text_limit($publicReason, 1000), 0));
        $eta = !empty($shipment->date_eta) ? dol_print_date($shipment->date_eta, 'day') : '';
        $link = DOL_MAIN_URL_ROOT.'/custom/chinaplanetlogistics/card.php?id='.(int) $shipment->id;

        if (strpos(strtolower($language), 'ar') === 0) {
            $subject = 'تحديث حالة الشحنة '.$ref.' — '.$toLabel;
            $body = 'مرحبًا'.($name !== '' ? ' '.$name : '')."،\n\nتم تحديث حالة شحنتكم لدى China Planet.\n\nرقم الشحنة: ".$ref."\nالحالة السابقة: ".$fromLabel."\nالحالة الجديدة: ".$toLabel;
            if ($eta !== '') $body .= "\nموعد الوصول المتوقع: ".$eta;
            if ($reason !== '') $body .= "\nملاحظة: ".$reason;
            $body .= "\n\nمتابعة الشحنة: ".$link."\n\nيمكنكم الرد على هذا البريد إذا احتجتم إلى المساعدة.\n\nمع التحية،\nفريق China Planet";
        } elseif (strpos(strtolower($language), 'zh') === 0) {
            $subject = '货运状态更新 '.$ref.' — '.$toLabel;
            $body = ($name !== '' ? $name.'，' : '')."您好：\n\n您的 China Planet 货运状态已更新。\n\n货运编号：".$ref."\n原状态：".$fromLabel."\n新状态：".$toLabel;
            if ($eta !== '') $body .= "\n预计到达日期：".$eta;
            if ($reason !== '') $body .= "\n备注：".$reason;
            $body .= "\n\n查看货运：".$link."\n\n如需帮助，请直接回复此邮件。\n\nChina Planet 团队";
        } else {
            $subject = 'Shipment status update '.$ref.' — '.$toLabel;
            $body = 'Hello'.($name !== '' ? ' '.$name : '').",\n\nThe status of your China Planet shipment has been updated.\n\nShipment reference: ".$ref."\nPrevious status: ".$fromLabel."\nNew status: ".$toLabel;
            if ($eta !== '') $body .= "\nExpected arrival: ".$eta;
            if ($reason !== '') $body .= "\nNote: ".$reason;
            $body .= "\n\nTrack your shipment: ".$link."\n\nPlease reply to this email if you need assistance.\n\nBest regards,\nChina Planet Team";
        }

        return array('subject'=>$this->headerText($subject, 255), 'body'=>$body, 'language'=>$language);
    }

    private function normalizeLanguage($language)
    {
        $language = strtolower(trim((string) $language));
        if (strpos($language, 'ar') === 0) return 'ar_SA';
        if (strpos($language, 'zh') === 0) return 'zh_CN';
        return 'en_US';
    }

    private function headerText($value, $limit)
    {
        $value = preg_replace('/[\r\n]+/', ' ', (string) $value);
        return trim(dol_string_nohtmltag(cpl_text_limit($value, $limit), 1));
    }

    private function recordOutgoingEvent($shipment, array $message, $recipient, $from, $messageId, $user)
    {
        $event = new ActionComm($this->db);
        $event->type_code = 'AC_OTH_AUTO';
        $event->code = 'AC_EMAIL';
        $event->label = cpl_text_limit('Shipment '.$shipment->ref.' — '.$message['subject'], 255);
        $event->note_private = $message['body'];
        $event->datep = dol_now();
        $event->datef = $event->datep;
        $event->percentage = -1;
        $event->authorid = (int) $user->id;
        $event->userownerid = (int) $user->id;
        $event->socid = (int) $shipment->fk_soc;
        $event->email_msgid = (string) $messageId;
        $event->email_subject = $message['subject'];
        $event->email_from = $from;
        $event->email_to = $recipient;
        $event->errors_to = $from;
        $event->reply_to = $from;
        $event->elementtype = 'chinaplanetlogistics';
        $event->elementid = (int) $shipment->id;

        $result = $event->create($user);
        if ($result <= 0) {
            dol_syslog(__METHOD__.' could not record email event for shipment '.((int) $shipment->id).': '.$event->error, LOG_ERR);
            return 0;
        }
        return (int) $result;
    }
}
