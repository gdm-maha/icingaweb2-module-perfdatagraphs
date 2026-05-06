<?php

namespace Icinga\Module\Perfdatagraphs\Widget;

use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\I18n\Translation;
use ipl\Web\Url;

/**
 * MetricSelector renders a checkbox list so the user can pick one or more
 * perfdata metrics to display. The form uses GET so that IcingaWeb2 handles it
 * via normal AJAX navigation, no custom javascript.
 */
class MetricSelector extends BaseHtmlElement
{
    use Translation;

    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'perfdatagraphs-metric-selector'];

    /** @var string[] */
    protected array $availableLabels;

    /** @var string[] */
    protected array $selectedLabels;

    protected Url $baseUrl;

    public function __construct(array $availableLabels, array $selectedLabels, Url $baseUrl)
    {
        $this->availableLabels = $availableLabels;
        $this->selectedLabels  = $selectedLabels;
        $this->baseUrl         = $baseUrl;
    }

    protected function assemble(): void
    {
        if (empty($this->availableLabels)) {
            return;
        }

        // Build the form action (path only) and collect hidden inputs for
        // all existing URL params except perfdatagraphs.label, which the
        // <select> will supply.
        $urlStr     = $this->baseUrl->without('perfdatagraphs.label')
                                    ->without('perfdatagraphs.noselector')
                                    ->getAbsoluteUrl();
        $actionPath = $urlStr;
        $hiddenInputs = [];

        if (($qpos = strpos($urlStr, '?')) !== false) {
            $actionPath = substr($urlStr, 0, $qpos);
            foreach (explode('&', substr($urlStr, $qpos + 1)) as $pair) {
                if ($pair === '') {
                    continue;
                }
                [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
                $hiddenInputs[] = Html::tag('input', [
                    'type'  => 'hidden',
                    'name'  => rawurldecode($name),
                    'value' => rawurldecode($value),
                ]);
            }
        }

        $selectedCount = count($this->selectedLabels);
        $labelText     = $selectedCount > 0
            ? sprintf($this->translate('%d metric(s) selected'), $selectedCount)
            : $this->translate('Select metrics');

        // We use a hidden checkbox + label as a pure-CSS toggle.
        // The checkbox has no name so it is never submitted with the form.
        // IcingaWeb2 does not intercept <label> clicks, so this works reliably.
        $toggleAttrs = ['type' => 'checkbox', 'id' => 'pfdg-metric-toggle', 'class' => 'metric-toggle-cb'];
        if (empty($this->selectedLabels)) {
            // Start the panel open when nothing is selected yet.
            $toggleAttrs['checked'] = true;
        }

        // Items are wrapped in a scrollable div; submit button is a sibling
        // below it so it stays visible outside the scroll area.
        $checkboxList = Html::tag('div', ['class' => 'metric-checkbox-list']);
        $itemsWrapper = Html::tag('div', ['class' => 'metric-items-scroll']);
        foreach ($this->availableLabels as $label) {
            $cbAttrs = [
                'type'  => 'checkbox',
                'name'  => 'perfdatagraphs.label',
                'value' => $label,
            ];
            if (in_array($label, $this->selectedLabels, true)) {
                $cbAttrs['checked'] = true;
            }
            $itemsWrapper->add(
                Html::tag('label', ['class' => 'metric-item'], [
                    Html::tag('input', $cbAttrs),
                    Html::tag('span', [], $label),
                ])
            );
        }

        $checkboxList->add($itemsWrapper);
        $checkboxList->add(
            Html::tag('div', ['class' => 'metric-selector-submit-row icinga-controls'],
                Html::tag('button', ['type' => 'submit', 'name' => 'undefined'], $this->translate('Show'))
            )
        );

        $form = Html::tag('form', [
            'method'           => 'get',
            'action'           => $actionPath,
            'data-base-target' => '_self',
        ]);
        foreach ($hiddenInputs as $input) {
            $form->add($input);
        }
        // noselector=1 is baked into the submitted URL so that dashboard tiles
        // (which capture the current URL) suppress the selector widget.
        $form->add(Html::tag('input', [
            'type'  => 'hidden',
            'name'  => 'perfdatagraphs.noselector',
            'value' => '1',
        ]));
        $form->add(Html::tag('input', $toggleAttrs));
        $form->add(Html::tag('label', ['for' => 'pfdg-metric-toggle', 'class' => 'metric-toggle-label'], $labelText));
        $form->add($checkboxList);

        $this->add($form);
    }
}
