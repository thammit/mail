<?php
namespace MEDIAESSENZ\Mail\Template\Components\Buttons;

use TYPO3\CMS\Backend\Template\Components\Buttons\AbstractButton;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LinkButtonWithInlineIcon extends AbstractButton
{
    /**
     * HREF attribute of the link
     *
     * @var string
     */
    protected string $href = '';

    protected ?string $alternativeMarkupIdentifier = 'inline';

    /**
     * Get href
     *
     * @return string
     */
    public function getHref(): string
    {
        return $this->href;
    }

    /**
     * Set href
     *
     * @param string $href HREF attribute
     *
     * @return LinkButtonWithInlineIcon
     */
    public function setHref(string $href): LinkButtonWithInlineIcon
    {
        $this->href = $href;
        return $this;
    }

    /**
     * Validates the current button
     *
     * @return bool
     */
    public function isValid(): bool
    {
        if (
            trim($this->getHref()) !== ''
            && trim($this->getTitle()) !== ''
            && $this->getType() === self::class
            && $this->getIcon() !== null
        ) {
            return true;
        }
        return false;
    }

    public function setAlternativeMarkupIdentifier(?string $alternativeMarkupIdentifier): LinkButtonWithInlineIcon
    {
        $this->alternativeMarkupIdentifier = $alternativeMarkupIdentifier;
        return $this;
    }

    public function render(): string
    {
        $attributes = [
            'href' => $this->getHref(),
            'class' => 'btn btn-sm btn-default ' . $this->getClasses(),
            'title' => $this->getTitle(),
        ];
        $labelText = '';
        if ($this->showLabelText) {
            $labelText = ' ' . $this->title;
        }
        foreach ($this->dataAttributes as $attributeName => $attributeValue) {
            $attributes['data-' . $attributeName] = $attributeValue;
        }
        if ($this->isDisabled()) {
            $attributes['disabled'] = 'disabled';
            $attributes['class'] .= ' disabled';
        }
        $attributesString = GeneralUtility::implodeAttributes($attributes, true);

        return '<a ' . $attributesString . '>'
            . $this->getIcon()->render($this->alternativeMarkupIdentifier) . htmlspecialchars($labelText)
            . '</a>';
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
