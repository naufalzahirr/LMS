# Learning analytics metric semantics

Learning Analytics & Progress Insights V1 is a current-state view of observable LMS data. It does not calculate risk scores, make predictions, rank Tutors, or reconstruct historical trends from current rows.

## Current-learning scope

Admin and Tutor analytics include only:

- active Programs and Courses;
- active Learning Classes;
- active Enrollments;
- active Competencies;
- active Modules and active, non-authoring-draft Lessons that have not been soft-deleted;
- active class-assessment assignments whose Assessment is published.

Inactive, completed, withdrawn, archived, deleted, or draft objects are excluded from current-learning analytics. Historical records remain available in the established reporting and Parent progress experiences where they are explicitly labelled as history.

Tutor scope is further restricted on the server to Learning Classes assigned to the authenticated Tutor. Student insights query only the authenticated Student's active Enrollments. Parent progress remains child-specific and uses the existing linked-child authorization policy.

## Metric definitions

### Active Students

The number of distinct Student user IDs with at least one active Enrollment in an active Learning Class inside the selected active Program/Course scope. Multiple active Enrollment rows for the same Student count once in this metric.

Class rows count distinct active Students in that class. Student-class detail rows continue to describe a single Enrollment context.

### Lesson completion

At Student/Enrollment level:

`completed accessible Lessons / total accessible Lessons`

An accessible Lesson is active, non-draft, not soft-deleted, and belongs to an active Module, Competency, Course, and Program in the Enrollment's Course. A completion is a `lesson_progress` row in the existing `Completed` state.

Class and Admin/Tutor aggregate rates use:

`completed Student-Lesson cells / eligible Student-Lesson cells`

A percentage is unavailable when the denominator is zero. It is not shown as a failing `0%`.

### Competency mastery

`mastered Student-Competency cells / eligible Student-Competency cells`

The denominator is derived from every active Competency in each active Enrollment's Course through `MasteryProgressQueryService`. Missing `student_competency_progress` rows therefore become the established default `Learning` state and remain in the denominator. A new Student in a Course with four active Competencies is `0 / 4`, never `0 / 0`.

The Student-level summary follows the established Student Dashboard behavior and de-duplicates the same Competency ID when the Student has concurrent active Enrollments that expose that Competency.

Cross-class Competency insight labels its denominator as eligible Student-class contexts because the same Student can legitimately have more than one active class context.

### Students needing remedial

The number of distinct Student user IDs with at least one eligible Student-Competency cell in the existing `NeedsRemedial` state.

### Remedial competency cases

The number of eligible Student-Competency cells in the existing `NeedsRemedial` state. One Student needing remedial in three Competencies is one Student needing remedial and three remedial competency cases.

These current-state metrics intentionally reuse `student_competency_progress`. They do not count completed historical remedial assignments or infer remedial need from scores.

### Assessment participation

`submitted Student-assignment cells / eligible Student-assignment cells`

An eligible cell is one active Enrollment in the Learning Class for one active class-assessment assignment backed by a published Assessment. A Student participates when the cell has at least one attempt in `PendingGrading` or `Graded`. Multiple attempts do not increase either numerator or denominator. Pending grading counts as submitted.

### Assessment performance

The average percentage of the latest valid graded attempt for each Student-assignment cell. Selection is ordered by attempt number and ID, newest first, among attempts with status `Graded` and a non-null percentage.

This canonical-attempt rule is consistent with retries: a Student contributes at most one score per assignment, an older retry never enlarges the sample, and a later pending attempt does not erase the latest comparable graded result. Pending-grading and in-progress attempts are never converted to zero. The interface always shows the graded Student sample size with the average and displays an empty state when the sample is empty.

`Pending grading` counts the Student-assignment cells whose latest submitted attempt is pending review. This can overlap with the performance sample when a Student has a prior graded attempt and a newer pending retry; the labels intentionally describe different observable facts.

## Query architecture

- `LearningProgressQueryService` remains the source of accessible-Lesson denominators and completion state.
- `MasteryProgressQueryService` remains the source of sparse-safe Competency states, prerequisites, mastery rules, and remedial context.
- `AssessmentAnalyticsQueryService` selects one participation/performance record per Student-assignment without loading questions, answers, or answer keys.
- `LearningAnalyticsMetricService` combines those lower-level semantics into class, Student, Competency, remedial, and assessment aggregates.
- `AdminLearningAnalyticsQueryService`, `TutorLearningAnalyticsQueryService`, and `StudentProgressInsightsQueryService` apply role-specific scope and payload shaping. Tutor scoping is applied in the database query before metrics are calculated.

Summary aggregates are separate from paginated Admin class and Tutor Student tables. Related classes, Courses, Programs, Tutors, Students, active Lessons, progress rows, mastery rows, assignments, and attempts are batch-loaded or aggregated per selected scope rather than queried per rendered row.

## Exports and privacy

Admin class analytics and Tutor Student analytics reuse streamed CSV downloads with spreadsheet-formula escaping. Export routes apply the same authorization and filters as their pages.

Analytics payloads contain names, statuses, counts, percentages, and authorized drill-down URLs. They do not load or expose assessment questions, raw Student answers, correct options, accepted short answers, explanations, or other answer-key material.
