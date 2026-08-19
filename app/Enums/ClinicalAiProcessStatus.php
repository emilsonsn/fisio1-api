<?php

namespace App\Enums;

enum ClinicalAiProcessStatus: string
{
    case Pending = 'pending';
    case Splitting = 'splitting';
    case Transcribing = 'transcribing';
    case Consolidating = 'consolidating';
    case Completed = 'completed';
    case Failed = 'failed';
}
