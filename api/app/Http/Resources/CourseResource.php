<?php

namespace App\Http\Resources;

use App\Domain\Catalog\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Course
 */
class CourseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'description' => $this->when($request->routeIs('*.show'), $this->description),
            'category' => $this->category,
            'level' => $this->level->value,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'included_in_subscription' => $this->included_in_subscription,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'cover_url' => $this->whenLoaded('media', fn () => $this->getFirstMediaUrl('cover') ?: null),
            'instructor' => $this->whenLoaded('instructor', fn () => ['id' => $this->instructor->id, 'name' => $this->instructor->name]),
            'lessons_count' => $this->whenCounted('lessons'),
            'duration_seconds' => $this->whenAggregated('lessons', 'duration_seconds', 'sum', fn ($value) => (int) $value),
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
        ];
    }
}
