<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordResetTest extends DatabaseTestCase
{
    public function testRegistrationStoresAnEmailAddress(): void
    {
        $this->client->request('POST', '/register', ['registration_form' => [
            'username' => 'neuling',
            'email' => 'neuling@example.de',
            'plainPassword' => 'startpasswort',
            'agreeTerms' => '1',
        ]]);

        self::assertResponseRedirects();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'neuling']);
        self::assertNotNull($user);
        self::assertSame('neuling@example.de', $user->getEmail());
    }

    public function testAForgottenPasswordCanBeReplacedAndLogsTheUserIn(): void
    {
        $this->createUser('koch', 'altesPasswort', 'koch@example.de');

        $this->requestReset('koch@example.de');
        self::assertEmailCount(1);
        self::assertResponseRedirects('/reset-password/check-email');

        $this->client->request('GET', $this->resetLinkFromTheEmail());
        $this->client->followRedirect();

        $this->client->request('POST', '/reset-password/reset', ['change_password_form' => [
            'plainPassword' => ['first' => 'neuesPasswort', 'second' => 'neuesPasswort'],
        ]]);

        // The generated controller redirected to a route named after the user
        // class here, which was a 500 with the password already changed.
        self::assertResponseRedirects();
        self::assertNotNull($this->client->getContainer()->get('security.token_storage')->getToken(), 'the user should be logged in');

        $this->assertPasswordIs('koch', 'neuesPasswort');
    }

    public function testTheOldPasswordStopsWorking(): void
    {
        $this->createUser('koch', 'altesPasswort', 'koch@example.de');

        $this->requestReset('koch@example.de');
        $this->client->request('GET', $this->resetLinkFromTheEmail());
        $this->client->followRedirect();
        $this->client->request('POST', '/reset-password/reset', ['change_password_form' => [
            'plainPassword' => ['first' => 'neuesPasswort', 'second' => 'neuesPasswort'],
        ]]);

        self::assertFalse($this->passwordMatches('koch', 'altesPasswort'));
    }

    /**
     * Telling visitors whether an address is registered would turn this form
     * into a way to enumerate accounts.
     */
    public function testAnUnknownAddressLooksExactlyTheSame(): void
    {
        $this->requestReset('niemand@example.de');

        self::assertEmailCount(0);
        self::assertResponseRedirects('/reset-password/check-email');
    }

    public function testATokenCannotBeUsedTwice(): void
    {
        $this->createUser('koch', 'altesPasswort', 'koch@example.de');
        $this->requestReset('koch@example.de');
        $link = $this->resetLinkFromTheEmail();

        $this->client->request('GET', $link);
        $this->client->followRedirect();
        $this->client->request('POST', '/reset-password/reset', ['change_password_form' => [
            'plainPassword' => ['first' => 'neuesPasswort', 'second' => 'neuesPasswort'],
        ]]);

        // The link first parks the token in the session, then the page itself
        // rejects it — two redirects before anything is rendered.
        $this->client->followRedirects();
        $this->client->request('GET', $link);

        self::assertSelectorTextContains('.bg-red-50', 'Dieser Link ist nicht mehr gültig.');
    }

    public function testMismatchedPasswordsAreRefused(): void
    {
        $this->createUser('koch', 'altesPasswort', 'koch@example.de');
        $this->requestReset('koch@example.de');
        $this->client->request('GET', $this->resetLinkFromTheEmail());
        $this->client->followRedirect();

        $this->client->request('POST', '/reset-password/reset', ['change_password_form' => [
            'plainPassword' => ['first' => 'neuesPasswort', 'second' => 'etwasAnderes'],
        ]]);

        self::assertSelectorTextContains('.text-red-600', 'stimmen nicht überein');
        self::assertTrue($this->passwordMatches('koch', 'altesPasswort'), 'the password must be untouched');
    }

    public function testTheLoginPageOffersTheResetFlow(): void
    {
        $crawler = $this->client->request('GET', '/login');

        self::assertSame(1, $crawler->filter('a[href="/reset-password"]')->count());
    }

    private function requestReset(string $email): void
    {
        $this->client->request('POST', '/reset-password', [
            'reset_password_request_form' => ['email' => $email],
        ]);
    }

    private function resetLinkFromTheEmail(): string
    {
        $body = self::getMailerMessage()?->getHtmlBody() ?? '';
        self::assertIsString($body);
        self::assertSame(1, preg_match('#(/reset-password/reset/[A-Za-z0-9]+)#', $body, $matches), 'the email should carry a reset link');

        return $matches[1];
    }

    private function assertPasswordIs(string $username, string $plainPassword): void
    {
        self::assertTrue($this->passwordMatches($username, $plainPassword), sprintf('"%s" should be the password of %s', $plainPassword, $username));
    }

    private function passwordMatches(string $username, string $plainPassword): bool
    {
        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(User::class, $user);

        return static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, $plainPassword);
    }
}
