<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\ViewHelpers;

use Closure;
use MEDIAESSENZ\Mail\Constants;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class ConstantViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    protected $escapeOutput = false;

    /**
     * Initialize the arguments.
     */
    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'Name of the constant', true);
        $this->registerArgument('classFQN', 'string', 'Full qualified class name', false, Constants::class);
    }

    /**
     * get country infos from a given ISO3
     *
     * @param array $arguments
     * @param Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     *
     * @return mixed
     */
    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        $constantName = $arguments['name'];
        $classFQN = $arguments['classFQN'];
        if ($classFQN) {
            return constant("$classFQN::$constantName") ?? null;
        }
        return constant($constantName) ?? null;
    }
}
