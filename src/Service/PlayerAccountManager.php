<?php

namespace App\Service;

use App\Entity\Player;
use App\Entity\User;
use App\Enum\AccountLanguage;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Compte (User) lié à un joueur, à partir d'un email saisi dans un formulaire de l'admin
 * (Player::$accountEmail : formulaire Joueur, nouvelle inscription, page "Compléter les joueurs").
 *
 * Un compte créé ici a un mot de passe aléatoire, jamais communiqué : la personne passe par
 * "Mot de passe oublié" ou un lien de connexion. Aucun email n'est envoyé.
 */
class PlayerAccountManager
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    )
    {
    }

    /**
     * Applique l'email saisi (Player::setAccountEmail()) : crée le compte, ou change son email.
     * Sans email saisi, rien ne change. Le compte est persisté avec le joueur (cascade).
     *
     * @return bool true si un compte a été créé ou lié
     */
    public function sync(Player $player): bool
    {
        $email = $player->getAccountEmail();

        if ($email === null) {
            return false;
        }

        if ($player->hasAccount()) {
            if ($player->getUser()->getEmail() !== $email) {
                $player->getUser()->setEmail($email);
            }

            return false;
        }

        // un compte existant sans joueur (inscrit sur le site) : on le lie plutôt que d'en créer un autre
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user !== null && $user->getPlayer() !== null) {
            // AvailableAccountEmail l'a refusé avant l'enregistrement
            throw new \LogicException(sprintf('L\'email %s est celui du compte d\'un autre joueur.', $email));
        }

        if ($user === null) {
            $user = $this->newUser()
                ->setEmail($email)
                ->setName($player->getName());
        }

        $player->setUser($user);

        return true;
    }

    /**
     * Compte créé par un admin : actif, vérifié, mot de passe aléatoire (jamais communiqué)
     */
    public function newUser(): User
    {
        $user = new User();

        return $user
            ->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(10))))
            ->setEnabled(true)
            ->setVerified(true)
            ->setAccountLanguage(AccountLanguage::FRENCH);
    }
}
