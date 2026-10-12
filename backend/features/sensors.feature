Feature: Sensor list
  Admins and managers see a paginated, sortable, filterable list of sensors; other roles are refused.

  Background:
    Given 30 test sensors with code prefix "BHT-"

  Scenario: Manager gets the first page with defaults
    Given an active "manager" user "manager@behat.test" with password "secret-pass-1"
    And I am signed in as "manager@behat.test" with password "secret-pass-1"
    When I send an authenticated "GET" request to "/api/sensors?code=BHT-"
    Then the response status code should be 200
    And the JSON field "total" should be "30"
    And the JSON field "page" should be "1"
    And the JSON field "page_size" should be "25"
    And the JSON list "items" should have 25 elements
    And the JSON items should not expose "secret_enc"
    And the JSON items should not expose "key_version"

  Scenario: Admin can read the second page
    Given an active "admin" user "admin@behat.test" with password "secret-pass-1"
    And I am signed in as "admin@behat.test" with password "secret-pass-1"
    When I send an authenticated "GET" request to "/api/sensors?code=BHT-&page=2"
    Then the response status code should be 200
    And the JSON list "items" should have 5 elements

  Scenario: Sorting by code descending
    Given an active "manager" user "manager@behat.test" with password "secret-pass-1"
    And I am signed in as "manager@behat.test" with password "secret-pass-1"
    When I send an authenticated "GET" request to "/api/sensors?code=BHT-&sort=code&order=desc&page_size=2"
    Then the response status code should be 200
    And the JSON item 0 field "code" should be "BHT-0030"
    And the JSON item 1 field "code" should be "BHT-0029"

  Scenario: Page size above the maximum is rejected
    Given an active "manager" user "manager@behat.test" with password "secret-pass-1"
    And I am signed in as "manager@behat.test" with password "secret-pass-1"
    When I send an authenticated "GET" request to "/api/sensors?page_size=101"
    Then the response status code should be 422
    And the JSON field "error" should be "validation_failed"

  Scenario: Sorting by a non-whitelisted field is rejected
    Given an active "manager" user "manager@behat.test" with password "secret-pass-1"
    And I am signed in as "manager@behat.test" with password "secret-pass-1"
    When I send an authenticated "GET" request to "/api/sensors?sort=secret_enc"
    Then the response status code should be 422

  Scenario: Too short text filter is rejected
    Given an active "manager" user "manager@behat.test" with password "secret-pass-1"
    And I am signed in as "manager@behat.test" with password "secret-pass-1"
    When I send an authenticated "GET" request to "/api/sensors?name=te"
    Then the response status code should be 422

  Scenario: The user role has no access
    Given an active "user" user "plain@behat.test" with password "secret-pass-1"
    And I am signed in as "plain@behat.test" with password "secret-pass-1"
    When I send an authenticated "GET" request to "/api/sensors"
    Then the response status code should be 403
    And the response body should not contain "BHT-"

  Scenario: Anonymous access is refused
    When I send a "GET" request to "/api/sensors"
    Then the response status code should be 401
