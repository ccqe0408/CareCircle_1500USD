<?php

namespace App\Filament\Widgets;

use App\Services\CareService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CommunityStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $s = app(CareService::class)->stats();

        return [Stat::make('Waiting for help', $s['awaiting_help'])->description('Matching or invited')->color('info'), Stat::make('Active tasks', $s['active'])->description('Accepted through handover')->color('primary'), Stat::make('Verified handovers', $s['verified_completed'])->description('Confirmed by one-time code')->color('success'), Stat::make('Needs attention', $s['needs_attention'])->description('Unassigned, overdue or concerns')->color('danger'), Stat::make('Admin closed', $s['admin_closed'])->description('Excluded from verified completion')->color('gray'), Stat::make('Volunteer reviews', $s['pending_volunteers'])->description('Awaiting community verification')->color('warning'), Stat::make('Open concerns', $s['open_incidents'])->description('Resident or volunteer reports')->color('danger'), Stat::make('Cancelled', $s['cancelled'])->description('Cancelled before execution')->color('gray')];
    }
}
