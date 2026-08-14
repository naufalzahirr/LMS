<?php

namespace Tests\Feature;

use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use App\Enums\LessonCheckpointType;
use App\Models\Assessment;
use App\Models\Competency;
use App\Models\Lesson;
use App\Models\LessonAsset;
use App\Models\LessonCheckpoint;
use App\Models\MasteryRule;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionAsset;
use App\Models\QuestionBank;
use App\Services\LessonContentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class B1MathematicsContentInstallationTest extends TestCase
{
    use RefreshDatabase;

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

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->temporaryDirectory = sys_get_temp_dir().'/b1-content-test-'.Str::lower(Str::random(12));
        File::ensureDirectoryExists($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_final_b1_package_is_installed_and_reruns_without_duplicates(): void
    {
        [$zipOne, $zipTwo] = $this->validAssetZips();

        $this->artisan('content:install-b1', [
            'zip-one' => $zipOne,
            'zip-two' => $zipTwo,
        ])->assertSuccessful();

        $program = Program::query()->where('slug', 'matematika')->sole();
        $course = $program->courses()->where('slug', 'matematika-fase-a-kelas-i')->sole();
        $competency = $course->competencies()->where('code', 'B.1')->sole();
        $module = $competency->modules()->where('slug', 'unit-1-banyak-benda-di-sekitarku')->sole();
        $lessons = $module->lessons()->get();
        $bank = QuestionBank::query()->where('course_id', $course->id)->where('code', 'B1-BANK')->sole();
        $assessment = Assessment::query()->where('competency_id', $competency->id)->where('code', 'B1-ASSESSMENT')->sole();

        $this->assertSame('Matematika', $program->name);
        $this->assertSame('Matematika Fase A – Kelas I', $course->name);
        $this->assertSame(
            'Membilang benda, mengenali banyak benda dalam satu kumpulan tanpa membilang (subitasi), mengenali dan menyatakan kumpulan benda yang sama banyak, lebih banyak atau lebih sedikit.',
            $competency->name,
        );
        $this->assertSame('Unit 1 – Banyak Benda di Sekitarku', $module->name);
        $this->assertSame(7, $lessons->count());
        $this->assertSame(
            ['b1-l1', 'b1-l2', 'b1-l3', 'b1-l4', 'b1-l5', 'b1-l6', 'b1-l7'],
            $lessons->pluck('slug')->all(),
        );
        $this->assertSame([15, 20, 15, 20, 20, 20, 25], $lessons->pluck('duration_minutes')->all());

        $checkpointCounts = $lessons->map(fn (Lesson $lesson): int => $lesson->checkpoints()->count())->all();
        $this->assertSame([2, 2, 2, 2, 2, 2, 5], $checkpointCounts);
        $this->assertSame(17, LessonCheckpoint::query()->whereIn('lesson_id', $lessons->modelKeys())->count());
        $this->assertSame([
            LessonCheckpointType::MultipleChoice->value => 13,
            LessonCheckpointType::MultipleSelect->value => 1,
            LessonCheckpointType::TrueFalse->value => 3,
        ], LessonCheckpoint::query()
            ->whereIn('lesson_id', $lessons->modelKeys())
            ->get()
            ->countBy(fn (LessonCheckpoint $checkpoint): string => $checkpoint->checkpoint_type->value)
            ->sortKeys()
            ->all());

        $this->assertSame(124, $lessons->sum(
            fn (Lesson $lesson): int => count($lesson->content_document['content'] ?? []),
        ));
        $this->assertSame(27, $lessons->sum(function (Lesson $lesson): int {
            return collect($lesson->content_document['content'] ?? [])->where('type', 'lessonImage')->count();
        }));
        $this->assertSame(27, LessonAsset::query()->whereIn('lesson_id', $lessons->modelKeys())->count());

        foreach ($lessons as $lesson) {
            $this->assertSame(
                $lesson->content_document,
                app(LessonContentService::class)->normalize($lesson, $lesson->content_document),
            );

            foreach (collect($lesson->content_document['content'])->where('type', 'lessonCheckpoint') as $node) {
                $this->assertDatabaseHas('lesson_checkpoints', [
                    'id' => $node['attrs']['checkpointId'],
                    'lesson_id' => $lesson->id,
                ]);
            }
        }

        foreach (LessonAsset::query()->whereIn('lesson_id', $lessons->modelKeys())->get() as $asset) {
            $this->assertNotNull($asset->managedFilePath());
            Storage::disk('local')->assertExists($asset->managedFilePath());
        }

        $questions = $bank->questions()->with(['options', 'image'])->get();
        $this->assertSame(8, $questions->count());
        $this->assertSame(8, QuestionAsset::query()->whereIn('question_id', $questions->modelKeys())->count());
        $this->assertSame(AssessmentPurpose::Mastery, $assessment->purpose);
        $this->assertSame(AssessmentStatus::Published, $assessment->status);
        $this->assertSame(8, $assessment->assessmentQuestions()->count());
        $this->assertSame(8.0, (float) $assessment->assessmentQuestions()->sum('points'));
        $this->assertSame(0, MasteryRule::query()->where('competency_id', $competency->id)->count());
        $this->assertSame([
            'b1_a26_empat_bebek.png',
            'b1_a27_sembilan_bintang.png',
            'b1_a11_kartu_empat_titik.png',
            'b1_a28_lima_sepatu_lima_kaus_kaki.png',
            'b1_a29_tujuh_balok_vs_lima_balok.png',
            'b1_a30_tiga_payung_vs_enam_payung.png',
            'b1_a31_lima_anak_empat_balon.png',
            'b1_a32_delapan_buku_di_rak.png',
        ], $questions->pluck('image.original_name')->all());

        $this->assertCorrectionPatches($lessons, $questions);
        $this->assertCheckpointFeedbackIsStored($lessons);

        $identifiers = [
            'program' => $program->id,
            'course' => $course->id,
            'competency' => $competency->id,
            'module' => $module->id,
            'lessons' => $lessons->modelKeys(),
            'lesson_assets' => LessonAsset::query()->whereIn('lesson_id', $lessons->modelKeys())->orderBy('id')->pluck('id')->all(),
            'checkpoints' => LessonCheckpoint::query()->whereIn('lesson_id', $lessons->modelKeys())->orderBy('id')->pluck('id')->all(),
            'bank' => $bank->id,
            'questions' => $questions->modelKeys(),
            'question_options' => $questions->flatMap->options->pluck('id')->all(),
            'question_assets' => QuestionAsset::query()->whereIn('question_id', $questions->modelKeys())->orderBy('id')->pluck('id')->all(),
            'assessment' => $assessment->id,
            'assessment_questions' => $assessment->assessmentQuestions()->pluck('id')->all(),
        ];

        $this->artisan('content:install-b1', [
            'zip-one' => $zipOne,
            'zip-two' => $zipTwo,
        ])->assertSuccessful();

        $this->assertSame($identifiers, [
            'program' => Program::query()->where('slug', 'matematika')->sole()->id,
            'course' => $program->courses()->where('slug', 'matematika-fase-a-kelas-i')->sole()->id,
            'competency' => Competency::query()->where('course_id', $course->id)->where('code', 'B.1')->sole()->id,
            'module' => $competency->modules()->where('slug', 'unit-1-banyak-benda-di-sekitarku')->sole()->id,
            'lessons' => $module->lessons()->get()->modelKeys(),
            'lesson_assets' => LessonAsset::query()->whereIn('lesson_id', $lessons->modelKeys())->orderBy('id')->pluck('id')->all(),
            'checkpoints' => LessonCheckpoint::query()->whereIn('lesson_id', $lessons->modelKeys())->orderBy('id')->pluck('id')->all(),
            'bank' => QuestionBank::query()->where('course_id', $course->id)->where('code', 'B1-BANK')->sole()->id,
            'questions' => Question::query()->where('question_bank_id', $bank->id)->orderBy('sort_order')->pluck('id')->all(),
            'question_options' => Question::query()->where('question_bank_id', $bank->id)->orderBy('sort_order')->with('options')->get()->flatMap->options->pluck('id')->all(),
            'question_assets' => QuestionAsset::query()->whereIn('question_id', $questions->modelKeys())->orderBy('id')->pluck('id')->all(),
            'assessment' => Assessment::query()->where('competency_id', $competency->id)->where('code', 'B1-ASSESSMENT')->sole()->id,
            'assessment_questions' => $assessment->assessmentQuestions()->pluck('id')->all(),
        ]);
    }

    public function test_installer_rejects_an_incomplete_asset_package_before_writing_content(): void
    {
        $zipOne = $this->temporaryDirectory.'/a01-a30.zip';
        $zipTwo = $this->temporaryDirectory.'/a31-only.zip';
        $this->writeZip($zipOne, array_slice(self::ASSET_FILENAMES, 0, 30, true), 'B1_ASSET_PASS_A01-A30/');
        $this->writeZip($zipTwo, ['B1-A31' => self::ASSET_FILENAMES['B1-A31']]);

        $this->artisan('content:install-b1', [
            'zip-one' => $zipOne,
            'zip-two' => $zipTwo,
        ])->assertFailed();

        $this->assertDatabaseCount('programs', 0);
        $this->assertDatabaseCount('lessons', 0);
        $this->assertDatabaseCount('lesson_assets', 0);
        $this->assertDatabaseCount('questions', 0);
    }

    /** @param Collection<int, Lesson> $lessons @param \Illuminate\Database\Eloquent\Collection<int, Question> $questions */
    private function assertCorrectionPatches($lessons, $questions): void
    {
        $checkpoints = LessonCheckpoint::query()->whereIn('lesson_id', $lessons->modelKeys())->get();
        $a23Fewest = $checkpoints->firstWhere('configuration.code', 'B1-L6-CP02');
        $a23Most = $checkpoints->firstWhere('configuration.code', 'B1-L7-CP04');
        $subitisation = $checkpoints->firstWhere('configuration.code', 'B1-L3-CP01');
        $lessonSix = $lessons->firstWhere('slug', 'b1-l6');
        $finalCallout = collect($lessonSix->content_document['content'])->last();
        $questionThree = $questions->firstWhere('sort_order', 3);
        $questionFive = $questions->firstWhere('sort_order', 5);

        $this->assertSame(
            ['Kumpulan kiri', 'Kumpulan tengah', 'Kumpulan kanan'],
            collect($a23Fewest->configuration['options'])->pluck('text')->all(),
        );
        $this->assertSame('Betul! Kumpulan kanan paling sedikit.', $a23Fewest->correct_feedback);
        $this->assertSame('Hebat! Kumpulan tengah paling banyak.', $a23Most->correct_feedback);
        $this->assertSame('Ada satu titik di setiap sudut. Semuanya ada empat titik.', $subitisation->explanation);
        $this->assertSame('Ada satu titik di setiap sudut. Semuanya ada empat titik.', $questionThree->explanation);
        $this->assertSame('callout', $finalCallout['type']);
        $this->assertSame('important', $finalCallout['attrs']['type']);
        $this->assertSame(
            'Pasangkan benda satu per satu. Kumpulan yang masih punya benda sisa adalah yang lebih banyak. Kumpulan yang bendanya lebih dulu habis adalah yang lebih sedikit.',
            $finalCallout['content'][0]['text'],
        );
        $this->assertSame('Kumpulan balok mana yang lebih banyak?', $questionFive->prompt);
        $this->assertSame(
            'Tujuh balok hijau di sebelah kiri dan lima balok hijau di sebelah kanan',
            $questionFive->image->alt_text,
        );

        $serializedContent = json_encode([
            $lessons->pluck('content_document')->all(),
            $checkpoints->toArray(),
            $questions->toArray(),
        ], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Kumpulan A', $serializedContent);
        $this->assertStringNotContainsString('Kumpulan B', $serializedContent);
        $this->assertStringNotContainsString('Kumpulan C', $serializedContent);
        $this->assertStringNotContainsString('semut', Str::lower($serializedContent));
        $this->assertStringNotContainsString('Pola persegi berarti empat.', $serializedContent);
        $this->assertStringNotContainsString('Lebih banyak artinya jumlahnya lebih besar.', $serializedContent);
    }

    /** @param Collection<int, Lesson> $lessons */
    private function assertCheckpointFeedbackIsStored($lessons): void
    {
        $checkpoints = LessonCheckpoint::query()->whereIn('lesson_id', $lessons->modelKeys())->get();

        $this->assertCount(17, $checkpoints);
        $this->assertTrue($checkpoints->every(
            fn (LessonCheckpoint $checkpoint): bool => filled($checkpoint->correct_feedback)
                && filled($checkpoint->incorrect_feedback),
        ));
    }

    /** @return array{string, string} */
    private function validAssetZips(): array
    {
        $zipOne = $this->temporaryDirectory.'/a01-a30.zip';
        $zipTwo = $this->temporaryDirectory.'/a31-a32.zip';
        $this->writeZip($zipOne, array_slice(self::ASSET_FILENAMES, 0, 30, true), 'B1_ASSET_PASS_A01-A30/');
        $this->writeZip($zipTwo, array_slice(self::ASSET_FILENAMES, 30, 2, true));

        return [$zipOne, $zipTwo];
    }

    /** @param array<string, string> $assets */
    private function writeZip(string $path, array $assets, string $prefix = ''): void
    {
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        foreach ($assets as $assetId => $filename) {
            $archive->addFromString($prefix.$filename, $this->pngBytes().$assetId);
        }

        $archive->close();
    }

    private function pngBytes(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        $this->assertIsString($bytes);

        return $bytes;
    }
}
