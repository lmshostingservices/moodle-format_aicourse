@format @format_aicourse
Feature: Access to the AI Tutor reports
  In order to review how learners are using the AI Tutor
  As a teacher or an administrator
  I need to reach the AI Tutor reports for my courses

  # Scope note. Only the ALLOWED paths are asserted here. Moodle's Behat treats a thrown
  # required_capability_exception as a test failure rather than a page to assert against, so
  # DENIED paths are covered in PHPUnit instead, where the exception can be asserted directly:
  #   tests/report/report_test.php          - a student is refused the course report
  #   tests/external/*_test.php             - every external function has a capability-failure test
  # That split matches how Moodle core tests access control.
  #
  # No @javascript tag: these pages render server side, so this suite runs with no browser and
  # no webdriver.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format   | numsections |
      | Course 1 | C1        | aicourse | 2           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: A teacher can open the course AI Tutor report
    Given I log in as "teacher1"
    When I am on the "C1" "format_aicourse > course report" page
    Then I should see "AI Tutor"

  Scenario: An administrator can open the site wide AI Tutor report
    Given I log in as "admin"
    When I am on the "format_aicourse > admin report" page
    Then I should see "AI Tutor"

  Scenario: An administrator can open the AI Tutor plugin index page
    # index.php is gated on moodle/site:config, so it is a site administration page rather than
    # a course page. Asserting that here documents the intended scope.
    #
    # Assert on real page content. Under the non-JavaScript driver Behat's page text includes
    # the contents of <script> elements, so a string that also appears in Moodle's own JS
    # language bundle -- "Debug info" among them -- will match even when nothing is displayed.
    Given I log in as "admin"
    When I am on the "format_aicourse > index" page
    Then I should see "Course 1"
