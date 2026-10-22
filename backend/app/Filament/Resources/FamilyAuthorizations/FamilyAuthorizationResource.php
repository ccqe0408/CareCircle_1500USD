<?php

namespace App\Filament\Resources\FamilyAuthorizations;

use App\Filament\Resources\FamilyAuthorizations\Pages\ManageFamilyAuthorizations;
use App\Models\FamilyAuthorization;
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

class FamilyAuthorizationResource extends Resource
{
    protected static ?string $model = FamilyAuthorization::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Family authorizations';

    protected static ?string $modelLabel = 'Family authorizations';

    protected static ?int $navigationSort = 30;

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
        return $table->defaultSort('created_at', 'desc')->columns([TextColumn::make('beneficiary.name')->label('Recipient')->searchable(), TextColumn::make('user.name')->label('Family member')->searchable(), TextColumn::make('relationship'), TextColumn::make('status')->badge(), TextColumn::make('verified_at')->dateTime('d M H:i', 'Asia/Kuala_Lumpur')])->filters([SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'revoked' => 'Revoked'])])->recordActions([
            Action::make('review')->schema([Textarea::make('consent')->default(fn ($record) => $record->consent_note)->disabled()->dehydrated(false), Select::make('status')->options(['approved' => 'Approve', 'rejected' => 'Reject', 'revoked' => 'Revoke'])->required(), Textarea::make('note')->label('Consent verification / decision reason')->required()->minLength(5)->maxLength(2000)])->action(function ($record, array $data) {
                app(CareService::class)->reviewFamily(auth()->user(), $record, $data['status'], $data['note']);
                Notification::make()->title('Authorization updated')->success()->send();
            }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFamilyAuthorizations::route('/')];
    }
}
