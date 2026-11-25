<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Tests\Functional;

use Teris\ERecht24Bundle\ClientRegistrar;
use Teris\ERecht24Bundle\Tests\Fixtures\ApiResponses;
use Teris\ERecht24Bundle\Tests\Fixtures\ResponseQueue;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

final class BundleTest extends KernelTestCase
{
    private KernelInterface $app;
    private ResponseQueue $http;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        $this->app = self::bootKernel();
        $http = self::getContainer()->get('test.http_mock');
        self::assertInstanceOf(ResponseQueue::class, $http);
        $this->http = $http;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // FrameworkBundle 7.x registers a global exception handler on boot (8.x no longer does).
        if (Kernel::MAJOR_VERSION < 8) {
            restore_exception_handler();
        }
    }

    public function testPushEndToEndUpdatesTwigOutput(): void
    {
        $this->http->responses = ([ApiResponses::imprint()]);

        $response = $this->app->handle(Request::create('/erecht24/push', 'POST', [
            'erecht24_secret' => 'the-secret',
            'erecht24_type' => 'imprint',
        ]));

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(1, $this->http->requests);
        self::assertSame('<h1>Site Notice</h1>', $this->render("{{ erecht24_document('imprint', 'en') }}"));
        self::assertSame(1, $this->http->requests, 'Rendering must read from the store.');
    }

    public function testTwigPullsMissingDocumentOnce(): void
    {
        $this->http->responses = ([ApiResponses::json(['html_de' => '<h1>Datenschutz</h1>', 'html_en' => ''])]);

        self::assertSame('<h1>Datenschutz</h1>', $this->render("{{ erecht24_document('privacyPolicy') }}"));
        self::assertSame('<h1>Datenschutz</h1>', $this->render("{{ erecht24_document('privacyPolicy', 'en') }}"));
        self::assertSame(1, $this->http->requests);
    }

    public function testRegisterCommandUsesGeneratedPushUrlAndPrintsEnvLines(): void
    {
        $this->http->responses = ([ApiResponses::json(['secret' => 'new-secret'])]);

        $tester = new CommandTester((new Application($this->app))->find('erecht24:client:register'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('ERECHT24_CLIENT_SECRET=new-secret', $tester->getDisplay());
        // client_id 7 is configured → update instead of a new registration
        self::assertSame('https://example.org/erecht24/push', self::getContainer()->get(ClientRegistrar::class)->pushUri('default'));
    }

    private function render(string $template): string
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->createTemplate($template)->render();
    }
}
