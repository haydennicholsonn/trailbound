<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['key', 'name', 'biome', 'summary', 'difficulty', 'map_x', 'map_y', 'sort_order', 'start_keywords', 'polygon'])]
class Region extends Model
{
    protected function casts(): array
    {
        return [
            'start_keywords' => 'array',
            'polygon' => 'array',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('unlock_order');
    }

    public function runActivities(): HasMany
    {
        return $this->hasMany(RunActivity::class);
    }

    public static function startingFor(string $homeArea): self
    {
        $needle = Str::of($homeArea)->lower()->toString();
        $regions = self::query()->orderBy('sort_order')->get();

        foreach ($regions as $region) {
            foreach ($region->start_keywords ?? [] as $keyword) {
                if (Str::contains($needle, Str::lower($keyword))) {
                    return $region;
                }
            }
        }

        return $regions->firstWhere('key', 'city-bowl') ?? $regions->first();
    }
}
