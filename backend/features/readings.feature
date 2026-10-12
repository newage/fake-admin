Feature: Raw sensor readings
  Managers and admins read the raw readings of one sensor for a required time range, paginated.

  Background:
    Given 3 test sensors with code prefix "BHT-"
    And sensor "BHT-0001" has 5 readings one minute apart starting at "2026-10-01 10:00:00"
    And sensor "BHT-0002" has 2 readings one minute apart starting at "2026-10-01 10:00:00"
    And an active "manager" user "manager@behat.test" with password "secret-pass-1"
    And I am signed in as "manager@behat.test" with password "secret-pass-1"

  Scenario: Readings of one sensor inside the range
    When I send an authenticated "GET" request to "/api/sensors/{sensor:BHT-0001}/readings?from=2026-10-01&to=2026-10-01"
    Then the response status code should be 200
    And the JSON list "items" should have 5 elements
    And the JSON item 0 field "ts" should be "2026-10-01T10:00:00Z"
    And the JSON item 4 field "ts" should be "2026-10-01T10:04:00Z"
    And the JSON field "has_more" should be ""

  Scenario: The time range limits the result
    When I send an authenticated "GET" request to "/api/sensors/{sensor:BHT-0001}/readings?from=2026-10-01T10:01:00&to=2026-10-01T10:02:30"
    Then the response status code should be 200
    And the JSON list "items" should have 2 elements

  Scenario: Pagination tells whether there is more
    When I send an authenticated "GET" request to "/api/sensors/{sensor:BHT-0001}/readings?from=2026-10-01&to=2026-10-01&page_size=2&page=1"
    Then the response status code should be 200
    And the JSON list "items" should have 2 elements
    And the JSON field "has_more" should be "1"

  Scenario: Unknown sensor
    When I send an authenticated "GET" request to "/api/sensors/999999999/readings?from=2026-10-01&to=2026-10-01"
    Then the response status code should be 404

  Scenario: The time range is required
    When I send an authenticated "GET" request to "/api/sensors/{sensor:BHT-0001}/readings"
    Then the response status code should be 422

  Scenario: Page size above the maximum is rejected
    When I send an authenticated "GET" request to "/api/sensors/{sensor:BHT-0001}/readings?from=2026-10-01&to=2026-10-01&page_size=1001"
    Then the response status code should be 422

  Scenario: The user role has no access
    Given an active "user" user "plain@behat.test" with password "secret-pass-1"
    And I am signed in as "plain@behat.test" with password "secret-pass-1"
    When I send an authenticated "GET" request to "/api/sensors/{sensor:BHT-0001}/readings?from=2026-10-01&to=2026-10-01"
    Then the response status code should be 403
