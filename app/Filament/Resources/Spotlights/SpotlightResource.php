<?php

namespace App\Filament\Resources\Spotlights;

use App\Enums\ButtonStyle;
use App\Enums\SpotlightImageSide;
use App\Filament\Resources\Spotlights\Pages\ManageSpotlights;
use App\Models\Spotlight;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SpotlightResource extends Resource
{
    protected static ?string $model = Spotlight::class;

    protected static string|UnitEnum|null $navigationGroup = 'Homepage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    protected static ?string $navigationLabel = 'Spotlight sections';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Copy')->schema([
                TextInput::make('eyebrow'),
                TextInput::make('heading')->required(),
                Textarea::make('body')->rows(4),
                Repeater::make('bullets')
                    ->simple(TextInput::make('bullet')->required())
                    ->reorderable()
                    ->defaultItems(0),
            ]),
            Section::make('Buttons')->schema([
                Repeater::make('buttons')
                    ->hiddenLabel()
                    ->schema([
                        TextInput::make('label')->required(),
                        TextInput::make('url')->label('Link')->required()->placeholder('/services/ or #contact'),
                        Select::make('style')->options(ButtonStyle::class)->default(ButtonStyle::Primary->value)->required(),
                    ])
                    ->columns(3)
                    ->reorderable()
                    ->defaultItems(0),
            ]),
            Section::make('Image')->schema([
                FileUpload::make('image')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('spotlights')
                    ->visibility('public'),
                TextInput::make('image_alt')->label('Alt text'),
                ToggleButtons::make('image_side')->options(SpotlightImageSide::class)->default(SpotlightImageSide::Right)->inline()->required(),
            ]),
            Section::make('Advanced')->collapsed()->schema([
                TextInput::make('anchor')
                    ->helperText('Section id used by homepage navigation links, e.g. "about" for #about.')
                    ->alphaDash(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image')->disk('public')->circular()->imageHeight(48),
                TextColumn::make('heading')->description(fn (Spotlight $record): ?string => $record->eyebrow)->wrap(),
            ])
            ->headerActions([
                CreateAction::make()->slideOver(),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSpotlights::route('/'),
        ];
    }
}
