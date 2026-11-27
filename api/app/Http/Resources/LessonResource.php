<?php

namespace App\Http\Resources;

use App\Domain\Catalog\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lesson outline. The body is only included when the caller is allowed to see it
 * (preview lessons, the course staff, or enrolled students via the learning API).
 *
 * @mixin Lesson
 */
class LessonResource extends JsonResource
{
    private bool $withContent = false;

    public function withContent(bool $withContent = true): static
    {
        $this->withContent = $withContent;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $showContent = $this->withContent || $request->attributes->getBoolean('lesson_content') || $this->is_preview;

        return [
            'id' => $this->id,
            'section_id' => $this->section_id,
            'title' => $this->title,
            'type' => $this->type->value,
            'duration_seconds' => $this->duration_seconds,
            'position' => $this->position,
            'is_preview' => $this->is_preview,
            'video_status' => $this->video_status->value,
            'content' => $this->when($showContent, $this->content),
            'transcript' => $this->when($showContent && $request->attributes->getBoolean('lesson_content'), $this->transcript),
            'video_url' => $this->when($showContent && $this->relationLoaded('media'), fn () => $this->getFirstMediaUrl('video') ?: null),
            'thumbnail_url' => $this->whenLoaded('media', fn () => $this->getFirstMediaUrl('thumbnail') ?: null),
        ];
    }
}
