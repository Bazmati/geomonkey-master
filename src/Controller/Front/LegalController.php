<?php

namespace App\Controller\Front;

use App\Service\MembershipFeeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/legal')]
class LegalController extends AbstractController
{
    public function __construct(
        private MembershipFeeService $feeService
    ) {}

    #[Route('/cgu', name: 'app_cgu')]
    public function cgu(): Response
    {
        try {
            $feeAmount = $this->feeService->getCurrentAmountInEuros();
        } catch (\RuntimeException $e) {
            $feeAmount = 15.00; // Valeur par défaut
        }

        return $this->render('front/legal/cgu.html.twig', [
            'feeAmount' => $feeAmount,
        ]);
    }

    #[Route('/confidentialite', name: 'app_privacy')]
    public function privacy(): Response
    {
        return $this->render('front/legal/privacy.html.twig');
    }
}
