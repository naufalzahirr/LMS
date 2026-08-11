# Rich lesson content

Lessons are multimedia learning documents. One Lesson owns one ordered Tiptap JSON document in `lessons.content_document`; paragraphs, media, code, callouts, tables, and resources are blocks in that document rather than separate Lesson records.

## Canonical document

Structured Tiptap JSON is canonical because it can be validated by node and attribute, rendered without user-authored HTML, migrated safely, and extended without redesigning Lesson storage. The application never stores the primary rich lesson as rendered HTML and never renders lesson JSON with `v-html`.

Supported content is deliberately limited to:

- document, paragraph, text, hard break, and heading levels 1–3;
- bold, italic, and safe HTTP/HTTPS link marks;
- bullet and numbered lists, block quotes, horizontal dividers, and tables;
- syntax-highlighted code blocks using `plain`, `html`, `css`, `javascript`, `typescript`, `php`, `sql`, `python`, `cpp`, `java`, `json`, or `bash`;
- `lessonImage`, `externalVideo`, `callout`, and `lessonFile` custom nodes.

`LessonContentService` rejects unknown nodes, marks, and attributes. Asset ownership/type, supported video providers, link schemes, code languages, and callout types are validated again on the server. Text extraction for future indexing uses the same known-node tree and does not interpret markup.

## Private assets

`LessonAsset` records belong to one Lesson and have type `image` or `document`. Images accept JPEG, PNG, or WebP up to 10 MB and require alt text unless the content node is explicitly decorative. Documents accept PDF up to 20 MB. Files use Laravel's private `local` disk; content nodes store only `lessonAssetId`, never a browser-supplied path.

Admin and Tutor upload/read routes reuse the Lesson policy. Tutor access remains limited to active assigned courses. Student asset routes repeat the same enrollment, active hierarchy, and competency-lock checks as the Lesson player. Parent access is denied. Responses disable caching and MIME sniffing and apply a sandbox content security policy.

An asset referenced by `content_document` cannot be deleted. Removing a node does not immediately delete its file, so Undo and a subsequent save remain safe. The hourly `lesson-authoring:cleanup` command removes an unreferenced asset only after both the asset and its lesson have been unchanged for the configured 24-hour grace period. Deleting a lesson removes its private rich assets and managed directories consistently.

## Authoring draft lifecycle

The Create Lesson page begins without a database row. After the author selects a module, `LessonDraftController` creates one private Lesson with `status=inactive`, `is_authoring_draft=true`, its `draft_owner_id`, and a 24-hour expiration. This provides a normal Lesson ID for private image/PDF routes without a save-and-reopen step. Changing the selected module moves that same history-free draft after the target module is authorized; it does not create a chain of drafts.

Admins retain their normal lesson permissions. A Tutor must be assigned to the target course and may access only their own authoring draft. Drafts are omitted from the normal author list and all student hierarchy queries. Student lesson, asset, and preview access is rejected even if an ID is guessed.

Submitting Create validates the complete content document against assets owned by that draft, updates that same Lesson row, applies the selected active/inactive status, and clears all draft metadata atomically. Cancel performs a best-effort immediate discard. Browser closure or network loss is handled by the scheduled cleanup: expired drafts are force-deleted together with their private files. Successful draft uploads extend the expiration.

Preview posts the current unsaved JSON to the author-only preview endpoint. The server validates and adds route-derived asset/trusted embed URLs without saving, then the UI renders the result through the same `LessonContentRenderer` used by students. Preview has no enrollment, progress, mastery, or assessment side effects.

## External video and code

Video remains external. `LessonVideoEmbedService` accepts HTTP/HTTPS YouTube and Vimeo forms, extracts the provider identifier, and produces a trusted YouTube No-Cookie or Vimeo embed URL at presentation time. Arbitrary iframe HTML and video uploads are not accepted.

Code is stored and rendered as text. Lowlight highlights only the allowed language set. The player provides a copy button but never executes, previews, compiles, or sends code to a runtime.

## Legacy migration

The following Lesson columns remain for backward compatibility but are deprecated for new authoring:

- `lesson_type`
- `content`
- `external_url`
- `file_path`

`LessonContentMigrationService` converts rows whose `content_document` is null. Text becomes paragraphs; supported videos become video nodes; links become safe link marks; managed images/PDFs become LessonAsset nodes pointing at the existing managed path. Notes are retained as paragraphs. Unsupported but safe legacy video URLs become normal links.

Conversion is idempotent: a populated document is never replaced, legacy asset creation uses a unique Lesson/path pair, and files are neither copied nor moved. LessonProgress, mastery, assessment, and remedial relationships are untouched. Reads prefer `content_document`; the legacy request contract remains temporarily available for compatibility.

## Product boundaries

Assessment remains a separate measurement domain and is not a lesson node. AssessmentAttempt and mastery decisions remain authoritative and are unchanged by lesson formatting.

A future `interactivePractice` node can be added to the validator, editor schema, and controlled renderer without changing `content_document`. Candidate non-mastery practice types are fill-in-the-blank, multiple choice, multiple select, and true/false. None are implemented in this sprint.
