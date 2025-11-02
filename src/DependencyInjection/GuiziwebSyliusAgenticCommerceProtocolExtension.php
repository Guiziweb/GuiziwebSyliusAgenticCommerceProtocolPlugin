<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class GuiziwebSyliusAgenticCommerceProtocolExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    /** @psalm-suppress UnusedVariable */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);
        $this->prependSyliusResource($container);
        $this->prependSyliusPayment($container);
    }

    private function prependSyliusResource(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('sylius_resource', [
            'mapping' => [
                'paths' => [
                    dirname(__DIR__) . '/Entity',
                ],
            ],
        ]);
    }

    private function prependSyliusPayment(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('sylius_payment', [
            'gateway_config' => [
                'validation_groups' => [
                    'acp' => ['sylius'],
                ],
            ],
        ]);
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Guiziweb\SyliusAgenticCommerceProtocolPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@GuiziwebSyliusAgenticCommerceProtocolPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
