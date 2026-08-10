<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learning_class_id
 * @property int $student_id
 * @property EnrollmentStatus $status
 * @property Carbon $enrolled_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LearningClass $learningClass
 * @property-read User $student
 * @property-read Collection<int, LessonProgress> $lessonProgress
 * @property-read Collection<int, AssessmentAttempt> $assessmentAttempts
 * @property-read Collection<int, StudentCompetencyProgress> $competencyProgress
 * @property-read Collection<int, RemedialAssignment> $remedialAssignments
 */
#[Fillable(['learning_class_id', 'student_id', 'status', 'enrolled_at', 'completed_at'])]
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<LearningClass, $this>
     */
    public function learningClass(): BelongsTo
    {
        return $this->belongsTo(LearningClass::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return HasMany<LessonProgress, $this>
     */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /** @return HasMany<AssessmentAttempt, $this> */
    public function assessmentAttempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    /** @return HasMany<StudentCompetencyProgress, $this> */
    public function competencyProgress(): HasMany
    {
        return $this->hasMany(StudentCompetencyProgress::class);
    }

    /** @return HasMany<RemedialAssignment, $this> */
    public function remedialAssignments(): HasMany
    {
        return $this->hasMany(RemedialAssignment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
