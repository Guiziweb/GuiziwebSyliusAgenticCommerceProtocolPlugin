<?php

declare(strict_types=1);

namespace Tests\Guiziweb\SyliusAgenticCommerceProtocolPlugin\Unit\Mapper;

use Doctrine\Common\Collections\ArrayCollection;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ProductFeedConfig;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper\ProductFeedMapper;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tests for ProductFeedMapper
 *
 * Validates correct mapping according to OpenAI Product Feed spec:
 * @see https://developers.openai.com/commerce/specs/feed/
 */
final class ProductFeedMapperTest extends TestCase
{
    private ProductFeedMapper $mapper;
    private UrlGeneratorInterface $urlGenerator;
    private CacheManager $imagineCacheManager;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->imagineCacheManager = $this->createMock(CacheManager::class);

        $this->mapper = new ProductFeedMapper(
            $this->urlGenerator,
            $this->imagineCacheManager
        );
    }

    public function test_it_maps_variant_with_all_required_fields(): void
    {
        // Given
        $variant = $this->createVariantWithProduct();
        $channel = $this->createChannel();
        $feedConfig = $this->createFeedConfig();

        $this->setupUrlGenerator('https://example.com/products/test-product');
        $this->setupImageCache('test.jpg', 'https://example.com/cache/test.jpg');

        // When
        $result = $this->mapper->mapVariants([$variant], $channel, $feedConfig);

        // Then
        $this->assertCount(1, $result);
        $item = $result[0];

        // Verify required OpenAI fields
        $this->assertArrayHasKey('enable_search', $item);
        $this->assertArrayHasKey('enable_checkout', $item);
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('title', $item);
        $this->assertArrayHasKey('description', $item);
        $this->assertArrayHasKey('link', $item);
        $this->assertArrayHasKey('brand', $item);
        $this->assertArrayHasKey('product_category', $item);
        $this->assertArrayHasKey('item_group_id', $item);
        $this->assertArrayHasKey('availability', $item);
        $this->assertArrayHasKey('inventory_quantity', $item);
        $this->assertArrayHasKey('price', $item);
        $this->assertArrayHasKey('image_link', $item);
        $this->assertArrayHasKey('condition', $item);
    }

    public function test_it_skips_variant_without_product(): void
    {
        // Given
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getProduct')->willReturn(null);
        $channel = $this->createChannel();

        // When
        $result = $this->mapper->mapVariants([$variant], $channel, null);

        // Then
        $this->assertCount(0, $result);
    }

    public function test_it_skips_variant_without_pricing(): void
    {
        // Given
        $product = $this->createProduct();
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getCode')->willReturn('TEST_VAR');
        $variant->method('getProduct')->willReturn($product);
        $variant->method('getChannelPricingForChannel')->willReturn(null);
        $channel = $this->createChannel();

        // When
        $result = $this->mapper->mapVariants([$variant], $channel, null);

        // Then
        $this->assertCount(0, $result);
    }

    public function test_it_skips_variant_without_currency(): void
    {
        // Given
        $variant = $this->createVariantWithProduct();
        $channel = $this->createChannel(withCurrency: false);

        // When
        $result = $this->mapper->mapVariants([$variant], $channel, null);

        // Then - Should be skipped due to missing currency
        $this->assertCount(0, $result);
    }

    public function test_it_sets_enable_search_based_on_product_enabled_status(): void
    {
        // Given - enabled product
        $variant = $this->createVariantWithProduct(productEnabled: true);
        $channel = $this->createChannel();

        $this->setupUrlGenerator('https://example.com/products/test');
        $this->setupImageCache('test.jpg', 'https://example.com/cache/test.jpg');

        // When
        $result = $this->mapper->mapVariants([$variant], $channel, null);

        // Then
        $this->assertTrue($result[0]['enable_search']);

        // Given - disabled product
        $variant2 = $this->createVariantWithProduct(productEnabled: false);

        // When
        $result2 = $this->mapper->mapVariants([$variant2], $channel, null);

        // Then
        $this->assertFalse($result2[0]['enable_search']);
    }

    public function test_it_sets_enable_checkout_based_on_enabled_and_stock(): void
    {
        // Given - enabled product with stock
        $variant = $this->createVariantWithProduct(productEnabled: true, stock: 5);
        $channel = $this->createChannel();

        $this->setupUrlGenerator('https://example.com/products/test');
        $this->setupImageCache('test.jpg', 'https://example.com/cache/test.jpg');

        // When
        $result = $this->mapper->mapVariants([$variant], $channel, null);

        // Then
        $this->assertTrue($result[0]['enable_checkout']);

        // Given - enabled product without stock
        $variant2 = $this->createVariantWithProduct(productEnabled: true, stock: 0);
        $result2 = $this->mapper->mapVariants([$variant2], $channel, null);

        // Then
        $this->assertFalse($result2[0]['enable_checkout']);

        // Given - disabled product with stock
        $variant3 = $this->createVariantWithProduct(productEnabled: false, stock: 5);
        $result3 = $this->mapper->mapVariants([$variant3], $channel, null);

        // Then
        $this->assertFalse($result3[0]['enable_checkout']);
    }

    public function test_it_sets_availability_based_on_stock(): void
    {
        // Given - variant with stock
        $variant = $this->createVariantWithProduct(stock: 10);
        $channel = $this->createChannel();

        $this->setupUrlGenerator('https://example.com/products/test');
        $this->setupImageCache('test.jpg', 'https://example.com/cache/test.jpg');

        // When
        $result = $this->mapper->mapVariants([$variant], $channel, null);

        // Then
        $this->assertSame('in_stock', $result[0]['availability']);

        // Given - variant without stock
        $variant2 = $this->createVariantWithProduct(stock: 0);
        $result2 = $this->mapper->mapVariants([$variant2], $channel, null);

        // Then
        $this->assertSame('out_of_stock', $result2[0]['availability']);
    }

    public function test_it_uses_config_defaults_when_provided(): void
    {
        // Given
        $variant = $this->createVariantWithProduct();
        $channel = $this->createChannel();
        $feedConfig = $this->createFeedConfig(
            brand: 'TestBrand',
            weight: '2.5 kg',
            material: 'Cotton'
        );

        $this->setupUrlGenerator('https://example.com/products/test');
        $this->setupImageCache('test.jpg', 'https://example.com/cache/test.jpg');

        // When
        $result = $this->mapper->mapVariants([$variant], $channel, $feedConfig);

        // Then
        $this->assertSame('TestBrand', $result[0]['brand']);
        $this->assertSame('2.5 kg', $result[0]['weight']);
        $this->assertSame('Cotton', $result[0]['material']);
    }

    public function test_it_uses_default_brand_when_no_config(): void
    {
        // Given
        $variant = $this->createVariantWithProduct();
        $channel = $this->createChannel();

        $this->setupUrlGenerator('https://example.com/products/test');
        $this->setupImageCache('test.jpg', 'https://example.com/cache/test.jpg');

        // When
        $result = $this->mapper->mapVariants([$variant], $channel, null);

        // Then
        $this->assertSame('Unknown', $result[0]['brand']);
    }

    public function test_it_formats_price_with_currency(): void
    {
        // Given
        $variant = $this->createVariantWithProduct();
        $channel = $this->createChannel();

        $this->setupUrlGenerator('https://example.com/products/test');
        $this->setupImageCache('test.jpg', 'https://example.com/cache/test.jpg');

        // When
        $result = $this->mapper->mapVariants([$variant], $channel, null);

        // Then
        $this->assertSame('2500 USD', $result[0]['price']);
    }

    public function test_it_groups_variants_with_item_group_id(): void
    {
        // Given
        $product = $this->createProduct('PRODUCT_CODE');
        $variant1 = $this->createVariant('VAR1', $product);
        $variant2 = $this->createVariant('VAR2', $product);
        $channel = $this->createChannel();

        $this->setupUrlGenerator('https://example.com/products/test');
        $this->setupImageCache('test.jpg', 'https://example.com/cache/test.jpg');

        // When
        $result = $this->mapper->mapVariants([$variant1, $variant2], $channel, null);

        // Then
        $this->assertCount(2, $result);
        $this->assertSame('VAR1', $result[0]['id']);
        $this->assertSame('VAR2', $result[1]['id']);
        $this->assertSame('PRODUCT_CODE', $result[0]['item_group_id']);
        $this->assertSame('PRODUCT_CODE', $result[1]['item_group_id']);
    }

    public function test_it_generates_image_url_with_liip_imagine(): void
    {
        // Given
        $variant = $this->createVariantWithProduct();
        $channel = $this->createChannel();

        $this->setupUrlGenerator('https://example.com/products/test');
        $this->imagineCacheManager->expects($this->once())
            ->method('getBrowserPath')
            ->with('uploads/image.jpg', 'sylius_shop_product_large_thumbnail')
            ->willReturn('https://example.com/media/cache/resolve/sylius_shop_product_large_thumbnail/uploads/image.jpg');

        // When
        $result = $this->mapper->mapVariants([$variant], $channel, null);

        // Then
        $this->assertSame(
            'https://example.com/media/cache/resolve/sylius_shop_product_large_thumbnail/uploads/image.jpg',
            $result[0]['image_link']
        );
    }

    public function test_it_adds_merchant_fields_when_checkout_enabled(): void
    {
        // Given
        $variant = $this->createVariantWithProduct(productEnabled: true, stock: 5);
        $channel = $this->createChannel();
        $feedConfig = $this->createFeedConfig(
            returnPolicyUrl: 'https://example.com/returns',
            returnWindowDays: 30,
            privacyPolicyUrl: 'https://example.com/privacy',
            termsOfServiceUrl: 'https://example.com/terms'
        );

        $this->setupUrlGenerator('https://example.com/products/test');
        $this->setupImageCache('test.jpg', 'https://example.com/cache/test.jpg');

        // When
        $result = $this->mapper->mapVariants([$variant], $channel, $feedConfig);

        // Then
        $this->assertArrayHasKey('seller_name', $result[0]);
        $this->assertArrayHasKey('seller_url', $result[0]);
        $this->assertArrayHasKey('return_policy', $result[0]);
        $this->assertArrayHasKey('return_window', $result[0]);
        $this->assertArrayHasKey('seller_privacy_policy', $result[0]);
        $this->assertArrayHasKey('seller_tos', $result[0]);

        $this->assertSame(30, $result[0]['return_window']);
        $this->assertSame('https://example.com/returns', $result[0]['return_policy']);
    }

    // Helper methods

    private function createVariantWithProduct(
        bool $productEnabled = true,
        int $stock = 5,
    ): ProductVariantInterface {
        $product = $this->createProduct();
        return $this->createVariant('TEST_VAR', $product, $productEnabled, $stock);
    }

    private function createProduct(string $code = 'PRODUCT_CODE'): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getCode')->willReturn($code);
        $product->method('getName')->willReturn('Test Product');
        $product->method('getDescription')->willReturn('Test Description');
        $product->method('getSlug')->willReturn('test-product');

        $taxon = $this->createMock(TaxonInterface::class);
        $taxon->method('getName')->willReturn('Electronics');
        $product->method('getMainTaxon')->willReturn($taxon);

        $image = $this->createMock(ImageInterface::class);
        $image->method('getPath')->willReturn('uploads/image.jpg');
        $images = new ArrayCollection([$image]);
        $product->method('getImages')->willReturn($images);

        return $product;
    }

    private function createVariant(
        string $code,
        ProductInterface $product,
        bool $productEnabled = true,
        int $stock = 5,
    ): ProductVariantInterface {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getCode')->willReturn($code);
        $variant->method('getProduct')->willReturn($product);
        $variant->method('getOnHand')->willReturn($stock);

        $product->method('isEnabled')->willReturn($productEnabled);

        $channelPricing = $this->createMock(ChannelPricingInterface::class);
        $channelPricing->method('getPrice')->willReturn(2500);
        $variant->method('getChannelPricingForChannel')->willReturn($channelPricing);

        return $variant;
    }

    private function createChannel(bool $withCurrency = true): ChannelInterface
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getName')->willReturn('Web Store');
        $channel->method('getHostname')->willReturn('example.com');

        if ($withCurrency) {
            $currency = $this->createMock(CurrencyInterface::class);
            $currency->method('getCode')->willReturn('USD');
            $channel->method('getBaseCurrency')->willReturn($currency);
        } else {
            $channel->method('getBaseCurrency')->willReturn(null);
        }

        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn('en_US');
        $channel->method('getDefaultLocale')->willReturn($locale);

        return $channel;
    }

    private function createFeedConfig(
        ?string $brand = null,
        ?string $weight = null,
        ?string $material = null,
        ?string $returnPolicyUrl = null,
        ?int $returnWindowDays = null,
        ?string $privacyPolicyUrl = null,
        ?string $termsOfServiceUrl = null,
    ): ProductFeedConfig {
        $config = $this->createMock(ProductFeedConfig::class);
        $config->method('getDefaultBrand')->willReturn($brand);
        $config->method('getDefaultWeight')->willReturn($weight);
        $config->method('getDefaultMaterial')->willReturn($material);
        $config->method('getReturnPolicyUrl')->willReturn($returnPolicyUrl);
        $config->method('getReturnWindowDays')->willReturn($returnWindowDays);
        $config->method('getPrivacyPolicyUrl')->willReturn($privacyPolicyUrl);
        $config->method('getTermsOfServiceUrl')->willReturn($termsOfServiceUrl);

        return $config;
    }

    private function setupUrlGenerator(string $url): void
    {
        $this->urlGenerator->method('generate')->willReturn($url);
    }

    private function setupImageCache(string $path, string $cacheUrl): void
    {
        $this->imagineCacheManager->method('getBrowserPath')->willReturn($cacheUrl);
    }
}