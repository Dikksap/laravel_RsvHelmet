# AGENTS.md — RSV Helmet

## Stack

- Laravel 13, PHP 8.3, Filament 5, Livewire 4, MariaDB, Vite + Tailwind CSS 4.
- Filament admin panel lives at `/admin`; the host app is exposed on port `8001`.
- The domain is inventory and production management: items and BOM, warehouses,
  inventory balances and movements, stock opnames, item requests, and
  production batches.

## Docker environment

This project runs with Docker Compose: `app` provides Laravel/PHP and `db`
provides MariaDB. The repository is mounted at `/var/www/html` in the app
container. The host may not have PHP or Composer installed; do not install host
versions for this project. Use Docker for all PHP, Artisan, Composer, test, and
Pint commands:

```sh
docker compose up -d
docker compose exec -T app php artisan [command]
docker compose exec -T app composer [command]
docker compose exec -T app php artisan test --compact [test-path-or-filter]
docker compose exec -T app vendor/bin/pint --dirty --format agent
```

If the tools are missing inside the container, inspect the Dockerfile and
Compose configuration rather than installing them on the host. Run frontend
commands inside the container as well when Node is available there.

## Application conventions

- UI labels, helper texts, validation messages, and notifications are written in
  Indonesian.
- Models use UUID primary keys (`HasUuids`), PHP attributes such as
  `#[Fillable([...])]`, and a `casts()` method.
- Domain workflows that span several models live in `app/Services`; keep Filament
  resources and pages thin.
- Livewire components live in `app/Livewire`; custom Filament pages live in
  `app/Filament/Pages`.
- Feature tests live in `tests/Feature` and use `RefreshDatabase`.

## Filament resources

- Resource list pages extend
  `App\Filament\Resources\Pages\InfiniteScrollListRecords`, which uses
  `Fibtegis\FilamentInfiniteScroll\Concerns\InteractsWithInfiniteScroll`.
- Every resource `table()` ends with `->infinite(perPage: 50)`. The plugin
  disables normal pagination and loads 50 rows per batch; search, filters, and
  sorting reset the batch automatically.
- Apply infinite scroll only to the 13 resource `ListRecords` pages. Custom
  pages, relation managers, and widgets keep Filament's normal pagination.
- The admin panel uses SPA navigation. The table height recalculation is
  registered through `PanelsRenderHook::BODY_END` in
  `AdminPanelProvider` and lives in
  `resources/views/filament/partials/infinite-scroll-spa-fix.blade.php`. Keep
  that hook and partial when changing the panel layout or theme.

### Item-heavy selects

Use `MrPunyapal\FilamentInfiniteSelect\InfiniteSelect` instead of a preloaded
`Select` when the options come from the items table:

```php
InfiniteSelect::make('item_id')
    ->label('Barang')
    ->getPaginatedOptionsUsing(function (int $offset, int $limit, ?string $search): array {
        $query = Item::query()
            ->when(filled($search), fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->where('item_code', 'like', "%{$search}%")
                    ->orWhere('item_name', 'like', "%{$search}%"),
            ));

        $rows = $query->orderBy('item_name')->skip($offset)->take($limit + 1)->get();

        return [
            'options' => $rows->take($limit)
                ->mapWithKeys(fn (Item $item): array => [$item->getKey() => $item->item_name])
                ->all(),
            'hasMore' => $rows->count() > $limit,
        ];
    })
    ->getOptionLabelUsing(fn ($value): ?string => Item::query()->find($value)?->item_name);
```

- Preserve the filters that the old select had, such as `is_stockable`,
  `has_variants`, excluding the current record, or excluding items already in a
  relation.
- Keep the existing labels, helper texts, and validation rules.
- `InfiniteSelect` already makes the field searchable; do not add `preload()`.
- Do not use the string rule `different:other_field` on `InfiniteSelect`. Use a
  closure rule that reads the other value through `Get`:

```php
->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
    if (filled($value) && (string) $value === (string) $get('source_item_id')) {
        $fail('Barang tujuan tidak boleh sama dengan barang template.');
    }
})
```

## Testing and formatting

- Run the full suite after changes:
  `docker compose exec -T app php artisan test --compact`.
- Run a focused test while iterating:
  `docker compose exec -T app php artisan test --compact --filter=TestName`.
- `tests/Feature/InfiniteScrollTest.php` covers the 50-row first batch and
  `loadMore()`; keep it passing when changing list pages.
- Format with `docker compose exec -T app vendor/bin/pint --dirty --format agent`.

## Generated assets

`public/js/filament`, `public/css/filament`, `public/fonts/filament`, and
`public/js/mrpunyapal` are generated and ignored by Git. They are republished by
`php artisan filament:upgrade` (already part of Composer's
`post-autoload-dump`) or directly with `php artisan filament:assets`. Run that
command after installing or updating a Filament plugin.

## Laravel Boost

Laravel Boost is installed. Follow its generated guidelines and the available
skills when they apply; this file records the project-specific rules that take
precedence.
