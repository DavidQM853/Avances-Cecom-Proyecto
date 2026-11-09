<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRolCategoriaRestriccion extends Model
{
    protected $table = 'user_rol_categoria_restriccion';

    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'user_rol_id',
        'categoria_opex_id',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaOpex::class, 'categoria_opex_id');
    }
}
