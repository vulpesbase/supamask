<?php

namespace Supamask\Challenge\Presentation;

/**
 * Renders the hidden, non-interactive honeypot used by challenge pages.
 */
final class HoneypotRenderer
{
    public static function render(PresentationIdentifierSet $identifiers, HoneypotData $data): string
    {
        $escape = static fn (string $value): string =>
            htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $class = $escape($identifiers->honeypot());
        $id = $escape($data->id());
        $attributeName = $data->attributeName();
        $attributeValue = $escape($data->attributeValue());
        $value = $escape($data->value());
        $childValue = $escape($data->childValue());

        return '<div class="'.$class.'" id="'.$id.'" '.$attributeName.'="'.$attributeValue.'" aria-hidden="true" tabindex="-1" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none">'
            .'<span>'.$value.'</span><i>'.$childValue.'</i></div>';
    }
}
