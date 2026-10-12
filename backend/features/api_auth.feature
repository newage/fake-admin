Feature: Protected API requires a valid access token
  Every /api/* route except the health ping needs a valid, unexpired access token signed by the server.

  Scenario: Health ping is public
    When I send a "GET" request to "/api/ping"
    Then the response status code should be 200

  Scenario: No token
    When I send a "GET" request to "/api/sensors"
    Then the response status code should be 401

  Scenario: Garbage token
    Given I use the bearer token "not.a.jwt"
    When I send a "GET" request to "/api/sensors"
    Then the response status code should be 401

  Scenario: Expired token
    Given I use an access token that expired a minute ago
    When I send a "GET" request to "/api/sensors"
    Then the response status code should be 401

  Scenario: Token signed with another key
    Given I use an access token signed with a foreign key
    When I send a "GET" request to "/api/sensors"
    Then the response status code should be 401

  Scenario: Unknown API route is also protected
    When I send a "GET" request to "/api/does-not-exist"
    Then the response status code should be 401

  Scenario: A valid token passes authentication
    Given an active "manager" user "manager@behat.test" with password "secret-pass-1"
    And I am signed in as "manager@behat.test" with password "secret-pass-1"
    When I send an authenticated "GET" request to "/api/ping"
    Then the response status code should be 200
