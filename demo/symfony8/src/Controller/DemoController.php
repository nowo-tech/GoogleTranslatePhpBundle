<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\GoogleTranslatePhpBundle\Translator\WorkerSafeGoogleTranslate;
use Stichoza\GoogleTranslate\Exceptions\RateLimitException;
use Stichoza\GoogleTranslate\Exceptions\TranslationRequestException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class DemoController extends AbstractController
{
    public function __construct(
        private readonly WorkerSafeGoogleTranslate $translator,
    ) {
    }

    #[Route('/', name: 'demo_home', methods: ['GET', 'POST'])]
    public function home(Request $request): Response
    {
        $input      = trim((string) $request->request->get('text', 'Hello world'));
        $translated = null;
        $error      = null;
        $detected   = null;

        if ($request->isMethod('POST') && $input !== '') {
            try {
                $translated = $this->translator->translate($input);
                $detected   = $this->translator->getLastDetectedSource();
            } catch (RateLimitException $e) {
                $error = 'Rate limited by Google Translate: ' . $e->getMessage();
            } catch (TranslationRequestException $e) {
                $error = 'Translation request failed (network/timeout): ' . $e->getMessage();
            } catch (Throwable $e) {
                $error = 'Unexpected error: ' . $e->getMessage();
            }
        }

        return $this->render('demo/home.html.twig', [
            'version_badge'   => 'Symfony 8.1',
            'input'           => $input,
            'translated'      => $translated,
            'detected'        => $detected,
            'error'           => $error,
            'timeout'         => 10.0,
            'connect_timeout' => 5.0,
        ]);
    }
}
