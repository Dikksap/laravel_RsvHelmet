<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemType;
use App\Models\Uom;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class MasterItemSeeder extends Seeder
{
    /**
     * Seed raw materials, semi-finished goods, and consumable items.
     */
    public function run(): void
    {
        foreach ([
            ['code' => 'PCS', 'name' => 'Piece'],
            ['code' => 'LTR', 'name' => 'Liter'],
            ['code' => 'SET', 'name' => 'Set'],
            ['code' => 'UNIT', 'name' => 'Unit'],
        ] as $uom) {
            Uom::updateOrCreate(
                ['code' => $uom['code']],
                ['name' => $uom['name'], 'is_active' => true],
            );
        }

        ItemType::query()
            ->whereRaw('LOWER(name) = ?', ['raw material'])
            ->update(['name' => 'RAW MATERIAL']);

        $itemTypes = [];

        foreach (['RAW MATERIAL', 'SEMI MATERIAL', 'HABIS PAKAI'] as $itemTypeName) {
            $itemTypes[$itemTypeName] = ItemType::updateOrCreate(
                ['name' => $itemTypeName],
                ['is_active' => true],
            );
        }

        $uoms = Uom::query()
            ->whereIn('code', ['PCS', 'LTR', 'SET', 'UNIT'])
            ->get()
            ->keyBy('code');

        foreach (self::items() as $item) {
            Item::updateOrCreate(
                ['item_code' => $item['code']],
                [
                    'item_name' => $item['name'],
                    'item_type_id' => $itemTypes[$item['type']]->getKey(),
                    'base_uom_id' => $uoms[$item['unit']]->getKey(),
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<int, array{code: string, name: string, type: string, unit: string}>
     */
    public static function items(): array
    {
        return array_map(
            fn (array $row): array => [
                'code' => $row[0],
                'name' => $row[1],
                'type' => self::typeForCode($row[0]),
                'unit' => $row[2],
            ],
            self::rows(),
        );
    }

    private static function typeForCode(string $code): string
    {
        return match (true) {
            str_starts_with($code, 'RAW') => 'RAW MATERIAL',
            str_starts_with($code, 'SM') => 'SEMI MATERIAL',
            str_starts_with($code, 'HP') => 'HABIS PAKAI',
            default => throw new InvalidArgumentException("Unknown item type for code [{$code}]."),
        };
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private static function rows(): array
    {
        return [
            // Raw materials.
            ['RAW001', '10 mm Busa Adhesive WB Belakang', 'PCS'],
            ['RAW002', '10 mm Busa Adhesive WB Depan', 'PCS'],
            ['RAW003', '10 mm Busa Adhesive WB Samping Kanan', 'PCS'],
            ['RAW004', '10 mm Busa Adhesive WB Samping Kiri', 'PCS'],
            ['RAW005', '5 mm Busa Adhesive WB Atas', 'PCS'],
            ['RAW006', '5 mm Busa Adhesive WB Belakang', 'PCS'],
            ['RAW007', '5 mm Busa Adhesive WB Depan', 'PCS'],
            ['RAW008', '5 mm Busa Adhesive WB Samping Kanan', 'PCS'],
            ['RAW009', '5 mm Busa Adhesive WB Samping Kiri', 'PCS'],
            ['RAW010', '5 mm Busa Pipi Tambahan WB', 'PCS'],
            ['RAW011', 'Bandela WB', 'PCS'],
            ['RAW012', 'Base Coat Black', 'LTR'],
            ['RAW013', 'Base Coat Platinum Grey', 'LTR'],
            ['RAW014', 'Base Coat White', 'LTR'],
            ['RAW015', 'Base Liner Cheek Pad WB Kanan', 'PCS'],
            ['RAW016', 'Base Liner Cheek Pad WB Kiri', 'PCS'],
            ['RAW017', 'Base Rachet WB Kanan', 'PCS'],
            ['RAW018', 'Base Rachet WB Kiri', 'PCS'],
            ['RAW019', 'Base Top Vent WB', 'PCS'],
            ['RAW020', 'Batok Mentah WB', 'PCS'],
            ['RAW021', 'Busa WB Pipi LG Kanan', 'PCS'],
            ['RAW022', 'Busa WB Pipi LG Kiri', 'PCS'],
            ['RAW023', 'Busa WB Pipi MD Kanan', 'PCS'],
            ['RAW024', 'Busa WB Pipi MD Kiri', 'PCS'],
            ['RAW025', 'Busa WB Pipi XL Kanan', 'PCS'],
            ['RAW026', 'Busa WB Pipi XL Kiri', 'PCS'],
            ['RAW027', 'Busa WB Pipi XXL Kanan', 'PCS'],
            ['RAW028', 'Busa WB Pipi XXL Kiri', 'PCS'],
            ['RAW029', 'Chinstrap WB', 'PCS'],
            ['RAW030', 'Crownpad WB', 'PCS'],
            ['RAW031', 'Decal Cutting BOB', 'SET'],
            ['RAW032', 'Decal Logo Solid Black', 'SET'],
            ['RAW033', 'Decal Logo Solid Silver', 'SET'],
            ['RAW034', 'Decal Motif Carbon', 'SET'],
            ['RAW035', 'Decal Motif Nation', 'SET'],
            ['RAW036', 'Decal Motif Redline', 'SET'],
            ['RAW037', 'Hangtag WB', 'PCS'],
            ['RAW038', 'Inner Box WB', 'PCS'],
            ['RAW039', 'Kain Tape Adhesive', 'SET'],
            ['RAW040', 'Kain Tape Foam Adhesive', 'PCS'],
            ['RAW041', 'Kancing Kuping Metal', 'PCS'],
            ['RAW042', 'Kancing T5 Dalam', 'PCS'],
            ['RAW043', 'LAMPU TL LED 120 CM', 'PCS'],
            ['RAW044', 'Liner Cheek Pad WB', 'PCS'],
            ['RAW045', 'List Karet Atas AB092U8', 'PCS'],
            ['RAW046', 'List Karet Bawah AB093D8', 'PCS'],
            ['RAW047', 'Manual Book', 'PCS'],
            ['RAW048', 'Master Box RSV Koli 8', 'PCS'],
            ['RAW049', 'Mur Flange Nut F M5 Hex 8mm', 'PCS'],
            ['RAW050', 'Mur Nut M5 7mm', 'PCS'],
            ['RAW051', 'Paku Rivet Big 4x10 BK 12', 'PCS'],
            ['RAW052', 'Per Rachet WB', 'PCS'],
            ['RAW053', 'Plastik Zipper 12x18 cm', 'PCS'],
            ['RAW054', 'Polyfoam 8mm', 'PCS'],
            ['RAW055', 'Rachet WB', 'PCS'],
            ['RAW056', 'Ram SS Spoiler WB', 'PCS'],
            ['RAW057', 'Ram SS Top Vent WB', 'PCS'],
            ['RAW058', 'Ribbon Wax 110x300mm', 'PCS'],
            ['RAW059', 'Ring Washer M4', 'PCS'],
            ['RAW060', 'Sarung Hellm WB', 'PCS'],
            ['RAW061', 'SCREW M3 X 7MM', 'PCS'],
            ['RAW062', 'Screw M3x5mm', 'PCS'],
            ['RAW063', 'Screw M3x7mm', 'PCS'],
            ['RAW064', 'SELANG KOMPRESOR SPIRAL 6M', 'PCS'],
            ['RAW065', 'Spoiler WB', 'PCS'],
            ['RAW066', 'Static Visor WB', 'PCS'],
            ['RAW067', 'STEKER', 'PCS'],
            ['RAW068', 'Stoper Visor WB', 'PCS'],
            ['RAW069', 'Stoper Visor WB Kanan', 'PCS'],
            ['RAW070', 'Stoper Visor WB Kiri', 'PCS'],
            ['RAW071', 'Surfacer Grey', 'LTR'],
            ['RAW072', 'Surfacer White', 'LTR'],
            ['RAW073', 'Tang Lancip', 'PCS'],
            ['RAW074', 'Tang Potong', 'PCS'],
            ['RAW075', 'Tekiro Micro Air Satu Set', 'SET'],
            ['RAW076', 'Terminal Lubang 2', 'PCS'],
            ['RAW077', 'Terminal Lubang 4', 'PCS'],
            ['RAW078', 'Thinner Base Coat', 'LTR'],
            ['RAW079', 'Thinner Top Coat', 'PCS'],
            ['RAW080', 'Top Coat Clear Doff', 'LTR'],
            ['RAW081', 'Top Coat Clear Glossy', 'LTR'],
            ['RAW082', 'Top Coat Pearl White', 'LTR'],
            ['RAW083', 'Top Liner EPS WB Polos', 'PCS'],
            ['RAW084', 'Tuas Rachet WB Kanan', 'PCS'],
            ['RAW085', 'Tuas Rachet WB Kiri', 'PCS'],
            ['RAW086', 'Tuas Top Vent WB', 'PCS'],
            ['RAW087', 'Visor WB Clear', 'PCS'],
            ['RAW088', 'Visor WB Dark Smoke', 'PCS'],
            ['RAW089', 'Visor WB Light Smoke', 'PCS'],

            // Semi-finished materials.
            ['SM0001', 'Batok WB Top Coat Motif BOB', 'PCS'],
            ['SM0002', 'Batok WB Top Coat Motif Carbon', 'PCS'],
            ['SM0003', 'Batok WB Top Coat Motif Nation', 'PCS'],
            ['SM0004', 'Batok WB Top Coat Motif Redline', 'PCS'],
            ['SM0005', 'Batok WB Top Coat Solid Black Doff', 'PCS'],
            ['SM0006', 'Batok WB Top Coat Solid Black Glossy', 'PCS'],
            ['SM0007', 'Batok WB Top Coat Solid Platinum Grey', 'PCS'],
            ['SM0008', 'Batok WB Top Coat Solid White Glossy', 'PCS'],
            ['SM0009', 'Interior WB Busa LG', 'SET'],
            ['SM0010', 'Interior WB Busa MD', 'SET'],
            ['SM0011', 'Interior WB Busa XL', 'SET'],
            ['SM0012', 'Interior WB Busa XXL', 'SET'],
            ['SM0013', 'Liner Cheek Pad WB Kanan', 'PCS'],
            ['SM0014', 'Liner Cheek Pad WB Kiri', 'PCS'],
            ['SM0015', 'List Karet WB Atas', 'PCS'],
            ['SM0016', 'List Karet WB Bawah', 'PCS'],
            ['SM0017', 'Manual Book WB', 'PCS'],
            ['SM0018', 'Rachet WB Kanan', 'PCS'],
            ['SM0019', 'Rachet WB Kiri', 'PCS'],
            ['SM0020', 'Top Liner EPS WB', 'PCS'],
            ['SM0021', 'Top Vent WB', 'PCS'],
            ['SM0022', 'Visor Windbreaker Clear', 'PCS'],
            ['SM0023', 'Visor Windbreaker Dark Smoke', 'PCS'],
            ['SM0024', 'Visor Windbreaker Light Smoke', 'PCS'],

            // Consumables.
            ['HP0001', "Busa Poles 3'", 'UNIT'],
            ['HP0002', 'CAIRAN KARET LATEX GAJAH', 'UNIT'],
            ['HP0003', 'CUMPOUND TANURI', 'UNIT'],
            ['HP0004', 'CUTTING STICKER BOB', 'UNIT'],
            ['HP0005', 'Double Tape 1"', 'UNIT'],
            ['HP0006', 'FILTER CAT', 'UNIT'],
            ['HP0007', 'HAMPLAS BULAT 3" 2500 GRIT', 'UNIT'],
            ['HP0008', 'HAMPLAS BULAT 3" 4000 GRIT', 'UNIT'],
            ['HP0009', 'HAMPLAS BULAT 4" 3000 GRIT', 'UNIT'],
            ['HP0010', 'HAMPLAS BULAT 5" 120 GRIT', 'UNIT'],
            ['HP0011', 'HAMPLAS BULAT 5" 240 GRIT', 'UNIT'],
            ['HP0012', 'HAMPLAS BULAT 5" 400 GRIT', 'UNIT'],
            ['HP0013', 'HAMPLAS BULAT 5" 600 GRIT', 'UNIT'],
            ['HP0014', 'HAMPLAS KERTAS 1000 GRIT', 'UNIT'],
            ['HP0015', 'HAMPLAS KERTAS 1500 GRIT', 'UNIT'],
            ['HP0016', 'HAMPLAS KERTAS 2000 GRIT', 'UNIT'],
            ['HP0017', 'HAMPLAS KERTAS 3000 GRIT', 'UNIT'],
            ['HP0018', 'HAMPLAS KERTAS 600 GRIT', 'UNIT'],
            ['HP0019', 'HAMPLAS KERTAS 800 GRIT', 'UNIT'],
            ['HP0020', 'ISI CUTTER BESAR', 'UNIT'],
            ['HP0021', 'ISI CUTTER KECIL', 'UNIT'],
            ['HP0022', 'ISI CUTTER KECIL 30*', 'UNIT'],
            ['HP0023', 'Lakban 2 Inch', 'UNIT'],
            ['HP0024', 'Lakban Washi 1 Inch', 'UNIT'],
            ['HP0025', 'LAMINASI KAIN NYLEX', 'UNIT'],
            ['HP0026', 'LEM DEXTONE SUPER GLUE', 'UNIT'],
            ['HP0027', 'LEM TEMBAK', 'UNIT'],
            ['HP0028', 'Masker', 'UNIT'],
            ['HP0029', 'MATA BOR 4,5 MM', 'UNIT'],
            ['HP0030', 'MATA BOR ASAH CYLINDER 3X8', 'UNIT'],
            ['HP0031', "Mata Obeng Impact 1/4 x PH2 x 1''", 'UNIT'],
            ['HP0032', "Mata Obeng Impact 1/4 x PH2 x 2'", 'UNIT'],
            ['HP0033', 'MATABOR 3MM', 'UNIT'],
            ['HP0034', 'MATABOR 4MM', 'UNIT'],
            ['HP0035', 'MATABOR 5MM', 'UNIT'],
            ['HP0036', 'RIBBON WAX 110MMX300M', 'UNIT'],
            ['HP0037', 'SARUNG TANGAN KAIN', 'UNIT'],
            ['HP0038', 'SUNLIGHT SABUN', 'UNIT'],
            ['HP0039', 'RING WASHER M5 KAIN KANAN', 'UNIT'],
            ['HP0040', 'RING WASHER M5 KAIN KIRI', 'UNIT'],
            ['HP0041', 'UV T/C CLEAR PEARL WHITE RSV RF 010', 'UNIT'],
            ['HP0042', 'RACHET DAN TUAS TANPA PER KIRI', 'UNIT'],
            ['HP0043', 'RACHET DAN TUAS TANPA PER KANAN', 'UNIT'],
            ['HP0044', 'KAIN TAPE ADHESIVE MELENGKUNG', 'UNIT'],
            ['HP0045', 'CHINSTRAP WB KANAN', 'UNIT'],
            ['HP0046', 'SCREW M4', 'UNIT'],
            ['HP0047', 'KOREK API', 'UNIT'],
            ['HP0048', 'LABEL STICKER 75X100MM', 'UNIT'],
            ['HP0049', 'LABEL STICKER DOT MARK LABEL BIRU', 'UNIT'],
            ['HP0050', 'LABEL STICKER QC GOLD DIAMETER 16MM', 'UNIT'],
            ['HP0051', 'LAKBAN KERTAS 2 INCH', 'UNIT'],
            ['HP0052', 'Plastic Zipper 12 X 18 CM', 'UNIT'],
            ['HP0053', 'SARUNG TANGAN LATEX', 'UNIT'],
            ['HP0054', 'SARUNG TANGAN LATEX TEBAL', 'UNIT'],
            ['HP0055', 'SARUNG TANGAN VINYL', 'UNIT'],
        ];
    }
}
