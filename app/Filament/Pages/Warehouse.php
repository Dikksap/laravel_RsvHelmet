<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use UnitEnum;

class Warehouse extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Ringkasan Gudang';

    protected static ?string $title = 'Ringkasan Gudang';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.warehouse';
}
