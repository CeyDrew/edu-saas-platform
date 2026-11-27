<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Lesson;
use App\Domain\Learning\LearningService;
use App\Domain\Learning\Models\Enrollment;
use App\Domain\Learning\Models\Question;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * A small question bank per lesson (one video task, two quiz questions) and simulated
 * study activity for enrolled students, so progress and reports have data.
 */
class LearningSeeder extends Seeder
{
    public function run(TenantContext $context, LearningService $learning): void
    {
        mt_srand(7);

        Tenant::query()->each(function (Tenant $tenant) use ($context, $learning) {
            $context->run($tenant, function () use ($learning) {
                Lesson::query()->each(fn (Lesson $lesson) => $this->questions($lesson));
                $this->activity($learning);
            });
        });

        Carbon::setTestNow();
    }

    private function questions(Lesson $lesson): void
    {
        $topic = $lesson->title;
        $bank = [
            [
                'stem' => "“{$topic}” konusunda en sık yapılan hata nedir?",
                'options' => ['Kuralı ezberleyip ne zaman kullanıldığını bilmemek', 'Konuyu örnekle çalışmak', 'Kısa tekrarlar yapmak', 'Soruları süre tutarak çözmek'],
                'correct_index' => 0,
                'explanation' => 'Derste vurgulandığı gibi kuralı ezberlemek yerine hangi durumda kullanıldığını anlamak gerekir.',
                'video_timestamp' => (int) round($lesson->duration_seconds / 3),
            ],
            [
                'stem' => "“{$topic}” dersinde konu hangi sırayla işlenir?",
                'options' => ['Örnek, tanım, değerlendirme', 'Tanım, örnek, sık yapılan hatalar', 'Sadece soru çözümü', 'Değerlendirme, tanım, örnek'],
                'correct_index' => 1,
                'explanation' => 'Önce tanım yapılır, ardından günlük hayattan bir örnek ve sık yapılan hatalar gösterilir.',
                'video_timestamp' => null,
            ],
            [
                'stem' => 'Dersin sonunda seni ne bekliyor?',
                'options' => ['Ödev teslimi', 'Canlı yayın', 'Kendini sınaman için kısa bir değerlendirme', 'Sertifika sınavı'],
                'correct_index' => 2,
                'explanation' => 'Her dersin sonunda kısa bir değerlendirme bulunur; yanlışların tekrar listene eklenir.',
                'video_timestamp' => null,
            ],
        ];

        foreach ($bank as $question) {
            Question::query()->create($question + [
                'course_id' => $lesson->course_id,
                'lesson_id' => $lesson->id,
                'source' => 'manual',
                'status' => 'approved',
            ]);
        }
    }

    /**
     * Replay a few weeks of study for each enrollment through the real service, so the
     * archive and SM-2 schedule are realistic.
     */
    private function activity(LearningService $learning): void
    {
        Enrollment::query()->with(['user', 'course.lessons'])->each(function (Enrollment $enrollment) use ($learning) {
            $lessons = $enrollment->course->lessons->sortBy('position')->values();
            $studied = $lessons->take(mt_rand(0, $lessons->count()));

            foreach ($studied as $lesson) {
                Carbon::setTestNow(now()->subDays(mt_rand(1, 30)));

                $learning->complete($enrollment->user, $lesson);

                foreach (Question::query()->where('lesson_id', $lesson->id)->get() as $question) {
                    // Roughly 70% correct.
                    $selected = mt_rand(1, 10) <= 7 ? $question->correct_index : ($question->correct_index + 1) % 4;
                    $learning->answer($enrollment->user, $question, $selected, $question->video_timestamp ? 'video' : 'quiz');
                }

                Carbon::setTestNow();
            }
        });
    }
}
