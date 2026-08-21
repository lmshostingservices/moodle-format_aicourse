# Tour narration audio

The first-run tour reads each step aloud. It plays an MP3 from this directory when one exists for
that step, and falls back to the browser's own speech synthesis when one does not — so a partial
set is fine, and you can regenerate a single step after editing its wording.

## Generating them

    gcloud auth application-default login
    gcloud config set project YOUR_PROJECT_ID
    gcloud services enable texttospeech.googleapis.com

    ./generate.sh

That produces all thirteen files using `en-AU-Chirp3-HD-Charon`. To use a different voice:

    VOICE=en-AU-Chirp3-HD-Aoede ./generate.sh

To regenerate only some steps after changing their wording:

    ./generate.sh t_welcome s_done

Copy the resulting MP3s into the installed plugin at `course/format/aicourse/pix/tour/` and purge
Moodle's caches.

## The files

Thirteen steps, eight for the teacher tour and five for the learner tour:

    t_welcome  t_banner  t_generate  t_cards  t_tutor  t_studentview  t_settings  t_done
    s_welcome  s_progress  s_cards  s_tutor  s_done

The keys are role-prefixed deliberately. Both tours have a welcome and a done step, but their
narration differs; without the prefix a learner would have heard the teacher's script.

## Why files rather than a live API call

The narration never changes, so the speech only needs producing once. A live call would mean a
charge on every page view for identical text, latency before each step could speak, and a
dependency on a third service being reachable from the browser.

The whole script is about 2,000 characters — a fraction of a cent at current Text-to-Speech
pricing, paid once.

`narration.tsv` holds the exact text, extracted from the language strings, one `key<TAB>text` pair
per line. If you translate the tour, regenerate this file from the translated strings before
generating audio.
