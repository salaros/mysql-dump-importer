<?php

namespace Salaros\Database;

class MysqlDumpImporter
{
    private static function startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }

    private static function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return $length === 0 || (substr($haystack, -$length) === $needle);
    }

    public static function importSql($dbOptions, $sqlFilePath, $collate = 'utf8_unicode_ci')
    {
        $sqlFile = fopen($sqlFilePath, "r");
        if (empty($sqlFile)) {
            die("Couldn't read SQL dump file located at '$sqlFilePath'");
        }

        $queryCount = 0;
        $queryMultiline = [];

        $mysqliConnection = mysqli_connect(
            $dbOptions['host'],
            $dbOptions['user'],
            $dbOptions['pass'],
            $dbOptions['name']
        );
        mysqli_query($mysqliConnection, "SET NAMES 'utf8' COLLATE '$collate'");

        while (!feof($sqlFile)) {
            $line = trim(fgets($sqlFile));
            if (empty($line)) {
                continue; // Skipping empty lines
            }

            if (self::startsWith($line, '--')) {
                continue; // Skipping lines with standard SQL comments
            }

            if (self::startsWith($line, '/*')) {
                while (!self::endsWith($line, '*/')) {
                    $line = trim(fgets($sqlFile));
                }
                continue; // Skipping through /* commented stuff */ style comment lines
            }

            if (!self::endsWith($line, ';')) {
                $queryMultiline[] = $line;
                continue; // Loop through multi-line SQL statements
            }

            // For some reason utf8mb4 stuff imported only
            self::replaceEncoding($line);

            if (empty($queryMultiline)) {
                $query = $line; // Not in multi-line mode: 1 x line = 1 x query
            } else {
                // Merge multi-line query into a one-liner and reset the container
                $queryMultiline[] = $line;
                $query = implode(PHP_EOL, $queryMultiline);
                $queryMultiline = [];
            }

            // Run the query and check the result
            $queryResult = mysqli_query($mysqliConnection, $query);
            if (1 > $queryResult) {
                die(sprintf('Error: %s %s', $query, PHP_EOL));
            }
            $queryCount++;
        }

        echo sprintf('Executed: %s queries%s', $queryCount, PHP_EOL);
        fclose($sqlFile);
    }

    public static function replaceEncoding($sql, $encOld = 'utf8mb4', $enc = 'utf8')
    {
        $sql = str_ireplace(
            "CHARSET={$encOld}", "CHARSET={$enc}",
            $sql
        );
        return str_ireplace(
            "CHARSET={$encOld}", "CHARSET={$enc}",
            $sql
        );
    }
}
