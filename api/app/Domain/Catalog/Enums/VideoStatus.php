<?php

namespace App\Domain\Catalog\Enums;

enum VideoStatus: string
{
    case None = 'none';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
