<?php

namespace App\Models;

use App\Enums\AcademicStatus;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $program_id
 * @property string $name
 * @property string $slug
 * @property string|null $code
 * @property string|null $description
 * @property AcademicStatus $status
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Program $program
 * @property-read Collection<int, Competency> $competencies
 * @property-read Collection<int, LearningClass> $learningClasses
 * @property-read int|null $competencies_count
 */
#[Fillable(['program_id', 'name', 'slug', 'code', 'description', 'status', 'sort_order'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return HasMany<Competency, $this>
     */
    public function competencies(): HasMany
    {
        return $this->hasMany(Competency::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * @return HasMany<LearningClass, $this>
     */
    public function learningClasses(): HasMany
    {
        return $this->hasMany(LearningClass::class)->orderBy('name');
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
