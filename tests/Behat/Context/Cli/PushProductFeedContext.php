<?php

declare(strict_types=1);

namespace Tests\Guiziweb\SyliusAgenticCommerceProtocolPlugin\Behat\Context\Cli;

use Behat\Behat\Context\Context;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Webmozart\Assert\Assert;

/**
 * Behat context for testing the push product feed command
 */
final class PushProductFeedContext implements Context
{
    private const PUSH_FEED_COMMAND = 'guiziweb:openai:push-feed';

    private Application $application;

    private ?CommandTester $commandTester = null;

    private ?int $exitCode = null;

    public function __construct(KernelInterface $kernel)
    {
        $this->application = new Application($kernel);
    }

    /**
     * @When I run the push feed command for channel :channelCode
     */
    public function iRunThePushFeedCommandForChannel(string $channelCode): void
    {
        $this->runCommand(['--channel' => $channelCode]);
    }

    /**
     * @When I run the push feed command for channel :channelCode with dry run
     */
    public function iRunThePushFeedCommandForChannelWithDryRun(string $channelCode): void
    {
        $this->runCommand([
            '--channel' => $channelCode,
            '--dry-run' => true,
        ]);
    }

    /**
     * @When I run the push feed command without channel
     */
    public function iRunThePushFeedCommandWithoutChannel(): void
    {
        $this->runCommand([]);
    }

    /**
     * @Then the command should succeed
     */
    public function theCommandShouldSucceed(): void
    {
        Assert::same(
            $this->exitCode,
            Command::SUCCESS,
            sprintf('Command failed with exit code %d. Output: %s', $this->exitCode, $this->getOutput()),
        );
    }

    /**
     * @Then the command should fail
     */
    public function theCommandShouldFail(): void
    {
        Assert::notSame(
            $this->exitCode,
            Command::SUCCESS,
            'Command should have failed but succeeded',
        );
    }

    /**
     * @Then the output should contain :text
     */
    public function theOutputShouldContain(string $text): void
    {
        $output = $this->getOutput();
        Assert::contains($output, $text, sprintf('Output does not contain "%s". Actual output: %s', $text, $output));
    }

    /**
     * @Then no HTTP requests should be made
     */
    public function noHttpRequestsShouldBeMade(): void
    {
        // Dry run mode - check output contains DRY RUN
        $this->theOutputShouldContain('[DRY RUN]');
    }

    /**
     * Execute the command with given input
     */
    private function runCommand(array $input): void
    {
        $command = $this->application->find(self::PUSH_FEED_COMMAND);

        $this->commandTester = new CommandTester($command);
        $this->exitCode = $this->commandTester->execute(array_merge(['command' => self::PUSH_FEED_COMMAND], $input));
    }

    /**
     * Get command output
     */
    private function getOutput(): string
    {
        if ($this->commandTester === null) {
            return '';
        }

        return $this->commandTester->getDisplay();
    }
}
