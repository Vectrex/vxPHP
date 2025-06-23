<?php
    /*
     * This file is part of the vxPHP/vxWeb framework
     *
     * (c) Gregor Kofler <info@gregorkofler.com>
     *
     * For the full copyright and license information, please view the LICENSE
     * file that was distributed with this source code.
     */


    namespace vxPHP\Webpage\Menu\Renderer;

    use vxPHP\Webpage\Menu\Menu;

    interface MenuRendererInterface
    {
        /**
         * convenience method; allow chaining of renderer instantiation parameter setting and rendering
         *
         * @param Menu $menu
         * @return MenuRendererInterface
         */
        public static function create(Menu $menu): self;

        /**
         * set parameters required by renderer
         *
         * @param array $parameters
         * @return MenuRendererInterface
         */
        public function setParameters(array $parameters): self;

        /**
         * render menu with its menu entries
         *
         * @return string
         */
        public function render(): string;
    }