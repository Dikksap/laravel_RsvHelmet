<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemAttribute;
use App\Models\ItemAttributeValue;
use App\Models\ItemType;
use App\Models\ItemVariantMapping;
use App\Models\Uom;
use Illuminate\Database\Seeder;

class FinishGoodItemSeeder extends Seeder
{
    /**
     * Seed the finished good windbreaker items as parent items with size variants.
     */
    public function run(): void
    {
        $itemType = ItemType::updateOrCreate(
            ['name' => 'FINISH GOOD'],
            ['is_active' => true],
        );

        $uom = Uom::query()->where('code', 'PCS')->firstOrFail();

        $sizeAttribute = ItemAttribute::updateOrCreate(
            ['name' => 'Ukuran'],
            [
                'description' => 'Ukuran item',
                'is_active' => true,
            ],
        );

        $sizes = [];

        foreach (['MD', 'LG', 'XL', 'XXL'] as $size) {
            $sizes[$size] = ItemAttributeValue::updateOrCreate(
                [
                    'attribute_id' => $sizeAttribute->getKey(),
                    'value' => $size,
                ],
                ['code' => $size],
            );
        }

        foreach ($this->motifs() as $parentCode => $motif) {
            $parent = Item::updateOrCreate(
                ['item_code' => $parentCode],
                [
                    'item_name' => $motif['name'],
                    'item_type_id' => $itemType->getKey(),
                    'base_uom_id' => $uom->getKey(),
                    'parent_item_id' => null,
                    'has_variants' => true,
                    'is_active' => true,
                ],
            );

            foreach ($motif['variants'] as $itemCode => $variant) {
                $child = Item::updateOrCreate(
                    ['item_code' => $itemCode],
                    [
                        'item_name' => $variant['name'],
                        'item_type_id' => $itemType->getKey(),
                        'base_uom_id' => $uom->getKey(),
                        'parent_item_id' => $parent->getKey(),
                        'has_variants' => false,
                        'is_active' => true,
                    ],
                );

                ItemVariantMapping::updateOrCreate(
                    [
                        'item_id' => $child->getKey(),
                        'attribute_id' => $sizeAttribute->getKey(),
                    ],
                    ['attribute_value_id' => $sizes[$variant['size']]->getKey()],
                );
            }
        }
    }

    /**
     * @return array<string, array{name: string, variants: array<string, array{name: string, size: string}>}>
     */
    private function motifs(): array
    {
        return [
            'WB-MOTIF-BOB' => [
                'name' => 'Windbreaker Motif BOB',
                'variants' => [
                    'G00021' => ['name' => 'Windbreaker Motif BOB MD', 'size' => 'MD'],
                    'G00022' => ['name' => 'Windbreaker Motif BOB LG', 'size' => 'LG'],
                    'G00023' => ['name' => 'Windbreaker Motif BOB XL', 'size' => 'XL'],
                    'G00024' => ['name' => 'Windbreaker Motif BOB XXL', 'size' => 'XXL'],
                ],
            ],
            'WB-MOTIF-CARBON' => [
                'name' => 'Windbreaker Motif Carbon',
                'variants' => [
                    'G00001' => ['name' => 'Windbreaker Motif Carbon MD', 'size' => 'MD'],
                    'G00002' => ['name' => 'Windbreaker Motif Carbon LG', 'size' => 'LG'],
                    'G00003' => ['name' => 'Windbreaker Motif Carbon XL', 'size' => 'XL'],
                    'G00004' => ['name' => 'Windbreaker Motif Carbon XXL', 'size' => 'XXL'],
                ],
            ],
            'WB-MOTIF-NATION' => [
                'name' => 'Windbreaker Motif Nation',
                'variants' => [
                    'G00005' => ['name' => 'Windbreaker Motif Nation MD', 'size' => 'MD'],
                    'G00006' => ['name' => 'Windbreaker Motif Nation LG', 'size' => 'LG'],
                    'G00007' => ['name' => 'Windbreaker Motif Nation XL', 'size' => 'XL'],
                    'G00008' => ['name' => 'Windbreaker Motif Nation XXL', 'size' => 'XXL'],
                ],
            ],
            'WB-MOTIF-REDLINE' => [
                'name' => 'Windbreaker Motif Redline',
                'variants' => [
                    'G00009' => ['name' => 'Windbreaker Motif Redline MD', 'size' => 'MD'],
                    'G00010' => ['name' => 'Windbreaker Motif Redline LG', 'size' => 'LG'],
                    'G00011' => ['name' => 'Windbreaker Motif Redline XL', 'size' => 'XL'],
                    'G00012' => ['name' => 'Windbreaker Motif Redline XXL', 'size' => 'XXL'],
                ],
            ],
            'WB-SOLID-BLACK-DOFF' => [
                'name' => 'Windbreaker Solid Black Doff',
                'variants' => [
                    'G00013' => ['name' => 'Windbreaker Solid Black Doff MD', 'size' => 'MD'],
                    'G00014' => ['name' => 'Windbreaker Solid Black Doff LG', 'size' => 'LG'],
                    'G00015' => ['name' => 'Windbreaker Solid Black Doff XL', 'size' => 'XL'],
                    'G00016' => ['name' => 'Windbreaker Solid Black Doff XXL', 'size' => 'XXL'],
                ],
            ],
            'WB-SOLID-BLACK-GLOSSY' => [
                'name' => 'Windbreaker Solid Black Glossy',
                'variants' => [
                    'G00017' => ['name' => 'Windbreaker Solid Black Glossy MD', 'size' => 'MD'],
                    'G00018' => ['name' => 'Windbreaker Solid Black Glossy LG', 'size' => 'LG'],
                    'G00019' => ['name' => 'Windbreaker Solid Black Glossy XL', 'size' => 'XL'],
                    'G00020' => ['name' => 'Windbreaker Solid Black Glossy XXL', 'size' => 'XXL'],
                ],
            ],
            'WB-SOLID-PLATINUM-GREY' => [
                'name' => 'Windbreaker Solid Platinum Grey',
                'variants' => [
                    'G00025' => ['name' => 'Windbreaker Solid Platinum Grey MD', 'size' => 'MD'],
                    'G00026' => ['name' => 'Windbreaker Solid Platinum Grey LG', 'size' => 'LG'],
                    'G00027' => ['name' => 'Windbreaker Solid Platinum Grey XL', 'size' => 'XL'],
                    'G00028' => ['name' => 'Windbreaker Solid Platinum Grey XXL', 'size' => 'XXL'],
                ],
            ],
            'WB-SOLID-WHITE-GLOSSY' => [
                'name' => 'Windbreaker Solid White Glossy',
                'variants' => [
                    'G00029' => ['name' => 'Windbreaker Solid White Glossy MD', 'size' => 'MD'],
                    'G00030' => ['name' => 'Windbreaker Solid White Glossy LG', 'size' => 'LG'],
                    'G00031' => ['name' => 'Windbreaker Solid White Glossy XL', 'size' => 'XL'],
                    'G00032' => ['name' => 'Windbreaker Solid White Glossy XXL', 'size' => 'XXL'],
                ],
            ],
        ];
    }
}
