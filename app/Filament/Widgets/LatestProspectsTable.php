<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Prospects\ProspectResource;
use App\Models\Prospect;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
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
            ->headerActions([
                Action::make('viewAll')
                    ->label('All Prospects')
                    ->link()
                    ->icon(Heroicon::ArrowRight)
                    ->iconPosition(IconPosition::After)
                    ->url(ProspectResource::getUrl()),
            ])
            // paginated(false): Filament's table normally wraps the query in
            // its own paginator (10-per-page by default), which silently
            // overrides a plain ->limit() on the query builder. Disabling
            // pagination makes it fetch the query as-is, respecting the
            // limit below.
            ->paginated(false)
            // Same pure-descending-by-submitted_at ordering, and the same
            // spam exclusion, as the Prospects table itself, so this
            // bird's-eye view matches what actually sits at the top of the
            // real list.
            ->query(fn (): Builder => Prospect::query()
                ->notSpam()
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->limit(5))
            ->columns([
                TextColumn::make('submitted_at')->label('Date')->date(),
                TextColumn::make('name')->label('Name')->placeholder('(not given)'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            // Same review slide-over as the Prospects table's own row
            // action, reusing its infolist directly -- a widget has no
            // resource context of its own to auto-resolve it from, so the
            // schema is passed explicitly.
            ->recordActions([
                ViewAction::make('review')
                    ->label('Review')
                    ->icon(Heroicon::Cog6Tooth)
                    ->modalHeading(fn (Prospect $record): string => 'View '.($record->name ?: 'prospect'))
                    ->modalCancelActionLabel('Close')
                    ->slideOver()
                    ->schema(fn (Schema $schema): array => ProspectResource::infolist($schema)->getComponents()),
            ]);
    }
}
