<?php

namespace App\Filament\Resources\FamilyAuthorizations\Pages;

use App\Filament\Resources\FamilyAuthorizations\FamilyAuthorizationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageFamilyAuthorizations extends ManageRecords
{
    protected static string $resource = FamilyAuthorizationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
