<?php

namespace App\Filament\Resources\ReplyExamples;

use App\Filament\Resources\ReplyExamples\Pages\ManageReplyExamples;
use App\Models\ReplyExample;
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

class ReplyExampleResource extends Resource
{
    protected static ?string $model = ReplyExample::class;

    protected static string|UnitEnum|null $navigationGroup = 'Leads';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $modelLabel = 'reply example';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('title')
                ->required()
                ->helperText('A short label for your own reference, e.g. "Wedding inquiry, LA".'),
            Textarea::make('inquiry')
                ->label('Their message (optional)')
                ->rows(5)
                ->helperText('Paste what the client wrote, so the AI can see what you were answering.'),
            Textarea::make('reply')
                ->label('Your reply')
                ->required()
                ->rows(12)
                ->helperText('Paste the email exactly as you sent it, sign-off included. The AI copies your tone and phrasing from these, not the specific details.'),
            Toggle::make('is_active')
                ->label('Use when drafting replies')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')->wrap()->searchable(),
                ToggleColumn::make('is_active')->label('In use'),
            ])
            ->headerActions([
                CreateAction::make()->slideOver(),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ])
            ->recordAction('edit');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReplyExamples::route('/'),
        ];
    }
}
