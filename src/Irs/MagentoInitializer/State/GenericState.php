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
    private $archive;

    const MODE_CREATE = 1; // ZIPARCHIVE::CREATE

    const TYPE_VAR   = 'var';
    const TYPE_MEDIA = 'media';
    const TYPE_DUMP  = 'dump';

    public function __construct($fileName)
    {
        $this->archive = new \ZipArchive();
        $newFile = !file_exists($fileName);

        if (true !== $this->archive->open($fileName, self::MODE_CREATE)) {
            throw new \InvalidArgumentException('Cannot ' . ($newFile ? 'create' : 'open') . " '$fileName'.");
        }

        if (!$newFile) {
            if (__CLASS__ != $this->archive->comment) {
                throw new \InvalidArgumentException('Incorrect state file.');
            }
        } else {
            $this->archive->setArchiveComment(__CLASS__);
        }
    }

    public function save()
    {
        $fileName = $this->archive->filename;
        if (true !== $this->archive->close()) {
            throw new \RuntimeException("Unable to save state.");
        }

        $result =  $this->archive->open($fileName, self::MODE_CREATE);
        if ($result !== true) {
            $msg = $this->getZipErrorMessageByCode($result);
            throw new \RuntimeException('Unable to save state ' . ($msg ? "($msg)" : '') . '');
        }

        return $this;
    }

    public function setVar($path)
    {
        $this->delete(self::TYPE_VAR);
        $this->addDirectory(self::TYPE_VAR, $path);

        return $this;
    }

    public function setMedia($path)
    {
        $this->delete(self::TYPE_MEDIA);
        $this->addDirectory(self::TYPE_MEDIA, $path);

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
        $this->delete(self::TYPE_DUMP);
        $this->archive->addFile($path, self::TYPE_DUMP);

        return $this;
    }

    public function extractDump($destination)
    {
        $this->extractByType(self::TYPE_DUMP, $destination);

        return $this;
    }

    public function extractVar($destination)
    {
        $this->extractByType(self::TYPE_VAR, $destination);

        return $this;
    }

    public function extractMedia($destination)
    {
        $this->extractByType(self::TYPE_MEDIA, $destination);

        return $this;
    }

    protected function extractByType($type, $destination)
    {
        if (!$this->isCorrectType($type)) {
            throw new \RuntimeException('Incorrect type.');
        }
        if (!is_dir($destination)) {
            throw new \InvalidArgumentException("Destination should point to directory.");
        }
        if (!is_readable($destination)) {
            throw new \InvalidArgumentException("Destination should be readable.");
        }

        $entriesToExtract = array();

        for ($i = 0; $i < $this->archive->numFiles; $i++) {
            $name = $this->archive->getNameIndex($i);
            if ($type == substr($name, 0, strlen($type))) {
                $entriesToExtract[] = $name;
            }
        }

        $this->archive->extractTo($destination, $entriesToExtract);
    }

    protected function addDirectory($type, $path)
    {
        if (!$this->isCorrectType($type)) {
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
            $localName = str_replace(DIRECTORY_SEPARATOR, '/', $localName);
            if ($item->isFile()) {
                $this->archive->addFile($item->getPathname(), $type . '/' . $localName);
            } else if ($item->isDir()) {
                $this->archive->addEmptyDir($type . '/'  . $localName);
            }
        }
    }

    protected function delete($type = null)
    {
        if ($type !== null && !in_array($type, array(self::TYPE_DUMP, self::TYPE_MEDIA, self::TYPE_VAR))) {
            throw new \RuntimeException('Incorrect type.');
        }
        for ($i = 0; $i < $this->archive->numFiles; $i++) {
            if ($type === null || $type == substr($this->archive->getNameIndex($i), 0, strlen($type))) {
                $this->archive->deleteIndex($i);
            }
        }
    }

    protected function isCorrectType($type)
    {
        return in_array($type, array(self::TYPE_DUMP, self::TYPE_MEDIA, self::TYPE_VAR));
    }

    protected function getZipErrorMessageByCode($code)
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

