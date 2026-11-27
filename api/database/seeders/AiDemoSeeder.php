<?php

namespace Database\Seeders;

use App\Domain\Ai\Models\AiGeneration;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Learning\Models\Question;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * One finished AI generation waiting for review, so the approval screen has content
 * without a model running. The drafts are a real qwen2.5:7b answer for this lesson,
 * recorded during local testing.
 */
class AiDemoSeeder extends Seeder
{
    private const string TRANSCRIPT = 'Eloquent, Laravel ile gelen ORM katmanıdır. Her veritabanı tablosu bir model sınıfıyla '
        .'temsil edilir; örneğin users tablosu User modeline karşılık gelir. Kayıtlar User::query()->where(...)->get() gibi '
        .'akıcı sorgularla okunur, create ve update metotlarıyla yazılır. İlişkiler hasMany, belongsTo ve belongsToMany '
        .'metotlarıyla tanımlanır. Bir döngü içinde ilişkili kayda erişmek N+1 sorgu problemine yol açar; bunu önlemek için '
        .'with() ile eager loading yapılır. Toplu atamada güvenlik için fillable alanları tanımlanır.';

    public function run(TenantContext $context): void
    {
        $tenant = Tenant::query()->where('slug', 'kodakademi')->first();

        if ($tenant === null) {
            return;
        }

        $context->run($tenant, function () {
            $lesson = Lesson::query()->where('title', 'Eloquent ORM ile veritabanı')->first();

            if ($lesson === null) {
                return;
            }

            $lesson->forceFill(['transcript' => self::TRANSCRIPT])->save();

            $generation = AiGeneration::query()->create([
                'lesson_id' => $lesson->id,
                'provider' => 'ollama',
                'model' => 'qwen2.5:7b',
                'status' => 'completed',
                'requested_count' => 3,
            ]);
            $generation->forceFill(['questions_created' => 3, 'attempts' => 1, 'duration_ms' => 39065])->save();

            $drafts = [
                ['Eloquent ORM, hangi framework ile birlikte gelir?', ['Symfony', 'Laravel', 'CodeIgniter', 'Zend'], 1,
                    'Metinde Eloquent’in Laravel ile gelen ORM katmanı olduğu belirtiliyor.'],
                ['User modeli, hangi veritabanı tablosunu temsil eder?', ['products', 'users', 'categories', 'orders'], 1,
                    'Örnekte users tablosunun User modeline karşılık geldiği söyleniyor.'],
                ['Eloquent ORM’de ilişkiler nasıl tanımlanır?', ['hasMany, belongsTo, belongsToMany', 'hasOne, belongsTo, belongsToMany', 'hasMany, hasOne, belongsTo', 'hasMany, belongsTo, hasManyThrough'], 0,
                    'Metinde ilişkilerin hasMany, belongsTo ve belongsToMany ile tanımlandığı yazıyor.'],
            ];

            foreach ($drafts as [$stem, $options, $correct, $explanation]) {
                Question::query()->create([
                    'course_id' => $lesson->course_id,
                    'lesson_id' => $lesson->id,
                    'stem' => $stem,
                    'options' => $options,
                    'correct_index' => $correct,
                    'explanation' => $explanation,
                    'source' => 'ai',
                    'status' => 'draft',
                    'ai_generation_id' => $generation->id,
                ]);
            }
        });
    }
}
