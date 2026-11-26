# AI Course Format and the EU AI Act

Version 3.4.0, 26 November 2026. Written by LMS Hosting Services (LMS Labs), the provider of the
AI Course Format plugin. This is the provider's technical analysis to help deployers (the RTOs,
schools and businesses that run the plugin). It is not legal advice.

## 1. What the plugin's AI does

| Use | What happens | Recorded result? |
|---|---|---|
| AI Tutor (chat panel) | Explains course content, asks check-for-understanding questions, offers practice questions in the chat. Locks itself during graded quiz attempts and after an assignment is submitted. | No. Nothing it says changes a grade, completion, certificate or record. |
| AI banner and card images | Makes a decorative image for the course, a section or an activity, from the names and summaries a teacher wrote. | No. |

What is sent (checked against the code in `classes/external/ai_chat.php`, `classes/local/byok/engine.php`
and `classes/external/generate_card_image.php`): the question, the course name and section and
activity names, the text content of the course, the tutor's short topic memory and recent history,
and, on LMS Labs credits only, the user ID and (for adult learners only) the first name. Images
get course, section and activity names, summaries and the teacher's own words. On Bring your own AI
no user ID or name is sent at all.

## 2. Classification

| Use | Classification | Reason |
|---|---|---|
| AI Tutor | Not high-risk. Article 50(1) transparency applies. | Annex III point 3(b) covers AI that **evaluates learning outcomes**. The tutor gives formative help only; no output is recorded or counts toward a result, and a teacher can correct any answer. It does not decide access (3(a)), assess the level of education (3(c)) or monitor tests (3(d)); in fact it switches off during graded attempts. |
| Practice questions in the chat | Not high-risk. | Self-check only. The score is shown to the learner and never stored as a grade. |
| Wellbeing marker | Not a prohibited emotion-recognition system. | Article 5(1)(f) and Article 3(39) concern inferring emotions from **biometric** data. The tutor reads only the words a learner types, to point them to support contacts. It does not use faces, voice or other biometric data. |
| AI images | Generative AI output. Article 50(2) marking applies. | Synthetic images. They are not deepfakes of real people (Article 50(4)), as prompts describe scenes, not identifiable persons. |

If a deployer changes how the plugin is used (for example, copying tutor answers into a grade), the
classification can change. Do not use the AI Tutor to mark, grade or decide anything about a learner.

## 3. Dates (checked 26 November 2026)

| Duty | Applies from |
|---|---|
| Prohibited practices, AI literacy (Article 4) | 2 February 2025 |
| General-purpose AI model duties | 2 August 2025 |
| Article 50 transparency (telling people they are using AI) | 2 August 2026 |
| Article 50(2) machine-readable marking, systems on the market before 2 August 2026 | **2 December 2026** |
| Annex III high-risk duties (not applicable to this plugin as used) | 2 December 2027 (Digital Omnibus, Regulation (EU) 2026/1744) |

## 4. Who does what

**LMS Hosting Services (provider)**
- Discloses AI interaction in the tutor by design (Article 50(1)).
- Marks every AI image in a machine-readable way (Article 50(2)).
- Supplies AI-literacy information to learners and administrators (supports Article 4).
- Supplies these instructions, the privacy metadata and a way to pause AI.
- Relies on its model suppliers (Google, OpenAI, and on Bring your own AI the site's chosen vendor)
  for their general-purpose AI model duties (Articles 53 and 55).

**The deployer (your organisation)**
- Keep the learner AI notice on (Site administration > Plugins > Course formats > AI Course Format >
  Responsible AI). It is on by default.
- Give staff who look after the tutor basic AI literacy (Article 4). The "About this AI" panel and
  this document help.
- Watch the AI Tutor Q&A report, correct wrong answers, and use "Pause all AI" if something goes wrong.
- Tell learners in your own policies how AI is used in your courses.
- If you are a public body, consider your wider duties (for example a fundamental rights impact
  assessment if you ever use AI for a high-risk purpose; the tutor is not one).

## 5. What the plugin does (feature matrix)

| Duty | Article | Feature in 3.4.0 |
|---|---|---|
| People know they are talking to AI | 50(1) | "AI" badge on the tutor's title, always shown; learner notice under the message box ("You're chatting with an AI tutor. It can make mistakes..."), on by default. |
| Synthetic content is marked, machine-readably | 50(2) | Every AI banner and card image is saved with an XMP packet carrying the IPTC Digital Source Type `trainedAlgorithmicMedia`, the generator and the date (JPEG APP1 / PNG iTXt). AI answers in the chat carry `data-ai-generated="true"` and the `acf-ai-output` class. Teachers see "Marked as AI-generated" before generating. |
| AI literacy | 4 | "About this AI" panel in the tutor: what it is, its limits, bias and second-language learners, what data it reads, how to report a problem. Settings text explains each control. |
| Human oversight and correction | good practice (14 for high-risk) | Teachers can rate and correct any tutor answer; corrections feed back into later answers. The tutor never writes to the gradebook. |
| Monitor and suspend | supports 26(5) | "Pause all AI" (site setting): stops every AI call at once, hides the tutor, blocks image generation; courses, images and history keep working. Editors see an MDS warning alert while paused. |
| Logs | supports 12 and 26(6) | Every tutor question and answer is logged with time, user, activity, whether it refused, any correction and (3.4.0) where it was generated: `credits`, `moodle` or `ownkey`. Kept until deleted through the privacy API. |
| Academic integrity | good practice | Locks during graded quiz attempts, after submission and on activities tagged `ai-tutor-off`. |
| Privacy | GDPR | Moodle privacy API covers every table and both external destinations (LMS Labs, and the site's own AI on Bring your own AI). Children's first names are never sent. Noto Sans is served by the site, so the default font sends nothing to Google. |

## 6. Instructions for use

- **Intended purpose:** formative help for learners and teachers inside a course: explaining,
  questioning, practising. Decorative course imagery.
- **Not intended for:** grading, ranking, admission, deciding progression, proctoring, or any
  decision about a person. Do not paste tutor output into results.
- **Known limits:** the tutor can be wrong or out of date; it only knows the course content it is
  given; quality can be lower for learners writing in a second language or with very short questions.
- **Human oversight:** review the AI Tutor Q&A report regularly; correct answers; rate answers.
- **How to suspend:** turn on "Pause all AI". Turn it off to resume.
- **Logs:** the chat log stays until removed through Moodle's privacy tools; keep it at least six
  months if your organisation is a deployer under Article 26(6) for any purpose.
- **Telling learners:** keep the learner AI notice on; mention the AI Tutor in your course
  information.

## 7. Still to do (provider)

- Sign or align with the Commission's Code of Practice on Transparency of AI-Generated Content and
  watch for any EU icon requirements for visible labels.
- Consider adding C2PA content credentials alongside the IPTC/XMP marking once a PHP signer is
  practical.
- Re-check classification whenever a feature could record or influence a learner's result.

## Sources

- Regulation (EU) 2024/1689 (AI Act), EUR-Lex.
- Regulation (EU) 2026/1744 (Digital Omnibus on AI), in force 27 July 2026.
- European Commission, Guidelines on the transparency obligations under Article 50 (20 July 2026).
- European Commission, Code of Practice on Transparency of AI-Generated Content (10 June 2026).
- IPTC Digital Source Type vocabulary (`trainedAlgorithmicMedia`).
