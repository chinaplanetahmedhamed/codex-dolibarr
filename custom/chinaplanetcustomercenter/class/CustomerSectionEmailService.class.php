<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once __DIR__.'/../lib/financial.lib.php';
require_once __DIR__.'/../lib/export.lib.php';
require_once __DIR__.'/../lib/customer_documents.lib.php';

class CustomerSectionEmailException extends RuntimeException
{
}

/** Server-owned recipient and customer-safe section summaries with Agenda history. */
class CustomerSectionEmailService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public static function allowedSections()
    {
        return array('financial', 'invoice', 'project', 'shipment');
    }

    public function preview($section, $socid, $contextId = 0)
    {
        global $conf;
        $section = (string) $section;
        $socid = (int) $socid;
        $contextId = (int) $contextId;
        if (!in_array($section, self::allowedSections(), true) || $socid <= 0) throw new CustomerSectionEmailException('Invalid email section.');

        $recipient = $this->recipient($socid);
        if (empty($recipient['email'])) throw new CustomerSectionEmailException('This customer has no valid company email address.');
        $currencyFallback = cpcc_financial_currency(!empty($conf->currency) ? $conf->currency : 'USD');
        if ($section === 'financial') $message = $this->financialMessage($socid, $currencyFallback, $recipient);
        elseif ($section === 'invoice') $message = $this->invoiceMessage($socid, $contextId, $currencyFallback, $recipient);
        elseif ($section === 'project') $message = $this->projectMessage($socid, $contextId, $currencyFallback, $recipient);
        else $message = $this->shipmentMessage($socid, $contextId, $recipient);
        $message['recipient'] = $recipient;
        return $message;
    }

    public function send($section, $socid, $contextId, $user)
    {
        if (empty($user->id) || !empty($user->socid)) throw new CustomerSectionEmailException('Staff access required.');
        $message = $this->preview($section, $socid, $contextId);
        $from = trim((string) getDolGlobalString('MAIN_MAIL_EMAIL_FROM'));
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) throw new CustomerSectionEmailException('Customer email sender is not configured.');
        $recipient = $message['recipient']['email'];
        $mail = new CMailFile($message['subject'], $recipient, $from, $message['body_html'], array(), array(), array(), '', '', 0, 1, $from, '', $message['trackid'], '', 'standard', $from);
        if (!$mail->sendfile()) {
            dol_syslog(__METHOD__.' failed for '.$section.' customer '.(int)$socid.': '.$mail->error, LOG_ERR);
            throw new CustomerSectionEmailException('Dolibarr could not send the customer email.');
        }
        $eventId = $this->recordEvent($message, $socid, $recipient, $from, $mail->msgid, $user);
        return array('recipient'=>$recipient, 'event_id'=>$eventId, 'history_recorded'=>$eventId > 0);
    }

    private function recipient($socid)
    {
        global $conf;
        $sql = "SELECT nom,email,default_lang FROM ".MAIN_DB_PREFIX."societe WHERE rowid=".(int)$socid." AND entity=".(int)$conf->entity." AND status=1 AND client IN (1,3)";
        $resql = $this->db->query($sql); $row = $resql ? $this->db->fetch_object($resql) : null;
        if (!$row) return array();
        $email = strtolower(trim((string)$row->email));
        return array('name'=>trim((string)$row->nom), 'email'=>filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '', 'language'=>$this->language($row->default_lang));
    }

    private function financialMessage($socid, $currencyFallback, array $recipient)
    {
        $invoices = cpcc_financial_customer_invoices($this->db, $socid, $currencyFallback);
        $totals = cpcc_financial_invoice_totals($invoices);
        $transactions = cpcc_financial_statement($this->db, $socid, $currencyFallback);
        $statementUrl = $this->portalUrl('/custom/chinaplanetcustomercenter/balance.php');
        $body = $this->greeting($recipient)."\n\n".$this->label($recipient, 'كشف الحساب التفصيلي', 'Detailed financial statement')."\n";
        $summaryHtml = '';
        foreach ($totals as $currency=>$values) {
            $body .= "\n".$currency."\n";
            $body .= $this->label($recipient, 'إجمالي الفواتير', 'Total invoiced').': '.$this->money($values['total']).' '.$currency."\n";
            $body .= $this->label($recipient, 'المدفوع أو الممول', 'Paid or funded').': '.$this->money($values['paid']).' '.$currency."\n";
            $body .= $this->label($recipient, 'المتبقي', 'Outstanding').': '.$this->money($values['remaining']).' '.$currency."\n";
            $summaryHtml .= $this->currencySummaryHtml($recipient, $currency, $values);
        }
        if (empty($totals)) {
            $empty = $this->label($recipient, 'لا توجد فواتير معتمدة.', 'No validated invoices.');
            $body .= "\n".$empty."\n";
            $summaryHtml = $this->emptyHtml($empty);
        }
        $body .= "\n".$this->label($recipient, 'الفواتير', 'Invoices')."\n";
        $invoiceRowsHtml = '';
        foreach ($invoices as $invoice) {
            $body .= $invoice['ref'].' | '.$this->money($invoice['total']).' '.$invoice['currency'].' | '.$this->label($recipient,'الممول','Funded').' '.$this->money($invoice['paid']).' | '.$this->label($recipient,'المتبقي','Remaining').' '.$this->money($invoice['remaining'])."\n";
            $invoiceRowsHtml .= $this->invoiceRowHtml($recipient, $invoice);
        }
        if ($invoiceRowsHtml === '') $invoiceRowsHtml = $this->emptyHtml($this->label($recipient, 'لا توجد فواتير معتمدة.', 'No validated invoices.'));

        $recentTransactions = array_slice($transactions, max(0, count($transactions) - 12));
        $body .= "\n".$this->label($recipient, 'أحدث الحركات', 'Latest transactions')."\n";
        $transactionsHtml = '';
        foreach ($recentTransactions as $row) {
            $body .= dol_print_date($row['date'],'day').' | '.cpcc_financial_type_label($row['type']).' | '.$this->money($row['amount']).' '.$row['currency'];
            if (!empty($row['reference'])) $body .= ' | '.$row['reference'];
            if (!empty($row['public_note'])) $body .= ' | '.$this->label($recipient,'ملاحظة','Note').': '.preg_replace('/\s+/', ' ', $row['public_note']);
            $body .= "\n";
            $transactionsHtml .= $this->transactionRowHtml($recipient, $row);
        }
        if ($transactionsHtml === '') $transactionsHtml = $this->emptyHtml($this->label($recipient, 'لا توجد حركات.', 'No transactions.'));
        if (count($transactions) > count($recentTransactions)) {
            $limited = $this->label(
                $recipient,
                'يظهر هنا أحدث 12 حركة من أصل '.count($transactions).'. افتح البوابة لعرض الكشف الكامل.',
                'Showing the latest 12 of '.count($transactions).' transactions. Open the portal for the complete statement.'
            );
            $body .= $limited."\n";
            $transactionsHtml .= '<p style="margin:12px 0 0;color:#647b87;font-size:13px;line-height:1.6;">'.$this->e($limited).'</p>';
        }
        $body .= "\n".$this->label($recipient, 'فتح كشف الحساب', 'Open financial statement').': '.$statementUrl;
        $body .= $this->closing($recipient);
        $content = $summaryHtml;
        $content .= $this->sectionHtml($this->label($recipient, 'الفواتير', 'Invoices'), $invoiceRowsHtml);
        $content .= $this->sectionHtml($this->label($recipient, 'أحدث الحركات', 'Latest transactions'), $transactionsHtml);
        $subject = $this->header($this->label($recipient, 'كشف حساب China Planet', 'China Planet financial statement').' — '.$recipient['name']);
        return $this->messageResult(
            $subject,
            $body,
            $this->emailHtml(
                $recipient,
                $this->label($recipient, 'كشف الحساب المالي', 'FINANCIAL STATEMENT'),
                $this->label($recipient, 'كشف حساب تفصيلي', 'Detailed financial statement'),
                $this->label($recipient, 'ملخص الفواتير المعتمدة والحركات المالية المسجلة في كل عملة أصلية.', 'A clear summary of validated invoices and recorded financial activity in each original currency.'),
                $content,
                $this->label($recipient, 'فتح كشف الحساب الكامل', 'Open full financial statement'),
                $statementUrl
            ),
            'societe',
            $socid,
            '',
            'Financial statement'
        );
    }

    private function invoiceMessage($socid, $invoiceId, $currencyFallback, array $recipient)
    {
        $invoice = null;
        foreach (cpcc_financial_customer_invoices($this->db, $socid, $currencyFallback) as $row) if ((int)$row['id']===(int)$invoiceId) { $invoice=$row; break; }
        if (!$invoice) throw new CustomerSectionEmailException('Invoice was not found in this customer account.');
        $body = $this->greeting($recipient)."\n\n".$this->label($recipient,'تفاصيل الفاتورة','Invoice details')."\n\n";
        $body .= $this->label($recipient,'الفاتورة','Invoice').': '.$invoice['ref']."\n".$this->label($recipient,'تاريخ الإصدار','Issue date').': '.dol_print_date($invoice['date'],'day')."\n";
        if ($invoice['due_date']!=='') $body .= $this->label($recipient,'الاستحقاق','Due date').': '.dol_print_date($invoice['due_date'],'day')."\n";
        $body .= $this->label($recipient,'الإجمالي','Total').': '.$this->money($invoice['total']).' '.$invoice['currency']."\n".$this->label($recipient,'المدفوع أو الممول','Paid or funded').': '.$this->money($invoice['paid']).' '.$invoice['currency']."\n".$this->label($recipient,'المتبقي','Outstanding').': '.$this->money($invoice['remaining']).' '.$invoice['currency']."\n";
        $invoiceUrl = $this->portalUrl('/custom/chinaplanetcustomercenter/invoice.php?id='.(int)$invoiceId);
        $body .= "\n".$this->label($recipient,'فتح التفاصيل والبنود','Open details and line items').': '.$invoiceUrl;
        $body .= $this->closing($recipient);
        $values = array('total'=>$invoice['total'], 'paid'=>$invoice['paid'], 'remaining'=>$invoice['remaining']);
        $content = $this->currencySummaryHtml($recipient, $invoice['currency'], $values);
        $metadata = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">';
        $metadata .= $this->detailRowHtml($this->label($recipient,'الفاتورة','Invoice'), $invoice['ref']);
        $metadata .= $this->detailRowHtml($this->label($recipient,'تاريخ الإصدار','Issue date'), dol_print_date($invoice['date'],'day'));
        if ($invoice['due_date'] !== '') $metadata .= $this->detailRowHtml($this->label($recipient,'الاستحقاق','Due date'), dol_print_date($invoice['due_date'],'day'));
        $metadata .= '</table>';
        $content .= $this->sectionHtml($this->label($recipient,'بيانات الفاتورة','Invoice information'), $metadata);
        $subject = $this->header($this->label($recipient,'تفاصيل الفاتورة','Invoice details').' '.$invoice['ref']);
        return $this->messageResult(
            $subject,
            $body,
            $this->emailHtml(
                $recipient,
                $this->label($recipient, 'الفاتورة', 'INVOICE'),
                $this->label($recipient, 'تفاصيل الفاتورة', 'Invoice details').' '.$invoice['ref'],
                $this->label($recipient, 'ملخص آمن للفاتورة المعتمدة وحالة تمويلها.', 'A customer-safe summary of the validated invoice and its funding status.'),
                $content,
                $this->label($recipient,'فتح التفاصيل والبنود','Open details and line items'),
                $invoiceUrl
            ),
            'facture',
            $invoiceId,
            'fac'.(int)$invoiceId,
            'Invoice '.$invoice['ref']
        );
    }

    private function projectMessage($socid, $projectId, $currencyFallback, array $recipient)
    {
        global $conf;
        $sql = "SELECT ref,title,description,fk_statut FROM ".MAIN_DB_PREFIX."projet WHERE rowid=".(int)$projectId." AND entity=".(int)$conf->entity." AND fk_soc=".(int)$socid;
        $resql=$this->db->query($sql);$project=$resql?$this->db->fetch_object($resql):null;
        if(!$project)throw new CustomerSectionEmailException('Project was not found in this customer account.');
        $invoices=cpcc_financial_customer_invoices($this->db,$socid,$currencyFallback,$projectId);$documents=cpcc_customer_documents($this->db,$socid,$projectId);
        $body=$this->greeting($recipient)."\n\n".$this->label($recipient,'ملخص المشروع','Project summary')."\n\n".$project->ref.' — '.$project->title."\n";
        if(trim((string)$project->description)!=='')$body.=preg_replace('/\s+/', ' ', dol_string_nohtmltag($project->description,0))."\n";
        $body.="\n".$this->label($recipient,'الفواتير المرتبطة','Linked invoices').': '.count($invoices)."\n".$this->label($recipient,'المستندات المنشورة','Published documents').': '.count($documents)."\n";
        foreach(cpcc_financial_invoice_totals($invoices) as $currency=>$values)$body.=$currency.' — '.$this->label($recipient,'الإجمالي','Total').' '.$this->money($values['total']).' | '.$this->label($recipient,'المتبقي','Outstanding').' '.$this->money($values['remaining'])."\n";
        $projectUrl = $this->portalUrl('/custom/chinaplanetcustomercenter/project.php?id='.(int)$projectId);
        $body.="\n".$this->label($recipient,'فتح المشروع','Open project').': '.$projectUrl;
        $body.=$this->closing($recipient);
        $metadata = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">';
        $metadata .= $this->detailRowHtml($this->label($recipient,'المشروع','Project'), $project->ref.' — '.$project->title);
        $metadata .= $this->detailRowHtml($this->label($recipient,'الفواتير المرتبطة','Linked invoices'), count($invoices));
        $metadata .= $this->detailRowHtml($this->label($recipient,'المستندات المنشورة','Published documents'), count($documents));
        $metadata .= '</table>';
        $content = $metadata;
        $description = $this->cleanText($project->description);
        if ($description !== '') $content .= '<p style="margin:16px 0 0;color:#385365;font-size:14px;line-height:1.75;">'.$this->e($description).'</p>';
        foreach(cpcc_financial_invoice_totals($invoices) as $currency=>$values) $content .= $this->currencySummaryHtml($recipient, $currency, $values);
        $subject = $this->header($this->label($recipient,'تحديث المشروع','Project update').' '.$project->ref);
        return $this->messageResult(
            $subject,
            $body,
            $this->emailHtml(
                $recipient,
                $this->label($recipient, 'المشروع', 'PROJECT'),
                $this->label($recipient,'ملخص المشروع','Project summary').' '.$project->ref,
                $this->label($recipient, 'ملخص حالة المشروع والبيانات المنشورة المرتبطة به.', 'A summary of the project and its published customer information.'),
                $content,
                $this->label($recipient,'فتح المشروع','Open project'),
                $projectUrl
            ),
            'project',
            $projectId,
            'proj'.(int)$projectId,
            'Project '.$project->ref
        );
    }

    private function shipmentMessage($socid, $shipmentId, array $recipient)
    {
        global $conf;
        $sql="SELECT rowid,ref,container_no,bl_no,port_loading,port_destination,date_etd,date_eta,date_ata,fk_statut,customer_note FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE rowid=".(int)$shipmentId." AND entity=".(int)$conf->entity." AND fk_soc=".(int)$socid;
        $resql=$this->db->query($sql);$shipment=$resql?$this->db->fetch_object($resql):null;
        if(!$shipment)throw new CustomerSectionEmailException('Shipment was not found in this customer account.');
        $body=$this->greeting($recipient)."\n\n".$this->label($recipient,'ملخص الشحنة','Shipment summary')."\n\n".$this->label($recipient,'الشحنة','Shipment').': '.$shipment->ref."\n";
        if($shipment->container_no)$body.=$this->label($recipient,'الحاوية','Container').': '.$shipment->container_no."\n";
        if($shipment->bl_no)$body.='BL: '.$shipment->bl_no."\n";
        if($shipment->port_loading||$shipment->port_destination)$body.=$this->label($recipient,'المسار','Route').': '.$shipment->port_loading.' → '.$shipment->port_destination."\n";
        if($shipment->date_etd)$body.='ETD: '.dol_print_date($shipment->date_etd,'day')."\n";
        if($shipment->date_eta)$body.='ETA: '.dol_print_date($shipment->date_eta,'day')."\n";
        if(trim((string)$shipment->customer_note)!=='')$body.=$this->label($recipient,'ملاحظة','Note').': '.preg_replace('/\s+/', ' ', dol_string_nohtmltag($shipment->customer_note,0))."\n";
        $shipmentUrl = $this->portalUrl('/custom/chinaplanetlogistics/card.php?id='.(int)$shipmentId);
        $body.="\n".$this->label($recipient,'متابعة الشحنة','Track shipment').': '.$shipmentUrl;
        $body.=$this->closing($recipient);
        $metadata = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">';
        $metadata .= $this->detailRowHtml($this->label($recipient,'الشحنة','Shipment'), $shipment->ref);
        if ($shipment->container_no) $metadata .= $this->detailRowHtml($this->label($recipient,'الحاوية','Container'), $shipment->container_no);
        if ($shipment->bl_no) $metadata .= $this->detailRowHtml('BL', $shipment->bl_no);
        if ($shipment->port_loading || $shipment->port_destination) $metadata .= $this->detailRowHtml($this->label($recipient,'المسار','Route'), $shipment->port_loading.' → '.$shipment->port_destination);
        if ($shipment->date_etd) $metadata .= $this->detailRowHtml('ETD', dol_print_date($shipment->date_etd,'day'));
        if ($shipment->date_eta) $metadata .= $this->detailRowHtml('ETA', dol_print_date($shipment->date_eta,'day'));
        $metadata .= '</table>';
        $note = $this->cleanText($shipment->customer_note);
        if ($note !== '') $metadata .= '<div style="margin-top:16px;padding:14px 16px;border-radius:10px;background:#f5f9fb;color:#385365;font-size:14px;line-height:1.7;"><strong>'.$this->e($this->label($recipient,'ملاحظة','Note')).':</strong> '.$this->e($note).'</div>';
        $subject = $this->header($this->label($recipient,'ملخص الشحنة','Shipment summary').' '.$shipment->ref);
        return $this->messageResult(
            $subject,
            $body,
            $this->emailHtml(
                $recipient,
                $this->label($recipient, 'الشحنة', 'SHIPMENT'),
                $this->label($recipient,'ملخص الشحنة','Shipment summary').' '.$shipment->ref,
                $this->label($recipient, 'أحدث البيانات المنشورة للعميل عن هذه الشحنة.', 'The latest customer-published information for this shipment.'),
                $metadata,
                $this->label($recipient,'متابعة الشحنة','Track shipment'),
                $shipmentUrl
            ),
            'chinaplanetlogistics',
            $shipmentId,
            '',
            'Shipment '.$shipment->ref
        );
    }

    private function messageResult($subject, $bodyText, $bodyHtml, $elementType, $elementId, $trackId, $label)
    {
        return array(
            'subject'=>$subject,
            'body'=>$bodyText,
            'body_text'=>$bodyText,
            'body_html'=>$bodyHtml,
            'elementtype'=>$elementType,
            'elementid'=>(int)$elementId,
            'trackid'=>$trackId,
            'label'=>$label,
        );
    }

    private function emailHtml(array $recipient, $eyebrow, $title, $intro, $content, $actionLabel, $actionUrl)
    {
        $rtl = strpos($recipient['language'], 'ar') === 0;
        $dir = $rtl ? 'rtl' : 'ltr';
        $align = $rtl ? 'right' : 'left';
        $greeting = $this->label($recipient, 'مرحبًا', 'Hello').($recipient['name'] !== '' ? ' '.$recipient['name'] : '').'،';
        $reply = $this->label($recipient, 'يمكنكم الرد مباشرة على هذا البريد إذا احتجتم إلى المساعدة.', 'You can reply directly to this email if you need assistance.');
        $regards = $this->label($recipient, 'مع التحية، فريق China Planet', 'Best regards, China Planet Team');

        return '<div dir="'.$dir.'" style="margin:0;padding:24px 12px;background:#f2f6f8;color:#173e56;font-family:Arial,Tahoma,sans-serif;text-align:'.$align.';">'
            .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:720px;margin:0 auto;border-collapse:separate;border-spacing:0;background:#ffffff;border:1px solid #d7e4e9;border-radius:18px;overflow:hidden;">'
            .'<tr><td style="padding:24px 28px;background:#123f58;color:#ffffff;">'
            .'<div style="font-size:13px;font-weight:800;letter-spacing:1.8px;color:#f3bf2e;">CHINA PLANET</div>'
            .'<div style="margin-top:16px;font-size:11px;font-weight:800;letter-spacing:1.2px;color:#a9c8d7;">'.$this->e($eyebrow).'</div>'
            .'<h1 style="margin:7px 0 0;font-size:28px;line-height:1.25;color:#ffffff;">'.$this->e($title).'</h1>'
            .'</td></tr>'
            .'<tr><td style="padding:26px 28px 30px;">'
            .'<p style="margin:0;color:#173e56;font-size:16px;font-weight:700;line-height:1.7;">'.$this->e($greeting).'</p>'
            .'<p style="margin:8px 0 24px;color:#5e7683;font-size:14px;line-height:1.75;">'.$this->e($intro).'</p>'
            .$content
            .'<div style="margin-top:28px;text-align:center;"><a href="'.$this->e($actionUrl).'" style="display:inline-block;padding:13px 22px;border-radius:10px;background:#d3a21b;color:#102f44;font-size:14px;font-weight:800;text-decoration:none;">'.$this->e($actionLabel).'</a></div>'
            .'<p style="margin:24px 0 0;color:#647b87;font-size:13px;line-height:1.7;">'.$this->e($reply).'</p>'
            .'<p style="margin:8px 0 0;color:#173e56;font-size:13px;font-weight:700;line-height:1.7;">'.$this->e($regards).'</p>'
            .'</td></tr></table>'
            .'<p style="max-width:720px;margin:14px auto 0;color:#78909c;font-size:11px;line-height:1.5;text-align:center;">'.$this->e($this->label($recipient, 'هذه الرسالة أُنشئت من مركز عملاء China Planet.', 'This message was generated from the China Planet Customer Center.')).'</p>'
            .'</div>';
    }

    private function currencySummaryHtml(array $recipient, $currency, array $values)
    {
        $cells = array(
            array($this->label($recipient, 'إجمالي الفواتير', 'Total invoiced'), $values['total'], '#123f58'),
            array($this->label($recipient, 'المدفوع أو الممول', 'Paid or funded'), $values['paid'], '#11827a'),
            array($this->label($recipient, 'المتبقي', 'Outstanding'), $values['remaining'], '#b64136'),
        );
        $html = '<div style="margin:0 0 16px;padding:16px;border:1px solid #d7e4e9;border-radius:12px;background:#fbfdfe;">';
        $html .= '<div style="margin-bottom:12px;font-size:13px;font-weight:800;color:#173e56;">'.$this->e($currency).'</div>';
        $html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="table-layout:fixed;border-collapse:collapse;"><tr>';
        foreach ($cells as $index=>$cell) {
            $border = $index > 0 ? 'border-inline-start:1px solid #dfe9ed;' : '';
            $html .= '<td width="33.33%" style="padding:4px 8px;vertical-align:top;'.$border.'">'
                .'<div style="color:#647b87;font-size:11px;font-weight:700;line-height:1.4;">'.$this->e($cell[0]).'</div>'
                .'<div style="margin-top:6px;color:'.$cell[2].';font-size:18px;font-weight:800;line-height:1.25;overflow-wrap:anywhere;">'.$this->e($this->money($cell[1])).'</div>'
                .'</td>';
        }
        return $html.'</tr></table></div>';
    }

    private function invoiceRowHtml(array $recipient, array $invoice)
    {
        return '<div style="margin:0 0 10px;padding:13px 14px;border:1px solid #e0e9ed;border-radius:10px;background:#ffffff;">'
            .'<div style="font-size:14px;font-weight:800;color:#173e56;">'.$this->e($invoice['ref']).' <span style="color:#78909c;font-size:12px;font-weight:700;">'.$this->e($invoice['currency']).'</span></div>'
            .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:9px;table-layout:fixed;border-collapse:collapse;"><tr>'
            .$this->miniValueHtml($this->label($recipient, 'الإجمالي', 'Total'), $this->money($invoice['total']))
            .$this->miniValueHtml($this->label($recipient, 'الممول', 'Funded'), $this->money($invoice['paid']))
            .$this->miniValueHtml($this->label($recipient, 'المتبقي', 'Outstanding'), $this->money($invoice['remaining']))
            .'</tr></table></div>';
    }

    private function transactionRowHtml(array $recipient, array $row)
    {
        $direction = $row['direction'] === 'C' ? '+' : '−';
        $color = $row['direction'] === 'C' ? '#11827a' : '#b64136';
        $meta = array();
        if (!empty($row['reference'])) $meta[] = $this->cleanText($row['reference']);
        if (!empty($row['public_note'])) $meta[] = $this->label($recipient, 'ملاحظة', 'Note').': '.$this->cleanText($row['public_note']);
        return '<div style="margin:0 0 10px;padding:13px 14px;border:1px solid #e0e9ed;border-radius:10px;background:#ffffff;">'
            .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>'
            .'<td style="vertical-align:top;"><div style="font-size:13px;font-weight:800;color:#173e56;">'.$this->e(cpcc_financial_type_label($row['type'])).'</div><div style="margin-top:4px;color:#78909c;font-size:11px;">'.$this->e(dol_print_date($row['date'],'day')).'</div></td>'
            .'<td style="padding-inline-start:12px;vertical-align:top;text-align:end;"><div style="color:'.$color.';font-size:14px;font-weight:800;white-space:nowrap;">'.$direction.$this->e($this->money($row['amount'])).' '.$this->e($row['currency']).'</div><div style="margin-top:4px;color:#78909c;font-size:11px;">'.$this->e($this->label($recipient, 'الرصيد', 'Balance')).': '.$this->e($this->money($row['balance'])).'</div></td>'
            .'</tr></table>'
            .(!empty($meta) ? '<div style="margin-top:8px;color:#5e7683;font-size:12px;line-height:1.6;overflow-wrap:anywhere;">'.$this->e(implode(' · ', $meta)).'</div>' : '')
            .'</div>';
    }

    private function miniValueHtml($label, $value)
    {
        return '<td width="33.33%" style="padding:0 6px;vertical-align:top;"><div style="color:#78909c;font-size:10px;font-weight:700;">'.$this->e($label).'</div><div style="margin-top:4px;color:#173e56;font-size:13px;font-weight:800;overflow-wrap:anywhere;">'.$this->e($value).'</div></td>';
    }

    private function detailRowHtml($label, $value)
    {
        return '<tr><td style="width:38%;padding:9px 0;border-bottom:1px solid #e6eef1;color:#647b87;font-size:13px;font-weight:700;vertical-align:top;">'.$this->e($label).'</td><td style="padding:9px 12px;border-bottom:1px solid #e6eef1;color:#173e56;font-size:13px;font-weight:800;vertical-align:top;overflow-wrap:anywhere;">'.$this->e($value).'</td></tr>';
    }

    private function sectionHtml($title, $content)
    {
        return '<div style="margin-top:24px;"><h2 style="margin:0 0 12px;color:#173e56;font-size:17px;line-height:1.4;">'.$this->e($title).'</h2>'.$content.'</div>';
    }

    private function emptyHtml($message)
    {
        return '<div style="padding:14px 16px;border:1px dashed #cbdbe2;border-radius:10px;background:#f8fbfc;color:#647b87;font-size:13px;line-height:1.6;">'.$this->e($message).'</div>';
    }

    private function portalUrl($path)
    {
        $path = '/'.ltrim((string)$path, '/');
        $configured = function_exists('getDolGlobalString') ? trim((string)getDolGlobalString('MAIN_APPLICATION_URL_ROOT')) : '';
        if (filter_var($configured, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $configured)) return rtrim($configured, '/').$path;

        $root = defined('DOL_MAIN_URL_ROOT') ? rtrim((string)DOL_MAIN_URL_ROOT, '/') : '';
        if (filter_var($root, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $root)) return $root.$path;

        $host = isset($_SERVER['HTTP_HOST']) ? trim((string)$_SERVER['HTTP_HOST']) : '';
        if ($host !== '' && preg_match('/^(?:\[[0-9a-f:]+\]|[a-z0-9.-]+)(?::[0-9]{1,5})?$/i', $host)) {
            $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
            return ($https ? 'https://' : 'http://').$host.$root.$path;
        }
        return $root.$path;
    }

    private function cleanText($value)
    {
        return trim((string)preg_replace('/\s+/', ' ', dol_string_nohtmltag((string)$value, 0)));
    }

    private function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function recordEvent(array $message,$socid,$recipient,$from,$messageId,$user)
    {
        $event=new ActionComm($this->db);$event->type_code='AC_OTH_AUTO';$event->code='AC_EMAIL';$event->label=$this->header($message['label'].' — '.$message['subject']);$event->note_private=$message['body_text'];$event->datep=dol_now();$event->datef=$event->datep;$event->percentage=-1;$event->authorid=(int)$user->id;$event->userownerid=(int)$user->id;$event->socid=(int)$socid;$event->email_msgid=(string)$messageId;$event->email_subject=$message['subject'];$event->email_from=$from;$event->email_to=$recipient;$event->errors_to=$from;$event->reply_to=$from;$event->elementtype=$message['elementtype'];$event->elementid=(int)$message['elementid'];
        $result=$event->create($user);if($result<=0){dol_syslog(__METHOD__.' could not record section email: '.$event->error,LOG_ERR);return 0;}return (int)$result;
    }

    private function language($value){$value=strtolower(trim((string)$value));return strpos($value,'ar')===0?'ar_SA':'en_US';}
    private function label(array $recipient,$ar,$en){return strpos($recipient['language'],'ar')===0?$ar:$en;}
    private function greeting(array $recipient){return $this->label($recipient,'مرحبًا','Hello').($recipient['name']!==''?' '.$recipient['name']:'').'،';}
    private function closing(array $recipient){return "\n\n".$this->label($recipient,"مع التحية،\nفريق China Planet","Best regards,\nChina Planet Team");}
    private function money($amount){return number_format((float)$amount,2,'.',',');}
    private function header($value){return mb_substr(trim(dol_string_nohtmltag(preg_replace('/[\r\n]+/',' ',(string)$value),1)),0,255);}
}
