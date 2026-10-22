<?php

namespace App\Filament\Resources\Beneficiaries;

use App\Filament\Resources\Beneficiaries\Pages\ManageBeneficiaries;
use App\Models\Beneficiary;
use App\Models\Zone;
use App\Services\CareService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BeneficiaryResource extends Resource
{
    protected static ?string $model = Beneficiary::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationLabel = 'Residents & recipients';

    protected static ?string $modelLabel = 'Residents & recipients';

    protected static ?int $navigationSort = 40;

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
        return $schema->components([TextInput::make('name')->required()->maxLength(100), TextInput::make('phone')->required()->maxLength(30), Textarea::make('address')->required()->maxLength(500), Select::make('zone_id')->label('Service area')->options(Zone::where('active', true)->pluck('name', 'id'))->required(), TextInput::make('created_by')->default(fn () => auth()->id())->hidden()->dehydrated()]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([TextColumn::make('name')->searchable(), TextColumn::make('user.name')->label('Resident account')->placeholder('Assisted recipient'), TextColumn::make('zone.name')->label('Area'), TextColumn::make('phone')->searchable(), TextColumn::make('address')->limit(40)])->recordActions([
            EditAction::make()->after(fn ($record) => app(CareService::class)->audit(auth()->user(), 'recipient_updated', $record)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBeneficiaries::route('/')];
    }
}
