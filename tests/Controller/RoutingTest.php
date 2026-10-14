<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\DefaultController;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Every route answers, the legal pages stay out of the index, the contact
 * routes redirect rather than carrying the address in a document, a case
 * study exists only where the content file says it does, and a printed
 * short link leads home under the campaign it is counted as.
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

        // The slug exists in references.json, but with `hatDetailseite:
        // false`—that card links out instead, so the internal route must not answer.
        $client->request('GET', '/referenzen/3d-druck-kostenrechner');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnUnknownSlugIsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/referenzen/gibt-es-nicht');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function printEditions(): iterable
    {
        foreach (DefaultController::PRINT_EDITIONS as $prefix => $codes) {
            foreach ($codes as $code) {
                $query = 'mtm_campaign=' . DefaultController::PRINT_CAMPAIGNS[$prefix] . '&mtm_medium=print';
                $query .= $prefix === 'ad'
                    ? '&mtm_source=' . DefaultController::PRINT_SOURCES[substr($code, 0, 2)] . '&mtm_kwd=' . substr($code, 2)
                    : '&mtm_kwd=' . $code;

                yield $prefix . '-' . $code => [$prefix . '-' . $code, $query];
            }
        }
    }

    #[DataProvider('printEditions')]
    public function testAPrintLeadsHomeCountedAsPrint(string $slug, string $query): void
    {
        $client = static::createClient();

        $client->request('GET', '/s/' . $slug);

        self::assertResponseRedirects('/?' . $query, 302);
    }

    /**
     * What Matomo reports, written out once rather than derived from the
     * controller, so a renamed campaign cannot pass unnoticed.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function countedAs(): iterable
    {
        yield 'advertisement' => ['/s/ad-ea2610', 'mtm_campaign=advertisement&mtm_medium=print&mtm_source=erftstadt-aktuell&mtm_kwd=2610'];
        yield 'business card' => ['/s/bc-2610', 'mtm_campaign=business-card&mtm_medium=print&mtm_kwd=2610'];
        yield 'flyer' => ['/s/fl-2610', 'mtm_campaign=flyer&mtm_medium=print&mtm_kwd=2610'];
    }

    #[DataProvider('countedAs')]
    public function testAPrintIsCountedUnderItsReadableCampaign(string $path, string $query): void
    {
        $client = static::createClient();

        $client->request('GET', $path);

        self::assertResponseRedirects('/?' . $query, 302);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownShortLinks(): iterable
    {
        yield 'advertisement in an unknown medium' => ['/s/ad-xx2610'];
        yield 'advertisement without edition' => ['/s/ad-ea'];
        yield 'business card without edition' => ['/s/bc-'];
        yield 'code with a hyphen' => ['/s/fl-2610-b'];
        yield 'flyer without edition' => ['/s/fl-'];
        yield 'path behind the slug' => ['/s/gibt-es-nicht/s/fl-2610'];
        yield 'signature model, printed for krausgedruckt only' => ['/s/sm-d13'];
        yield 'unknown advertisement edition' => ['/s/ad-ea0000'];
        yield 'unknown business card edition' => ['/s/bc-0000'];
        yield 'unknown flyer edition' => ['/s/fl-0000'];
        yield 'unknown prefix' => ['/s/xx-2610'];
        yield 'unknown slug' => ['/s/gibt-es-nicht'];
    }

    /**
     * Two prints under one slug would share one count, a slug the route
     * refuses would put a dead code on paper, and an advertisement in a
     * medium without a source would answer 404.
     */
    public function testPrintedSlugsFollowTheSchemeAndAreUnique(): void
    {
        $slugs = array_map(static fn (array $case): string => $case[0], iterator_to_array(self::printEditions(), false));

        foreach ($slugs as $slug) {
            self::assertMatchesRegularExpression('/^[a-z]{2}-[a-z0-9]+$/', $slug);
        }

        foreach (DefaultController::PRINT_EDITIONS['ad'] as $code) {
            self::assertArrayHasKey(substr($code, 0, 2), DefaultController::PRINT_SOURCES);
        }

        self::assertSame(array_values(array_unique($slugs)), $slugs);
    }

    #[DataProvider('unknownShortLinks')]
    public function testAnUnknownShortLinkIsNotFound(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

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
     * The mailbox is a mandatory disclosure, so it has to be readable—and it
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
        self::assertStringNotContainsString('/s/', $inhalt);
    }

    public function testRobotsKeepsTheRedirectRoutesOutOfCrawlerCorpora(): void
    {
        $client = static::createClient();
        $client->request('GET', '/robots.txt');

        $inhalt = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Sitemap:', $inhalt);
        self::assertStringContainsString('/kontakt-per-email', $inhalt);
        self::assertStringNotContainsString('/s/', $inhalt);
    }
}
