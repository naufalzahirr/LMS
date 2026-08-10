<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Enrollment> $enrollments
 * @property-read Collection<int, LearningClass> $learningClasses
 * @property-read Collection<int, LearningClass> $teachingClasses
 * @property-read Collection<int, User> $children
 * @property-read Collection<int, User> $parents
 * @property-read Collection<int, AssessmentAnswer> $gradedAssessmentAnswers
 */
#[Fillable(['name', 'email', 'email_verified_at', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    /**
     * @return BelongsToMany<LearningClass, $this>
     */
    public function learningClasses(): BelongsToMany
    {
        return $this->belongsToMany(LearningClass::class, 'enrollments', 'student_id', 'learning_class_id')
            ->withPivot(['id', 'status', 'enrolled_at', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<LearningClass, $this>
     */
    public function teachingClasses(): BelongsToMany
    {
        return $this->belongsToMany(LearningClass::class, 'learning_class_tutor', 'tutor_id', 'learning_class_id')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student_relationships', 'parent_id', 'student_id')
            ->withPivot(['id', 'relationship_type'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student_relationships', 'student_id', 'parent_id')
            ->withPivot(['id', 'relationship_type'])
            ->withTimestamps();
    }

    /** @return HasMany<AssessmentAnswer, $this> */
    public function gradedAssessmentAnswers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class, 'graded_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
