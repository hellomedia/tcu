<?php

namespace App\Enum;

enum DateIntervalType: string
{
    case HOURS = "H";
    case DAYS = 'D';
    case WEEKS = 'W';
    case MONTHS = 'M';
    case YEARS = 'Y';
}