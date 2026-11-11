<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Target extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'label', 'url', 'source_path', 'image', 'snapshot_path', 'snapshot_error', 'snapshot_at'];

    protected function casts(): array
    {
        return ['snapshot_at' => 'datetime'];
    }

    public function latestScan(): HasOne
    {
        return $this->hasOne(Scan::class)->latestOfMany();
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class)->latest('id');
    }

    public function displayName(): string
    {
        return $this->label ?: $this->name;
    }
}
