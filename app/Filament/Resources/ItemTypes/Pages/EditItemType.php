<?php

namespace App\Filament\Resources\ItemTypes\Pages;

use App\Filament\Resources\ItemTypes\ItemTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditItemType extends EditRecord
{
    protected static string $resource = ItemTypeResource::class;
}
