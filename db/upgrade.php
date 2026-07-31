<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_format_aicourse_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025122732) {
        // Create chat history table
        $table = new xmldb_table('format_aicourse_chats');
        
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('question', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('response', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('rating', XMLDB_TYPE_INTEGER, '2', null, null, null, '0');
        $table->add_field('correction', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('correctedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecorrected', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        
        $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        $table->add_index('rating_idx', XMLDB_INDEX_NOTUNIQUE, ['rating']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025122732, 'format', 'aicourse');
    }

    // Add activityid, refused, locked columns to chats table (v1.5.70)
    if ($oldversion < 2025122910) {
        $table = new xmldb_table('format_aicourse_chats');
        
        // Add activityid column
        $field = new xmldb_field('activityid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add refused column
        $field = new xmldb_field('refused', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'rating');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add locked column
        $field = new xmldb_field('locked', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'refused');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add index for activityid
        $index = new xmldb_index('activityid_idx', XMLDB_INDEX_NOTUNIQUE, ['activityid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Add index for refused
        $index = new xmldb_index('refused_idx', XMLDB_INDEX_NOTUNIQUE, ['refused']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Add questionslot column for quiz question awareness
        $field = new xmldb_field('questionslot', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'activityid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add index for questionslot
        $index = new xmldb_index('questionslot_idx', XMLDB_INDEX_NOTUNIQUE, ['questionslot']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Create AI memory table
        $memorytable = new xmldb_table('format_aicourse_ai_memory');
        
        $memorytable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $memorytable->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $memorytable->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $memorytable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $memorytable->add_field('memory', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $memorytable->add_field('timeupdated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        
        $memorytable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $memorytable->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $memorytable->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        
        $memorytable->add_index('unique_memory', XMLDB_INDEX_UNIQUE, ['courseid', 'activityid', 'userid']);
        
        if (!$dbman->table_exists($memorytable)) {
            $dbman->create_table($memorytable);
        }
        
        upgrade_plugin_savepoint(true, 2025122910, 'format', 'aicourse');
    }

    // =========================================================================
    // SCHEMA REPAIR SECTION - Always runs to fix missing tables/columns
    // This handles cases where plugin was installed at a higher version
    // or database got corrupted/incomplete
    // =========================================================================
    
    // Ensure chats table exists with all required columns
    if (!$dbman->table_exists('format_aicourse_chats')) {
        $table = new xmldb_table('format_aicourse_chats');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('questionslot', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('question', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('response', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('rating', XMLDB_TYPE_INTEGER, '2', null, null, null, '0');
        $table->add_field('refused', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
        $table->add_field('locked', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
        $table->add_field('correction', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('correctedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecorrected', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        $table->add_index('rating_idx', XMLDB_INDEX_NOTUNIQUE, ['rating']);
        $table->add_index('activityid_idx', XMLDB_INDEX_NOTUNIQUE, ['activityid']);
        $table->add_index('refused_idx', XMLDB_INDEX_NOTUNIQUE, ['refused']);
        $table->add_index('questionslot_idx', XMLDB_INDEX_NOTUNIQUE, ['questionslot']);
        $dbman->create_table($table);
    } else {
        // Table exists - ensure all columns exist
        $table = new xmldb_table('format_aicourse_chats');
        
        $field = new xmldb_field('activityid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('questionslot', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'activityid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('refused', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'rating');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('locked', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'refused');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Ensure indexes exist
        $index = new xmldb_index('activityid_idx', XMLDB_INDEX_NOTUNIQUE, ['activityid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        $index = new xmldb_index('refused_idx', XMLDB_INDEX_NOTUNIQUE, ['refused']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        $index = new xmldb_index('questionslot_idx', XMLDB_INDEX_NOTUNIQUE, ['questionslot']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
    }
    
    // Ensure AI memory table exists
    if (!$dbman->table_exists('format_aicourse_ai_memory')) {
        $memorytable = new xmldb_table('format_aicourse_ai_memory');
        $memorytable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $memorytable->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $memorytable->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $memorytable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $memorytable->add_field('memory', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $memorytable->add_field('timeupdated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $memorytable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $memorytable->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $memorytable->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $memorytable->add_index('unique_memory', XMLDB_INDEX_UNIQUE, ['courseid', 'activityid', 'userid']);
        $dbman->create_table($memorytable);
    }

    // v1.7.0: AI TUTOR DEEP CONTENT EXTRACTION — No DB changes.
    // Rewrote format_aicourse_get_course_content_for_ai() to deeply extract content from
    // all AI plugins (Content Creator manifestjson, Knowledge Check Q+A+explanations,
    // AI Activities JSON, Video Activity transcripts+questions, Essay Maker rubrics).
    // Added getactivitycontext handlers for aiactivities, aivideoactivity, contentcreator.
    // Raised per-activity content limit 2KB→8KB, overall context limit 15KB→50KB.
    if ($oldversion < 202603221700) {
        upgrade_plugin_savepoint(true, 202603221700, 'format', 'aicourse');
    }

    // v1.7.1: Re-release of deep content extraction with build sync fix.
    if ($oldversion < 202603221800) {
        upgrade_plugin_savepoint(true, 202603221800, 'format', 'aicourse');
    }

    // v1.7.2: Fix duplicate section (capability fallback to course_create_section).
    //         Add Section card: dotted rectangle placeholder in edit mode.
    if ($oldversion < 202603231800) {
        upgrade_plugin_savepoint(true, 202603231800, 'format', 'aicourse');
    }

    // v1.7.3: Version bump — maintenance release.
    if ($oldversion < 202603231803) {
        upgrade_plugin_savepoint(true, 202603231803, 'format', 'aicourse');
    }

    // v1.7.4: Optional custom banner image upload in course format settings.
    //   - format_aicourse_pluginfile() now serves files from the 'bannerimage' filearea.
    //   - create_edit_form_elements() adds filemanager (max 5 MB, JPG/PNG/WebP).
    //   - set_edit_form_data() / update_course_format_options() manage draft→permanent.
    //   - format_aicourse_get_banner_image_url() retrieves uploaded image URL.
    //   - Both hero render functions use custom banner first, fall back to course image.
    //   - CSS: .aicourse-hero-bg-img isolated layer with hover scale(1.04) zoom effect.
    //   - CSS: prefers-reduced-motion respected; overlay z-index corrected.
    if ($oldversion < 202603250000) {
        upgrade_plugin_savepoint(true, 202603250000, 'format', 'aicourse');
    }

    // v1.7.6: AI banner image generation via Google Imagen 4 Ultra.
    //   - New wand button in hero icons (editors only) opens cost-confirmation modal.
    //   - Modal calls ajax.php action generate_banner_image → Vault API → Moodle bannerimage filearea.
    //   - Image is 1400px wide 16:9 JPEG, saved to format_aicourse/bannerimage, served by pluginfile.
    //   - Hero background updates in-page immediately on success; page reloads on Done.
    if ($oldversion < 2026032500102) {
        upgrade_plugin_savepoint(true, 2026032500102, 'format', 'aicourse');
    }

    // v1.7.14: BUGFIX — Delete banner button now gated on $PAGE->user_is_editing().
    //   - Previously the trash icon was shown to any user with moodle/course:update capability.
    //   - Now it only appears when Moodle edit mode is active, preventing accidental deletions.
    //   - Fix applied to both format_aicourse_render_hero_banner() and
    //     format_aicourse_render_activity_hero_banner() in lib.php.
    if ($oldversion < 2026032700001) {
        upgrade_plugin_savepoint(true, 2026032700001, 'format', 'aicourse');
    }

    // v1.7.15: UI — Remove folder icon from section hero; reduce title font size.
    //   - Folder SVG no longer rendered on section-view hero banners (book icon kept on course home).
    //   - Title font sizes reduced across all breakpoints (was up to 3.2rem, now max 1.9rem).
    if ($oldversion < 2026032700002) {
        upgrade_plugin_savepoint(true, 2026032700002, 'format', 'aicourse');
    }

    // v1.7.18: UI — Hero banner shows course title only (removed shortname label pill and book icon).
    //   - Shortname pill above title removed from course-home hero in image mode.
    //   - Book SVG icon removed from the title-wrap on course home.
    //   - Only the plain course title text is shown.
    if ($oldversion < 2026032700018) {
        upgrade_plugin_savepoint(true, 2026032700018, 'format', 'aicourse');
    }

    if ($oldversion < 2026033000019) {
        // v1.7.19: VERSION BUMP — Maintenance release. Corrects BUILD_INFO.json which was stale
        // at 1.7.17 despite version.php being at 1.7.18. All 6 sync locations now consistent.
        // No code or DB schema changes.
        upgrade_plugin_savepoint(true, 2026033000019, 'format', 'aicourse');
    }

    if ($oldversion < 2026033000020) {
        // v1.7.20: BANNER OVERLAY CLEANUP — Replaced the messy multi-layer cinematic
        // gradient (linear bottom-dark + radial edge-dark) on the hero banner image
        // with a single clean uniform rgba(0,0,0,0.42) wash. The old gradient created
        // visually distinct patches of different grey intensity — top-transparent,
        // bottom very dark (0.82), plus edge darkening from the radial — making the
        // banner look inconsistent. The new single overlay covers the full image
        // evenly. Also unified the [style*="background-image"] fallback overlay to the
        // same rgba value (was 0.25, now 0.42). CSS-only change — no PHP or DB schema
        // changes. styles.css only. version.php → 2026033000020.
        upgrade_plugin_savepoint(true, 2026033000020, 'format', 'aicourse');
    }

    if ($oldversion < 2026033000021) {
        // v1.7.21: BANNER UI CLEANUP — Removed the frosted-glass rectangle backgrounds
        // from the nav/progress bar and the icons cluster on the hero banner. Previously
        // three separate grey shapes were visible over the banner image (full overlay +
        // nav-progress frosted pill + icons frosted pill). Now only the single uniform
        // rgba(0,0,0,0.42) overlay covers the image — the nav arrows, completion widget,
        // and icon buttons float directly over it with no additional box shapes. CSS-only
        // change — no PHP or DB schema changes. styles.css only. version.php → 2026033000021.
        upgrade_plugin_savepoint(true, 2026033000021, 'format', 'aicourse');
    }

    if ($oldversion < 2026033000022) {
        // v1.7.22: VERSION BUMP — Maintenance release. All 6 sync locations updated to
        // 1.7.22. No code or DB schema changes. version.php → 2026033000022.
        upgrade_plugin_savepoint(true, 2026033000022, 'format', 'aicourse');
    }

    if ($oldversion < 2026033000023) {
        // v1.7.23: BANNER UI FIX — Completed the progress widget rectangle removal. The
        // base .aicourse-hero-progress had background rgba(0,0,0,0.04), backdrop-filter
        // blur(8px), and border that were only partially overridden for the image-banner
        // context (border-color was transparent but backdrop-filter still active). The
        // has-image override now fully clears background, border, backdrop-filter,
        // box-shadow, and padding so no shape remains. CSS-only change — no PHP or DB
        // schema changes. styles.css only. version.php → 2026033000023.
        upgrade_plugin_savepoint(true, 2026033000023, 'format', 'aicourse');
    }

    if ($oldversion < 2026033000024) {
        // v1.7.24: BANNER READABILITY FIX — Three CSS changes. (1) Overlay darkened
        // from rgba(0,0,0,0.42) to rgba(0,0,0,0.65) on both .aicourse-hero-has-image
        // and the [style*="background-image"] fallback — white text now legible on
        // bright image areas. (2) Title element gains overflow:visible, text-overflow:unset,
        // white-space:normal all with !important, fully overriding the base ellipsis/nowrap
        // rule that caused section titles to truncate to "Ad...". Stronger text-shadow
        // 0.80 opacity. (3) Icons SVG gets filter:drop-shadow(0 1px 4px rgba(0,0,0,0.85))
        // so icons remain visible on any image colour. Nav arrows also get drop-shadow.
        // Completion/progress labels get text-shadow 0.75. CSS-only. No DB schema changes.
        // version.php → 2026033000024.
        upgrade_plugin_savepoint(true, 2026033000024, 'format', 'aicourse');
    }

    if ($oldversion < 2026033000025) {
        // v1.7.25: BANNER OVERLAY + Z-INDEX FIX — Two CSS-only changes to styles.css.
        // (1) Overlay darkened from rgba(0,0,0,0.65) to rgba(0,0,0,0.80) on both the
        // .aicourse-hero-has-image selector and the [style*="background-image"] fallback.
        // The previous 0.65 opacity was insufficient on photos with bright mid-tones
        // (e.g. whiteboards, windows). (2) Added isolation:isolate and z-index:1 to
        // .aicourse-hero-banner.aicourse-hero-has-image. Without these, the banner did
        // not create its own stacking context — Moodle edit-mode elements injected inside
        // the banner DOM (course admin toolbars, bulk-action controls) could appear above
        // the overlay (z-index:1) but below the content (z-index:2), creating a visible
        // "ghost panel" effect over the banner image. isolation:isolate forces a new
        // stacking context so all z-index values are resolved relative to the banner,
        // not the page. CSS-only. No PHP, JS, AMD, or DB schema changes.
        // version.php → 2026033000025.
        upgrade_plugin_savepoint(true, 2026033000025, 'format', 'aicourse');
    }

    if ($oldversion < 2026033000026) {
        // v1.7.26: COMPLETE IMAGE BANNER OVERHAUL — CSS-only changes to styles.css.
        // (1) OVERLAY FIX: The has-image overlay was missing position:absolute + inset:0,
        // meaning it never covered the image at all. All prior overlay opacity tweaks were
        // ineffective. Now correctly positioned.
        // (2) GRADIENT OVERLAY: Replaced flat rgba(0,0,0,0.80) with a cinematic gradient
        // (transparent 0%→28%, 0.52 at 62%, 0.84 at 100%). Image is now visible at the
        // top of the banner while remaining readable at the bottom where text lives.
        // (3) BOTTOM ACCENT BAR REMOVED: The ::before primary-colour stripe at the bottom
        // of the banner fought the image composition. Removed (display:none).
        // (4) OVERFLOW HIDDEN: Added overflow:hidden to banner shell so the hover-zoom
        // bg-img transform is clipped correctly by the 12px border-radius.
        // (5) ICON GROUP: Icon buttons now grouped in a glass pill container
        // (rgba(0,0,0,0.30) + blur(10px)) so they are always visible on any image colour.
        // (6) COURSE LABEL: Changed from hardcoded indigo rgba(99,102,241,0.50) to neutral
        // frosted glass rgba(255,255,255,0.18) — works with any image or Moodle theme.
        // (7) NAV ARROWS: Added backdrop-filter:blur(8px) + rgba(0,0,0,0.32) base fill.
        // (8) PROGRESS BAR: Changed hardcoded #6366f1→#8b5cf6 gradient to var(--primary).
        // (9) TITLE: text-shadow layered (3 stops), line-height 1.12→1.15.
        // (10) MIN-HEIGHTS INCREASED: 160/190/230/260px → 220/260/300/340px.
        // CSS-only. No PHP, JS, AMD, or DB schema changes.
        // version.php → 2026033000026.
        upgrade_plugin_savepoint(true, 2026033000026, 'format', 'aicourse');
    }

    if ($oldversion < 2026033000027) {
        // v1.7.27: ACTIVITY VIEW FIXES — CSS + PHP changes only.
        // (1) COMPLETION RING WHITE: CSS base rule was overriding PHP-rendered inline SVG
        //     stroke colours (rgba(255,255,255,0.5)) with rgba(0,0,0,0.15), making the ring
        //     invisible on the dark overlay. Added has-image CSS overrides to restore white.
        // (2) OVERLAY LEFT GRADIENT: Added a second horizontal gradient axis (90deg) so the
        //     left side of the banner (where the title lives) is always darkened enough for
        //     readability, even when the image has no natural dark edge on that side.
        // (3) ICON SIZE: Icon SVGs in image mode increased from 16px → 18px.
        // (4) NUMBERED ACTIVITY CIRCLES: Replaced plain "X of Y activities completed" text
        //     in section view with numbered circle indicators — white = not started,
        //     amber = in progress, green = completed, dimmed = no completion tracking.
        //     Circles are clickable links to each activity. PHP iterates all visible CMs in
        //     the section and renders one circle per activity.
        // (5) PROGRESS RING BG: Background track circle now rgba(255,255,255,0.25) in image
        //     mode so the unfilled portion of the ring is visible.
        // CSS and PHP (lib.php) only. No JS, AMD, or DB schema changes.
        // version.php → 2026033000027.
        upgrade_plugin_savepoint(true, 2026033000027, 'format', 'aicourse');
    }

    if ($oldversion < 2026033000028) {
        // v1.7.28: VISUAL SIZING FIXES FOR IMAGE-MODE BANNER — CSS-only changes to styles.css.
        // All overrides use !important and sit in a dedicated has-image block so they beat
        // every responsive-breakpoint rule lower in the cascade.
        //
        // (1) PROGRESS RING SIZE: .aicourse-progress-ring-container forced to 60×60px in image
        //     mode (was 34–48px scaling across breakpoints — too small against tall imagery).
        // (2) PROGRESS RING TEXT: font-size 8–11px → 13px with text-shadow for legibility.
        // (3) COMPLETION RING SIZE (activity page): 40px → 54px in image mode.
        // (4) COMPLETION LABEL TEXT: 14px → 15px in image mode.
        // (5) ICON BUTTONS (glass pill, top-right): containers 30px → 36px; SVG icons 16px →
        //     22px with !important so the responsive .aicourse-hero-home svg overrides cannot
        //     reduce them back down.
        // CSS-only. No PHP, JS, AMD, or DB schema changes.
        // version.php → 2026033000028.
        upgrade_plugin_savepoint(true, 2026033000028, 'format', 'aicourse');
    }

    if ($oldversion < 2026033000029) {
        // v1.7.29: ACTIVITY BANNER UX FIXES — CSS + PHP (lib.php) changes.
        // Three improvements to the activity hero banner (format_aicourse_render_activity_hero_banner):
        //
        // (1) COMPLETE ACTIVITY TEXT LAYOUT: .aicourse-hero-progress was a horizontal flex row,
        //     so the "Complete activity" label rendered beside the ring. Added
        //     flex-direction:column !important and align-items:center !important to the
        //     has-image image-mode override so the label stacks below the ring.
        //
        // (2) SECTION LABEL VISIBILITY: .aicourse-hero-section-label ("ELEMENT 1 — IDENTIFY…")
        //     was rgba(255,255,255,0.65) with opacity:0.55 from the base rule — far too dim.
        //     Image-mode override updated to #fff !important + opacity:1 !important plus the same
        //     3-layer text-shadow stack used by the title (0 1px 4px / 0 3px 12px / 0 6px 24px)
        //     for maximum legibility against any background image.
        //
        // (3) ICON REMOVED FROM TITLE: lib.php previously emitted an <img> Moodle activity icon
        //     inside .aicourse-hero-title-wrap. The icon cluttered the title line and was removed.
        //     The title <span> is now the only child of that wrapper.
        //
        // CSS (styles.css) + PHP (lib.php). No JS, AMD, or DB schema changes.
        // version.php → 2026033000029.
        upgrade_plugin_savepoint(true, 2026033000029, 'format', 'aicourse');
    }

    if ($oldversion < 2026033000030) {
        // v1.7.30: HERO Z-INDEX FIX — .aicourse-hero-sticky-wrap had z-index:100 on
        //   three selectors (base, body.path-mod, .pagetype-course-section). This
        //   created a stacking context that sat above the Content Creator player's
        //   modal overlays (position:fixed, z-index:9999), causing "Slide Paused"
        //   and all other modals to appear behind the course hero banner. Fix:
        //   z-index lowered from 100 to 1 on all three selectors. Companion fix in
        //   mod_contentcreator v12.17 moves all modal appends to document.body.
        //   CSS only (styles.css). No PHP, JS, AMD, or DB schema changes.
        //   version.php → 2026033000030.
        upgrade_plugin_savepoint(true, 2026033000030, 'format', 'aicourse');
    }

    if ($oldversion < 2026040100031) {
        // v1.7.31: BANNER FIXES — Three improvements to the course hero banner:
        //   1. CSS min-heights reduced by 33%: 220→148px (mobile), 260→175px (sm),
        //      300→200px (md), 340→228px (lg) — resolves banner consuming ~50% of
        //      viewport height on smaller screens.
        //   2. border-radius reduced from 12px to 8px for a less pill-like appearance.
        //   3. Image generation prompt updated: removed misleading "5:1 panoramic"
        //      description (Imagen only supports 16:9); replaced with explicit 16:9
        //      landscape composition guidance with center-vertical subject placement.
        //   4. Server-side banner resize changed from resize(1400, null, inside) to
        //      resize(1400, 420, cover+center) — crops image to banner dimensions
        //      server-side, reducing file size by ~50%.
        //   5. AMD modal description updated: "perfectly cropped" → accurate wording.
        //   CSS (styles.css), AMD (courseformat.js src+build+min), server (routes.ts).
        //   No DB schema changes. version.php → 2026040100031.
        upgrade_plugin_savepoint(true, 2026040100031, 'format', 'aicourse');
    }

    // v1.7.39 — CSS FIX: Activity number circles (CSS only, no DB schema changes):
    //   1. Completed green circles on hover: general :hover rule applied
    //      background:rgba(255,255,255,0.18) to ALL circles including completed ones,
    //      washing out the green fill to near-white. Fixed: added specific
    //      .aicourse-number-completed:hover rule that keeps background:#357a32 and
    //      enforces color:#fff, overriding the general hover wash-out.
    //   2. Darker green outline on completed circles: in non-image mode the
    //      .aicourse-hero-banner:not(.aicourse-hero-has-image) .aicourse-activity-number
    //      rule (border-color:rgba(0,0,0,0.35)) had equal CSS specificity to the
    //      .aicourse-number-completed rule (border-color:#357a32) and appeared later in
    //      the stylesheet, overriding the green border with a dark ring. Fixed: changed
    //      completed circle styling to border:none (flat fill, no border needed) across
    //      all modes, and added explicit border:none overrides in both the base and
    //      non-image-mode completed selectors to eliminate any dark ring artifact.
    //   version.php → 2026041700039.
    if ($oldversion < 2026041700039) {
        upgrade_plugin_savepoint(true, 2026041700039, 'format', 'aicourse');
    }

    // v1.7.40 - Four edit-mode UX bug fixes:
    //   (1) BUG-ACF-DROPDOWN-CLIP: .aicourse-card had overflow:hidden + transform:translateZ(0).
    //       Together these clipped Moodle's native 3-dot action menus and "Add activity" dropdowns
    //       to the card's bounds (overflow:hidden) and created a stacking context that prevented
    //       dropdowns from appearing above other content (transform creates stacking context).
    //       Result: clicking 3-dot menus appeared to do nothing. Fixed by adding
    //       overflow:visible!important + transform:none!important to body.editing/.editingon/
    //       .editing-mode .aicourse-card in styles.css.
    //   (2) BUG-ACF-SCROLL-DELAY: scrollToTop() called on every init() — smooth-scrolled the
    //       page for up to 1 second on every page load. During scroll, click targets moved
    //       under the cursor causing missed clicks. Removed from init().
    //   (3) BUG-ACF-LISTENER-STACK: $(document).on('keydown') and .on('completionchange') had
    //       no jQuery namespace. If AMD module re-initialised (Moodle AJAX navigation), handlers
    //       stacked — multiple AJAX requests per completion event. Namespaced all 4 listeners.
    //   (4) BUG-ACF-KEYNAV-DROPDOWN: Arrow-key nav handler fired inside open dropdowns/dialogs
    //       causing page navigation. Added guard checking for open .dropdown-menu.show, modals,
    //       and [aria-expanded="true"] elements. AMD triple-matched (1d9986def51a19226ad42859e5b36ad4).
    //   No DB schema changes. version.php → 2026041800040.
    if ($oldversion < 2026041800040) {
        upgrade_plugin_savepoint(true, 2026041800040, 'format', 'aicourse');
    }

    // v1.7.41 - Section description display:
    //   (1) BUG-ACF-SECTION-SUMMARY: Section descriptions (summaries) entered by teachers in
    //       Moodle's course settings were never rendered in the AI Course Format student view.
    //       Two locations fixed:
    //       a) Section cards on the course home page (format_aicourse_render_section_cards in
    //          lib.php): summary stripped of HTML, truncated to 130 chars, and shown in an
    //          .aicourse-card-summary <p> below the section title.
    //       b) Section activity page (format.php): full formatted summary (including rich text
    //          and images) output in a .format-aicourse-section-summary block between the hero
    //          banner and the activity cards list. CSS added to styles.css for both locations
    //          with dark-mode support.
    //   No DB schema changes. Files: format.php, lib.php, styles.css. version.php → 2026042000041.
    if ($oldversion < 2026042000041) {
        upgrade_plugin_savepoint(true, 2026042000041, 'format', 'aicourse');
    }
    // v1.7.42: AMD ENCODING FIX: All non-ASCII characters (em dashes, arrows, box-drawing chars, ellipsis, bullets, emoji, accented Latin) scrubbed from all AMD JS files (amd/src, amd/build, amd/build/*.min.js). Root cause of Moodle primary/secondary navigation menus disappearing site-wide: non-ASCII bytes in any installed plugin's AMD file cause a SyntaxError inside RequireJS's first.js bundle, throwing "No define call for core/first" and aborting the entire AMD module chain. No PHP, DB schema, or functional changes in this release.
    if ($oldversion < 2026042200042) {
        upgrade_plugin_savepoint(true, 2026042200042, 'format', 'aicourse');
    }
    // v1.7.48: Three tester-reported bugs fixed (no DB schema changes):
    //   FIX-ACF-NAVCHEVRONS: shownavchevrons setting was stored/displayed in the format
    //     options form but the value was never consulted in render_hero_banner(). The nav
    //     arrow markup was therefore always emitted even when the teacher disabled arrows.
    //     Fixed by gating the get_section_nav_links() call on !empty($options['shownavchevrons']).
    //   FIX-ACF-NAVSKIP: The previous format_aicourse_get_section_nav_links() navigated to
    //     the URL of the first/last *activity* in the adjacent section rather than to the
    //     section page itself. This caused sub-sections (e.g. 5.1, 5.2) to be skipped when
    //     they contained no directly-visible activities, so the arrow jumped 5.1 -> 6.
    //     Rewritten to iterate section_info_all(), build an ordered visible-section list, and
    //     return moodle_url('/course/view.php', ['section'=>N]) for prev/next.
    //   FIX-ACF-SUBSECTION: Moodle 4.4+ mod_subsection creates cm_info objects in the parent
    //     section's cmids list with $cm->url === null. The existing "if (!$cm->url) continue"
    //     guard silently dropped them, making parent sections (3, 5, 8, 10) appear empty in
    //     student view. Fixed by detecting $cm->modname === 'subsection', resolving the child
    //     section via cm_info::get_delegated_section_info(), and rendering a section-nav card.
    //   Files changed: lib.php. version.php -> 2026050700107.
    if ($oldversion < 2026050700107) {
        upgrade_plugin_savepoint(true, 2026050700107, 'format', 'aicourse');
    }

    if ($oldversion < 2026050700108) {
        // UX-CARDS-PREMIUM: CSS-only card redesign — no DB schema changes.
        // Section cards: white bg, layered shadows, translateY spring hover, gradient top accent strip,
        // tinted icon wrap, staggered entry animation. Activity cards: same shadow/lift upgrade,
        // primary-tinted icon bg, status colours via icon bg replacing left border hack.
        upgrade_plugin_savepoint(true, 2026050700108, 'format', 'aicourse');
    }

    // v1.7.52: FIX-CURL-BATCH — ajax.php switched from raw curl_init() to Moodle \curl
    //   wrapper. No DB schema changes.
    if ($oldversion < 2026051200111) {
        upgrade_plugin_savepoint(true, 2026051200111, 'format', 'aicourse');
    }

    // v1.7.67: SAVEPOINT-BUMP — no-op marker for clean upgrade path. No DB schema changes.
    if ($oldversion < 2026060400015) {
        upgrade_plugin_savepoint(true, 2026060400015, 'format', 'aicourse');
    }

    // v1.7.68: FIX-ACF-EDITOR-HERO — Hero banner now renders for editing teachers and
    //   course creators (moodle/course:update) so they can access the AI Generate Banner
    //   button and progress bar. Previously all graders were skipped. No DB schema changes.
    if ($oldversion < 2026060700068) {
        upgrade_plugin_savepoint(true, 2026060700068, 'format', 'aicourse');
    }

    if ($oldversion < 2026060700069) {
        // FIX-BANNER-FALLBACK (v1.7.69): generate-banner route now routes through generateImage()
        // which has Imagen 4 Ultra -> OpenAI gpt-image-1 fallback. No DB changes.
        upgrade_plugin_savepoint(true, 2026060700069, 'format', 'aicourse');
    }

    if ($oldversion < 2026072300204) {
        // FIX-API-DOMAIN: Updated all API endpoint URLs from lms-labs.com to lms-labs.com.
        // lms-labs.com has no DNS resolution from Moodle server side; lms-labs.com is the
        // correct working domain. All ajax.php, api_client, unlock_verifier, lib.php calls updated.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026072300204, 'format', 'aicourse');
    }

    if ($oldversion < 2026072300205) {
        // FIX-API-DOMAIN: Reverted API endpoint to lms-labs.com (correct domain).
        // essaygraderai.app was the original single-plugin domain; lms-labs.com is correct.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_plugin_savepoint(true, 2026072300205, 'format', 'aicourse');
    }

    if ($oldversion < 2026072300206) {
        // Domain update: lms-labs.com → lms-labs.com
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'lib.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_plugin_savepoint(true, 2026072300206, 'format', 'aicourse');
    }

    return true;
}