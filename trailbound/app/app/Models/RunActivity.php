<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'region_id', 'distance_km', 'duration_minutes', 'xp_awarded', 'source', 'external_id', 'run_at', 'polyline', 'start_lat', 'start_lng', 'image_paths'])]
class RunActivity extends Model
{
    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:2',
            'duration_minutes' => 'decimal:1',
            'run_at' => 'datetime',
            'start_lat' => 'decimal:7',
            'start_lng' => 'decimal:7',
        ];
    }

    public function getImagePathsAttribute($value): array
    {
        $paths = is_array($value) ? $value : json_decode($value ?: '[]', true);

        if (! is_array($paths)) {
            return [];
        }

        return array_values(array_map(fn ($path) => $this->publicMediaPath($path), $paths));
    }

    public function setImagePathsAttribute($value): void
    {
        $this->attributes['image_paths'] = json_encode(array_values($value ?: []));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    private function publicMediaPath(?string $path): ?string
    {
        if (! $path || str_starts_with($path, 'http') || str_starts_with($path, '/storage/')) {
            return $path;
        }

        return '/storage/' . ltrim($path, '/');
    }
}
