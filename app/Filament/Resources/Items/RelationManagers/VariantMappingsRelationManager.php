<?php

namespace App\Filament\Resources\Items\RelationManagers;

use App\Models\ItemAttributeValue;
use App\Models\ItemVariantMapping;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class VariantMappingsRelationManager extends RelationManager
{
    protected static string $relationship = 'variantMappings';

    protected static ?string $title = 'Atribut varian';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('attribute_id')
                    ->label('Atribut')
                    ->relationship(
                        name: 'attribute',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query, mixed $record): Builder {
                            $usedAttributeIds = $this->getOwnerRecord()
                                ->variantMappings()
                                ->when(
                                    $record instanceof ItemVariantMapping,
                                    fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()),
                                )
                                ->pluck('attribute_id')
                                ->all();

                            return $query->whereNotIn('item_attributes.id', array_map(intval(...), $usedAttributeIds));
                        },
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->rule(fn (?ItemVariantMapping $record) => Rule::unique('item_variant_mappings', 'attribute_id')
                        ->where('item_id', $this->getOwnerRecord()->getKey())
                        ->ignore($record?->getKey()))
                    ->validationMessages([
                        'in' => 'Atribut ini sudah dipasangkan ke barang ini.',
                        'unique' => 'Atribut ini sudah dipasangkan ke barang ini.',
                    ]),
                Select::make('attribute_value_id')
                    ->label('Nilai')
                    ->options(fn (Get $get) => ItemAttributeValue::query()
                        ->where('attribute_id', $get('attribute_id'))
                        ->orderBy('value')
                        ->pluck('value', 'id'))
                    ->required()
                    ->searchable()
                    ->rule(fn (Get $get) => Rule::exists('item_attribute_values', 'id')
                        ->where('attribute_id', $get('attribute_id'))),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attribute.name')
                    ->label('Atribut')
                    ->sortable(),
                TextColumn::make('attributeValue.value')
                    ->label('Nilai')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('attributeValue.code')
                    ->label('Kode'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
