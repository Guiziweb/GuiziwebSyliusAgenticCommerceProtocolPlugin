<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Applier;

use Sylius\Component\Addressing\Model\ProvinceInterface;
use Sylius\Component\Addressing\Repository\ProvinceRepositoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service to apply ACP address data to Sylius Address entities
 *
 * Handles:
 * - Mapping ACP address fields to Sylius Address
 * - Province/state validation (only set if exists in Sylius)
 */
final readonly class ACPAddressApplier
{
    /**
     * @param ProvinceRepositoryInterface<ProvinceInterface> $provinceRepository
     */
    public function __construct(
        #[Autowire(service: 'sylius.factory.address')]
        private FactoryInterface $addressFactory,
        private ProvinceRepositoryInterface $provinceRepository,
    ) {
    }

    /**
     * Creates a new Sylius Address from ACP address data
     *
     * @param array<string, mixed> $addressData ACP address data (name, line_one, line_two, city, state, country, postal_code)
     *
     * @return AddressInterface Sylius address entity
     */
    public function createAddress(array $addressData): AddressInterface
    {
        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();

        $this->applyAddressData($address, $addressData);

        return $address;
    }

    /**
     * Applies ACP address data to an existing Sylius Address
     *
     * @param AddressInterface $address Sylius address to modify
     * @param array<string, mixed> $addressData ACP address data
     */
    public function applyAddressData(AddressInterface $address, array $addressData): void
    {
        // Mapping ACP → Sylius Address
        // ACP spec line 290-301: name, line_one, line_two, city, state, country, postal_code

        if (isset($addressData['name']) && is_string($addressData['name'])) {
            $nameParts = explode(' ', $addressData['name'], 2);
            $address->setFirstName($nameParts[0] ?? '');
            $address->setLastName($nameParts[1] ?? '');
        }

        if (isset($addressData['line_one']) && is_string($addressData['line_one'])) {
            $address->setStreet($addressData['line_one']);
        }

        if (isset($addressData['line_two']) && is_string($addressData['line_two']) && $addressData['line_two'] !== '') {
            $street = $address->getStreet() . "\n" . $addressData['line_two'];
            $address->setStreet($street);
        }

        if (isset($addressData['city']) && is_string($addressData['city'])) {
            $address->setCity($addressData['city']);
        }

        if (isset($addressData['postal_code']) && is_string($addressData['postal_code'])) {
            $address->setPostcode($addressData['postal_code']);
        }

        // Set country first (needed before province lookup)
        $countryCode = null;
        if (isset($addressData['country']) && is_string($addressData['country'])) {
            $countryCode = strtoupper($addressData['country']);
            $address->setCountryCode($countryCode);
        }

        // Set province/state only if it exists in Sylius
        if (isset($addressData['state']) && is_string($addressData['state']) && $countryCode !== null) {
            $province = $this->provinceRepository->findOneBy(['code' => $addressData['state']]);
            if ($province instanceof ProvinceInterface) {
                $address->setProvinceCode($addressData['state']);
            }
            // If province doesn't exist in Sylius, silently skip it
        }
    }
}
