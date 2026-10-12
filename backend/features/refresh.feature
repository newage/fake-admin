Feature: Refresh token rotation and revocation
  Refresh tokens are single-use, can be revoked on logout and stop working for blocked users.

  Background:
    Given an active "manager" user "manager@behat.test" with password "secret-pass-1"
    And I am signed in as "manager@behat.test" with password "secret-pass-1"

  Scenario: Refresh issues a new pair of tokens
    When I refresh the session
    Then the response status code should be 200
    And the JSON field "access_token" should not be empty
    And the new refresh token should differ from the previous one

  Scenario: A used refresh token cannot be used again
    Given I refresh the session
    When I refresh with the previous refresh token
    Then the response status code should be 400
    And the JSON field "error" should be "invalid_grant"
    And the response should not contain tokens

  Scenario: The newly issued refresh token keeps working
    Given I refresh the session
    When I refresh the session
    Then the response status code should be 200

  Scenario: Logout revokes the refresh token
    When I sign out
    Then the response status code should be 200
    When I refresh the session
    Then the response status code should be 400
    And the response should not contain tokens

  Scenario: Revoking an unknown token is not an error
    When I revoke the token "not-a-real-token"
    Then the response status code should be 200

  Scenario: Blocked user cannot refresh
    Given the user "manager@behat.test" is blocked
    When I refresh the session
    Then the response status code should be 400
    And the response should not contain tokens
