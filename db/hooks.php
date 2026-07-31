<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_standard_footer_html_generation::class,
        'callback' => [\format_aicourse\hook\before_footer_html_generation::class, 'callback'],
        'priority' => 500,
    ],
];
