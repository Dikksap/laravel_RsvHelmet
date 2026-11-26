<?php

namespace Tests\Feature;

use App\Services\ExternalBarangApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalBarangApiServiceTest extends TestCase
{
    public function test_get_barang_list_returns_data_and_meta(): void
    {
        Http::fake([
            'http://localhost:8000/api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 101,
                        'kodeBarang' => 'BC001-WB005-181126-0051',
                        'variantId' => 5,
                        'batchId' => 1,
                        'status' => 'FINISHGOOD',
                        'tanggal' => '2026-11-17T17:19:42.416Z',
                        'finishGoodAt' => '2026-11-17T17:20:00.000Z',
                        'variant' => [
                            'id' => 5,
                            'kodeVariant' => 'WB005',
                            'product' => ['id' => 1, 'nama' => 'Windbreaker'],
                            'style' => ['id' => 1, 'nama' => 'Motif'],
                            'color' => ['id' => 2, 'nama' => 'Carbon'],
                            'size' => ['id' => 1, 'nama' => 'LG'],
                        ],
                    ],
                ],
                'meta' => [
                    'page' => 1,
                    'limit' => 20,
                    'total' => 1,
                    'totalPages' => 1,
                ],
            ], 200),
        ]);

        $service = new ExternalBarangApiService('http://localhost:8000');
        $result = $service->getBarangList(['limit' => 1]);

        $this->assertCount(1, $result['data']);
        $this->assertSame('BC001-WB005-181126-0051', $result['data'][0]['kodeBarang']);
        $this->assertSame(5, $result['data'][0]['variantId']);
    }

    public function test_get_barang_detail_returns_unit_or_null(): void
    {
        Http::fake([
            'http://localhost:8000/api/public/barang/BC001-WB005-181126-0051' => Http::response([
                'id' => 101,
                'kodeBarang' => 'BC001-WB005-181126-0051',
                'status' => 'FINISHGOOD',
                'variantId' => 5,
            ], 200),
            'http://localhost:8000/api/public/barang/NOTFOUND' => Http::response([
                'message' => 'Barang tidak ditemukan',
            ], 404),
        ]);

        $service = new ExternalBarangApiService('http://localhost:8000');

        $found = $service->getBarangDetail('BC001-WB005-181126-0051');
        $this->assertNotNull($found);
        $this->assertSame('BC001-WB005-181126-0051', $found['kodeBarang']);

        $notFound = $service->getBarangDetail('NOTFOUND');
        $this->assertNull($notFound);
    }

    public function test_get_rekap_variant_returns_aggregates(): void
    {
        Http::fake([
            'http://localhost:8000/api/public/barang/rekap-variant*' => Http::response([
                'data' => [
                    [
                        'tanggal' => '2026-11-17',
                        'variantId' => 5,
                        'kodeVariant' => 'WB005',
                        'product' => 'Windbreaker',
                        'style' => 'Motif',
                        'color' => 'Carbon',
                        'size' => 'LG',
                        'perStatus' => [
                            'REGISTER' => 0,
                            'FINISHGOOD' => 100,
                        ],
                        'total' => 100,
                    ],
                ],
                'statuses' => ['REGISTER', 'FINISHGOOD', 'OUT'],
                'total' => 100,
            ], 200),
        ]);

        $service = new ExternalBarangApiService('http://localhost:8000');
        $result = $service->getRekapVariant('2026-11-17', '2026-11-17');

        $this->assertCount(1, $result['data']);
        $this->assertSame('WB005', $result['data'][0]['kodeVariant']);
    }

    public function test_status_options_use_api_values_and_only_exclude_configured_statuses_from_sync(): void
    {
        config(['services.external_barang.excluded_statuses' => ['Register', 'QUALITYHOLD']]);

        Http::fake([
            '*api/public/barang/rekap-variant*' => Http::response([
                'statuses' => ['REGISTER', 'QualityHold', 'QualityApproved'],
            ]),
        ]);

        $api = new ExternalBarangApiService;

        $this->assertSame([
            'REGISTER' => 'REGISTER',
            'QualityHold' => 'QualityHold',
            'QualityApproved' => 'QualityApproved',
        ], $api->getStatusOptions());
        $this->assertSame(['QualityApproved' => 'QualityApproved'], $api->getStatusOptions(forSync: true));
    }

    public function test_status_options_are_empty_when_api_does_not_return_statuses(): void
    {
        Http::fake([
            '*api/public/barang/rekap-variant*' => Http::response(['data' => []]),
        ]);

        $api = new ExternalBarangApiService;

        $this->assertSame([], $api->getStatusOptions());
        $this->assertSame([], $api->getStatusOptions(forSync: true));
    }

    public function test_fetch_available_variants_extracts_unique_variants(): void
    {
        Http::fake([
            'http://localhost:8000/api/public/barang/rekap-variant*' => Http::response([
                'data' => [
                    [
                        'variantId' => 5,
                        'kodeVariant' => 'WB005',
                        'product' => 'Windbreaker',
                        'style' => 'Motif',
                        'color' => 'Carbon',
                        'size' => 'LG',
                    ],
                    [
                        'variantId' => 29,
                        'kodeVariant' => 'WB029',
                        'product' => 'Windbreaker',
                        'style' => 'Solid',
                        'color' => 'White Glossy',
                        'size' => 'LG',
                    ],
                ],
            ], 200),
        ]);

        $service = new ExternalBarangApiService('http://localhost:8000');
        $variants = $service->fetchAvailableVariants();

        $this->assertCount(2, $variants);
        $this->assertSame(5, $variants[0]['external_variant_id']);
        $this->assertSame('WB005', $variants[0]['external_variant_code']);
        $this->assertSame(29, $variants[1]['external_variant_id']);
    }

    public function test_ping_returns_true_when_reachable(): void
    {
        Http::fake([
            'http://localhost:8000/api/public/barang*' => Http::response(['data' => []], 200),
        ]);

        $service = new ExternalBarangApiService('http://localhost:8000');
        $this->assertTrue($service->ping());
    }
}
