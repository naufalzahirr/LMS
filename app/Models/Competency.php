<?php

namespace App\Models;

use App\Enums\AcademicStatus;
use Database\Factories\CompetencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
