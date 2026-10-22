<?php

namespace App\Filament\Resources\VolunteerProfiles;

use App\Filament\Resources\VolunteerProfiles\Pages\ManageVolunteerProfiles;
use App\Models\VolunteerProfile;
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

class VolunteerProfileResource extends Resource
{
    protected static ?string $model = VolunteerProfile::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Volunteers';

    protected static ?string $modelLabel = 'Volunteers';

    protected static ?int $navigationSort = 20;

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
        return $table->defaultSort('created_at', 'desc')->columns([TextColumn::make('user.name')->searchable(), TextColumn::make('user.email')->searchable(), TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
            'approved' => 'success','pending' => 'warning',default => 'danger'
        }), TextColumn::make('max_tasks'), TextColumn::make('verified_at')->dateTime('d M H:i', 'Asia/Kuala_Lumpur')])->filters([SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'suspended' => 'Suspended'])])->recordActions([
            Action::make('review')->schema([Select::make('status')->options(['approved' => 'Approve', 'rejected' => 'Reject', 'suspended' => 'Suspend'])->required(), Textarea::make('note')->label('Identity / area checks and reason')->required()->minLength(5)->maxLength(2000)])->action(function ($record, array $data) {
                app(CareService::class)->reviewVolunteer(auth()->user(), $record, $data['status'], $data['note']);
                Notification::make()->title('Review saved')->success()->send();
            }),
            Action::make('details')->modalContent(fn ($record) => view('admin.volunteer-details', ['profile' => $record]))->modalSubmitAction(false),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageVolunteerProfiles::route('/')];
    }
}
