<?php

namespace App\Enums;

enum QuestionType: string
{
    case SCALE = 'SCALE';
    case MULTIPLE_CHOICE = 'MULTIPLE_CHOICE';
    case TEXT = 'TEXT';
    case YES_NO = 'YES_NO';
}
