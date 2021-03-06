<?php
/**
 * Created by PhpStorm.
 * User: fg
 * Date: 2/27/19
 * Time: 11:03 PM
 */

namespace App\Service;

use App\Entity\Token;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;

class TokenService
{
    /**
     * generate token string
     *
     * @param int $length
     * @return string
     * @throws \Exception
     */
    public function generateToken(int $length = 255):string
    {
        $rand = bin2hex(random_bytes($length));
        return substr($rand, 0, $length);
    }

    public function generatePass($string): string
    {
        return substr(md5($string), -12) . substr(md5($string), 0, -12);
    }

    public function newTokens(User $user, EntityManager $manager): array {

        $accessToken = $this->generateToken();
        $refreshToken = $this->generateToken();
        $accessTokenTime = \DateTime::createFromFormat('U', time() + 86400);
        $accessTokenTime->setTimezone(new \DateTimeZone('UTC'));
        $refreshTokenTime = \DateTime::createFromFormat('U', time() + 86400 * 30 );
        $refreshTokenTime->setTimezone(new \DateTimeZone('UTC'));

        try {
            $token = $manager->getRepository(Token::class)->findOneBy(["user_id" => $user->getId(), "type" => "access"]);
            if (!$token) $token = new Token();

            $token->setType("access");
            $token->setToken($accessToken);
            $token->setFinishAt($accessTokenTime);
            $token->setUserId($user->getId());
            $manager->persist($token);
            $manager->flush();

            $token = $manager->getRepository(Token::class)->findOneBy(["user_id" => $user->getId(), "type" => "refresh"]);
            if (!$token) $token = new Token();

            $token->setType("refresh");
            $token->setToken($refreshToken);
            $token->setFinishAt($refreshTokenTime);
            $token->setUserId($user->getId());
            $manager->persist($token);
            $manager->flush();
        } catch (Exception $e) {
            return ["error" => "Can't create tokens. Error: " . $e->getMessage()];
        }
        return ["accessToken" => $accessToken, "refreshToken" => $refreshToken];
    }

    /**
     * refresh token
     *
     * @param string $refresh
     * @param EntityManager $manager
     * @return array
     */
    public function refreshTokens(string $refresh, EntityManager $manager): array {

        $token = $manager->getRepository(Token::class)->findOneBy(["token" => $refresh, "type" => "refresh"]);

        if (!$token) return ["error" => "Token is not exist..."];
        if (time() > $token->getFinishAt()->getTimestamp()) {
            return ["error" => "Token is time elapsed..."];
        }

        $user = $manager->getRepository(User::class)->findOneBy(["id" => $token->getUserId()]);
        if (!$user) return ["error" => "User not found..."];
        return $this->newTokens($user, $manager);

    }

    /**
     * get user by token
     *
     * @param string $access
     * @param EntityManager $manager
     * @return User
     */
    public function getUserByAccessToken(string $access, EntityManager $manager): User
    {
        $token = $manager->getRepository(Token::class)->findOneBy(["token" => $access, "type" => "access"]);
        if (!$token) {
            $user = new User();
            $user->setRole("404");
            return $user;
        }
        if (time() > $token->getFinishAt()->getTimestamp()) {
            $user = new User();
            $user->setRole("401");
            return $user;
        }

        $user = $manager->getRepository(User::class)->findOneBy(["id" => $token->getUserId()]);
        if (!$user) {
            $user = new User();
            $user->setRole("404");
            return $user;
        }
        return $user;
    }

}
