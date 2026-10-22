<?php

namespace App\Filament\Resources\Zones\Pages;

use App\Filament\Resources\Zones\ZoneResource;
use App\Services\CareService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageZones extends ManageRecords
{
    protected static string $resource = ZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->after(fn ($record) => app(CareService::class)->audit(auth()->user(), 'area_created', $record))];
    }
}
