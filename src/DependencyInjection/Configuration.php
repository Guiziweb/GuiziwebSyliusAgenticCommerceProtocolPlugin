<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress UnusedVariable
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('guiziweb_sylius_agentic_commerce_protocol');
        $rootNode = $treeBuilder->getRootNode();

        return $treeBuilder;
    }
}
