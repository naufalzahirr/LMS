<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
