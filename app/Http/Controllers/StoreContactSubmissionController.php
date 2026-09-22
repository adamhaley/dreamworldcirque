<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactSubmissionRequest;
use App\Services\Prospects\ProspectIntakeData;
use App\Services\Prospects\ProspectIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class StoreContactSubmissionController extends Controller
{
    public function __invoke(StoreContactSubmissionRequest $request, ProspectIntakeService $intake): JsonResponse|RedirectResponse
    {
        $intake->record('website_contact_form', $request->string('form_type')->toString(), new ProspectIntakeData(
            name: $request->string('name')->trim()->toString(),
            email: $request->string('email')->trim()->toString(),
            phone: $request->filled('phone') ? $request->string('phone')->trim()->toString() : null,
            message: $request->string('message')->trim()->toString(),
            interests: $request->input('interests'),
            rawPayload: ['submitted_from' => $request->headers->get('referer')],
        ));

        $message = "Thanks for reaching out! We'll be in touch within 24 hours.";

        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 201);
        }

        return back()->with('status', $message);
    }
}
