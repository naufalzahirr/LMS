<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use App\Enums\LessonAssetType;
use App\Enums\LessonCheckpointType;
use App\Enums\LessonType;
use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAsset;
use App\Models\LessonCheckpoint;
use App\Models\Module;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionAsset;
use App\Models\QuestionBank;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

final class B1MathematicsContentInstaller
{
    private const PROGRAM_NAME = 'Matematika';

    private const COURSE_NAME = 'Matematika Fase A – Kelas I';

    private const COMPETENCY_CODE = 'B.1';

    private const COMPETENCY_TITLE = 'Membilang benda, mengenali banyak benda dalam satu kumpulan tanpa membilang (subitasi), mengenali dan menyatakan kumpulan benda yang sama banyak, lebih banyak atau lebih sedikit.';

    private const MODULE_NAME = 'Unit 1 – Banyak Benda di Sekitarku';

    private const QUESTION_BANK_CODE = 'B1-BANK';

    private const QUESTION_BANK_NAME = 'Bank Soal Matematika Kelas I – B.1';

    private const ASSESSMENT_CODE = 'B1-ASSESSMENT';

    private const ASSESSMENT_TITLE = 'Asesmen B.1 — Banyak Benda di Sekitarku';

    /** @var array<string, string> */
    private const ASSET_FILENAMES = [
        'B1-A01' => 'b1_a01_tiga_kumpulan_di_meja.png',
        'B1-A02' => 'b1_a02_empat_jeruk_keranjang.png',
        'B1-A03' => 'b1_a03_lima_apel_berjajar.png',
        'B1-A04' => 'b1_a04_tiga_bola_berjajar.png',
        'B1-A05' => 'b1_a05_menunjuk_apel_satu_per_satu.png',
        'B1-A06' => 'b1_a06_empat_pensil_berjajar.png',
        'B1-A07' => 'b1_a07_kartu_tiga_titik.png',
        'B1-A08' => 'b1_a08_kartu_lima_titik.png',
        'B1-A09' => 'b1_a09_tangan_dua_jari.png',
        'B1-A10' => 'b1_a10_tangan_lima_jari.png',
        'B1-A11' => 'b1_a11_kartu_empat_titik.png',
        'B1-A12' => 'b1_a12_enam_pensil_berjajar.png',
        'B1-A13' => 'b1_a13_delapan_bola_dua_baris.png',
        'B1-A14' => 'b1_a14_sepuluh_balok_dua_baris.png',
        'B1-A15' => 'b1_a15_tujuh_jeruk_berjajar.png',
        'B1-A16' => 'b1_a16_cangkir_sendok_berpasangan.png',
        'B1-A17' => 'b1_a17_tiga_kelinci_tiga_wortel.png',
        'B1-A18' => 'b1_a18_lima_anak_lima_kursi.png',
        'B1-A19' => 'b1_a19_empat_topi_tiga_anak.png',
        'B1-A20' => 'b1_a20_lima_apel_vs_tiga_apel.png',
        'B1-A21' => 'b1_a21_enam_ikan_vs_empat_ikan.png',
        'B1-A22' => 'b1_a22_tujuh_bunga_vs_sembilan_bunga.png',
        'B1-A23' => 'b1_a23_tiga_kumpulan_permen.png',
        'B1-A24' => 'b1_a24_enam_kupu_kupu.png',
        'B1-A25' => 'b1_a25_delapan_kelereng_vs_delapan_kelereng.png',
        'B1-A26' => 'b1_a26_empat_bebek.png',
        'B1-A27' => 'b1_a27_sembilan_bintang.png',
        'B1-A28' => 'b1_a28_lima_sepatu_lima_kaus_kaki.png',
        'B1-A29' => 'b1_a29_tujuh_balok_vs_lima_balok.png',
        'B1-A30' => 'b1_a30_tiga_payung_vs_enam_payung.png',
        'B1-A31' => 'b1_a31_lima_anak_empat_balon.png',
        'B1-A32' => 'b1_a32_delapan_buku_di_rak.png',
    ];

    /** @var array<string, string> */
    private const ASSET_ALT_TEXTS = [
        'B1-A01' => 'Meja dengan tiga kumpulan benda: tiga apel merah, lima pensil kuning, dan dua bola biru',
        'B1-A02' => 'Keranjang berisi empat jeruk oranye',
        'B1-A03' => 'Lima apel merah berjajar dalam satu baris',
        'B1-A04' => 'Tiga bola biru berjajar dalam satu baris',
        'B1-A05' => 'Tangan anak menunjuk apel pertama dari lima apel yang berjajar',
        'B1-A06' => 'Empat pensil kuning berdiri berjajar',
        'B1-A07' => 'Kartu dengan tiga titik hitam tersusun diagonal',
        'B1-A08' => 'Kartu dengan lima titik hitam, empat di sudut dan satu di tengah',
        'B1-A09' => 'Tangan anak dengan dua jari terbuka',
        'B1-A10' => 'Tangan anak dengan lima jari terbuka',
        'B1-A11' => 'Kartu dengan empat titik hitam membentuk persegi',
        'B1-A12' => 'Enam pensil kuning berdiri berjajar',
        'B1-A13' => 'Delapan bola biru tersusun dua baris, masing-masing empat bola',
        'B1-A14' => 'Sepuluh balok merah tersusun dua baris, masing-masing lima balok',
        'B1-A15' => 'Tujuh jeruk oranye berjajar dalam satu baris',
        'B1-A16' => 'Empat cangkir dan empat sendok dihubungkan garis berpasangan satu lawan satu',
        'B1-A17' => 'Tiga kelinci di baris atas dan tiga wortel di baris bawah, tersusun berpasangan',
        'B1-A18' => 'Lima anak di baris atas dan lima kursi di baris bawah, tersusun berpasangan',
        'B1-A19' => 'Empat topi di baris atas dan tiga anak di baris bawah, satu topi tidak punya pasangan',
        'B1-A20' => 'Lima apel merah di sebelah kiri dan tiga apel merah di sebelah kanan',
        'B1-A21' => 'Enam ikan di baris atas dan empat ikan di baris bawah, dua ikan atas tidak punya pasangan',
        'B1-A22' => 'Tujuh bunga di sebelah kiri dan sembilan bunga di sebelah kanan',
        'B1-A23' => 'Tiga kumpulan permen dengan empat permen di kiri, enam di tengah, dan dua di kanan.',
        'B1-A24' => 'Enam kupu-kupu ungu tersusun dua baris',
        'B1-A25' => 'Delapan kelereng di sebelah kiri dan delapan kelereng di sebelah kanan',
        'B1-A26' => 'Empat bebek kuning berjajar dalam satu baris',
        'B1-A27' => 'Sembilan bintang kuning tersusun dua baris',
        'B1-A28' => 'Lima sepatu di baris atas dan lima kaus kaki di baris bawah, tersusun berpasangan',
        'B1-A29' => 'Tujuh balok hijau di sebelah kiri dan lima balok hijau di sebelah kanan',
        'B1-A30' => 'Tiga payung di sebelah kiri dan enam payung di sebelah kanan',
        'B1-A31' => 'Lima anak berdiri berjajar dan empat balon di atasnya, satu anak tidak mendapat balon',
        'B1-A32' => 'Delapan buku berdiri berjajar di sebuah rak',
    ];

