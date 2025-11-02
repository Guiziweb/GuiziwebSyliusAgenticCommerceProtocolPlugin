<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Command;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ProductFeedConfig;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper\ProductFeedMapperInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Push product feed to OpenAI endpoint
 *
 * @see https://developers.openai.com/commerce/specs/feed/
 */
#[AsCommand(
    name: 'guiziweb:openai:push-feed',
    description: 'Push product catalog to OpenAI Product Feed endpoint',
)]
final class PushProductFeedCommand extends Command
{
    /**
     * @param ProductVariantRepositoryInterface<\Sylius\Component\Core\Model\ProductVariantInterface> $productVariantRepository
     * @param ChannelRepositoryInterface<\Sylius\Component\Channel\Model\ChannelInterface> $channelRepository
     * @param RepositoryInterface<ProductFeedConfig> $feedConfigRepository
     */
    public function __construct(
        private readonly ProductVariantRepositoryInterface $productVariantRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly RepositoryInterface $feedConfigRepository,
        private readonly ProductFeedMapperInterface $productFeedMapper,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'channel',
                'c',
                InputOption::VALUE_REQUIRED,
                'Channel code to push feed for',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Display the feed without sending it',
            )
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_REQUIRED,
                'Number of variants to process per batch',
                '500',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get channel (required)
        $channelCode = $input->getOption('channel');
        if (!is_string($channelCode)) {
            $io->error('Channel code is required. Use --channel option.');
            $io->text('Example: bin/console guiziweb:openai:push-feed --channel=FASHION_WEB');

            return Command::FAILURE;
        }

        $channel = $this->channelRepository->findOneByCode($channelCode);
        if (!$channel instanceof ChannelInterface) {
            $io->error(sprintf('Channel not found: "%s"', $channelCode));

            return Command::FAILURE;
        }

        $io->info(sprintf('Processing feed for channel: %s', $channel->getName()));

        // Get feed configuration
        /** @var ProductFeedConfig|null $feedConfig */
        $feedConfig = $this->feedConfigRepository->findOneBy(['channel' => $channel]);
        $isDryRun = $input->getOption('dry-run');

        if (!$feedConfig instanceof ProductFeedConfig) {
            if ($isDryRun) {
                $io->info('No ProductFeedConfig found. Using default values (brand: "Unknown", weight: "1.0 lb").');
            } else {
                $io->error('No Product Feed configuration found for this channel.');

                return Command::FAILURE;
            }
        }

        // Check endpoint configuration (only needed for real push, not dry-run)
        if (!$isDryRun && $feedConfig instanceof ProductFeedConfig) {
            if ($feedConfig->getFeedEndpoint() === null) {
                $io->error('Feed endpoint not configured. Please configure feedEndpoint in ProductFeedConfig.');

                return Command::FAILURE;
            }

            if ($feedConfig->getFeedBearerToken() === null) {
                $io->warning('No Bearer token configured. Request may fail if authentication is required.');
            }
        }

        // Get batch size
        $batchSize = (int) $input->getOption('batch-size');
        if ($batchSize < 1) {
            $io->error('Batch size must be at least 1.');

            return Command::FAILURE;
        }

        // Count total variants
        $io->text('Counting variants...');
        /** @var \Doctrine\ORM\EntityRepository<\Sylius\Component\Core\Model\ProductVariantInterface> $repository */
        $repository = $this->productVariantRepository;
        $totalVariants = (int) $repository->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->innerJoin('v.product', 'p')
            ->andWhere(':channel MEMBER OF p.channels')
            ->andWhere('p.enabled = true')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        if ($totalVariants === 0) {
            $io->warning('No variants found in this channel.');

            return Command::SUCCESS;
        }

        $totalBatches = (int) ceil($totalVariants / $batchSize);
        $io->info(sprintf('Found %d variants. Processing in %d batches of %d.', $totalVariants, $totalBatches, $batchSize));

        $totalMapped = 0;
        $totalPushed = 0;

        // Process each batch
        for ($batchNumber = 0; $batchNumber < $totalBatches; ++$batchNumber) {
            $offset = $batchNumber * $batchSize;
            $io->section(sprintf('Batch %d/%d (offset: %d)', $batchNumber + 1, $totalBatches, $offset));

            // Fetch variants for this batch
            $io->text('Fetching variants...');
            $variants = $repository->createQueryBuilder('v')
                ->innerJoin('v.product', 'p')
                ->andWhere(':channel MEMBER OF p.channels')
                ->andWhere('p.enabled = true')
                ->setParameter('channel', $channel)
                ->setMaxResults($batchSize)
                ->setFirstResult($offset)
                ->getQuery()
                ->getResult()
            ;

            // Map variants to feed format
            $io->text(sprintf('Mapping %d variants...', count($variants)));
            $feedData = $this->productFeedMapper->mapVariants(
                $variants,
                $channel,
                $feedConfig,
            );

            $totalMapped += count($feedData);
            $io->text(sprintf('Mapped %d variants.', count($feedData)));

            if (count($feedData) === 0) {
                $io->warning('No variants to push in this batch.');

                continue;
            }

            // Build feed payload for this batch
            $payload = [
                'products' => $feedData,
            ];

            // Dry run mode - show only first batch
            if ($isDryRun) {
                if ($batchNumber === 0) {
                    $io->section('Feed Preview (first batch, JSON)');
                    $json = json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
                    if ($json === false) {
                        $io->error('Failed to encode payload to JSON');

                        return Command::FAILURE;
                    }
                    $output->writeln($json);
                }
                $io->text(sprintf('[DRY RUN] Would push %d variants', count($feedData)));

                continue;
            }

            // Push to OpenAI endpoint
            // Assert: We're not in dry-run mode, so feedConfig must exist (checked earlier)
            if (!$feedConfig instanceof ProductFeedConfig) {
                throw new \LogicException('ProductFeedConfig is required for pushing feed');
            }

            $feedEndpoint = $feedConfig->getFeedEndpoint();
            if ($feedEndpoint === null) {
                throw new \LogicException('Feed endpoint must be configured');
            }

            $io->text(sprintf('Pushing to: %s', $feedEndpoint));

            try {
                $headers = [
                    'Content-Type' => 'application/json',
                ];

                if ($feedConfig->getFeedBearerToken() !== null) {
                    $headers['Authorization'] = 'Bearer ' . $feedConfig->getFeedBearerToken();
                }

                $response = $this->httpClient->request('POST', $feedEndpoint, [
                    'headers' => $headers,
                    'json' => $payload,
                    'timeout' => 60,
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode >= 200 && $statusCode < 300) {
                    $totalPushed += count($feedData);
                    $io->success(sprintf('Pushed %d variants (HTTP %d)', count($feedData), $statusCode));
                } else {
                    $io->error(sprintf('Push failed with HTTP %d', $statusCode));
                    $io->text('Response: ' . $response->getContent(false));

                    return Command::FAILURE;
                }
            } catch (Throwable $e) {
                $io->error(sprintf('Failed to push feed: %s', $e->getMessage()));

                return Command::FAILURE;
            }

            // Small delay between batches to avoid rate limiting
            if ($batchNumber < $totalBatches - 1) {
                usleep(100000); // 100ms
            }
        }

        // Final summary
        if ($isDryRun) {
            $io->success(sprintf('[DRY RUN] Would have pushed %d variants in %d batches.', $totalMapped, $totalBatches));
        } else {
            $io->success(sprintf('Successfully pushed %d variants in %d batches!', $totalPushed, $totalBatches));
        }

        return Command::SUCCESS;
    }
}
