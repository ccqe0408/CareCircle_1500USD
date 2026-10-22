<?php

namespace App\Filament\Resources\HelpRequests\Pages;

use App\Filament\Resources\HelpRequests\HelpRequestResource;
use App\Models\Beneficiary;
use App\Models\ServiceCategory;
use App\Services\CareService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageHelpRequests extends ManageRecords
{
    protected static string $resource = HelpRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('assist')->label('Create assisted request')->icon('heroicon-o-plus')->schema([
            Select::make('beneficiary_id')->label('Service recipient')->options(Beneficiary::pluck('name', 'id'))->searchable()->required(), Select::make('service_category_id')->label('Category')->options(ServiceCategory::where('active', true)->pluck('name', 'id'))->required(), TextInput::make('title')->required()->minLength(5)->maxLength(120), Textarea::make('pickup_address')->required()->minLength(5)->maxLength(500), Textarea::make('notes')->maxLength(1000), DateTimePicker::make('scheduled_start')->timezone('Asia/Kuala_Lumpur')->seconds(false)->required(), DateTimePicker::make('scheduled_end')->timezone('Asia/Kuala_Lumpur')->seconds(false)->required(), Toggle::make('prepaid_confirmed')->label('Prepaid, non-emergency and within service rules')->accepted()->required(),
        ])->action(function (array $data) {
            app(CareService::class)->createRequest(auth()->user(), $data);
            Notification::make()->title('Assisted request created')->success()->send();
        })];
    }
}
