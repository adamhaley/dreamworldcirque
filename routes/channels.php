<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Fixed, page-level channel (not per-record) -- Livewire only computes and
// sends its getListeners() array once, at component mount, so a channel
// name scoped to whichever record's modal happens to be open at that
// moment would go stale the instant a different record is viewed. See
// wiki/patterns/push-reactivity-not-polling.md.
Broadcast::channel('prospects-pipeline', fn ($user): bool => $user->isAdmin());
