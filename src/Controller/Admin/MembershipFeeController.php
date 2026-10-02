<?php

namespace App\Controller\Admin;

use App\Form\MembershipFeeType;
use App\Repository\MembershipFeeRepository;
use App\Service\MembershipFeeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ENTITY_DELETE')] // Président uniquement (acte financier + irréversible)
#[Route('/admin/membership/fee', name: 'app_admin_membership_fee_')]
class MembershipFeeController extends AbstractController
{
    public function __construct(
        private MembershipFeeRepository $feeRepo,
        private MembershipFeeService $feeService,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $currentFee = $this->feeRepo->findCurrentFee();
        $history = $this->feeRepo->findBy([], ['effectiveFrom' => 'DESC', 'updatedAt' => 'DESC']);

        return $this->render('admin/membership/fee/index.html.twig', [
            'currentFee' => $currentFee,
            'history' => $history,
        ]);
    }

    #[Route('/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        try {
            $currentAmount = $this->feeService->getCurrentAmountInEuros();
        } catch (\RuntimeException $e) {
            $currentAmount = 15.00; // Valeur par défaut pour le formulaire uniquement
        }

        $form = $this->createForm(MembershipFeeType::class, null, [
            'default_amount' => $currentAmount,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $amountCents = (int) ($data['amount'] * 100); // Convertir en centimes

            // Convertir DateTime en DateTimeImmutable si présent
            $effectiveFrom = $data['effectiveFrom'] ? \DateTimeImmutable::createFromMutable($data['effectiveFrom']) : null;

            $this->feeService->updateFee(
                $amountCents,
                $effectiveFrom,
                $data['notes']
            );

            $this->addFlash('success', 'Tarif mis à jour avec succès !');
            return $this->redirectToRoute('app_admin_membership_fee_index');
        }

        return $this->render('admin/membership/fee/edit.html.twig', [
            'form' => $form->createView(),
            'currentFee' => $this->feeRepo->findCurrentFee(),
        ]);
    }
}
