Feature: Service health
  Scenario: Ping responds
    When I send a "GET" request to "/api/ping"
    Then the response status code should be 200
    And the response should be JSON with key "ack"