    public function __construct(
        private readonly LessonContentService $lessonContent,
        private readonly QuestionService $questions,
    ) {}

    /**
     * @return array{
     *     unique_source_assets: int,
     *     lessons: int,
     *     lesson_blocks: int,
     *     lesson_assets: int,
     *     checkpoints: int,
     *     questions: int,
     *     question_assets: int
     * }
     */
    public function install(string $zipOne, string $zipTwo): array
    {
        [$stageDirectory, $sourcePaths] = $this->stageAssets($zipOne, $zipTwo);

        try {
            return DB::transaction(function () use ($sourcePaths): array {
                $program = $this->program();
                $course = $this->course($program);
                $competency = $this->competency($course);
                $module = $this->module($competency);
                $lessons = $this->installLessons($module, $sourcePaths);
                [$assessment, $questions] = $this->installAssessment($course, $competency, $sourcePaths);

                $lessonBlocks = array_sum(array_map(
                    fn (Lesson $lesson): int => count($lesson->content_document['content'] ?? []),
                    $lessons,
                ));

                return [
                    'unique_source_assets' => count($sourcePaths),
                    'lessons' => count($lessons),
                    'lesson_blocks' => $lessonBlocks,
                    'lesson_assets' => LessonAsset::query()
                        ->whereIn('lesson_id', array_map(fn (Lesson $lesson): int => $lesson->id, $lessons))
                        ->whereIn('original_name', array_values(self::ASSET_FILENAMES))
                        ->count(),
                    'checkpoints' => LessonCheckpoint::query()
                        ->whereIn('lesson_id', array_map(fn (Lesson $lesson): int => $lesson->id, $lessons))
                        ->get()
                        ->filter(fn (LessonCheckpoint $checkpoint): bool => $this->isB1Checkpoint($checkpoint))
                        ->count(),
                    'questions' => count($questions),
                    'question_assets' => QuestionAsset::query()
                        ->whereIn('question_id', array_map(fn (Question $question): int => $question->id, $questions))
                        ->count(),
                ];
            });
        } finally {
            File::deleteDirectory($stageDirectory);
        }
    }

    /** @return array{string, array<string, string>} */
    private function stageAssets(string $zipOne, string $zipTwo): array
    {
        foreach ([$zipOne, $zipTwo] as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                throw new RuntimeException("Asset ZIP is missing or unreadable: {$path}");
            }
        }

        $stageDirectory = sys_get_temp_dir().'/b1-content-'.Str::lower(Str::random(16));
        File::ensureDirectoryExists($stageDirectory);

