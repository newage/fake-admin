@superadmin
Feature: First sign-in of the superadmin
  The superadmin account is created with an empty password; the first sign-in sets it, later attempts cannot.

  Scenario: First sign-in sets the password
    Given the superadmin has not set a password yet
    When I request a token for the superadmin with password "first-password-1"
    Then the response status code should be 200
    And the JSON field "access_token" should not be empty

  Scenario: The password cannot be set a second time
    Given the superadmin has not set a password yet
    And I request a token for the superadmin with password "first-password-1"
    When I request a token for the superadmin with password "attacker-password"
    Then the response status code should be 400
    And the response should not contain tokens

  Scenario: The password set at first sign-in keeps working
    Given the superadmin has not set a password yet
    And I request a token for the superadmin with password "first-password-1"
    When I request a token for the superadmin with password "first-password-1"
    Then the response status code should be 200

  Scenario: Too short first password is rejected
    Given the superadmin has not set a password yet
    When I request a token for the superadmin with password "short"
    Then the response status code should be 400
    And the response should not contain tokens
