# ADR 0004: Schema-validated AI output and human approval

## Status
Accepted

## Context
Instructors can generate multiple-choice questions from a lesson's text or transcript with an
LLM. Models can return malformed JSON, wrong shapes, duplicate options, answers that are not
in the text, or plainly wrong questions. Students must never see that output unchecked.

## Decision
- Providers sit behind `LlmProvider::generateJson(system, prompt, schema)`. Ollama (local,
  default, free) receives the schema as `format`; Gemini (used when `GEMINI_API_KEY` is set)
  gets `responseMimeType: application/json` with the schema; a deterministic fake provider
  is used in tests.
- The same rules are enforced again on our side with the Laravel validator: exactly four
  distinct options per question, `correct_index` 0–3, explanation required, video second
  within the lesson length. Markdown fences around the JSON are tolerated.
- Invalid output is retried once, with the validation errors added to the prompt. A second
  failure marks the generation as failed and stores nothing.
- Valid questions are stored as `status = draft`, `source = ai`, linked to the generation
  (`ai_generations` keeps provider, model, raw response, attempts and duration for audit).
  The learning API only serves `approved` questions; an instructor approves or rejects drafts.
- Generation runs on the `ai` queue (tenant restored from the job payload) and is rate
  limited per brand.

## Consequences
- A bad model answer costs at most two calls and never reaches students.
- Switching models is configuration only. In local testing `qwen2.5:7b` on CPU produced
  three valid questions from one lesson on the first attempt in about 40 seconds.
- Approval is a deliberate manual step; it trades speed for content quality.
