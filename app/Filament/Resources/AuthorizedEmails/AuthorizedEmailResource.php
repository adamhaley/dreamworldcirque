<?php

namespace App\Filament\Resources\AuthorizedEmails;

use App\Enums\UserRole;
use App\Filament\Resources\AuthorizedEmails\Pages\ManageAuthorizedEmails;
use App\Models\AuthorizedEmail;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AuthorizedEmailResource extends Resource
{
    protected static ?string $model = AuthorizedEmail::class;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Allowlist';

    protected static ?string $modelLabel = 'allowed email';

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->helperText('The Google account (or password login email) allowed into the admin.'),
            Select::make('role')
                ->options(UserRole::class)
                ->required()
                ->default(UserRole::Editor),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('role')->badge()->sortable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('updated_at')->since()->sortable(),
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
            'index' => ManageAuthorizedEmails::route('/'),
        ];
    }
}
