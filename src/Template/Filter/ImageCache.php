<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Template\Filter;

use vxPHP\Application\Exception\ApplicationException;
use vxPHP\File\FilesystemFolder;
use vxPHP\Image\Exception\ImageModifierException;
use vxPHP\Template\Exception\SimpleTemplateException;
use vxPHP\Application\Application;
use vxPHP\Image\ImageModifierFactory;

/**
 * This filter replaces images which are set to specific sizes by optimized resized images in caches
 * in addition cropping and turning into B/W can be added to the src attribute of the image
 *
 * @version 1.5.1 2020-09-16
 * @author Gregor Kofler
 *
 * @todo parse inline url() style rule
 */
class ImageCache extends SimpleTemplateFilter implements SimpleTemplateFilterInterface
{
    /**
     * @var array
     *
     * markup possibilities to which the filter will be applied
     */
    private	$markupToMatch = [
        '~<img\s+.*?src=(["\'])(.*?)\1.*?>~i'
    ];

    /**
     * (non-PHPdoc)
     *
     * @param $templateString
     * @see SimpleTemplateFilterInterface::apply()
     */
    public function apply(&$templateString): void
    {
        $templateString = preg_replace_callback(
            $this->markupToMatch,
            [$this, 'filterCallBack'],
            $templateString
        );
    }

