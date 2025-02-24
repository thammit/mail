<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\ViewHelpers;

use MEDIAESSENZ\Mail\Constants;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class ConstantViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    /**
     * Initialize the arguments.
     */
    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'Name of the constant', true);
        $this->registerArgument('classFQN', 'string', 'Full qualified class name', false, Constants::class);
    }

    public function render()
    {
        $constantName = $this->arguments['name'];
        $classFQN = $this->arguments['classFQN'];
        if ($classFQN) {
            return constant("$classFQN::$constantName") ?? null;
        }
        return constant($constantName) ?? null;
    }
}
