<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalBarangApiService
{
    protected string $baseUrl;

    protected int $timeout;

    public function __construct(?string $baseUrl = null, ?int $timeout = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.external_barang.base_url', 'http://localhost:8000'), '/');
        $this->timeout = $timeout ?? (int) config('services.external_barang.timeout', 15);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Ambil daftar unit barang dari API publik.
     *
     * @param  array<string, mixed>  $params  (page, limit, variantId, status)
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function getBarangList(array $params = []): array
    {
        try {
            $response = $this->client()->get('/api/public/barang', $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('ExternalBarangApiService: Gagal mengambil daftar barang', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['data' => [], 'meta' => []];
        } catch (Exception $e) {
            Log::error('ExternalBarangApiService: Error koneksi ke API barang', [
                'error' => $e->getMessage(),
            ]);

            return ['data' => [], 'meta' => []];
        }
    }

    /**
     * Ambil detail satu unit barang berdasarkan kodeBarang.
     *
     * @return array<string, mixed>|null
     */
    public function getBarangDetail(string $kodeBarang): ?array
    {
        try {
            $response = $this->client()->get('/api/public/barang/'.urlencode($kodeBarang));

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (Exception $e) {
            Log::error('ExternalBarangApiService: Error getBarangDetail', [
                'kodeBarang' => $kodeBarang,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Ambil data agregat rekap variant per rentang tanggal.
     *
     * @return array{data: array<int, array<string, mixed>>, statuses: array<int, string>, total: int}
     */
    public function getRekapVariant(?string $tanggalAwal = null, ?string $tanggalAkhir = null): array
    {
        try {
            $params = array_filter([
                'tanggalAwal' => $tanggalAwal,
                'tanggalAkhir' => $tanggalAkhir,
            ]);

            $response = $this->client()->get('/api/public/barang/rekap-variant', $params);

            if ($response->successful()) {
                return $response->json();
            }

            return ['data' => [], 'statuses' => [], 'total' => 0];
        } catch (Exception $e) {
            Log::error('ExternalBarangApiService: Error getRekapVariant', [
                'error' => $e->getMessage(),
            ]);

            return ['data' => [], 'statuses' => [], 'total' => 0];
        }
    }

    /**
     * Ambil daftar varian unik dari endpoint rekap atau daftar barang
     * untuk mempermudah pemetaan varian di sistem.
     *
     * @return array<int, array{
     *     external_variant_id: int,
     *     external_variant_code: ?string,
     *     external_product_name: ?string,
     *     external_style_name: ?string,
     *     external_color_name: ?string,
     *     external_size_name: ?string
     * }>
     */
    public function fetchAvailableVariants(): array
    {
        $rekap = $this->getRekapVariant();
        $variants = [];

        if (! empty($rekap['data'])) {
            foreach ($rekap['data'] as $item) {
                $variantId = (int) ($item['variantId'] ?? 0);
                if ($variantId > 0 && ! isset($variants[$variantId])) {
                    $variants[$variantId] = [
                        'external_variant_id' => $variantId,
                        'external_variant_code' => $item['kodeVariant'] ?? null,
                        'external_product_name' => $item['product'] ?? null,
                        'external_style_name' => $item['style'] ?? null,
                        'external_color_name' => $item['color'] ?? null,
                        'external_size_name' => $item['size'] ?? null,
                    ];
                }
            }
        }

        if (empty($variants)) {
            $list = $this->getBarangList(['limit' => 100]);
            foreach ($list['data'] ?? [] as $item) {
                $variant = $item['variant'] ?? null;
                $variantId = (int) ($variant['id'] ?? $item['variantId'] ?? 0);

                if ($variantId > 0 && ! isset($variants[$variantId])) {
                    $variants[$variantId] = [
                        'external_variant_id' => $variantId,
                        'external_variant_code' => $variant['kodeVariant'] ?? null,
                        'external_product_name' => is_array($variant['product'] ?? null) ? ($variant['product']['nama'] ?? null) : null,
                        'external_style_name' => is_array($variant['style'] ?? null) ? ($variant['style']['nama'] ?? null) : null,
                        'external_color_name' => is_array($variant['color'] ?? null) ? ($variant['color']['nama'] ?? null) : null,
                        'external_size_name' => is_array($variant['size'] ?? null) ? ($variant['size']['nama'] ?? null) : null,
                    ];
                }
            }
        }

        return array_values($variants);
    }

    /**
     * Cek apakah API eksternal dapat dihubungi.
     */
    public function ping(): bool
    {
        try {
            $response = $this->client()->timeout(5)->get('/api/public/barang', ['limit' => 1]);

            return $response->successful();
        } catch (Exception) {
            return false;
        }
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson();
    }
}
