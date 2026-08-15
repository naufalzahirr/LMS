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
use App\Enums\MasteryRuleStatus;
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
use App\Models\MasteryRule;
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

final class B2MathematicsContentInstaller
{
    private const PROGRAM_NAME = 'Matematika';

    private const COURSE_NAME = 'Matematika Fase A – Kelas I';

    private const COMPETENCY_CODE = 'B.2';

    private const COMPETENCY_TITLE = 'Membaca dan menulis bilangan sampai 20 dengan korespondensi satu-satu.';

    private const MODULE_NAME = 'Unit 2 – Membaca dan Menulis Angka';

    private const QUESTION_BANK_CODE = 'B2-BANK';

    private const QUESTION_BANK_NAME = 'Bank Soal Matematika Kelas I – B.2';

    private const ASSESSMENT_CODE = 'B2-ASSESSMENT';

    private const ASSESSMENT_TITLE = 'Asesmen B.2 — Membaca dan Menulis Angka';

    /** @var array<string, string> */
    private const ASSET_FILENAMES = [
        'B2-A01' => 'b2_a01_kartu_angka_1.png',
        'B2-A02' => 'b2_a02_kartu_angka_2.png',
        'B2-A03' => 'b2_a03_kartu_angka_3.png',
        'B2-A04' => 'b2_a04_kartu_angka_4.png',
        'B2-A05' => 'b2_a05_kartu_angka_5.png',
        'B2-A06' => 'b2_a06_kartu_angka_6.png',
        'B2-A07' => 'b2_a07_kartu_angka_7.png',
        'B2-A08' => 'b2_a08_kartu_angka_8.png',
        'B2-A09' => 'b2_a09_kartu_angka_9.png',
        'B2-A10' => 'b2_a10_kartu_angka_10.png',
        'B2-A11' => 'b2_a11_tiga_balon.png',
        'B2-A12' => 'b2_a12_lima_kelereng.png',
        'B2-A13' => 'b2_a13_delapan_kancing.png',
        'B2-A14' => 'b2_a14_sepuluh_bintang.png',
        'B2-A15' => 'b2_a15_arah_menulis_angka_1.png',
        'B2-A16' => 'b2_a16_arah_menulis_angka_2.png',
        'B2-A17' => 'b2_a17_arah_menulis_angka_5.png',
        'B2-A18' => 'b2_a18_angka_2_benar_vs_cermin.png',
        'B2-A19' => 'b2_a19_angka_5_benar_vs_cermin.png',
        'B2-A20' => 'b2_a20_arah_menulis_angka_6.png',
        'B2-A21' => 'b2_a21_arah_menulis_angka_7.png',
        'B2-A22' => 'b2_a22_angka_6_benar_vs_terbalik.png',
        'B2-A23' => 'b2_a23_angka_9_benar_vs_terbalik.png',
        'B2-A24' => 'b2_a24_angka_11_bingkai_sepuluh.png',
        'B2-A25' => 'b2_a25_angka_15_bingkai_sepuluh.png',
        'B2-A26' => 'b2_a26_angka_20_dua_bingkai_sepuluh.png',
        'B2-A27' => 'b2_a27_tigabelas_apel.png',
        'B2-A28' => 'b2_a28_tujuhbelas_pensil.png',
        'B2-A29' => 'b2_a29_arah_menulis_angka_12.png',
        'B2-A30' => 'b2_a30_angka_14_vs_41.png',
        'B2-A31' => 'b2_a31_angka_19_vs_91.png',
        'B2-A32' => 'b2_a32_enam_apel.png',
        'B2-A33' => 'b2_a33_angka_8_vs_3.png',
        'B2-A34' => 'b2_a34_enambelas_kelereng.png',
        'B2-A35' => 'b2_a35_angka_13_vs_31.png',
        'B2-A36' => 'b2_a36_kartu_angka_19.png',
        'B2-A37' => 'b2_a37_tujuh_permen.png',
        'B2-A38' => 'b2_a38_angka_4_benar_vs_cermin.png',
        'B2-A39' => 'b2_a39_enambelas_telur.png',
        'B2-A40' => 'b2_a40_angka_17_vs_71.png',
        'B2-A41' => 'b2_a41_kartu_angka_9.png',
        'B2-A42' => 'b2_a42_papan_nomor_rumah_14.png',
        'B2-A43' => 'b2_a43_halaman_buku_angka_8.png',
    ];

