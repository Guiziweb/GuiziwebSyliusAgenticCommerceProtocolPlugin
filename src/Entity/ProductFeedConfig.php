<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Create;
use Sylius\Resource\Metadata\Delete;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Metadata\Show;
use Sylius\Resource\Metadata\Update;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Product Feed Configuration Entity
 *
 * Stores OpenAI ChatGPT Product Feed configuration per Sylius channel.
 *
 * Context:
 * - Product Feed is NOT part of the ACP checkout spec
 * - It's a separate system for product discovery in ChatGPT
 * - Merchants must register at chatgpt.com/merchants to receive endpoint/token
 * - Feed format follows OpenAI Product Feed Spec (JSON/TSV/CSV)
 *
 * @see https://developers.openai.com/commerce/specs/feed/
 */
#[AsResource(
    alias: 'guiziweb.product_feed_config',
    section: 'admin',
    formType: 'Guiziweb\SyliusAgenticCommerceProtocolPlugin\Form\Type\ProductFeedConfigType',
    templatesDir: '@SyliusAdmin/shared/crud',
    routePrefix: '/admin',
    operations: [
        new Index(grid: 'guiziweb_admin_product_feed_config'),
        new Create(),
        new Update(),
        new Delete(),
        new Show(),
    ],
)]
class ProductFeedConfig implements ProductFeedConfigInterface
{
    private ?int $id = null;

    #[Assert\NotNull]
    private ?ChannelInterface $channel = null;

    // OpenAI Feed Endpoint Configuration
    #[Assert\Url]
    private ?string $feedEndpoint = null;

    private ?string $feedBearerToken = null;

    // Default values for required OpenAI fields missing in Sylius
    private ?string $defaultBrand = null;

    private ?string $defaultWeight = null;

    private ?string $defaultMaterial = null;

    // Merchant Policies (required by OpenAI if enable_checkout=true)
    #[Assert\Url]
    private ?string $returnPolicyUrl = null;

    #[Assert\PositiveOrZero]
    private ?int $returnWindowDays = 30;

    #[Assert\Url]
    private ?string $privacyPolicyUrl = null;

    #[Assert\Url]
    private ?string $termsOfServiceUrl = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?ChannelInterface $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function getFeedEndpoint(): ?string
    {
        return $this->feedEndpoint;
    }

    public function setFeedEndpoint(?string $feedEndpoint): self
    {
        $this->feedEndpoint = $feedEndpoint;

        return $this;
    }

    public function getFeedBearerToken(): ?string
    {
        return $this->feedBearerToken;
    }

    public function setFeedBearerToken(?string $feedBearerToken): self
    {
        $this->feedBearerToken = $feedBearerToken;

        return $this;
    }

    public function getDefaultBrand(): ?string
    {
        return $this->defaultBrand;
    }

    public function setDefaultBrand(?string $defaultBrand): self
    {
        $this->defaultBrand = $defaultBrand;

        return $this;
    }

    public function getDefaultWeight(): ?string
    {
        return $this->defaultWeight;
    }

    public function setDefaultWeight(?string $defaultWeight): self
    {
        $this->defaultWeight = $defaultWeight;

        return $this;
    }

    public function getDefaultMaterial(): ?string
    {
        return $this->defaultMaterial;
    }

    public function setDefaultMaterial(?string $defaultMaterial): self
    {
        $this->defaultMaterial = $defaultMaterial;

        return $this;
    }

    public function getReturnPolicyUrl(): ?string
    {
        return $this->returnPolicyUrl;
    }

    public function setReturnPolicyUrl(?string $returnPolicyUrl): self
    {
        $this->returnPolicyUrl = $returnPolicyUrl;

        return $this;
    }

    public function getReturnWindowDays(): ?int
    {
        return $this->returnWindowDays;
    }

    public function setReturnWindowDays(?int $returnWindowDays): self
    {
        $this->returnWindowDays = $returnWindowDays;

        return $this;
    }

    public function getPrivacyPolicyUrl(): ?string
    {
        return $this->privacyPolicyUrl;
    }

    public function setPrivacyPolicyUrl(?string $privacyPolicyUrl): self
    {
        $this->privacyPolicyUrl = $privacyPolicyUrl;

        return $this;
    }

    public function getTermsOfServiceUrl(): ?string
    {
        return $this->termsOfServiceUrl;
    }

    public function setTermsOfServiceUrl(?string $termsOfServiceUrl): self
    {
        $this->termsOfServiceUrl = $termsOfServiceUrl;

        return $this;
    }
}
