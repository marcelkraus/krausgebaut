<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The listener is the only thing in the source that hardens a response, and it
 * fails silently: a renamed header or a lost autoconfiguration takes the
 * headers off without breaking a page. So the headers are asserted on every
 * public path rather than on the homepage alone.
 */
final class SecurityHeadersListenerTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function publicPaths(): iterable
    {
        yield 'homepage' => ['/'];
        yield 'imprint' => ['/impressum'];
        yield 'privacy' => ['/datenschutz'];
        yield 'robots' => ['/robots.txt'];
        yield 'sitemap' => ['/sitemap.xml'];
        yield 'case study' => ['/referenzen/meetmyrc'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicPaths')]
    public function testEveryPublicResponseCarriesTheHardeningHeaders(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
    }
}
