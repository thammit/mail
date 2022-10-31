<?php

namespace MEDIAESSENZ\Mail\ContentObject;

use Symfony\Component\CssSelector\Exception\ParseException;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;
use MEDIAESSENZ\Mail\Utility\EmogrifierUtility;

class EmogrifierContentObject extends AbstractContentObject
{

    /**
     * @throws ParseException
     */
    public function render($conf = [])
    {
        $content = $css = null;

        if (array_key_exists('html', $conf) && array_key_exists('html.', $conf)) {
            $content = $this->cObj->cObjGetSingle($conf['html'], $conf['html.']);
        }

        if (array_key_exists('css', $conf) && array_key_exists('css.', $conf)) {
            $css = $this->cObj->cObjGetSingle($conf['css'], $conf['css.']);
        }
        $extractContent = (array_key_exists('extractContent', $conf) && $conf['extractContent']);

        $options = [];
        if (array_key_exists('options.', $conf) && is_array($conf['options.'])) {
            $options = $conf['options.'];
        }

        return EmogrifierUtility::emogrify($content, $css, $extractContent, $options);
    }
}
