<?php
declare(strict_types=1);

/** Read-only UI over already-authorized records. No queries, links, transitions or grants. */
final class OfficeExpenseRequestProgress
{
    private const FLOW=[
        'draft'=>['Draft','Preparer'], 'submitted'=>['Submitted','Approver'],
        'approved'=>['Approved','Executor'], 'rejected'=>['Rejected','Preparer'],
        'posted'=>['Posted','Reviewer'], 'reversed'=>['Reversed','Reviewer'],
        'archived'=>['Archived','RecordManager'],
    ];
    private static function e(string $value): string{return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    private static function labels(string $lang): array
    {
        $labels=[];
        foreach(file(__DIR__.'/../langs/'.($lang==='ar'?'ar_SA':'en_US').'/chinaplanetofficeexpenses.lang',FILE_IGNORE_NEW_LINES)as$line){
            $v=explode('=',$line,2);if(count($v)===2&&str_starts_with($v[0],'OfficeExpensesFlow'))$labels[substr($v[0],18)]=$v[1];
        }
        return $labels;
    }
    private static function context(array $row,array $t): string
    {
        $v=$row['content']??$row;$h='';$parts=[];
        if(is_string($v['purpose']??null))$h.='<h3>'.self::e($v['purpose']).'</h3>';
        $office=['egypt'=>'Egypt','china'=>'China'][$row['office']??'']??null;
        if($office!==null)$parts[]=self::e($t[$office]);
        $amount=$v['amount']??$v['net']??null;
        if(is_string($amount)&&is_string($v['currency']??null))$parts[]='<bdi>'.self::e($amount.' '.$v['currency']).'</bdi>';
        if(is_int($row['revision']??null)&&$row['revision']>0)$parts[]=self::e($t['Revision'].' '.$row['revision']);
        return $h.($parts?'<p>'.implode(' · ',$parts).'</p>':'');
    }
    public static function entry(string $lang,?array $row=null): string
    {
        $t=self::labels($lang);
        return '<section class="workspace-card request-progress" data-entry-workflow><h2>'.self::e($t['EntryTitle']).'</h2>'.($row?self::context($row,$t):'').'<p>'.self::e($t['EntryHelp']).'</p><p><strong>'.self::e($t['NextActor']).':</strong> '.self::e($t['Preparer']).'</p></section>';
    }
    public static function workspace(array $row,array $finance,string $lang): string
    {
        if(!in_array($row['kind']??'',['expense','advance','advance_return','advance_settlement'],true))return '';
        $t=self::labels($lang);$status=$row['status']??'';
        if(!isset(self::FLOW[$status]))return '<section class="workspace-card request-progress"><p>'.self::e($t['Unknown']).'</p></section>';
        [$label,$actor]=self::FLOW[$status];
        $h='<section class="workspace-card request-progress" data-request-status="'.$status.'"><h2>'.self::e($t['Title']).'</h2>'.self::context($row,$t).'<dl><div><dt>'.self::e($t['Status']).'</dt><dd>'.self::e($t[$label]).'</dd></div><div><dt>'.self::e($t['NextActor']).'</dt><dd>'.self::e($t[$actor]).'</dd></div></dl><p>'.self::e($t[$label.'Help']).'</p>';
        if($status==='submitted'&&!empty($finance['permissions']['approve']))$h.='<p>'.self::e($t['YourApproval']).'</p>';
        if($status==='approved'&&!empty($finance['enabled'])&&!empty($finance['permissions']['post']))$h.='<p>'.self::e($t['YourPosting']).'</p>';
        if($status==='rejected'){
            $reason=$row['content']['_finance']['rejection_reason']??null;
            $h.='<div class="request-return-reason"><h3>'.self::e($t['Reason']).'</h3><p>'.self::e(is_string($reason)&&trim($reason)!==''?$reason:$t['ReasonMissing']).'</p></div>';
        }
        if(empty($finance['enabled']))$h.='<p class="workspace-notice">'.self::e($t['Disabled']).'</p>';
        return $h.'</section>';
    }
}
