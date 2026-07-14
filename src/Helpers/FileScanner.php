<?php

    namespace App\Helpers;

    class FileScanner
    {
        public static function globRecursive(string $pattern): array
        {
            $files = glob($pattern) ?: [];

            foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR) as $dir) {
                $files = array_merge(
                    $files,
                    self::globRecursive($dir . '/' . basename($pattern))
                );
            }
            return $files;
        }
    public static function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatch) &&
            preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return $nsMatch[1] . '\\' . $classMatch[1];
        }
        return null;
    }
}