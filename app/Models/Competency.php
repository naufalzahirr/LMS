<?php

namespace App\Models;

use App\Enums\AcademicStatus;
use Database\Factories\CompetencyFactory;
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
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $learning_objectives
 * @property int $sort_order
 * @property AcademicStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Course $course
 * @property-read Collection<int, Module> $modules
 * @property-read Collection<int, Question> $questions
 * @property-read Collection<int, Assessment> $assessments
 * @property-read Collection<int, Competency> $prerequisites
 * @property-read Collection<int, Competency> $dependents
 * @property-read Collection<int, MasteryRule> $masteryRules
 * @property-read Collection<int, StudentCompetencyProgress> $studentProgress
 * @property-read Collection<int, RemedialAssignment> $remedialAssignments
 * @property-read int|null $modules_count
 */
#[Fillable(['course_id', 'code', 'name', 'slug', 'description', 'learning_objectives', 'sort_order', 'status'])]
class Competency extends Model
{
    /** @use HasFactory<CompetencyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<Module, $this>
     */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Assessment, $this> */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class)->orderBy('title');
    }

    /** @return BelongsToMany<Competency, $this> */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'competency_prerequisites',
            'competency_id',
            'prerequisite_competency_id',
        )->withTimestamps()->orderBy('sort_order')->orderBy('name');
    }

    /** @return BelongsToMany<Competency, $this> */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'competency_prerequisites',
            'prerequisite_competency_id',
            'competency_id',
        )->withTimestamps();
    }

    /** @return HasMany<MasteryRule, $this> */
    public function masteryRules(): HasMany
    {
        return $this->hasMany(MasteryRule::class);
    }

    /** @return HasMany<StudentCompetencyProgress, $this> */
    public function studentProgress(): HasMany
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
            'sort_order' => 'integer',
            'status' => AcademicStatus::class,
        ];
    }
}
