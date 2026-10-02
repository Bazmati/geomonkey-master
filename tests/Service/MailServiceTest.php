<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\MailService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MailServiceTest extends TestCase
{
    private MailerInterface $mailer;
    private UrlGeneratorInterface $urlGenerator;
    private MailService $mailService;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        
        $this->mailService = new MailService(
            $this->mailer,
            $this->urlGenerator,
            'contact@geomonkey.fr',
            'https://whatsapp.com/invite/group',
            'Assurance RC et individuelle accident incluse'
        );
    }

    public function testSendRegistrationConfirmationForVisitor(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@email.com');
        $user->method('getFirstName')->willReturn('John');
        $user->method('needsBureauValidation')->willReturn(false);

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getSubject() === 'Ton inscription à GéoMonkey est confirmée !';
            }));

        $this->mailService->sendRegistrationConfirmation($user);
    }

    public function testSendRegistrationConfirmationForActiveMember(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@email.com');
        $user->method('getFirstName')->willReturn('John');
        $user->method('needsBureauValidation')->willReturn(true);

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getSubject() === 'Ta demande d\'adhésion à GéoMonkey est reçue !';
            }));

        $this->mailService->sendRegistrationConfirmation($user);
    }

    public function testSendMembershipRequestAlert(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('newmember@email.com');
        $user->method('getLastName')->willReturn('Doe');
        $user->method('getFirstName')->willReturn('John');

        $this->urlGenerator->method('generate')
            ->with('app_admin_membership_requests', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://localhost:8000/admin/membership/requests');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getTo()[0]->getAddress() === 'contact@geomonkey.fr';
            }));

        $this->mailService->sendMembershipRequestAlert($user);
    }

    public function testSendValidationEmail(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@email.com');
        $user->method('getFirstName')->willReturn('John');

        $paymentUrl = 'https://checkout.stripe.com/session_id';

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getSubject() === 'Ta demande d\'adhésion est acceptée ✅';
            }));

        $this->mailService->sendValidationEmail($user, $paymentUrl);
    }

    public function testSendMembershipConfirmedEmail(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@email.com');
        $user->method('getFirstName')->willReturn('John');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getSubject() === 'Bienvenue officiellement chez GéoMonkey ! 🎉';
            }));

        $this->mailService->sendMembershipConfirmedEmail($user);
    }

    public function testSendExpirationReminderEmail(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@email.com');
        $user->method('getFirstName')->willReturn('John');

        $daysUntilExpiry = 30;

        $this->urlGenerator->method('generate')
            ->with('app_membership_register', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://localhost:8000/inscription');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use ($daysUntilExpiry) {
                return strpos($email->getSubject(), (string) $daysUntilExpiry) !== false;
            }));

        $this->mailService->sendExpirationReminderEmail($user, $daysUntilExpiry);
    }

    public function testSetAssuranceDetails(): void
    {
        $details = 'Nouveaux détails assurance';
        $this->mailService->setAssuranceDetails($details);
        
        // Utiliser la réflexion pour vérifier la propriété privée
        $reflection = new \ReflectionClass($this->mailService);
        $property = $reflection->getProperty('assuranceDetails');
        $property->setAccessible(true);
        
        $this->assertEquals($details, $property->getValue($this->mailService));
    }
}
