<?php

namespace App\Tests\Form;

use App\Entity\User;
use App\Enum\OfficeFunction;
use App\Form\UserType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

#[AllowMockObjectsWithoutExpectations]
class UserTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [
            new \Symfony\Component\Form\Extension\Validator\ValidatorExtension(
                Validation::createValidator()
            ),
        ];
    }

    public function testFormStructure(): void
    {
        $form = $this->factory->create(UserType::class, new User());
        
        // Vérifie que tous les champs attendus sont présents
        $this->assertTrue($form->has('email'));
        $this->assertTrue($form->has('firstName'));
        $this->assertTrue($form->has('lastName'));
        $this->assertTrue($form->has('officeFunction'));
        $this->assertTrue($form->has('isAdmin'));
        $this->assertTrue($form->has('membershipValidUntil'));
        $this->assertTrue($form->has('plainPassword'));
    }

    public function testSubmitValidDataForNewUser(): void
    {
        $formData = [
            'email' => 'test@example.com',
            'firstName' => 'Jean',
            'lastName' => 'Dupont',
            'officeFunction' => OfficeFunction::President->value,
            'isAdmin' => true,
            'membershipValidUntil' => '2025-12-31',
            'plainPassword' => 'password123',
        ];

        $form = $this->factory->create(UserType::class, new User(), [
            'is_new' => true,
            'current_roles' => [],
        ]);
        
        $form->submit($formData);

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $fieldName = method_exists($error->getOrigin(), 'getName') ? $error->getOrigin()->getName() : 'root';
                $errors[] = $fieldName . ': ' . $error->getMessage();
            }
            $this->fail('Form has errors: ' . implode('\n', $errors));
        }

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());

        $user = $form->getData();
        
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('Jean', $user->getFirstName());
        $this->assertEquals('Dupont', $user->getLastName());
        $this->assertEquals(OfficeFunction::President, $user->getOfficeFunction());
    }

    public function testSubmitValidDataForExistingUser(): void
    {
        $user = new User();
        $user->setEmail('existing@example.com');
        $user->setFirstName('Pierre');
        $user->setLastName('Martin');
        $user->setOfficeFunction(OfficeFunction::Treasurer);
        $user->setRoles(['ROLE_ADMIN']);

        $formData = [
            'email' => 'existing@example.com',
            'firstName' => 'Pierre',
            'lastName' => 'Martin',
            'officeFunction' => OfficeFunction::Treasurer->value,
            'isAdmin' => true,
            'membershipValidUntil' => '2025-12-31',
            'plainPassword' => '', // Laisser vide pour ne pas changer
        ];

        $form = $this->factory->create(UserType::class, $user, [
            'is_new' => false,
            'current_roles' => ['ROLE_ADMIN'],
        ]);
        
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        
        $submittedUser = $form->getData();
        
        $this->assertEquals('existing@example.com', $submittedUser->getEmail());
        $this->assertEquals('Pierre', $submittedUser->getFirstName());
        $this->assertEquals('Martin', $submittedUser->getLastName());
        $this->assertEquals(OfficeFunction::Treasurer, $submittedUser->getOfficeFunction());
    }

    public function testFirstNameConstraints(): void
    {
        // Test prénom vide
        $formData = [
            'email' => 'test@example.com',
            'firstName' => '',
            'lastName' => 'Dupont',
            'officeFunction' => OfficeFunction::None->value,
            'isAdmin' => false,
            'membershipValidUntil' => null,
            'plainPassword' => 'password123',
        ];

        $form = $this->factory->create(UserType::class, new User(), [
            'is_new' => true,
            'current_roles' => [],
        ]);
        
        $form->submit($formData);
        
        $this->assertFalse($form->isValid());
        $errors = $form->get('firstName')->getErrors();
        $this->assertNotEmpty($errors);
        
        $errorMessage = (string) $errors->current()->getMessage();
        $this->assertStringContainsString('obligatoire', $errorMessage);

        // Test prénom trop long (51 caractères)
        $formData['firstName'] = str_repeat('a', 51);
        
        $form = $this->factory->create(UserType::class, new User(), [
            'is_new' => true,
            'current_roles' => [],
        ]);
        
        $form->submit($formData);
        
        $this->assertFalse($form->isValid());
        $errors = $form->get('firstName')->getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testLastNameConstraints(): void
    {
        // Test nom vide
        $formData = [
            'email' => 'test@example.com',
            'firstName' => 'Jean',
            'lastName' => '',
            'officeFunction' => OfficeFunction::None->value,
            'isAdmin' => false,
            'membershipValidUntil' => null,
            'plainPassword' => 'password123',
        ];

        $form = $this->factory->create(UserType::class, new User(), [
            'is_new' => true,
            'current_roles' => [],
        ]);
        
        $form->submit($formData);
        
        $this->assertFalse($form->isValid());
        $errors = $form->get('lastName')->getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testPasswordConstraintsForNewUser(): void
    {
        // Test mot de passe vide
        $formData = [
            'email' => 'test@example.com',
            'firstName' => 'Jean',
            'lastName' => 'Dupont',
            'officeFunction' => OfficeFunction::None->value,
            'isAdmin' => false,
            'membershipValidUntil' => null,
            'plainPassword' => '',
        ];

        $form = $this->factory->create(UserType::class, new User(), [
            'is_new' => true,
            'current_roles' => [],
        ]);
        
        $form->submit($formData);
        
        $this->assertFalse($form->isValid());
        $errors = $form->get('plainPassword')->getErrors();
        $this->assertNotEmpty($errors);

        // Test mot de passe trop court
        $formData['plainPassword'] = 'short';
        
        $form = $this->factory->create(UserType::class, new User(), [
            'is_new' => true,
            'current_roles' => [],
        ]);
        
        $form->submit($formData);
        
        $this->assertFalse($form->isValid());
        $errors = $form->get('plainPassword')->getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testOfficeFunctionChoices(): void
    {
        $form = $this->factory->create(UserType::class, new User());
        
        $officeFunctionField = $form->get('officeFunction');
        $choices = $officeFunctionField->getConfig()->getOption('choices');
        
        $this->assertCount(5, $choices);
        $this->assertContains(OfficeFunction::President, $choices);
        $this->assertContains(OfficeFunction::Treasurer, $choices);
        $this->assertContains(OfficeFunction::Secretary, $choices);
        $this->assertContains(OfficeFunction::ActiveMember, $choices);
        $this->assertContains(OfficeFunction::None, $choices);
    }

    public function testIsAdminFieldWithRoles(): void
    {
        // Test avec ROLE_ADMIN
        $form = $this->factory->create(UserType::class, new User(), [
            'is_new' => false,
            'current_roles' => ['ROLE_ADMIN'],
        ]);
        
        $isAdminField = $form->get('isAdmin');
        $this->assertTrue($isAdminField->getData());

        // Test sans ROLE_ADMIN
        $form = $this->factory->create(UserType::class, new User(), [
            'is_new' => false,
            'current_roles' => ['ROLE_MEMBER'],
        ]);
        
        $isAdminField = $form->get('isAdmin');
        $this->assertFalse($isAdminField->getData());
    }

    public function testMembershipValidUntilField(): void
    {
        $date = new \DateTime('2025-12-31');
        
        $formData = [
            'email' => 'test@example.com',
            'firstName' => 'Jean',
            'lastName' => 'Dupont',
            'officeFunction' => OfficeFunction::None->value,
            'isAdmin' => false,
            'membershipValidUntil' => '2025-12-31',
            'plainPassword' => 'password123',
        ];

        $form = $this->factory->create(UserType::class, new User(), [
            'is_new' => true,
            'current_roles' => [],
        ]);
        
        $form->submit($formData);

        $user = $form->getData();
        
        $this->assertNotNull($user->getMembershipValidUntil());
        $this->assertEquals('2025-12-31', $user->getMembershipValidUntil()->format('Y-m-d'));
    }
}
