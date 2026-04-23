<?php

namespace Icinga\Module\PerfdataGraphs\ProvidedHook\Monitoring;

use Icinga\Module\Perfdatagraphs\Common\ModuleConfig;
use Icinga\Module\Perfdatagraphs\Common\PerfdataChart;
use Icinga\Module\Perfdatagraphs\Common\PerfdataSource;
use Icinga\Module\Perfdatagraphs\Ido\IcingaObjectHelper as IdoCVH;
use Icinga\Module\Perfdatagraphs\Model\PerfdataRequest;

use Icinga\Module\Monitoring\Hook\ObjectDetailsTabHook;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Service;

use Icinga\Web\Request;

use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Html\HtmlString;

class ObjectDetailsTab extends ObjectDetailsTabHook
{
    use PerfdataChart;

    public function getName()
    {
        return 'graphs';
    }

    public function getLabel()
    {
        return 'Performance Data Graph';
    }

    protected function addError(string $message): HtmlElement
    {
        $err = Html::tag('div');
        $err->add(HtmlElement::create('p', ['class' => 'line-chart-error preformatted'], $message));
        return $err;
    }

    public function getContent(MonitoredObject $object, Request $request)
    {
        $isHostCheck = false;

        if ($object instanceof Host) {
            $serviceName = $object->host_check_command;
            $hostName = $object->getName();
            $checkCommandName = $object->host_check_command;
            $checkInterval = intval($object->host_check_interval);
            $isHostCheck = true;
        } elseif ($object instanceof Service) {
            $serviceName = $object->getName();
            $hostName = $object->getHost()->getName();
            $checkCommandName = $object->check_command;
            $checkInterval = intval($object->service_check_interval);
        } else {
            return Html::tag('div');
        }

        $config = ModuleConfig::getConfigWithDefaults();
        $defaultDuration = $config['default_timerange'];
        // Retrieve the URL parameters.
        $duration = $request->getParam('perfdatagraphs_duration', $defaultDuration);

        // Optional list of labels, when passed only the given perfdata metrics will be shown
        $labels = $request->getUrl()->getParams()->getValues('perfdatagraphs.label');

        $cvh = new IdoCVH();

        $customvars = $cvh->getPerfdataGraphsConfigForObject($object);

        // If the object wants the data from a custom backend
        if ($customvars[$cvh::CUSTOM_VAR_CONFIG_BACKEND] ?? false) {
            $hook = ModuleConfig::getHookByName($customvars[$cvh::CUSTOM_VAR_CONFIG_BACKEND]);
        } else {
            $hook = ModuleConfig::getHook();
        }

        // If there is no hook configured we return here.
        if (empty($hook)) {
            return $this->addError($this->translate('No hook configured'));
        }

        $metricsToExclude = [];
        if ($customvars[$cvh::CUSTOM_VAR_CONFIG_EXCLUDE] ?? false) {
            $metricsToExclude = $customvars[$cvh::CUSTOM_VAR_CONFIG_EXCLUDE];
        }

        $source = new PerfdataSource($config, $hook);
        $perfdatarequest = new PerfdataRequest(
            hostName: $hostName,
            serviceName: $serviceName,
            checkCommand: $checkCommandName,
            checkInterval: $checkInterval,
            duration: $duration,
            isHostCheck: $isHostCheck,
            includeMetrics: [],
            excludeMetrics: $metricsToExclude
        );

        $customVarsMetrics = $cvh->getPerfdataGraphsMetricsForObject($object);

        $response = $source->fetch($perfdatarequest, $customVarsMetrics);

        $limit = -1;
        $chart = $this->createChart(request: $perfdatarequest, response: $response, filter: $labels, limit: $limit);

        if (empty($chart)) {
            return $this->addError($this->translate('Chart could not be rendered'));
        }

        return Html::tag('div', ['class' => 'icinga-module module-perfdatagraphs'], HtmlString::create($chart));
    }
}
