<?php

namespace App\Filament\Resources\HelpRequests;

use App\Filament\Resources\HelpRequests\Pages\ManageHelpRequests;
use App\Models\HelpRequest;
use App\Models\User;
use App\Services\CareService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class HelpRequestResource extends Resource
{
    protected static ?string $model = HelpRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-hand-raised';

    protected static ?string $navigationLabel = 'Help requests';

    protected static ?string $modelLabel = 'Help requests';

    protected static ?int $navigationSort = 10;

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
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('id')->label('#')->sortable(), TextColumn::make('title')->searchable()->description(fn ($record) => $record->beneficiary->name), TextColumn::make('zone.name')->label('Area'), TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                'completed' => 'success','escalated' => 'danger','cancelled','closed' => 'gray',default => 'info'
            }), TextColumn::make('volunteer.name')->placeholder('Unassigned'), TextColumn::make('scheduled_start')->dateTime('d M, H:i', 'Asia/Kuala_Lumpur')->sortable(), TextColumn::make('completion_kind')->toggleable(isToggledHiddenByDefault: true),
        ])->filters([SelectFilter::make('status')->options(array_combine(['matching', 'offered', 'accepted', 'in_progress', 'awaiting_confirmation', 'completed', 'escalated', 'cancelled', 'closed'], ['Matching', 'Invited', 'Accepted', 'In progress', 'Awaiting handover', 'Verified complete', 'Needs attention', 'Cancelled', 'Admin closed']))])->recordActions([
            Action::make('details')->icon('heroicon-o-eye')->modalHeading('Request details & history')->modalContent(fn ($record) => view('admin.request-details', ['request' => $record, 'candidates' => app(CareService::class)->candidates($record)]))->modalSubmitAction(false)->modalCancelActionLabel('Close'),
            Action::make('assign')->color('info')->visible(fn ($record) => ! $record->volunteer_id && in_array($record->status, ['matching', 'offered', 'escalated']))->schema([Select::make('volunteer_id')->label('Eligible volunteer')->options(fn ($record) => collect(app(CareService::class)->candidates($record))->where('eligible', true)->pluck('name', 'id'))->required(), Textarea::make('reason')->label('Reason and volunteer agreement')->required()->minLength(5)->maxLength(1000)])->action(function ($record, array $data) {
                app(CareService::class)->assign(auth()->user(), $record, User::findOrFail($data['volunteer_id']), $data['reason']);
                Notification::make()->title('Volunteer assigned')->success()->send();
            }),
            Action::make('rematch')->visible(fn ($record) => ! $record->volunteer_id && in_array($record->status, ['matching', 'offered', 'escalated']))->schema([Textarea::make('reason')->required()->minLength(5)->maxLength(1000)])->action(function ($record, array $data) {
                app(CareService::class)->rematch(auth()->user(), $record, $data['reason']);
                Notification::make()->title('Matching queued')->success()->send();
            }),
            Action::make('close')->color('danger')->visible(fn ($record) => ! in_array($record->status, ['completed', 'cancelled', 'closed']))->schema([Textarea::make('reason')->label('Resolution (does not count as verified completion)')->required()->minLength(5)->maxLength(1000)])->action(function ($record, array $data) {
                app(CareService::class)->close(auth()->user(), $record, $data['reason']);
                Notification::make()->title('Closed with audit record')->success()->send();
            }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageHelpRequests::route('/')];
    }
}
