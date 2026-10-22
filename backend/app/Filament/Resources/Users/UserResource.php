<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
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
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Accounts';

    protected static ?string $modelLabel = 'Accounts';

    protected static ?int $navigationSort = 45;

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
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('email')->searchable(), TextColumn::make('phone'), TextColumn::make('is_admin')->label('Admin')->badge()->formatStateUsing(fn ($state) => $state ? 'Admin' : 'Member'), TextColumn::make('suspended')->badge()->formatStateUsing(fn ($state) => $state ? 'Suspended' : 'Active')])->recordActions([
            Action::make('access')->label('Change access')->visible(fn ($record) => ! $record->is_admin)->schema([Select::make('suspended')->options([0 => 'Active', 1 => 'Suspend'])->required(), Textarea::make('reason')->required()->minLength(5)->maxLength(1000)])->action(function ($record, array $data) {
                app(CareService::class)->admin(auth()->user());
                DB::transaction(function () use ($record, $data) {
                    $record->forceFill(['suspended' => (bool) $data['suspended']])->save();
                    if ($record->suspended) {
                        $record->tokens()->delete();
                        if ($record->volunteerProfile) {
                            app(CareService::class)->reviewVolunteer(auth()->user(), $record->volunteerProfile, 'suspended', $data['reason']);
                        }
                    }app(CareService::class)->audit(auth()->user(), 'account_access', $record, ['suspended' => $record->suspended, 'reason' => $data['reason']]);
                });
                Notification::make()->title('Account access saved')->success()->send();
            }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
