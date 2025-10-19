<?php

namespace App\Enum;

enum ReportResultStatus: string
{
    case Pending = 'pending';
    case Calculating = 'calculating';
    case Ready = 'ready';
    case Failed = 'failed';
}
