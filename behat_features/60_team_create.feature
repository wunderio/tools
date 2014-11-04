Feature: User creation

  @javascript
  Scenario: Tests user creation form
    When I click the activation link that was sent to "[random:3]@example.com"
    Then I should see the text "Super! Das hat geklappt!" in the "content" region
    And show me a screenshot