        try {
            $paths = [
                ...$this->extractAssets($zipOne, array_slice(self::ASSET_FILENAMES, 0, 30, true), $stageDirectory),
                ...$this->extractAssets($zipTwo, array_slice(self::ASSET_FILENAMES, 30, 2, true), $stageDirectory),
            ];
            $hashes = [];

            foreach ($paths as $assetId => $path) {
                $bytes = file_get_contents($path);

                if (! is_string($bytes)
                    || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
                    || getimagesizefromstring($bytes) === false) {
                    throw new RuntimeException("Asset {$assetId} is not a valid PNG image.");
                }

                $hash = hash('sha256', $bytes);

                if (isset($hashes[$hash])) {
                    throw new RuntimeException("Duplicate asset binary found for {$hashes[$hash]} and {$assetId}.");
                }

                $hashes[$hash] = $assetId;
            }

            if (count($paths) !== 32 || count($hashes) !== 32) {
                throw new RuntimeException('The B.1 package must contain exactly 32 unique PNG assets.');
            }

            return [$stageDirectory, $paths];
        } catch (\Throwable $exception) {
            File::deleteDirectory($stageDirectory);

            throw $exception;
        }
    }

    /**
     * @param  array<string, string>  $expected
     * @return array<string, string>
     */
    private function extractAssets(string $zipPath, array $expected, string $stageDirectory): array
    {
        $archive = new ZipArchive;
        $opened = $archive->open($zipPath);

        if ($opened !== true) {
            throw new RuntimeException("Unable to open asset ZIP [{$zipPath}] (code {$opened}).");
        }

        try {
            $entries = [];

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entryName = $archive->getNameIndex($index);

                if (! is_string($entryName) || str_ends_with($entryName, '/')) {
                    continue;
                }

                $normalizedName = str_replace('\\', '/', $entryName);
                $basename = basename($normalizedName);

                if (strtolower(pathinfo($basename, PATHINFO_EXTENSION)) !== 'png') {
                    continue;
                }

                if (isset($entries[$basename])) {
                    throw new RuntimeException("Duplicate PNG filename in ZIP: {$basename}");
                }

                $bytes = $archive->getFromIndex($index);

                if (! is_string($bytes)) {
                    throw new RuntimeException("Unable to read ZIP entry: {$entryName}");
                }

                $entries[$basename] = $bytes;
            }

            $expectedNames = array_values($expected);
            $foundNames = array_keys($entries);
            sort($expectedNames);
            sort($foundNames);

            if ($foundNames !== $expectedNames) {
                $missing = array_values(array_diff($expectedNames, $foundNames));
                $unexpected = array_values(array_diff($foundNames, $expectedNames));

                throw new RuntimeException(sprintf(
                    'Asset ZIP contents do not match the B.1 manifest. Missing: %s. Unexpected: %s.',
                    $missing === [] ? 'none' : implode(', ', $missing),
                    $unexpected === [] ? 'none' : implode(', ', $unexpected),
                ));
            }

            $paths = [];

            foreach ($expected as $assetId => $filename) {
                $path = $stageDirectory.'/'.$filename;

                if (file_put_contents($path, $entries[$filename]) === false) {
                    throw new RuntimeException("Unable to stage asset {$assetId}.");
                }

                $paths[$assetId] = $path;
            }

            return $paths;
        } finally {
            $archive->close();
        }
    }

    private function program(): Program
    {
        $matches = Program::withTrashed()
            ->where(function ($query): void {
                $query->where('slug', 'matematika')->orWhere('name', self::PROGRAM_NAME);
            })
            ->get();

        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple Program records match Matematika; resolve the duplicate before installing B.1.');
        }

        $program = $matches->first() ?? new Program;
        $program->fill([
            'name' => self::PROGRAM_NAME,
            'slug' => 'matematika',
            'status' => AcademicStatus::Active,
        ])->save();
        $program->restore();

        return $program;
    }

    private function course(Program $program): Course
    {
        $matches = Course::withTrashed()
            ->where('program_id', $program->id)
            ->where(function ($query): void {
                $query->where('slug', 'matematika-fase-a-kelas-i')->orWhere('name', self::COURSE_NAME);
            })
            ->get();

        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple Course records match Matematika Fase A – Kelas I.');
        }

        $course = $matches->first() ?? new Course;
        $course->fill([
            'program_id' => $program->id,
            'name' => self::COURSE_NAME,
            'slug' => 'matematika-fase-a-kelas-i',
            'sort_order' => 1,
            'status' => AcademicStatus::Active,
        ])->save();
        $course->restore();

        return $course;
    }

    private function competency(Course $course): Competency
    {
        $matches = Competency::withTrashed()
            ->where('course_id', $course->id)
            ->where('code', self::COMPETENCY_CODE)
            ->get();

        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple B.1 competencies exist in the target course.');
        }

        $competency = $matches->first() ?? new Competency;
        $competency->fill([
            'course_id' => $course->id,
            'code' => self::COMPETENCY_CODE,
            'name' => self::COMPETENCY_TITLE,
            'slug' => 'b-1',
            'sort_order' => 1,
            'status' => AcademicStatus::Active,
        ])->save();
        $competency->restore();

        return $competency;
    }

    private function module(Competency $competency): Module
    {
        $matches = Module::withTrashed()
            ->where('competency_id', $competency->id)
            ->where(function ($query): void {
                $query->where('slug', 'unit-1-banyak-benda-di-sekitarku')->orWhere('name', self::MODULE_NAME);
            })
            ->get();

        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple modules match Unit 1 – Banyak Benda di Sekitarku.');
        }

        $module = $matches->first() ?? new Module;
        $module->fill([
            'competency_id' => $competency->id,
            'name' => self::MODULE_NAME,
            'slug' => 'unit-1-banyak-benda-di-sekitarku',
            'sort_order' => 1,
            'status' => AcademicStatus::Active,
        ])->save();
        $module->restore();

        return $module;
    }

    /**
     * @return array<string, array{
     *     title: string,
     *     objective: string,
     *     duration: int,
     *     sort_order: int,
     *     checkpoint_codes: list<string>,
     *     blocks: list<array<int, mixed>>
     * }>
     */
    private function lessonData(): array
    {
        return [
            'B1-L1' => [
                'title' => 'Ayo Mengenal Kumpulan Benda',
                'objective' => 'Murid dapat mengenali sekumpulan benda sebagai satu kumpulan, dan menyebutkan banyak benda di dalamnya.',
                'duration' => 15,
                'sort_order' => 1,
                'checkpoint_codes' => ['B1-L1-CP01', 'B1-L1-CP02'],
                'blocks' => [
                    ['heading', 2, 'Benda di Sekitar Kita'],
                    ['paragraph', 'Di sekitar kita ada banyak benda.'],
                    ['paragraph', 'Benda yang ada bersama-sama disebut kumpulan.'],
                    ['image', 'B1-A01'],
                    ['paragraph', 'Lihat gambar di atas. Ada kumpulan apel. Ada kumpulan pensil. Ada kumpulan bola.'],
                    ['callout', 'tip', 'Ayo tunjuk setiap kumpulan dengan jarimu.'],
                    ['checkpoint', 'B1-L1-CP01'],
                    ['heading', 2, 'Berapa Banyak Isinya?'],
                    ['paragraph', 'Setiap kumpulan punya isi.'],
                    ['paragraph', 'Kita bisa tahu ada berapa benda di dalamnya.'],
                    ['image', 'B1-A02'],
                    ['paragraph', 'Ini satu kumpulan jeruk. Ayo tunjuk jeruknya satu per satu.'],
                    ['checkpoint', 'B1-L1-CP02'],
                    ['callout', 'important', 'Kumpulan adalah benda-benda yang ada bersama. Banyak benda artinya ada berapa benda di dalam kumpulan itu.'],
                ],
            ],
            'B1-L2' => [
                'title' => 'Membilang Benda 1–5',
                'objective' => 'Murid dapat membilang benda sampai 5 dengan menunjuk satu benda untuk satu bilangan, dan menyebutkan bilangan terakhir sebagai banyak benda.',
                'duration' => 20,
                'sort_order' => 2,
                'checkpoint_codes' => ['B1-L2-CP01', 'B1-L2-CP02'],
                'blocks' => [
                    ['heading', 2, 'Ayo Membilang'],
                    ['paragraph', 'Membilang artinya menghitung benda satu per satu.'],
                    ['image', 'B1-A03'],
                    ['paragraph', 'Lihat apel di atas. Ayo kita bilang bersama-sama.'],
                    ['paragraph', 'Satu, dua, tiga, empat, lima.'],
                    ['paragraph', 'Bilangan terakhir adalah lima. Jadi apelnya ada lima.'],
                    ['heading', 2, 'Cara Membilang yang Benar'],
                    ['image', 'B1-A05'],
                    ['bullets', [
                        'Tunjuk satu benda, sebut satu bilangan.',
                        'Jangan ada benda yang terlewat.',
                        'Jangan ada benda yang dihitung dua kali.',
                    ]],
                    ['callout', 'tip', 'Sentuh apelnya dengan jarimu sambil membilang. Lebih mudah!'],
                    ['checkpoint', 'B1-L2-CP01'],
                    ['heading', 2, 'Ayo Coba Lagi'],
                    ['image', 'B1-A04'],
                    ['paragraph', 'Sekarang ada bola. Ayo bilang satu per satu.'],
                    ['image', 'B1-A06'],
                    ['paragraph', 'Lihat pensil di atas. Bilang pelan-pelan sambil menunjuk.'],
                    ['checkpoint', 'B1-L2-CP02'],
                    ['callout', 'important', 'Bilangan yang kamu sebut paling akhir adalah banyak bendanya.'],
                ],
            ],
            'B1-L3' => [
                'title' => 'Sekali Lihat, Aku Tahu!',
                'objective' => 'Murid dapat menyebutkan banyak benda pada kelompok kecil (sampai 5) secara langsung tanpa membilang satu per satu.',
                'duration' => 15,
                'sort_order' => 3,
                'checkpoint_codes' => ['B1-L3-CP01', 'B1-L3-CP02'],
                'blocks' => [
                    ['heading', 2, 'Main Tebak Jumlah'],
                    ['paragraph', 'Kadang kita tahu ada berapa benda tanpa menghitung.'],
                    ['paragraph', 'Cukup sekali lihat, kita sudah tahu!'],
                    ['image', 'B1-A07'],
                    ['paragraph', 'Lihat sebentar saja. Ada tiga titik, kan?'],
                    ['paragraph', 'Kamu tidak perlu menghitung satu per satu.'],
                    ['image', 'B1-A11'],
                    ['paragraph', 'Ini empat titik. Bentuknya seperti persegi.'],
                    ['callout', 'tip', 'Pola titik membantu kita tahu jumlahnya dengan cepat.'],
                    ['checkpoint', 'B1-L3-CP01'],
                    ['heading', 2, 'Jari Juga Bisa'],
                    ['image', 'B1-A09'],
                    ['paragraph', 'Lihat jari di atas. Sekali lihat, kita tahu ada dua.'],
                    ['image', 'B1-A10'],
                    ['paragraph', 'Kalau semua jari terbuka, itu lima.'],
                    ['checkpoint', 'B1-L3-CP02'],
                    ['callout', 'important', 'Kelompok kecil bisa kita kenali sekali lihat. Kelompok besar tetap perlu dibilang satu per satu.'],
                ],
            ],
            'B1-L4' => [
                'title' => 'Membilang Benda 6–10',
                'objective' => 'Murid dapat membilang benda sampai 10, termasuk pada susunan dua baris.',
                'duration' => 20,
                'sort_order' => 4,
                'checkpoint_codes' => ['B1-L4-CP01', 'B1-L4-CP02'],
                'blocks' => [
                    ['heading', 2, 'Lebih dari Lima'],
                    ['paragraph', 'Sekarang bendanya lebih banyak.'],
                    ['paragraph', 'Kita bilang terus sampai sepuluh.'],
                    ['image', 'B1-A12'],
                    ['paragraph', 'Ayo bilang bersama. Satu, dua, tiga, empat, lima, enam.'],
                    ['paragraph', 'Pensilnya ada enam.'],
                    ['callout', 'tip', 'Bilang pelan-pelan. Tidak perlu buru-buru.'],
                    ['image', 'B1-A15'],
                    ['paragraph', 'Sekarang ada jeruk. Ayo bilang sambil menunjuk.'],
                    ['checkpoint', 'B1-L4-CP01'],
                    ['heading', 2, 'Benda Tersusun Dua Baris'],
                    ['image', 'B1-A13'],
                    ['paragraph', 'Bola ini tersusun dua baris.'],
                    ['paragraph', 'Bilang baris atas dulu. Lalu lanjutkan ke baris bawah.'],
                    ['image', 'B1-A14'],
                    ['paragraph', 'Lihat balok di atas. Bilang baris atas, lalu baris bawah.'],
                    ['checkpoint', 'B1-L4-CP02'],
                    ['callout', 'important', 'Kalau benda tersusun rapi, membilang jadi lebih mudah.'],
                ],
            ],
            'B1-L5' => [
                'title' => 'Pasangkan! Sama Banyak',
                'objective' => 'Murid dapat menentukan dua kumpulan sama banyak dengan cara memasangkan benda satu lawan satu.',
                'duration' => 20,
                'sort_order' => 5,
                'checkpoint_codes' => ['B1-L5-CP01', 'B1-L5-CP02'],
                'blocks' => [
                    ['heading', 2, 'Ayo Memasangkan'],
                    ['paragraph', 'Kita bisa tahu dua kumpulan sama banyak tanpa membilang.'],
                    ['paragraph', 'Caranya dengan memasangkan.'],
                    ['image', 'B1-A16'],
                    ['paragraph', 'Setiap cangkir punya satu sendok.'],
                    ['paragraph', 'Tidak ada yang sisa. Berarti cangkir dan sendok sama banyak.'],
                    ['image', 'B1-A17'],
                    ['paragraph', 'Setiap kelinci punya satu wortel. Tidak ada yang sisa.'],
                    ['callout', 'tip', 'Kalau semua benda dapat pasangan, berarti sama banyak.'],
                    ['image', 'B1-A18'],
                    ['paragraph', 'Lihat gambar di atas. Coba pasangkan anak dengan kursinya.'],
                    ['checkpoint', 'B1-L5-CP01'],
                    ['heading', 2, 'Kalau Ada yang Sisa'],
                    ['image', 'B1-A19'],
                    ['paragraph', 'Coba pasangkan topi dengan anak.'],
                    ['paragraph', 'Ada satu topi yang tidak punya pasangan.'],
                    ['checkpoint', 'B1-L5-CP02'],
                    ['callout', 'important', 'Sama banyak artinya semua benda dapat pasangan dan tidak ada yang sisa.'],
                ],
            ],
            'B1-L6' => [
                'title' => 'Lebih Banyak dan Lebih Sedikit',
                'objective' => 'Murid dapat menentukan kumpulan mana yang lebih banyak dan mana yang lebih sedikit.',
                'duration' => 20,
                'sort_order' => 6,
                'checkpoint_codes' => ['B1-L6-CP01', 'B1-L6-CP02'],
                'blocks' => [
                    ['heading', 2, 'Mana yang Lebih Banyak?'],
                    ['paragraph', 'Kadang dua kumpulan tidak sama banyak.'],
                    ['image', 'B1-A20'],
                    ['paragraph', 'Lihat dua kumpulan apel di atas.'],
                    ['paragraph', 'Kumpulan kiri lebih banyak. Kumpulan kanan lebih sedikit.'],
                    ['heading', 2, 'Pasangkan Dulu, Baru Lihat'],
                    ['image', 'B1-A21'],
                    ['paragraph', 'Coba pasangkan ikan atas dengan ikan bawah.'],
                    ['paragraph', 'Ada ikan atas yang tidak punya pasangan.'],
                    ['paragraph', 'Berarti ikan baris atas lebih banyak.'],
                    ['callout', 'tip', 'Kumpulan yang punya sisa adalah yang lebih banyak.'],
                    ['image', 'B1-A22'],
                    ['paragraph', 'Lihat dua kumpulan bunga di atas.'],
                    ['checkpoint', 'B1-L6-CP01'],
                    ['heading', 2, 'Mana yang Paling Sedikit?'],
                    ['image', 'B1-A23'],
                    ['paragraph', 'Sekarang ada tiga kumpulan permen.'],
                    ['checkpoint', 'B1-L6-CP02'],
                    ['callout', 'important', 'Pasangkan benda satu per satu. Kumpulan yang masih punya benda sisa adalah yang lebih banyak. Kumpulan yang bendanya lebih dulu habis adalah yang lebih sedikit.'],
                ],
            ],
            'B1-L7' => [
                'title' => 'Tantangan B.1',
                'objective' => 'Murid dapat menerapkan seluruh kemampuan B.1 pada situasi baru dan konteks sehari-hari.',
                'duration' => 25,
                'sort_order' => 7,
                'checkpoint_codes' => ['B1-L7-CP01', 'B1-L7-CP02', 'B1-L7-CP03', 'B1-L7-CP04', 'B1-L7-CP05'],
                'blocks' => [
                    ['heading', 2, 'Ayo Uji Kemampuanmu'],
                    ['paragraph', 'Kamu sudah belajar banyak hal.'],
                    ['paragraph', 'Sekarang ayo coba tantangannya!'],
                    ['callout', 'tip', 'Kerjakan pelan-pelan. Kalau salah, coba lagi ya.'],
                    ['heading', 2, 'Tantangan 1'],
                    ['image', 'B1-A24'],
                    ['checkpoint', 'B1-L7-CP01'],
                    ['heading', 2, 'Tantangan 2'],
                    ['image', 'B1-A08'],
                    ['checkpoint', 'B1-L7-CP02'],
                    ['heading', 2, 'Tantangan 3'],
                    ['image', 'B1-A25'],
                    ['checkpoint', 'B1-L7-CP03'],
                    ['heading', 2, 'Tantangan 4'],
                    ['image', 'B1-A23'],
                    ['checkpoint', 'B1-L7-CP04'],
                    ['heading', 2, 'Tantangan 5'],
                    ['image', 'B1-A21'],
                    ['checkpoint', 'B1-L7-CP05'],
                    ['callout', 'important', 'Kamu hebat! Kamu sudah bisa membilang, memasangkan, dan membandingkan kumpulan benda.'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $sourcePaths
     * @return array<string, Lesson>
     */
    private function installLessons(Module $module, array $sourcePaths): array
    {
        $installed = [];

        foreach ($this->lessonData() as $lessonCode => $data) {
            $matches = Lesson::withTrashed()
                ->where('module_id', $module->id)
                ->where(function ($query) use ($lessonCode, $data): void {
                    $query->where('slug', Str::lower($lessonCode))->orWhere('title', $data['title']);
                })
                ->get();

            if ($matches->count() > 1) {
                throw new RuntimeException("Multiple lessons match {$lessonCode} in the B.1 module.");
            }

            $lesson = $matches->first() ?? new Lesson;
            $lesson->fill([
                'module_id' => $module->id,
                'title' => $data['title'],
                'slug' => Str::lower($lessonCode),
                'lesson_type' => LessonType::Text,
                'content' => $data['objective'],
                'external_url' => null,
                'file_path' => null,
                'duration_minutes' => $data['duration'],
                'sort_order' => $data['sort_order'],
                'status' => AcademicStatus::Active,
                'is_authoring_draft' => false,
                'draft_owner_id' => null,
                'draft_expires_at' => null,
            ])->save();
            $lesson->restore();

            $checkpoints = $this->installCheckpoints($lesson, $data['checkpoint_codes']);
            $assets = [];

            foreach ($data['blocks'] as $block) {
                if ($block[0] !== 'image') {
                    continue;
                }

                $assetId = $block[1];
                $assets[$assetId] ??= $this->installLessonAsset($lesson, $assetId, $sourcePaths[$assetId]);
            }

            $document = $this->lessonDocument($lesson, $data['blocks'], $assets, $checkpoints);
            $lesson->forceFill([
                'content_document' => $this->lessonContent->normalize($lesson, $document),
            ])->save();

            $expectedFilenames = array_map(
                fn (LessonAsset $asset): string => $asset->original_name,
                array_values($assets),
            );

            foreach ($lesson->assets()->whereIn('original_name', array_values(self::ASSET_FILENAMES))->get() as $asset) {
                if (! in_array($asset->original_name, $expectedFilenames, true)) {
                    $this->deleteLessonAsset($asset);
                }
            }

            $installed[$lessonCode] = $lesson->refresh();
        }

        return $installed;
    }

    private function installLessonAsset(Lesson $lesson, string $assetId, string $sourcePath): LessonAsset
    {
        $filename = self::ASSET_FILENAMES[$assetId];
        $bytes = file_get_contents($sourcePath);

        if (! is_string($bytes)) {
            throw new RuntimeException("Unable to read staged asset {$assetId}.");
        }

        $matches = LessonAsset::query()
            ->where('lesson_id', $lesson->id)
            ->where('original_name', $filename)
            ->orderBy('id')
            ->get();
        $asset = $matches->first() ?? new LessonAsset;
        $previousPath = $asset->exists ? $asset->managedFilePath() : null;
        $path = "lesson-assets/{$lesson->id}/{$filename}";
        Storage::disk('local')->put($path, $bytes);
        $asset->fill([
            'lesson_id' => $lesson->id,
            'asset_type' => LessonAssetType::Image,
            'original_name' => $filename,
            'file_path' => $path,
            'mime_type' => 'image/png',
            'file_size' => strlen($bytes),
            'alt_text' => self::ASSET_ALT_TEXTS[$assetId],
            'caption' => null,
        ])->save();

        if ($previousPath !== null && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        foreach ($matches->slice(1) as $duplicate) {
            $this->deleteLessonAsset($duplicate);
        }

        return $asset->refresh();
    }

    private function deleteLessonAsset(LessonAsset $asset): void
    {
        $path = $asset->managedFilePath();
        $asset->delete();

        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * @return array<string, array{
     *     type: string,
     *     prompt: string,
     *     options?: list<string>,
     *     correct_options?: list<string>,
     *     correct_boolean?: bool,
     *     correct_feedback: string,
     *     incorrect_feedback: string,
     *     explanation: string
     * }>
     */
    private function checkpointData(): array
    {
        return [
            'B1-L1-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Ada berapa kumpulan benda di atas meja?',
                'options' => ['2', '3', '4'],
                'correct_options' => ['3'],
                'correct_feedback' => 'Hebat! Ada tiga kumpulan.',
                'incorrect_feedback' => 'Belum tepat. Coba lihat lagi gambarnya.',
                'explanation' => 'Ada kumpulan apel, kumpulan pensil, dan kumpulan bola. Jadi ada tiga kumpulan.',
            ],
            'B1-L1-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Ada berapa jeruk di dalam keranjang?',
                'options' => ['3', '4', '5'],
                'correct_options' => ['4'],
                'correct_feedback' => 'Betul! Ada empat jeruk.',
                'incorrect_feedback' => 'Belum tepat. Tunjuk jeruknya satu per satu.',
                'explanation' => 'Kalau ditunjuk satu per satu, ada empat jeruk.',
            ],
            'B1-L2-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar apel di atas. Ada berapa apel?',
                'options' => ['3', '4', '5'],
                'correct_options' => ['5'],
                'correct_feedback' => 'Hebat! Ada lima apel.',
                'incorrect_feedback' => 'Belum tepat. Coba hitung lagi satu per satu.',
                'explanation' => 'Kalau dibilang satu per satu, ada lima apel.',
            ],
            'B1-L2-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar pensil di atas. Ada berapa pensil?',
                'options' => ['2', '3', '4'],
                'correct_options' => ['4'],
                'correct_feedback' => 'Betul sekali! Ada empat pensil.',
                'incorrect_feedback' => 'Belum tepat. Tunjuk pensilnya satu per satu, ya.',
                'explanation' => 'Satu, dua, tiga, empat. Pensilnya ada empat.',
            ],
            'B1-L3-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar titik di atas. Sekali lihat saja, ada berapa titik?',
                'options' => ['3', '4', '5'],
                'correct_options' => ['4'],
                'correct_feedback' => 'Hebat! Kamu langsung tahu ada empat.',
                'incorrect_feedback' => 'Belum tepat. Lihat lagi polanya, seperti persegi.',
                'explanation' => 'Ada satu titik di setiap sudut. Semuanya ada empat titik.',
            ],
            'B1-L3-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar tangan di atas. Ada berapa jari yang terbuka?',
                'options' => ['4', '5', '6'],
                'correct_options' => ['5'],
                'correct_feedback' => 'Betul! Satu tangan terbuka berarti lima.',
                'incorrect_feedback' => 'Belum tepat. Coba buka tanganmu sendiri dan lihat.',
                'explanation' => 'Satu tangan punya lima jari. Kalau semua terbuka, itu lima.',
            ],
            'B1-L4-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar jeruk di atas. Ada berapa jeruk?',
                'options' => ['6', '7', '8'],
                'correct_options' => ['7'],
                'correct_feedback' => 'Hebat! Ada tujuh jeruk.',
                'incorrect_feedback' => 'Belum tepat. Bilang lagi pelan-pelan sambil menunjuk.',
                'explanation' => 'Kalau dibilang satu per satu, ada tujuh jeruk.',
            ],
            'B1-L4-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar balok di atas. Ada berapa balok semuanya?',
                'options' => ['8', '9', '10'],
                'correct_options' => ['10'],
                'correct_feedback' => 'Betul sekali! Ada sepuluh balok.',
                'incorrect_feedback' => 'Belum tepat. Bilang baris atas dulu, baru baris bawah.',
                'explanation' => 'Baris atas ada lima. Baris bawah ada lima. Semuanya ada sepuluh.',
            ],
            'B1-L5-CP01' => [
                'type' => 'true_false',
                'prompt' => 'Lihat gambar anak dan kursi di atas. Apakah anak dan kursi sama banyak?',
                'correct_boolean' => true,
                'correct_feedback' => 'Hebat! Semua anak dapat kursi.',
                'incorrect_feedback' => 'Belum tepat. Coba pasangkan satu anak dengan satu kursi.',
                'explanation' => 'Setiap anak punya satu kursi dan tidak ada yang sisa. Jadi sama banyak.',
            ],
            'B1-L5-CP02' => [
                'type' => 'true_false',
                'prompt' => 'Lihat gambar topi dan anak di atas. Apakah topi dan anak sama banyak?',
                'correct_boolean' => false,
                'correct_feedback' => 'Betul! Ada satu topi yang tidak punya pasangan.',
                'incorrect_feedback' => 'Belum tepat. Coba pasangkan lagi. Adakah yang sisa?',
                'explanation' => 'Satu topi tidak punya pasangan. Jadi topi dan anak tidak sama banyak.',
            ],
            'B1-L6-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar bunga di atas. Kumpulan mana yang lebih banyak?',
                'options' => ['Kumpulan kiri', 'Kumpulan kanan', 'Sama banyak'],
                'correct_options' => ['Kumpulan kanan'],
                'correct_feedback' => 'Hebat! Kumpulan kanan lebih banyak.',
                'incorrect_feedback' => 'Belum tepat. Coba pasangkan bunga kiri dengan bunga kanan.',
                'explanation' => 'Kumpulan kiri ada tujuh bunga. Kumpulan kanan ada sembilan bunga. Kumpulan kanan lebih banyak.',
            ],
            'B1-L6-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar permen di atas. Kumpulan mana yang paling sedikit?',
                'options' => ['Kumpulan kiri', 'Kumpulan tengah', 'Kumpulan kanan'],
                'correct_options' => ['Kumpulan kanan'],
                'correct_feedback' => 'Betul! Kumpulan kanan paling sedikit.',
                'incorrect_feedback' => 'Belum tepat. Bilang permen di setiap kumpulan, lalu bandingkan.',
                'explanation' => 'Kumpulan kiri ada empat permen. Kumpulan tengah ada enam permen. Kumpulan kanan ada dua permen. Kumpulan kanan paling sedikit.',
            ],
            'B1-L7-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Ada berapa kupu-kupu di taman?',
                'options' => ['5', '6', '7'],
                'correct_options' => ['6'],
                'correct_feedback' => 'Hebat! Ada enam kupu-kupu.',
                'incorrect_feedback' => 'Belum tepat. Bilang kupu-kupunya satu per satu.',
                'explanation' => 'Kalau dibilang satu per satu, ada enam kupu-kupu.',
            ],
            'B1-L7-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar titik di atas. Sekali lihat saja, ada berapa titik?',
                'options' => ['4', '5', '6'],
                'correct_options' => ['5'],
                'correct_feedback' => 'Hebat! Kamu langsung tahu ada lima.',
                'incorrect_feedback' => 'Belum tepat. Lihat polanya, ada satu titik di tengah.',
                'explanation' => 'Polanya empat titik di sudut dan satu titik di tengah. Semuanya ada lima.',
            ],
            'B1-L7-CP03' => [
                'type' => 'true_false',
                'prompt' => 'Lihat gambar kelereng di atas. Apakah kelereng kiri dan kelereng kanan sama banyak?',
                'correct_boolean' => true,
                'correct_feedback' => 'Betul! Keduanya sama banyak.',
                'incorrect_feedback' => 'Belum tepat. Coba pasangkan kelereng kiri dengan kelereng kanan.',
                'explanation' => 'Kelereng kiri ada delapan. Kelereng kanan ada delapan. Jadi sama banyak.',
            ],
            'B1-L7-CP04' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar permen di atas. Kumpulan mana yang paling banyak?',
                'options' => ['Kumpulan kiri', 'Kumpulan tengah', 'Kumpulan kanan'],
                'correct_options' => ['Kumpulan tengah'],
                'correct_feedback' => 'Hebat! Kumpulan tengah paling banyak.',
                'incorrect_feedback' => 'Belum tepat. Bilang permen di setiap kumpulan, lalu bandingkan.',
                'explanation' => 'Kumpulan kiri ada empat permen. Kumpulan tengah ada enam permen. Kumpulan kanan ada dua permen. Kumpulan tengah paling banyak.',
            ],
            'B1-L7-CP05' => [
                'type' => 'multiple_select',
                'prompt' => 'Lihat gambar ikan di atas. Pilih semua kalimat yang benar.',
                'options' => [
                    'Ikan baris atas lebih banyak.',
                    'Ikan baris bawah lebih sedikit.',
                    'Ikan atas dan ikan bawah sama banyak.',
                ],
                'correct_options' => [
                    'Ikan baris atas lebih banyak.',
                    'Ikan baris bawah lebih sedikit.',
                ],
                'correct_feedback' => 'Hebat! Kamu memilih semua kalimat yang benar.',
                'incorrect_feedback' => 'Belum tepat. Ada dua kalimat yang benar. Coba pasangkan ikannya lagi.',
                'explanation' => 'Ikan baris atas ada enam. Ikan baris bawah ada empat. Jadi ikan atas lebih banyak dan ikan bawah lebih sedikit.',
            ],
        ];
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, LessonCheckpoint>
     */
    private function installCheckpoints(Lesson $lesson, array $codes): array
    {
        $definitions = $this->checkpointData();
        $installed = [];

        foreach ($codes as $code) {
            $data = $definitions[$code];
            $type = LessonCheckpointType::from($data['type']);
            $options = [];
            $correctOptionIds = [];

            foreach ($data['options'] ?? [] as $index => $text) {
                $id = $this->deterministicUuid("{$code}:option:{$index}");
                $options[] = ['id' => $id, 'text' => $text];

                if (in_array($text, $data['correct_options'] ?? [], true)) {
                    $correctOptionIds[] = $id;
                }
            }

            $configuration = ['code' => $code];
            $answerKey = [];

            if (in_array($type, [LessonCheckpointType::MultipleChoice, LessonCheckpointType::MultipleSelect], true)) {
                $configuration['options'] = $options;
                $answerKey['correct_option_ids'] = $correctOptionIds;
            } elseif ($type === LessonCheckpointType::TrueFalse) {
                $correctBoolean = $data['correct_boolean'] ?? null;

                if (! is_bool($correctBoolean)) {
                    throw new RuntimeException("True/false checkpoint {$code} is missing its answer key.");
                }

                $answerKey['correct_boolean'] = $correctBoolean;
            }

            $checkpoint = $lesson->checkpoints()
                ->get()
                ->first(fn (LessonCheckpoint $candidate): bool => ($candidate->configuration['code'] ?? null) === $code)
                ?? new LessonCheckpoint;
            $checkpoint->fill([
                'lesson_id' => $lesson->id,
                'checkpoint_type' => $type,
                'prompt' => $data['prompt'],
                'correct_feedback' => $data['correct_feedback'],
                'incorrect_feedback' => $data['incorrect_feedback'],
                'explanation' => $data['explanation'],
                'configuration' => $configuration,
                'answer_key' => $answerKey,
                'created_by' => null,
            ])->save();
            $installed[$code] = $checkpoint->refresh();
        }

        foreach ($lesson->checkpoints()->get() as $checkpoint) {
            $code = $checkpoint->configuration['code'] ?? null;

            if (is_string($code) && str_starts_with($code, 'B1-') && ! in_array($code, $codes, true)) {
                $checkpoint->delete();
            }
        }

        return $installed;
    }

    private function isB1Checkpoint(LessonCheckpoint $checkpoint): bool
    {
        $code = $checkpoint->configuration['code'] ?? null;

        return is_string($code) && str_starts_with($code, 'B1-');
    }

    /**
     * @param  list<array<int, mixed>>  $blocks
     * @param  array<string, LessonAsset>  $assets
     * @param  array<string, LessonCheckpoint>  $checkpoints
     * @return array{type: string, content: list<array<string, mixed>>}
     */
    private function lessonDocument(Lesson $lesson, array $blocks, array $assets, array $checkpoints): array
    {
        $content = [];

        foreach ($blocks as $block) {
            $content[] = match ($block[0]) {
                'heading' => [
                    'type' => 'heading',
                    'attrs' => ['level' => $block[1]],
                    'content' => [$this->textNode($block[2])],
                ],
                'paragraph' => [
                    'type' => 'paragraph',
                    'content' => [$this->textNode($block[1])],
                ],
                'bullets' => [
                    'type' => 'bulletList',
                    'content' => array_map(fn (string $text): array => [
                        'type' => 'listItem',
                        'content' => [[
                            'type' => 'paragraph',
                            'content' => [$this->textNode($text)],
                        ]],
                    ], $block[1]),
                ],
                'callout' => [
                    'type' => 'callout',
                    'attrs' => ['type' => $block[1]],
                    'content' => [$this->textNode($block[2])],
                ],
                'image' => [
                    'type' => 'lessonImage',
                    'attrs' => [
                        'lessonAssetId' => $assets[$block[1]]->id,
                        'altText' => $assets[$block[1]]->alt_text,
                        'caption' => null,
                        'alignment' => 'center',
                        'size' => 'large',
                        'decorative' => false,
                    ],
                ],
                'checkpoint' => [
                    'type' => 'lessonCheckpoint',
                    'attrs' => ['checkpointId' => $checkpoints[$block[1]]->id],
                ],
                default => throw new RuntimeException("Unsupported B.1 lesson block [{$block[0]}] in lesson {$lesson->slug}."),
            };
        }

        return ['type' => 'doc', 'content' => $content];
    }

    /** @return array{type: string, text: string} */
    private function textNode(string $text): array
    {
        return ['type' => 'text', 'text' => $text];
    }

    /**
     * @return list<array{
     *     code: string,
     *     prompt: string,
     *     asset: string,
     *     options: list<string>,
     *     correct_answer: string,
     *     explanation: string
     * }>
     */
    private function questionData(): array
    {
        return [
            [
                'code' => 'B1-Q1',
                'prompt' => 'Ada berapa bebek pada gambar?',
                'asset' => 'B1-A26',
                'options' => ['3', '4', '5'],
                'correct_answer' => '4',
                'explanation' => 'Kalau dibilang satu per satu, ada empat bebek.',
            ],
            [
                'code' => 'B1-Q2',
                'prompt' => 'Ada berapa bintang pada gambar?',
                'asset' => 'B1-A27',
                'options' => ['8', '9', '10'],
                'correct_answer' => '9',
                'explanation' => 'Baris atas ada lima bintang. Baris bawah ada empat bintang. Semuanya ada sembilan.',
            ],
            [
                'code' => 'B1-Q3',
                'prompt' => 'Lihat sebentar saja. Ada berapa titik pada gambar?',
                'asset' => 'B1-A11',
                'options' => ['3', '4', '5'],
                'correct_answer' => '4',
                'explanation' => 'Ada satu titik di setiap sudut. Semuanya ada empat titik.',
            ],
            [
                'code' => 'B1-Q4',
                'prompt' => 'Apakah sepatu dan kaus kaki sama banyak?',
                'asset' => 'B1-A28',
                'options' => ['Ya, sama banyak', 'Tidak, sepatu lebih banyak', 'Tidak, kaus kaki lebih banyak'],
                'correct_answer' => 'Ya, sama banyak',
                'explanation' => 'Setiap sepatu punya satu kaus kaki dan tidak ada yang sisa. Jadi sama banyak.',
            ],
            [
                'code' => 'B1-Q5',
                'prompt' => 'Kumpulan balok mana yang lebih banyak?',
                'asset' => 'B1-A29',
                'options' => ['Kumpulan kiri', 'Kumpulan kanan', 'Sama banyak'],
                'correct_answer' => 'Kumpulan kiri',
                'explanation' => 'Kumpulan kiri ada tujuh balok. Kumpulan kanan ada lima balok. Kumpulan kiri lebih banyak.',
            ],
            [
                'code' => 'B1-Q6',
                'prompt' => 'Kumpulan payung mana yang lebih sedikit?',
                'asset' => 'B1-A30',
                'options' => ['Kumpulan kiri', 'Kumpulan kanan', 'Sama banyak'],
                'correct_answer' => 'Kumpulan kiri',
                'explanation' => 'Kumpulan kiri ada tiga payung. Kumpulan kanan ada enam payung. Kumpulan kiri lebih sedikit.',
            ],
            [
                'code' => 'B1-Q7',
                'prompt' => 'Setiap anak ingin memegang satu balon. Apakah balonnya cukup?',
                'asset' => 'B1-A31',
                'options' => ['Cukup', 'Tidak cukup, balonnya kurang', 'Tidak cukup, anaknya kurang'],
                'correct_answer' => 'Tidak cukup, balonnya kurang',
                'explanation' => 'Ada lima anak dan empat balon. Satu anak tidak dapat balon. Jadi balonnya kurang.',
            ],
            [
                'code' => 'B1-Q8',
                'prompt' => 'Ada berapa buku di rak?',
                'asset' => 'B1-A32',
                'options' => ['7', '8', '9'],
                'correct_answer' => '8',
                'explanation' => 'Kalau dibilang satu per satu, ada delapan buku.',
            ],
        ];
    }

    /**
     * @param  array<string, string>  $sourcePaths
     * @return array{Assessment, list<Question>}
     */
    private function installAssessment(Course $course, Competency $competency, array $sourcePaths): array
    {
        $bank = QuestionBank::withTrashed()->updateOrCreate(
            ['course_id' => $course->id, 'code' => self::QUESTION_BANK_CODE],
            [
                'name' => self::QUESTION_BANK_NAME,
                'description' => null,
                'status' => AcademicStatus::Active,
            ],
        );
        $bank->restore();
        $installedQuestions = [];

        foreach ($this->questionData() as $index => $data) {
            $sortOrder = $index + 1;
            $question = Question::withTrashed()
                ->where('question_bank_id', $bank->id)
                ->where('sort_order', $sortOrder)
                ->first();
            $payload = [
                'question_bank_id' => $bank->id,
                'competency_id' => $competency->id,
                'question_type' => QuestionType::MultipleChoice,
                'prompt' => $data['prompt'],
                'explanation' => $data['explanation'],
                'default_points' => '1.00',
                'correct_boolean' => null,
                'status' => AcademicStatus::Active,
                'sort_order' => $sortOrder,
                'options' => array_map(
                    fn (string $option, int $optionIndex): array => [
                        'option_text' => $option,
                        'is_correct' => $option === $data['correct_answer'],
                        'sort_order' => $optionIndex,
                    ],
                    $data['options'],
                    array_keys($data['options']),
                ),
                'accepted_answers' => [],
            ];

            if (! $question instanceof Question) {
                $question = $this->questions->create($payload);
            } else {
                $question->restore();

                if (! $this->questionMatches($question, $payload)) {
                    if ($question->attemptQuestions()->exists()) {
                        throw new RuntimeException("Question {$data['code']} has attempts and no longer matches the final B.1 handoff.");
                    }

                    $question = $this->questions->update($question, $payload);
                }
            }

            $this->installQuestionAsset($question, $data['asset'], $sourcePaths[$data['asset']]);
            $installedQuestions[] = $question->refresh();
        }

        $assessment = Assessment::withTrashed()
            ->where('competency_id', $competency->id)
            ->where('code', self::ASSESSMENT_CODE)
            ->first();
        $assessmentUsed = $assessment?->classAssignments()->whereHas('attempts')->exists() === true;

        if (! $assessment instanceof Assessment) {
            $assessment = new Assessment;
        } elseif ($assessmentUsed && ! $this->assessmentMatches($assessment, $installedQuestions)) {
            throw new RuntimeException('The B.1 assessment has attempts and no longer matches the final handoff.');
        }

        if (! $assessmentUsed) {
            $assessment->fill([
                'competency_id' => $competency->id,
                'title' => self::ASSESSMENT_TITLE,
                'code' => self::ASSESSMENT_CODE,
                'description' => null,
                'purpose' => AssessmentPurpose::Mastery,
                'status' => AssessmentStatus::Published,
                'instructions' => null,
                'shuffle_questions' => false,
            ])->save();
            $assessment->restore();

            foreach ($installedQuestions as $index => $question) {
                AssessmentQuestion::query()->updateOrCreate(
                    ['assessment_id' => $assessment->id, 'question_id' => $question->id],
                    ['points' => '1.00', 'sort_order' => $index],
                );
            }

            $assessment->assessmentQuestions()
                ->whereNotIn('question_id', array_map(fn (Question $question): int => $question->id, $installedQuestions))
                ->delete();
        }

        return [$assessment->refresh(), $installedQuestions];
    }

    /** @param array<string, mixed> $payload */
    private function questionMatches(Question $question, array $payload): bool
    {
        $question->loadMissing(['options', 'acceptedAnswers']);
        $actualOptions = $question->options->map(fn ($option): array => [
            'option_text' => $option->option_text,
            'is_correct' => $option->is_correct,
            'sort_order' => $option->sort_order,
        ])->values()->all();

        return $question->competency_id === $payload['competency_id']
            && $question->question_type === $payload['question_type']
            && $question->prompt === $payload['prompt']
            && $question->explanation === $payload['explanation']
            && $question->default_points === $payload['default_points']
            && $question->correct_boolean === null
            && $question->status === $payload['status']
            && $question->sort_order === $payload['sort_order']
            && $actualOptions === $payload['options']
            && $question->acceptedAnswers->isEmpty();
    }

    /** @param list<Question> $questions */
    private function assessmentMatches(Assessment $assessment, array $questions): bool
    {
        $attachments = $assessment->assessmentQuestions()->get();

        return $assessment->title === self::ASSESSMENT_TITLE
            && $assessment->purpose === AssessmentPurpose::Mastery
            && $assessment->status === AssessmentStatus::Published
            && $assessment->shuffle_questions === false
            && $attachments->count() === 8
            && $attachments->pluck('question_id')->all() === array_map(fn (Question $question): int => $question->id, $questions)
            && $attachments->every(fn (AssessmentQuestion $attachment): bool => $attachment->points === '1.00');
    }

    private function installQuestionAsset(Question $question, string $assetId, string $sourcePath): QuestionAsset
    {
        $filename = self::ASSET_FILENAMES[$assetId];
        $altText = self::ASSET_ALT_TEXTS[$assetId];
        $bytes = file_get_contents($sourcePath);

        if (! is_string($bytes)) {
            throw new RuntimeException("Unable to read staged asset {$assetId}.");
        }

        $existing = $question->image;
        $path = "question-assets/{$question->id}/{$filename}";
        $matches = $existing instanceof QuestionAsset
            && $existing->original_name === $filename
            && $existing->alt_text === $altText
            && $existing->file_size === strlen($bytes)
            && $existing->managedFilePath() !== null
            && Storage::disk('local')->exists($existing->managedFilePath())
            && hash('sha256', Storage::disk('local')->get($existing->managedFilePath())) === hash('sha256', $bytes);

        if ($matches) {
            return $existing;
        }

        if ($question->attemptQuestions()->exists()) {
            throw new RuntimeException("Question image {$assetId} has already been used in a student attempt and cannot be replaced.");
        }

        $previousPath = $existing?->managedFilePath();
        Storage::disk('local')->put($path, $bytes);
        $asset = QuestionAsset::query()->updateOrCreate(
            ['question_id' => $question->id],
            [
                'original_name' => $filename,
                'file_path' => $path,
                'mime_type' => 'image/png',
                'file_size' => strlen($bytes),
                'alt_text' => $altText,
            ],
        );

        if ($previousPath !== null && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        $question->setRelation('image', $asset);

        return $asset->refresh();
    }

    private function deterministicUuid(string $value): string
    {
        $hex = sha1('mastery-learning-center:b1:'.$value);
        $variant = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf(
            '%s-%s-5%s-%s%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 13, 3),
            $variant,
            substr($hex, 17, 3),
            substr($hex, 20, 12),
        );
    }
}
