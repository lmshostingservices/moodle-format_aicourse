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

namespace format_aicourse\local\byok;

/**
 * An image failure with the status and code the service answers with (CourseImageError).
 *
 * @package    format_aicourse
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_image_error extends \Exception {
    /** @var int HTTP status. */
    public $status;
    /** @var string Error code. */
    public $errorcode;

    /**
     * Constructor.
     *
     * @param int $status
     * @param string $code
     */
    public function __construct(int $status, string $code) {
        parent::__construct($code);
        $this->status = $status;
        $this->errorcode = $code;
    }
}
