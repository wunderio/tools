Feature: Login

  @javascript
  Scenario: Tests login for existing user
    Given I am an anonymous user
    And I am at "/"
    And I should see the link "Login" in the "header" region
    When I click "Login"
    And I wait for AJAX to finish
    Then I should see the text "Dein Benutzername" in the "colorbox" region
    When I fill in the following:
    | Dein Benutzername | user4026 |
    | Dein Passwort | user4026 |
    And I press the "Anmelden" button
    Then the url should match "/node/2534"
    And I should see the heading "The Green Ladys" in the "content" region
    And I should see the link "Log out" in the "header" region
    Then show me a screenshot
    When I click "Log out"
    Then the url should match "/"
