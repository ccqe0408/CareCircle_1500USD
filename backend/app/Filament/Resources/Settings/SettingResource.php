<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Models\Setting;
use App\Services\CareService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Service settings';

    protected static ?string $modelLabel = 'Service settings';

    protected static ?int $navigationSort = 80;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('key')->disabled()->dehydrated(false), TextInput::make('value')->label('Invitation expiry in minutes')->required()->numeric()->minValue(1)->maxValue(60)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('key')->label('Setting'), TextColumn::make('value')])->recordActions([EditAction::make()->after(fn ($record) => app(CareService::class)->audit(auth()->user(), 'setting_updated', $record))]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSettings::route('/')];
    }
}
