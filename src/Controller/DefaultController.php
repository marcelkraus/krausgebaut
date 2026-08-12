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
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DefaultController extends AbstractController
{
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
        private readonly RateLimiterFactory $contactFormLimiter,
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
            throw $this->createNotFoundException('Referenz nicht gefunden.');
        }

        return $this->render('default/case-study.html.twig', ['reference' => $reference]);
    }

    #[Route('/kontakt', name: 'app_contact', methods: ['POST'])]
    public function contact(Request $request): Response
    {
        $timestampState = $this->timestampState($request);

        // Silent drop only for clear bot signals — a filled honeypot, a
        // missing/tampered signature, or an inhumanly fast submission. These
        // get a fake "success" so bots learn nothing; nothing is sent.
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

        // Keep the first violation per field: the constraints are written in
        // order of relevance, so an empty message must report NotBlank rather
        // than the minimum-length rule that fires alongside it.
        $errors = [];
        foreach ($this->validator->validate($data) as $violation) {
            $errors[$violation->getPropertyPath()] ??= $violation->getMessage();
        }

        if ($this->isCsrfTokenValid('contact', (string) $request->request->get('_token')) === false) {
            $errors['form'] = 'Ihre Sitzung ist abgelaufen. Bitte senden Sie das Formular erneut ab.';
        }

        // A valid but stale signature is a real user whose form sat open too
        // long — never silently drop it, ask them to resend instead.
        if ($timestampState === 'expired') {
            $errors['form'] = 'Das Formular war zu lange geöffnet. Bitte senden Sie das Formular erneut ab.';
        }

        if ($errors !== []) {
            return $this->renderContactErrors($request, $errors);
        }

        // Throttle per IP so a scraped token cannot be replayed into a flood.
        if ($this->contactFormLimiter->create($request->getClientIp() ?? 'anonymous')->consume(1)->isAccepted() === false) {
            return $this->renderContactErrors($request, [
                'form' => 'Es sind zu viele Nachrichten eingegangen. Bitte versuchen Sie es später noch einmal.',
            ]);
        }

        // A transport failure must not cost the enquiry and must not hand a
        // visitor a bare error page: the sendmail DSN has two documented ways
        // of being wrong on this host, and Apache replaces the Symfony error
        // page with its own.
        try {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->from(new Address($this->contactFrom, 'krausgebaut von Marcel Kraus'))
                    ->to($this->contactTo)
                    ->replyTo(new Address($data->email, $data->name))
                    ->subject(sprintf('Nachricht von %s', $data->name))
                    ->textTemplate('default/contact.txt.twig')
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

    /**
     * The address never appears in the markup — the link points at a route
     * and the route redirects. That keeps the mailto out of a shape that is
     * trivial to scrape, which is the same reason the legal pages spell the
     * address out in words.
     */
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
        return $this->redirect($_SERVER['GOOGLE_REVIEW_URL']);
    }

    #[Route('/impressum', name: 'app_imprint', methods: ['GET'])]
    public function imprint(): Response
    {
        return $this->render('default/imprint.html.twig');
    }

    #[Route('/datenschutz', name: 'app_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('default/data-privacy.html.twig');
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(): Response
    {
        $sitemap = $this->generateUrl('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        // The redirect routes carry no document, only a Location header — and
        // for the two contact routes that header holds the address. A
        // well-behaved crawler follows it and takes the address into its
        // corpus, and those corpora are where address lists come from.
        // Harvesters ignore robots.txt, but the corpora do not.
        $disallowed = [
            '/bewerten',
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
        // The homepage plus every reference that has its own case-study page.
        // The legal pages are noindex and stay out.
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
     * Renders the homepage, always seeding a fresh anti-spam timestamp and
     * the JSON-driven content (services, references, testimonials).
     *
     * @param array<string, mixed> $formState
     */
    private function renderHomepage(array $formState = [], int $status = Response::HTTP_OK): Response
    {
        $timestamp = (string) time();

        $response = $this->render('default/homepage.html.twig', array_merge([
            'services' => $this->loadContent('services'),
            'references' => $this->loadContent('references'),
            'testimonials' => $this->loadContent('testimonials'),
            'contact_ts' => $timestamp,
            'contact_ts_sig' => $this->signTimestamp($timestamp),
            'contact_errors' => [],
            'contact_old' => [],
            'contact_focus' => null,
        ], $formState));
        $response->setStatusCode($status);

        return $response;
    }

    /**
     * Re-renders the homepage with contact-form errors, the submitted values
     * and the first errored field name so the client can focus it.
     *
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

        // Reuse the visitor's still-valid timestamp on re-render so a quick
        // fix-and-resend is not misclassified as a bot ("too_fast"). Only seed
        // a fresh timestamp when none is reusable (missing, tampered, expired).
        $submittedTimestamp = (string) $request->request->get('ts', '');
        $submittedSignature = (string) $request->request->get('ts_sig', '');
        if ($submittedTimestamp !== ''
            && hash_equals($this->signTimestamp($submittedTimestamp), $submittedSignature)
            && time() - (int) $submittedTimestamp <= 7200
        ) {
            $formState['contact_ts'] = $submittedTimestamp;
            $formState['contact_ts_sig'] = $submittedSignature;
        }

        return $this->renderHomepage($formState, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Loads a content file from config/content and decodes it to an array.
     * Missing or malformed files degrade to an empty list.
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

    /**
     * Classifies the signed anti-spam timestamp: 'invalid' (missing or
     * tampered signature), 'too_fast' (submitted within 3 s), 'expired'
     * (older than two hours) or 'valid'.
     */
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

}
