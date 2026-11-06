<?php

declare(strict_types=1);

namespace Raips;

/**
 * The router under `php -S`: it hands every request to a Preview and writes
 * what comes back. Nothing here reads the disk, so a change to a build shows
 * on the next reload of the process only.
 */
final class Serve
{
    public static function run(string $dir, string $host, int $port): void
    {
        $project = Project::load($dir);
        $problems = $project->problems();
        if ($problems !== []) {
            throw new BuildError($problems);
        }

        $preview = new Preview($project->build());

        fwrite(STDOUT, Version::NAME . ' ' . Version::VERSION . ' preview of ' . $project->name . "\n");
        foreach ($preview->paths() as $path) {
            fwrite(STDOUT, '  ' . $path . "\n");
        }
        fwrite(STDOUT, 'listening on http://' . $host . ':' . $port . "/\n");

        $address = 'tcp://' . $host . ':' . $port;
        $server = @stream_socket_server($address, $errno, $errstr);
        if ($server === false) {
            throw new \RuntimeException('cannot listen on ' . $address . ': ' . $errstr);
        }

        while (true) {
            $client = @stream_socket_accept($server, 30);
            if ($client === false) {
                continue;
            }

            $request = '';
            while (!str_contains($request, "\r\n\r\n")) {
                $chunk = fread($client, 1024);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $request .= $chunk;
                if (strlen($request) > 16384) {
                    break;
                }
            }

            $path = '/';
            if (preg_match('#^[A-Z]+ (\S+)#', $request, $m) === 1) {
                $path = $m[1];
            }

            $answer = $preview->respond($path);
            $body = $answer['body'];

            fwrite($client, 'HTTP/1.1 ' . $answer['status'] . ' ' . ($answer['status'] === 200 ? 'OK' : 'Not Found') . "\r\n");
            fwrite($client, 'content-type: ' . $answer['type'] . "\r\n");
            fwrite($client, 'content-length: ' . strlen($body) . "\r\n");
            fwrite($client, "connection: close\r\n\r\n");
            fwrite($client, $body);
            fclose($client);
        }
    }
}
