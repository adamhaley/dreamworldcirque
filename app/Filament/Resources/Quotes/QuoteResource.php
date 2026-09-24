<?php

namespace App\Filament\Resources\Quotes;

use App\Filament\Resources\Quotes\Pages\ManageQuotes;
use App\Models\Quote;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class QuoteResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static string|UnitEnum|null $navigationGroup = 'Homepage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $modelLabel = 'client quote';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Textarea::make('body')
                ->label('Quote')
                ->required()
                ->rows(3)
                ->helperText('Without surrounding quotation marks — the site adds them.'),
            TextInput::make('name')->required(),
            TextInput::make('affiliation')->placeholder('e.g. Coca-Cola North America'),
            Toggle::make('is_active')->label('Show in homepage carousel')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('body')->label('Quote')->limit(80)->wrap()->searchable(),
                TextColumn::make('name')->description(fn (Quote $record): ?string => $record->affiliation)->searchable(),
                ToggleColumn::make('is_active')->label('In carousel'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->recordAction('edit');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageQuotes::route('/'),
        ];
    }
}
