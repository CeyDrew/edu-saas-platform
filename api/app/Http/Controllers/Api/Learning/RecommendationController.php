<?php

namespace App\Http\Controllers\Api\Learning;

use App\Domain\Ai\ReviewAdvisor;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __invoke(Request $request, ReviewAdvisor $advisor): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $advisor->advise($user)]);
    }
}
