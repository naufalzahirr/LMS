<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentFeedbackMode;
use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\LessonAssetType;
use App\Enums\LessonCheckpointType;
use App\Enums\LessonType;
use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\Competency;
use App\Models\Course;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
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

final class B3MathematicsContentInstaller
{
    private const PROGRAM_NAME = 'Matematika';

    private const COURSE_NAME = 'Matematika Fase A – Kelas I';

    private const COMPETENCY_CODE = 'B.3';

    private const COMPETENCY_TITLE = 'Mengurutkan bilangan maju dan mundur, komposisi dan dekomposisi bilangan sampai 20, dan menentukan nilai tempat sampai 20.';

    private const MODULE_NAME = 'Unit 2 — Urutan, Komposisi-Dekomposisi, dan Nilai Tempat Bilangan sampai 20';

    private const QUESTION_BANK_CODE = 'B3-BANK';

    private const QUESTION_BANK_NAME = 'Bank Soal Matematika Kelas I – B.3';

    private const ASSESSMENT_CODE = 'B3-ASSESSMENT';

    private const ASSESSMENT_TITLE = 'Asesmen B.3 — Urutan, Komposisi-Dekomposisi, dan Nilai Tempat Bilangan sampai 20';

    /** @var array<string, string> */
    private const ASSET_FILENAMES = [
        'B3-A01' => 'b3_a01_jalur_bilangan_1_10_maju.png',
        'B3-A02' => 'b3_a02_jalur_bilangan_1_10_hilang_6.png',
        'B3-A03' => 'b3_a03_jalur_bilangan_11_20_maju.png',
        'B3-A04' => 'b3_a04_jalur_bilangan_11_20_hilang_17.png',
        'B3-A05' => 'b3_a05_jalur_bilangan_1_10_mundur.png',
        'B3-A06' => 'b3_a06_jalur_bilangan_1_10_hilang_4_mundur.png',
        'B3-A07' => 'b3_a07_jalur_bilangan_11_20_mundur.png',
        'B3-A08' => 'b3_a08_jalur_bilangan_11_20_hilang_13_mundur.png',
        'B3-A09' => 'b3_a09_kelereng_komposisi_8.png',
        'B3-A10' => 'b3_a10_bingkai5_ganda_komposisi_8.png',
        'B3-A11' => 'b3_a11_duku_dekomposisi_7.png',
        'B3-A13' => 'b3_a13_bingkai10_penuh_14.png',
        'B3-A14' => 'b3_a14_bingkai10_penuh_17.png',
        'B3-A15' => 'b3_a15_bundel_stik_13.png',
        'B3-A16' => 'b3_a16_bingkai10_penuh_16.png',
        'B3-A17' => 'b3_a17_bingkai10_penuh_19.png',
        'B3-A18' => 'b3_a18_dua_bingkai10_penuh_20.png',
        'B3-A19' => 'b3_a19_jalur_8_15_hilang_10_13.png',
        'B3-A20' => 'b3_a20_bingkai10_penuh_18.png',
        'B3-A21' => 'b3_a21_assessment_jalur_3_9_hilang_6.png',
        'B3-A22' => 'b3_a22_assessment_jalur_9_15_hilang_12_mundur.png',
        'B3-A23' => 'b3_a23_assessment_kartu_9_sebelum_sesudah.png',
        'B3-A24' => 'b3_a24_assessment_bingkai5_ganda_9.png',
        'B3-A25' => 'b3_a25_assessment_kancing_dekomposisi_6.png',
        'B3-A26' => 'b3_a26_assessment_bingkai10_penuh_13.png',
        'B3-A27' => 'b3_a27_assessment_bingkai10_penuh_15.png',
    ];

    /** @var array<string, string> */
    private const ASSET_ALT_TEXTS = [
        'B3-A01' => 'Sepuluh kartu bilangan 1 sampai 10 berjajar berurutan dengan panah mengarah ke kanan',
        'B3-A02' => 'Barisan kartu bilangan 1, 2, 3, 4, 5, kotak kosong, 7, 8, 9, 10',
        'B3-A03' => 'Sepuluh kartu bilangan 11 sampai 20 berjajar berurutan dengan panah mengarah ke kanan',
        'B3-A04' => 'Barisan kartu bilangan 11, 12, 13, 14, 15, 16, kotak kosong, 18, 19, 20',
        'B3-A05' => 'Sepuluh kartu bilangan 1 sampai 10 berjajar berurutan dari kiri ke kanan, dengan panah arah mundur mengarah ke kiri',
        'B3-A06' => 'Jalur kartu bilangan 1, 2, 3, kotak kosong, 5, 6, 7, 8, 9, 10 tersusun dari kiri ke kanan, dengan panah arah mundur ke kiri',
        'B3-A07' => 'Sepuluh kartu bilangan 11 sampai 20 berjajar berurutan dari kiri ke kanan, dengan panah arah mundur mengarah ke kiri',
        'B3-A08' => 'Jalur kartu bilangan 11, 12, kotak kosong, 14, 15, 16, 17, 18, 19, 20 tersusun dari kiri ke kanan, dengan panah arah mundur ke kiri',
        'B3-A09' => 'Delapan kelereng terbagi menjadi kelompok merah berisi lima dan kelompok biru berisi tiga',
        'B3-A10' => 'Dua bingkai lima berdampingan, bingkai pertama penuh berisi lima lingkaran, bingkai kedua berisi tiga lingkaran dan dua slot kosong',
        'B3-A11' => 'Tujuh buah duku terbagi ke dua keranjang, keranjang pertama berisi empat dan keranjang kedua berisi tiga',
        'B3-A13' => 'Satu bingkai sepuluh penuh berisi sepuluh lingkaran, ditambah empat lingkaran lepas di luar bingkai',
        'B3-A14' => 'Satu bingkai sepuluh penuh berisi sepuluh lingkaran, ditambah tujuh lingkaran lepas di luar bingkai',
        'B3-A15' => 'Satu ikatan berisi sepuluh stik es krim yang diikat pita, ditambah tiga stik lepas di sampingnya',
        'B3-A16' => 'Satu bingkai sepuluh penuh berisi sepuluh lingkaran, ditambah enam lingkaran lepas di luar bingkai, mewakili bilangan enam belas',
        'B3-A17' => 'Satu bingkai sepuluh penuh berisi sepuluh lingkaran, ditambah sembilan lingkaran lepas di luar bingkai, mewakili bilangan sembilan belas',
        'B3-A18' => 'Dua bingkai sepuluh, masing-masing penuh berisi sepuluh lingkaran, tanpa ada lingkaran lepas di luar bingkai',
        'B3-A19' => 'Barisan kartu bilangan 8, 9, kotak kosong, 11, 12, kotak kosong, 14, 15',
        'B3-A20' => 'Satu bingkai sepuluh penuh berisi sepuluh lingkaran, ditambah delapan lingkaran lepas di luar bingkai, mewakili bilangan delapan belas',
        'B3-A21' => 'Barisan kartu bilangan 3, 4, 5, kotak kosong, 7, 8, 9 tersusun berurutan maju',
        'B3-A22' => 'Jalur kartu bilangan 9, 10, 11, kotak kosong, 13, 14, 15 tersusun dari kiri ke kanan, dengan panah arah mundur ke kiri',
        'B3-A23' => 'Kartu bilangan 9 di tengah, dengan kotak kosong di sebelah kiri dan kotak kosong di sebelah kanan',
        'B3-A24' => 'Dua bingkai lima berdampingan, bingkai pertama penuh berisi lima lingkaran, bingkai kedua berisi empat lingkaran',
        'B3-A25' => 'Enam kancing terbagi menjadi kelompok merah berisi empat dan kelompok biru berisi dua',
        'B3-A26' => 'Satu bingkai sepuluh penuh berisi sepuluh lingkaran, ditambah tiga lingkaran lepas di luar bingkai',
        'B3-A27' => 'Satu bingkai sepuluh penuh berisi sepuluh lingkaran, ditambah lima lingkaran lepas di luar bingkai, mewakili bilangan lima belas',
    ];

