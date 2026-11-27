<?php

use App\Domain\Catalog\Enums\VideoStatus;
use App\Domain\Catalog\Jobs\ProcessLessonVideo;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Video\VideoProbe;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->tenant = Tenant::factory()->create();
});

it('stores duration and thumbnail from the probe', function () {
    $this->app->instance(VideoProbe::class, new class implements VideoProbe
    {
        public function duration(string $path): int
        {
            return 754;
        }

        public function thumbnail(string $path, string $target, int $atSecond = 1): void
        {
            file_put_contents($target, UploadedFile::fake()->image('t.jpg')->getContent());
        }
    });

    $lesson = asTenant($this->tenant, function () {
        $lesson = Lesson::factory()->create(['type' => 'video']);
        $lesson->addMedia(fakeVideo())->toMediaCollection('video');

        return $lesson;
    });

    asTenant($this->tenant, fn () => (new ProcessLessonVideo($lesson->id))->handle(app(VideoProbe::class)));

    $lesson->refresh();
    expect($lesson->duration_seconds)->toBe(754)
        ->and($lesson->video_status)->toBe(VideoStatus::Ready)
        ->and($lesson->getFirstMedia('thumbnail'))->not->toBeNull();
});

it('marks the lesson as failed when processing gives up', function () {
    $lesson = asTenant($this->tenant, fn () => Lesson::factory()->create(['type' => 'video']));

    (new ProcessLessonVideo($lesson->id))->failed(new RuntimeException('bozuk dosya'));

    expect($lesson->fresh()->video_status)->toBe(VideoStatus::Failed);
});

it('reads real videos with ffmpeg', function () {
    $video = sys_get_temp_dir().'/sample.mp4';
    $result = Process::run(['ffmpeg', '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'testsrc=duration=4:size=320x240:rate=10', $video]);

    if ($result->failed()) {
        $this->markTestSkipped('ffmpeg is not installed.');
    }

    $probe = app(VideoProbe::class);
    $thumb = sys_get_temp_dir().'/sample.jpg';
    $probe->thumbnail($video, $thumb, 1);

    expect($probe->duration($video))->toBe(4)->and(filesize($thumb))->toBeGreaterThan(0);
});
