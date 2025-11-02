<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface ACPCheckoutSessionInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getAcpId(): ?string;

    public function setAcpId(?string $acpId): self;

    public function getOrder(): ?OrderInterface;

    public function setOrder(?OrderInterface $order): self;

    public function getChannel(): ?ChannelInterface;

    public function setChannel(?ChannelInterface $channel): self;

    public function getStatus(): ?string;

    public function setStatus(?string $status): self;

    public function getIdempotencyKey(): ?string;

    public function setIdempotencyKey(?string $idempotencyKey): self;

    public function getLastRequestHash(): ?string;

    public function setLastRequestHash(?string $lastRequestHash): self;
}
