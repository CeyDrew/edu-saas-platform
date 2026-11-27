<?php

namespace App\Domain\Catalog\Enums;

enum LessonType: string
{
    case Video = 'video';
    case Text = 'text';
    case Pdf = 'pdf';
}
