<?php

namespace App\Models;

use App\Enums\StudentCompetencyStatus;
use Carbon\CarbonInterface;
use Database\Factories\StudentCompetencyProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $enrollment_id
 * @property int $competency_id
 * @property StudentCompetencyStatus $status
 * @property string|null $latest_score
 * @property string|null $best_score
 * @property int $total_mastery_attempts
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $mastered_at
 * @property CarbonInterface|null $last_evaluated_at
 * @property-read Enrollment $enrollment
 * @property-read Competency $competency
 */
#[Fillable([
    'enrollment_id',
    'competency_id',
    'status',
    'latest_score',
    'best_score',
    'total_mastery_attempts',
    'started_at',
    'mastered_at',
    'last_evaluated_at',
])]
class StudentCompetencyProgress extends Model
{
    /** @use HasFactory<StudentCompetencyProgressFactory> */
    use HasFactory;

    protected $table = 'student_competency_progress';

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<Competency, $this> */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => StudentCompetencyStatus::class,
            'latest_score' => 'decimal:2',
            'best_score' => 'decimal:2',
            'total_mastery_attempts' => 'integer',
            'started_at' => 'datetime',
            'mastered_at' => 'datetime',
            'last_evaluated_at' => 'datetime',
        ];
    }
}
