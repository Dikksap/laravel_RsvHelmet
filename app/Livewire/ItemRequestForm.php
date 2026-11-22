<?php

namespace App\Livewire;

use App\Models\Division;
use App\Models\Item;
use App\Models\ItemRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Permintaan Barang')]
class ItemRequestForm extends Component
{
    public string $requester_name = '';

    public string $division_id = '';

    public string $note = '';

    /**
     * @var array<int, array{key: int, item_id: string, item_query: string, quantity: string, note: string}>
     */
    public array $lines = [];

    public string $website = '';

    public bool $submitted = false;

    public ?string $submittedCode = null;

    #[Locked]
    public ?int $submittedId = null;

    public int $nextLineKey = 1;

    public function mount(): void
    {
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'key' => $this->nextLineKey++,
            'item_id' => '',
            'item_query' => '',
            'quantity' => '',
            'note' => '',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);

        $this->lines = array_values($this->lines);

        if ($this->lines === []) {
            $this->addLine();
        }
    }

    public function selectItem(int $index, string $itemId): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        $this->lines[$index]['item_id'] = $itemId;
        $this->lines[$index]['item_query'] = '';

        $this->resetValidation("lines.$index.item_id");
        $this->resetValidation("lines.$index.item_query");
    }

    public function clearSelectedItem(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        $this->lines[$index]['item_id'] = '';
    }

    public function submit(): void
    {
        $this->withValidator(function (Validator $validator): void {
            $validator->after(function (Validator $validator): void {
                $seen = [];

                foreach ($this->lines as $index => $line) {
                    $selectedItemId = filled($line['item_id'] ?? null) ? $line['item_id'] : null;
                    $manualName = trim((string) ($line['item_query'] ?? ''));

                    if ($selectedItemId === null && $manualName === '') {
                        $validator->errors()->add("lines.$index.item_query", 'Pilih barang dari daftar atau tulis nama barang.');

                        continue;
                    }

                    $resolvedItemId = $selectedItemId ?? $this->findItemByName($manualName)?->getKey();
                    $duplicateKey = $resolvedItemId !== null
                        ? 'item:'.$resolvedItemId
                        : 'manual:'.mb_strtolower($manualName);

                    if (isset($seen[$duplicateKey])) {
                        $validator->errors()->add("lines.$index.item_query", 'Barang yang sama tidak boleh diminta dua kali.');

                        continue;
                    }

                    $seen[$duplicateKey] = true;
                }
            });
        })->validate();

        $itemRequest = DB::transaction(function (): ItemRequest {
            $itemRequest = ItemRequest::create([
                'requester_name' => $this->requester_name,
                'division_id' => $this->division_id,
                'note' => filled($this->note) ? $this->note : null,
            ]);

            foreach ($this->lines as $line) {
                $itemId = $this->resolveItemId($line);

                $itemRequest->lines()->create([
                    'item_id' => $itemId,
                    'requested_item_name' => $itemId === null ? trim($line['item_query']) : null,
                    'quantity' => $line['quantity'],
                    'note' => filled($line['note']) ? $line['note'] : null,
                ]);
            }

            return $itemRequest;
        });

        $this->submitted = true;
        $this->submittedCode = $itemRequest->code;
        $this->submittedId = $itemRequest->getKey();
        $this->resetValidation();
    }

    public function createAnother(): void
    {
        $this->reset([
            'requester_name',
            'division_id',
            'note',
            'lines',
            'website',
            'submitted',
            'submittedCode',
            'submittedId',
        ]);

        $this->addLine();
        $this->resetValidation();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'requester_name' => ['required', 'string', 'max:100'],
            'division_id' => ['required', 'integer', Rule::exists('divisions', 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'max:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', 'string', Rule::exists('items', 'id')->where('is_active', true)],
            'lines.*.item_query' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'requester_name.required' => 'Nama pemohon wajib diisi.',
            'division_id.required' => 'Divisi wajib dipilih.',
            'division_id.exists' => 'Divisi yang dipilih tidak valid.',
            'note.max' => 'Catatan maksimal 1000 karakter.',
            'lines.required' => 'Tambahkan minimal satu barang.',
            'lines.min' => 'Tambahkan minimal satu barang.',
            'lines.*.item_id.exists' => 'Barang yang dipilih tidak tersedia.',
            'lines.*.item_query.max' => 'Nama barang maksimal 255 karakter.',
            'lines.*.quantity.required' => 'Jumlah wajib diisi.',
            'lines.*.quantity.numeric' => 'Jumlah harus berupa angka.',
            'lines.*.quantity.gt' => 'Jumlah harus lebih dari 0.',
            'lines.*.note.max' => 'Catatan barang maksimal 255 karakter.',
        ];
    }

    public function render(): View
    {
        $items = Item::query()
            ->active()
            ->with('baseUom:id,code')
            ->orderBy('item_name')
            ->get();

        $searchResults = [];

        foreach ($this->lines as $index => $line) {
            if (filled($line['item_id'] ?? null) || blank($line['item_query'] ?? null)) {
                continue;
            }

            $searchResults[$index] = $this->searchItems($line['item_query']);
        }

        $submittedRequest = $this->submittedId === null
            ? null
            : ItemRequest::query()
                ->with(['division', 'lines.item.baseUom'])
                ->findOrFail($this->submittedId);

        $pdfUrl = $submittedRequest === null
            ? null
            : URL::temporarySignedRoute(
                'item-requests.pdf',
                now()->addDays(7),
                ['itemRequest' => $submittedRequest],
            );

        $shareMessage = $submittedRequest === null
            ? null
            : implode("\n", [
                "Permintaan barang {$submittedRequest->code}",
                "Peminta: {$submittedRequest->requester_name}",
                'Divisi: '.($submittedRequest->division?->name ?? '—'),
                'Mohon proses permintaan ini.',
            ]);

        return view('livewire.item-request-form', [
            'divisions' => Division::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'items' => $items,
            'searchResults' => $searchResults,
            'submittedRequest' => $submittedRequest,
            'pdfUrl' => $pdfUrl,
            'shareMessage' => $shareMessage,
        ]);
    }

    /**
     * @param  array{item_id: string, item_query: string, quantity: string, note: string}  $line
     */
    private function resolveItemId(array $line): ?string
    {
        if (filled($line['item_id'] ?? null)) {
            return $line['item_id'];
        }

        $manualName = trim((string) ($line['item_query'] ?? ''));

        return $manualName === '' ? null : $this->findItemByName($manualName)?->getKey();
    }

    private function findItemByName(string $name): ?Item
    {
        return Item::query()
            ->active()
            ->whereRaw('LOWER(item_name) = ?', [mb_strtolower(trim($name))])
            ->first();
    }

    /**
     * @return Collection<int, Item>
     */
    private function searchItems(string $term): Collection
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term));

        return Item::query()
            ->active()
            ->with('baseUom:id,code')
            ->where(function (Builder $query) use ($escaped): void {
                $query->where('item_name', 'like', "%{$escaped}%")
                    ->orWhere('item_code', 'like', "%{$escaped}%");
            })
            ->orderBy('item_name')
            ->limit(10)
            ->get();
    }
}
