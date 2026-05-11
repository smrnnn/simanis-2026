<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiodataSiswa extends Model
{
    protected $table = 'biodata_siswa';

    protected $fillable = [
        'siswa_id',
        'nik', 'no_kk', 'jenis_kelamin', 'agama',
        'tempat_lahir', 'tanggal_lahir', 'golongan_darah',
        'no_hp_siswa', 'alamat', 'kota', 'provinsi',
        'is_berkebutuhan_khusus', 'jenis_kebutuhan_khusus',
        'nama_ayah', 'pekerjaan_ayah', 'penghasilan_ayah', 'pendidikan_ayah',
        'nama_ibu', 'pekerjaan_ibu', 'penghasilan_ibu', 'pendidikan_ibu',
        'no_hp_ortu', 'email_ortu',
    ];

    protected $casts = [
        'tanggal_lahir'          => 'date',
        'is_berkebutuhan_khusus' => 'boolean',
        'penghasilan_ayah'       => 'decimal:2',
        'penghasilan_ibu'        => 'decimal:2',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}