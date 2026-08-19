<?php

namespace App\Enums;

enum ClinicalRecordStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Completed = 'completed';
    case Failed = 'failed';
}
