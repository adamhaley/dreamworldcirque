<?php

namespace App\Filament\Widgets;

use App\Models\Prospect;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestProspectsTable extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest leads')
            // paginated(false): Filament's table normally wraps the query in
            // its own paginator (10-per-page by default), which silently
            // overrides a plain ->limit() on the query builder. Disabling
            // pagination makes it fetch the query as-is, respecting the
            // limit below.
            ->paginated(false)
            // Same pure-descending-by-submitted_at ordering as the
            // Prospects table itself, so this bird's-eye view matches
            // what actually sits at the top of the real list.
            ->query(fn (): Builder => Prospect::query()
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->limit(5))
            ->columns([
                TextColumn::make('submitted_at')->label('Date')->date(),
                TextColumn::make('name')->label('Name')->placeholder('(not given)'),
                TextColumn::make('status')->label('Status')->badge(),
            ]);
    }
}
