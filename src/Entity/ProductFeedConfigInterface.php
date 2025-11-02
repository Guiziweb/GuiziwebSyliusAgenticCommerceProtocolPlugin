<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

/**
 * Product Feed Configuration Interface
 *
 * Stores OpenAI ChatGPT Product Feed configuration per channel.
 * This is separate from ACP checkout spec - it's for product discovery.
 */
interface ProductFeedConfigInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getChannel(): ?ChannelInterface;

    public function setChannel(?ChannelInterface $channel): self;

    public function getFeedEndpoint(): ?string;

    public function setFeedEndpoint(?string $feedEndpoint): self;

    public function getFeedBearerToken(): ?string;

    public function setFeedBearerToken(?string $feedBearerToken): self;

    public function getDefaultBrand(): ?string;

    public function setDefaultBrand(?string $defaultBrand): self;

    public function getDefaultWeight(): ?string;

    public function setDefaultWeight(?string $defaultWeight): self;

    public function getDefaultMaterial(): ?string;

    public function setDefaultMaterial(?string $defaultMaterial): self;

    public function getReturnPolicyUrl(): ?string;

    public function setReturnPolicyUrl(?string $returnPolicyUrl): self;

    public function getReturnWindowDays(): ?int;

    public function setReturnWindowDays(?int $returnWindowDays): self;

    public function getPrivacyPolicyUrl(): ?string;

    public function setPrivacyPolicyUrl(?string $privacyPolicyUrl): self;

    public function getTermsOfServiceUrl(): ?string;

    public function setTermsOfServiceUrl(?string $termsOfServiceUrl): self;
}
