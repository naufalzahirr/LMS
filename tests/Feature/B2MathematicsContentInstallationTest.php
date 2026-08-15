<?php

namespace Tests\Feature;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentFeedbackMode;
use App\Enums\AssessmentPurpose;
use App\Enums\EnrollmentStatus;
use App\Enums\MasteryRuleStatus;
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

class B2MathematicsContentInstallationTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->temporaryDirectory = sys_get_temp_dir().'/b2-content-test-'.Str::lower(Str::random(12));
        File::ensureDirectoryExists($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_final_b2_package_is_exact_and_idempotent(): void
    {
        $this->artisan('content:install-b2', ['zip' => $this->validAssetZip()])
            ->expectsOutputToContain('Mathematics B.2 content installed successfully.')
            ->assertSuccessful();

        $program = Program::query()->where('slug', 'matematika')->sole();
        $course = $program->courses()->where('slug', 'matematika-fase-a-kelas-i')->sole();
        $competency = $course->competencies()->where('code', 'B.2')->sole();
        $module = $competency->modules()->where('sort_order', 3)->sole();
        $lessons = $module->lessons()->get();
        $bank = QuestionBank::query()->where('course_id', $course->id)->where('code', 'B2-BANK')->sole();
        $assessment = Assessment::query()->where('competency_id', $competency->id)->where('code', 'B2-ASSESSMENT')->sole();

        $this->assertSame('Matematika', $program->name);
        $this->assertSame('Matematika Fase A – Kelas I', $course->name);
        $this->assertSame('Membaca dan menulis bilangan sampai 20 dengan korespondensi satu-satu.', $competency->name);
        $this->assertSame(3, $competency->sort_order);
        $this->assertSame('Unit 2 – Membaca dan Menulis Angka', $module->name);
        $this->assertSame(3, $module->sort_order);
        $this->assertSame([
            'Ayo Membaca Angka 1–5',
            'Ayo Menulis Angka 1–5',
            'Membaca Angka 6–10',
            'Menulis Angka 6–10',
            'Bilangan Sebelas Sampai Dua Puluh',
            'Menulis Angka 11–20',
            'Tantangan B.2',
        ], $lessons->pluck('title')->all());
        $this->assertSame(['b2-l1', 'b2-l2', 'b2-l3', 'b2-l4', 'b2-l5', 'b2-l6', 'b2-l7'], $lessons->pluck('slug')->all());
        $this->assertSame([15, 15, 15, 15, 20, 20, 25], $lessons->pluck('duration_minutes')->all());

        $this->assertLessonStructure($lessons->all());
        $this->assertCheckpoints($lessons->all());
        $questions = $this->assertAssessment($bank, $assessment, $competency);
        $this->assertAssets($lessons->all(), $questions->all());
        $this->assertContentHasNoPlaceholders($lessons->all(), $questions->all());
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

        $this->artisan('content:install-b2', ['zip' => $this->validAssetZip()])->assertSuccessful();

        $rerunCompetency = Competency::query()->where('course_id', $course->id)->where('code', 'B.2')->sole();
        $rerunModule = $rerunCompetency->modules()->where('sort_order', 3)->sole();
        $rerunLessons = $rerunModule->lessons()->get();
        $rerunBank = QuestionBank::query()->where('course_id', $course->id)->where('code', 'B2-BANK')->sole();
        $rerunQuestions = $rerunBank->questions()->with(['options', 'image'])->get();
        $rerunAssessment = Assessment::query()->where('competency_id', $rerunCompetency->id)->where('code', 'B2-ASSESSMENT')->sole();

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
    }

    public function test_installer_rejects_an_incomplete_asset_manifest_before_writing_content(): void
    {
        $zip = $this->temporaryDirectory.'/invalid-b2.zip';
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $archive->addFromString('b2_a01_kartu_angka_1.png', $this->pngBytes());
        $archive->close();

        $this->artisan('content:install-b2', ['zip' => $zip])
            ->expectsOutputToContain('Asset ZIP contents do not match the B.2 manifest.')
            ->assertFailed();

        $this->assertDatabaseCount('programs', 0);
        $this->assertDatabaseCount('lessons', 0);
        $this->assertDatabaseCount('lesson_assets', 0);
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_b2_is_added_after_b3_with_exactly_three_student_assessments_and_mastery_rule(): void
    {
        [$course, $b1Assessment, $b3Assessment] = $this->existingB1B3Course();
        $learningClass = LearningClass::factory()->for($course)->create();
        $b1Assignment = LearningClassAssessment::factory()->for($learningClass)->for($b1Assessment)->create();
        $b3Assignment = LearningClassAssessment::factory()->for($learningClass)->for($b3Assessment)->create([
            'max_attempts' => 3,
            'feedback_mode' => AssessmentFeedbackMode::AfterEachAttempt,
        ]);
        $this->seed(RolePermissionSeeder::class);
        $student = $this->user('Student');
        $enrollment = Enrollment::factory()->for($learningClass)->create([
            'student_id' => $student->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $this->artisan('content:install-b2', ['zip' => $this->validAssetZip()])->assertSuccessful();

        $this->assertSame(['B.1', 'B.3', 'B.2'], $course->competencies()->pluck('code')->all());
        $b2Competency = $course->competencies()->where('code', 'B.2')->sole();
        $b2Assessment = Assessment::query()->where('code', 'B2-ASSESSMENT')->sole();
        $b2Assignment = LearningClassAssessment::query()
            ->where('learning_class_id', $learningClass->id)
            ->where('assessment_id', $b2Assessment->id)
            ->sole();
        $rule = MasteryRule::query()
            ->where('learning_class_id', $learningClass->id)
            ->where('competency_id', $b2Competency->id)
            ->sole();

        $this->assertSame(3, $learningClass->assessmentAssignments()->count());
        $this->assertSame($b1Assignment->id, $learningClass->assessmentAssignments()->where('assessment_id', $b1Assessment->id)->sole()->id);
        $this->assertSame($b3Assignment->id, $learningClass->assessmentAssignments()->where('assessment_id', $b3Assessment->id)->sole()->id);
        $this->assertSame(3, $b2Assignment->max_attempts);
        $this->assertSame(AssessmentFeedbackMode::AfterEachAttempt, $b2Assignment->feedback_mode);
        $this->assertSame('75.00', $rule->mastery_score);
        $this->assertTrue($rule->require_remedial);
        $this->assertSame(MasteryRuleStatus::Active, $rule->status);
        $this->assertSame($b2Assignment->id, $rule->learning_class_assessment_id);
        $this->assertSame(
            ['b2-l1', 'b2-l3', 'b2-l2', 'b2-l4', 'b2-l5', 'b2-l6'],
            $rule->remedialLessons()->pluck('slug')->all(),
        );

        $cards = app(StudentAssessmentPayloadService::class)->assignmentsForEnrollment($enrollment);
        $this->assertCount(3, $cards);
        $this->assertSame([
            'Asesmen B.1 — Banyak Benda di Sekitarku',
            'Asesmen B.3 — Existing',
            'Asesmen B.2 — Membaca dan Menulis Angka',
        ], collect($cards)->pluck('title')->all());
        $this->assertSame(8, collect($cards)->firstWhere('title', $b2Assessment->title)['question_count']);
        $this->assertSame(8.0, (float) collect($cards)->firstWhere('title', $b2Assessment->title)['total_points']);

        $this->actingAs($student)->get(route('student.assessments.index', $learningClass))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/assessments/Index')
                ->has('assessments', 3));

        $assignmentIds = LearningClassAssessment::query()->orderBy('id')->pluck('id')->all();
        $ruleId = $rule->id;
        $this->artisan('content:install-b2', ['zip' => $this->validAssetZip()])->assertSuccessful();
        $this->assertSame($assignmentIds, LearningClassAssessment::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame($ruleId, MasteryRule::query()->where('competency_id', $b2Competency->id)->sole()->id);
        $this->assertSame(3, $learningClass->assessmentAssignments()->count());
    }

    public function test_b2_private_asset_uses_existing_student_parent_tutor_and_guest_authorization(): void
    {
        [$course] = $this->existingB1B3Course();
        $learningClass = LearningClass::factory()->for($course)->create();
        $this->seed(RolePermissionSeeder::class);
        $student = $this->user('Student');
        $unrelatedStudent = $this->user('Student');
        $parent = $this->user('Parent');
        $tutor = $this->user('Tutor');
        Enrollment::factory()->for($learningClass)->create([
            'student_id' => $student->id,
            'status' => EnrollmentStatus::Active,
        ]);
        $this->artisan('content:install-b2', ['zip' => $this->validAssetZip()])->assertSuccessful();

        $lesson = Lesson::query()->where('slug', 'b2-l1')->sole();
        $asset = $lesson->assets()->where('original_name', 'b2_a01_kartu_angka_1.png')->sole();
        $url = route('student.lesson-assets.file', [$learningClass, $lesson, $asset]);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($student)->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($unrelatedStudent)->get($url)->assertForbidden();
        $this->actingAs($parent)->get($url)->assertForbidden();
        $this->actingAs($tutor)->get($url)->assertForbidden();
    }

    /** @param list<Lesson> $lessons */
    private function assertLessonStructure(array $lessons): void
    {
        $expectedImages = [
            ['b2_a01_kartu_angka_1.png', 'b2_a02_kartu_angka_2.png', 'b2_a03_kartu_angka_3.png', 'b2_a11_tiga_balon.png', 'b2_a04_kartu_angka_4.png', 'b2_a05_kartu_angka_5.png', 'b2_a12_lima_kelereng.png'],
            ['b2_a15_arah_menulis_angka_1.png', 'b2_a16_arah_menulis_angka_2.png', 'b2_a18_angka_2_benar_vs_cermin.png', 'b2_a17_arah_menulis_angka_5.png', 'b2_a19_angka_5_benar_vs_cermin.png'],
            ['b2_a06_kartu_angka_6.png', 'b2_a07_kartu_angka_7.png', 'b2_a08_kartu_angka_8.png', 'b2_a13_delapan_kancing.png', 'b2_a09_kartu_angka_9.png', 'b2_a10_kartu_angka_10.png', 'b2_a14_sepuluh_bintang.png'],
            ['b2_a20_arah_menulis_angka_6.png', 'b2_a21_arah_menulis_angka_7.png', 'b2_a22_angka_6_benar_vs_terbalik.png', 'b2_a23_angka_9_benar_vs_terbalik.png'],
            ['b2_a24_angka_11_bingkai_sepuluh.png', 'b2_a25_angka_15_bingkai_sepuluh.png', 'b2_a27_tigabelas_apel.png', 'b2_a26_angka_20_dua_bingkai_sepuluh.png', 'b2_a28_tujuhbelas_pensil.png'],
            ['b2_a29_arah_menulis_angka_12.png', 'b2_a30_angka_14_vs_41.png', 'b2_a31_angka_19_vs_91.png'],
            ['b2_a32_enam_apel.png', 'b2_a33_angka_8_vs_3.png', 'b2_a34_enambelas_kelereng.png', 'b2_a35_angka_13_vs_31.png', 'b2_a36_kartu_angka_19.png'],
        ];

        foreach ($lessons as $index => $lesson) {
            $content = $lesson->content_document['content'] ?? [];
            $assets = $lesson->assets()->get()->keyBy('id');
            $actualImages = collect($content)
                ->where('type', 'lessonImage')
                ->map(fn (array $node): string => $assets->get($node['attrs']['lessonAssetId'])->original_name)
                ->values()
                ->all();

            $this->assertCount([22, 24, 21, 21, 21, 21, 20][$index], $content);
            $this->assertSame($expectedImages[$index], $actualImages);
            $this->assertSame(
                $lesson->content_document,
                app(LessonContentService::class)->normalize($lesson, $lesson->content_document),
            );
        }

        $this->assertSame(150, collect($lessons)->sum(
            fn (Lesson $lesson): int => count($lesson->content_document['content'] ?? []),
        ));
        $this->assertSame(36, collect($lessons)->sum(
            fn (Lesson $lesson): int => collect($lesson->content_document['content'] ?? [])->where('type', 'lessonImage')->count(),
        ));
    }

    /** @param list<Lesson> $lessons */
    private function assertCheckpoints(array $lessons): void
    {
        $checkpoints = LessonCheckpoint::query()
            ->whereIn('lesson_id', collect($lessons)->pluck('id'))
            ->get();
        $byCode = $checkpoints->keyBy(fn (LessonCheckpoint $checkpoint): string => $checkpoint->configuration['code']);
        $expectedAnswers = [
            'B2-L1-CP01' => '3',
            'B2-L1-CP02' => '5',
            'B2-L2-CP01' => 'Gambar kanan',
            'B2-L2-CP02' => 'Gambar kiri',
            'B2-L3-CP01' => '8',
            'B2-L3-CP02' => '10',
            'B2-L4-CP01' => 'Gambar kiri',
            'B2-L4-CP02' => 'Gambar kanan',
            'B2-L5-CP01' => '13',
            'B2-L5-CP02' => '17',
            'B2-L6-CP01' => 'Gambar kanan',
            'B2-L6-CP02' => 'Gambar kiri',
            'B2-L7-CP01' => '6',
            'B2-L7-CP02' => 'Gambar kiri',
            'B2-L7-CP03' => '16',
            'B2-L7-CP04' => 'Gambar kanan',
            'B2-L7-CP05' => 'sembilan belas',
        ];

        $this->assertCount(17, $checkpoints);
        $this->assertCount(17, $byCode);
        $this->assertSame([2, 2, 2, 2, 2, 2, 5], collect($lessons)->map(fn (Lesson $lesson): int => $lesson->checkpoints()->count())->all());

        foreach ($expectedAnswers as $code => $answer) {
            $checkpoint = $byCode->get($code);
            $correctIds = $checkpoint->answer_key['correct_option_ids'];
            $correctTexts = collect($checkpoint->configuration['options'])
                ->whereIn('id', $correctIds)
                ->pluck('text')
                ->all();
            $this->assertSame([$answer], $correctTexts, $code);
            $this->assertNotEmpty($checkpoint->correct_feedback);
            $this->assertNotEmpty($checkpoint->incorrect_feedback);
            $this->assertNotEmpty($checkpoint->explanation);
        }
    }

    private function assertAssessment(QuestionBank $bank, Assessment $assessment, Competency $competency): Collection
    {
        $questions = $bank->questions()->with(['options', 'image'])->get();
        $expectedAnswers = ['7', 'Gambar kiri', '16', 'Gambar kanan', 'sembilan', '12', '14', '8'];

        $this->assertSame('Asesmen B.2 — Membaca dan Menulis Angka', $assessment->title);
        $this->assertSame($competency->id, $assessment->competency_id);
        $this->assertCount(8, $questions);
        $this->assertSame(8.0, (float) $assessment->assessmentQuestions()->sum('points'));
        $this->assertSame(QuestionType::MultipleChoice, $questions->first()->question_type);

        foreach ($questions as $index => $question) {
            $this->assertSame($expectedAnswers[$index], $question->options->sole('is_correct', true)->option_text);
            $this->assertSame(1, $question->options->where('is_correct', true)->count());
            $this->assertSame('1.00', $question->default_points);
        }

        $this->assertNull($questions[5]->image);

        return $questions;
    }

    /**
     * @param  list<Lesson>  $lessons
     * @param  list<Question>  $questions
     */
    private function assertAssets(array $lessons, array $questions): void
    {
        $lessonAssets = LessonAsset::query()->whereIn('lesson_id', collect($lessons)->pluck('id'))->get();
        $questionAssets = QuestionAsset::query()->whereIn('question_id', collect($questions)->pluck('id'))->get();
        $allFilenames = $lessonAssets->pluck('original_name')->merge($questionAssets->pluck('original_name'))->sort()->values();

        $this->assertCount(36, $lessonAssets);
        $this->assertCount(7, $questionAssets);
        $this->assertCount(43, $allFilenames);
        $this->assertCount(43, $allFilenames->unique());

        foreach (range(1, 43) as $assetNumber) {
            $this->assertCount(1, $allFilenames->filter(
                fn (string $filename): bool => str_starts_with($filename, sprintf('b2_a%02d_', $assetNumber)),
            ));
        }

        $this->assertSame([
            'b2_a37_tujuh_permen.png',
            'b2_a38_angka_4_benar_vs_cermin.png',
            'b2_a39_enambelas_telur.png',
            'b2_a40_angka_17_vs_71.png',
            'b2_a41_kartu_angka_9.png',
            null,
            'b2_a42_papan_nomor_rumah_14.png',
            'b2_a43_halaman_buku_angka_8.png',
        ], collect($questions)->map(fn (Question $question): ?string => $question->image?->original_name)->all());

        foreach ($lessonAssets->concat($questionAssets) as $asset) {
            $this->assertNotNull($asset->managedFilePath());
            $this->assertNotEmpty($asset->alt_text);
            Storage::disk('local')->assertExists($asset->managedFilePath());
        }
    }

    /**
     * @param  list<Lesson>  $lessons
     * @param  list<Question>  $questions
     */
    private function assertContentHasNoPlaceholders(array $lessons, array $questions): void
    {
        $serialized = json_encode([
            collect($lessons)->pluck('content_document'),
            collect($lessons)->flatMap(fn (Lesson $lesson) => $lesson->checkpoints),
            $questions,
        ], JSON_THROW_ON_ERROR);

        foreach (['TODO', 'TBD', 'placeholder', 'sesuai asset nanti', 'ditentukan kemudian'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $serialized);
        }
    }

    /** @return array{Course, Assessment, Assessment} */
    private function existingB1B3Course(): array
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
        $b3Competency = Competency::factory()->for($course)->create([
            'code' => 'B.3',
            'name' => 'Kompetensi B.3',
            'slug' => 'b-3',
            'sort_order' => 2,
            'status' => AcademicStatus::Active,
        ]);
        $b1Assessment = Assessment::factory()->for($b1Competency)->published()->create([
            'title' => 'Asesmen B.1 — Banyak Benda di Sekitarku',
            'code' => 'B1-ASSESSMENT',
            'purpose' => AssessmentPurpose::Mastery,
        ]);
        $b3Assessment = Assessment::factory()->for($b3Competency)->published()->create([
            'title' => 'Asesmen B.3 — Existing',
            'code' => 'B3-ASSESSMENT',
            'purpose' => AssessmentPurpose::Mastery,
        ]);

        return [$course, $b1Assessment, $b3Assessment];
    }

    private function validAssetZip(): string
    {
        $path = base_path('B2_ASSET_PASS_A01-A43.zip');
        $this->assertFileExists($path);

        return $path;
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

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
