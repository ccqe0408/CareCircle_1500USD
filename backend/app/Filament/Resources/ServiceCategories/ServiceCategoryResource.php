<?php

namespace App\Filament\Resources\ServiceCategories;

use App\Filament\Resources\ServiceCategories\Pages\ManageServiceCategories;
use App\Models\ServiceCategory;
use App\Services\CareService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServiceCategoryResource extends Resource
{
    protected static ?string $model = ServiceCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Service categories';

    protected static ?string $modelLabel = 'Service categories';

    protected static ?int $navigationSort = 70;

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
        return $schema->components([TextInput::make('name')->required()->maxLength(100), Textarea::make('description')->required()->maxLength(500), Toggle::make('active')]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('description')->wrap(), TextColumn::make('active')->badge()->formatStateUsing(fn ($state) => $state ? 'Active' : 'Paused')])->recordActions([EditAction::make()->after(fn ($record) => app(CareService::class)->audit(auth()->user(), 'category_updated', $record))]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageServiceCategories::route('/')];
    }
}
