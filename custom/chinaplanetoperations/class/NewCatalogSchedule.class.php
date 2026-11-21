<?php
/** Daily kickoff with frequent bounded continuations, in the business timezone.
 * Native cron may invoke every minute; after a successful full scan all calls
 * remain idle until the next 03:00 Asia/Shanghai boundary. No desktop dependency.
 */
final class NewCatalogSchedule
{
    public static function due($lastScanSuccess, $now = null)
    {
        $now = $now === null ? time() : $now;
        if (!is_int($now) || ($lastScanSuccess !== null && (!is_int($lastScanSuccess)
            || $lastScanSuccess < 0 || $lastScanSuccess > $now))) {
            throw new RuntimeException('SCHEDULE_STATE_INVALID');
        }
        $local = (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Asia/Shanghai'));
        $boundary = $local->setTime(3, 0, 0);
        if ($local < $boundary) $boundary = $boundary->modify('-1 day');
        return $lastScanSuccess === null || $lastScanSuccess < $boundary->getTimestamp();
    }
}
