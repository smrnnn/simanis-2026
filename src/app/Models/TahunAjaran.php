<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahunAjaran extends Model
{
    protected $table = 'tahun_ajaran';

    protected $fillable = ['nama', 'aktif'];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class);
    }
}