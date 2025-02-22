<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace vxPHP\Form\FormElement;

use vxPHP\Application\Exception\ApplicationException;
use vxPHP\File\UploadedFile;
use vxPHP\Http\Request;
use vxPHP\Template\Exception\SimpleTemplateException;

class FileInputElement extends InputElement
{
    /**
     * return type of element
     *
     * @return string
     */
    public function getType(): string
    {
        return 'file';
    }

    /**
     * get original name of an uploaded file associated with this element
     * if attribute multiple is set the name of the first file is returned
     *
     * @return string|null
     */
    public function getValue(): ?string
    {
        $file = $this->getFile();

        if($file instanceof UploadedFile) {
            return $file->getOriginalName();
        }

        if(is_array($file) && $file[0] instanceof UploadedFile) {
            return $file[0]->getOriginalName();
        }

        return null;
    }

    /**
     * masks parent setValue method since a set value of a file
     * input is ignored upon rendering
     *
     * @param mixed $value
     * @return FormElement
     */

    public function setValue($value): FormElement
    {
        return $this;
    }

    /**
     * returns the uploaded file(s) associated with this element
     *
     * @return UploadedFile | UploadedFile[]
     */
    public function getFile(): array|UploadedFile
    {
        return Request::createFromGlobals()->files->get($this->name);
    }

    /**
     * @see FormElement::render
     * @param bool $force
     * @return string
     * @throws ApplicationException
     * @throws SimpleTemplateException
     */
    public function render(bool $force = false): string
    {
        if(empty($this->html) || $force) {

            $this->attributes['type'] = 'file';

            if($this->template) {
                parent::render();
            }

            else {
                $attr = [];

                // value and files is blacklisted since setting both properties by the server is disabled in browsers

                foreach($this->attributes as $k => $v) {
                    if(!in_array($k, ['value', 'files'])) {
                        $attr[] = sprintf('%s="%s"', $k, $v);
                    }
                }

                $this->html = sprintf('<input name="%s" %s>',
                    $this->getAttribute('multiple') ? ($this->getName() . '[]') : $this->getName(),
                    implode(' ', $attr)
                );

            }

        }

        return $this->html;
    }
}