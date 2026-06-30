@tool @tool_mailaudit
Feature: Browse the sent email audit
  In order to audit outbound Moodle mail
  As an administrator
  I need to open and filter the sent email audit browser

  Scenario: An administrator can open the sent email audit browser
    Given I log in as "admin"
    When I navigate to "Plugins > Admin tools > Sent email audit > Browse sent email" in site administration
    Then I should see "Sent email audit"
    And I should see "Filters"

  Scenario: The browser shows an empty state when no mail matches
    Given I log in as "admin"
    And I navigate to "Plugins > Admin tools > Sent email audit > Browse sent email" in site administration
    When I expand all fieldsets
    And I set the field "Subject contains" to "no-such-subject-zzz"
    And I press "Filter"
    Then I should see "No audited mail matched the current filters."
