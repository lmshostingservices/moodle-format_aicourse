<?php
namespace format_aicourse\output;

defined('MOODLE_INTERNAL') || die();

use core_courseformat\output\section_renderer;

/**
 * Renderer for AI Course Format
 * 
 * Extends the core courseformat section_renderer for full Moodle 4.0+ compatibility.
 * This approach properly uses PHP namespace autoloading instead of require_once.
 *
 * @package    format_aicourse
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends section_renderer {
    // Inherits all rendering from core section_renderer
    // Additional rendering handled by format.php and styles.css
    // Custom output classes can be added in classes/output/courseformat/ if needed
}