    /** @var array<string, string> */
    private const QUESTION_ALT_TEXTS = [
        'B3-A21' => 'Barisan kartu bilangan 3, 4, 5, kotak kosong, 7, 8, 9 tersusun berurutan maju',
        'B3-A22' => 'Jalur kartu bilangan 9, 10, 11, kotak kosong, 13, 14, 15 tersusun dari kiri ke kanan, dengan panah arah mundur ke kiri',
        'B3-A23' => 'Kartu bilangan 9 di tengah, dengan kotak kosong di sebelah kiri (sebelum) dan kotak kosong di sebelah kanan (sesudah)',
        'B3-A24' => 'Dua bingkai lima berdampingan, bingkai pertama penuh berisi 5 lingkaran, bingkai kedua berisi 4 lingkaran',
        'B3-A25' => '6 kancing terbagi menjadi kelompok merah berisi 4 kancing dan kelompok biru berisi 2 kancing',
        'B3-A26' => 'Satu bingkai sepuluh penuh berisi 10 lingkaran, ditambah 3 lingkaran lepas di luar bingkai',
        'B3-A27' => 'Satu bingkai sepuluh penuh berisi 10 lingkaran, ditambah 5 lingkaran lepas di luar bingkai, mewakili bilangan 15',
        'B3-A18' => 'Dua bingkai sepuluh, masing-masing penuh berisi 10 lingkaran, tanpa ada lingkaran lepas di luar bingkai',
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
    public function install(string $zipPath): array
    {
        [$stageDirectory, $sourcePaths] = $this->stageAssets($zipPath);

        try {
            return DB::transaction(function () use ($sourcePaths): array {
                $program = $this->program();
                $course = $this->course($program);
                $competency = $this->competency($course);
                $module = $this->module($competency);
                $lessons = $this->installLessons($module, $sourcePaths);
                [$assessment, $questions] = $this->installAssessment($course, $competency, $sourcePaths);
                $this->installClassAssignments($course, $assessment);

                return [
                    'unique_source_assets' => count($sourcePaths),
                    'lessons' => count($lessons),
                    'lesson_blocks' => array_sum(array_map(
                        fn (Lesson $lesson): int => count($lesson->content_document['content'] ?? []),
                        $lessons,
                    )),
                    'lesson_assets' => LessonAsset::query()
                        ->whereIn('lesson_id', array_map(fn (Lesson $lesson): int => $lesson->id, $lessons))
                        ->whereIn('original_name', array_values(self::ASSET_FILENAMES))
                        ->count(),
                    'checkpoints' => LessonCheckpoint::query()
                        ->whereIn('lesson_id', array_map(fn (Lesson $lesson): int => $lesson->id, $lessons))
                        ->get()
                        ->filter(fn (LessonCheckpoint $checkpoint): bool => $this->isB3Checkpoint($checkpoint))
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
    private function stageAssets(string $zipPath): array
    {
        if (! is_file($zipPath) || ! is_readable($zipPath)) {
            throw new RuntimeException("Asset ZIP is missing or unreadable: {$zipPath}");
        }

        $stageDirectory = sys_get_temp_dir().'/b3-content-'.Str::lower(Str::random(16));
        File::ensureDirectoryExists($stageDirectory);

        try {
            $paths = $this->extractAssets($zipPath, $stageDirectory);
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

            if (count($paths) !== 26 || count($hashes) !== 26) {
                throw new RuntimeException('The B.3 package must contain exactly 26 unique PNG assets.');
            }

            return [$stageDirectory, $paths];
        } catch (\Throwable $exception) {
            File::deleteDirectory($stageDirectory);

            throw $exception;
        }
    }

    /** @return array<string, string> */
    private function extractAssets(string $zipPath, string $stageDirectory): array
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

                $basename = basename(str_replace('\\', '/', $entryName));

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

            $expectedNames = array_values(self::ASSET_FILENAMES);
            $foundNames = array_keys($entries);
            sort($expectedNames);
            sort($foundNames);

            if ($foundNames !== $expectedNames) {
                $missing = array_values(array_diff($expectedNames, $foundNames));
                $unexpected = array_values(array_diff($foundNames, $expectedNames));

                throw new RuntimeException(sprintf(
                    'Asset ZIP contents do not match the B.3 manifest. Missing: %s. Unexpected: %s.',
                    $missing === [] ? 'none' : implode(', ', $missing),
                    $unexpected === [] ? 'none' : implode(', ', $unexpected),
                ));
            }

            $paths = [];

            foreach (self::ASSET_FILENAMES as $assetId => $filename) {
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
            ->where(fn ($query) => $query->where('slug', 'matematika')->orWhere('name', self::PROGRAM_NAME))
            ->get();

        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple Program records match Matematika; resolve the duplicate before installing B.3.');
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
            ->where(fn ($query) => $query->where('slug', 'matematika-fase-a-kelas-i')->orWhere('name', self::COURSE_NAME))
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
            throw new RuntimeException('Multiple B.3 competencies exist in the target course.');
        }

        $competency = $matches->first() ?? new Competency;
        $competency->fill([
            'course_id' => $course->id,
            'code' => self::COMPETENCY_CODE,
            'name' => self::COMPETENCY_TITLE,
            'slug' => 'b-3',
            'sort_order' => 2,
            'status' => AcademicStatus::Active,
        ])->save();
        $competency->restore();

        return $competency;
    }

    private function module(Competency $competency): Module
    {
        $matches = Module::withTrashed()
            ->where('competency_id', $competency->id)
            ->where(fn ($query) => $query->where('slug', 'unit-2-urutan-komposisi-dekomposisi-dan-nilai-tempat-bilangan-sampai-20')
                ->orWhere('name', self::MODULE_NAME))
            ->get();

        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple modules match the final B.3 Unit 2.');
        }

        $module = $matches->first() ?? new Module;
        $module->fill([
            'competency_id' => $competency->id,
            'name' => self::MODULE_NAME,
            'slug' => 'unit-2-urutan-komposisi-dekomposisi-dan-nilai-tempat-bilangan-sampai-20',
            'sort_order' => 2,
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
            'B3-L1' => [
                'title' => 'Mengurutkan Bilangan Maju 1–20',
                'objective' => 'Murid dapat mengurutkan bilangan 1–20 secara maju dan menentukan bilangan yang datang sesudah bilangan tertentu.',
                'duration' => 20,
                'sort_order' => 1,
                'checkpoint_codes' => ['B3-L1-CP01', 'B3-L1-CP02'],
                'blocks' => [
                    ['heading', 1, 'Mengurutkan Bilangan Maju 1–20'],
                    ['paragraph', 'Ayo berjalan maju di jalur bilangan! Kita akan belajar bilangan mana yang datang sesudah bilangan lain.'],
                    ['heading', 2, 'Amati Jalur Bilangan 1 sampai 10'],
                    ['paragraph', 'Perhatikan kartu-kartu bilangan berikut. Kartu-kartu ini disusun berurutan dari kecil ke besar, mulai dari 1 sampai 10.'],
                    ['image', 'B3-A01'],
                    ['paragraph', 'Bilangan 4 datang sesudah bilangan 3. Bilangan 8 datang sesudah bilangan 7. Setiap kita melangkah maju satu langkah, bilangannya bertambah satu.'],
                    ['callout', 'tip', 'Coba tunjuk bilangan 5 dengan jarimu. Bilangan apa yang ada tepat di sebelah kanannya? Itulah bilangan sesudah 5.'],
                    ['heading', 2, 'Ayo Coba: Bilangan yang Hilang'],
                    ['paragraph', 'Ada satu kartu yang hilang dari barisan ini. Bisakah kamu menemukan bilangan yang hilang?'],
                    ['image', 'B3-A02'],
                    ['checkpoint', 'B3-L1-CP01'],
                    ['heading', 2, 'Melangkah Lebih Jauh: Bilangan 11 sampai 20'],
                    ['paragraph', 'Sekarang kita lanjutkan langkah kita melewati bilangan 10! Sesudah 10, bilangan berikutnya adalah 11, lalu 12, terus sampai 20.'],
                    ['image', 'B3-A03'],
                    ['callout', 'info', 'Bilangan 11 sampai 19 punya nama khusus: sebelas, dua belas, tiga belas, dan seterusnya. Semuanya tetap berjalan maju satu-satu, sama seperti bilangan 1 sampai 10.'],
                    ['paragraph', 'Sekarang giliranmu mencoba lagi. Ada satu kartu yang hilang dari barisan 11 sampai 20 ini.'],
                    ['image', 'B3-A04'],
                    ['checkpoint', 'B3-L1-CP02'],
                    ['callout', 'important', 'Ingat: MAJU artinya bilangan bertambah satu setiap langkah. Ini berlaku baik di bilangan kecil (1–10) maupun bilangan besar (11–20).'],
                    ['paragraph', 'Hebat! Kamu sudah bisa mengurutkan bilangan maju dari 1 sampai 20 dan menemukan bilangan yang hilang.'],
                ],
            ],
            'B3-L2' => [
                'title' => 'Mengurutkan Bilangan Mundur 1–20',
                'objective' => 'Murid dapat mengurutkan bilangan 1–20 secara mundur dan menentukan bilangan yang datang sebelum bilangan tertentu.',
                'duration' => 20,
                'sort_order' => 2,
                'checkpoint_codes' => ['B3-L2-CP01', 'B3-L2-CP02'],
                'blocks' => [
                    ['heading', 1, 'Mengurutkan Bilangan Mundur 1–20'],
                    ['paragraph', 'Sekarang kita coba arah sebaliknya. Bilangan-bilangan tetap berbaris dari kecil ke besar seperti biasa, tapi kali ini kita akan bergerak MUNDUR, yaitu ke arah kiri.'],
                    ['heading', 2, 'Amati: Bergerak Mundur pada Jalur Bilangan 1 sampai 10'],
                    ['paragraph', 'Lihat kartu-kartu ini. Sama seperti sebelumnya, kartunya tetap berurutan dari 1 sampai 10 dari kiri ke kanan. Tapi sekarang perhatikan arah panahnya: mengarah ke KIRI.'],
                    ['image', 'B3-A05'],
                    ['paragraph', 'Kalau kita mulai dari 7 dan bergerak mundur satu langkah ke kiri, kita sampai di 6. Bilangan 6 disebut bilangan SEBELUM 7 — dan benar, 6 berada tepat di sebelah kiri 7.'],
                    ['callout', 'tip', 'Coba tunjuk bilangan 8 dengan jarimu. Bilangan apa yang ada tepat di sebelah KIRInya? Itulah bilangan sebelum 8.'],
                    ['heading', 2, 'Ayo Coba: Bilangan yang Hilang saat Mundur'],
                    ['paragraph', 'Ada satu kartu yang hilang. Kartu kosong itu ada di sebelah kiri angka 5. Bisakah kamu menemukan bilangan yang hilang?'],
                    ['image', 'B3-A06'],
                    ['checkpoint', 'B3-L2-CP01'],
                    ['heading', 2, 'Melangkah Mundur Lebih Jauh: Bilangan 11 sampai 20'],
                    ['paragraph', 'Sekarang kita coba pada bilangan yang lebih besar. Kartu-kartu ini tetap berurutan dari 11 sampai 20 dari kiri ke kanan, dan kita akan bergerak mundur ke kiri lagi.'],
                    ['image', 'B3-A07'],
                    ['callout', 'info', 'Coba mulai dari 20, lalu bergerak ke kiri sambil menyebutkan bilangannya: dua puluh, sembilan belas, delapan belas...'],
                    ['paragraph', 'Sekarang giliranmu. Ada satu kartu yang hilang di sebelah kiri angka 14.'],
                    ['image', 'B3-A08'],
                    ['checkpoint', 'B3-L2-CP02'],
                    ['callout', 'important', 'Ingat: MUNDUR artinya kita bergerak ke KIRI pada jalur bilangan. Bilangan sebelum sebuah angka selalu berada tepat di sebelah kirinya.'],
                    ['paragraph', 'Hebat! Sekarang kamu bisa mengurutkan bilangan maju (ke kanan) dan mundur (ke kiri) dari 1 sampai 20.'],
                ],
            ],
            'B3-L3' => [
                'title' => 'Komposisi dan Dekomposisi Bilangan sampai 10',
                'objective' => 'Murid dapat menunjukkan bahwa satu bilangan sampai 10 dapat dibentuk dari dua bagian (komposisi), dan dapat diurai menjadi dua bagian dengan berbagai cara (dekomposisi).',
                'duration' => 15,
                'sort_order' => 3,
                'checkpoint_codes' => ['B3-L3-CP01', 'B3-L3-CP02'],
                'blocks' => [
                    ['heading', 1, 'Komposisi dan Dekomposisi Bilangan sampai 10'],
                    ['paragraph', 'Tahukah kamu, satu bilangan bisa dibentuk dari dua kelompok yang lebih kecil? Ayo kita lihat caranya.'],
                    ['heading', 2, 'Amati: Menggabungkan Dua Kelompok'],
                    ['paragraph', 'Lihat kelereng-kelereng ini. Ada kelompok kelereng merah dan kelompok kelereng biru.'],
                    ['image', 'B3-A09'],
                    ['paragraph', 'Ada 5 kelereng merah dan 3 kelereng biru. Kalau digabungkan semuanya, jadilah 8 kelereng. Ini disebut komposisi: dua bagian digabung menjadi satu bilangan.'],
                    ['heading', 2, 'Menemukan Konsep: Bingkai-5'],
                    ['paragraph', 'Kita bisa menggunakan bingkai-5 untuk melihat komposisi lebih jelas. Setiap bingkai muat 5 lingkaran.'],
                    ['image', 'B3-A10'],
                    ['callout', 'tip', 'Bingkai pertama penuh berisi 5, bingkai kedua berisi 3. Coba hitung semuanya: 5 dan 3 menjadi 8.'],
                    ['checkpoint', 'B3-L3-CP01'],
                    ['heading', 2, 'Ayo Coba: Memecah Satu Kelompok'],
                    ['paragraph', 'Sekarang kita coba arah sebaliknya. Satu kelompok benda bisa dipecah menjadi dua bagian. Ini disebut dekomposisi.'],
                    ['image', 'B3-A11'],
                    ['paragraph', 'Ada 7 buah duku yang dibagi ke dalam dua keranjang. Keranjang pertama berisi 4 buah, keranjang kedua berisi 3 buah. 7 bisa dipecah menjadi 4 dan 3.'],
                    ['callout', 'info', 'Satu bilangan bisa dipecah dengan cara yang berbeda-beda. 7 juga bisa dipecah menjadi 5 dan 2, atau 6 dan 1. Jumlah totalnya tetap 7.'],
                    ['checkpoint', 'B3-L3-CP02'],
                    ['callout', 'important', 'Komposisi artinya menggabungkan dua bagian menjadi satu bilangan. Dekomposisi artinya memecah satu bilangan menjadi dua bagian.'],
                    ['paragraph', 'Kamu sudah bisa menggabungkan dan memecah bilangan sampai 10. Selanjutnya kita akan mencoba bilangan yang lebih besar!'],
                ],
            ],
            'B3-L4' => [
                'title' => 'Sepuluh dan Sisanya: Komposisi-Dekomposisi Bilangan 11–19',
                'objective' => 'Murid dapat menunjukkan bahwa setiap bilangan 11–19 selalu terdiri dari satu kelompok sepuluh yang penuh, ditambah sisa satuan lepas. (Bilangan 20 tidak termasuk pola ini — ditangani sebagai kasus khusus di B3-L5.)',
                'duration' => 18,
                'sort_order' => 4,
                'checkpoint_codes' => ['B3-L4-CP01', 'B3-L4-CP02'],
                'blocks' => [
                    ['heading', 1, 'Sepuluh dan Sisanya'],
                    ['paragraph', 'Sekarang kita akan melihat pola bilangan 11 sampai 19. Semuanya punya satu kelompok sepuluh yang penuh dan beberapa satuan yang lepas.'],
                    ['heading', 2, 'Amati: Satu Kelompok Sepuluh yang Penuh'],
                    ['paragraph', 'Lihat gambar ini. Ada satu kelompok berisi 10 benda yang penuh, dan beberapa benda lain yang lepas di luar kelompok.'],
                    ['image', 'B3-A13'],
                    ['paragraph', 'Kelompok sepuluh yang penuh, ditambah 4 yang lepas, menjadi 14. Setiap bilangan 11 sampai 19 selalu punya satu kelompok sepuluh penuh seperti ini, hanya sisanya yang berbeda-beda. (Ada satu bilangan istimewa yang sedikit berbeda — kita akan menemukannya nanti!)'],
                    ['callout', 'tip', 'Coba tutup kelompok sepuluh dengan tanganmu, lalu hitung hanya yang lepas di luar. Itulah "sisa" dari bilangan itu.'],
                    ['heading', 2, 'Menemukan Konsep: Bilangan Lain'],
                    ['paragraph', 'Sekarang lihat bilangan 17. Sama seperti tadi, ada satu kelompok sepuluh penuh, dan sisanya lepas di luar.'],
                    ['image', 'B3-A14'],
                    ['checkpoint', 'B3-L4-CP01'],
                    ['heading', 2, 'Ayo Coba: Bentuk yang Lain'],
                    ['paragraph', 'Kelompok sepuluh tidak selalu berbentuk bingkai. Kadang benda diikat menjadi satu bundel, seperti stik es krim ini.'],
                    ['image', 'B3-A15'],
                    ['paragraph', 'Ada satu bundel berisi 10 stik yang diikat jadi satu, ditambah beberapa stik lepas. Bentuknya berbeda, tapi caranya sama: kelompok sepuluh penuh, ditambah sisa.'],
                    ['checkpoint', 'B3-L4-CP02'],
                    ['callout', 'important', 'Setiap bilangan 11 sampai 19 selalu bisa dilihat sebagai satu kelompok sepuluh yang penuh, ditambah beberapa satuan yang lepas. Bilangan 20 istimewa dan sedikit berbeda — kita akan mempelajarinya di bagian berikutnya.'],
                    ['paragraph', 'Kamu sudah menemukan pola penting ini. Sebentar lagi kita akan memberi nama khusus untuk kelompok sepuluh dan sisanya!'],
                ],
            ],
            'B3-L5' => [
                'title' => 'Nilai Tempat Bilangan sampai 20: Puluhan dan Satuan',
                'objective' => 'Murid dapat menyatakan bilangan 1–20 dalam bentuk puluhan dan satuan — mencakup bilangan satu digit (0 puluhan), bilangan 11–19 (1 puluhan dan sisa), dan kasus khusus bilangan 20 (2 puluhan).',
                'duration' => 18,
                'sort_order' => 5,
                'checkpoint_codes' => ['B3-L5-CP01', 'B3-L5-CP02'],
                'blocks' => [
                    ['heading', 1, 'Nilai Tempat Bilangan sampai 20: Puluhan dan Satuan'],
                    ['paragraph', 'Kelompok sepuluh yang sudah kita temukan tadi punya nama khusus: PULUHAN. Sisanya yang lepas juga punya nama: SATUAN. Ayo kita pelajari lebih lanjut.'],
                    ['heading', 2, 'Amati: Puluhan dan Satuan'],
                    ['paragraph', 'Lihat bilangan 7. Bilangan 7 belum memiliki kelompok sepuluh, karena jumlahnya belum sampai sepuluh. Jadi 7 terdiri dari 0 puluhan dan 7 satuan.'],
                    ['table', [['Bilangan', 'Puluhan', 'Satuan'], ['7', '0', '7']]],
                    ['paragraph', 'Sekarang lihat bilangan 16. Ada satu kelompok sepuluh yang penuh, dan 6 yang lepas di luar kelompok.'],
                    ['image', 'B3-A16'],
                    ['paragraph', 'Kelompok sepuluh yang penuh disebut PULUHAN. Sisa yang lepas disebut SATUAN. Bilangan ini punya 1 puluhan dan 6 satuan. Bilangan ini adalah 16.'],
                    ['table', [['Bilangan', 'Puluhan', 'Satuan'], ['16', '1', '6']]],
                    ['callout', 'tip', 'Puluhan artinya "kelompok sepuluh yang penuh". Satuan artinya "sisa yang lepas di luar kelompok". Bilangan yang belum punya kelompok sepuluh (seperti 7) punya 0 puluhan.'],
                    ['heading', 2, 'Menemukan Konsep: Bilangan Lain'],
                    ['paragraph', 'Sekarang lihat bilangan 19. Ada satu kelompok sepuluh penuh, dan 9 yang lepas.'],
                    ['image', 'B3-A17'],
                    ['table', [['Bilangan', 'Puluhan', 'Satuan'], ['19', '1', '9']]],
                    ['checkpoint', 'B3-L5-CP01'],
                    ['heading', 2, 'Kasus Istimewa: Bilangan 20'],
                    ['paragraph', 'Bilangan 20 sedikit berbeda. Coba perhatikan baik-baik gambar berikut.'],
                    ['image', 'B3-A18'],
                    ['paragraph', 'Bilangan 20 dapat diurai menjadi dua kelompok sepuluh. Dalam nilai tempat, itu berarti 2 puluhan dan 0 satuan.'],
                    ['paragraph', 'Pada bilangan 20, semuanya pas menjadi DUA kelompok sepuluh yang penuh. Tidak ada satuan yang lepas sama sekali. Ini berbeda dari bilangan 11 sampai 19 yang selalu punya SATU kelompok sepuluh — bilangan 20 punya DUA kelompok sepuluh.'],
                    ['table', [['Bilangan', 'Puluhan', 'Satuan'], ['20', '2', '0']]],
                    ['callout', 'important', '20 adalah bilangan istimewa: 2 puluhan dan 0 satuan. Tidak apa-apa jika satuannya berjumlah 0 — itu artinya tidak ada sisa yang lepas.'],
                    ['checkpoint', 'B3-L5-CP02'],
                    ['paragraph', 'Hebat sekali! Kamu sudah bisa menyebutkan puluhan dan satuan dari bilangan satu digit sampai 20, termasuk bilangan istimewa 20.'],
                ],
            ],
            'B3-L6' => [
                'title' => 'Tantangan Campuran B.3',
                'objective' => 'Murid dapat menerapkan kemampuan urutan, komposisi-dekomposisi, dan nilai tempat bilangan sampai 20 secara bergantian dalam satu sesi latihan.',
                'duration' => 15,
                'sort_order' => 6,
                'checkpoint_codes' => ['B3-L6-CP01', 'B3-L6-CP02', 'B3-L6-CP03'],
                'blocks' => [
                    ['heading', 1, 'Tantangan Campuran B.3'],
                    ['paragraph', 'Sekarang saatnya tantangan! Kita akan menggabungkan semua yang sudah kamu pelajari: urutan bilangan, komposisi-dekomposisi, dan nilai tempat.'],
                    ['heading', 2, 'Tantangan 1: Kartu yang Hilang'],
                    ['paragraph', 'Kali ini ada DUA kartu yang hilang dari barisan bilangan berikut. Gunakan kemampuan urutan majumu untuk menemukan keduanya.'],
                    ['image', 'B3-A19'],
                    ['checkpoint', 'B3-L6-CP01'],
                    ['heading', 2, 'Tantangan 2: Ingat Bingkai-5'],
                    ['paragraph', 'Ingatkah kamu tentang bingkai-5? Sekarang coba jawab tanpa melihat gambar, hanya dengan membayangkannya di kepalamu.'],
                    ['callout', 'tip', 'Bayangkan bingkai pertama penuh berisi 5. Berapa lagi yang perlu ditambahkan supaya menjadi 9?'],
                    ['checkpoint', 'B3-L6-CP02'],
                    ['heading', 2, 'Tantangan 3: Puluhan dan Satuan'],
                    ['paragraph', 'Terakhir, coba tentukan puluhan dan satuan dari bilangan berikut.'],
                    ['image', 'B3-A20'],
                    ['checkpoint', 'B3-L6-CP03'],
                    ['callout', 'important', 'Kamu baru saja menyelesaikan tantangan campuran B.3! Urutan, komposisi-dekomposisi, dan nilai tempat sampai 20 sudah kamu kuasai.'],
                    ['paragraph', 'Kerja bagus! Sampai jumpa di materi selanjutnya.'],
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
                ->where(fn ($query) => $query->where('slug', Str::lower($lessonCode))->orWhere('title', $data['title']))
                ->get();

            if ($matches->count() > 1) {
                throw new RuntimeException("Multiple lessons match {$lessonCode} in the B.3 module.");
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
                if ($block[0] === 'image') {
                    $assetId = $block[1];
                    $assets[$assetId] ??= $this->installLessonAsset($lesson, $assetId, $sourcePaths[$assetId]);
                }
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
            'B3-L1-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Ada satu kartu yang kosong di barisan itu. Bilangan berapa yang seharusnya ada di kartu kosong itu?',
                'options' => ['5', '6', '7', '9'],
                'correct_options' => ['6'],
                'correct_feedback' => 'Betul! Sesudah 5, bilangan berikutnya adalah 6.',
                'incorrect_feedback' => 'Coba hitung lagi dari awal barisan sambil menunjuk tiap kartu. Bilangan apa yang datang sesudah 5?',
                'explanation' => 'Barisan itu berjalan maju: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10. Setiap kartu bertambah satu dari kartu sebelumnya, jadi kartu kosong setelah 5 adalah 6.',
            ],
            'B3-L1-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Sekarang lihat barisan bilangan 11 sampai 20 di atas. Bilangan berapa yang hilang dari kartu kosong itu?',
                'options' => ['16', '17', '18', '20'],
                'correct_options' => ['17'],
                'correct_feedback' => 'Tepat sekali! 17 datang sesudah 16 dan sebelum 18.',
                'incorrect_feedback' => 'Coba hitung maju mulai dari 11: 11, 12, 13, 14, 15, 16, ..., lalu berhenti tepat sebelum 18.',
                'explanation' => 'Bilangan sesudah 16 adalah 17. Barisan 11 sampai 20 juga berjalan maju satu-satu, sama seperti barisan 1 sampai 10.',
            ],
            'B3-L2-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat jalur bilangan ini. Ada satu kartu yang hilang di sebelah kiri angka 5. Bilangan berapa yang seharusnya ada di kartu kosong itu?',
                'options' => ['3', '4', '5', '6'],
                'correct_options' => ['4'],
                'correct_feedback' => 'Benar! Kartu di sebelah kiri 5 adalah 4 — itulah bilangan sebelum 5.',
                'incorrect_feedback' => 'Lihat arah panah mundur (ke kiri). Bilangan yang tepat di sebelah kiri 5 pada jalur bilangan adalah bilangan sebelum 5.',
                'explanation' => 'Pada jalur bilangan, bilangan sebelum sebuah angka selalu berada tepat di sebelah kirinya. Bilangan sebelum 5 adalah 4.',
            ],
            'B3-L2-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat jalur bilangan ini. Ada satu kartu yang hilang di sebelah kiri angka 14. Bilangan berapa yang seharusnya ada di kartu kosong itu?',
                'options' => ['12', '13', '14', '15'],
                'correct_options' => ['13'],
                'correct_feedback' => 'Tepat! Bilangan sebelum 14 adalah 13.',
                'incorrect_feedback' => 'Lihat kartu 14, lalu cari kartu tepat di sebelah kirinya.',
                'explanation' => 'Bilangan sebelum 14 adalah 13. Jika kita terus bergerak mundur satu langkah lagi dari 13, kita sampai ke 12.',
            ],
            'B3-L3-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat dua bingkai-5 di atas. Bingkai pertama penuh berisi 5 lingkaran dan bingkai kedua berisi 3 lingkaran. Ada berapa lingkaran semuanya?',
                'options' => ['7', '8', '9', '10'],
                'correct_options' => ['8'],
                'correct_feedback' => 'Benar! 5 lingkaran dan 3 lingkaran digabung menjadi 8 lingkaran.',
                'incorrect_feedback' => 'Coba hitung semua lingkaran yang terisi di kedua bingkai, mulai dari bingkai pertama.',
                'explanation' => '8 dapat dibentuk dari 5 dan 3. Bingkai pertama menunjukkan 5, bingkai kedua menunjukkan 3, totalnya 8.',
            ],
            'B3-L3-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar 7 buah duku yang dibagi ke dalam dua keranjang. Keranjang pertama berisi 4 buah. Berapa buah duku di keranjang kedua?',
                'options' => ['2', '3', '4', '5'],
                'correct_options' => ['3'],
                'correct_feedback' => 'Betul! 7 buah duku terbagi menjadi 4 dan 3.',
                'incorrect_feedback' => 'Hitung dulu semua duku di keranjang kedua satu per satu.',
                'explanation' => '7 bisa dipecah menjadi 4 dan 3. Ini disebut dekomposisi — satu bilangan diurai menjadi dua bagian.',
            ],
            'B3-L4-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Ada satu kelompok sepuluh yang penuh dan 7 yang lepas. Ada berapa semuanya?',
                'options' => ['7', '10', '17', '70'],
                'correct_options' => ['17'],
                'correct_feedback' => 'Tepat! Satu kelompok sepuluh dan 7 lagi menjadi 17.',
                'incorrect_feedback' => 'Hitung dulu yang di dalam kelompok sepuluh (ada 10), lalu tambahkan yang lepas satu per satu.',
                'explanation' => '17 terdiri dari satu kelompok sepuluh yang penuh dan 7 satuan lepas di luar kelompok.',
            ],
            'B3-L4-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar stik di atas. Ada satu bundel berisi 10 stik. Berapa stik yang lepas di samping bundel?',
                'options' => ['2', '3', '4', '5'],
                'correct_options' => ['3'],
                'correct_feedback' => 'Benar! Ada 3 stik yang lepas di samping bundel sepuluh.',
                'incorrect_feedback' => 'Hitung hanya stik yang berada DI LUAR ikatan/bundel.',
                'explanation' => '13 terdiri dari satu kelompok sepuluh (satu bundel berisi 10 stik) dan 3 satuan lepas di sampingnya.',
            ],
            'B3-L5-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Bilangan 19 terdiri dari berapa puluhan dan berapa satuan?',
                'options' => ['1 puluhan dan 9 satuan', '9 puluhan dan 1 satuan', '1 puluhan dan 19 satuan', '19 puluhan'],
                'correct_options' => ['1 puluhan dan 9 satuan'],
                'correct_feedback' => 'Tepat sekali! 19 punya 1 puluhan (satu kelompok sepuluh) dan 9 satuan.',
                'incorrect_feedback' => 'Ingat, \'puluhan\' adalah kelompok sepuluh yang penuh, dan \'satuan\' adalah sisanya yang lepas.',
                'explanation' => '19 terdiri dari 1 puluhan (satu kelompok sepuluh) dan 9 satuan (sisa lepas).',
            ],
            'B3-L5-CP02' => [
                'type' => 'true_false',
                'prompt' => 'Perhatikan gambar di atas. Pernyataan: \'20 terdiri dari 2 puluhan dan 0 satuan.\' Benar atau salah?',
                'correct_boolean' => true,
                'correct_feedback' => 'Benar sekali! 20 adalah dua kelompok sepuluh yang penuh, dan tidak ada sisa satuan sama sekali.',
                'incorrect_feedback' => 'Hitung lagi kelompok sepuluh pada gambar. Ada berapa kelompok sepuluh? Apakah ada yang lepas di luar kelompok?',
                'explanation' => '20 adalah bilangan istimewa pada rentang ini: seluruhnya pas menjadi 2 kelompok sepuluh yang penuh, tanpa ada satuan lepas (0 satuan).',
            ],
            'B3-L6-CP01' => [
                'type' => 'multiple_select',
                'prompt' => 'Ada dua kartu kosong pada barisan itu. Pilih SEMUA bilangan yang tepat untuk kartu kosong tersebut.',
                'options' => ['9', '10', '12', '13', '14'],
                'correct_options' => ['10', '13'],
                'correct_feedback' => 'Tepat! Kartu pertama adalah 10 dan kartu kedua adalah 13.',
                'incorrect_feedback' => 'Coba urutkan angka satu per satu dari 8 sampai 15, dan perhatikan tempat yang kosong.',
                'explanation' => 'Barisan itu berjalan maju: 8, 9, 10, 11, 12, 13, 14, 15. Kartu kosong pertama adalah 10, kartu kosong kedua adalah 13.',
            ],
            'B3-L6-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => '9 dapat dibentuk dari 5 dan ...',
                'options' => ['3', '4', '5', '6'],
                'correct_options' => ['4'],
                'correct_feedback' => 'Benar! 5 dan 4 digabung menjadi 9.',
                'incorrect_feedback' => 'Ingat bingkai-5 yang sudah kamu pelajari. Bingkai pertama penuh berisi 5. Berapa lagi supaya jadi 9?',
                'explanation' => '9 dapat dibentuk dari 5 dan 4, sama seperti pola komposisi yang sudah kamu pelajari sebelumnya.',
            ],
            'B3-L6-CP03' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Bilangan itu adalah 18. Berapa satuan yang dimiliki bilangan 18?',
                'options' => ['1', '6', '8', '10'],
                'correct_options' => ['8'],
                'correct_feedback' => 'Tepat! 18 punya 1 puluhan dan 8 satuan.',
                'incorrect_feedback' => 'Hitung benda yang lepas di luar kelompok sepuluh pada gambar.',
                'explanation' => '18 terdiri dari 1 puluhan (kelompok sepuluh penuh) dan 8 satuan (sisa lepas).',
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

