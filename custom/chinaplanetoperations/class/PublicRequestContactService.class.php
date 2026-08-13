<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

class PublicRequestContactException extends RuntimeException
{
}

/** Staff-only customer contact boundary for public website intake requests. */
class PublicRequestContactService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public static function whatsappNumber($phone, $countryCode = '')
    {
        $raw = trim((string) $phone);
        if ($raw === '') return '';

        $digits = preg_replace('/\D+/', '', $raw);
        if (strpos($digits, '00') === 0) $digits = substr($digits, 2);

        $callingCodes = array(
            'AE'=>'971', 'BH'=>'973', 'CN'=>'86', 'DZ'=>'213', 'EG'=>'20', 'IQ'=>'964', 'JO'=>'962',
            'KW'=>'965', 'LB'=>'961', 'LY'=>'218', 'MA'=>'212', 'OM'=>'968', 'QA'=>'974', 'SA'=>'966',
            'SD'=>'249', 'TN'=>'216',
        );
        $countryCode = strtoupper(trim((string) $countryCode));
        $wasExplicitInternational = strpos($raw, '+') === 0 || preg_match('/^\s*00/', $raw);
        if (!$wasExplicitInternational && isset($callingCodes[$countryCode])) {
            $callingCode = $callingCodes[$countryCode];
            if (strpos($digits, '0') === 0) $digits = $callingCode.ltrim($digits, '0');
            elseif (strpos($digits, $callingCode) !== 0) $digits = $callingCode.$digits;
        }

        return preg_match('/^[1-9][0-9]{7,14}$/', $digits) ? $digits : '';
    }

    public static function whatsappUrl(array $request)
    {
        $number = self::whatsappNumber($request['phone'] ?? '', $request['country_code'] ?? '');
        if ($number === '') return '';

        $name = trim((string) ($request['contact_name'] ?? ''));
        $ref = trim((string) ($request['ref'] ?? ''));
        $locale = strtolower((string) (($request['request']['locale'] ?? 'en')));
        if (strpos($locale, 'ar') === 0) {
            $message = 'مرحبًا'.($name !== '' ? ' '.$name : '').'، نتواصل معك بخصوص طلبك لدى China Planet رقم '.$ref.'.';
        } elseif (strpos($locale, 'zh') === 0) {
            $message = ($name !== '' ? $name.'，' : '').'您好，我们就您的 China Planet 申请 '.$ref.' 与您联系。';
        } else {
            $message = 'Hello'.($name !== '' ? ' '.$name : '').', we are contacting you about your China Planet request '.$ref.'.';
        }
        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }

    public static function defaultSubject(array $request)
    {
        return 'China Planet — request '.trim((string) ($request['ref'] ?? ''));
    }

    public static function defaultBody(array $request)
    {
        $name = trim((string) ($request['contact_name'] ?? ''));
        $ref = trim((string) ($request['ref'] ?? ''));
        $locale = strtolower((string) (($request['request']['locale'] ?? 'en')));
        if (strpos($locale, 'ar') === 0) {
            return 'مرحبًا'.($name !== '' ? ' '.$name : '')."،\n\nشكرًا لتواصلك مع China Planet بخصوص طلبك رقم ".$ref.".\n\n\nمع التحية،\nفريق مبيعات China Planet";
        }
        if (strpos($locale, 'zh') === 0) {
            return ($name !== '' ? $name.'，' : '')."您好：\n\n感谢您就申请 ".$ref." 联系 China Planet。\n\n\n此致\nChina Planet 销售团队";
        }
        return 'Hello'.($name !== '' ? ' '.$name : '').",\n\nThank you for contacting China Planet about request ".$ref.".\n\n\nBest regards,\nChina Planet Sales Team";
    }

    public function sendEmail(array $request, $subject, $body, $user)
    {
        $recipient = strtolower(trim((string) ($request['email'] ?? '')));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) throw new PublicRequestContactException('public_request_email_missing');

        $from = trim((string) getDolGlobalString('MAIN_MAIL_EMAIL_FROM'));
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) throw new PublicRequestContactException('public_request_sender_not_configured');

        $subject = trim(dol_string_nohtmltag(preg_replace('/[\r\n]+/', ' ', (string) $subject), 1));
        $body = trim(dol_string_nohtmltag((string) $body, 0));
        if ($subject === '' || mb_strlen($subject) > 255) throw new PublicRequestContactException('public_request_email_subject_invalid');
        if ($body === '' || mb_strlen($body) > 20000) throw new PublicRequestContactException('public_request_email_body_invalid');

        $trackid = !empty($request['fk_propal']) ? 'pro'.((int) $request['fk_propal']) : '';
        $mail = new CMailFile($subject, $recipient, $from, $body, array(), array(), array(), '', '', 0, 0, $from, '', $trackid, '', 'standard', $from);
        if (!$mail->sendfile()) {
            dol_syslog(__METHOD__.' failed for public request '.((int) ($request['rowid'] ?? 0)).': '.$mail->error, LOG_ERR);
            throw new PublicRequestContactException('public_request_email_send_failed');
        }

        $eventId = $this->recordOutgoingEvent($request, $subject, $body, $recipient, $from, $mail->msgid, $user);
        return array('event_id'=>$eventId, 'history_recorded'=>$eventId > 0, 'trackid'=>$trackid);
    }

    private function recordOutgoingEvent(array $request, $subject, $body, $recipient, $from, $messageId, $user)
    {
        $event = new ActionComm($this->db);
        $event->type_code = 'AC_OTH_AUTO';
        $event->code = 'AC_EMAIL';
        $event->label = mb_substr('Website request '.$request['ref'].' — '.$subject, 0, 255);
        $event->note_private = $body;
        $event->datep = dol_now();
        $event->datef = $event->datep;
        $event->percentage = -1;
        $event->authorid = (int) $user->id;
        $event->userownerid = (int) $user->id;
        $event->socid = (int) ($request['fk_soc'] ?? 0);
        $event->email_msgid = (string) $messageId;
        $event->email_subject = $subject;
        $event->email_from = $from;
        $event->email_to = $recipient;
        $event->errors_to = $from;
        $event->reply_to = $from;
        if (!empty($request['fk_propal'])) {
            $event->elementtype = 'propal';
            $event->elementid = (int) $request['fk_propal'];
        } else {
            $event->elementtype = 'cpo_public_request';
            $event->elementid = (int) $request['rowid'];
        }

        $result = $event->create($user);
        if ($result <= 0) {
            dol_syslog(__METHOD__.' could not record email event for public request '.((int) $request['rowid']).': '.$event->error, LOG_ERR);
            return 0;
        }
        return (int) $result;
    }
}
