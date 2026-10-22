<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Audit trail';

    protected static ?string $modelLabel = 'Audit trail';

    protected static ?int $navigationSort = 90;

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
        return $table->defaultSort('id', 'desc')->columns([TextColumn::make('created_at')->dateTime('d M Y H:i:s', 'Asia/Kuala_Lumpur')->sortable(), TextColumn::make('actor.name')->placeholder('System'), TextColumn::make('action')->searchable(), TextColumn::make('subject_type'), TextColumn::make('subject_id'), TextColumn::make('details')->formatStateUsing(fn ($record) => json_encode($record->details))->wrap()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditLogs::route('/')];
    }
}
