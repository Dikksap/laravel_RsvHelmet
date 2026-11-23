<?php

namespace App\Filament\Resources\ExternalItemMappings\Pages;

use App\Filament\Resources\ExternalItemMappings\ExternalItemMappingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExternalItemMapping extends EditRecord
{
    protected static string $resource = ExternalItemMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
