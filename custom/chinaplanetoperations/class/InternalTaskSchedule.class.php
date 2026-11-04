<?php

/** Scheduling values use the same server-local storage convention as task deadlines. */
class InternalTaskSchedule
{
    public static function dateTime($value)
    {
        $value = str_replace('T', ' ', trim((string) $value));
        if ($value === '') return '';
        if (strlen($value) === 16) $value .= ':00';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        if (!$date || $date->format('Y-m-d H:i:s') !== $value || (int)$date->format('Y') < 1000) throw new RuntimeException('Invalid task date/time.');
        return $value;
    }

    public static function start(array $details)
    {
        $date = isset($details['start_date']) ? (string) $details['start_date'] : '';
        $time = isset($details['start_time']) ? (string) $details['start_time'] : '';
        if ($time !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time)) throw new RuntimeException('Invalid start time.');
        if ($date === '') {
            if ($time !== '') throw new RuntimeException('Start date is required with a start time.');
            return '';
        }
        return self::dateTime($date.' '.($time !== '' ? $time : '00:00'));
    }

    public static function validate(array $details, $deadline)
    {
        $start = self::start($details);
        $end = self::dateTime($deadline);
        if ($start !== '' && $end !== '' && $end < $start) throw new RuntimeException('Deadline must be on or after the start.');
    }
}
