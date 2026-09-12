<?php
/** @var xPDOTransport $transport */
/** @var array $options */
/** @var modX $modx */

if ($transport->xpdo) {
    $modx =& $transport->xpdo;

    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            $eventName = 'OnFetchItBeforeProcess';
            /** @var modEvent $event */
            $event = $modx->getObject('modEvent', array('name' => $eventName));
            if (!$event) {
                $event = $modx->newObject('modEvent');
                $event->fromArray(array(
                    'name' => $eventName,
                    'service' => 1,
                    'groupname' => 'FetchIt',
                ), '', true, true);
                $event->save();
            }
            break;
    }
}

return true;
