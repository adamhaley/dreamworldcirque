<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChatLeadRequest;
use App\Models\ChatQuery;
use App\Services\Prospects\ProspectIntakeData;
use App\Services\Prospects\ProspectIntakeService;
use Illuminate\Http\JsonResponse;

class StoreChatLeadController extends Controller
{
    public function __invoke(StoreChatLeadRequest $request, ProspectIntakeService $intake): JsonResponse
    {
        $prospect = $intake->record('chatbot', 'Chat Fallback', new ProspectIntakeData(
            name: $request->string('name')->trim()->toString(),
            email: $request->string('email')->trim()->toString(),
            message: $request->string('question')->trim()->toString(),
            rawPayload: ['unmatched_question' => $request->string('question')->trim()->toString()],
        ));

        // Best-effort link back to the ChatQuery this question was originally
        // logged under, if it still matches -- not load-bearing for lead
        // capture itself, just keeps the admin log's trail intact.
        ChatQuery::query()
            ->where('question', $request->string('question')->trim()->toString())
            ->whereNull('prospect_id')
            ->latest('id')
            ->first()
            ?->update(['prospect_id' => $prospect->id]);

        return response()->json([
            'message' => "Thanks! We've passed your question along and will follow up by email shortly.",
        ], 201);
    }
}