    /** @var array<string, string> */
    private const ASSET_ALT_TEXTS = [
        'B2-A01' => 'Kartu dengan angka 1 besar berwarna hitam',
        'B2-A02' => 'Kartu dengan angka 2 besar berwarna hitam',
        'B2-A03' => 'Kartu dengan angka 3 besar berwarna hitam',
        'B2-A04' => 'Kartu dengan angka 4 besar berwarna hitam',
        'B2-A05' => 'Kartu dengan angka 5 besar berwarna hitam',
        'B2-A06' => 'Kartu dengan angka 6 besar berwarna hitam',
        'B2-A07' => 'Kartu dengan angka 7 besar berwarna hitam',
        'B2-A08' => 'Kartu dengan angka 8 besar berwarna hitam',
        'B2-A09' => 'Kartu dengan angka 9 besar berwarna hitam',
        'B2-A10' => 'Kartu dengan angka 10 besar berwarna hitam',
        'B2-A11' => 'Tiga balon merah berjajar dalam satu baris',
        'B2-A12' => 'Lima kelereng biru berjajar dalam satu baris',
        'B2-A13' => 'Delapan kancing kuning tersusun dalam dua baris, masing-masing empat kancing',
        'B2-A14' => 'Sepuluh bintang kuning tersusun dalam dua baris, masing-masing lima bintang',
        'B2-A15' => 'Diagram arah menulis angka 1 dengan panah menunjukkan urutan goresan',
        'B2-A16' => 'Diagram arah menulis angka 2 dengan panah menunjukkan urutan goresan',
        'B2-A17' => 'Diagram arah menulis angka 5 dengan panah menunjukkan urutan goresan',
        'B2-A18' => 'Dua bentuk angka 2 berdampingan, satu ditulis benar dan satu ditulis salah',
        'B2-A19' => 'Dua bentuk angka 5 berdampingan, satu dengan arah yang benar dan satu bayangan cerminnya',
        'B2-A20' => 'Diagram arah menulis angka 6 dengan panah menunjukkan urutan goresan',
        'B2-A21' => 'Diagram arah menulis angka 7 dengan panah menunjukkan urutan goresan',
        'B2-A22' => 'Dua bentuk angka 6 berdampingan, satu ditulis benar dan satu ditulis salah',
        'B2-A23' => 'Dua bentuk angka 9 berdampingan, satu ditulis benar dan satu ditulis salah',
        'B2-A24' => 'Sepuluh lingkaran tersusun dalam satu bingkai penuh dan satu lingkaran lagi berada di sampingnya, dengan angka 11 di atas',
        'B2-A25' => 'Sepuluh lingkaran tersusun dalam satu bingkai penuh dan lima lingkaran lagi berada di sampingnya, dengan angka 15 di atas',
        'B2-A26' => 'Dua bingkai sepuluh tersusun penuh berisi total dua puluh lingkaran, dengan angka 20 di atas',
        'B2-A27' => 'Sepuluh apel tersusun dalam satu bingkai penuh dan tiga apel lagi berada di sampingnya',
        'B2-A28' => 'Sepuluh pensil tersusun dalam satu bingkai penuh dan tujuh pensil lagi berada di sampingnya',
        'B2-A29' => 'Diagram arah menulis angka 12 dengan panah menunjukkan urutan goresan',
        'B2-A30' => 'Dua tulisan angka berdampingan, satu empat belas ditulis benar dan satu tertukar urutannya',
        'B2-A31' => 'Dua tulisan angka berdampingan, satu sembilan belas ditulis benar dan satu tertukar urutannya',
        'B2-A32' => 'Enam apel merah berjajar dalam satu baris',
        'B2-A33' => 'Dua kartu angka berdampingan, angka 8 di satu sisi dan angka 3 di sisi lain',
        'B2-A34' => 'Sepuluh kelereng tersusun dalam satu bingkai penuh dan enam kelereng lagi berada di sampingnya',
        'B2-A35' => 'Dua tulisan angka berdampingan, satu tiga belas ditulis benar dan satu tertukar urutannya',
        'B2-A36' => 'Kartu dengan angka 19 besar berwarna hitam',
        'B2-A37' => 'Tujuh permen bulat berjajar dalam satu baris',
        'B2-A38' => 'Dua bentuk angka empat berdampingan, satu benar dan satu bayangan cermin',
        'B2-A39' => 'Sepuluh telur tersusun dalam satu bingkai penuh dan enam telur lagi berada di sampingnya',
        'B2-A40' => 'Dua tulisan angka berdampingan, satu tujuh belas ditulis benar dan satu tertukar urutannya',
        'B2-A41' => 'Kartu putih dengan angka 9 besar berwarna hitam',
        'B2-A42' => 'Papan nomor rumah biru bertuliskan angka 14 pada pagar putih polos',
        'B2-A43' => 'Sudut halaman buku dengan angka 8 di pojok bawah',
    ];

    /** @var array<string, string> */
    private const QUESTION_ALT_TEXTS = [
        'B2-A37' => 'Tujuh permen bulat berjajar dalam satu baris',
        'B2-A38' => 'Dua bentuk angka empat berdampingan, satu benar dan satu bayangan cermin',
        'B2-A39' => 'Sepuluh telur tersusun dalam satu bingkai penuh dan enam telur lagi berada di sampingnya',
        'B2-A40' => 'Dua tulisan angka berdampingan, satu tujuh belas ditulis benar dan satu tertukar urutannya',
        'B2-A41' => 'Kartu putih dengan angka 9 besar berwarna hitam',
        'B2-A42' => 'Papan nomor rumah biru bertuliskan angka 14 pada pagar putih polos',
        'B2-A43' => 'Sudut halaman buku dengan angka 8 di pojok bawah',
    ];

    /** @var array<string, list<string>> */
    private const REMEDIAL_LESSON_CODES = [
        'A' => ['B2-L1', 'B2-L3'],
        'B' => ['B2-L2', 'B2-L4'],
        'C' => ['B2-L5', 'B2-L6'],
    ];

    public function __construct(
        private readonly LessonContentService $lessonContent,
        private readonly QuestionService $questions,
        private readonly MasteryRuleService $masteryRules,
    ) {}

