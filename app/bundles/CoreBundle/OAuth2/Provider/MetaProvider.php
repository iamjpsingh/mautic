<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\OAuth2\Provider;

class MetaProvider implements OAuthProviderInterface
{
    public function getName(): string
    {
        return 'meta';
    }

    public function getDisplayName(): string
    {
        return 'Meta';
    }

    public function getAuthorizationUrl(): string
    {
        return 'https://www.facebook.com/v21.0/dialog/oauth';
    }

    public function getTokenUrl(): string
    {
        return 'https://graph.facebook.com/v21.0/oauth/access_token';
    }

    public function getDefaultScopes(): array
    {
        return [
            'whatsapp_business_management',
            'whatsapp_business_messaging',
        ];
    }

    public function getIcon(): string
    {
        return 'app/bundles/CoreBundle/Assets/img/oauth/meta.png';
    }
}
