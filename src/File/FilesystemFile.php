<?php
    /*
     * This file is part of the vxPHP/vxWeb framework
     *
     * (c) Gregor Kofler <info@gregorkofler.com>
     *
     * For the full copyright and license information, please view the LICENSE
     * file that was distributed with this source code.
     */


    namespace vxPHP\File;

    use DirectoryIterator;
    use SplFileInfo;
    use vxPHP\Application\Exception\ApplicationException;
    use vxPHP\File\Exception\FilesystemFileException;
    use vxPHP\Observer\PublisherInterface;
    use vxPHP\Util\Text;

    /**
     * mapper for filesystem files
     *
     * @author Gregor Kofler
     *
     * @version 1.1.6 2025-01-13
     */
    class FilesystemFile implements PublisherInterface, FilesystemFileInterface
    {
        public const array WEBIMAGE_MIMETYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        /**
         * @var array
         */
        protected static array $instances = [];

        /**
         * @var string
         */
        protected string $filename;

        /**
         * @var FilesystemFolder
         */
        protected FilesystemFolder $folder;

        /**
         * @var string|null
         */
        protected ?string $mimetype = null;

        /**
         * @var SplFileInfo
         */
        protected \SplFileInfo $fileInfo;

        /**
         * @param string $path
         * @return FilesystemFile;
         * @throws FilesystemFileException
         * @throws Exception\FilesystemFolderException
         */
        public static function getInstance(string $path): self
        {
            if (!isset(self::$instances[$path])) {
                self::$instances[$path] = new self($path);
            }
            return self::$instances[$path];
        }

        public static function unsetInstance($path): void
        {
            if (isset(self::$instances[$path])) {
                unset(self::$instances[$path]);
            }
        }

        /**
         * constructs mapper for filesystem files
         * if folder is provided a bulk generation is assumed and certain checks are omitted
         *
         * @param string $path
         * @param FilesystemFolder|null $folder
         *
         * @throws Exception\FilesystemFolderException
         * @throws FilesystemFileException
         */
        public function __construct(string $path, ?FilesystemFolder $folder = null)
        {
            if ($folder) {
                $path = $folder->getPath() . $path;
            } else {
                $path = realpath($path);
            }

            if (!file_exists($path)) {
                throw new FilesystemFileException(sprintf("File '%s' does not exist!", $path), FilesystemFileException::FILE_DOES_NOT_EXIST);
            }

            $this->folder = $folder ?: FilesystemFolder::getInstance(pathinfo($path, PATHINFO_DIRNAME));
            $this->filename = pathinfo($path, PATHINFO_BASENAME);
            $this->fileInfo = new SplFileInfo($path);
        }

        /**
         * retrieve file information provided by SplFileInfo object
         */
        public function getFileInfo(): SplFileInfo
        {
            return $this->fileInfo;
        }

        /**
         * retrieve mime type
         * requires MimeTypeGetter
         *
         * @param bool $force forces re-read of the mime type
         * @return string
         */
        public function getMimetype(bool $force = false): string
        {
            if ($this->mimetype === null || $force) {
                $this->mimetype = MimeTypeGetter::get($this->folder->getPath() . $this->filename);
            }
            return $this->mimetype;
        }

        /**
         * check whether the mime type indicates a web image
         * (i.e. image/jpeg, image/gif, image/png, image/webp)
         *
         * @param bool $force forces re-read of the mime type
         * @return bool
         */
        public function isWebImage(bool $force = false): bool
        {
            if (!isset($this->mimetype) || $force) {
                $this->mimetype = MimeTypeGetter::get($this->folder->getPath() . $this->filename);
            }
            return in_array($this->mimetype, self::WEBIMAGE_MIMETYPES, true);
        }

        /**
         * retrieve filename
         */
        public function getFilename(): string
        {
            return $this->filename;
        }

        /**
         * retrieves the physical path of the file
         */
        public function getPath(): string
        {
            return $this->folder->getPath() . $this->filename;
        }

        /**
         * returns path relative to assets path root
         *
         * @param boolean $force
         * @return string
         * @throws ApplicationException
         */
        public function getRelativePath(bool $force = false): string
        {
            return $this->folder->getRelativePath($force) . $this->filename;
        }

        /**
         * return filesystem folder of the file
         */
        public function getFolder(): FilesystemFolder
        {
            return $this->folder;
        }

        /**
         * rename file
         *
         * @param string $to new filename
         * @return FilesystemFileInterface
         * @throws FilesystemFileException
         */
        public function rename(string $to): FilesystemFileInterface
        {
            $from = $this->filename;

            // name is unchanged, nothing to do

            if ($from !== $to) {

                $oldpath = $this->folder->getPath() . $from;
                $newpath = $this->folder->getPath() . $to;

                if (file_exists($newpath)) {
                    throw new FilesystemFileException("Rename from '$oldpath' to '$newpath' failed. '$newpath' already exists.", FilesystemFileException::FILE_RENAME_FAILED);
                }

                if (@rename($oldpath, $newpath)) {

                    $this->renameCacheEntries($to);

                    // set a new filename

                    $this->filename = $to;

                    // re-read fileinfo

                    $this->fileInfo = new SplFileInfo($newpath);

                    self::$instances[$newpath] = $this;
                    unset(self::$instances[$oldpath]);
                } else {
                    throw new FilesystemFileException(sprintf("Rename from '%s' to '%s' failed.", $oldpath, $newpath), FilesystemFileException::FILE_RENAME_FAILED);
                }

            }

            return $this;
        }

        /**
         * move file into new folder,
         * orphaned cache entries are deleted, new cache entries are not generated
         *
         * @param FilesystemFolder $destination
         * @return FilesystemFileInterface
         * @throws FilesystemFileException
         */
        public function move(FilesystemFolder $destination): FilesystemFileInterface
        {
            // already in the destination folder, nothing to do

            if ($destination !== $this->folder) {

                $oldpath = $this->folder->getPath() . $this->filename;
                $newpath = $destination->getPath() . $this->filename;

                if (@rename($oldpath, $newpath)) {

                    $this->clearCacheEntries();

                    // set a new folder reference

                    $this->folder = $destination;

                    // re-read fileinfo

                    $this->fileInfo = new SplFileInfo($newpath);

                    self::$instances[$newpath] = $this;
                    unset(self::$instances[$oldpath]);

                    // @todo: check necessity of chmod

                    @chmod($newpath, 0666 & ~umask());

                } else {
                    throw new FilesystemFileException(sprintf("Moving from '%s' to '%s' failed.", $oldpath, $newpath), FilesystemFileException::FILE_RENAME_FAILED);
                }

            }

            return $this;
        }

        /**
         * updates names of cache entries
         *
         * @param string $to new filename
         */
        protected function renameCacheEntries(string $to): void
        {
            if (($cachePath = $this->folder->getCachePath(true))) {

                $di = new DirectoryIterator($cachePath);

                foreach ($di as $fileinfo) {

                    $filename = $fileinfo->getFilename();

                    if ($fileinfo->isDot() ||
                        !$fileinfo->isFile() ||
                        !str_starts_with($filename, $this->filename)
                    ) {
                        continue;
                    }

                    $renamed = substr_replace($filename, $to, 0, strlen($this->filename));
                    rename($fileinfo->getRealPath(), $fileinfo->getPath() . DIRECTORY_SEPARATOR . $renamed);
                }
            }
        }

        /**
         * deletes file and removes instance from the lookup array
         *
         * @throws FilesystemFileException
         */
        public function delete(): void
        {
            if (@unlink($this->getPath())) {
                $this->deleteCacheEntries();
                self::unsetInstance($this->getPath());
            } else {
                throw new FilesystemFileException("Delete of file '{$this->getPath()}' failed.", FilesystemFileException::FILE_DELETE_FAILED);
            }
        }

        /**
         * cleans up cache entries associated with
         * the "original" file
         */
        protected function deleteCacheEntries(): void
        {
            if (($cachePath = $this->folder->getCachePath(true))) {

                $di = new DirectoryIterator($cachePath);

                foreach ($di as $fileinfo) {
                    if ($fileinfo->isDot() ||
                        !$fileinfo->isFile() ||
                        !str_starts_with($fileinfo->getFilename(), $this->filename)
                    ) {
                        continue;
                    }

                    unlink($fileinfo->getRealPath());
                }
            }
        }

        /**
         * remove all cache entries of the file
         */
        public function clearCacheEntries(): void
        {
            $this->deleteCacheEntries();
        }

        /**
         * retrieve information about cached files
         * @return array|false information
         */
        public function getCacheInfo(): array|false
        {
            if (($cachePath = $this->folder->getCachePath(true))) {
                $size = 0;
                $count = 0;

                $di = new DirectoryIterator($cachePath);

                foreach ($di as $fileinfo) {
                    if ($fileinfo->isDot() ||
                        !$fileinfo->isFile() ||
                        !str_starts_with($fileinfo->getFilename(), $this->filename)
                    ) {
                        continue;
                    }
                    ++$count;
                    $size += $fileinfo->getSize();
                }
                return ['count' => $count, 'totalSize' => $size];
            }
            return false;
        }

        /**
         * return all filesystem files instances within a certain folder
         *
         * @param FilesystemFolder $folder
         * @return array filesystem files
         * @throws Exception\FilesystemFolderException
         * @throws FilesystemFileException
         */
        public static function getFilesystemFilesInFolder(FilesystemFolder $folder): array
        {
            $files = [];

            $glob = glob($folder->getPath() . '*', GLOB_NOSORT);

            if ($glob !== false) {

                foreach ($glob as $f) {
                    if (!is_dir($f)) {
                        if (!isset(self::$instances[$f])) {
                            self::$instances[$f] = new self($f);
                        }

                        $files[] = self::$instances[$f];
                    }
                }
            }

            return $files;
        }

        /**
         * clean up $filename and avoid duplicate filenames within folder $folder
         * the cleanup is simple and does not take reserved filenames into consideration
         * (e.g. PRN or CON on Windows systems)
         * @see https://msdn.microsoft.com/en-us/library/aa365247
         *
         * @param string $filename
         * @param FilesystemFolder $folder
         * @param integer $ndx starting index used in the renamed file
         * @return string
         */
        public static function sanitizeFilename(string $filename, FilesystemFolder $folder, int $ndx = 2): string
        {
            // remove any characters which are not allowed in any file system

            $filename = Text::toSanitizedFilename($filename);

            if (!file_exists($folder->getPath() . $filename)) {
                return $filename;
            }

            $pathinfo = pathinfo($filename);

            $pathinfo['extension'] = !empty($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';

            while (file_exists($folder->getPath() . sprintf('%s(%d)%s', $pathinfo['filename'], $ndx, $pathinfo['extension']))) {
                ++$ndx;
            }

            return sprintf('%s(%d)%s', $pathinfo['filename'], $ndx, $pathinfo['extension']);
        }
    }