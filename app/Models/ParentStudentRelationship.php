<?php

namespace App\Models;

use App\Enums\ParentRelationshipType;
use Database\Factories\ParentStudentRelationshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $parent_id
 * @property int $student_id
 * @property ParentRelationshipType $relationship_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $parent
 * @property-read User $student
 */
#[Fillable(['parent_id', 'student_id', 'relationship_type'])]
class ParentStudentRelationship extends Model
{
    /** @use HasFactory<ParentStudentRelationshipFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
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
        return ['relationship_type' => ParentRelationshipType::class];
    }
}
