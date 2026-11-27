<?php

namespace App\Http\Resources;

use App\Domain\Learning\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Students never receive the correct option before answering; staff see everything.
 *
 * @mixin Question
 */
class QuestionResource extends JsonResource
{
    private bool $forStaff = false;

    public function forStaff(bool $forStaff = true): static
    {
        $this->forStaff = $forStaff;

        return $this;
    }

    /**
     * @param  iterable<Question>  $questions
     * @return list<array<int|string, mixed>>
     */
    public static function staff(iterable $questions, Request $request): array
    {
        $rows = [];
        foreach ($questions as $question) {
            $rows[] = (new self($question))->forStaff()->resolve($request);
        }

        return $rows;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'stem' => $this->stem,
            'options' => $this->options,
            'video_timestamp' => $this->video_timestamp,
            $this->mergeWhen($this->forStaff, fn () => [
                'correct_index' => $this->correct_index,
                'explanation' => $this->explanation,
                'source' => $this->source,
                'status' => $this->status,
            ]),
        ];
    }
}
