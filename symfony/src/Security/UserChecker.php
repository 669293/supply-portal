<?php

namespace App\Security;

use App\Entity\Users as AppUser;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(EntityManagerInterface $manager){
        $this->manager = $manager;
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof AppUser) {
            return;
        }

        if (!$user->getActive()) {
            // Пользователь не автивен
            throw new CustomUserMessageAccountStatusException('Недействительные аутентификационные данные.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof AppUser) {
            return;
        }
        
        $user->setLastLogin(new \DateTime('now'));
        
        $this->manager->persist($user);
        $this->manager->flush();
    }
}