<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\TranslateFormType;
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
        $form = $this->createForm(TranslateFormType::class, ['text' => 'Hello world'], [
            'action' => $this->generateUrl('demo_home'),
        ]);
        $form->handleRequest($request);
        /** @var array{text?: string|null} $data */
        $data       = $form->getData();
        $input      = trim((string) ($data['text'] ?? ''));
        $translated = null;
        $error      = null;
        $detected   = null;

        if ($form->isSubmitted() && $form->isValid() && $input !== '') {
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
            'form'            => $form,
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
