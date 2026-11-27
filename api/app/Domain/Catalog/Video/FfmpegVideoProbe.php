<?php

namespace App\Domain\Catalog\Video;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class FfmpegVideoProbe implements VideoProbe
{
    public function duration(string $path): int
    {
        $result = Process::timeout(60)->run([
            'ffprobe', '-v', 'error', '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1', $path,
        ]);

        if ($result->failed() || ! is_numeric(trim($result->output()))) {
            throw new RuntimeException('ffprobe could not read the video: '.$result->errorOutput());
        }

        return (int) round((float) trim($result->output()));
    }

    public function thumbnail(string $path, string $target, int $atSecond = 1): void
    {
        $result = Process::timeout(120)->run([
            'ffmpeg', '-y', '-loglevel', 'error', '-ss', (string) $atSecond, '-i', $path,
            '-frames:v', '1', '-vf', 'scale=640:-2', $target,
        ]);

        if ($result->failed() || ! is_file($target)) {
            throw new RuntimeException('ffmpeg could not create a thumbnail: '.$result->errorOutput());
        }
    }
}
