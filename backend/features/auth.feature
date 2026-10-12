Feature: Login with email and password
  Users sign in with email and password and receive access and refresh tokens.

  Scenario: Successful login
    Given an active "manager" user "manager@behat.test" with password "secret-pass-1"
    When I request a token for "manager@behat.test" with password "secret-pass-1"
    Then the response status code should be 200
    And the JSON field "access_token" should not be empty
    And the JSON field "refresh_token" should not be empty
    And the JSON field "expires_in" should be at most 300

  Scenario: Wrong password
    Given an active "manager" user "manager@behat.test" with password "secret-pass-1"
    When I request a token for "manager@behat.test" with password "wrong-password"
    Then the response status code should be 400
    And the JSON field "error" should be "invalid_grant"
    And the response should not contain tokens

  Scenario: Unknown email looks the same as a wrong password
    When I request a token for "nobody@behat.test" with password "whatever-pass"
    Then the response status code should be 400
    And the JSON field "error" should be "invalid_grant"
    And the response should not contain tokens

  Scenario: Blocked user cannot sign in
    Given a blocked "user" user "blocked@behat.test" with password "secret-pass-1"
    When I request a token for "blocked@behat.test" with password "secret-pass-1"
    Then the response status code should be 400
    And the response should not contain tokens
