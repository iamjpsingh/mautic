<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Tests\Unit\OAuth2;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\EncryptionHelper;
use Mautic\CoreBundle\OAuth2\OAuthProviderRegistry;
use Mautic\CoreBundle\OAuth2\Provider\GoogleProvider;
use Mautic\CoreBundle\OAuth2\Provider\MetaProvider;
use Mautic\CoreBundle\OAuth2\Provider\MicrosoftProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OAuthProviderRegistryTest extends TestCase
{
    private MockObject&CoreParametersHelper $coreParametersHelper;
    private MockObject&EncryptionHelper $encryptionHelper;
    private OAuthProviderRegistry $registry;

    protected function setUp(): void
    {
        $this->coreParametersHelper = $this->createMock(CoreParametersHelper::class);
        $this->encryptionHelper = $this->createMock(EncryptionHelper::class);
        $this->registry = new OAuthProviderRegistry($this->coreParametersHelper, $this->encryptionHelper);
    }

    public function testAddAndGetProvider(): void
    {
        $google = new GoogleProvider();
        $this->registry->addProvider($google);

        $this->assertSame($google, $this->registry->getProvider('google'));
        $this->assertNull($this->registry->getProvider('nonexistent'));
    }

    public function testGetProviders(): void
    {
        $google = new GoogleProvider();
        $microsoft = new MicrosoftProvider();
        $meta = new MetaProvider();

        $this->registry->addProvider($google);
        $this->registry->addProvider($microsoft);
        $this->registry->addProvider($meta);

        $providers = $this->registry->getProviders();

        $this->assertCount(3, $providers);
        $this->assertArrayHasKey('google', $providers);
        $this->assertArrayHasKey('microsoft', $providers);
        $this->assertArrayHasKey('meta', $providers);
    }

    public function testIsConfiguredReturnsTrueWhenClientIdSet(): void
    {
        $this->coreParametersHelper
            ->method('get')
            ->with('oauth2_google_client_id')
            ->willReturn('my-client-id');

        $this->assertTrue($this->registry->isConfigured('google'));
    }

    public function testIsConfiguredReturnsFalseWhenEmpty(): void
    {
        $this->coreParametersHelper
            ->method('get')
            ->with('oauth2_google_client_id')
            ->willReturn('');

        $this->assertFalse($this->registry->isConfigured('google'));
    }

    public function testGetCallbackUrl(): void
    {
        $this->coreParametersHelper
            ->method('get')
            ->willReturnMap([
                ['site_url', 'https://mautic.example.com'],
                ['oauth2_google_client_id', null],
            ]);

        $url = $this->registry->getCallbackUrl('google');
        $this->assertSame('https://mautic.example.com/oauth/callback/google', $url);
    }

    public function testGetScopesMergesDefaultAndExtra(): void
    {
        $google = new GoogleProvider();
        $this->registry->addProvider($google);

        $this->coreParametersHelper
            ->method('get')
            ->with('oauth2_google_scopes')
            ->willReturn('https://www.googleapis.com/auth/contacts.readonly');

        $scopes = $this->registry->getScopes('google');

        $this->assertContains('https://mail.google.com/', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/gmail.send', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/contacts.readonly', $scopes);
    }

    public function testIsTokenExpiredReturnsTrueWhenNoExpiry(): void
    {
        $this->coreParametersHelper
            ->method('get')
            ->with('oauth2_google_token_expires_at')
            ->willReturn(null);

        $this->assertTrue($this->registry->isTokenExpired('google'));
    }

    public function testIsTokenExpiredReturnsTrueWhenPast(): void
    {
        $this->coreParametersHelper
            ->method('get')
            ->with('oauth2_google_token_expires_at')
            ->willReturn((string) (time() - 3600));

        $this->assertTrue($this->registry->isTokenExpired('google'));
    }

    public function testIsTokenExpiredReturnsFalseWhenFuture(): void
    {
        $this->coreParametersHelper
            ->method('get')
            ->with('oauth2_google_token_expires_at')
            ->willReturn((string) (time() + 3600));

        $this->assertFalse($this->registry->isTokenExpired('google'));
    }

    public function testGoogleProviderEndpoints(): void
    {
        $provider = new GoogleProvider();

        $this->assertSame('google', $provider->getName());
        $this->assertSame('Google', $provider->getDisplayName());
        $this->assertSame('https://accounts.google.com/o/oauth2/v2/auth', $provider->getAuthorizationUrl());
        $this->assertSame('https://oauth2.googleapis.com/token', $provider->getTokenUrl());
        $this->assertNotEmpty($provider->getDefaultScopes());
    }

    public function testMicrosoftProviderEndpoints(): void
    {
        $provider = new MicrosoftProvider();

        $this->assertSame('microsoft', $provider->getName());
        $this->assertSame('Microsoft', $provider->getDisplayName());
        $this->assertStringContainsString('login.microsoftonline.com', $provider->getAuthorizationUrl());
        $this->assertStringContainsString('login.microsoftonline.com', $provider->getTokenUrl());
        $this->assertContains('offline_access', $provider->getDefaultScopes());
    }

    public function testMetaProviderEndpoints(): void
    {
        $provider = new MetaProvider();

        $this->assertSame('meta', $provider->getName());
        $this->assertSame('Meta', $provider->getDisplayName());
        $this->assertStringContainsString('facebook.com', $provider->getAuthorizationUrl());
        $this->assertStringContainsString('graph.facebook.com', $provider->getTokenUrl());
    }
}
