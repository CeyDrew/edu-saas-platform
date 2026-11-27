<?php

namespace App\Domain\Catalog\Jobs;

use App\Domain\Catalog\Enums\VideoStatus;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Video\VideoProbe;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Reads the duration of an uploaded lesson video and extracts a thumbnail.
 * Runs on the "media" queue; the tenant is restored from the job payload.
 */
class ProcessLessonVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $lessonId)
    {
        $this->onQueue('media');
    }

    public function handle(VideoProbe $probe): void
    {
        $lesson = Lesson::query()->findOrFail($this->lessonId);
        $video = $lesson->getFirstMedia('video');

        if ($video === null) {
            $lesson->forceFill(['video_status' => VideoStatus::None])->save();

            return;
        }

        $lesson->forceFill(['video_status' => VideoStatus::Processing])->save();

        // Work on a local copy so the job also works with remote disks.
        $local = tempnam(sys_get_temp_dir(), 'video');
        $thumb = $local.'.jpg';
        file_put_contents($local, Storage::disk($video->disk)->get($video->getPathRelativeToRoot()));

        try {
            $duration = $probe->duration($local);
            $probe->thumbnail($local, $thumb, min(3, max(0, intdiv($duration, 2))));

            $lesson->addMedia($thumb)->usingFileName("lesson-{$lesson->id}.jpg")->toMediaCollection('thumbnail');
            $lesson->forceFill(['duration_seconds' => $duration, 'video_status' => VideoStatus::Ready])->save();
        } finally {
            @unlink($local);
            @unlink($thumb);
        }
    }

    public function failed(Throwable $exception): void
    {
        Lesson::query()->whereKey($this->lessonId)->update(['video_status' => VideoStatus::Failed]);
    }
}
