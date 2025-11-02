<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Controller;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper\ProductFeedMapperInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Repository\ProductFeedConfigRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /acp/products
 *
 * Returns the product catalog for the MCP server in OpenAI Product Feed format.
 * This is NOT part of the official ACP spec, but required by MCP implementations
 * to display and search products.
 *
 * @see https://developers.openai.com/commerce/specs/feed/
 */
final readonly class GetProductsAction
{
    /**
     * @param ProductVariantRepositoryInterface<\Sylius\Component\Core\Model\ProductVariantInterface> $productVariantRepository
     */
    public function __construct(
        private ProductVariantRepositoryInterface $productVariantRepository,
        private ChannelContextInterface $channelContext,
        private ProductFeedConfigRepositoryInterface $feedConfigRepository,
        private ProductFeedMapperInterface $productFeedMapper,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        // Get Product Feed configuration for this channel
        $feedConfig = $this->feedConfigRepository->findOneByChannel($channel);

        // Fetch enabled product variants for current channel
        // Use same logic as PushProductFeedCommand
        /** @var \Doctrine\ORM\EntityRepository<\Sylius\Component\Core\Model\ProductVariantInterface> $repository */
        $repository = $this->productVariantRepository;
        $variants = $repository->createQueryBuilder('v')
            ->innerJoin('v.product', 'p')
            ->andWhere(':channel MEMBER OF p.channels')
            ->andWhere('p.enabled = true')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getResult()
        ;

        // Use ProductFeedMapper to format variants in OpenAI Product Feed format
        $formattedProducts = $this->productFeedMapper->mapVariants(
            $variants,
            $channel,
            $feedConfig,
        );

        return new JsonResponse([
            'products' => $formattedProducts,
        ], Response::HTTP_OK);
    }
}
