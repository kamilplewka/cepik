<?php

namespace App\Enum;

enum ReportQueryStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case Succeeded = 'succeeded';
    case Retrying = 'retrying';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
