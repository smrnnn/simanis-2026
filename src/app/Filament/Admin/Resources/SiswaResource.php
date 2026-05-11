<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SiswaResource\Pages;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\Tingkat;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class SiswaResource extends Resource
{
    protected static ?string $model = Siswa::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Akademik';
    protected static ?string $modelLabel = 'Siswa';

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ═══════════════════════════════════════════════════════════════
            // SECTION 1 — DATA UTAMA
            // ═══════════════════════════════════════════════════════════════
            Section::make('Data Utama')
                ->columns(2)
                ->schema([

                    // ── 1. afterStateHydrated: nama selalu ucwords saat load ──────
                    TextInput::make('nama')
                        ->label('Nama Lengkap')
                        ->required()
                        ->maxLength(100)
                        ->afterStateHydrated(function (TextInput $component, ?string $state) {
                            // LIFECYCLE hydration: capitalize setiap kata dari DB
                            $component->state(ucwords(strtolower($state ?? '')));
                        })
                        ->columnSpanFull(),

                    // ── 2. live(debounce:500): NISN ──────────────────────────────
                    // Debounce 500ms sebelum form re-render
                    TextInput::make('nisn')
                        ->label('NISN')
                        ->maxLength(10)
                        ->unique(ignoreRecord: true)
                        ->live(debounce: 500)
                        ->helperText('10 digit Nomor Induk Siswa Nasional'),

                    TextInput::make('nis')
                        ->label('NIS (Lokal)')
                        ->maxLength(15)
                        ->unique(ignoreRecord: true),

                    // ── 3. live(onBlur:true): email re-render saat blur ──────────
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->unique(ignoreRecord: true)
                        ->live(onBlur: true),

                    // ── 4. Password lifecycle ────────────────────────────────────
                    // dehydrateStateUsing → Hash::make
                    // dehydrated → hanya simpan jika terisi
                    // required   → hanya wajib saat create
                    TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->helperText(
                            fn (string $operation) => $operation === 'edit'
                                ? 'Kosongkan jika tidak ingin mengubah password.'
                                : null
                        ),

                    // password_confirmation: dehydrated(false) → tidak ada kolom di DB
                    TextInput::make('password_confirmation')
                        ->label('Konfirmasi Password')
                        ->password()
                        ->dehydrated(false)          // ← tidak disimpan ke DB
                        ->same('password')
                        ->required(fn (string $operation): bool => $operation === 'create'),

                    // ── 5. Foto ──────────────────────────────────────────────────
                    FileUpload::make('foto')
                        ->label('Foto Siswa')
                        ->image()
                        ->directory('foto-siswa')
                        ->columnSpanFull(),
                ]),

            // ═══════════════════════════════════════════════════════════════
            // SECTION 2 — DEPENDENT SELECT BERTINGKAT
            // Tingkat → Jurusan → Kelas (3 level dependent select)
            // ═══════════════════════════════════════════════════════════════
            Section::make('Data Kelas')
                ->columns(2)
                ->schema([

                    Select::make('tahun_ajaran_id')
                        ->label('Tahun Ajaran')
                        ->relationship('tahunAjaran', 'nama')
                        ->required()
                        ->columnSpanFull(),

                    // ── 6. Dependent Select Level 1: Tingkat ─────────────────────
                    Select::make('tingkat_id')
                        ->label('Tingkat')
                        ->options(Tingkat::pluck('nama', 'id'))
                        ->required()
                        ->live()   // re-render setiap ganti tingkat
                        ->afterStateUpdated(function (Set $set) {
                            // Reset jurusan & kelas saat tingkat berubah
                            $set('jurusan_id', null);
                            $set('kelas_id', null);
                        }),

                    // ── 7. Dependent Select Level 2: Jurusan ─────────────────────
                    // Options berubah berdasarkan tingkat_id
                    // SMP → hanya 'Umum'  |  SMA → IPA, IPS, Bahasa
                    Select::make('jurusan_id')
                        ->label('Jurusan')
                        ->options(fn (Get $get): Collection =>
                            Jurusan::where('tingkat_id', $get('tingkat_id'))
                                ->pluck('nama', 'id')
                        )
                        ->required()
                        ->live()   // re-render setiap ganti jurusan
                        ->afterStateUpdated(fn (Set $set) => $set('kelas_id', null)),

                    // ── 8. Dependent Select Level 3: Kelas ───────────────────────
                    // Options berubah berdasarkan tingkat_id + jurusan_id
                    Select::make('kelas_id')
                        ->label('Kelas')
                        ->options(fn (Get $get): Collection =>
                            Kelas::where('tingkat_id', $get('tingkat_id'))
                                ->where('jurusan_id', $get('jurusan_id'))
                                ->where('aktif', true)
                                ->pluck('nama', 'id')
                        )
                        ->required(),

                    // ── 9. Status Siswa — live() → dynamic schema ────────────────
                    Select::make('status_siswa')
                        ->label('Status')
                        ->options([
                            'aktif'    => 'Aktif',
                            'nonaktif' => 'Non-Aktif',
                            'lulus'    => 'Lulus',
                            'drop'     => 'Drop Out',
                        ])
                        ->default('aktif')
                        ->required()
                        ->live(),

                    // ── 10. Jalur Masuk — live() → dynamic schema ────────────────
                    Select::make('jalur_masuk')
                        ->label('Jalur Masuk')
                        ->options([
                            'reguler'  => 'Reguler',
                            'mutasi'   => 'Mutasi (Pindah Sekolah)',
                            'prestasi' => 'Jalur Prestasi',
                        ])
                        ->default('reguler')
                        ->required()
                        ->live(),
                ]),

            // ═══════════════════════════════════════════════════════════════
            // SECTION 3 — DYNAMIC SCHEMA: STATUS SISWA
            // ═══════════════════════════════════════════════════════════════
            Section::make('Detail Status')
                ->schema([
                    Grid::make(2)
                        ->schema(fn (Get $get): array => match ($get('status_siswa')) {

                            // AKTIF: nilai rapor + catatan prestasi
                            'aktif' => [
                                TextInput::make('nilai_rapor')
                                    ->label('Rata-rata Nilai Rapor')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    // dehydrateStateUsing: round 2 desimal
                                    ->dehydrateStateUsing(fn (?string $state): ?float =>
                                        $state ? round((float) $state, 2) : null
                                    ),
                                TextInput::make('prestasi')
                                    ->label('Catatan Prestasi')
                                    ->placeholder('Juara 1 Olimpiade Matematika, dsb.'),
                            ],

                            // NON-AKTIF: alasan wajib
                            'nonaktif' => [
                                Textarea::make('alasan_nonaktif')
                                    ->label('Alasan Non-Aktif')
                                    ->required()
                                    ->columnSpanFull(),
                            ],

                            // LULUS: tanggal lulus + nomor ijazah
                            // hidden() ditangani oleh match → tidak muncul saat status lain
                            'lulus' => [
                                DatePicker::make('tanggal_lulus')
                                    ->label('Tanggal Lulus')
                                    ->required(),
                                TextInput::make('nomor_ijazah')
                                    ->label('Nomor Ijazah')
                                    ->required()
                                    ->unique(ignoreRecord: true),
                            ],

                            // DROP OUT: alasan + tanggal
                            'drop' => [
                                Textarea::make('alasan_drop')
                                    ->label('Alasan Drop Out')
                                    ->required()
                                    ->columnSpanFull(),
                                DatePicker::make('tanggal_drop')
                                    ->label('Tanggal Drop Out')
                                    ->required(),
                            ],

                            default => [],
                        })
                        ->key('dynamicStatusFields'),
                ]),

            // ═══════════════════════════════════════════════════════════════
            // SECTION 4 — DYNAMIC SCHEMA: JALUR MASUK
            // ═══════════════════════════════════════════════════════════════
            Section::make('Detail Jalur Masuk')
                ->hidden(fn (Get $get): bool => $get('jalur_masuk') === 'reguler' || ! $get('jalur_masuk'))
                ->schema([
                    Grid::make(2)
                        ->schema(fn (Get $get): array => match ($get('jalur_masuk')) {

                            // MUTASI: asal sekolah + surat mutasi (file upload)
                            'mutasi' => [
                                TextInput::make('asal_sekolah')
                                    ->label('Asal Sekolah')
                                    ->required(),
                                FileUpload::make('surat_mutasi')
                                    ->label('Surat Keterangan Mutasi')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->required(),
                            ],

                            // PRESTASI: nilai prestasi + jenis prestasi required
                            'prestasi' => [
                                TextInput::make('nilai_prestasi')
                                    ->label('Nilai / Skor Prestasi')
                                    ->numeric()
                                    ->required()   // required kondisional via dynamic schema
                                    ->minValue(0)
                                    ->maxValue(100),
                                Select::make('jenis_prestasi')
                                    ->label('Jenis Prestasi')
                                    ->options([
                                        'Akademik'    => 'Akademik',
                                        'Olahraga'    => 'Olahraga',
                                        'Seni'        => 'Seni & Budaya',
                                        'Keagamaan'   => 'Keagamaan',
                                        'Lainnya'     => 'Lainnya',
                                    ])
                                    ->required(),
                            ],

                            // REGULER: tidak perlu field tambahan
                            default => [],
                        })
                        ->key('dynamicJalurFields'),
                ]),

            // ═══════════════════════════════════════════════════════════════
            // SECTION 5 — CONDITIONAL VISIBILITY: KONDISI SOSIAL
            // Checkbox is_yatim_piatu live() → section wali_id jadi required
            // ═══════════════════════════════════════════════════════════════
            Section::make('Kondisi Sosial')
                ->columns(2)
                ->schema([

                    // ── 11. Checkbox live() → mempengaruhi visibility & required ──
                    Checkbox::make('is_yatim_piatu')
                        ->label('Yatim / Piatu / Yatim Piatu?')
                        ->live()
                        ->columnSpanFull(),

                    // Pesan info kondisional: visible jika is_yatim_piatu = true
                    // (implementasi via hidden/visible pada Group wali di bawah)
                ]),

            // ═══════════════════════════════════════════════════════════════
            // SECTION 6 — HasOne RELATIONSHIP: BIODATA
            // Fieldset::make()->relationship('biodata')
            // Filament otomatis load & save ke biodata_siswa
            // ═══════════════════════════════════════════════════════════════
            Section::make('Data Pribadi')
                ->schema([
                    Fieldset::make('Biodata')
                        ->relationship('biodata')   // ← HasOne relationship saving
                        ->columns(2)
                        ->schema([

                            TextInput::make('nik')
                                ->label('NIK')
                                ->maxLength(16),

                            TextInput::make('no_kk')
                                ->label('No. KK')
                                ->maxLength(16),

                            Select::make('jenis_kelamin')
                                ->label('Jenis Kelamin')
                                ->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])
                                ->required(),

                            Select::make('agama')
                                ->label('Agama')
                                ->options([
                                    'Islam'    => 'Islam',
                                    'Kristen'  => 'Kristen',
                                    'Katholik' => 'Katholik',
                                    'Hindu'    => 'Hindu',
                                    'Buddha'   => 'Buddha',
                                    'Konghucu' => 'Konghucu',
                                ]),

                            TextInput::make('tempat_lahir')
                                ->label('Tempat Lahir'),

                            DatePicker::make('tanggal_lahir')
                                ->label('Tanggal Lahir')
                                ->maxDate(now()->subYears(10)),

                            Select::make('golongan_darah')
                                ->label('Golongan Darah')
                                ->options(['A' => 'A', 'B' => 'B', 'AB' => 'AB', 'O' => 'O']),

                            TextInput::make('no_hp_siswa')
                                ->label('No. HP Siswa')
                                ->tel(),

                            Textarea::make('alamat')
                                ->label('Alamat Lengkap')
                                ->columnSpanFull(),

                            TextInput::make('kota')->label('Kota / Kabupaten'),
                            TextInput::make('provinsi')->label('Provinsi'),

                            // ── Kebutuhan Khusus ──────────────────────────────────
                            // live() + visible() kondisional di dalam relationship
                            Checkbox::make('is_berkebutuhan_khusus')
                                ->label('Berkebutuhan Khusus?')
                                ->live()
                                ->columnSpanFull(),

                            TextInput::make('jenis_kebutuhan_khusus')
                                ->label('Jenis Kebutuhan Khusus')
                                ->placeholder('Tunarungu, Tunanetra, Disleksia, dsb.')
                                ->visible(fn (Get $get): bool => (bool) $get('is_berkebutuhan_khusus'))
                                ->required(fn (Get $get): bool => (bool) $get('is_berkebutuhan_khusus'))
                                ->columnSpanFull(),

                            // ── Data Orang Tua ────────────────────────────────────
                            TextInput::make('nama_ayah')
                                ->label('Nama Ayah'),

                            TextInput::make('pekerjaan_ayah')
                                ->label('Pekerjaan Ayah')
                                ->live(onBlur: true),

                            // required kondisional: wajib jika pekerjaan_ayah terisi
                            TextInput::make('penghasilan_ayah')
                                ->label('Penghasilan Ayah (Rp/bulan)')
                                ->numeric()
                                ->prefix('Rp')
                                ->required(fn (Get $get): bool => filled($get('pekerjaan_ayah'))),

                            Select::make('pendidikan_ayah')
                                ->label('Pendidikan Terakhir Ayah')
                                ->options(['SD'=>'SD','SMP'=>'SMP','SMA/SMK'=>'SMA/SMK',
                                           'D3'=>'D3','S1'=>'S1','S2'=>'S2','S3'=>'S3']),

                            TextInput::make('nama_ibu')
                                ->label('Nama Ibu'),

                            TextInput::make('pekerjaan_ibu')
                                ->label('Pekerjaan Ibu')
                                ->live(onBlur: true),

                            TextInput::make('penghasilan_ibu')
                                ->label('Penghasilan Ibu (Rp/bulan)')
                                ->numeric()
                                ->prefix('Rp')
                                ->required(fn (Get $get): bool => filled($get('pekerjaan_ibu'))),

                            Select::make('pendidikan_ibu')
                                ->label('Pendidikan Terakhir Ibu')
                                ->options(['SD'=>'SD','SMP'=>'SMP','SMA/SMK'=>'SMA/SMK',
                                           'D3'=>'D3','S1'=>'S1','S2'=>'S2','S3'=>'S3']),

                            TextInput::make('no_hp_ortu')
                                ->label('No. HP Orang Tua')
                                ->tel()
                                ->required(),

                            TextInput::make('email_ortu')
                                ->label('Email Orang Tua')
                                ->email(),
                        ]),
                ]),

            // ═══════════════════════════════════════════════════════════════
            // SECTION 7 — BelongsTo CONDITIONAL: WALI SISWA
            // Group::make()->relationship('wali', condition:...)
            // Wajib diisi jika siswa is_yatim_piatu = true
            // Dibuat/diperbarui hanya jika nama_wali terisi
            // ═══════════════════════════════════════════════════════════════
            Section::make('Data Wali')
                ->description(fn (Get $get) => $get('is_yatim_piatu')
                    ? '⚠️ Siswa yatim/piatu — data wali wajib diisi.'
                    : 'Isi jika siswa diasuh oleh wali selain orang tua kandung.')
                ->schema([
                    Group::make()
                        ->relationship(
                            'wali',
                            // condition: record wali dibuat HANYA jika nama_wali terisi
                            condition: fn (?array $state): bool => filled($state['nama_wali'] ?? null),
                        )
                        ->columns(2)
                        ->schema([
                            TextInput::make('nama_wali')
                                ->label('Nama Wali')
                                // required jika siswa yatim piatu
                                ->required(fn (Get $get): bool => (bool) $get('is_yatim_piatu')),

                            TextInput::make('hubungan_wali')
                                ->label('Hubungan dengan Siswa')
                                ->placeholder('Kakak, Paman, Nenek, dsb.')
                                ->requiredWith('nama_wali'),

                            TextInput::make('nik_wali')
                                ->label('NIK Wali')
                                ->maxLength(16),

                            TextInput::make('no_hp_wali')
                                ->label('No. HP Wali')
                                ->tel()
                                ->requiredWith('nama_wali'),

                            TextInput::make('pekerjaan_wali')
                                ->label('Pekerjaan Wali'),

                            Select::make('pendidikan_wali')
                                ->label('Pendidikan Terakhir Wali')
                                ->options(['SD'=>'SD','SMP'=>'SMP','SMA/SMK'=>'SMA/SMK',
                                           'D3'=>'D3','S1'=>'S1','S2'=>'S2','S3'=>'S3']),

                            TextInput::make('penghasilan_wali')
                                ->label('Penghasilan Wali (Rp/bulan)')
                                ->numeric()
                                ->prefix('Rp'),

                            Textarea::make('alamat_wali')
                                ->label('Alamat Wali')
                                ->columnSpanFull(),
                        ]),
                ]),

        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSiswas::route('/'),
            'create' => Pages\CreateSiswa::route('/create'),
            'edit'   => Pages\EditSiswa::route('/{record}/edit'),
        ];
    }
}