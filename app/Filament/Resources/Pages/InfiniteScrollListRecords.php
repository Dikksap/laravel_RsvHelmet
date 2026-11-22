<?php

namespace App\Filament\Resources\Pages;

use Fibtegis\FilamentInfiniteScroll\Concerns\InteractsWithInfiniteScroll;
use Filament\Resources\Pages\ListRecords;

abstract class InfiniteScrollListRecords extends ListRecords
{
    use InteractsWithInfiniteScroll;
}
