<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Every route answers, the legal pages stay out of the index, the contact
 * routes redirect rather than carrying the address in a document, and a case
 * study exists only where the content file says it does.
 */
final class RoutingTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function successfulPaths(): iterable
    {
        yield 'homepage' => ['/'];
        yield 'imprint' => ['/impressum'];
        yield 'privacy' => ['/datenschutz'];
        yield 'robots' => ['/robots.txt'];
        yield 'sitemap' => ['/sitemap.xml'];
        yield 'case study meetmyrc' => ['/referenzen/meetmyrc'];
        yield 'case study ownyard' => ['/referenzen/ownyard'];
    }

    #[DataProvider('successfulPaths')]
    public function testPathAnswers(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
    }

    public function testEveryPageCarriesExactlyOneMainHeading(): void
    {
        $client = static::createClient();

        foreach (['/', '/impressum', '/datenschutz', '/referenzen/meetmyrc'] as $path) {
            $crawler = $client->request('GET', $path);

            self::assertCount(1, $crawler->filter('h1'), sprintf('%s carries one <h1>', $path));
        }
    }

    public function testAReferenceWithoutADetailPageHasNoCaseStudy(): void
    {
        $client = static::createClient();

        // The slug exists in references.json, but with `hatDetailseite: false`
        // — that card links out instead, so the internal route must not answer.
        $client->request('GET', '/referenzen/3d-druck-kostenrechner');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnUnknownSlugIsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/referenzen/gibt-es-nicht');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLegalPagesAreNotIndexed(): void
    {
        $client = static::createClient();

        foreach (['/impressum', '/datenschutz'] as $path) {
            $client->request('GET', $path);

            self::assertSelectorExists('meta[name="robots"][content="noindex,follow"]');
        }
    }

    public function testHomepageAndCaseStudiesAreIndexed(): void
    {
        $client = static::createClient();

        foreach (['/', '/referenzen/meetmyrc'] as $path) {
            $client->request('GET', $path);

            self::assertSelectorExists('meta[name="robots"][content="index,follow"]');
        }
    }

    public function testTheRedirectRoutesRedirect(): void
    {
        $client = static::createClient();

        foreach (['/kontakt-per-email', '/kontakt-per-whats-app', '/bewerten'] as $path) {
            $client->request('GET', $path);

            self::assertResponseRedirects(message: sprintf('%s redirects', $path));
        }
    }

    public function testTheMailAddressNeverAppearsInTheMarkup(): void
    {
        $client = static::createClient();

        // The address is offered through a redirect route precisely so that no
        // document carries it. A `mailto:` written into the markup would undo
        // that in one line.
        foreach (['/', '/impressum', '/datenschutz'] as $path) {
            $client->request('GET', $path);
            $inhalt = (string) $client->getResponse()->getContent();

            self::assertStringNotContainsString('mailto:', $inhalt, sprintf('%s carries no mailto:', $path));
            self::assertStringNotContainsString('mail@krausgebaut.de', $inhalt, sprintf('%s carries no plain address', $path));
        }
    }

    /**
     * The mailbox is a mandatory disclosure, so it has to be readable — and it
     * is hidden from a harvester by a decoy that only the markup carries. Both
     * halves are asserted: drop the decoy and the obfuscation is gone, drop
     * the plain address and the disclosure is.
     */
    public function testTheLegalPagesSpellTheMailboxOutBehindADecoy(): void
    {
        $client = static::createClient();

        foreach (['/impressum', '/datenschutz'] as $path) {
            $client->request('GET', $path);
            $inhalt = (string) $client->getResponse()->getContent();

            self::assertStringContainsString(
                'mail+legal@<span style="display:none" aria-hidden="true">nospam.</span>krausgebaut.de',
                $inhalt,
                $path,
            );
            self::assertStringNotContainsString('mail(at)', $inhalt, $path);
        }
    }

    public function testSitemapIsWellFormedAndCarriesTheCaseStudies(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sitemap.xml');

        $inhalt = (string) $client->getResponse()->getContent();

        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        self::assertNotFalse(simplexml_load_string($inhalt), 'The sitemap parses as XML');
        self::assertStringContainsString('/referenzen/meetmyrc', $inhalt);
        self::assertStringNotContainsString('/impressum', $inhalt);
    }

    public function testRobotsKeepsTheRedirectRoutesOutOfCrawlerCorpora(): void
    {
        $client = static::createClient();
        $client->request('GET', '/robots.txt');

        $inhalt = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Sitemap:', $inhalt);
        self::assertStringContainsString('/kontakt-per-email', $inhalt);
    }
}
