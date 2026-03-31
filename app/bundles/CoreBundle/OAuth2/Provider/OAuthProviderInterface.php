<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\OAuth2\Provider;

/**
 * Defines an OAuth2 provider (Google, Microsoft, Meta, etc.)
 * that can be configured system-wide and shared across plugins.
 */
interface OAuthProviderInterface
{
    /**
     * Unique identifier for this provider (e.g. 'google', 'microsoft', 'meta').
     */
    public function getName(): string;

    /**
     * Human-readable display name (e.g. 'Google', 'Microsoft Azure AD', 'Meta').
     */
    public function getDisplayName(): string;

    /**
     * OAuth2 authorization endpoint URL.
     */
    public function getAuthorizationUrl(): string;

    /**
     * OAuth2 token endpoint URL.
     */
    public function getTokenUrl(): string;

    /**
     * Default scopes for this provider.
     *
     * @return string[]
     */
    public function getDefaultScopes(): array;

    /**
     * Path to the provider's icon (relative to bundle assets).
     */
    public function getIcon(): string;
}
