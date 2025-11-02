<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'sylius.menu.admin.main', method: 'addMenuItems')]
final class AdminMenuListener
{
    public function addMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $acpMenu = $menu
            ->addChild('acp')
            ->setLabel('guiziweb.ui.acp_menu');

        $acpMenu
            ->addChild('acp_checkout_sessions', [
                'route' => 'guiziweb_admin_acp_checkout_session_index',
            ])
            ->setLabel('guiziweb.ui.acp_checkout_sessions')
            ->setLabelAttribute('icon', 'tabler:shopping-cart-code');

        $acpMenu
            ->addChild('product_feed_configs', [
                'route' => 'guiziweb_admin_product_feed_config_index',
            ])
            ->setLabel('guiziweb.ui.product_feed_configs')
            ->setLabelAttribute('icon', 'tabler:rss');
    }
}
