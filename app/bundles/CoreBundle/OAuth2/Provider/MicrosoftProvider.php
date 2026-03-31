<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\OAuth2\Provider;

class MicrosoftProvider implements OAuthProviderInterface
{
    public function getName(): string
    {
        return 'microsoft';
    }

    public function getDisplayName(): string
    {
        return 'Microsoft';
    }

    public function getAuthorizationUrl(): string
    {
        return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    }

    public function getTokenUrl(): string
    {
        return 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    }

    public function getDefaultScopes(): array
    {
        return [
            'https://outlook.office365.com/SMTP.Send',
            'https://graph.microsoft.com/Mail.Send',
            'https://graph.microsoft.com/User.Read',
            'offline_access',
        ];
    }

    public function getIcon(): string
    {
        return 'app/bundles/CoreBundle/Assets/img/oauth/microsoft.png';
    }
}
