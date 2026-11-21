<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\ItemAttribute;
use App\Models\ItemAttributeValue;
use App\Models\ItemType;
use App\Models\Uom;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Seed the application's master reference data.
     */
    public function run(): void
    {
        $uoms = [
            ['code' => 'PCS', 'name' => 'Piece'],
            ['code' => 'KG', 'name' => 'Kilogram'],
            ['code' => 'BOX', 'name' => 'Box'],
            ['code' => 'CM', 'name' => 'Centimeter'],
            ['code' => 'LTR', 'name' => 'Liter'],
            ['code' => 'SET', 'name' => 'Set'],
            ['code' => 'UNIT', 'name' => 'Unit'],
        ];

        foreach ($uoms as $uom) {
            Uom::updateOrCreate(['code' => $uom['code']], [
                'name' => $uom['name'],
                'is_active' => true,
            ]);
        }

        $divisions = ['Produksi', 'Gudang', 'Purchasing', 'Penjualan', 'Keuangan', 'HRD', 'IT'];

        foreach ($divisions as $division) {
            Division::updateOrCreate(['name' => $division], ['is_active' => true]);
        }

        $itemTypes = ['Product', 'Service', 'RAW MATERIAL', 'SEMI MATERIAL', 'HABIS PAKAI', 'FINISH GOOD'];

        foreach ($itemTypes as $itemType) {
            ItemType::updateOrCreate(['name' => $itemType], ['is_active' => true]);
        }

        $attributes = [
            [
                'name' => 'Warna',
                'description' => 'Warna item',
                'values' => [
                    ['value' => 'Red', 'code' => 'RD'],
                    ['value' => 'Blue', 'code' => 'BL'],
                    ['value' => 'Black', 'code' => 'BK'],
                ],
            ],
            [
                'name' => 'Ukuran',
                'description' => 'Ukuran item',
                'values' => [
                    ['value' => 'S', 'code' => 'S'],
                    ['value' => 'M', 'code' => 'M'],
                    ['value' => 'L', 'code' => 'L'],
                    ['value' => 'MD', 'code' => 'MD'],
                    ['value' => 'LG', 'code' => 'LG'],
                    ['value' => 'XL', 'code' => 'XL'],
                    ['value' => 'XXL', 'code' => 'XXL'],
                ],
            ],
        ];

        foreach ($attributes as $attribute) {
            $record = ItemAttribute::updateOrCreate(
                ['name' => $attribute['name']],
                [
                    'description' => $attribute['description'],
                    'is_active' => true,
                ],
            );

            foreach ($attribute['values'] as $value) {
                ItemAttributeValue::updateOrCreate(
                    [
                        'attribute_id' => $record->getKey(),
                        'value' => $value['value'],
                    ],
                    ['code' => $value['code']],
                );
            }
        }
    }
}
