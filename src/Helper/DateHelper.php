<?php

namespace App\Helper;

use App\Enum\DateIntervalType;
use DateInterval;
use DateTime;
use DateTimeImmutable;

class DateHelper
{
    public static function calculateExpirationDate(DateIntervalType $intervalType, int $duration, ?DateTimeImmutable $start = new DateTimeImmutable()): DateTimeImmutable
    {
        // https://www.php.net/manual/en/dateinterval.construct.php
        $intervalString = 'P' . $duration . $intervalType->value;

        $interval = new DateInterval($intervalString);
        
        return $start->add($interval);
    }

    /**
     * Returns difference between 2 dates in number of days
     * 
     * If withSign = false, returns an int
     * If withSign = true, returns value as a string prefixed with +/-
     * 
     * NB: origin->diff(target) > 0  if target > origin
     */
    public static function diffDates(DateTimeImmutable $origin, DateTimeImmutable $target, ?bool $withSign = false): int|string
    {
        if ($withSign) {
            return $origin->diff($target)->format('%R%a');
        }

        return (int) $origin->diff($target)->format('%a');
    }

    public static function isPast(DateTimeImmutable $date): bool
    {
        return self::diffDates(new DateTimeImmutable('now'), $date, true) < 0;
    }

    public static function isFuture(DateTimeImmutable $date): bool
    {
        return self::diffDates(new DateTimeImmutable('now'), $date, true) >= 0;
    }

    /**
     * Get days since event
     * 
     * @var $precision string <'date'|'time'>
     *      'time'   => 
     *         - difference between yesterday at 4pm and today ('now') at 3pm is 0.
     *         - difference between yesterday at 4pm and today ('now') at 5pm is 1.
     *      'date' => 
     *         - difference between yesterday at 4pm (or any other time, all forced to 00:00::00)
     *         and today ('today') (which means today 00:00:00) is 1.
     *         Thus the difference between any event yesterday and 'today' is 1, when called at any time.
     */
    public static function getDaysSince(DateTimeImmutable $event, ?string $precision = 'date'): int
    {
        if ($precision == 'time') {
            return (int) $event->diff(new DateTime('now'))->format('%a');
        }

        return (int) $event->setTime(0, 0)->diff(new DateTime('today'))->format('%a');
    }

    public static function min(DateTimeImmutable $dateTime1, DateTimeImmutable $dateTime2): DateTimeImmutable
    {
        return $dateTime1 < $dateTime2 ? $dateTime1 : $dateTime2;
    }
}
