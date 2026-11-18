<?php
// app/Models/Teb/TebBmpCategory.php

namespace App\Models\Teb;

// A BMP Alignment category under one TQRDC driver, with the change classification it carries.
// Kept on the Settings page; projects store the category name in charter.bmp.
class TebBmpCategory extends TebModel
{
    protected $table = 'teb_bmp_categories';

    protected $fillable = ['driver', 'name', 'classification', 'sort', 'active'];

    protected $casts = ['active' => 'boolean', 'sort' => 'integer'];

    protected static ?array $cache = null;

    protected static function booted(): void
    {
        static::saved(fn () => static::$cache = null);
        static::deleted(fn () => static::$cache = null);
    }

    // [driver => [name => classification]], in order. With $activeOnly, retired categories are left out.
    // Falls back to config teb.bmp_categories while the table is not migrated yet.
    public static function map(bool $activeOnly = false): array
    {
        $all = static::$cache ??= rescue(
            fn () => static::orderBy('sort')->orderBy('id')->get(['driver', 'name', 'classification', 'active'])
                ->groupBy('driver')
                ->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [$r->name => ['class' => $r->classification, 'active' => $r->active]])->all())
                ->all(),
            fn () => collect(config('teb.bmp_categories', []))
                ->map(fn ($cats) => collect($cats)->map(fn ($c) => ['class' => $c, 'active' => true])->all())->all(),
            report: false,
        );

        return collect(config('teb.bmp_drivers'))->mapWithKeys(fn ($label, $d) => [$d => collect($all[$d] ?? [])
            ->when($activeOnly, fn ($c) => $c->filter(fn ($r) => $r['active']))
            ->map(fn ($r) => $r['class'])->all()])->all();
    }

    // [driver => [names]] of retired categories (still shown on entries that picked them).
    public static function inactive(): array
    {
        $all = static::map();
        $active = static::map(true);

        return collect($all)->map(fn ($cats, $d) => array_values(array_diff(array_keys($cats), array_keys($active[$d] ?? []))))->all();
    }

    // Entries that picked this category.
    public function projectsQuery()
    {
        return TebProject::where("charter->bmp->{$this->driver}", $this->getOriginal('name') ?? $this->name);
    }
}
