<?php

namespace App\Filament\Resources\Beneficiaries\Pages;

use App\Filament\Resources\Beneficiaries\BeneficiaryResource;
use App\Services\CareService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBeneficiaries extends ManageRecords
{
    protected static string $resource = BeneficiaryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->after(fn ($record) => app(CareService::class)->audit(auth()->user(), 'recipient_created', $record))];
    }
}
