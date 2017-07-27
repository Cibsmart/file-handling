<?php
namespace Czim\FileHandling\Support\Download;

use Czim\FileHandling\Contracts\Support\MimeTypeHelperInterface;
use Czim\FileHandling\Contracts\Support\UrlDownloaderInterface;
use Czim\FileHandling\Exceptions\CouldNotRetrieveRemoteFileException;
use Exception;

class UrlDownloader implements UrlDownloaderInterface
{

    /**
     * @var MimeTypeHelperInterface
     */
    protected $mimeTypeHelper;


    /**
     * @param MimeTypeHelperInterface $mimeTypeHelper
     */
    public function __construct(MimeTypeHelperInterface $mimeTypeHelper)
    {
        $this->mimeTypeHelper = $mimeTypeHelper;
    }


    /**
     * Downloads from a URL and returns locally stored temporary file.
     *
     * @param string $url
     * @return string
     */
    public function download($url)
    {
        $localPath = $this->makeLocalTemporaryPath();

        $this->downloadToTempLocalPath($url, $localPath);

        // Remove the query string if it exists, to make sure the extension is valid
        if (false !== strpos($url, '?')) {
            $url = explode('?', $url)[0];
        }

        $pathinfo = pathinfo($url);

        // If the file has no extension, rename the local instance with a guessed extension added.
        if (empty($pathinfo['extension'])) {
            $localPath = $this->renameLocalTemporaryFileWithAddedExtension($localPath, $pathinfo['basename']);
        }

        return $localPath;
    }

    /**
     * @param string $url
     * @param string $localPath
     * @throws CouldNotRetrieveRemoteFileException
     */
    protected function downloadToTempLocalPath($url, $localPath)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $rawFile = curl_exec($ch);
        curl_close($ch);

        try {
            if (false === file_put_contents($localPath, $rawFile)) {
                throw new CouldNotRetrieveRemoteFileException('file_put_contents call failed');
            }

        } catch (Exception $e) {

            throw new CouldNotRetrieveRemoteFileException('file_put_contents call threw an exception', $e->getCode(), $e);
        }
    }

    /**
     * @param string $path
     * @param string $name
     * @return string
     * @throws CouldNotRetrieveRemoteFileException
     */
    protected function renameLocalTemporaryFileWithAddedExtension($path, $name)
    {
        try {
            $extension = $this->mimeTypeHelper->guessExtensionForPath($path);

        } catch (Exception $e) {

            throw new CouldNotRetrieveRemoteFileException(
                "Failed to fill in extension for local file: {$path}",
                $e->getCode(),
                $e
            );
        }

        return $this->renameFile($path, "{$name}.{$extension}");
    }

    /**
     * @return string
     */
    protected function makeLocalTemporaryPath()
    {
        return sys_get_temp_dir() . '/' . uniqid('media-download-');
    }

    /**
     * Renames a local (temp) file and returns the new path to it.
     *
     * @param string $path
     * @param string $newName
     * @return string
     * @throws CouldNotRetrieveRemoteFileException
     */
    protected function renameFile($path, $newName)
    {
        $newPath = pathinfo($path, PATHINFO_DIRNAME) . '/' . $newName;

        if ( ! rename($path, $newPath)) {
            throw new CouldNotRetrieveRemoteFileException("Failed to rename '{$path}' to '{$newName}'");
        }

        return $newPath;
    }

}