<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ContactRequest;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DefaultController extends AbstractController
{
    /**
     * Prefix => campaign.
     *
     * @var array<string, string>
     */
    public const PRINT_CAMPAIGNS = [
        'ad' => 'advertisement',
        'bc' => 'business-card',
        'fl' => 'flyer',
    ];

    /**
     * Prefix => codes in print.
     *
     * @var array<string, list<string>>
     */
    public const PRINT_EDITIONS = [
        'ad' => ['ea2610'],
        'bc' => ['2610'],
        'fl' => ['2610'],
    ];

    /**
     * Medium => the source it is counted under.
     *
     * @var array<string, string>
     */
    public const PRINT_SOURCES = [
        'ea' => 'erftstadt-aktuell',
    ];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ValidatorInterface $validator,
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%env(CONTACT_TO)%')]
        private readonly string $contactTo,
        #[Autowire('%env(CONTACT_FROM)%')]
        private readonly string $contactFrom,
        #[Autowire(service: 'limiter.contact_form')]
        private readonly RateLimiterFactoryInterface $contactFormLimiter,
    ) {
    }

    #[Route('/', name: 'app_homepage', methods: ['GET'])]
    public function homepage(): Response
    {
        return $this->renderHomepage();
    }

    #[Route('/referenzen/{slug}', name: 'app_case_study', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'])]
    public function caseStudy(string $slug): Response
    {
        $reference = null;
        foreach ($this->loadContent('references') as $entry) {
            if (($entry['slug'] ?? null) === $slug && ($entry['hatDetailseite'] ?? false)) {
                $reference = $entry;
                break;
            }
        }

        if ($reference === null) {
            throw $this->createNotFoundException(sprintf('Reference "%s" not found.', $slug));
        }

        return $this->render('content/case-study.html.twig', ['reference' => $reference]);
    }

    #[Route('/kontakt', name: 'app_contact', methods: ['POST'])]
    public function contact(Request $request): Response
    {
        $timestampState = $this->timestampState($request);

        // A clear bot signal gets a fake success, so the bot learns nothing.
        $honeypot = trim((string) $request->request->get('website', ''));
        if ($honeypot !== '' || $timestampState === 'invalid' || $timestampState === 'too_fast') {
            $this->addFlash('contact_success', true);

            return $this->redirect($this->generateUrl('app_homepage', ['_fragment' => 'kontakt']));
        }

        $data = new ContactRequest();
        $data->name = trim((string) $request->request->get('name', ''));
        $data->email = trim((string) $request->request->get('email', ''));
        $data->company = trim((string) $request->request->get('company', ''));
        $data->phone = trim((string) $request->request->get('phone', ''));
        $data->message = trim((string) $request->request->get('message', ''));

        // The constraints are written in order of relevance; the first one wins.
        $errors = [];
        foreach ($this->validator->validate($data) as $violation) {
            $errors[$violation->getPropertyPath()] ??= $violation->getMessage();
        }

        if ($this->isCsrfTokenValid('contact', (string) $request->request->get('_token')) === false) {
            $errors['form'] = 'Ihre Sitzung ist abgelaufen. Bitte senden Sie das Formular erneut ab.';
        }

        // A stale signature is a real visitor whose form sat open too long.
        if ($timestampState === 'expired') {
            $errors['form'] = 'Das Formular war zu lange geöffnet. Bitte senden Sie das Formular erneut ab.';
        }

        if ($errors !== []) {
            return $this->renderContactErrors($request, $errors);
        }

        // Per IP, so a scraped token cannot be replayed into a flood.

        if ($this->contactFormLimiter->create($request->getClientIp() ?? 'anonymous')->consume(1)->isAccepted() === false) {
            return $this->renderContactErrors($request, [
                'form' => 'Es sind zu viele Nachrichten eingegangen. Bitte versuchen Sie es später noch einmal.',
            ]);
        }

        try {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->from(new Address($this->contactFrom, 'krausgebaut von Marcel Kraus'))
                    ->to($this->contactTo)
                    ->replyTo(new Address($data->email, $data->name))
                    ->subject(sprintf('Nachricht von %s', $data->name))
                    ->textTemplate('content/contact.txt.twig')
                    ->context([
                        'company' => $data->company,
                        'emailAddress' => $data->email,
                        'message' => $data->message,
                        'name' => $data->name,
                        'phone' => $data->phone,
                    ])
            );
        } catch (TransportExceptionInterface) {
            return $this->renderContactErrors($request, [
                'form' => 'Die Nachricht konnte gerade nicht zugestellt werden. Bitte versuchen Sie es später noch einmal oder schreiben Sie mir per E-Mail.',
            ]);
        }

        $this->addFlash('contact_success', true);

        return $this->redirect($this->generateUrl('app_homepage', ['_fragment' => 'kontakt']));
    }

    #[Route('/kontakt-per-email', name: 'app_contact_email', methods: ['GET'])]
    public function contactByEmail(): Response
    {
        return $this->redirect('mailto:' . $this->getParameter('app.contact_email_address'));
    }

    #[Route('/kontakt-per-whats-app', name: 'app_contact_whats_app', methods: ['GET'])]
    public function contactByWhatsApp(): Response
    {
        return $this->redirect($this->getParameter('app.whats_app_url'));
    }

    #[Route('/bewerten', name: 'app_review', methods: ['GET'])]
    public function review(): Response
    {
        return $this->redirect($this->getParameter('app.google_review_url'));
    }

    // 302, because the target of something already printed must be free to change.
    #[Route('/s/{slug}', name: 'app_short_link', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'])]
    public function shortLink(string $slug): Response
    {
        $query = $this->countedAs($slug);

        if ($query === null) {
            throw $this->createNotFoundException(sprintf('Short link "%s" not found.', $slug));
        }

        return $this->redirectToRoute('app_homepage', $query);
    }

    #[Route('/impressum', name: 'app_imprint', methods: ['GET'])]
    public function imprint(): Response
    {
        return $this->render('content/imprint.html.twig');
    }

    #[Route('/datenschutz', name: 'app_data_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('content/data-privacy.html.twig');
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(): Response
    {
        $sitemap = $this->generateUrl('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $disallowed = [
            '/kontakt-per-email',
            '/kontakt-per-whats-app',
        ];

        $rules = "User-agent: *\n";
        foreach ($disallowed as $route) {
            $rules .= "Disallow: {$route}\n";
        }

        return new Response(
            $rules . "\nSitemap: {$sitemap}\n",
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        $locations = [
            [$this->generateUrl('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL), '1.0'],
        ];

        foreach ($this->loadContent('references') as $entry) {
            if ($entry['hatDetailseite'] ?? false) {
                $locations[] = [
                    $this->generateUrl('app_case_study', ['slug' => $entry['slug']], UrlGeneratorInterface::ABSOLUTE_URL),
                    '0.7',
                ];
            }
        }

        $urls = '';
        foreach ($locations as [$loc, $priority]) {
            $urls .= "    <url>\n        <loc>{$loc}</loc>\n        <changefreq>monthly</changefreq>\n        <priority>{$priority}</priority>\n    </url>\n";
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            . $urls
            . "</urlset>\n";

        return new Response($xml, Response::HTTP_OK, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * @param array<string, mixed> $formState
     */
    private function renderHomepage(array $formState = [], int $status = Response::HTTP_OK): Response
    {
        $timestamp = (string) time();

        $response = $this->render('content/homepage.html.twig', array_merge([
            'services' => $this->loadContent('services'),
            'references' => $this->loadContent('references'),
            'testimonials' => $this->loadContent('testimonials'),
            'contact_timestamp' => $timestamp,
            'contact_timestamp_signature' => $this->signTimestamp($timestamp),
            'contact_errors' => [],
            'contact_old' => [],
            'contact_focus' => null,
        ], $formState));
        $response->setStatusCode($status);

        return $response;
    }

    /**
     * @param array<string, string> $errors
     */
    private function renderContactErrors(Request $request, array $errors): Response
    {
        $focus = null;
        foreach (array_keys($errors) as $field) {
            if ($field !== 'form') {
                $focus = $field;
                break;
            }
        }

        $formState = [
            'contact_errors' => $errors,
            'contact_old' => $request->request->all(),
            'contact_focus' => $focus,
        ];

        // A fresh timestamp would make a quick fix-and-resend look too fast.
        $submittedTimestamp = (string) $request->request->get('ts', '');
        $submittedSignature = (string) $request->request->get('ts_sig', '');
        if ($submittedTimestamp !== ''
            && hash_equals($this->signTimestamp($submittedTimestamp), $submittedSignature)
            && time() - (int) $submittedTimestamp <= 7200
        ) {
            $formState['contact_timestamp'] = $submittedTimestamp;
            $formState['contact_timestamp_signature'] = $submittedSignature;
        }

        return $this->renderHomepage($formState, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * A missing or malformed file reads as an empty list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadContent(string $name): array
    {
        $path = $this->projectDir . '/config/content/' . $name . '.json';
        if (is_file($path) === false) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function signTimestamp(string $timestamp): string
    {
        return hash_hmac('sha256', $timestamp, $this->appSecret);
    }

    private function timestampState(Request $request): string
    {
        $timestamp = (string) $request->request->get('ts', '');
        $signature = (string) $request->request->get('ts_sig', '');

        if ($timestamp === '' || hash_equals($this->signTimestamp($timestamp), $signature) === false) {
            return 'invalid';
        }

        $elapsed = time() - (int) $timestamp;

        if ($elapsed < 3) {
            return 'too_fast';
        }

        if ($elapsed > 7200) {
            return 'expired';
        }

        return 'valid';
    }

    /**
     * @return array<string, string>|null
     */
    private function countedAs(string $slug): ?array
    {
        $parts = explode('-', $slug);

        if (\count($parts) !== 2 || !isset(self::PRINT_CAMPAIGNS[$parts[0]])) {
            return null;
        }

        [$prefix, $code] = $parts;
        $query = ['mtm_campaign' => self::PRINT_CAMPAIGNS[$prefix], 'mtm_medium' => 'print'];

        if (!\in_array($code, self::PRINT_EDITIONS[$prefix] ?? [], true)) {
            return null;
        }

        if ($prefix === 'ad') {
            $source = self::PRINT_SOURCES[substr($code, 0, 2)] ?? null;

            return $source === null ? null : $query + ['mtm_source' => $source, 'mtm_kwd' => substr($code, 2)];
        }

        return $query + ['mtm_kwd' => $code];
    }
}
