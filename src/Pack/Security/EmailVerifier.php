<?php

namespace App\Pack\Security;

use App\Entity\User;
use App\Pack\Security\Exception\EmailAlreadyVerifiedException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class EmailVerifier
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private EntityManager $entityManager,
        private UserRepository $userRepository,
    ) {}

    public function getSignedUrl(User $user, string $route = 'verify_email'): string
    {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $route,
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()]
        );

        return $signatureComponents->getSignedUrl();
    }

    public function getUnverifiedUser(Request $request): User
    {
        $id = $request->query->get('id');

        if (null === $id) {
            throw new NotFoundHttpException();
        }

        $user = $this->userRepository->find($id);

        if (null === $user) {
            throw new NotFoundHttpException();
        }

        if ($user->isVerified()) {
            throw new EmailAlreadyVerifiedException();
        }

        return $user;
    }

    public function getUnverifiedUserByEmail(?string $email): User
    {
        if (null === $email) {
            throw new NotFoundHttpException();
        }

        $user = $this->userRepository->findUserByIdentifier($email);

        if (null === $user) {
            throw new NotFoundHttpException();
        }

        if ($user->isVerified()) {
            throw new EmailAlreadyVerifiedException();
        }

        return $user;
    }

    /**
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailValidation(Request $request, User $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
            $request,
            (string) $user->getId(),
            $user->getEmail()
        );

        $user->setVerified(true);
        $user->setEnabled(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
