<?php
// app/Http/Controllers/Teb/BmpController.php

namespace App\Http\Controllers\Teb;

use App\Models\Teb\TebBmpCategory;
use App\Models\Teb\TebProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

// BMP maintenance (Settings page, TRB Admins): the categories under each TQRDC driver and their classification.
// A changed classification applies to entries registered or edited from then on; routed entries keep theirs.
class BmpController extends TebController
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'driver'         => ['required', Rule::in(array_keys(config('teb.bmp_drivers')))],
            'name'           => ['required', 'string', 'max:150',
                Rule::unique(TebBmpCategory::class, 'name')->where('driver', $request->input('driver'))],
            'classification' => ['required', Rule::in(array_keys(config('teb.classifications')))],
        ], ['name.unique' => 'That category is already listed under this driver.']);

        TebBmpCategory::create($data + [
            'sort'   => (int) TebBmpCategory::where('driver', $data['driver'])->max('sort') + 1,
            'active' => true,
        ]);

        return back()->with('success', "Added “{$data['name']}” under {$data['driver']} – ".config("teb.bmp_drivers.{$data['driver']}").'.');
    }

    public function update(Request $request, TebBmpCategory $category)
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:150',
                Rule::unique(TebBmpCategory::class, 'name')->where('driver', $category->driver)->ignore($category->id)],
            'classification' => ['required', Rule::in(array_keys(config('teb.classifications')))],
            'active'         => ['required', 'boolean'],
        ], ['name.unique' => 'That category is already listed under this driver.']);

        DB::connection($category->getConnectionName())->transaction(function () use ($category, $data) {
            $old = $category->name;
            // Entries store the category name, so a rename carries over to the entries that picked it.
            if ($data['name'] !== $old) {
                $category->projectsQuery()->get()->each(function (TebProject $p) use ($category, $data) {
                    $charter = $p->charter;
                    $charter['bmp'][$category->driver] = $data['name'];
                    $p->charter = $charter;
                    $p->save();
                });
            }
            $category->update($data);
        });

        return back()->with('success', "Saved “{$data['name']}”.");
    }

    public function destroy(TebBmpCategory $category)
    {
        $used = $category->projectsQuery()->count();
        if ($used) {
            return back()->with('error', "“{$category->name}” is used by {$used} ".str('entry')->plural($used).'. Untick Active to stop offering it instead.');
        }
        $category->delete();

        return back()->with('success', "Deleted “{$category->name}”.");
    }
}
