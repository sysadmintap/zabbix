<?php

namespace Modules\NetworkAvailability;

use Zabbix\Core\CModule;
use APP;
use CMenuItem;

class Module extends CModule {

    public function init(): void {
        APP::Component()->get('menu.main')
            ->findOrAdd(_('Reports'))
            ->getSubmenu()
            ->add(
                (new CMenuItem(_('Network Availability')))->setAction('network.availability')
            );
    }
}
