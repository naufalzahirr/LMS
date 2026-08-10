# Security and access matrix

All application routes except the welcome and authentication flows require authentication; learning routes also require verified email. Authorization is enforced in policies/controllers, not only by navigation visibility.

| Area | Admin | Tutor | Student | Parent |
| --- | --- | --- | --- | --- |
| Users and parent links | Required management permission | Denied | Denied | Denied |
| Academic structure | Matching management permission | Active assigned course only | Denied | Denied |
| Questions and assessments | Assessment management permission | Active assigned course only | Assigned assessment payload only | Denied |
| Classes/enrollment/tutor assignment | Matching management permission | Assigned classes, read/report access | Active or completed own enrollment | Denied |
| Attempt grading and review | Assessment management permission | Assigned class plus progress permission | Own attempt only | Denied |
| Lesson files | Matching lesson permission | Active assigned course only | Own eligible enrollment and active lesson | Denied |
| Remedial plans | Class and assessment management permissions | Assigned class plus progress permission | Own plan only | Denied |
| Progress reports | All-progress permission | Assigned class plus progress permission | Own dashboard/classes | Linked children only |

Private lesson paths and answer-key fields are hidden from generic model serialization. Student assessment responses are built from explicit safe payloads. Login, password reset, and assessment mutation endpoints are rate limited. The security regression suite covers cross-role and cross-record access, answer-key leakage, private-file authorization, historical-record protection, and rate-limit behavior.

Report suspected vulnerabilities privately to the application owner. Do not include student records, credentials, answer keys, or private files in public issue trackers.
