<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\ViewHelpers;

use MEDIAESSENZ\Mail\Mail\MailMessage;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * Render an image for usage within an email.
 * If a mail message is provided the image is embedded into the message.
 */
class ImageViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\ImageViewHelper
{

    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('msg', 'object', 'MailMessage object to embed image');
    }

    /**
     * @return string Rendered tag
     * @throws \UnexpectedValueException
     * @throws Exception
     */
    public function render()
    {
        $src = (string)$this->arguments['src'];
        if (($src === '' && $this->arguments['image'] === null) || ($src !== '' && $this->arguments['image'] !== null)) {
            throw new Exception('You must either specify a string src or a File object.', 1382284106);
        }

        try {
            $image = $this->imageService->getImage(
                $this->arguments['src'],
                $this->arguments['image'],
                $this->arguments['treatIdAsReference']
            );
            $cropString = $this->arguments['crop'];
            if ($cropString === null && $image->hasProperty('crop') && $image->getProperty('crop')) {
                $cropString = $image->getProperty('crop');
            }
            $cropVariantCollection = CropVariantCollection::create((string)$cropString);
            $cropVariant = $this->arguments['cropVariant'] ?: 'default';
            $cropArea = $cropVariantCollection->getCropArea($cropVariant);
            $processingInstructions = [
                'width' => $this->arguments['width'],
                'height' => $this->arguments['height'],
                'minWidth' => $this->arguments['minWidth'],
                'minHeight' => $this->arguments['minHeight'],
                'maxWidth' => $this->arguments['maxWidth'],
                'maxHeight' => $this->arguments['maxHeight'],
                'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image),
            ];
            $processedImage = $this->imageService->applyProcessingInstructions($image, $processingInstructions);

            /** @var MailMessage $message */
            $message = $this->arguments['msg'];
            if ($message) {
                $path = GeneralUtility::getFileAbsFileName($processedImage->getForLocalProcessing(false));
                $id = md5($path);
                $message->embedFromPath($path, $id);
                $this->tag->addAttribute('src', "cid:" . $id);
            } else {
                $imageUri = $this->imageService->getImageUri($processedImage, true);
                $this->tag->addAttribute('src', $imageUri);
            }
            $this->tag->addAttribute('width', $processedImage->getProperty('width'));
            $this->tag->addAttribute('height', $processedImage->getProperty('height'));

            $alt = $image->getProperty('alternative');
            $title = $image->getProperty('title');

            // The alt-attribute is mandatory to have valid html-code, therefore add it even if it is empty
            if (empty($this->arguments['alt'])) {
                $this->tag->addAttribute('alt', $alt);
            }
            if (empty($this->arguments['title']) && $title) {
                $this->tag->addAttribute('title', $title);
            }
        } catch (ResourceDoesNotExistException $e) {
            // thrown if file does not exist
        } catch (\UnexpectedValueException $e) {
            // thrown if a file has been replaced with a folder
        } catch (\RuntimeException $e) {
            // RuntimeException thrown if a file is outside of a storage
        } catch (\InvalidArgumentException $e) {
            // thrown if file storage does not exist
        }

        return $this->tag->render();
    }
}
