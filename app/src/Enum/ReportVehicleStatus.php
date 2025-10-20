<?php

namespace App\Enum;

enum ReportVehicleStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case Retrying = 'retrying';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
