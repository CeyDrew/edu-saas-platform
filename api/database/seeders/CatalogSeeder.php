<?php

namespace Database\Seeders;

use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\LessonType;
use App\Domain\Catalog\Enums\VideoStatus;
use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Synthetic catalog per brand. Videos are placeholders (no files): lessons are marked
 * "ready" with a duration so the storefront and player UI have something to show.
 */
class CatalogSeeder extends Seeder
{
    /**
     * @var array<string, list<array{title: string, category: string, level: string, price: int, summary: string, sections: array<string, list<string>>}>>
     */
    private const array CATALOG = [
        'kodakademi' => [
            [
                'title' => 'Sıfırdan PHP ve Laravel', 'category' => 'Backend', 'level' => 'beginner', 'price' => 59900,
                'summary' => 'PHP temellerinden Laravel ile gerçek bir REST API yazmaya kadar.',
                'sections' => [
                    'PHP Temelleri' => ['Değişkenler ve veri tipleri', 'Fonksiyonlar ve kapsam', 'Sınıflar ve nesneler'],
                    'Laravel’e Giriş' => ['Routing ve controller', 'Eloquent ORM ile veritabanı', 'Form Request ile doğrulama'],
                    'API Geliştirme' => ['API Resource ile çıktı', 'Sanctum ile kimlik doğrulama', 'Kuyruk ve job’lar'],
                ],
            ],
            [
                'title' => 'React ve Next.js ile Modern Arayüzler', 'category' => 'Frontend', 'level' => 'intermediate', 'price' => 69900,
                'summary' => 'Bileşen mimarisi, state yönetimi ve sunucu tarafı render.',
                'sections' => [
                    'React Temelleri' => ['JSX ve bileşenler', 'Props ve state', 'useEffect ve yan etkiler'],
                    'Next.js' => ['App Router ve layout’lar', 'Sunucu bileşenleri', 'SEO ve metadata'],
                ],
            ],
            [
                'title' => 'Git ve GitHub ile Takım Çalışması', 'category' => 'Araçlar', 'level' => 'beginner', 'price' => 0,
                'summary' => 'Branch, pull request ve code review akışı. Ücretsiz.',
                'sections' => [
                    'Git' => ['Commit ve geçmiş', 'Branch ve merge', 'Çakışmaları çözmek'],
                ],
            ],
            [
                'title' => 'Docker ile Geliştirme Ortamı', 'category' => 'DevOps', 'level' => 'intermediate', 'price' => 39900,
                'summary' => 'İmaj, konteyner ve docker compose ile tekrarlanabilir ortamlar.',
                'sections' => [
                    'Docker' => ['İmaj ve konteyner farkı', 'Dockerfile yazmak', 'docker compose ile çoklu servis'],
                ],
            ],
        ],
        'dilokulu' => [
            [
                'title' => 'İngilizce A1: Sıfırdan Konuşma', 'category' => 'İngilizce', 'level' => 'beginner', 'price' => 34900,
                'summary' => 'Günlük hayatta kendini tanıtmak ve basit diyaloglar.',
                'sections' => [
                    'Tanışma' => ['Selamlaşma kalıpları', 'To be fiili', 'Kendini tanıtmak'],
                    'Günlük Hayat' => ['Saatler ve günler', 'Present Simple', 'Alışveriş diyalogları'],
                ],
            ],
            [
                'title' => 'İş İngilizcesi: Toplantı ve E-posta', 'category' => 'İngilizce', 'level' => 'advanced', 'price' => 79900,
                'summary' => 'Toplantı yönetmek, sunum yapmak ve resmi e-posta yazmak.',
                'sections' => [
                    'Toplantılar' => ['Toplantı açmak ve gündem', 'Fikir belirtmek ve itiraz etmek'],
                    'Yazışma' => ['Resmi e-posta yapısı', 'Kibar rica kalıpları'],
                ],
            ],
            [
                'title' => 'Almanca A1 Başlangıç', 'category' => 'Almanca', 'level' => 'beginner', 'price' => 39900,
                'summary' => 'Artikeller, temel fiiller ve günlük kelimeler.',
                'sections' => [
                    'Temeller' => ['Der, die, das', 'Sein ve haben', 'Sayılar ve renkler'],
                ],
            ],
        ],
        'zirvedershane' => [
            [
                'title' => 'TYT Matematik Kampı', 'category' => 'TYT', 'level' => 'intermediate', 'price' => 89900,
                'summary' => 'Temel kavramlardan problemlere, soru çözümlü anlatım.',
                'sections' => [
                    'Sayılar' => ['Temel kavramlar', 'Bölünebilme kuralları', 'Rasyonel sayılar'],
                    'Problemler' => ['Sayı problemleri', 'Yaş problemleri', 'Hareket problemleri'],
                ],
            ],
            [
                'title' => 'AYT Fizik: Kuvvet ve Hareket', 'category' => 'AYT', 'level' => 'advanced', 'price' => 99900,
                'summary' => 'Newton yasaları, iş-güç-enerji ve atışlar.',
                'sections' => [
                    'Dinamik' => ['Newton’un hareket yasaları', 'Sürtünme kuvveti'],
                    'Enerji' => ['İş ve güç', 'Enerjinin korunumu'],
                ],
            ],
            [
                'title' => 'LGS Türkçe Paragraf Teknikleri', 'category' => 'LGS', 'level' => 'beginner', 'price' => 29900,
                'summary' => 'Ana fikir, yardımcı fikir ve paragraf soruları için strateji.',
                'sections' => [
                    'Paragraf' => ['Ana fikir bulma', 'Yardımcı fikirler', 'Paragrafın yapısı'],
                ],
            ],
        ],
    ];

