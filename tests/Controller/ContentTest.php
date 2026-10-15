<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
        // File order is rendered order; the featured service sits last on purpose.
        foreach (['services.json', 'references.json'] as $datei) {
            $eintraege = array_values(array_filter(
                self::inhalt($datei),
                static fn (array $eintrag): bool => ($eintrag['feature'] ?? false) === false
            ));
            $titel = array_column($eintraege, 'title');
            $sortiert = $titel;
            usort($sortiert, static fn (string $a, string $b): int => strcmp(
                self::sortierschluessel($a),
                self::sortierschluessel($b)
            ));

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

        // An empty file means no section, not an empty one.
        self::assertSelectorNotExists('blockquote');
    }

    public function testACaseStudyRendersTheChaptersFromItsEntry(): void
    {
        $client = static::createClient();
        $client->request('GET', '/referenzen/meetmyrc');

        self::assertSelectorTextContains('body', 'Ausgangslage');
        self::assertSelectorTextContains('body', 'Lösung');
        self::assertSelectorTextContains('body', 'Ergebnis');
    }

    /**
     * German collation by hand: strcoll needs a de_DE locale a CI runner lacks.
     */
    private static function sortierschluessel(string $titel): string
    {
        return strtolower(strtr($titel, [
            'Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U',
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
        ]));
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
