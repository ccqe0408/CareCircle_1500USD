<?php

namespace App\Filament\Resources\Incidents;

use App\Filament\Resources\Incidents\Pages\ManageIncidents;
use App\Models\Incident;
use App\Services\CareService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IncidentResource extends Resource
{
    protected static ?string $model = Incident::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Concerns & complaints';

    protected static ?string $modelLabel = 'Concerns & complaints';

    protected static ?int $navigationSort = 50;

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
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([TextColumn::make('help_request_id')->label('Request #'), TextColumn::make('reporter.name'), TextColumn::make('description')->wrap()->limit(100)->searchable(), TextColumn::make('status')->badge()->color(fn ($state) => $state === 'open' ? 'danger' : 'success'), TextColumn::make('resolution')->wrap()->limit(100)])->filters([SelectFilter::make('status')->options(['open' => 'Open', 'resolved' => 'Resolved'])])->recordActions([
            Action::make('resolve')->visible(fn ($record) => $record->status === 'open')->schema([Textarea::make('resolution')->required()->minLength(5)->maxLength(2000)])->action(function ($record, array $data) {
                app(CareService::class)->resolveIncident(auth()->user(), $record, $data['resolution']);
                Notification::make()->title('Concern resolved; review task separately')->success()->send();
            }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageIncidents::route('/')];
    }
}
