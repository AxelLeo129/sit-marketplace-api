<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Área terapéutica. Agrupa productos y sirve como especialidad de los médicos.
 */
#[Fillable(['name'])]
class Category extends Model
{
    use HasFactory;

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * Médicos que tienen esta especialidad.
     */
    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
