<?php

namespace App\Enum;

enum ReportRunStatus: string
{
    case Pending = 'pending';
    case BuildingQueries = 'building_queries';
    case Queued = 'queued';
    case Fetching = 'fetching';
    case Aggregating = 'aggregating';
    case Completed = 'completed';
    case Failed = 'failed';
}
