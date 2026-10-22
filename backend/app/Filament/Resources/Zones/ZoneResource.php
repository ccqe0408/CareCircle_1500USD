<?php

namespace App\Filament\Resources\Zones;

use App\Filament\Resources\Zones\Pages\ManageZones;
use App\Models\Zone;
use App\Services\CareService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ZoneResource extends Resource
{
    protected static ?string $model = Zone::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Service areas';

    protected static ?string $modelLabel = 'Service areas';

    protected static ?int $navigationSort = 60;

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required()->maxLength(100), Toggle::make('active')->default(true)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('active')->badge()->formatStateUsing(fn ($state) => $state ? 'Active' : 'Paused')])->recordActions([EditAction::make()->after(fn ($record) => app(CareService::class)->audit(auth()->user(), 'area_updated', $record))]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageZones::route('/')];
    }
}
