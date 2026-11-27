<?php

namespace App\Http\Controllers\Api;

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Role;
use App\Domain\Reporting\CsvExport;
use App\Domain\Reporting\SalesReport;
use App\Domain\Reporting\StudentReport;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly SalesReport $sales,
        private readonly StudentReport $students,
    ) {}

    public function brandSales(Request $request): JsonResponse|StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasRole(Role::BrandAdmin->value), 403);

        $report = $this->sales->build($this->days($request));

        if ($request->query('format') === 'csv') {
            return CsvExport::download(
                "satis-raporu-{$report['period']['from']}-{$report['period']['to']}.csv",
                ['Tarih', 'Sipariş', 'Ciro (TL)'],
                array_map(fn ($day) => [$day['date'], $day['orders'], CsvExport::money($day['revenue'])], $report['daily']),
            );
        }

        return response()->json(['data' => $report]);
    }

    public function courseStudents(Request $request, Course $course): JsonResponse|StreamedResponse
    {
        Gate::authorize('update', $course);

        $report = $this->students->build($course);

        if ($request->query('format') === 'csv') {
            return CsvExport::download(
                "ogrenci-performansi-{$course->slug}.csv",
                ['Ad', 'E-posta', 'Tamamlanan ders', 'İlerleme (%)', 'Doğru oranı (%)', 'Son etkinlik'],
                array_map(fn ($s) => [$s['name'], $s['email'], $s['completed_lessons'], $s['progress'], $s['accuracy'] ?? '', $s['last_activity'] ?? ''], $report['students']),
            );
        }

        return response()->json(['data' => $report]);
    }

    public function platform(Request $request): JsonResponse|StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('viewAny', Tenant::class), 403);

        $rows = $this->sales->platform($this->days($request));

        if ($request->query('format') === 'csv') {
            return CsvExport::download(
                'platform-raporu.csv',
                ['Marka', 'Alt alan adı', 'Aktif', 'Sipariş', 'Ciro (TL)', 'Komisyon (TL)', 'Öğrenci'],
                array_map(fn ($r) => [$r['name'], $r['slug'], $r['is_active'], $r['orders'], CsvExport::money($r['revenue']), CsvExport::money($r['platform_fee']), $r['students']], $rows),
            );
        }

        return response()->json(['data' => [
            'brands' => $rows,
            'totals' => [
                'revenue' => array_sum(array_column($rows, 'revenue')),
                'platform_fee' => array_sum(array_column($rows, 'platform_fee')),
                'orders' => array_sum(array_column($rows, 'orders')),
                'students' => array_sum(array_column($rows, 'students')),
            ],
        ]]);
    }

    private function days(Request $request): int
    {
        $request->validate(['days' => ['nullable', 'integer', 'in:7,30,90,365']]);

        return $request->integer('days', 30);
    }
}
