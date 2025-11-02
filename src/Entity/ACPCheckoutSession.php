<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Enum\ACPStatus;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Metadata\Show;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ACP Checkout Session Entity
 *
 * Represents a checkout session created by an AI agent (ChatGPT) following the
 * Agentic Commerce Protocol (ACP) specification v2025-09-29.
 *
 * Architecture: Session ACP = Order Sylius (state='cart') + metadata ACP
 * This entity serves as a bridge between ACP format and Sylius without duplicating business data.
 */
#[AsResource(
    alias: 'guiziweb.acp_checkout_session',
    section: 'admin',
    routePrefix: '/admin',
    templatesDir: '@SyliusAdmin/shared/crud',
    operations: [
        new Index(grid: 'guiziweb_admin_acp_checkout_session'),
        new Show(),
    ],
)]
class ACPCheckoutSession implements ACPCheckoutSessionInterface
{
    private ?int $id = null;

    #[Assert\NotBlank]
    private ?string $acpId = null;

    #[Assert\NotNull]
    private ?OrderInterface $order = null;

    #[Assert\NotNull]
    private ?ChannelInterface $channel = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [ACPStatus::class, 'values'])]
    private ?string $status = null;

    private ?string $idempotencyKey = null;

    private ?string $lastRequestHash = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAcpId(): ?string
    {
        return $this->acpId;
    }

    public function setAcpId(?string $acpId): self
    {
        $this->acpId = $acpId;

        return $this;
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function setOrder(?OrderInterface $order): self
    {
        $this->order = $order;

        return $this;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): self
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }

    public function getLastRequestHash(): ?string
    {
        return $this->lastRequestHash;
    }

    public function setLastRequestHash(?string $lastRequestHash): self
    {
        $this->lastRequestHash = $lastRequestHash;

        return $this;
    }
}
