<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        #[Autowire(service: 'limiter.registration_limiter')] RateLimiterFactory $registrationLimiter
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = null;
        $email = '';

        if ($request->isMethod('POST')) {
            $clientIp = $request->getClientIp() ?: '127.0.0.1';
            $limiter = $registrationLimiter->create($clientIp);
            $limit = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                $retryAfter = $limit->getRetryAfter();
                $diffSec = $retryAfter ? ($retryAfter->getTimestamp() - time()) : 900;
                $minutes = max(1, (int) ceil($diffSec / 60));
                $error = sprintf('Przekroczono limit prób rejestracji (maks. 3 na 15 min). Spróbuj ponownie za ok. %d min.', $minutes);
            } else {
                $email = trim($request->request->getString('email'));
                $password = $request->request->getString('password');
                $passwordConfirm = $request->request->getString('password_confirm');

                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Wprowadź poprawny adres e-mail.';
                } elseif (strlen($password) < 6) {
                    $error = 'Hasło musi mieć co najmniej 6 znaków.';
                } elseif ($password !== $passwordConfirm) {
                    $error = 'Wprowadzone hasła nie są identyczne.';
                } elseif ($userRepository->findOneBy(['email' => $email]) !== null) {
                    $error = 'Konto z tym adresem e-mail już istnieje.';
                } else {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setRoles(['ROLE_USER']);
                    $hashed = $passwordHasher->hashPassword($user, $password);
                    $user->setPassword($hashed);

                    $em->persist($user);
                    $em->flush();

                    $this->addFlash('success', 'Konto zostało pomyślnie utworzone! Zaloguj się poniżej.');
                    return $this->redirectToRoute('app_login');
                }
            }
        }

        return $this->render('security/register.html.twig', [
            'last_email' => $email,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method should not be reached; Symfony handles logout automatically.');
    }
}