<?php

declare(strict_types=1);

use App\Auth\Repository\AccessTokenRepository;
use App\Auth\Repository\ClientRepository;
use App\Auth\Repository\RefreshTokenRepository;
use App\Auth\Repository\ScopeRepository;
use App\Auth\Repository\UserRepository;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\ImplicitGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;

// Keys are taken from environment variables (see .env.example, docker/scripts/gen-keys.sh).
// That is why config caching is disabled in mezzio.global.php: keys must not end up in the cache file.
return [
    'authentication' => [
        'private_key'          => ['key_or_path' => (string) base64_decode((string) getenv('OAUTH_PRIVATE_KEY_B64'))],
        'public_key'           => ['key_or_path' => (string) base64_decode((string) getenv('OAUTH_PUBLIC_KEY_B64'))],
        'encryption_key'       => (string) getenv('OAUTH_ENCRYPTION_KEY'),
        'access_token_expire'  => 'PT5M',
        'refresh_token_expire' => 'P30D',
        // Configs are merged with the package defaults, so unneeded grants are disabled explicitly via null
        'grants'               => [
            ClientCredentialsGrant::class => null,
            AuthCodeGrant::class          => null,
            ImplicitGrant::class          => null,
            PasswordGrant::class     => PasswordGrant::class,
            RefreshTokenGrant::class => RefreshTokenGrant::class,
        ],
    ],
    'dependencies'   => [
        'aliases' => [
            AccessTokenRepositoryInterface::class  => AccessTokenRepository::class,
            ClientRepositoryInterface::class       => ClientRepository::class,
            RefreshTokenRepositoryInterface::class => RefreshTokenRepository::class,
            ScopeRepositoryInterface::class        => ScopeRepository::class,
            UserRepositoryInterface::class         => UserRepository::class,
        ],
    ],
];
