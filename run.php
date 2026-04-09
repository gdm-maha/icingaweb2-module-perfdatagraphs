<?php

use Icinga\Module\Perfdatagraphs\ProvidedHook\Icingadb\IcingadbSupport;

/** @var $this \Icinga\Application\Modules\Module */

$this->provideHook('icingadb/IcingadbSupport');
$this->provideHook('icingadb/ServiceDetailExtension');
$this->provideHook('icingadb/HostDetailExtension');

// This is needed because IcingaDB also calls this hooks and we want to avoid this
if (! static::exists('icingadb') || ! IcingadbSupport::useIcingaDbAsBackend()) {
    $this->provideHook('monitoring/DetailviewExtension');
}
