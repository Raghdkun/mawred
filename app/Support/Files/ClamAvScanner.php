<?php

declare(strict_types=1);

namespace App\Support\Files;

use RuntimeException;

/**
 * Streams a file to clamd (INSTREAM). Fails closed: if clamd can't be reached, the upload is
 * refused rather than accepted unscanned.
 */
final readonly class ClamAvScanner implements FileScanner
{
    public function __construct(private string $host, private int $port = 3310, private float $timeout = 10.0) {}

    public function isClean(string $path): bool
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $error, $this->timeout);
        $file = @fopen($path, 'rb');

        if ($socket === false || $file === false) {
            throw new RuntimeException('The virus scanner is unavailable.');
        }

        try {
            stream_set_timeout($socket, (int) ceil($this->timeout));
            fwrite($socket, "zINSTREAM\0");

            while (! feof($file)) {
                $chunk = (string) fread($file, 8192);

                if ($chunk !== '') {
                    fwrite($socket, pack('N', strlen($chunk)).$chunk);
                }
            }

            fwrite($socket, pack('N', 0));
            $reply = trim((string) fgets($socket), "\0\n ");

            return str_ends_with($reply, 'OK') && ! str_contains($reply, 'FOUND');
        } finally {
            fclose($file);
            fclose($socket);
        }
    }
}
