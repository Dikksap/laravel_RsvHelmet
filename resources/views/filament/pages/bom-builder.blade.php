<x-filament-panels::page>
    @php
        $root = $this->rootItem();
        $treeRows = $this->tree();
        $summary = $this->summary();
        $shopping = $this->shoppingList();
        $library = $this->libraryItems();
        $templates = $this->templateItems();
        $addOptions = $this->addOptions();
        $addTarget = $this->addTargetLabel();

        $rupiah = fn (float|int $value): string => 'Rp '.number_format((float) $value, 0, ',', '.');
        $qty = function (float|int $value): string {
            $formatted = number_format((float) $value, 3, ',', '.');

            return rtrim(rtrim($formatted, '0'), ',');
        };

        $libraryGroups = [];

        foreach ($library as $item) {
            $libraryGroups[$item->type?->name ?? 'Lainnya'][] = $item;
        }

        $shoppingTotal = array_sum(array_column($shopping, 'subtotal'));
    @endphp

    {{ $this->table }}

    <div class="bom-app" x-data="bomBuilder">
        <div class="bom-grid">
            <aside class="bom-side">
                <div class="bom-card">
                    <h3 class="bom-card-title">Pustaka komponen</h3>
                    <input
                        class="bom-search"
                        type="search"
                        placeholder="Cari nama atau kode komponen"
                        aria-label="Cari komponen"
                        wire:model.live.debounce.300ms="search"
                    >
                </div>

                <div class="bom-card bom-lib-list">
                    @forelse ($libraryGroups as $typeName => $items)
                        <div class="bom-group-label">{{ $typeName }}</div>

                        @foreach ($items as $item)
                            <div
                                class="bom-lib-item"
                                draggable="true"
                                x-on:dragstart="startLib($event, '{{ $item->getKey() }}')"
                                x-on:dragend="endDrag($event)"
                            >
                                <div class="bom-lib-info">
                                    <b>{{ $item->item_name }}</b>
                                    <small>{{ $item->item_code }} · {{ $rupiah($item->standard_cost) }} / {{ $item->baseUom?->code ?? '—' }}</small>
                                </div>
                                <button
                                    type="button"
                                    class="bom-plus"
                                    title="Tambahkan ke {{ $root?->item_name ?? 'komposisi' }}"
                                    wire:click="addToRoot('{{ $item->getKey() }}')"
                                    @disabled(! $root)
                                >+</button>
                            </div>
                        @endforeach
                    @empty
                        <p class="bom-muted">Tidak ada komponen yang cocok.</p>
                    @endforelse
                </div>

                <div class="bom-card">
                    <h3 class="bom-card-title">Template komposisi</h3>
                    <p class="bom-muted bom-small">Barang yang sudah punya komposisi bisa dimuat sebagai barang yang disusun, atau disisipkan sebagai sub-rakitan.</p>

                    <div class="bom-tpl-list">
                        @forelse ($templates as $template)
                            <div class="bom-tpl">
                                <div class="bom-tpl-info">
                                    <b>{{ $template->item_name }}</b>
                                    <small>{{ $template->item_code }} · {{ $template->components_count }} komponen</small>
                                </div>
                                <div class="bom-tpl-actions">
                                    <button type="button" wire:click="loadTemplate('{{ $template->getKey() }}')">Muat</button>
                                    <button type="button" wire:click="insertTemplate('{{ $template->getKey() }}')" @disabled(! $root)>Sisipkan</button>
                                </div>
                            </div>
                        @empty
                            <p class="bom-muted">Belum ada template komposisi.</p>
                        @endforelse
                    </div>
                </div>
            </aside>

            <section class="bom-work">
                <div class="bom-card">
                    {{ $this->form }}

                    @if ($root)
                        <div class="bom-root-info">
                            <span class="bom-level bom-l0">L0</span>
                            <b>{{ $root->item_code }}</b>
                            <span>{{ $root->item_name }}</span>
                            <span class="bom-muted">· {{ $root->baseUom?->code ?? 'unit' }}</span>
                        </div>
                    @endif
                </div>

                <div class="bom-stats">
                    <div class="bom-stat">
                        <span>Biaya per unit</span>
                        <b>{{ $rupiah($summary['per_unit']) }}</b>
                    </div>
                    <div class="bom-stat">
                        <span>
                            Biaya untuk
                            <input
                                class="bom-qty-input"
                                type="number"
                                min="1"
                                aria-label="Jumlah produksi"
                                wire:model.live.debounce.500ms="productionQty"
                            > unit
                        </span>
                        <b>{{ $rupiah($summary['total']) }}</b>
                    </div>
                    <div class="bom-stat">
                        <span>Baris BOM</span>
                        <b>{{ $summary['rows'] }}</b>
                    </div>
                    <div class="bom-stat">
                        <span>Jenis komponen</span>
                        <b>{{ $summary['leaves'] }}</b>
                    </div>
                </div>

                <div class="bom-card">
                    <div class="bom-tabs" role="tablist">
                        <button
                            type="button"
                            role="tab"
                            class="bom-tab {{ $tab === 'tree' ? 'on' : '' }}"
                            aria-selected="{{ $tab === 'tree' ? 'true' : 'false' }}"
                            wire:click="setTab('tree')"
                        >Struktur BOM</button>
                        <button
                            type="button"
                            role="tab"
                            class="bom-tab {{ $tab === 'shopping' ? 'on' : '' }}"
                            aria-selected="{{ $tab === 'shopping' ? 'true' : 'false' }}"
                            wire:click="setTab('shopping')"
                        >Daftar belanja</button>
                    </div>

                    @if (! $root)
                        <div class="bom-empty">
                            <b>Belum ada barang yang disusun</b>
                            Pilih barang pada kolom di atas, atau klik Kelola pada daftar barang.
                        </div>
                    @elseif ($tab === 'tree')
                        @if ($summary['max_level'] > 1)
                            <div class="bom-chips">
                                <span class="bom-muted bom-small">Tampilkan</span>
                                <button
                                    type="button"
                                    class="bom-chip {{ $visibleLevel === 0 ? 'on' : '' }}"
                                    wire:click="setVisibleLevel(0)"
                                >Semua level</button>
                                @for ($level = 1; $level <= $summary['max_level']; $level++)
                                    <button
                                        type="button"
                                        class="bom-chip {{ $visibleLevel === $level ? 'on' : '' }}"
                                        wire:click="setVisibleLevel({{ $level }})"
                                    >Sampai L{{ $level }}</button>
                                @endfor
                            </div>
                        @endif

                        <p class="bom-muted bom-small bom-inline-hint">
                            Seret ⠿ untuk memindahkan baris. Lepas di tengah baris untuk menjadikannya sub-rakitan, atau di tepi atas/bawah untuk mengatur urutan.
                            Mengubah isi sub-rakitan ikut memengaruhi semua barang yang memakainya.
                        </p>

                        @if ($addParentId !== null)
                            <div class="bom-add-panel">
                                <div class="bom-add-head">
                                    <div>
                                        <b>Tambah komponen</b>
                                        <span class="bom-muted">ke {{ $addTarget }}</span>
                                    </div>
                                    <button type="button" class="bom-icon-btn" wire:click="closeAdd" aria-label="Tutup">×</button>
                                </div>

                                <input
                                    class="bom-search"
                                    type="search"
                                    placeholder="Cari nama atau kode komponen"
                                    aria-label="Cari komponen yang akan ditambahkan"
                                    wire:model.live.debounce.300ms="addSearch"
                                >

                                <div class="bom-add-list">
                                    @forelse ($addOptions as $option)
                                        <button
                                            type="button"
                                            class="bom-add-option"
                                            wire:click="pickComponent('{{ $option->getKey() }}')"
                                        >
                                            <span>
                                                <b>{{ $option->item_name }}</b>
                                                <small>{{ $option->item_code }} · {{ $rupiah($option->standard_cost) }} / {{ $option->baseUom?->code ?? '—' }}</small>
                                            </span>
                                            <span class="bom-plus">+</span>
                                        </button>
                                    @empty
                                        <p class="bom-muted">Tidak ada komponen yang cocok.</p>
                                    @endforelse
                                </div>
                            </div>
                        @endif

                        <div class="bom-scroll">
                            <div
                                class="bom-tree"
                                x-on:dragover.prevent
                                x-on:drop="dropRoot($event)"
                            >
                                <div class="bom-thead" aria-hidden="true">
                                    <span></span>
                                    <span>Level</span>
                                    <span>No</span>
                                    <span>Komponen</span>
                                    <span class="r">Jumlah</span>
                                    <span>Satuan</span>
                                    <span class="r">Harga</span>
                                    <span class="r">Subtotal</span>
                                    <span></span>
                                </div>

                                <div class="bom-row bom-root">
                                    <span></span>
                                    <span class="bom-level bom-l0">L0</span>
                                    <span class="bom-no">0</span>
                                    <span class="bom-name">{{ $root->item_name }}</span>
                                    <span class="r">1</span>
                                    <span>{{ $root->baseUom?->code ?? 'unit' }}</span>
                                    <span class="r bom-muted">—</span>
                                    <span class="r bom-sub">{{ $rupiah($summary['per_unit']) }}</span>
                                    <span></span>
                                </div>

                                <div
                                    class="bom-tree-body"
                                    x-on:dragover.prevent
                                    x-on:drop="dropRoot($event)"
                                >
                                    @forelse ($treeRows as $row)
                                        <div
                                            class="bom-row {{ $row['is_active'] ? '' : 'is-inactive' }}"
                                            wire:key="bom-row-{{ $row['parent_id'] }}-{{ $row['item']->getKey() }}"
                                            style="--bom-depth: {{ $row['depth'] }}"
                                            x-bind:class="{
                                                'bom-over-before': overKey === '{{ $row['key'] }}' && overZone === 'before',
                                                'bom-over-after': overKey === '{{ $row['key'] }}' && overZone === 'after',
                                                'bom-over-in': overKey === '{{ $row['key'] }}' && overZone === 'in'
                                            }"
                                            x-on:dragover="dragOver($event, '{{ $row['key'] }}')"
                                            x-on:dragleave="dragLeave($event, '{{ $row['key'] }}')"
                                            x-on:drop="dropRow($event, '{{ $row['parent_id'] }}', {{ $row['index'] }}, '{{ $row['item']->getKey() }}')"
                                        >
                                            <span
                                                class="bom-grip"
                                                draggable="true"
                                                title="Seret untuk memindahkan"
                                                x-on:dragstart="startRow($event, '{{ $row['parent_id'] }}', {{ $row['index'] }})"
                                                x-on:dragend="endDrag($event)"
                                            >⠿</span>

                                            <span class="bom-level bom-l{{ min($row['level'], 4) }}">L{{ $row['level'] }}</span>
                                            <span class="bom-no">{{ $row['number'] }}</span>

                                            <div class="bom-name">
                                                <div class="bom-name-line">
                                                    @if ($row['has_children'])
                                                        <button
                                                            type="button"
                                                            class="bom-chev"
                                                            aria-label="Lipat atau bentangkan"
                                                            wire:click="toggleCollapsed('{{ $row['item']->getKey() }}')"
                                                        >
                                                            <x-filament::icon
                                                                icon="heroicon-m-chevron-right"
                                                                class="bom-chev-icon {{ $row['collapsed'] ? '' : 'is-open' }}"
                                                            />
                                                        </button>
                                                    @else
                                                        <span class="bom-chev-spacer"></span>
                                                    @endif

                                                    <b>{{ $row['item']->item_name }}</b>
                                                    <span class="bom-code">{{ $row['item']->item_code }}</span>

                                                    @unless ($row['is_active'])
                                                        <span class="bom-tag">nonaktif</span>
                                                    @endunless
                                                </div>

                                                <input
                                                    class="bom-note"
                                                    type="text"
                                                    value="{{ $row['note'] }}"
                                                    placeholder="Catatan..."
                                                    aria-label="Catatan {{ $row['item']->item_name }}"
                                                    wire:change="updateNote('{{ $row['parent_id'] }}', {{ $row['index'] }}, $event.target.value)"
                                                >
                                            </div>

                                            <input
                                                class="bom-qty"
                                                type="number"
                                                min="0.001"
                                                step="any"
                                                value="{{ $row['quantity'] }}"
                                                aria-label="Jumlah {{ $row['item']->item_name }}"
                                                wire:change="updateQuantity('{{ $row['parent_id'] }}', {{ $row['index'] }}, $event.target.value)"
                                            >

                                            <span class="bom-unit">{{ $row['item']->baseUom?->code ?? '—' }}</span>
                                            <span class="r bom-cost">{{ $rupiah($row['unit_cost']) }}</span>
                                            <span class="r bom-sub">{{ $rupiah($row['subtotal']) }}</span>

                                            <div class="bom-row-actions">
                                                <button
                                                    type="button"
                                                    class="bom-icon-btn"
                                                    title="Tambah sub-komponen"
                                                    aria-label="Tambah sub-komponen ke {{ $row['item']->item_name }}"
                                                    wire:click="openAdd('{{ $row['item']->getKey() }}')"
                                                >+</button>

                                                <label class="bom-active" title="Aktif">
                                                    <input
                                                        type="checkbox"
                                                        @checked($row['is_active'])
                                                        aria-label="Aktifkan {{ $row['item']->item_name }}"
                                                        wire:change="toggleActive('{{ $row['parent_id'] }}', {{ $row['index'] }})"
                                                    >
                                                </label>

                                                <button
                                                    type="button"
                                                    class="bom-icon-btn danger"
                                                    title="Hapus baris"
                                                    aria-label="Hapus {{ $row['item']->item_name }}"
                                                    wire:click="removeRow('{{ $row['parent_id'] }}', {{ $row['index'] }})"
                                                >×</button>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="bom-empty">
                                            <b>Belum ada komponen</b>
                                            Seret komponen dari pustaka, klik + pada komponen, atau tekan tombol di bawah.
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="bom-tree-footer">
                            <button type="button" class="bom-add-btn" wire:click="openAdd('{{ $root->getKey() }}')">
                                + Tambah komponen
                            </button>
                        </div>
                    @else
                        @if ($shopping === [])
                            <div class="bom-empty">
                                <b>Belum ada bahan untuk dibelanjakan</b>
                                Tambahkan komponen pada tab Struktur BOM terlebih dahulu.
                            </div>
                        @else
                            <div class="bom-scroll">
                                <table class="bom-shopping">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Komponen</th>
                                            <th class="r">Jumlah total</th>
                                            <th>Satuan</th>
                                            <th class="r">Harga satuan</th>
                                            <th class="r">Stok</th>
                                            <th class="r">Perkiraan biaya</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($shopping as $line)
                                            <tr>
                                                <td>{{ $line['item']->item_code }}</td>
                                                <td>{{ $line['item']->item_name }}</td>
                                                <td class="r">{{ $qty($line['quantity']) }}</td>
                                                <td>{{ $line['item']->baseUom?->code ?? '—' }}</td>
                                                <td class="r">{{ $rupiah($line['unit_cost']) }}</td>
                                                <td class="r {{ $line['stock'] < $line['quantity'] ? 'bom-short' : '' }}">{{ $qty($line['stock']) }}</td>
                                                <td class="r">{{ $rupiah($line['subtotal']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="6" class="r"><b>Total untuk {{ $productionQty }} unit</b></td>
                                            <td class="r"><b>{{ $rupiah($shoppingTotal) }}</b></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endif
                    @endif

                    @if ($root)
                        <div class="bom-actions">
                            <div class="bom-dirty {{ $dirty ? 'on' : '' }}">
                                {{ $dirty ? 'Perubahan belum disimpan' : 'Semua perubahan tersimpan' }}
                            </div>
                            <div class="bom-action-btns">
                                <x-filament::button color="gray" wire:click="discardChanges" :disabled="! $dirty">
                                    Batalkan
                                </x-filament::button>
                                <x-filament::button icon="heroicon-o-check" wire:click="save" :disabled="! $dirty">
                                    Simpan komposisi
                                </x-filament::button>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <style>
        .bom-app {
            --bom-bg: var(--gray-50);
            --bom-surface: #fff;
            --bom-soft: var(--gray-100);
            --bom-head: var(--gray-100);
            --bom-line: var(--gray-200);
            --bom-ink: var(--gray-950);
            --bom-mute: var(--gray-500);
            --bom-accent: var(--primary-600);
            --bom-accent-soft: color-mix(in srgb, var(--primary-600) 9%, transparent);
            --bom-danger: var(--danger-600);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .dark .bom-app {
            --bom-bg: var(--gray-950);
            --bom-surface: var(--gray-900);
            --bom-soft: color-mix(in srgb, #fff 6%, transparent);
            --bom-head: var(--gray-800);
            --bom-line: color-mix(in srgb, #fff 12%, transparent);
            --bom-ink: #fff;
            --bom-mute: var(--gray-400);
            --bom-accent: var(--primary-400);
            --bom-accent-soft: color-mix(in srgb, var(--primary-400) 16%, transparent);
            --bom-danger: var(--danger-400);
        }

        .bom-grid {
            display: grid;
            grid-template-columns: 300px minmax(0, 1fr);
            gap: 1.25rem;
            align-items: start;
        }

        .bom-work {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            min-width: 0;
        }

        .bom-side {
            display: flex;
            flex-direction: column;
            gap: .9rem;
            position: sticky;
            top: 1rem;
            max-height: calc(100vh - 2rem);
            overflow: auto;
            padding-right: .15rem;
        }

        .bom-card {
            background: var(--bom-surface);
            border: 1px solid var(--bom-line);
            border-radius: .9rem;
            padding: 1rem;
        }

        .bom-card-title {
            margin: 0 0 .6rem;
            font-size: .8rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--bom-mute);
        }

        .bom-group-label {
            margin: .9rem 0 .25rem;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--bom-mute);
        }

        .bom-group-label:first-child {
            margin-top: 0;
        }

        .bom-search {
            width: 100%;
            border: 1px solid var(--bom-line);
            background: var(--bom-surface);
            color: var(--bom-ink);
            border-radius: .55rem;
            padding: .55rem .7rem;
            font-size: .875rem;
        }

        .bom-search:focus {
            outline: none;
            border-color: var(--bom-accent);
            box-shadow: 0 0 0 3px var(--bom-accent-soft);
        }

        .bom-lib-item {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .45rem .55rem;
            border: 1px solid var(--bom-line);
            border-radius: .6rem;
            margin-top: .35rem;
            cursor: grab;
            background: var(--bom-surface);
        }

        .bom-lib-item:hover {
            border-color: var(--bom-accent);
            background: var(--bom-accent-soft);
        }

        .bom-lib-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
            flex: 1;
        }

        .bom-lib-info b {
            font-size: .85rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bom-lib-info small {
            color: var(--bom-mute);
            font-size: .72rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bom-plus {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.9rem;
            height: 1.9rem;
            border-radius: .5rem;
            border: 1px solid var(--bom-line);
            background: var(--bom-surface);
            color: var(--bom-ink);
            font-size: 1.1rem;
            line-height: 1;
        }

        .bom-plus:hover {
            border-color: var(--bom-accent);
            color: var(--bom-accent);
        }

        .bom-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            border: 1px solid var(--bom-line);
            border-radius: .9rem;
            background: var(--bom-surface);
            overflow: hidden;
        }

        .bom-stat {
            padding: .9rem 1rem;
            border-left: 1px solid var(--bom-line);
            display: flex;
            flex-direction: column;
            gap: .2rem;
        }

        .bom-stat:first-child {
            border-left: 0;
        }

        .bom-stat > span {
            font-size: .75rem;
            color: var(--bom-mute);
        }

        .bom-stat b {
            font-size: 1.15rem;
            font-weight: 600;
        }

        .bom-qty-input {
            width: 3.6rem;
            border: 1px solid var(--bom-line);
            border-radius: .4rem;
            padding: .05rem .3rem;
            background: var(--bom-surface);
            color: var(--bom-ink);
            font-size: .8rem;
        }

        .bom-tabs {
            display: flex;
            gap: 1.25rem;
            border-bottom: 1px solid var(--bom-line);
            margin-bottom: .75rem;
        }

        .bom-tab {
            border: 0;
            background: none;
            padding: .6rem .1rem;
            margin-bottom: -1px;
            border-bottom: 2px solid transparent;
            color: var(--bom-mute);
            font-weight: 500;
            font-size: .9rem;
        }

        .bom-tab.on {
            color: var(--bom-ink);
            border-bottom-color: var(--bom-accent);
        }

        .bom-chips {
            display: flex;
            align-items: center;
            gap: .4rem;
            flex-wrap: wrap;
            margin-bottom: .6rem;
        }

        .bom-chip {
            border: 1px solid var(--bom-line);
            background: var(--bom-surface);
            color: var(--bom-mute);
            border-radius: 999px;
            padding: .2rem .7rem;
            font-size: .75rem;
        }

        .bom-chip.on {
            background: var(--bom-ink);
            border-color: var(--bom-ink);
            color: var(--bom-surface);
        }

        .bom-inline-hint {
            margin: 0 0 .6rem;
        }

        .bom-scroll {
            overflow-x: auto;
        }

        .bom-tree {
            border: 1px solid var(--bom-line);
            border-radius: .7rem;
            overflow-x: auto;
            overflow-y: hidden;
        }

        .bom-row,
        .bom-thead {
            display: grid;
            grid-template-columns: 20px 38px 42px minmax(180px, 1fr) 80px 46px 96px 104px 92px;
            gap: .35rem;
            align-items: center;
            padding: .35rem .6rem;
            min-width: 760px;
        }

        .bom-thead {
            background: var(--bom-head);
            color: var(--bom-mute);
            font-size: .68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .03em;
            border-bottom: 1px solid var(--bom-line);
            padding-top: .6rem;
            padding-bottom: .6rem;
        }

        .bom-row {
            border-bottom: 1px solid var(--bom-line);
            position: relative;
            background: var(--bom-surface);
            color: var(--bom-ink);
            font-size: .875rem;
        }

        .bom-row:last-child {
            border-bottom: 0;
        }

        .bom-row:hover {
            background: color-mix(in srgb, var(--bom-accent) 4%, var(--bom-surface));
        }

        .bom-row.is-inactive {
            opacity: .55;
        }

        .bom-root {
            background: var(--bom-soft);
            font-weight: 600;
        }

        .bom-thead > span,
        .bom-row > span {
            min-width: 0;
        }

        .r {
            text-align: right;
        }

        .bom-level {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            justify-self: start;
            font-size: .68rem;
            font-weight: 700;
            padding: .1rem .4rem;
            border-radius: 999px;
            background: var(--bom-soft);
            color: var(--bom-mute);
        }

        .bom-l0 { color: var(--bom-ink); background: color-mix(in srgb, var(--bom-ink) 10%, transparent); }
        .bom-l1 { color: #0369a1; background: color-mix(in srgb, #0ea5e9 15%, transparent); }
        .bom-l2 { color: #4338ca; background: color-mix(in srgb, #6366f1 15%, transparent); }
        .bom-l3 { color: #b45309; background: color-mix(in srgb, #f59e0b 15%, transparent); }
        .bom-l4 { color: #be185d; background: color-mix(in srgb, #ec4899 15%, transparent); }

        .dark .bom-l0 { color: #fff; background: color-mix(in srgb, #fff 14%, transparent); }
        .dark .bom-l1 { color: #7dd3fc; }
        .dark .bom-l2 { color: #a5b4fc; }
        .dark .bom-l3 { color: #fcd34d; }
        .dark .bom-l4 { color: #f9a8d4; }

        .bom-no {
            color: var(--bom-mute);
            font-size: .72rem;
            white-space: nowrap;
        }

        .bom-name {
            display: flex;
            flex-direction: column;
            gap: .1rem;
            min-width: 0;
            padding-left: calc(var(--bom-depth, 0) * 18px);
        }

        .bom-root .bom-name {
            padding-left: 0;
        }

        .bom-name-line {
            display: flex;
            align-items: center;
            gap: .35rem;
            min-width: 0;
        }

        .bom-name-line b {
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bom-code {
            color: var(--bom-mute);
            font-size: .72rem;
            white-space: nowrap;
        }

        .bom-tag {
            font-size: .65rem;
            padding: 0 .35rem;
            border-radius: 999px;
            background: var(--bom-soft);
            color: var(--bom-mute);
            white-space: nowrap;
        }

        .bom-note {
            border: 1px solid transparent;
            background: transparent;
            color: var(--bom-mute);
            font-size: .72rem;
            padding: .05rem .3rem;
            border-radius: .3rem;
            width: 100%;
        }

        .bom-note:hover {
            border-color: var(--bom-line);
        }

        .bom-note:focus {
            outline: none;
            border-color: var(--bom-accent);
            background: var(--bom-surface);
            color: var(--bom-ink);
        }

        .bom-chev,
        .bom-chev-spacer {
            width: 1.1rem;
            height: 1.1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: none;
        }

        .bom-chev {
            border: 0;
            background: none;
            padding: 0;
            color: var(--bom-mute);
        }

        .bom-chev-icon {
            width: .9rem;
            height: .9rem;
            transition: transform .15s ease;
        }

        .bom-chev-icon.is-open {
            transform: rotate(90deg);
        }

        .bom-qty {
            width: 100%;
            border: 1px solid transparent;
            background: transparent;
            color: var(--bom-ink);
            text-align: right;
            padding: .25rem .35rem;
            border-radius: .35rem;
            font-size: .85rem;
        }

        .bom-qty:hover {
            border-color: var(--bom-line);
        }

        .bom-qty:focus {
            outline: none;
            border-color: var(--bom-accent);
            background: var(--bom-surface);
        }

        .bom-unit {
            color: var(--bom-mute);
            font-size: .78rem;
            white-space: nowrap;
        }

        .bom-cost,
        .bom-sub {
            font-size: .8rem;
            white-space: nowrap;
        }

        .bom-sub {
            font-weight: 600;
        }

        .bom-row-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .1rem;
        }

        /* Kolom aksi selalu terlihat walau tabel digeser ke samping. */
        .bom-thead > span:last-child,
        .bom-row > .bom-row-actions {
            position: sticky;
            right: 0;
            z-index: 2;
            background: inherit;
            box-shadow: -10px 0 10px -10px rgba(0, 0, 0, .35);
            padding-left: .4rem;
        }

        .bom-thead > span:last-child {
            z-index: 3;
        }

        .bom-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.5rem;
            height: 1.5rem;
            border: 1px solid transparent;
            background: transparent;
            border-radius: .35rem;
            color: var(--bom-mute);
            font-size: 1rem;
            line-height: 1;
        }

        .bom-icon-btn:hover {
            background: var(--bom-soft);
            color: var(--bom-ink);
        }

        .bom-icon-btn.danger:hover {
            color: var(--bom-danger);
        }

        .bom-active {
            display: inline-flex;
            align-items: center;
        }

        .bom-active input {
            accent-color: var(--bom-accent);
        }

        .bom-grip {
            cursor: grab;
            color: var(--bom-mute);
            text-align: center;
            user-select: none;
        }

        .bom-grip:active {
            cursor: grabbing;
        }

        .bom-row.bom-over-before::before,
        .bom-row.bom-over-after::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--bom-accent);
            z-index: 2;
        }

        .bom-row.bom-over-before::before {
            top: -1px;
        }

        .bom-row.bom-over-after::after {
            bottom: -1px;
        }

        .bom-row.bom-over-in {
            outline: 2px solid var(--bom-accent);
            outline-offset: -2px;
            background: var(--bom-accent-soft);
        }

        .bom-tree-footer {
            margin-top: .6rem;
        }

        .bom-add-btn {
            border: 1px dashed var(--bom-line);
            background: transparent;
            color: var(--bom-mute);
            border-radius: .55rem;
            padding: .45rem .8rem;
            font-size: .85rem;
            width: 100%;
        }

        .bom-add-btn:hover {
            border-color: var(--bom-accent);
            color: var(--bom-accent);
        }

        .bom-add-panel {
            margin: .25rem 0 .75rem;
            border: 1px solid var(--bom-accent);
            border-radius: .7rem;
            padding: .75rem;
            background: var(--bom-accent-soft);
        }

        .bom-add-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .5rem;
            font-size: .85rem;
        }

        .bom-add-list {
            display: flex;
            flex-direction: column;
            gap: .3rem;
            margin-top: .5rem;
            max-height: 16rem;
            overflow: auto;
        }

        .bom-add-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            width: 100%;
            text-align: left;
            border: 1px solid var(--bom-line);
            background: var(--bom-surface);
            border-radius: .5rem;
            padding: .4rem .6rem;
            color: var(--bom-ink);
        }

        .bom-add-option:hover {
            border-color: var(--bom-accent);
        }

        .bom-add-option span {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .bom-add-option small {
            color: var(--bom-mute);
            font-size: .72rem;
        }

        .bom-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: .9rem;
            padding-top: .9rem;
            border-top: 1px solid var(--bom-line);
        }

        .bom-action-btns {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .bom-dirty {
            font-size: .8rem;
            color: var(--bom-mute);
        }

        .bom-dirty.on {
            color: var(--bom-danger);
            font-weight: 600;
        }

        .bom-empty {
            padding: 2.5rem 1rem;
            text-align: center;
            color: var(--bom-mute);
        }

        .bom-empty b {
            display: block;
            color: var(--bom-ink);
            margin-bottom: .25rem;
        }

        .bom-muted {
            color: var(--bom-mute);
        }

        .bom-small {
            font-size: .78rem;
        }

        .bom-root-info {
            display: flex;
            align-items: center;
            gap: .4rem;
            flex-wrap: wrap;
            margin-top: .75rem;
            padding-top: .75rem;
            border-top: 1px solid var(--bom-line);
            font-size: .85rem;
        }

        .bom-tpl-list {
            display: flex;
            flex-direction: column;
            gap: .4rem;
        }

        .bom-tpl {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            border: 1px solid var(--bom-line);
            border-radius: .6rem;
            padding: .45rem .55rem;
        }

        .bom-tpl-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .bom-tpl-info b {
            font-size: .85rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bom-tpl-info small {
            color: var(--bom-mute);
            font-size: .72rem;
        }

        .bom-tpl-actions {
            display: flex;
            gap: .25rem;
            flex: none;
        }

        .bom-tpl-actions button {
            border: 1px solid var(--bom-line);
            background: var(--bom-surface);
            color: var(--bom-ink);
            border-radius: .4rem;
            padding: .2rem .5rem;
            font-size: .72rem;
        }

        .bom-tpl-actions button:hover {
            border-color: var(--bom-accent);
            color: var(--bom-accent);
        }

        .bom-tpl-actions button:disabled {
            opacity: .45;
        }

        .bom-shopping {
            width: 100%;
            border-collapse: collapse;
            font-size: .85rem;
            min-width: 720px;
        }

        .bom-shopping th,
        .bom-shopping td {
            padding: .5rem .6rem;
            border-bottom: 1px solid var(--bom-line);
            text-align: left;
        }

        .bom-shopping th {
            background: var(--bom-soft);
            color: var(--bom-mute);
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .bom-shopping tfoot td {
            border-bottom: 0;
            border-top: 1px solid var(--bom-line);
        }

        .bom-short {
            color: var(--bom-danger);
            font-weight: 600;
        }

        @media (max-width: 1024px) {
            .bom-grid {
                grid-template-columns: 1fr;
            }

            .bom-side {
                position: static;
                max-height: none;
                overflow: visible;
            }
        }

        @media (max-width: 720px) {
            .bom-stats {
                grid-template-columns: 1fr 1fr;
            }

            .bom-stat:nth-child(3) {
                border-left: 0;
            }

            .bom-stat:nth-child(n+3) {
                border-top: 1px solid var(--bom-line);
            }
        }
    </style>

    @script
    <script>
        Alpine.data('bomBuilder', () => ({
                drag: null,
                overKey: null,
                overZone: null,

                startRow(event, parentId, index) {
                    this.drag = { type: 'row', parentId: parentId, index: index };
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', 'row');
                    event.target.classList.add('is-dragging');
                },

                startLib(event, itemId) {
                    this.drag = { type: 'lib', itemId: itemId };
                    event.dataTransfer.effectAllowed = 'copy';
                    event.dataTransfer.setData('text/plain', 'lib');
                    event.target.classList.add('is-dragging');
                },

                endDrag(event) {
                    if (event && event.target) {
                        event.target.classList.remove('is-dragging');
                    }

                    this.drag = null;
                    this.overKey = null;
                    this.overZone = null;
                },

                dragOver(event, key) {
                    if (! this.drag) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();

                    const rect = event.currentTarget.getBoundingClientRect();
                    const y = (event.clientY - rect.top) / rect.height;

                    this.overKey = key;
                    this.overZone = y < 0.3 ? 'before' : (y > 0.7 ? 'after' : 'in');
                    event.dataTransfer.dropEffect = this.drag.type === 'row' ? 'move' : 'copy';
                },

                dragLeave(event, key) {
                    if (this.overKey === key) {
                        this.overKey = null;
                        this.overZone = null;
                    }
                },

                dropRow(event, parentId, index, itemId) {
                    event.preventDefault();
                    event.stopPropagation();

                    if (! this.drag) {
                        return;
                    }

                    const zone = this.overZone || 'after';

                    if (this.drag.type === 'lib') {
                        if (zone === 'in') {
                            this.$wire.addComponent(itemId, this.drag.itemId);
                        } else {
                            this.$wire.addComponent(parentId, this.drag.itemId, zone === 'before' ? index : index + 1);
                        }
                    } else {
                        this.$wire.moveRow(this.drag.parentId, this.drag.index, zone === 'in' ? itemId : parentId, index, zone);
                    }

                    this.endDrag(event);
                },

                dropRoot(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    if (! this.drag) {
                        return;
                    }

                    if (this.drag.type === 'lib') {
                        this.$wire.addToRoot(this.drag.itemId);
                    }

                    this.endDrag(event);
                }
        }));
    </script>
    @endscript
</x-filament-panels::page>
