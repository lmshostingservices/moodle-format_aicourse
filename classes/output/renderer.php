<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace format_aicourse\output;

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
    // Inherits all rendering from core section_renderer.
    // Additional rendering handled by format.php and styles.css.
    // Custom output classes can be added in classes/output/courseformat/ if needed.
}
