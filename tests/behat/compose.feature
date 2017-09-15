@block @block_clampmail
Feature: Send email
  In order to communicate effectively
  As someone who can send email
  I need the ability to send email

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Test Course Separate | CF101 | 0 | 1 |
      | Test Course Visible  | CF102 | 0 | 2 |
      | Test Course NoGroups | CF100 | 0 | 0 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
      | student3 | Student | 3 | student3@example.com |
      | student4 | Student | 4 | student4@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | CF101 | editingteacher |
      | teacher1 | CF102 | editingteacher |
      | teacher1 | CF100 | editingteacher |
      | student1 | CF101 | student |
      | student2 | CF101 | student |
      | student3 | CF101 | student |
      | student4 | CF101 | student |
      | student1 | CF102 | student |
      | student2 | CF102 | student |
      | student3 | CF102 | student |
      | student4 | CF102 | student |
      | student1 | CF100 | student |
      | student2 | CF100 | student |
      | student3 | CF100 | student |
      | student4 | CF100 | student |
    And the following "groups" exist:
      | name    | description | course | idnumber |
      | Group A | Group A     | CF101  | GROUPA   |
      | Group B | Group B     | CF101  | GROUPB   |
      | Group C | Group C     | CF102  | GROUPC   |
      | Group D | Group D     | CF102  | GROUPD   |
      | Group E | Group E     | CF100  | GROUPE   |
    And the following "group members" exist:
      | user     | group  |
      | student1 | GROUPA |
      | student2 | GROUPA |
      | student3 | GROUPB |
      | student4 | GROUPB |
      | student1 | GROUPC |
      | student2 | GROUPC |
      | student3 | GROUPD |
      | student4 | GROUPD |
      | student2 | GROUPE |
    And the following "permission overrides" exist:
      | capability              | permission | role           | contextlevel | reference |
      | block/clampmail:cansend | Allow      | student        | Course       | CF101     |
      | block/clampmail:cansend | Allow      | student        | Course       | CF102     |
      | block/clampmail:cansend | Allow      | student        | Course       | CF100     |
    And I log in as "teacher1"
    And I am on "Test Course Separate" course homepage
    And I turn editing mode on
    And I add the "Quickmail" block
    And I am on "Test Course Visible" course homepage
    And I add the "Quickmail" block
    And I am on "Test Course NoGroups" course homepage
    And I add the "Quickmail" block
    And I log out

  @javascript
  Scenario: Internal navigation
    Given I log in as "teacher1"
    And I am on "Test Course Separate" course homepage
    And I follow "Compose new email"
    And I follow "View drafts"
    Then I should see "You have no email drafts"
    When I press "Continue"
    And I follow "View history"
    Then I should see "You have no email history yet"
    When I press "Continue"
    Then I should see "Selected recipients"

  @javascript
  Scenario: Teacher composes to a single group
    Given I log in as "teacher1"
    And I am on "Test Course Separate" course homepage
    And I follow "Compose new email"
    And I set the following fields to these values:
      | groups | Group B |
    And I press "Add"
    And I set the following fields to these values:
      | Subject | Doom At 11 |
      | Message | Salvation At Noon |
    And I upload "blocks/clampmail/tests/fixtures/uploadtext.txt" file to "Attachment(s)" filemanager
    When I press "Send email"
    Then I should see "View history"
    And I should see "uploadtext.txt"
    And I follow "Open email"
    Then I should see "Student 3" in the "#mail_users" "css_element"
    And I should see "Student 4" in the "#mail_users" "css_element"

  @javascript
  Scenario: Student uses separate groups
    Given I log in as "student1"
    And I am on "Test Course Separate" course homepage
    And I follow "Compose new email"
    And I should not see "Student 3" in the "#from_users" "css_element"
    And I set the following fields to these values:
      | groups | Group A |
    And I press "Add"
    And I set the following fields to these values:
      | Subject | Doom At 11 |
      | Message | Salvation At Noon |
    And I upload "blocks/clampmail/tests/fixtures/uploadtext.txt" file to "Attachment(s)" filemanager
    When I press "Send email"
    Then I should see "View history"
    And I should see "uploadtext.txt"
    And I follow "Open email"
    Then I should see "Student 2" in the "#mail_users" "css_element"

  @javascript
  Scenario: Student uses visible groups
    Given I log in as "student1"
    And I am on "Test Course Visible" course homepage
    And I follow "Compose new email"
    And I set the following fields to these values:
      | groups | Group C |
    And I press "Add"
    And I set the following fields to these values:
      | groups | Group D |
    And I press "Add"
    And I set the following fields to these values:
      | Subject | Doom At 11 |
      | Message | Salvation At Noon |
    And I upload "blocks/clampmail/tests/fixtures/uploadtext.txt" file to "Attachment(s)" filemanager
    When I press "Send email"
    Then I should see "View history"
    And I should see "uploadtext.txt"
    And I follow "Open email"
    And I should see "Student 2" in the "#mail_users" "css_element"
    And I should see "Student 3" in the "#mail_users" "css_element"

  @javascript
  Scenario: Student uses no groups
  Given I log in as "student1"
    And I am on "Test Course NoGroups" course homepage
    And I follow "Compose new email"
    And I set the following fields to these values:
      | groups | Not in a group |
    And I press "Add"
    And I set the following fields to these values:
      | Subject | Doom At 11 |
      | Message | Salvation At Noon |
    And I upload "blocks/clampmail/tests/fixtures/uploadtext.txt" file to "Attachment(s)" filemanager
    When I press "Send email"
    Then I should see "View history"
    And I should see "uploadtext.txt"
    And I follow "Open email"
    And I should see "Student 2" in the "#mail_users" "css_element"
    And I should see "Teacher 1" in the "#mail_users" "css_element"