    /**
     * @return array{
     *     source_assets: int,
     *     lessons: int,
     *     lesson_blocks: int,
     *     lesson_assets: int,
     *     checkpoints: int,
     *     questions: int,
     *     question_assets: int,
     *     class_assignments: int,
     *     mastery_rules: int
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
                $this->installClassAssignments($course, $competency, $assessment, $lessons);
                $lessonIds = array_map(fn (Lesson $lesson): int => $lesson->id, $lessons);
                $questionIds = array_map(fn (Question $question): int => $question->id, $questions);

                return [
                    'source_assets' => count($sourcePaths),
                    'lessons' => count($lessons),
                    'lesson_blocks' => array_sum(array_map(
                        fn (Lesson $lesson): int => count($lesson->content_document['content'] ?? []),
                        $lessons,
                    )),
                    'lesson_assets' => LessonAsset::query()
                        ->whereIn('lesson_id', $lessonIds)
                        ->whereIn('original_name', array_values(self::ASSET_FILENAMES))
                        ->count(),
                    'checkpoints' => LessonCheckpoint::query()
                        ->whereIn('lesson_id', $lessonIds)
                        ->get()
                        ->filter(fn (LessonCheckpoint $checkpoint): bool => $this->isB2Checkpoint($checkpoint))
                        ->count(),
                    'questions' => count($questions),
                    'question_assets' => QuestionAsset::query()->whereIn('question_id', $questionIds)->count(),
                    'class_assignments' => LearningClassAssessment::query()
                        ->where('assessment_id', $assessment->id)
                        ->count(),
                    'mastery_rules' => MasteryRule::query()
                        ->where('competency_id', $competency->id)
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

        $stageDirectory = sys_get_temp_dir().'/b2-content-'.Str::lower(Str::random(16));
        File::ensureDirectoryExists($stageDirectory);

        try {
            $paths = $this->extractAssets($zipPath, $stageDirectory);

            foreach ($paths as $assetId => $path) {
                $bytes = file_get_contents($path);

                if (! is_string($bytes)
                    || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
                    || getimagesizefromstring($bytes) === false) {
                    throw new RuntimeException("Asset {$assetId} is not a valid PNG image.");
                }
            }

            if (count($paths) !== 43) {
                throw new RuntimeException('The B.2 package must contain exactly 43 PNG assets.');
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
                    'Asset ZIP contents do not match the B.2 manifest. Missing: %s. Unexpected: %s.',
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
            throw new RuntimeException('Multiple Program records match Matematika; resolve the duplicate before installing B.2.');
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
            throw new RuntimeException('Multiple B.2 competencies exist in the target course.');
        }

        $competency = $matches->first() ?? new Competency;
        $competency->fill([
            'course_id' => $course->id,
            'code' => self::COMPETENCY_CODE,
            'name' => self::COMPETENCY_TITLE,
            'slug' => 'b-2',
            'sort_order' => 3,
            'status' => AcademicStatus::Active,
        ])->save();
        $competency->restore();

        return $competency;
    }

    private function module(Competency $competency): Module
    {
        $matches = Module::withTrashed()
            ->where('competency_id', $competency->id)
            ->where(fn ($query) => $query->where('slug', 'unit-2-membaca-dan-menulis-angka')->orWhere('name', self::MODULE_NAME))
            ->get();

        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple modules match the final B.2 Unit 2.');
        }

        $module = $matches->first() ?? new Module;
        $module->fill([
            'competency_id' => $competency->id,
            'name' => self::MODULE_NAME,
            'slug' => 'unit-2-membaca-dan-menulis-angka',
            'sort_order' => 3,
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
            'B2-L1' => [
                'title' => 'Ayo Membaca Angka 1–5',
                'objective' => 'Murid dapat membaca lambang bilangan 1 sampai 5 dan memasangkannya dengan kuantitas benda yang sesuai.',
                'duration' => 15,
                'sort_order' => 1,
                'checkpoint_codes' => ['B2-L1-CP01', 'B2-L1-CP02'],
                'blocks' => [
                    ['heading', 2, 'Ayo Berkenalan dengan Angka'],
                    ['paragraph', 'Kamu sudah pandai membilang benda satu per satu.'],
                    ['paragraph', 'Banyak benda itu bisa dituliskan. Tulisannya disebut angka.'],
                    ['image', 'B2-A01'],
                    ['paragraph', 'Ini angka 1. Angka 1 dibaca "satu". Artinya ada satu benda.'],
                    ['image', 'B2-A02'],
                    ['paragraph', 'Ini angka 2. Angka 2 dibaca "dua".'],
                    ['image', 'B2-A03'],
                    ['paragraph', 'Ini angka 3. Angka 3 dibaca "tiga".'],
                    ['callout', 'tip', 'Setiap angka punya bentuk sendiri. Ayo ingat-ingat bentuknya.'],
                    ['image', 'B2-A11'],
                    ['paragraph', 'Lihat balon di atas. Ayo cari angka yang cocok dengan banyak balonnya.'],
                    ['checkpoint', 'B2-L1-CP01'],
                    ['heading', 2, 'Angka 4 dan 5'],
                    ['image', 'B2-A04'],
                    ['paragraph', 'Ini angka 4. Angka 4 dibaca "empat".'],
                    ['image', 'B2-A05'],
                    ['paragraph', 'Ini angka 5. Angka 5 dibaca "lima". Coba tunjuk lima jarimu.'],
                    ['image', 'B2-A12'],
                    ['paragraph', 'Lihat kelereng di atas. Ayo cari angka yang cocok.'],
                    ['checkpoint', 'B2-L1-CP02'],
                    ['callout', 'important', 'Angka menunjukkan berapa banyak benda. Angka yang berbeda punya bentuk yang berbeda.'],
                ],
            ],
            'B2-L2' => [
                'title' => 'Ayo Menulis Angka 1–5',
                'objective' => 'Murid dapat berlatih menuliskan angka 1 sampai 5 di kertas, dan mengenali bentuk tulisan angka yang benar.',
                'duration' => 15,
                'sort_order' => 2,
                'checkpoint_codes' => ['B2-L2-CP01', 'B2-L2-CP02'],
                'blocks' => [
                    ['heading', 2, 'Ayo Menulis Angka'],
                    ['paragraph', 'Sekarang kita belajar menulis angka.'],
                    ['paragraph', 'Ambil pensil dan kertasmu. Kita akan berlatih bersama.'],
                    ['image', 'B2-A15'],
                    ['paragraph', 'Ini cara menulis angka 1. Tarik garis lurus dari atas ke bawah.'],
                    ['callout', 'tip', 'Duduk tegak. Pegang pensil dengan santai, tidak terlalu kuat.'],
                    ['image', 'B2-A16'],
                    ['paragraph', 'Ini cara menulis angka 2. Mulai dari atas, lengkung ke kanan, lalu turun miring, lalu garis lurus di bawah.'],
                    ['paragraph', 'Angka 3 dimulai dari atas, dua lengkung ke kanan, seperti huruf B tanpa garis tegak di kiri.'],
                    ['callout', 'info', 'Sekarang coba sendiri. Tulis angka 1, 2, dan 3 di kertasmu, masing-masing tiga kali.'],
                    ['paragraph', 'Sudah selesai menulis? Ayo lanjutkan.'],
                    ['image', 'B2-A18'],
                    ['paragraph', 'Lihat dua angka di atas. Satu ditulis benar, satu ditulis terbalik seperti bayangan cermin.'],
                    ['checkpoint', 'B2-L2-CP01'],
                    ['heading', 2, 'Menulis Angka 4 dan 5'],
                    ['image', 'B2-A17'],
                    ['paragraph', 'Ini cara menulis angka 5. Mulai dengan garis pendek di atas, turun lurus, lalu lengkung besar di bawah.'],
                    ['paragraph', 'Angka 4 ditulis dengan dua garis miring bertemu, lalu satu garis tegak memotongnya.'],
                    ['callout', 'info', 'Coba lagi di kertasmu. Tulis angka 4 dan 5, masing-masing tiga kali.'],
                    ['callout', 'tip', 'Kalau bentuknya belum rapi, tidak apa-apa. Coba lagi pelan-pelan.'],
                    ['image', 'B2-A19'],
                    ['paragraph', 'Lihat dua angka di atas. Mana yang bentuknya benar?'],
                    ['checkpoint', 'B2-L2-CP02'],
                    ['callout', 'important', 'Menulis angka butuh latihan. Semakin sering berlatih di kertas, tulisanmu semakin rapi.'],
                ],
            ],
            'B2-L3' => [
                'title' => 'Membaca Angka 6–10',
                'objective' => 'Murid dapat membaca lambang bilangan 6 sampai 10 dan memasangkannya dengan kuantitas benda yang sesuai.',
                'duration' => 15,
                'sort_order' => 3,
                'checkpoint_codes' => ['B2-L3-CP01', 'B2-L3-CP02'],
                'blocks' => [
                    ['heading', 2, 'Angka Lebih dari Lima'],
                    ['paragraph', 'Sekarang kita kenali angka yang lebih besar dari lima.'],
                    ['image', 'B2-A06'],
                    ['paragraph', 'Ini angka 6. Angka 6 dibaca "enam".'],
                    ['image', 'B2-A07'],
                    ['paragraph', 'Ini angka 7. Angka 7 dibaca "tujuh".'],
                    ['image', 'B2-A08'],
                    ['paragraph', 'Ini angka 8. Angka 8 dibaca "delapan".'],
                    ['callout', 'tip', 'Angka 6 dan 9 bentuknya mirip tapi terbalik. Lihat baik-baik arah lengkungnya.'],
                    ['image', 'B2-A13'],
                    ['paragraph', 'Lihat kancing di atas. Ayo cari angka yang cocok.'],
                    ['checkpoint', 'B2-L3-CP01'],
                    ['heading', 2, 'Angka 9 dan 10'],
                    ['image', 'B2-A09'],
                    ['paragraph', 'Ini angka 9. Angka 9 dibaca "sembilan".'],
                    ['image', 'B2-A10'],
                    ['paragraph', 'Ini angka 10. Angka 10 ditulis dengan dua digit berdampingan: 1 dan 0. Dibaca "sepuluh".'],
                    ['image', 'B2-A14'],
                    ['paragraph', 'Lihat bintang di atas. Ayo cari angka yang cocok.'],
                    ['checkpoint', 'B2-L3-CP02'],
                    ['callout', 'important', 'Angka 10 adalah angka pertama yang ditulis dengan dua digit: satu bilangan, sepuluh, bukan "satu" dan "nol" terpisah.'],
                ],
            ],
            'B2-L4' => [
                'title' => 'Menulis Angka 6–10',
                'objective' => 'Murid dapat berlatih menuliskan angka 6 sampai 10 di kertas, dan mengenali bentuk tulisan angka yang benar.',
                'duration' => 15,
                'sort_order' => 4,
                'checkpoint_codes' => ['B2-L4-CP01', 'B2-L4-CP02'],
                'blocks' => [
                    ['heading', 2, 'Menulis Angka 6 dan 7'],
                    ['paragraph', 'Ambil pensilmu lagi. Sekarang kita menulis angka yang lebih besar.'],
                    ['image', 'B2-A20'],
                    ['paragraph', 'Ini cara menulis angka 6. Mulai dari atas, lengkung turun, lalu lingkaran kecil di bawah.'],
                    ['image', 'B2-A21'],
                    ['paragraph', 'Ini cara menulis angka 7. Garis lurus mendatar di atas, lalu garis miring turun ke bawah.'],
                    ['callout', 'tip', 'Angka 6 lengkungnya di bawah. Angka 9 lengkungnya di atas. Jangan tertukar.'],
                    ['callout', 'info', 'Ambil kertasmu. Tulis angka 6 dan 7, masing-masing tiga kali.'],
                    ['image', 'B2-A22'],
                    ['paragraph', 'Lihat dua angka 6 di atas. Mana yang ditulis dengan benar?'],
                    ['checkpoint', 'B2-L4-CP01'],
                    ['heading', 2, 'Menulis Angka 8, 9, dan 10'],
                    ['paragraph', 'Angka 8 ditulis dengan dua lingkaran kecil bertumpuk.'],
                    ['paragraph', 'Angka 9 ditulis dengan lingkaran kecil di atas, lalu garis lurus turun.'],
                    ['paragraph', 'Angka 10 ditulis dua digit berdampingan: dulu angka 1, baru angka 0 di sebelah kanannya.'],
                    ['callout', 'tip', 'Menulis angka 10 selalu dari kiri ke kanan: satu dulu, baru nol.'],
                    ['callout', 'info', 'Sekarang coba di kertasmu. Tulis angka 8, 9, dan 10, masing-masing tiga kali.'],
                    ['image', 'B2-A23'],
                    ['paragraph', 'Lihat dua angka 9 di atas. Mana yang ditulis dengan benar?'],
                    ['checkpoint', 'B2-L4-CP02'],
                    ['callout', 'important', 'Angka 6 dan 9 sering tertukar kalau tergesa-gesa. Tulis pelan-pelan dan periksa lagi.'],
                ],
            ],
            'B2-L5' => [
                'title' => 'Bilangan Sebelas Sampai Dua Puluh',
                'objective' => 'Murid dapat membaca lambang bilangan 11 sampai 20 dan memahami bahwa bilangan belasan tersusun dari satu puluhan dan beberapa satuan.',
                'duration' => 20,
                'sort_order' => 5,
                'checkpoint_codes' => ['B2-L5-CP01', 'B2-L5-CP02'],
                'blocks' => [
                    ['heading', 2, 'Sesudah Sepuluh'],
                    ['paragraph', 'Kamu sudah kenal angka 10.'],
                    ['paragraph', 'Sekarang ayo kenali angka sesudah sepuluh.'],
                    ['image', 'B2-A24'],
                    ['paragraph', 'Ini angka 11. Artinya sepuluh benda, dan satu lagi. Angka 11 dibaca "sebelas".'],
                    ['callout', 'tip', 'Sebelas ditulis dengan dua angka 1 berdampingan.'],
                    ['image', 'B2-A25'],
                    ['paragraph', 'Ini angka 15. Artinya sepuluh benda, dan lima lagi. Angka 15 dibaca "lima belas".'],
                    ['paragraph', 'Angka 15 ditulis dengan 1 di depan dan 5 di belakang. Angka 1 menunjukkan satu puluhan, dan angka 5 menunjukkan lima satuan.'],
                    ['image', 'B2-A27'],
                    ['paragraph', 'Lihat apel di atas. Ayo cari angka yang cocok dengan banyak apelnya.'],
                    ['checkpoint', 'B2-L5-CP01'],
                    ['heading', 2, 'Sampai Dua Puluh'],
                    ['image', 'B2-A26'],
                    ['paragraph', 'Ini angka 20. Artinya dua kumpulan sepuluh. Angka 20 dibaca "dua puluh".'],
                    ['paragraph', 'Bilangan 11 sampai 19 memakai kata "belas". Setelah itu, angka 20 dibaca "dua puluh".'],
                    ['callout', 'tip', 'Sebelas, dua belas, tiga belas... sampai sembilan belas. Lalu dua puluh.'],
                    ['image', 'B2-A28'],
                    ['paragraph', 'Lihat pensil di atas. Ayo cari angka yang cocok.'],
                    ['checkpoint', 'B2-L5-CP02'],
                    ['callout', 'important', 'Bilangan 11 sampai 19 terdiri dari satu kelompok sepuluh dan beberapa satuan lagi. Itu sebabnya namanya memakai kata "belas".'],
                ],
            ],
            'B2-L6' => [
                'title' => 'Menulis Angka 11–20',
                'objective' => 'Murid dapat berlatih menuliskan angka 11 sampai 20 di kertas, dan mengenali urutan digit yang benar.',
                'duration' => 20,
                'sort_order' => 6,
                'checkpoint_codes' => ['B2-L6-CP01', 'B2-L6-CP02'],
                'blocks' => [
                    ['heading', 2, 'Menulis Bilangan Belasan'],
                    ['paragraph', 'Bilangan belasan ditulis dengan dua angka.'],
                    ['paragraph', 'Kamu sudah tahu bilangan belasan punya satu puluhan dan beberapa satuan.'],
                    ['callout', 'info', 'Ambil kertas dan pensilmu. Kita akan berlatih menulis bilangan belasan.'],
                    ['image', 'B2-A29'],
                    ['paragraph', 'Ini cara menulis angka 12. Angka di tempat puluhan (1) ditulis dulu, baru angka di tempat satuan (2) di sebelah kanannya.'],
                    ['callout', 'warning', 'Hati-hati! "Tiga belas" ditulis dengan angka puluhan dulu, menjadi 13 — bukan 31.'],
                    ['paragraph', 'Pada 13, angka 1 ada di tempat puluhan dan angka 3 ada di tempat satuan.'],
                    ['callout', 'info', 'Coba di kertasmu. Tulis angka 11, 12, 13, 14, dan 15, masing-masing dua kali.'],
                    ['image', 'B2-A30'],
                    ['paragraph', 'Lihat dua tulisan angka di atas. Mana yang menuliskan "empat belas" dengan benar?'],
                    ['checkpoint', 'B2-L6-CP01'],
                    ['heading', 2, 'Angka Belasan Lainnya'],
                    ['paragraph', 'Bilangan 11 sampai 19 semuanya dimulai dengan angka 1 di tempat puluhan.'],
                    ['paragraph', 'Enam belas ditulis 16. Tujuh belas ditulis 17. Delapan belas ditulis 18. Sembilan belas ditulis 19.'],
                    ['callout', 'tip', 'Bilangan 20 berbeda. Dua puluh ditulis dengan angka 2 di tempat puluhan dan angka 0 di tempat satuan.'],
                    ['callout', 'info', 'Sekarang coba di kertasmu. Tulis angka 16 sampai 20, masing-masing satu kali.'],
                    ['image', 'B2-A31'],
                    ['paragraph', 'Lihat dua tulisan angka di atas. Mana yang menuliskan "sembilan belas" dengan benar?'],
                    ['checkpoint', 'B2-L6-CP02'],
                    ['callout', 'important', 'Bilangan 11 sampai 19 dimulai dengan angka 1 di tempat puluhan. Bilangan 20 dimulai dengan angka 2.'],
                ],
            ],
            'B2-L7' => [
                'title' => 'Tantangan B.2',
                'objective' => 'Murid dapat membaca dan menulis lambang bilangan 1 sampai 20 pada berbagai konteks baru.',
                'duration' => 25,
                'sort_order' => 7,
                'checkpoint_codes' => ['B2-L7-CP01', 'B2-L7-CP02', 'B2-L7-CP03', 'B2-L7-CP04', 'B2-L7-CP05'],
                'blocks' => [
                    ['heading', 2, 'Ayo Uji Kemampuanmu'],
                    ['paragraph', 'Kamu sudah belajar membaca dan menulis banyak angka.'],
                    ['paragraph', 'Sekarang ayo coba tantangannya!'],
                    ['callout', 'tip', 'Kerjakan pelan-pelan. Kalau salah, coba lagi ya.'],
                    ['heading', 2, 'Tantangan 1'],
                    ['image', 'B2-A32'],
                    ['checkpoint', 'B2-L7-CP01'],
                    ['heading', 2, 'Tantangan 2'],
                    ['image', 'B2-A33'],
                    ['checkpoint', 'B2-L7-CP02'],
                    ['heading', 2, 'Tantangan 3'],
                    ['image', 'B2-A34'],
                    ['checkpoint', 'B2-L7-CP03'],
                    ['heading', 2, 'Tantangan 4'],
                    ['image', 'B2-A35'],
                    ['checkpoint', 'B2-L7-CP04'],
                    ['heading', 2, 'Tantangan 5'],
                    ['image', 'B2-A36'],
                    ['checkpoint', 'B2-L7-CP05'],
                    ['callout', 'important', 'Kamu hebat! Kamu sudah bisa membaca dan menulis angka sampai dua puluh.'],
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
                throw new RuntimeException("Multiple lessons match {$lessonCode} in the B.2 module.");
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
     *     options: list<string>,
     *     correct_options: list<string>,
     *     correct_feedback: string,
     *     incorrect_feedback: string,
     *     explanation: string
     * }>
     */
    private function checkpointData(): array
    {
        return [
            'B2-L1-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Angka berapa yang cocok dengan banyak balonnya?',
                'options' => ['2', '3', '4'],
                'correct_options' => ['3'],
                'correct_feedback' => 'Hebat! Ada tiga balon, cocok dengan angka 3.',
                'incorrect_feedback' => 'Belum tepat. Bilang dulu balonnya satu per satu.',
                'explanation' => 'Ada tiga balon. Angka yang cocok adalah 3.',
            ],
            'B2-L1-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Angka berapa yang cocok dengan banyak kelerengnya?',
                'options' => ['4', '5', '6'],
                'correct_options' => ['5'],
                'correct_feedback' => 'Betul! Ada lima kelereng, cocok dengan angka 5.',
                'incorrect_feedback' => 'Belum tepat. Bilang dulu kelerengnya satu per satu.',
                'explanation' => 'Ada lima kelereng. Angka yang cocok adalah 5.',
            ],
            'B2-L2-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Mana angka 2 yang ditulis dengan benar?',
                'options' => ['Gambar kiri', 'Gambar kanan'],
                'correct_options' => ['Gambar kanan'],
                'correct_feedback' => 'Betul! Itu bentuk angka 2 yang benar.',
                'incorrect_feedback' => 'Belum tepat. Lihat lagi arah lengkungannya.',
                'explanation' => 'Angka 2 dimulai dari atas, melengkung ke kanan, lalu turun dan mendatar di bawah.',
            ],
            'B2-L2-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Mana angka 5 yang ditulis dengan benar?',
                'options' => ['Gambar kiri', 'Gambar kanan'],
                'correct_options' => ['Gambar kiri'],
                'correct_feedback' => 'Hebat! Itu bentuk angka 5 yang benar.',
                'incorrect_feedback' => 'Belum tepat. Lihat lagi arah bentuk angka 5.',
                'explanation' => 'Angka 5 di sebelah kiri memiliki arah yang benar. Gambar di sebelah kanan adalah bentuk cerminnya.',
            ],
            'B2-L3-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Angka berapa yang cocok dengan banyak kancingnya?',
                'options' => ['7', '8', '9'],
                'correct_options' => ['8'],
                'correct_feedback' => 'Betul! Ada delapan kancing, cocok dengan angka 8.',
                'incorrect_feedback' => 'Belum tepat. Bilang lagi kancingnya satu per satu.',
                'explanation' => 'Ada delapan kancing. Angka yang cocok adalah 8.',
            ],
            'B2-L3-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Angka berapa yang cocok dengan banyak bintangnya?',
                'options' => ['8', '9', '10'],
                'correct_options' => ['10'],
                'correct_feedback' => 'Hebat! Ada sepuluh bintang, cocok dengan angka 10.',
                'incorrect_feedback' => 'Belum tepat. Bilang lagi bintangnya satu per satu.',
                'explanation' => 'Ada sepuluh bintang. Angka yang cocok adalah 10.',
            ],
            'B2-L4-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Mana angka 6 yang ditulis dengan benar?',
                'options' => ['Gambar kiri', 'Gambar kanan'],
                'correct_options' => ['Gambar kiri'],
                'correct_feedback' => 'Betul! Lengkung angka 6 ada di bawah.',
                'incorrect_feedback' => 'Belum tepat. Angka 6 lengkungnya di bawah, bukan di atas.',
                'explanation' => 'Angka 6 dimulai dari atas, garis melengkung turun, lalu ada lingkaran kecil di bagian bawah.',
            ],
            'B2-L4-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Mana angka 9 yang ditulis dengan benar?',
                'options' => ['Gambar kiri', 'Gambar kanan'],
                'correct_options' => ['Gambar kanan'],
                'correct_feedback' => 'Hebat! Lingkaran angka 9 ada di atas.',
                'incorrect_feedback' => 'Belum tepat. Angka 9 lingkarannya di atas, bukan di bawah.',
                'explanation' => 'Angka 9 dimulai lingkaran kecil di atas, lalu garis lurus turun ke bawah.',
            ],
            'B2-L5-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Angka berapa yang cocok dengan banyak apelnya?',
                'options' => ['12', '13', '14'],
                'correct_options' => ['13'],
                'correct_feedback' => 'Betul! Ada sepuluh dan tiga lagi. Itu tiga belas.',
                'incorrect_feedback' => 'Belum tepat. Bilang dulu yang di bingkai sepuluh, lalu sisanya.',
                'explanation' => 'Ada sepuluh apel penuh satu bingkai, dan tiga apel lagi. Sepuluh dan tiga lagi adalah tiga belas.',
            ],
            'B2-L5-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Angka berapa yang cocok dengan banyak pensilnya?',
                'options' => ['16', '17', '18'],
                'correct_options' => ['17'],
                'correct_feedback' => 'Hebat! Ada sepuluh dan tujuh lagi. Itu tujuh belas.',
                'incorrect_feedback' => 'Belum tepat. Bilang dulu yang di bingkai sepuluh, lalu sisanya.',
                'explanation' => 'Ada sepuluh pensil penuh satu bingkai, dan tujuh pensil lagi. Sepuluh dan tujuh lagi adalah tujuh belas.',
            ],
            'B2-L6-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Mana yang menuliskan "empat belas" dengan benar?',
                'options' => ['Gambar kiri', 'Gambar kanan'],
                'correct_options' => ['Gambar kanan'],
                'correct_feedback' => 'Betul! Empat belas ditulis 1 dulu, baru 4.',
                'incorrect_feedback' => 'Belum tepat. Ingat, angka di tempat puluhan selalu ditulis lebih dulu pada bilangan belasan.',
                'explanation' => 'Pada bilangan 14, angka 1 berada di tempat puluhan dan angka 4 di tempat satuan. Itu sebabnya ditulis 1 dulu, baru 4.',
            ],
            'B2-L6-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Mana yang menuliskan "sembilan belas" dengan benar?',
                'options' => ['Gambar kiri', 'Gambar kanan'],
                'correct_options' => ['Gambar kiri'],
                'correct_feedback' => 'Hebat! Sembilan belas ditulis 1 dulu, baru 9.',
                'incorrect_feedback' => 'Belum tepat. Angka di tempat puluhan selalu ditulis lebih dulu pada bilangan belasan.',
                'explanation' => 'Pada bilangan 19, angka 1 berada di tempat puluhan dan angka 9 di tempat satuan. Itu sebabnya ditulis 1 dulu, baru 9.',
            ],
            'B2-L7-CP01' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Angka berapa yang cocok dengan banyak apelnya?',
                'options' => ['5', '6', '7'],
                'correct_options' => ['6'],
                'correct_feedback' => 'Hebat! Ada enam apel.',
                'incorrect_feedback' => 'Belum tepat. Bilang lagi satu per satu.',
                'explanation' => 'Ada enam apel. Angka yang cocok adalah 6.',
            ],
            'B2-L7-CP02' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Mana angka 8?',
                'options' => ['Gambar kiri', 'Gambar kanan'],
                'correct_options' => ['Gambar kiri'],
                'correct_feedback' => 'Betul! Itu angka 8.',
                'incorrect_feedback' => 'Belum tepat. Lihat lagi. Angka 8 memiliki dua bagian tertutup.',
                'explanation' => 'Angka di sebelah kiri adalah 8. Angka di sebelah kanan adalah 3.',
            ],
            'B2-L7-CP03' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Angka berapa yang cocok dengan banyak kelerengnya?',
                'options' => ['15', '16', '17'],
                'correct_options' => ['16'],
                'correct_feedback' => 'Hebat! Sepuluh dan enam lagi adalah enam belas.',
                'incorrect_feedback' => 'Belum tepat. Bilang dulu bingkai sepuluhnya, lalu sisanya.',
                'explanation' => 'Ada sepuluh kelereng penuh satu bingkai, dan enam kelereng lagi. Itu enam belas.',
            ],
            'B2-L7-CP04' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Mana yang menuliskan "tiga belas" dengan benar?',
                'options' => ['Gambar kiri', 'Gambar kanan'],
                'correct_options' => ['Gambar kanan'],
                'correct_feedback' => 'Betul! Tiga belas ditulis 1 dulu, baru 3.',
                'incorrect_feedback' => 'Belum tepat. Angka di tempat puluhan selalu ditulis lebih dulu pada bilangan belasan.',
                'explanation' => 'Pada bilangan 13, angka 1 berada di tempat puluhan dan angka 3 di tempat satuan.',
            ],
            'B2-L7-CP05' => [
                'type' => 'multiple_choice',
                'prompt' => 'Lihat gambar di atas. Angka ini dibaca...',
                'options' => ['delapan belas', 'sembilan belas', 'dua puluh'],
                'correct_options' => ['sembilan belas'],
                'correct_feedback' => 'Hebat! Angka 19 dibaca "sembilan belas".',
                'incorrect_feedback' => 'Belum tepat. Lihat lagi angka keduanya, itu angka 9.',
                'explanation' => 'Angka 19 terdiri dari 1 di tempat puluhan dan 9 di tempat satuan. Dibaca "sembilan belas".',
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
            $options = [];
            $correctOptionIds = [];

            foreach ($data['options'] as $index => $optionText) {
                $id = $this->deterministicUuid("{$code}:option:{$index}");
                $options[] = ['id' => $id, 'text' => $optionText];

                if (in_array($optionText, $data['correct_options'], true)) {
                    $correctOptionIds[] = $id;
                }
            }

            $checkpoint = $lesson->checkpoints()
                ->get()
                ->first(fn (LessonCheckpoint $candidate): bool => ($candidate->configuration['code'] ?? null) === $code)
                ?? new LessonCheckpoint;
            $checkpoint->fill([
                'lesson_id' => $lesson->id,
                'checkpoint_type' => LessonCheckpointType::MultipleChoice,
                'prompt' => $data['prompt'],
                'correct_feedback' => $data['correct_feedback'],
                'incorrect_feedback' => $data['incorrect_feedback'],
                'explanation' => $data['explanation'],
                'configuration' => ['code' => $code, 'options' => $options],
                'answer_key' => ['correct_option_ids' => $correctOptionIds],
                'created_by' => null,
            ])->save();
            $installed[$code] = $checkpoint->refresh();
        }

        foreach ($lesson->checkpoints()->get() as $checkpoint) {
            $code = $checkpoint->configuration['code'] ?? null;

            if (is_string($code) && str_starts_with($code, 'B2-') && ! in_array($code, $codes, true)) {
                $checkpoint->delete();
            }
        }

        return $installed;
    }

    private function isB2Checkpoint(LessonCheckpoint $checkpoint): bool
    {
        $code = $checkpoint->configuration['code'] ?? null;

        return is_string($code) && str_starts_with($code, 'B2-');
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
                default => throw new RuntimeException("Unsupported B.2 lesson block [{$block[0]}] in lesson {$lesson->slug}."),
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
     *     asset: string|null,
     *     options: list<string>,
     *     correct_answer: string,
     *     explanation: string
     * }>
     */
    private function questionData(): array
    {
        return [
            [
                'code' => 'B2-Q1',
                'prompt' => 'Ada berapa permen pada gambar? Pilih angka yang cocok.',
                'asset' => 'B2-A37',
                'options' => ['6', '7', '8'],
                'correct_answer' => '7',
                'explanation' => 'Kalau dibilang satu per satu, ada tujuh permen. Angka yang cocok adalah 7.',
            ],
            [
                'code' => 'B2-Q2',
                'prompt' => 'Mana angka 4 yang ditulis dengan benar?',
                'asset' => 'B2-A38',
                'options' => ['Gambar kiri', 'Gambar kanan'],
                'correct_answer' => 'Gambar kiri',
                'explanation' => 'Angka 4 dimulai dengan dua garis miring yang bertemu, lalu satu garis tegak memotongnya dari atas ke bawah.',
            ],
            [
                'code' => 'B2-Q3',
                'prompt' => 'Ada berapa telur pada gambar? Pilih angka yang cocok.',
                'asset' => 'B2-A39',
                'options' => ['15', '16', '17'],
                'correct_answer' => '16',
                'explanation' => 'Ada sepuluh telur dalam bingkai penuh, dan enam telur lagi. Sepuluh dan enam lagi adalah enam belas.',
            ],
            [
                'code' => 'B2-Q4',
                'prompt' => 'Mana yang menuliskan "tujuh belas" dengan benar?',
                'asset' => 'B2-A40',
                'options' => ['Gambar kiri', 'Gambar kanan'],
                'correct_answer' => 'Gambar kanan',
                'explanation' => 'Tujuh belas artinya satu puluhan dan tujuh satuan lagi. Angka di tempat puluhan (1) ditulis dulu, baru angka di tempat satuan (7).',
            ],
            [
                'code' => 'B2-Q5',
                'prompt' => 'Angka ini dibaca...',
                'asset' => 'B2-A41',
                'options' => ['tujuh', 'delapan', 'sembilan'],
                'correct_answer' => 'sembilan',
                'explanation' => 'Angka 9 dibaca "sembilan".',
            ],
            [
                'code' => 'B2-Q6',
                'prompt' => 'Manakah lambang bilangan untuk "dua belas"?',
                'asset' => null,
                'options' => ['11', '12', '13'],
                'correct_answer' => '12',
                'explanation' => 'Dua belas terdiri dari satu puluhan dan dua satuan, ditulis 12.',
            ],
            [
                'code' => 'B2-Q7',
                'prompt' => 'Ini adalah nomor rumah. Angka berapa nomor rumah ini?',
                'asset' => 'B2-A42',
                'options' => ['14', '41', '4'],
                'correct_answer' => '14',
                'explanation' => 'Angka pada papan itu adalah 14. Pada 14, angka 1 ada di tempat puluhan dan angka 4 di tempat satuan, dibaca "empat belas".',
            ],
            [
                'code' => 'B2-Q8',
                'prompt' => 'Ini adalah nomor halaman buku. Angka berapa nomor halaman ini?',
                'asset' => 'B2-A43',
                'options' => ['6', '8', '9'],
                'correct_answer' => '8',
                'explanation' => 'Angka pada halaman itu adalah 8, dibaca "delapan".',
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
                        throw new RuntimeException("Question {$data['code']} has attempts and no longer matches the final B.2 handoff.");
                    }

                    $question = $this->questions->update($question, $payload);
                }
            }

            if (is_string($data['asset'])) {
                $this->installQuestionAsset($question, $data['asset'], $sourcePaths[$data['asset']]);
            } else {
                $this->deleteQuestionAsset($question, $data['code']);
            }

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
            throw new RuntimeException('The B.2 assessment has attempts and no longer matches the final handoff.');
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

    /**
     * @param  array<string, Lesson>  $lessons
     */
    private function installClassAssignments(
        Course $course,
        Competency $competency,
        Assessment $assessment,
        array $lessons,
    ): void {
        $classes = LearningClass::query()
            ->where('course_id', $course->id)
            ->orderBy('id')
            ->get();
        $remedialLessonIds = collect(self::REMEDIAL_LESSON_CODES)
            ->flatten()
            ->unique()
            ->map(function (string $lessonCode) use ($lessons): int {
                if (! isset($lessons[$lessonCode])) {
                    throw new RuntimeException("Missing B.2 remedial lesson {$lessonCode}.");
                }

                return $lessons[$lessonCode]->id;
            })
            ->values()
            ->all();

        foreach ($classes as $learningClass) {
            $referenceAssignment = LearningClassAssessment::query()
                ->where('learning_class_id', $learningClass->id)
                ->whereHas('assessment', fn ($query) => $query->where('code', 'B3-ASSESSMENT'))
                ->first()
                ?? LearningClassAssessment::query()
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

            if ($referenceAssignment instanceof LearningClassAssessment) {
                $configuration = [
                    'opens_at' => $referenceAssignment->opens_at,
                    'closes_at' => $referenceAssignment->closes_at,
                    'max_attempts' => $referenceAssignment->max_attempts,
                    'status' => $referenceAssignment->status,
                    'feedback_mode' => $referenceAssignment->feedback_mode,
                ];
            }

            $assignment = LearningClassAssessment::query()->firstOrCreate(
                [
                    'learning_class_id' => $learningClass->id,
                    'assessment_id' => $assessment->id,
                ],
                $configuration,
            );

            $this->masteryRules->save($learningClass, $competency, [
                'learning_class_assessment_id' => $assignment->id,
                'mastery_score' => '75.00',
                'require_remedial' => true,
                'status' => MasteryRuleStatus::Active,
                'remedial_lesson_ids' => $remedialLessonIds,
            ]);
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

    private function deleteQuestionAsset(Question $question, string $questionCode): void
    {
        $asset = $question->image;

        if (! $asset instanceof QuestionAsset) {
            return;
        }

        if ($question->attemptQuestions()->exists()) {
            throw new RuntimeException("Question {$questionCode} has attempts and its image cannot be removed.");
        }

        $path = $asset->managedFilePath();
        $asset->delete();

        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }

        $question->setRelation('image', null);
    }

    private function deterministicUuid(string $value): string
    {
        $hex = sha1('mastery-learning-center:b2:'.$value);
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
