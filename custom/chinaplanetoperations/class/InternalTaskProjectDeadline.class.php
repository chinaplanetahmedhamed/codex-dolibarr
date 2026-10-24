<?php
/** Native project end is a date-only field, entered at server midnight. */
class InternalTaskProjectDeadline
{
    public static function summarize($timestamp,$status,$now=null){
        $out=['date'=>null,'display'=>null,'days'=>null,'indicator'=>'none'];
        if(empty($timestamp))return $out;$now=$now===null?dol_now():(int)$now;
        // Match the native project card's server-local date semantics. Compare
        // calendar dates, not rolling seconds: due today remains due today.
        $date=dol_print_date((int)$timestamp,'%Y-%m-%d','tzserver');$today=dol_print_date($now,'%Y-%m-%d','tzserver');
        $zone=new DateTimeZone('UTC');$end=new DateTimeImmutable($date,$zone);$start=new DateTimeImmutable($today,$zone);$days=(int)$start->diff($end)->format('%r%a');
        $out=['date'=>$date,'display'=>dol_print_date((int)$timestamp,'day','tzserver'),'days'=>$days,'indicator'=>'none'];
        if((int)$status===2)$out['indicator']='completed';elseif($days<0)$out['indicator']='overdue';elseif($days<3)$out['indicator']='urgent';
        return $out;
    }
}
