<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The JSON content files reach the page, and the rules the files are kept
 * under hold — alphabetical order, and a testimonials section that stays
 * hidden until there is something real to show.
 */
final class ContentTest extends WebTestCase
{
    public function testEveryServiceIsRendered(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        $inhalt = (string) $client->getResponse()->getContent();

        foreach (self::inhalt('services.json') as $eintrag) {
            self::assertStringContainsString(
                htmlspecialchars($eintrag['title'], ENT_QUOTES),
                $inhalt,
                sprintf('The service "%s" reaches the page', $eintrag['title'])
            );
        }
    }

    public function testEveryReferenceIsRendered(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        $inhalt = (string) $client->getResponse()->getContent();

        foreach (self::inhalt('references.json') as $eintrag) {
            self::assertStringContainsString(
                htmlspecialchars($eintrag['title'], ENT_QUOTES),
                $inhalt,
                sprintf('The reference "%s" reaches the page', $eintrag['title'])
            );
        }
    }

    public function testServicesAndReferencesAreKeptAlphabetical(): void
    {
        // The file order is the rendered order, so the sorting rule is not a
        // matter of taste here — it decides what a visitor sees first. The
        // featured service is the documented exception: it leaves the tile
        // grid for a panel of its own and sits last in the file.
        foreach (['services.json', 'references.json'] as $datei) {
            $eintraege = array_values(array_filter(
                self::inhalt($datei),
                static fn (array $eintrag): bool => ($eintrag['feature'] ?? false) === false
            ));
            $titel = array_column($eintraege, 'title');
            $sortiert = $titel;
            setlocale(LC_COLLATE, 'de_DE.UTF-8');
            usort($sortiert, static fn (string $a, string $b): int => strcoll($a, $b));

            self::assertSame($sortiert, $titel, sprintf('%s is ordered by title', $datei));
        }
    }

    public function testTheFeaturedServiceSitsLastAndAloneInItsPanel(): void
    {
        $hervorgehoben = array_filter(
            self::inhalt('services.json'),
            static fn (array $eintrag): bool => ($eintrag['feature'] ?? false) === true
        );

        self::assertLessThanOrEqual(1, \count($hervorgehoben), 'At most one service is featured');

        if ($hervorgehoben === []) {
            return;
        }

        $alle = self::inhalt('services.json');
        self::assertTrue(
            (bool) (end($alle)['feature'] ?? false),
            'The featured service is the last entry in the file'
        );
    }

    public function testTheTestimonialsSectionStaysHiddenWhileThereAreNone(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $vorhanden = self::inhalt('testimonials.json') !== [];

        if ($vorhanden) {
            self::assertSelectorExists('blockquote');

            return;
        }

        // Never publish placeholder quotes: an empty file means the section
        // does not exist, not that it renders empty.
        self::assertSelectorNotExists('blockquote');
    }

    public function testACaseStudyRendersTheChaptersFromItsEntry(): void
    {
        $client = static::createClient();
        $client->request('GET', '/referenzen/meetmyrc');

        // Each chapter carries a heading of its own, so the body of a case
        // study appears in the heading outline instead of hanging under the h1.
        self::assertSelectorTextContains('body', 'Ausgangslage');
        self::assertSelectorTextContains('body', 'Lösung');
        self::assertSelectorTextContains('body', 'Ergebnis');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function inhalt(string $datei): array
    {
        $pfad = \dirname(__DIR__, 2) . '/config/content/' . $datei;
        $rohdaten = json_decode((string) file_get_contents($pfad), true);

        return \is_array($rohdaten) ? $rohdaten : [];
    }
}
