<?php

namespace App\Filament\Resources\Prospects\Pages;

use App\Filament\Resources\Prospects\ProspectResource;
use Filament\Resources\Pages\ManageRecords;

class ManageProspects extends ManageRecords
{
    protected static string $resource = ProspectResource::class;

    /**
     * Fixed channel, always known at mount -- see routes/channels.php for
     * why this can't be scoped to whichever record's modal is open.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            'echo-private:prospects-pipeline,.status.changed' => '$refresh',
        ];
    }
}