    public function run(TenantContext $context): void
    {
        Tenant::query()->each(function (Tenant $tenant) use ($context) {
            $context->run($tenant, function () use ($tenant) {
                $instructors = User::query()->role(Role::Instructor->value)->get();

                foreach (self::CATALOG[$tenant->slug] ?? [] as $i => $data) {
                    $this->createCourse($data, $instructors[$i % $instructors->count()]);
                }
            });
        });
    }

    /**
     * @param  array{title: string, category: string, level: string, price: int, summary: string, sections: array<string, list<string>>}  $data
     */
    private function createCourse(array $data, User $instructor): void
    {
        $course = Course::query()->create([
            'instructor_id' => $instructor->id,
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'summary' => $data['summary'],
            'description' => $data['summary']."\n\nBu kursta konular kısa videolar, okuma notları ve her dersin sonundaki sorularla pekiştirilir. Yanlış yaptığın sorular kişisel tekrar listene eklenir.",
            'category' => $data['category'],
            'level' => $data['level'],
            'price' => $data['price'],
            'compare_at_price' => $data['price'] > 0 ? (int) round($data['price'] * 1.4, -2) : null,
        ]);

        $position = 0;
        foreach ($data['sections'] as $sectionTitle => $lessons) {
            $section = $course->sections()->create(['title' => $sectionTitle, 'position' => $position++]);

            foreach ($lessons as $j => $lessonTitle) {
                $section->lessons()->create([
                    'course_id' => $course->id,
                    'title' => $lessonTitle,
                    'type' => LessonType::Video,
                    'content' => $this->lessonText($data['title'], $lessonTitle),
                    'transcript' => $this->lessonText($data['title'], $lessonTitle),
                    'duration_seconds' => random_int(240, 1500),
                    'position' => $j,
                    'is_preview' => $position === 1 && $j === 0,
                ])->forceFill(['video_status' => VideoStatus::Ready])->save();
            }
        }

        $course->forceFill(['status' => CourseStatus::Published, 'published_at' => now()->subDays(random_int(1, 90))])->save();
    }

    private function lessonText(string $course, string $lesson): string
    {
        return "{$lesson} ({$course}). Bu derste konunun temel tanımı yapılır, günlük hayattan bir örnekle açıklanır "
            .'ve sık yapılan hatalar gösterilir. Önce kavramın neden gerekli olduğunu görürüz, ardından adım adım bir '
            .'örnek üzerinden ilerleriz. Dersin sonunda kendini sınaman için kısa bir değerlendirme bulunur. '
            .'Önemli nokta: kuralı ezberlemek yerine hangi durumda kullanıldığını anlamak, soruları çok daha hızlı çözmeni sağlar.';
    }
}
