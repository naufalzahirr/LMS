<?php

namespace Tests\Feature;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentFeedbackMode;
use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonCheckpointType;
use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\Lesson;
use App\Models\LessonAsset;
use App\Models\LessonCheckpoint;
use App\Models\MasteryRule;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionAsset;
use App\Models\QuestionBank;
use App\Models\User;
use App\Services\LessonContentService;
use App\Services\StudentAssessmentPayloadService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class B3MathematicsContentInstallationTest extends TestCase
{
    use RefreshDatabase;

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

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->temporaryDirectory = sys_get_temp_dir().'/b3-content-test-'.Str::lower(Str::random(12));
        File::ensureDirectoryExists($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_final_b3_package_is_installed_and_reruns_without_duplicates(): void
    {
        $zip = $this->validAssetZip();

        $this->artisan('content:install-b3', ['zip' => $zip])
            ->expectsOutputToContain('Mathematics B.3 content installed successfully.')
            ->assertSuccessful();

        $program = Program::query()->where('slug', 'matematika')->sole();
        $course = $program->courses()->where('slug', 'matematika-fase-a-kelas-i')->sole();
        $competency = $course->competencies()->where('code', 'B.3')->sole();
        $module = $competency->modules()->where('sort_order', 2)->sole();
        $lessons = $module->lessons()->get();
        $bank = QuestionBank::query()->where('course_id', $course->id)->where('code', 'B3-BANK')->sole();
        $assessment = Assessment::query()->where('competency_id', $competency->id)->where('code', 'B3-ASSESSMENT')->sole();

        $this->assertSame('Matematika', $program->name);
        $this->assertSame('Matematika Fase A – Kelas I', $course->name);
        $this->assertSame(
            'Mengurutkan bilangan maju dan mundur, komposisi dan dekomposisi bilangan sampai 20, dan menentukan nilai tempat sampai 20.',
            $competency->name,
        );
        $this->assertSame(2, $competency->sort_order);
        $this->assertSame('Unit 2 — Urutan, Komposisi-Dekomposisi, dan Nilai Tempat Bilangan sampai 20', $module->name);
        $this->assertSame(2, $module->sort_order);
        $this->assertSame([
            'Mengurutkan Bilangan Maju 1–20',
            'Mengurutkan Bilangan Mundur 1–20',
            'Komposisi dan Dekomposisi Bilangan sampai 10',
            'Sepuluh dan Sisanya: Komposisi-Dekomposisi Bilangan 11–19',
            'Nilai Tempat Bilangan sampai 20: Puluhan dan Satuan',
            'Tantangan Campuran B.3',
        ], $lessons->pluck('title')->all());
        $this->assertSame(['b3-l1', 'b3-l2', 'b3-l3', 'b3-l4', 'b3-l5', 'b3-l6'], $lessons->pluck('slug')->all());
        $this->assertSame([20, 20, 15, 18, 18, 15], $lessons->pluck('duration_minutes')->all());

        $this->assertLessonStructure($lessons->all());
        $this->assertCheckpoints($lessons->all());
        $questions = $this->assertAssessment($bank, $assessment, $competency);
        $this->assertAssets($lessons->all(), $questions);
        $this->assertSame(0, MasteryRule::query()->where('competency_id', $competency->id)->count());

        $identifiers = [
            'program' => $program->id,
            'course' => $course->id,
            'competency' => $competency->id,
            'module' => $module->id,
            'lessons' => $lessons->modelKeys(),
            'documents' => $lessons->pluck('content_document')->all(),
            'lesson_assets' => LessonAsset::query()->whereIn('lesson_id', $lessons->modelKeys())->orderBy('id')->pluck('id')->all(),
            'checkpoints' => LessonCheckpoint::query()->whereIn('lesson_id', $lessons->modelKeys())->orderBy('id')->pluck('id')->all(),
            'bank' => $bank->id,
            'questions' => $questions->modelKeys(),
            'question_options' => $questions->flatMap->options->pluck('id')->all(),
            'question_assets' => QuestionAsset::query()->whereIn('question_id', $questions->modelKeys())->orderBy('id')->pluck('id')->all(),
            'assessment' => $assessment->id,
            'assessment_questions' => $assessment->assessmentQuestions()->pluck('id')->all(),
        ];

        $this->artisan('content:install-b3', ['zip' => $zip])->assertSuccessful();

        $rerunCompetency = Competency::query()->where('course_id', $course->id)->where('code', 'B.3')->sole();
        $rerunModule = $rerunCompetency->modules()->where('sort_order', 2)->sole();
        $rerunLessons = $rerunModule->lessons()->get();
        $rerunBank = QuestionBank::query()->where('course_id', $course->id)->where('code', 'B3-BANK')->sole();
        $rerunQuestions = $rerunBank->questions()->with(['options', 'image'])->get();
        $rerunAssessment = Assessment::query()->where('competency_id', $rerunCompetency->id)->where('code', 'B3-ASSESSMENT')->sole();

        $this->assertSame($identifiers, [
            'program' => Program::query()->where('slug', 'matematika')->sole()->id,
            'course' => $program->courses()->where('slug', 'matematika-fase-a-kelas-i')->sole()->id,
            'competency' => $rerunCompetency->id,
            'module' => $rerunModule->id,
            'lessons' => $rerunLessons->modelKeys(),
            'documents' => $rerunLessons->pluck('content_document')->all(),
            'lesson_assets' => LessonAsset::query()->whereIn('lesson_id', $rerunLessons->modelKeys())->orderBy('id')->pluck('id')->all(),
            'checkpoints' => LessonCheckpoint::query()->whereIn('lesson_id', $rerunLessons->modelKeys())->orderBy('id')->pluck('id')->all(),
            'bank' => $rerunBank->id,
            'questions' => $rerunQuestions->modelKeys(),
            'question_options' => $rerunQuestions->flatMap->options->pluck('id')->all(),
            'question_assets' => QuestionAsset::query()->whereIn('question_id', $rerunQuestions->modelKeys())->orderBy('id')->pluck('id')->all(),
            'assessment' => $rerunAssessment->id,
            'assessment_questions' => $rerunAssessment->assessmentQuestions()->pluck('id')->all(),
        ]);

        $this->assertDatabaseCount('programs', 1);
        $this->assertDatabaseCount('courses', 1);
        $this->assertDatabaseCount('competencies', 1);
        $this->assertDatabaseCount('modules', 1);
        $this->assertDatabaseCount('lessons', 6);
        $this->assertDatabaseCount('lesson_assets', 19);
        $this->assertDatabaseCount('lesson_checkpoints', 13);
        $this->assertDatabaseCount('question_banks', 1);
        $this->assertDatabaseCount('questions', 8);
        $this->assertDatabaseCount('question_options', 32);
        $this->assertDatabaseCount('question_assets', 8);
        $this->assertDatabaseCount('assessments', 1);
        $this->assertDatabaseCount('assessment_questions', 8);
    }

    public function test_installer_rejects_assets_outside_the_active_manifest_before_writing_content(): void
    {
        $assets = self::ASSET_FILENAMES;
        unset($assets['B3-A27']);
        $assets['B3-A12'] = 'b3_a12_bingkai5_kosong.png';
        $zip = $this->temporaryDirectory.'/invalid-b3.zip';
        $this->writeZip($zip, $assets);

        $this->artisan('content:install-b3', ['zip' => $zip])
            ->expectsOutputToContain('Asset ZIP contents do not match the B.3 manifest.')
            ->assertFailed();

        $this->assertDatabaseCount('programs', 0);
        $this->assertDatabaseCount('lessons', 0);
        $this->assertDatabaseCount('lesson_assets', 0);
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_installer_assigns_b3_to_existing_course_classes_and_student_flow_is_idempotent(): void
    {
        $program = Program::factory()->create([
            'name' => 'Matematika',
            'slug' => 'matematika',
            'status' => AcademicStatus::Active,
        ]);
        $course = Course::factory()->for($program)->create([
            'name' => 'Matematika Fase A – Kelas I',
            'slug' => 'matematika-fase-a-kelas-i',
            'sort_order' => 1,
            'status' => AcademicStatus::Active,
        ]);
        $b1Competency = Competency::factory()->for($course)->create([
            'code' => 'B.1',
            'name' => 'Kompetensi B.1',
            'slug' => 'b-1',
            'sort_order' => 1,
            'status' => AcademicStatus::Active,
        ]);
        $b1Assessment = Assessment::factory()->for($b1Competency)->published()->create([
            'title' => 'Asesmen B.1 — Banyak Benda di Sekitarku',
            'code' => 'B1-ASSESSMENT',
            'purpose' => AssessmentPurpose::Mastery,
        ]);
        $learningClass = LearningClass::factory()->for($course)->create([
            'name' => 'Matematika Fase A',
            'code' => 'TESTING',
        ]);
        $classWithoutB1 = LearningClass::factory()->for($course)->create();
        $b1Assignment = LearningClassAssessment::factory()
            ->for($learningClass)
            ->for($b1Assessment)
            ->create([
                'opens_at' => null,
                'closes_at' => null,
                'max_attempts' => 2,
                'status' => ClassAssessmentStatus::Active,
                'feedback_mode' => AssessmentFeedbackMode::AfterEachAttempt,
            ]);

        $this->seed(RolePermissionSeeder::class);
        $student = User::factory()->create();
        $student->assignRole('Student');
        $enrollment = Enrollment::factory()->for($learningClass)->create([
            'student_id' => $student->id,
            'status' => EnrollmentStatus::Active,
        ]);
        $zip = $this->validAssetZip();

        $this->artisan('content:install-b3', ['zip' => $zip])->assertSuccessful();

        $b3Assessment = Assessment::query()->where('code', 'B3-ASSESSMENT')->sole();
        $b3Assignment = LearningClassAssessment::query()
            ->where('learning_class_id', $learningClass->id)
            ->where('assessment_id', $b3Assessment->id)
            ->sole();
        $defaultAssignment = LearningClassAssessment::query()
            ->where('learning_class_id', $classWithoutB1->id)
            ->where('assessment_id', $b3Assessment->id)
            ->sole();

        $this->assertSame(8, $b3Assessment->assessmentQuestions()->count());
        $this->assertSame(2, $learningClass->assessmentAssignments()->count());
        $this->assertSame(1, $learningClass->assessmentAssignments()->where('assessment_id', $b1Assessment->id)->count());
        $this->assertSame($b1Assignment->id, $learningClass->assessmentAssignments()->where('assessment_id', $b1Assessment->id)->sole()->id);
        $this->assertSame(1, $learningClass->assessmentAssignments()->where('assessment_id', $b3Assessment->id)->count());
        $this->assertSame(2, $b3Assignment->max_attempts);
        $this->assertSame(ClassAssessmentStatus::Active, $b3Assignment->status);
        $this->assertSame(AssessmentFeedbackMode::AfterEachAttempt, $b3Assignment->feedback_mode);
        $this->assertSame(1, $defaultAssignment->max_attempts);
        $this->assertSame(ClassAssessmentStatus::Active, $defaultAssignment->status);
        $this->assertSame(AssessmentFeedbackMode::AfterFinalAttempt, $defaultAssignment->feedback_mode);

        $cards = app(StudentAssessmentPayloadService::class)->assignmentsForEnrollment($enrollment);
        $this->assertSame([
            'Asesmen B.1 — Banyak Benda di Sekitarku',
            'Asesmen B.3 — Urutan, Komposisi-Dekomposisi, dan Nilai Tempat Bilangan sampai 20',
        ], collect($cards)->pluck('title')->sort()->values()->all());
        $this->assertSame(8, collect($cards)->firstWhere('title', $b3Assessment->title)['question_count']);

        $this->actingAs($student)->get(route('student.classes.show', $learningClass))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/classes/Show')
                ->has('assessments', 2));
        $this->actingAs($student)->get(route('student.assessments.index', $learningClass))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/assessments/Index')
                ->has('assessments', 2));

        $assignmentIds = LearningClassAssessment::query()->orderBy('id')->pluck('id')->all();
        $this->artisan('content:install-b3', ['zip' => $zip])->assertSuccessful();

        $this->assertSame($assignmentIds, LearningClassAssessment::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame(2, $learningClass->assessmentAssignments()->count());
        $this->assertSame(1, $learningClass->assessmentAssignments()->where('assessment_id', $b1Assessment->id)->count());
        $this->assertSame(1, $learningClass->assessmentAssignments()->where('assessment_id', $b3Assessment->id)->count());
        $this->assertSame(1, $classWithoutB1->assessmentAssignments()->where('assessment_id', $b3Assessment->id)->count());
        $this->assertSame(8, $b3Assessment->assessmentQuestions()->count());
        $this->actingAs($student)->get(route('student.assessments.index', $learningClass))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('assessments', 2));
    }

    /** @param list<Lesson> $lessons */
    private function assertLessonStructure(array $lessons): void
    {
        $blockCounts = [20, 20, 19, 18, 24, 16];
        $imageCounts = [4, 4, 3, 3, 3, 2];
        $expectedImages = [
            array_slice(array_values(self::ASSET_FILENAMES), 0, 4),
            array_slice(array_values(self::ASSET_FILENAMES), 4, 4),
            array_slice(array_values(self::ASSET_FILENAMES), 8, 3),
            array_slice(array_values(self::ASSET_FILENAMES), 11, 3),
            array_slice(array_values(self::ASSET_FILENAMES), 14, 3),
            array_slice(array_values(self::ASSET_FILENAMES), 17, 2),
        ];

        foreach ($lessons as $index => $lesson) {
            $content = $lesson->content_document['content'] ?? [];
            $assets = $lesson->assets()->get()->keyBy('id');
            $actualImages = collect($content)
                ->where('type', 'lessonImage')
                ->map(fn (array $node): string => $assets->get($node['attrs']['lessonAssetId'])->original_name)
                ->values()
                ->all();

            $this->assertCount($blockCounts[$index], $content);
            $this->assertCount($imageCounts[$index], $actualImages);
            $this->assertSame($expectedImages[$index], $actualImages);
            $this->assertSame(
                $lesson->content_document,
                app(LessonContentService::class)->normalize($lesson, $lesson->content_document),
            );
        }

        $this->assertSame(117, collect($lessons)->sum(
            fn (Lesson $lesson): int => count($lesson->content_document['content'] ?? []),
        ));
        $this->assertSame(19, collect($lessons)->sum(
            fn (Lesson $lesson): int => collect($lesson->content_document['content'] ?? [])->where('type', 'lessonImage')->count(),
        ));

        $lessonFive = $lessons[4];
        $blocks = $lessonFive->content_document['content'];
        $assets = $lessonFive->assets()->get()->keyBy('id');
        $this->assertSame('b3_a16_bingkai10_penuh_16.png', $assets->get($blocks[6]['attrs']['lessonAssetId'])->original_name);
        $this->assertSame('b3_a17_bingkai10_penuh_19.png', $assets->get($blocks[12]['attrs']['lessonAssetId'])->original_name);
        $this->assertSame('b3_a18_dua_bingkai10_penuh_20.png', $assets->get($blocks[17]['attrs']['lessonAssetId'])->original_name);
        $this->assertSame(['table', 'table', 'table', 'table'], collect([4, 8, 13, 20])->map(fn (int $index): string => $blocks[$index]['type'])->all());
        $this->assertSame('lessonCheckpoint', $blocks[14]['type']);
        $this->assertSame('lessonCheckpoint', $blocks[22]['type']);
        $this->assertSame('Bilangan 20 dapat diurai menjadi dua kelompok sepuluh. Dalam nilai tempat, itu berarti 2 puluhan dan 0 satuan.', $blocks[18]['content'][0]['text']);
    }

    /** @param list<Lesson> $lessons */
    private function assertCheckpoints(array $lessons): void
    {
        $checkpoints = LessonCheckpoint::query()
            ->whereIn('lesson_id', collect($lessons)->pluck('id'))
            ->get();
        $byCode = $checkpoints->keyBy(fn (LessonCheckpoint $checkpoint): string => $checkpoint->configuration['code']);

        $this->assertCount(13, $checkpoints);
        $this->assertCount(13, $byCode);
        $this->assertSame([2, 2, 2, 2, 2, 3], collect($lessons)->map(fn (Lesson $lesson): int => $lesson->checkpoints()->count())->all());
        $this->assertSame([
            LessonCheckpointType::MultipleChoice->value => 11,
            LessonCheckpointType::MultipleSelect->value => 1,
            LessonCheckpointType::TrueFalse->value => 1,
        ], $checkpoints
            ->countBy(fn (LessonCheckpoint $checkpoint): string => $checkpoint->checkpoint_type->value)
            ->sortKeys()
            ->all());
        $this->assertTrue($checkpoints->every(
            fn (LessonCheckpoint $checkpoint): bool => filled($checkpoint->correct_feedback)
                && filled($checkpoint->incorrect_feedback)
                && filled($checkpoint->explanation),
        ));

        $expectedAnswers = [
            'B3-L1-CP01' => ['6'],
            'B3-L1-CP02' => ['17'],
            'B3-L2-CP01' => ['4'],
            'B3-L2-CP02' => ['13'],
            'B3-L3-CP01' => ['8'],
            'B3-L3-CP02' => ['3'],
            'B3-L4-CP01' => ['17'],
            'B3-L4-CP02' => ['3'],
            'B3-L5-CP01' => ['1 puluhan dan 9 satuan'],
            'B3-L5-CP02' => true,
            'B3-L6-CP01' => ['10', '13'],
            'B3-L6-CP02' => ['4'],
            'B3-L6-CP03' => ['8'],
        ];

        foreach ($expectedAnswers as $code => $answer) {
            $checkpoint = $byCode->get($code);
            $actual = $checkpoint->checkpoint_type === LessonCheckpointType::TrueFalse
                ? $checkpoint->answer_key['correct_boolean']
                : collect($checkpoint->configuration['options'])
                    ->whereIn('id', $checkpoint->answer_key['correct_option_ids'])
                    ->pluck('text')
                    ->values()
                    ->all();
            $this->assertSame($answer, $actual, "Incorrect answer key for {$code}");
        }

        $this->assertSame(
            'Ingat, \'puluhan\' adalah kelompok sepuluh yang penuh, dan \'satuan\' adalah sisanya yang lepas.',
            $byCode->get('B3-L5-CP01')->incorrect_feedback,
        );
        $this->assertSame(
            'Perhatikan gambar di atas. Pernyataan: \'20 terdiri dari 2 puluhan dan 0 satuan.\' Benar atau salah?',
            $byCode->get('B3-L5-CP02')->prompt,
        );

        $expectedCodePlacements = [
            ['B3-L1-CP01', 'B3-L1-CP02'],
            ['B3-L2-CP01', 'B3-L2-CP02'],
            ['B3-L3-CP01', 'B3-L3-CP02'],
            ['B3-L4-CP01', 'B3-L4-CP02'],
            ['B3-L5-CP01', 'B3-L5-CP02'],
            ['B3-L6-CP01', 'B3-L6-CP02', 'B3-L6-CP03'],
        ];

        foreach ($lessons as $index => $lesson) {
            $lessonCheckpoints = $lesson->checkpoints()->get()->keyBy('id');
            $actualCodes = collect($lesson->content_document['content'])
                ->where('type', 'lessonCheckpoint')
                ->map(fn (array $node): string => $lessonCheckpoints->get($node['attrs']['checkpointId'])->configuration['code'])
                ->values()
                ->all();
            $this->assertSame($expectedCodePlacements[$index], $actualCodes);
        }
    }

    /**
     * @return Collection<int, Question>
     */
    private function assertAssessment(QuestionBank $bank, Assessment $assessment, Competency $competency)
    {
        $questions = $bank->questions()->with(['options', 'image'])->get();

        $this->assertSame('Asesmen B.3 — Urutan, Komposisi-Dekomposisi, dan Nilai Tempat Bilangan sampai 20', $assessment->title);
        $this->assertSame(AssessmentPurpose::Mastery, $assessment->purpose);
        $this->assertSame(AssessmentStatus::Published, $assessment->status);
        $this->assertFalse($assessment->shuffle_questions);
        $this->assertSame(8, $questions->count());
        $this->assertTrue($questions->every(fn (Question $question): bool => $question->competency_id === $competency->id));
        $this->assertTrue($questions->every(fn (Question $question): bool => $question->question_type === QuestionType::MultipleChoice));
        $this->assertTrue($questions->every(fn (Question $question): bool => $question->default_points === '1.00'));
        $this->assertSame(8, $assessment->assessmentQuestions()->count());
        $this->assertSame(8.0, (float) $assessment->assessmentQuestions()->sum('points'));
        $this->assertSame([
            '6',
            '12',
            '8',
            '9',
            '2',
            '13',
            '1 puluhan dan 5 satuan',
            '20, yaitu 2 puluhan dan 0 satuan',
        ], $questions->map(fn (Question $question): string => $question->options->sole('is_correct', true)->option_text)->all());
        $this->assertSame([
            'b3_a21_assessment_jalur_3_9_hilang_6.png',
            'b3_a22_assessment_jalur_9_15_hilang_12_mundur.png',
            'b3_a23_assessment_kartu_9_sebelum_sesudah.png',
            'b3_a24_assessment_bingkai5_ganda_9.png',
            'b3_a25_assessment_kancing_dekomposisi_6.png',
            'b3_a26_assessment_bingkai10_penuh_13.png',
            'b3_a27_assessment_bingkai10_penuh_15.png',
            'b3_a18_dua_bingkai10_penuh_20.png',
        ], $questions->pluck('image.original_name')->all());
        $this->assertSame(
            'Kartu bilangan 9 di tengah, dengan kotak kosong di sebelah kiri (sebelum) dan kotak kosong di sebelah kanan (sesudah)',
            $questions[2]->image->alt_text,
        );
        $this->assertSame(
            'Dua bingkai sepuluh, masing-masing penuh berisi 10 lingkaran, tanpa ada lingkaran lepas di luar bingkai',
            $questions[7]->image->alt_text,
        );

        return $questions;
    }

    /**
     * @param  list<Lesson>  $lessons
     * @param  Collection<int, Question>  $questions
     */
    private function assertAssets(array $lessons, $questions): void
    {
        $lessonAssets = LessonAsset::query()->whereIn('lesson_id', collect($lessons)->pluck('id'))->get();
        $questionAssets = QuestionAsset::query()->whereIn('question_id', $questions->modelKeys())->get();
        $uniqueFilenames = $lessonAssets->pluck('original_name')
            ->merge($questionAssets->pluck('original_name'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $expectedFilenames = array_values(self::ASSET_FILENAMES);
        sort($expectedFilenames);
        $this->assertCount(19, $lessonAssets);
        $this->assertCount(8, $questionAssets);
        $this->assertCount(26, $uniqueFilenames);
        $this->assertSame($expectedFilenames, $uniqueFilenames);
        $this->assertSame(1, $lessonAssets->where('original_name', self::ASSET_FILENAMES['B3-A18'])->count());
        $this->assertSame(1, $questionAssets->where('original_name', self::ASSET_FILENAMES['B3-A18'])->count());

        foreach ($lessonAssets as $asset) {
            $this->assertNotNull($asset->managedFilePath());
            Storage::disk('local')->assertExists($asset->managedFilePath());
        }

        foreach ($questionAssets as $asset) {
            $this->assertNotNull($asset->managedFilePath());
            Storage::disk('local')->assertExists($asset->managedFilePath());
        }

        $serialized = json_encode([$uniqueFilenames, collect($lessons)->pluck('content_document')], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('b3_a12', $serialized);
        $this->assertStringNotContainsString('b3_a28', $serialized);
        $this->assertStringNotContainsString('b3_a29', $serialized);
    }

    private function validAssetZip(): string
    {
        $zip = $this->temporaryDirectory.'/b3-active-assets.zip';
        $this->writeZip($zip, self::ASSET_FILENAMES, 'B3_ASSET_ACTIVE_PASS/');

        return $zip;
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
