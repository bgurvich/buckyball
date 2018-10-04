<?php

namespace Buckyball\Core\Controller\Response;

use Buckyball\Core\Controller\Response;

class File extends Response
{
    public function setContents(string $contents)
    {

    }

    public function setFile(string $filename)
    {

    }

    public function render()
    {

    }

    /**
     * Send file download to client
     *
     * @param        $source
     * @param null   $fileName
     * @param string $disposition
     * @param string $root
     * @return exit
     */
    public function sendFile($source, $fileName = null, $disposition = 'attachment', $root = null)
    {
        $this->BSession->close();

        if (!file_exists($source)) {
            $this->status(404, (('File not found')), 'File not found');
            $this->shutdown(__METHOD__);
            return;
        }

        $source = realpath($source);
        if (!$this->BUtil->isPathWithinRoot($source, $root)) {
            $this->status(403, (('Invalid file location')), 'Invalid file location');
            $this->shutdown(__METHOD__);
            return;
        }

        if (!$fileName) {
            $fileName = basename($source);
        }

        $this->header([
            'Pragma: public',
            'Cache-Control: must-revalidate, post-check=0, pre-check=0',
            'Content-Length: ' . filesize($source),
            'Last-Modified: ' . date('r'),
            'Content-Type: ' . $this->fileContentType($source),
            'Content-Disposition: ' . $disposition . '; filename=' . $fileName,
        ]);

        //echo file_get_contents($source);
        $fs = fopen($source, 'rb');
        $fd = fopen('php://output', 'wb');
        while (!feof($fs)) fwrite($fd, fread($fs, 8192));
        fclose($fs);
        fclose($fd);

        $this->shutdown(__METHOD__);
    }

    /**
     * Send text content as a file download to client
     *
     * @param string $content
     * @param string $fileName
     * @param string $disposition
     * @return exit
     */
    public function sendContent($content, $fileName = 'download.txt', $disposition = 'attachment')
    {
        $this->BSession->close();

        $this->header([
            'Pragma: public',
            'Cache-Control: must-revalidate, post-check=0, pre-check=0',
            'Content-Type: ' . $this->fileContentType($fileName),
            'Content-Length: ' . strlen($content),
            'Last-Modified: ' . date('r'),
            'Content-Disposition: ' . $disposition . '; filename=' . $fileName,
        ]);
        echo $content;
        $this->shutdown(__METHOD__);
    }
}