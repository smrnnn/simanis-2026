<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Siswa extends Model
{
    use SoftDeletes;

    protected $table = 'siswa';

    protected $fillable = [
        'nama', 'nisn', 'nis', 'email', 'password',
        'tingkat_id', 'jurusan_id', 'kelas_id', 'tahun_ajaran_id', 'wali_id',
        'status_siswa',
        'nilai_rapor', 'prestasi',
        'alasan_nonaktif',
        'tanggal_lulus', 'nomor_ijazah',
        'alasan_drop', 'tanggal_drop',
        'jalur_masuk', 'asal_sekolah', 'surat_mutasi',
        'nilai_prestasi', 'jenis_prestasi',
        'is_yatim_piatu',
        'foto',
    ];

    protected $casts = [
        'is_yatim_piatu' => 'boolean',
        'nilai_rapor'    => 'decimal:2',
        'nilai_prestasi' => 'decimal:2',
        'tanggal_lulus'  => 'date',
        'tanggal_drop'   => 'date',
    ];

    protected $hidden = ['password'];

    // ── Relationships ────────────────────────────────────────────────────────

    /** HasOne → Fieldset::make()->relationship('biodata') */
    public function biodata(): HasOne
    {
        return $this->hasOne(BiodataSiswa::class);
    }

    /** BelongsTo opsional → Group::make()->relationship('wali', condition:...) */
    public function wali(): BelongsTo
    {
        return $this->belongsTo(WaliSiswa::class, 'wali_id');
    }

    // Dependent select
    public function tingkat(): BelongsTo
    {
        return $this->belongsTo(Tingkat::class);
    }

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }
}