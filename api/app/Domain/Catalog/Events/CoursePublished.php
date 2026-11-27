<?php

namespace App\Domain\Catalog\Events;

use App\Domain\Catalog\Models\Course;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CoursePublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Course $course) {}
}
