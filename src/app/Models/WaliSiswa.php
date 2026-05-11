<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaliSiswa extends Model
{
    protected $table = 'wali_siswa';

    protected $fillable = [
        'nama_wali', 'hubungan_wali', 'nik_wali',
        'pekerjaan_wali', 'pendidikan_wali',
        'penghasilan_wali', 'no_hp_wali', 'alamat_wali',
    ];

    protected $casts = [
        'penghasilan_wali' => 'decimal:2',
    ];

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class, 'wali_id');
    }
}