<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class CommunityWelcome extends Widget
{
    protected string $view = 'filament.widgets.community-welcome';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;
}
