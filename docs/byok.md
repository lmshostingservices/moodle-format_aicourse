# Bring your own AI (AI Course Format 3.4)

Where the AI Tutor and the image generator run is chosen once, for every LMS Labs plugin, in
**AI Grader Central Config** (`local_aiconfig` 1.1 or later) on its "Bring your own AI" page.
AI Course Format shows the result under Site administration > Plugins > Course formats >
AI Course Format > Bring your own AI.

| Service | LMS Labs credits (default) | Moodle AI | Your own key |
|---|---|---|---|
| AI Tutor (text) | lms-labs.com | Moodle 4.5+ AI providers | Anthropic, OpenAI or Gemini |
| Banner and card images | lms-labs.com | Moodle AI image providers | OpenAI |

- Needs a Bring your own AI licence (yearly Stripe subscription) that covers `format_aicourse`
  (the "All LMS Labs plugins" plan). Without one, or with any key missing, the plugin keeps using
  credits. It never falls back to credits on its own after a failed own-AI call.
- On Moodle AI or your own key, calls go straight from your Moodle site to the provider. Nothing is
  sent to lms-labs.com and no LMS Labs credits are used. No user ID or name is sent.

## Same requests as the LMS Labs service

`classes/local/byok/engine.php` (added in 3.4.0) runs the service's tutor and image routes inside
Moodle, and `classes/byok.php` points it at AI Grader Central Config's `\local_aiconfig\byok\ai`:

- The tutor builds the service's prompt (`/api/moodle/course-assistant/chat` in lms-labs
  `server/routes.ts`) with the same branches (assignment-safe, safety-critical, practice, low
  confidence) and the same "Student says:" ending, as one user turn at temperature 0.7.
- Images use the service's scene writer, prompts and validation (`server/lib/aiCourseVisualConcept.ts`),
  are requested at 1536x1024 (high quality) and cropped to the service's 1400x420 banner and
  1536x864 card.
- The answer comes back in the service's own response shape, so everything after it (refusal
  markers, the tutor log, image checks, storage) is the same code as on credits.

`tests/local/byok_parity_test.php` checks the engine against output of the service's own code:
30 tutor prompts and 60 scene compositions, byte for byte. `tests/external/byok_test.php` runs the
tutor and image routes through the engine with a stubbed provider.

Each tutor answer records where it was made (`aisource`: credits, moodle or ownkey), and every AI
image is marked as AI-generated in its own file metadata, whichever AI made it.
