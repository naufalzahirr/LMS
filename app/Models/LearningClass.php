<?php

namespace App\Models;

use App\Enums\LearningClassStatus;
use Database\Factories\LearningClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property LearningClassStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Course $course
 * @property-read Collection<int, Enrollment> $enrollments
 * @property-read Collection<int, User> $students
 * @property-read Collection<int, User> $tutors
 * @property-read Collection<int, LearningClassAssessment> $assessmentAssignments
 * @property-read Collection<int, Assessment> $assessments
 * @property-read int|null $enrollments_count
 * @property-read int|null $students_count
 * @property-read int|null $tutors_count
 * @property-read int|null $active_students_count
 */
#[Fillable(['course_id', 'name', 'code', 'description', 'start_date', 'end_date', 'status'])]
class LearningClass extends Model
{
    /** @use HasFactory<LearningClassFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->orderByDesc('enrolled_at');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments', 'learning_class_id', 'student_id')
            ->withPivot(['id', 'status', 'enrolled_at', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function tutors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'learning_class_tutor', 'learning_class_id', 'tutor_id')
            ->withTimestamps();
    }

    /** @return HasMany<LearningClassAssessment, $this> */
    public function assessmentAssignments(): HasMany
    {
        return $this->hasMany(LearningClassAssessment::class)->orderByDesc('created_at');
    }

    /** @return BelongsToMany<Assessment, $this> */
    public function assessments(): BelongsToMany
    {
        return $this->belongsToMany(Assessment::class, 'learning_class_assessments')
            ->withPivot(['id', 'opens_at', 'closes_at', 'max_attempts', 'status'])
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => LearningClassStatus::class,
        ];
    }
}
