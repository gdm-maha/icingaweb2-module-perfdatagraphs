<?php

namespace Icinga\Module\Perfdatagraphs\ProvidedHook\Icingadb;

use Icinga\Module\Perfdatagraphs\Common\ModuleConfig;
use Icinga\Module\Perfdatagraphs\Common\PerfdataChart;
use Icinga\Module\Perfdatagraphs\Common\PerfdataSource;
use Icinga\Module\Perfdatagraphs\Icingadb\IcingaObjectHelper;
use Icinga\Module\Perfdatagraphs\Model\PerfdataRequest;
use Icinga\Module\Perfdatagraphs\Widget\MetricSelector;

use Icinga\Module\Icingadb\Hook\TabHook;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;

use Icinga\Application\Icinga;

use ipl\Web\Url;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Html\HtmlString;
use ipl\Orm\Model;

class Tab extends TabHook
{
    use PerfdataChart;

    public function getName(): string
    {
        return 'graphs';
    }

    public function getLabel(): string
    {
        return t('Performance Data Graph');
    }

    protected function addError(string $message): HtmlElement
    {
        $err = Html::tag('div');
        $err->add(HtmlElement::create('p', ['class' => 'line-chart-error preformatted'], $message));
        return $err;
    }

    public function getContent(Model $object): array
    {
        $isHostCheck = false;
        if ($object instanceof Host) {
            $serviceName = $object->checkcommand_name;
            $isHostCheck = true;
            $checkCommandName = $object->checkcommand_name;
            $checkInterval = intval($object->check_interval);
            $hostName = $object->name;
        } elseif ($object instanceof Service) {
            $serviceName = $object->name;
            $checkCommandName = $object->checkcommand_name;
            $checkInterval = intval($object->check_interval);
            $hostName = $object->host->name;
        } else {
            return [];
        }

        $request = Icinga::app()->getRequest();

        $config = ModuleConfig::getConfigWithDefaults();
        $defaultDuration = $config['default_timerange'];

        // Retrieve the URL parameters.
        $duration = $request->getParam('perfdatagraphs_duration', $defaultDuration);

        // Optional list of labels, when passed only the given perfdata metrics will be shown.
        // Support both repeated params (?perfdatagraphs.label=a&perfdatagraphs.label=b) and
        // comma-separated single param (?perfdatagraphs.label=a,b) used by dashboard tile links.
        $labels = [];
        foreach ($request->getUrl()->getParams()->getValues('perfdatagraphs.label') as $raw) {
            foreach (explode(',', $raw) as $label) {
                $label = trim($label);
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
        }

        // When noselector=1 the metric selector UI is suppressed (used by dashboard tiles).
        // Do not suppress it for an empty selection, otherwise the tab returns no
        // content and Icinga falls back to looking for a default view script.
        $noSelector = $request->getUrl()->getParam('perfdatagraphs.noselector', '0') === '1'
            && ! empty($labels);
        $cvh = new IcingaObjectHelper();

        $customvars = $cvh->getPerfdataGraphsConfigForObject($object);

        // If the object wants the data from a custom backend
        if ($customvars[$cvh::CUSTOM_VAR_CONFIG_BACKEND] ?? false) {
            $hook = ModuleConfig::getHookByName($customvars[$cvh::CUSTOM_VAR_CONFIG_BACKEND]);
        } else {
            $hook = ModuleConfig::getHook();
        }

        // If there is no hook configured we return here.
        $content = [];
        if (empty($hook)) {
            $content[] = $this->addError($this->translate('No hook configured'));
            return $content;
        }

        $metricsToExclude = [];
        if ($customvars[$cvh::CUSTOM_VAR_CONFIG_EXCLUDE] ?? false) {
            $metricsToExclude = $customvars[$cvh::CUSTOM_VAR_CONFIG_EXCLUDE];
        }

        $source = new PerfdataSource($config, $hook);
        $request = new PerfdataRequest(
            hostName: $hostName,
            serviceName: $serviceName,
            checkCommand: $checkCommandName,
            checkInterval: $checkInterval,
            duration: $duration,
            isHostCheck: $isHostCheck,
            includeMetrics: [],
            excludeMetrics: $metricsToExclude,
        );

        $customVarsMetrics = $cvh->getPerfdataGraphsMetricsForObject($object);

        $response = $source->fetch($request, $customVarsMetrics);

        // Collect all available metric labels from the response
        $availableLabels = [];
        foreach ($response->getDatasets() as $dataset) {
            $availableLabels[] = $dataset->getTitle();
        }

        if (!empty($availableLabels) && !$noSelector) {
            $content[] = new MetricSelector($availableLabels, $labels, Url::fromRequest());
        }

        if (!empty($labels)) {
            // Render charts for the selected labels.
            $chart = $this->createChart(request: $request, response: $response, filter: $labels, limit: -1);
            $content[] = HtmlString::create($chart);
        } elseif (empty($availableLabels)) {
            // No datasets at all, fall through to standard empty/error rendering.
            $chart = $this->createChart(request: $request, response: $response, filter: $labels, limit: -1);
            $content[] = HtmlString::create($chart);
        }

        return $content;
    }
}