            if (is_string($code) && str_starts_with($code, 'B3-') && ! in_array($code, $codes, true)) {
                $checkpoint->delete();
            }
        }

        return $installed;
    }

    private function isB3Checkpoint(LessonCheckpoint $checkpoint): bool
    {
        $code = $checkpoint->configuration['code'] ?? null;

        return is_string($code) && str_starts_with($code, 'B3-');
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
                'table' => $this->tableNode($block[1]),
                default => throw new RuntimeException("Unsupported B.3 lesson block [{$block[0]}] in lesson {$lesson->slug}."),
            };
        }

        return ['type' => 'doc', 'content' => $content];
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array<string, mixed>
     */
    private function tableNode(array $rows): array
    {
        return [
            'type' => 'table',
            'content' => array_map(fn (array $row, int $rowIndex): array => [
                'type' => 'tableRow',
                'content' => array_map(fn (string $text): array => [
                    'type' => $rowIndex === 0 ? 'tableHeader' : 'tableCell',
                    'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null],
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [$this->textNode($text)],
                    ]],
                ], $row),
            ], $rows, array_keys($rows)),
        ];
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
                'code' => 'B3-Q1',
                'prompt' => 'Perhatikan barisan bilangan berikut. Bilangan berapa yang seharusnya ada di kotak kosong?',
                'asset' => 'B3-A21',
                'options' => ['5', '6', '7', '8'],
                'correct_answer' => '6',
                'explanation' => 'Barisan berjalan maju dari 3 ke 9. Sesudah 5, bilangan berikutnya adalah 6.',
            ],
            [
                'code' => 'B3-Q2',
                'prompt' => 'Perhatikan jalur bilangan berikut. Jika kita mulai dari 15 dan bergerak mundur (ke kiri), bilangan berapa yang seharusnya ada di kotak kosong itu?',
                'asset' => 'B3-A22',
                'options' => ['10', '11', '12', '13'],
                'correct_answer' => '12',
                'explanation' => 'Bilangan sebelum 13 (satu langkah mundur ke kiri) adalah 12.',
            ],
            [
                'code' => 'B3-Q3',
                'prompt' => 'Lihat kartu bilangan 9 dengan dua kotak kosong di kiri dan kanannya. Bilangan berapa yang ada tepat SEBELUM 9?',
                'asset' => 'B3-A23',
                'options' => ['7', '8', '10', '11'],
                'correct_answer' => '8',
                'explanation' => 'Bilangan sebelum 9 adalah 8, karena 8 datang tepat satu langkah sebelum 9 dalam urutan.',
            ],
            [
                'code' => 'B3-Q4',
                'prompt' => 'Lihat gambar dua bingkai berikut. Bingkai pertama berisi 5 lingkaran penuh, bingkai kedua berisi 4 lingkaran. Kalau digabungkan, bilangan berapa yang terbentuk?',
                'asset' => 'B3-A24',
                'options' => ['8', '9', '10', '14'],
                'correct_answer' => '9',
                'explanation' => '9 dapat dibentuk dari 5 dan 4 digabungkan menjadi satu.',
            ],
            [
                'code' => 'B3-Q5',
                'prompt' => 'Lihat gambar 6 kancing yang terbagi menjadi dua kelompok warna. Kelompok pertama ada 4 kancing. Berapa kancing di kelompok kedua?',
                'asset' => 'B3-A25',
                'options' => ['1', '2', '3', '4'],
                'correct_answer' => '2',
                'explanation' => '6 bisa dipecah menjadi 4 dan 2. Ini adalah dekomposisi — satu bilangan diurai menjadi dua bagian.',
            ],
            [
                'code' => 'B3-Q6',
                'prompt' => 'Lihat gambar di atas. Ada satu kelompok sepuluh yang penuh, dan beberapa yang lepas di luar kelompok. Bilangan berapa yang ditunjukkan gambar ini?',
                'asset' => 'B3-A26',
                'options' => ['3', '10', '13', '30'],
                'correct_answer' => '13',
                'explanation' => '13 terdiri dari satu kelompok sepuluh yang penuh, ditambah 3 satuan lepas di luar kelompok.',
            ],
            [
                'code' => 'B3-Q7',
                'prompt' => 'Bilangan 15 terdiri dari berapa puluhan dan berapa satuan?',
                'asset' => 'B3-A27',
                'options' => ['1 puluhan dan 5 satuan', '5 puluhan dan 1 satuan', '1 puluhan dan 15 satuan', '15 puluhan'],
                'correct_answer' => '1 puluhan dan 5 satuan',
                'explanation' => '15 terdiri dari 1 puluhan (satu kelompok sepuluh) dan 5 satuan (sisa lepas).',
            ],
            [
                'code' => 'B3-Q8',
                'prompt' => 'Perhatikan gambar dua kelompok sepuluh yang penuh ini, tanpa ada yang lepas. Bilangan berapa yang ditunjukkan, dan berapa puluhan-satuannya?',
                'asset' => 'B3-A18',
                'options' => ['20, yaitu 2 puluhan dan 0 satuan', '20, yaitu 1 puluhan dan 10 satuan', '10, yaitu 1 puluhan dan 0 satuan', '12, yaitu 1 puluhan dan 2 satuan'],
                'correct_answer' => '20, yaitu 2 puluhan dan 0 satuan',
                'explanation' => '20 adalah bilangan istimewa: seluruhnya pas menjadi 2 kelompok sepuluh tanpa sisa, jadi 2 puluhan dan 0 satuan.',
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
                        throw new RuntimeException("Question {$data['code']} has attempts and no longer matches the final B.3 handoff.");
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
            throw new RuntimeException('The B.3 assessment has attempts and no longer matches the final handoff.');
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

    private function installClassAssignments(Course $course, Assessment $assessment): void
    {
        $classes = LearningClass::query()
            ->where('course_id', $course->id)
            ->orderBy('id')
            ->get();

        foreach ($classes as $learningClass) {
            $b1Assignment = LearningClassAssessment::query()
                ->where('learning_class_id', $learningClass->id)
                ->whereHas('assessment', fn ($query) => $query->where('code', 'B1-ASSESSMENT'))
                ->first();
            $configuration = [
                'opens_at' => null,
                'closes_at' => null,
                'max_attempts' => 1,
                'status' => ClassAssessmentStatus::Active,
                'feedback_mode' => AssessmentFeedbackMode::AfterFinalAttempt,
            ];

            if ($b1Assignment instanceof LearningClassAssessment) {
                $configuration = [
                    'opens_at' => $b1Assignment->opens_at,
                    'closes_at' => $b1Assignment->closes_at,
                    'max_attempts' => $b1Assignment->max_attempts,
                    'status' => $b1Assignment->status,
                    'feedback_mode' => $b1Assignment->feedback_mode,
                ];
            }

            LearningClassAssessment::query()->firstOrCreate(
                [
                    'learning_class_id' => $learningClass->id,
                    'assessment_id' => $assessment->id,
                ],
                $configuration,
            );
        }
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
        $altText = self::QUESTION_ALT_TEXTS[$assetId];
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
        $hex = sha1('mastery-learning-center:b3:'.$value);
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
