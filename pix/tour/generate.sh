#!/usr/bin/env bash
#
# Generate the tour narration as Google Chirp 3 HD audio.
#
# Produces one MP3 per tour step, named after the step key, in this directory. The tour plays
# these files when they are present and falls back to the browser's own speech synthesis for any
# step whose file is missing -- so a partial set is fine, and you can regenerate a single step
# after editing its wording.
#
# Total narration is about 2,000 characters, which is a fraction of a cent at current
# Text-to-Speech pricing and is charged once rather than on every page view. That is the reason
# these are files rather than a live API call: the words never change.
#
# Requirements
#   gcloud CLI, authenticated:        gcloud auth application-default login
#   A GCP project with billing, and:  gcloud services enable texttospeech.googleapis.com
#
# Usage
#   ./generate.sh                     # en-AU-Chirp3-HD-Charon, the shipped default
#   VOICE=en-AU-Chirp3-HD-Aoede ./generate.sh
#   ./generate.sh t_welcome s_done    # regenerate specific steps only

set -euo pipefail

VOICE="${VOICE:-en-AU-Chirp3-HD-Charon}"
LANG_CODE="${LANG_CODE:-en-AU}"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE="$HERE/narration.tsv"

if [ ! -f "$SOURCE" ]; then
    echo "narration.tsv not found next to this script." >&2
    exit 1
fi

if ! command -v gcloud >/dev/null 2>&1; then
    echo "gcloud is not installed. See https://cloud.google.com/sdk/docs/install" >&2
    exit 1
fi

TOKEN="$(gcloud auth application-default print-access-token)"
PROJECT="$(gcloud config get-value project 2>/dev/null)"

if [ -z "$TOKEN" ] || [ -z "$PROJECT" ]; then
    echo "Not authenticated, or no project set. Run:" >&2
    echo "  gcloud auth application-default login" >&2
    echo "  gcloud config set project YOUR_PROJECT_ID" >&2
    exit 1
fi

echo "Voice:   $VOICE"
echo "Project: $PROJECT"
echo

WANTED=("$@")

made=0
skipped=0

while IFS=$'\t' read -r KEY TEXT; do
    [ -z "${KEY:-}" ] && continue

    # When step keys are given on the command line, generate only those.
    if [ ${#WANTED[@]} -gt 0 ]; then
        found=0
        for w in "${WANTED[@]}"; do
            [ "$w" = "$KEY" ] && found=1
        done
        if [ $found -eq 0 ]; then
            skipped=$((skipped + 1))
            continue
        fi
    fi

    # jq is not assumed: python3 does the JSON escaping, which has to be right because the
    # narration contains apostrophes, commas and em dashes.
    PAYLOAD="$(TEXT="$TEXT" VOICE="$VOICE" LANG_CODE="$LANG_CODE" python3 - <<'PY'
import json, os
print(json.dumps({
    "input": {"text": os.environ["TEXT"]},
    "voice": {
        "languageCode": os.environ["LANG_CODE"],
        "name": os.environ["VOICE"],
    },
    "audioConfig": {
        "audioEncoding": "MP3",
        # Slightly under natural pace: this is narration over a UI the listener is also reading.
        "speakingRate": 0.96,
    },
}))
PY
)"

    printf '  %-16s ' "$KEY"

    RESPONSE="$(curl -sS -X POST \
        -H "Authorization: Bearer $TOKEN" \
        -H "x-goog-user-project: $PROJECT" \
        -H "Content-Type: application/json; charset=utf-8" \
        --data "$PAYLOAD" \
        "https://texttospeech.googleapis.com/v1/text:synthesize")"

    if ! echo "$RESPONSE" | python3 -c 'import json,sys; sys.exit(0 if "audioContent" in json.load(sys.stdin) else 1)' 2>/dev/null; then
        echo "FAILED"
        echo "$RESPONSE" | head -c 400 >&2
        echo >&2
        exit 1
    fi

    echo "$RESPONSE" \
        | python3 -c 'import json,sys,base64; sys.stdout.buffer.write(base64.b64decode(json.load(sys.stdin)["audioContent"]))' \
        > "$HERE/$KEY.mp3"

    SIZE="$(wc -c < "$HERE/$KEY.mp3")"
    echo "${SIZE} bytes"
    made=$((made + 1))
done < "$SOURCE"

echo
echo "Generated $made file(s), skipped $skipped."
echo "Copy this directory into the installed plugin at course/format/aicourse/pix/tour/,"
echo "then purge Moodle's caches."
