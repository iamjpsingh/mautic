<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\OAuth2\Provider;

class GoogleProvider implements OAuthProviderInterface
{
    public function getName(): string
    {
        return 'google';
    }

    public function getDisplayName(): string
    {
        return 'Google';
    }

    public function getAuthorizationUrl(): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    public function getTokenUrl(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    public function getDefaultScopes(): array
    {
        return [
            'https://mail.google.com/',
            'https://www.googleapis.com/auth/gmail.send',
            'https://www.googleapis.com/auth/userinfo.email',
        ];
    }

    public function getIcon(): string
    {
        return 'app/bundles/CoreBundle/Assets/img/oauth/google.png';
    }
}
