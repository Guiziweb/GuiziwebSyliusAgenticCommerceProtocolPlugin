<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ProductFeedConfig;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Maps Sylius products to OpenAI Product Feed format
 *
 * @see https://developers.openai.com/commerce/specs/feed/
 */
final readonly class ProductFeedMapper implements ProductFeedMapperInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private CacheManager $imagineCacheManager,
    ) {
    }

    public function mapVariants(
        array $variants,
        ChannelInterface $channel,
        ?ProductFeedConfig $config,
    ): array {
        $feedItems = [];

        foreach ($variants as $variant) {
            $feedItem = $this->mapVariant($variant, $channel, $config);
            if ($feedItem !== null) {
                $feedItems[] = $feedItem;
            }
        }

        return $feedItems;
    }

    /**
     * Map a single variant to OpenAI feed format
     *
     * @return array<string, mixed>|null
     */
    private function mapVariant(
        ProductVariantInterface $variant,
        ChannelInterface $channel,
        ?ProductFeedConfig $config,
    ): ?array {
        // Get product from variant
        $product = $variant->getProduct();
        if (!$product instanceof ProductInterface) {
            return null;
        }
        // Skip variants with no pricing for this channel
        $channelPricing = $variant->getChannelPricingForChannel($channel);
        if (!$channelPricing instanceof ChannelPricingInterface) {
            return null;
        }

        // Skip variants with no currency (invalid data)
        $currency = $channel->getBaseCurrency()?->getCode();
        if ($currency === null) {
            return null;
        }

        // OpenAI Control Flags - mapped from Sylius fields
        $isEnabled = $product->isEnabled();
        $hasStock = ($variant->getOnHand() ?? 0) > 0;
        $enableSearch = $isEnabled;
        $enableCheckout = $isEnabled && $hasStock;

        // Availability - mapped from stock
        $availability = $hasStock ? 'in_stock' : 'out_of_stock';

        // Build product URL
        $productUrl = $this->urlGenerator->generate(
            'sylius_shop_product_show',
            [
                'slug' => $product->getSlug(),
                '_locale' => $channel->getDefaultLocale()?->getCode() ?? 'en_US',
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        // Get main image URL using LiipImagine
        $imageUrl = '';
        $images = $product->getImages();
        if (!$images->isEmpty()) {
            $image = $images->first();
            if ($image !== false) {
                $imagePath = $image->getPath();
                if ($imagePath !== null && $imagePath !== '') {
                    // Use LiipImagine to generate proper image URL with filter
                    // 'sylius_shop_product_large_thumbnail' is a standard Sylius filter
                    $imageUrl = $this->imagineCacheManager->getBrowserPath(
                        $imagePath,
                        'sylius_shop_product_large_thumbnail',
                    );
                }
            }
        }

        // Base product data (OpenAI format)
        $feedData = [
            // OpenAI Control
            'enable_search' => $enableSearch,
            'enable_checkout' => $enableCheckout,

            // Required Core Fields
            'id' => (string) $variant->getCode(),
            'title' => (string) $product->getName(),
            'description' => (string) ($product->getDescription() ?? $product->getShortDescription() ?? ''),
            'link' => $productUrl,
            'brand' => $config?->getDefaultBrand() ?? 'Unknown',
            'product_category' => (string) ($product->getMainTaxon()?->getName() ?? 'Uncategorized'),

            // Variant Grouping (groups variants of same product)
            'item_group_id' => (string) $product->getCode(),

            // Availability & Pricing
            'availability' => $availability,
            'inventory_quantity' => (int) ($variant->getOnHand() ?? 0),
            'price' => sprintf(
                '%d %s',
                $channelPricing->getPrice(),
                $currency,
            ),
            'image_link' => $imageUrl,

            // Conditional/Optional Fields
            'condition' => 'new',
            'weight' => $config?->getDefaultWeight(),
            'material' => $config?->getDefaultMaterial(),
        ];

        // Remove null/empty values
        $feedData = array_filter($feedData, fn ($value) => $value !== null && $value !== '');

        // Add merchant/seller fields if checkout is enabled
        if ($enableCheckout && $config !== null) {
            $merchantFields = $this->getMerchantFields($config, $channel);
            $feedData = array_merge($feedData, $merchantFields);
        }

        return $feedData;
    }

    /**
     * Get merchant/seller fields required for checkout-enabled products
     *
     * @return array<string, mixed>
     */
    private function getMerchantFields(ProductFeedConfig $config, ChannelInterface $channel): array
    {
        $fields = [
            'seller_name' => $channel->getName(),
            'seller_url' => $channel->getHostname() ? 'https://' . $channel->getHostname() : null,
        ];

        // Add policy URLs if configured
        if ($config->getPrivacyPolicyUrl() !== null) {
            $fields['seller_privacy_policy'] = $config->getPrivacyPolicyUrl();
        }

        if ($config->getTermsOfServiceUrl() !== null) {
            $fields['seller_tos'] = $config->getTermsOfServiceUrl();
        }

        if ($config->getReturnPolicyUrl() !== null) {
            $fields['return_policy'] = $config->getReturnPolicyUrl();
        }

        if ($config->getReturnWindowDays() !== null) {
            $fields['return_window'] = $config->getReturnWindowDays();
        }

        // Remove null values
        return array_filter($fields, fn ($value) => $value !== null && $value !== '');
    }
}
