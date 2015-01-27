Feature: Anonymous page access

  Scenario Outline: Tests page access for anonymous users
    Given I am an anonymous user
    When I go to "<url>"
    Then the response status code should be <code>
    Examples:
      | url                       | code  |
      | / | 200 |

