<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer\State;

class GenericState implements StateInterface
{
    private $_archive;

    const MODE_CREATE = 1; // ZIPARCHIVE::CREATE

    const TYPE_VAR   = 'var';
    const TYPE_MEDIA = 'media';
    const TYPE_DUMP  = 'dump';

    public function __construct($fileName)
    {
        $this->_archive = new \ZipArchive();
        $newFile = !file_exists($fileName);

        if (true !== $this->_archive->open($fileName, self::MODE_CREATE)) {
            throw new \InvalidArgumentException('Cannot ' . ($newFile ? 'create' : 'open') . " '$fileName'.");
        }

        if (!$newFile) {
            if (__CLASS__ != $this->_archive->comment) {
                throw new \InvalidArgumentException('Incorrect state file.');
            }
        } else {
            $this->_archive->setArchiveComment(__CLASS__);
        }
    }

    public function save()
    {
        $fileName = $this->_archive->filename;
        if (true !== $this->_archive->close()) {
            throw new \RuntimeException("Unable to save state.");
        }

        $result =  $this->_archive->open($fileName, self::MODE_CREATE);
        if ($result !== true) {
            $msg = $this->_getZipErrorMessageByCode($result);
            throw new \RuntimeException('Unable to save state ' . ($msg ? "($msg)" : '') . '');
        }

        return $this;
    }

    public function setVar($path)
    {
        $this->_delete(self::TYPE_VAR);
        $this->_addDirectory(self::TYPE_VAR, $path);

        return $this;
    }

    public function setMedia($path)
    {
        $this->_delete(self::TYPE_MEDIA);
        $this->_addDirectory(self::TYPE_MEDIA, $path);

        return $this;
    }

    public function setDump($path)
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("Path should point to file.");
        }
        if (!is_readable($path)) {
            throw new \InvalidArgumentException("Path should be readable.");
        }
        $this->_delete(self::TYPE_DUMP);
        $this->_archive->addFile($path, self::TYPE_DUMP);

        return $this;
    }

    public function extractDump($destination)
    {
        $this->_extractByType(self::TYPE_DUMP, $destination);

        return $this;
    }

    public function extractVar($destination)
    {
        $this->_extractByType(self::TYPE_VAR, $destination);

        return $this;
    }

    public function extractMedia($destination)
    {
        $this->_extractByType(self::TYPE_MEDIA, $destination);

        return $this;
    }

    protected function _extractByType($type, $destination)
    {
        if (!$this->_isCorrectType($type)) {
            throw new \RuntimeException('Incorrect type.');
        }
        if (!is_dir($destination)) {
            throw new \InvalidArgumentException("Destination should point to directory.");
        }
        if (!is_readable($destination)) {
            throw new \InvalidArgumentException("Destination should be readable.");
        }

        $entriesToExtract = array();

        for ($i = 0; $i < $this->_archive->numFiles; $i++) {
            $name = $this->_archive->getNameIndex($i);
            if ($type == substr($name, 0, strlen($type))) {
                $entriesToExtract[] = $name;
            }
        }

        $this->_archive->extractTo($destination, $entriesToExtract);
    }

    protected function _addDirectory($type, $path)
    {
        if (!$this->_isCorrectType($type)) {
            throw new \RuntimeException('Incorrect type.');
        }
        if (!is_dir($path)) {
            throw new \InvalidArgumentException("Path should point to directory.");
        }
        if (!is_readable($path)) {
            throw new \InvalidArgumentException("Path should be readable.");
        }

        $iteratorFlags = \FilesystemIterator::KEY_AS_PATHNAME |
            \FilesystemIterator::CURRENT_AS_FILEINFO |
            \FilesystemIterator::SKIP_DOTS;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, $iteratorFlags)) as $item) {
            if (!$item->isReadable()) {
                throw new \InvalidArgumentException("Unable to read '{$item->getPathname()}'.");
            }
            $localName = ltrim(substr($item->getPathname(), strlen($path)), '\\/');
            if ($item->isFile()) {
                $this->_archive->addFile($item->getPathname(), $type . '/' . $localName);
            } else if ($item->isDir()) {
                $this->_archive->addEmptyDir($type . '/'  . $localName);
            }
        }
    }

    protected function _delete($type = null)
    {
        if ($type !== null && !in_array($type, array(self::TYPE_DUMP, self::TYPE_MEDIA, self::TYPE_VAR))) {
            throw new \RuntimeException('Incorrect type.');
        }
        for ($i = 0; $i < $this->_archive->numFiles; $i++) {
            if ($type === null || $type == substr($this->_archive->getNameIndex($i), 0, strlen($type))) {
                $this->_archive->deleteIndex($i);
            }
        }
    }

    protected function _isCorrectType($type)
    {
        return in_array($type, array(self::TYPE_DUMP, self::TYPE_MEDIA, self::TYPE_VAR));
    }

    protected function _getZipErrorMessageByCode($code)
    {
        switch ($code) {
            // ZIPARCHIVE::ER_EXISTS
            case 10:  return 'File already exists.';

            // ZIPARCHIVE::ER_INCONS
            case 21:  return 'Zip archive inconsistent.';

            // ZIPARCHIVE::ER_INVAL
            case 18:  return 'Invalid argument.';

            // ZIPARCHIVE::ER_MEMORY
            case 14:  return 'Malloc failure.';


            // ZIPARCHIVE::ER_NOENT
            case 9:  return 'No such file.';

            // ZIPARCHIVE::ER_NOZIP
            case 19: return 'Not a zip archive.';

            // ZIPARCHIVE::ER_OPEN
            case 11: return "Can't open file.";

            // ZIPARCHIVE::ER_READ
            case 5:  return 'Read error.';

            // ZIPARCHIVE::ER_SEEK
            case 4:  return 'Seek error.';

            default: return null;
        }
    }
}

