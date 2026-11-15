<?php

namespace App\Http\Controllers;

use App\Models\Prospect;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShowProspectReplyAttachmentController extends Controller
{
    /**
     * Streams a private reply attachment, looked up by its index in the prospect's list (never a request path).
     */
    public function __invoke(Request $request, Prospect $prospect, int $index): StreamedResponse
    {
        $user = $request->user();

        abort_unless(
            $user !== null
                && $user->canAccessPanel(Filament::getPanel('admin'))
                && $user->can('view', $prospect),
            403,
        );

        $entry = array_values($prospect->reply_attachments ?? [])[$index] ?? null;

        abort_unless(is_array($entry) && ($entry['disk'] ?? null) === 'local', 404);

        $path = $entry['path'] ?? null;
        $prefix = 'prospect-replies/'.$prospect->getKey().'/';

        abort_unless(
            is_string($path)
                && str_starts_with($path, $prefix)
                && ! str_contains($path, '..')
                && ! str_contains($path, "\0"),
            404,
        );

        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $entry['name'] ?? basename($path), disposition: 'inline');
    }
}
