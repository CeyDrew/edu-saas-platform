<?php

namespace App\Domain\Catalog\Video;

interface VideoProbe
{
    /**
     * Duration of the video in whole seconds.
     */
    public function duration(string $path): int;

    /**
     * Write a JPEG frame taken at $atSecond to $target.
     */
    public function thumbnail(string $path, string $target, int $atSecond = 1): void;
}
