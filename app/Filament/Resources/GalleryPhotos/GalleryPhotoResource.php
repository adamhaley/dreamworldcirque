<?php

namespace App\Filament\Resources\GalleryPhotos;

use App\Filament\Resources\GalleryPhotos\Pages\ManageGalleryPhotos;
use App\Models\GalleryPhoto;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class GalleryPhotoResource extends Resource
{
    protected static ?string $model = GalleryPhoto::class;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            FileUpload::make('image')
                ->image()
                ->imageEditor()
                ->disk('public')
                ->directory('gallery')
                ->visibility('public')
                ->required(),
            TextInput::make('title')
                ->required()
                ->helperText('Shown in the gallery lightbox before the caption.'),
            TextInput::make('caption'),
            TextInput::make('alt')
                ->label('Alt text')
                ->required()
                ->helperText('Describes the photo for screen readers and search engines.'),
            Toggle::make('in_homepage_carousel')->label('Show in homepage carousel'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image')->disk('public')->imageHeight(56),
                TextColumn::make('title')->description(fn (GalleryPhoto $record): ?string => $record->caption)->wrap()->searchable(),
                ToggleColumn::make('in_homepage_carousel')->label('Homepage carousel'),
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
            'index' => ManageGalleryPhotos::route('/'),
        ];
    }
}
