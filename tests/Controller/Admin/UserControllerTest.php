<?php

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use App\Enum\OfficeFunction;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserControllerTest extends WebTestCase
{
    
    public function testIndex(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $em = $container->get('doctrine')->getManager();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get('security.user_password_hasher');

        $admin = (new User())->setEmail('admin-test@asso.fr');
        $admin->setFirstName('Admin');
        $admin->setLastName('Test');
        $admin->setPassword($hasher->hashPassword($admin, 'password'));
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setOfficeFunction(OfficeFunction::President);
        $admin->setRoles(['ROLE_ADMIN']);
        $em->persist($admin);
        $em->flush();

        $client->loginUser($admin);
        $client->request('GET', '/admin/user/');

        self::assertResponseIsSuccessful();
    }
}