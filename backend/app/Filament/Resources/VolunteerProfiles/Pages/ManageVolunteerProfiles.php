<?php

namespace App\Filament\Resources\VolunteerProfiles\Pages;

use App\Filament\Resources\VolunteerProfiles\VolunteerProfileResource;
use Filament\Resources\Pages\ManageRecords;

class ManageVolunteerProfiles extends ManageRecords
{
    protected static string $resource = VolunteerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
