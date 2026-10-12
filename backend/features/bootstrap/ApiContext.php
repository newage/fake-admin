<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use App\Db\ConnectionFactory;
use Doctrine\DBAL\DriverManager;
use GuzzleHttp\Client;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Psr\Http\Message\ResponseInterface;

final class ApiContext implements Context
{
    private Client $http;
    private ?ResponseInterface $response = null;
    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private ?string $previousRefreshToken = null;

    public function __construct()
    {
        $this->http = new Client([
            'base_uri'    => getenv('API_BASE_URL') ?: 'http://nginx',
            'http_errors' => false,
        ]);
    }

    #[When('I send a :method request to :path')]
    public function iSendARequestTo(string $method, string $path): void
    {
        $path    = $this->resolveSensorPlaceholders($path);
        $options = $this->accessToken === null ? [] : ['headers' => ['Authorization' => 'Bearer ' . $this->accessToken]];

        $this->response = $this->http->request($method, $path, $options);
    }

    #[When('I send an authenticated :method request to :path')]
    public function iSendAnAuthenticatedRequestTo(string $method, string $path): void
    {
        $this->iSendARequestTo($method, $path);
    }

    #[Given('I use the bearer token :token')]
    public function iUseTheBearerToken(string $token): void
    {
        $this->accessToken = $token;
    }

    #[Given('I use an access token that expired a minute ago')]
    public function iUseAnExpiredAccessToken(): void
    {
        $this->accessToken = $this->signedToken((string) base64_decode((string) getenv('OAUTH_PRIVATE_KEY_B64')), '-1 minute');
    }

    #[Given('I use an access token signed with a foreign key')]
    public function iUseATokenSignedWithAForeignKey(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);

