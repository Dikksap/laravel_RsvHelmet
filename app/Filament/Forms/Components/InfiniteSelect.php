<?php

namespace App\Filament\Forms\Components;

use Filament\Support\Components\Attributes\Exposed;
use Livewire\Attributes\Renderless;
use MrPunyapal\FilamentInfiniteSelect\InfiniteSelect as BaseInfiniteSelect;

class InfiniteSelect extends BaseInfiniteSelect
{
    /**
     * @return array<array<string, mixed>>
     */
    #[Exposed]
    #[Renderless]
    public function getOptionsForJs(): array
    {
        if (! $this->hasPaginatedOptions()) {
            return parent::getOptionsForJs();
        }

        $result = $this->getPaginatedOptions(0, $this->getPerPage(), null);

        return $this->transformOptionsForJs($result['options']);
    }

    /**
     * @return array<array<string, mixed>>
     */
    #[Exposed]
    #[Renderless]
    public function getSearchResultsForJs(string $search): array
    {
        if (! $this->hasPaginatedOptions()) {
            return parent::getSearchResultsForJs($search);
        }

        $result = $this->getPaginatedOptions(0, $this->getPerPage(), $search);

        return $this->transformOptionsForJs($result['options']);
    }
}
