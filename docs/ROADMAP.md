# Post-V1 roadmap

## Shipped

- Rich Multimedia Lesson V1: rich lesson authoring and delivery (text, images, files, embedded video, callouts, tables, code blocks) with draft-backed editing independent from published content.
- Interactive Learning Checkpoints V1: implemented with four formative inline question types, server-side answer evaluation, attempt history, persistent mastery state, and draft-backed authoring independent from formal Assessments.
- Role-Based Dashboard & Action Center V1: role-specific "what should I do next" dashboards for Admin, Tutor, and Student, built by aggregating existing learning-class, progress, mastery, remedial, and assessment domains (no new business logic or grading formulas). Parent's existing dashboard is unchanged.
- Notifications & Learning Reminders V1: in-app-only notification bell, dropdown panel, and full Notification Center, using Laravel's database notification channel. Event-driven generation (no polling) for a submission needing grading, an assessment being fully graded, a student newly entering remedial, and an assessment assignment becoming available — each scoped to the exact existing authorization rules for that recipient (Student/Tutor/Parent), with built-in deduplication so repeated evaluation of unchanged state never re-notifies. A scheduled command sends a one-time reminder ~24 hours before an assessment's deadline, scoped to assignments that actually have a `closes_at` set (most do not, since it's an optional field today) and to students for whom the assessment is still genuinely actionable. Email/push delivery, per-user preferences, and Tutor-facing deadline reminders are intentionally deferred — see below.

## Candidate future work

These are candidate ideas for V1.1 or V2. They are not commitments and none are implemented in V1.

- Certificates with verifiable completion records
- Email and push delivery for notifications, plus per-user notification preferences (mute, quiet hours, digest frequency)
- Tutor-facing assessment deadline reminders (Student-facing reminders shipped in V1; Tutor reminders were deferred as lower priority)
- AI tutor assistance and AI-supported remediation recommendations
- Richer cohort and longitudinal analytics
- A versioned mobile API
- Expanded audit-event retention and an administrator-facing audit explorer
- Formal assessment versioning and change comparison
- Dedicated object storage and malware scanning for private lesson uploads
- Organization/tenant isolation if the platform expands beyond one learning center
- Background exports for very large reports
- SSO and institution-managed identity lifecycle
- Deeper observability dashboards, tracing, and service-level objectives
- Localization and broader accessibility testing with assistive-technology users
- Disaster-recovery automation across regions
- Optional future checkpoint extensions such as richer feedback or aggregate teacher analytics, subject to separate product approval

Before planning or implementation, each candidate requires product approval, threat modeling, authorization tests, data-migration planning, operational documentation, and MySQL integration coverage.