        $this->accessToken = $this->signedToken($pem, '+5 minutes');
    }

    #[Then('the response status code should be :code')]
    public function theResponseStatusCodeShouldBe(string $code): void
    {
        $actual = $this->response?->getStatusCode();
        if ($actual !== (int) $code) {
            throw new RuntimeException(sprintf('Expected status %s, got %s', $code, var_export($actual, true)));
        }
    }

    #[Then('the response should be JSON with key :key')]
    public function theResponseShouldBeJsonWithKey(string $key): void
    {
        $data = json_decode((string) $this->response?->getBody(), true);
        if (!is_array($data) || !array_key_exists($key, $data)) {
            throw new RuntimeException(sprintf('Response is not JSON with key "%s"', $key));
        }
    }

    #[When('I request a token for :email with password :password')]
    public function iRequestATokenWithPassword(string $email, string $password): void
    {
        $this->response = $this->http->post('/token', [
            'form_params' => [
                'grant_type' => 'password',
                'client_id'  => 'fake-admin-spa',
                'username'   => $email,
                'password'   => $password,
            ],
        ]);
    }

    #[When('I request a token for the superadmin with password :password')]
    public function iRequestATokenForTheSuperadmin(string $password): void
    {
        $this->iRequestATokenWithPassword((string) getenv('SUPERADMIN_EMAIL'), $password);
    }

    #[Given('I am signed in as :email with password :password')]
    public function iAmSignedInAs(string $email, string $password): void
    {
        $this->iRequestATokenWithPassword($email, $password);
        $this->rememberRefreshToken();
    }

    #[When('I refresh the session')]
    public function iRefreshTheSession(): void
    {
        $this->postRefresh((string) $this->refreshToken);
        $this->rememberRefreshToken();
    }

    #[When('I refresh with the previous refresh token')]
    public function iRefreshWithThePreviousRefreshToken(): void
    {
        $this->postRefresh((string) $this->previousRefreshToken);
    }

    #[When('I sign out')]
    public function iSignOut(): void
    {
        $this->iRevokeTheToken((string) $this->refreshToken);
    }

    #[When('I revoke the token :token')]
    public function iRevokeTheToken(string $token): void
    {
        $this->response = $this->http->post('/token/revoke', [
            'form_params' => ['token' => $token, 'client_id' => 'fake-admin-spa'],
        ]);
    }

    #[Then('the new refresh token should differ from the previous one')]
    public function theNewRefreshTokenShouldDiffer(): void
    {
        if ($this->refreshToken === null || $this->refreshToken === $this->previousRefreshToken) {
            throw new RuntimeException('Refresh token was not rotated');
        }
    }

    #[Then('the JSON field :field should not be empty')]
    public function theJsonFieldShouldNotBeEmpty(string $field): void
    {
        if (empty($this->json()[$field] ?? null)) {
            throw new RuntimeException(sprintf('JSON field "%s" is empty or missing', $field));
        }
    }

    #[Then('the JSON field :field should be :expected')]
    public function theJsonFieldShouldBe(string $field, string $expected): void
    {
        $actual = $this->json()[$field] ?? null;
        if ((string) $actual !== $expected) {
            throw new RuntimeException(sprintf('Expected "%s" = "%s", got %s', $field, $expected, var_export($actual, true)));
        }
    }

    #[Then('the JSON field :field should be at most :max')]
    public function theJsonFieldShouldBeAtMost(string $field, string $max): void
    {
        $actual = $this->json()[$field] ?? null;
        if (!is_numeric($actual) || $actual > (int) $max) {
            throw new RuntimeException(sprintf('Expected "%s" <= %s, got %s', $field, $max, var_export($actual, true)));
        }
    }

    #[Then('the JSON list :field should have :count elements')]
    public function theJsonListShouldHave(string $field, string $count): void
    {
        $list = $this->json()[$field] ?? null;
        if (!is_array($list) || count($list) !== (int) $count) {
            throw new RuntimeException(sprintf('Expected %s elements in "%s", got %s', $count, $field, is_array($list) ? count($list) : 'none'));
        }
    }

    #[Then('the JSON items should not expose :field')]
    public function theJsonItemsShouldNotExpose(string $field): void
    {
        foreach ($this->json()['items'] ?? [] as $item) {
            if (array_key_exists($field, $item)) {
                throw new RuntimeException(sprintf('Item exposes "%s"', $field));
            }
        }
    }

    #[Then('the JSON item :index field :field should be :expected')]
    public function theJsonItemFieldShouldBe(string $index, string $field, string $expected): void
    {
        $actual = $this->json()['items'][(int) $index][$field] ?? null;
        if ((string) $actual !== $expected) {
            throw new RuntimeException(sprintf('Expected item %s "%s" = "%s", got %s', $index, $field, $expected, var_export($actual, true)));
        }
    }

    #[Then('the response body should not contain :text')]
    public function theResponseShouldNotContain(string $text): void
    {
        if (str_contains((string) $this->response?->getBody(), $text)) {
            throw new RuntimeException(sprintf('Response unexpectedly contains "%s"', $text));
        }
    }

    #[Then('the response should not contain tokens')]
    public function theResponseShouldNotContainTokens(): void
    {
        $data = $this->json();
        if (isset($data['access_token']) || isset($data['refresh_token'])) {
            throw new RuntimeException('Response unexpectedly contains tokens');
        }
    }

    /** `{sensor:CODE}` in the path is replaced with the sensor id from the DB. */
    private function resolveSensorPlaceholders(string $path): string
    {
        return (string) preg_replace_callback('/\{sensor:([^}]+)\}/', function (array $m): string {
            $db = DriverManager::getConnection(ConnectionFactory::params());

            return (string) $db->fetchOne('SELECT id FROM sensors WHERE code = ?', [$m[1]]);
        }, $path);
    }

    private function postRefresh(string $refreshToken): void
    {
        $this->response = $this->http->post('/token', [
            'form_params' => [
                'grant_type'    => 'refresh_token',
                'client_id'     => 'fake-admin-spa',
                'refresh_token' => $refreshToken,
            ],
        ]);
    }

    /** A token of the same shape the server issues, but with an arbitrary key and expiry. */
    private function signedToken(string $privateKeyPem, string $expiresAt): string
    {
        $config = Configuration::forAsymmetricSigner(new Sha256(), InMemory::plainText($privateKeyPem), InMemory::plainText('unused'));
        $now    = new DateTimeImmutable();

        return $config->builder()
            ->permittedFor('fake-admin-spa')
            ->identifiedBy(bin2hex(random_bytes(20)))
            ->issuedAt($now->modify('-10 minutes'))
            ->canOnlyBeUsedAfter($now->modify('-10 minutes'))
            ->expiresAt($now->modify($expiresAt))
            ->relatedTo('1')
            ->withClaim('scopes', ['role:admin'])
            ->getToken($config->signer(), $config->signingKey())
            ->toString();
    }

    private function rememberRefreshToken(): void
    {
        $token = $this->json()['refresh_token'] ?? null;
        $access = $this->json()['access_token'] ?? null;
        if ($access !== null) {
            $this->accessToken = $access;
        }
        if ($token !== null) {
            $this->previousRefreshToken = $this->refreshToken;
            $this->refreshToken         = $token;
        }
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        $data = json_decode((string) $this->response?->getBody(), true);

        return is_array($data) ? $data : [];
    }
}
