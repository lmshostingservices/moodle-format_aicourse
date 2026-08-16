@format @format_aicourse
Feature: Course home page rendering in the AI Course Format
  In order to find my way around a course
  As a user of a course in AI Course Format
  I need the General section and the section cards to render correctly

  # None of these scenarios are tagged @javascript. The format renders server side, so the whole
  # suite runs without a browser or a webdriver, which means it also runs in a plain CI container.
  # Only tag a scenario @javascript if it genuinely needs a live DOM.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format   | numsections | initsections |
      | Course 1 | C1        | aicourse | 3           | 1            |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name                 | intro                      | course | idnumber | section |
      | label    | General notice label | Read this before you start | C1     | label0   | 0       |
      | page     | Welcome page         | Welcome to the course      | C1     | page0    | 0       |
      | assign   | Week one assignment  | Submit your work           | C1     | assign1  | 1       |
      | quiz     | Week two quiz        | Check your knowledge       | C1     | quiz2    | 2       |

  # A label displays its intro, never its name, so every assertion below is on the intro text.
  # Asserting on the name would pass even when the label is not rendered on the course page at
  # all, because the name still appears in the course index.

  Scenario: The General section and its label render on the course home page
    # THE headline regression. The General section's content probe required $cm->url, which is
    # NULL for mod_label, so a General section holding a welcome label was dropped entirely --
    # wrapper, summary and all -- and the page looked empty until you clicked into a section.
    Given I am on the "Course 1" course page logged in as student1
    Then I should see "Read this before you start"
    And I should see "Welcome page"

  Scenario: The General section renders for a teacher who is not editing
    Given I am on the "Course 1" course page logged in as teacher1
    Then I should see "Read this before you start"
    And I should see "Welcome page"

  Scenario: The General section renders in edit mode
    # The edit mode path hands section 0 to core's own section renderer so teachers keep drag
    # handles and "Add an activity or resource". A stylesheet rule that matched ".course-section"
    # too broadly once blanked that content while leaving it in the DOM, so assert on visible text.
    Given I am on the "Course 1" course page logged in as teacher1
    And I turn editing mode on
    Then I should see "Read this before you start"
    And I should see "Welcome page"

  Scenario: The General section still renders when it holds only labels
    Given the following "activities" exist:
      | activity | name         | intro      | course | idnumber | section |
      | label    | Lonely label | On its own | C1     | label9   | 0       |
    And I am on the "Course 1" course page logged in as student1
    Then I should see "On its own"
    And I should see "Read this before you start"

  Scenario: Section cards appear for every numbered section
    Given I am on the "Course 1" course page logged in as student1
    Then I should see "Section 1"
    And I should see "Section 2"
    And I should see "Section 3"

  Scenario: A student never sees the section card edit controls
    Given I am on the "Course 1" course page logged in as student1
    Then "//*[contains(@class, 'aicourse-card-edit-buttons')]" "xpath_element" should not exist
    And "//*[contains(@class, 'aicourse-card-delete')]" "xpath_element" should not exist
    And "//*[contains(@class, 'aicourse-card-duplicate')]" "xpath_element" should not exist
    And "//*[contains(@class, 'aicourse-card-icon-editable')]" "xpath_element" should not exist
    And "//*[contains(@class, 'aicourse-card-drag-handle')]" "xpath_element" should not exist

  Scenario: A teacher sees the section card edit controls only in edit mode
    Given I am on the "Course 1" course page logged in as teacher1
    Then "//*[contains(@class, 'aicourse-card-edit-buttons')]" "xpath_element" should not exist
    And I turn editing mode on
    Then "//*[contains(@class, 'aicourse-card-edit-buttons')]" "xpath_element" should exist
    And "//*[contains(@class, 'aicourse-card-drag-handle')]" "xpath_element" should exist

  Scenario: Section cards do not list their activities by default
    # "Show activities on cards" is off by default, so the only place "Week one assignment" can
    # appear on the course home page is the course index drawer -- never inside a card.
    Given I am on the "Course 1" course page logged in as student1
    Then "//*[contains(@class, 'aicourse-card-activities')]" "xpath_element" should not exist
    And "//*[contains(@class, 'aicourse-cards-grid')]//*[contains(text(), 'Week one assignment')]" "xpath_element" should not exist

  Scenario: Turning on "Show activities on cards" lists each section's activities on its card
    Given I am on the "Course 1" course page logged in as teacher1
    And I navigate to "Settings" in current page administration
    And I set the following fields to these values:
      | Show activities on cards | Yes |
    And I press "Save and display"
    And I am on the "Course 1" course page logged in as student1
    Then "//*[contains(@class, 'aicourse-card-activities')]" "xpath_element" should exist
    And "//*[contains(@class, 'aicourse-cards-grid')]//*[contains(@class, 'aicourse-card-activity-name')][contains(text(), 'Week one assignment')]" "xpath_element" should exist
    And "//*[contains(@class, 'aicourse-cards-grid')]//*[contains(@class, 'aicourse-card-activity-name')][contains(text(), 'Week two quiz')]" "xpath_element" should exist

  Scenario: Section pages list their activities
    # "Section 1" also appears in the course index drawer, so scope the click to the card grid.
    Given I am on the "Course 1" course page logged in as student1
    When I click on "Section 1" "link" in the "//*[contains(@class, 'aicourse-cards-grid')]" "xpath_element"
    Then I should see "Week one assignment"