    /**
     * replaces the matched string
     * uses regular expression for a faster processing where useful
     * $matches[0] contains complete image tag
     * $matches[2] the src attribute value
     *
     * @param array $matches
     * @return string
     * @throws SimpleTemplateException
     * @throws ApplicationException
     * @throws ImageModifierException
     */
    private function filterCallBack(array $matches): ?string
    {
        // narrow down the type of replacement, matches[2] contains src attribute value

        /*
         * compare modifications, if src attribute indicates image coming from cache folder
         * 1. retrieve modifications of cached file
         * 2. retrieve current modifications
         * 3. compare 1. and 2. (sorting of actions is irrelevant)
         * 4. with changed modifications, retrieve source file and proceed as with normal modifications
         */
        try {
            if (preg_match('~(.*?)' . preg_quote(FilesystemFolder::CACHE_PATH, '~') . '/([^/]+)@(.*?)\.\w+$~i', $matches[2], $filestrings) && 4 === count($filestrings)) {

                // retrieve modifications of cached file and source file

                $srcFile = $filestrings[1] . $filestrings[2];
                $cachedActions = $this->sanitizeActions($filestrings[3]);
            }

            // <img src="...#{actions}">

            if (preg_match('~(.*?)#([\w\s.|]+)~', $matches[2], $details)) {

                $sanitizedActions = $this->sanitizeActions($details[2]);

                if (isset($cachedActions)) {

                    // detected actions are duplicates of already executed actions

                    if (count(array_intersect($cachedActions, $sanitizedActions)) === count($cachedActions)) {
                        return $matches[0];
                    } // create new thumb with new actions

                    $dest = $this->getCachedImagePath($srcFile, $sanitizedActions);

                } // no previously cached image detected

                else {
                    $dest = $this->getCachedImagePath($details[1], $sanitizedActions);
                }

                return preg_replace('~src=([\'"]).*?\1~i', 'src="' . $dest . '"', $matches[0]);

            } // <img src="..." style="width: ...; height: ...">

            if (preg_match('~\s+style=(["\'])(.*?)\1~i', $matches[0], $details)) {

                // analyze dimensions

                if (!preg_match('~(width|height):\s*(\d+)px;.*?(width|height):\s*(\d+)px~', strtolower($details[2]), $dimensions)) {

                    return $matches[0];

                }

                if ($dimensions[1] === 'width') {
                    $width = $dimensions[2];
                    $height = $dimensions[4];
                } else {
                    $width = $dimensions[4];
                    $height = $dimensions[2];
                }

                $sanitizedActions['resize'] = 'resize ' . $width . ' ' . $height;

                if (isset($cachedActions)) {

                    // set size mirrors size of already saved thumbnail

                    if (count(array_intersect($cachedActions, $sanitizedActions)) === count($cachedActions)) {
                        return $matches[0];
                    } // create new thumb with new size

                    $dest = $this->getCachedImagePath($srcFile, $sanitizedActions);

                } // no previously cached image detected

                else {
                    $dest = $this->getCachedImagePath($matches[2], $sanitizedActions);
                }

                return preg_replace('~src=([\'"]).*?\1~i', 'src="' . $dest . '"', $matches[0]);

            } // <img src="..." width="..." height="...">

            if (preg_match('~\s+(width|height)=~', $matches[0])) {

                $dom = new \DOMDocument();
                $dom->loadHTML($matches[0]);
                $img = $dom->getElementsByTagName('img')->item(0);

                // if width attribute is not set, this will evaluate to 0 and force a proportional scaling

                $width = (int) $img->getAttribute('width');
                $height = (int) $img->getAttribute('height');

                $sanitizedActions['resize'] = 'resize ' . $width . ' ' . $height;

                if (isset($cachedActions)) {

                    // set size mirrors size of already saved thumbnail

                    if (count(array_intersect($cachedActions, $sanitizedActions)) === count($cachedActions)) {
                        return $matches[0];
                    } // create new thumb with new size

                    else {
                        $dest = $this->getCachedImagePath($srcFile, $sanitizedActions);
                    }
                } // no previously cached image detected

                else {
                    $dest = $this->getCachedImagePath($matches[2], $sanitizedActions);
                }

                $img->setAttribute('src', $dest);
                return $dom->saveHTML($img);

            }

            return $matches[0];

            /*
                    // url(...#...), won't be matched by assetsPath filter
                    // @FIXME: getRelativeAssetsPath() doesn't observe mod rewrite

                    $relAssetsPath = ltrim(Application::getInstance()->getRelativeAssetsPath(), '/');
                    return 'url(' . $matches[1] . '/' . $relAssetsPath . $dest . $matches[1] . ')';
                }
            */
        }
        catch (ImageModifierException $e) {
            if(isset($this->parameters['image_not_found_placeholder']) && $e->getCode() === ImageModifierException::FILE_NOT_FOUND) {
                return str_replace($matches[2], $this->parameters['image_not_found_placeholder'], $matches[0]);
            }
            throw $e;
        }
    }
    /**
     * retrieve cached image which matches src attribute $src and actions $actions
     * if no cached image is found, a cached image with $actions applied is created
     *
     * @param string $src
     * @param array $actions
     * @return string
     * @throws SimpleTemplateException
     * @throws ApplicationException
     */
    private function getCachedImagePath(string $src, array $actions): string
    {
        $pathinfo	= pathinfo($src);
        $extension	= isset($pathinfo['extension']) ? ('.' . $pathinfo['extension']) : '';

        // destination file name

        $dest =
            $pathinfo['dirname'] . '/' .
            FilesystemFolder::CACHE_PATH . '/' .
            $pathinfo['filename'] .
            $extension .
            '@' . implode('|', $actions) .
            $extension;

        // absolute path to cached file

        $path = Application::getInstance()->extendToAbsoluteAssetsPath(ltrim($dest, '/'));

        // generate cache directory and file if necessary

        if(!file_exists($path)) {

            $cachePath = dirname($path);

            if(!file_exists($cachePath)) {

                if(!mkdir($cachePath) && !is_dir($cachePath)) {
                    throw new SimpleTemplateException("Failed to create cache folder $cachePath");
                }
                chmod($cachePath, 0777);
            }

            // apply actions and create file

            $imgEdit = ImageModifierFactory::create(Application::getInstance()->extendToAbsoluteAssetsPath(ltrim($pathinfo['dirname'], '/') . '/' . $pathinfo['basename']));

            foreach($actions as $a) {

                $params = preg_split('~\s+~', $a);
                $method = array_shift($params);

                if(method_exists($imgEdit, $method)) {
                    call_user_func_array([$imgEdit, $method], $params);
                }
            }

            $imgEdit->export($path);

        }

        return $dest;
    }

    /**
     * turns actions string into array
     * avoid duplicate actions
     *
     * @param string $actionsString
     * @return array
     */
    private function sanitizeActions(string $actionsString): array
    {
        $actions = [];
        $actionsString = strtolower($actionsString);

        // with duplicate actions the latter ones overwrite previous ones

        foreach(explode('|', $actionsString) as $action) {

            $action = trim($action);
            $actions[strstr($action, ' ', true)] = $action;

        }

        return $actions;
    }
}
