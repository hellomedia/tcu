<?php

namespace App\Helper;

use App\Enum\DateIntervalType;

class DateHelper
{
    public static function calculateExpirationDate(DateIntervalType $intervalType, int $duration, ?\DateTimeImmutable $start = new \DateTimeImmutable()): \DateTimeImmutable
    {
        // https://www.php.net/manual/en/dateinterval.construct.php
        $intervalString = 'P' . $duration . $intervalType->value;

        $interval = new \DateInterval($intervalString);

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
    public static function diffDates(\DateTimeImmutable $origin, \DateTimeImmutable $target, ?bool $withSign = false): int|string
    {
        if ($withSign) {
            return $origin->diff($target)->format('%R%a');
        }

        return (int) $origin->diff($target)->format('%a');
    }

    public static function isPast(\DateTimeImmutable $date): bool
    {
        return self::diffDates(new \DateTimeImmutable('now'), $date, true) < 0;
    }

    public static function isFuture(\DateTimeImmutable $date): bool
    {
        return self::diffDates(new \DateTimeImmutable('now'), $date, true) >= 0;
    }

    /**
     * Get days since event
     *
     * $precision:
     * - 'time': difference between yesterday at 4pm and today ('now') at 3pm is 0, at 5pm it is 1.
     * - 'date': all times are forced to 00:00:00, so the difference between yesterday at 4pm (or any
     *   other time) and today ('today', i.e. today 00:00:00) is 1, whenever it is called.
     *
     * @param 'date'|'time' $precision
     */
    public static function getDaysSince(\DateTimeImmutable $event, ?string $precision = 'date'): int
    {
        if ($precision == 'time') {
            return (int) $event->diff(new \DateTime('now'))->format('%a');
        }

        return (int) $event->setTime(0, 0)->diff(new \DateTime('today'))->format('%a');
    }

    public static function min(\DateTimeImmutable $dateTime1, \DateTimeImmutable $dateTime2): \DateTimeImmutable
    {
        return $dateTime1 < $dateTime2 ? $dateTime1 : $dateTime2;
    }
}
