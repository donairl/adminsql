<?php
/**
 * Merge and minify PHP, HTML, CSS, JS — no external dependencies.
 * Author: Donny + Copilot
 */

$sourceDir = __DIR__ . '/..'; // Change to your source folder
$outputFile = __DIR__ . '/index.php';
$processed = [];

function inlineIncludes($filePath, &$processed, $sourceDir) {
    if (isset($processed[$filePath])) return '';
    $processed[$filePath] = true;
    $content = file_get_contents($filePath);

    // Replace include/require with actual content
    $pattern = '/\b(include|require)(_once)?\s*\(?[\'"](.+?\.php)[\'"]\)?\s*;/';
    $content = preg_replace_callback($pattern, function ($matches) use ($filePath, &$processed, $sourceDir) {
        $includePath = $matches[3];

        // Check multiple possible locations for the include
        $possiblePaths = [
            dirname($filePath) . '/' . $includePath,           // Relative to current file
            $sourceDir . '/' . $includePath,                   // Relative to source directory
            __DIR__ . '/' . $includePath,                      // Relative to project root
            __DIR__ . '/src/' . $includePath,                  // In src directory
        ];

        $includedPath = null;
        foreach ($possiblePaths as $path) {
            $realPath = realpath($path);
            if ($realPath && file_exists($realPath)) {
                $includedPath = $realPath;
                break;
            }
        }

        if (!$includedPath) {
            return "// Missing: {$includePath}\n";
        }

        $includedContent = inlineIncludes($includedPath, $processed, $sourceDir);
        // Strip PHP tags from included content
        $includedContent = preg_replace('/^<\?php\s*/', '', $includedContent);
        $includedContent = preg_replace('/\?>$/', '', $includedContent);
        return $includedContent;
    }, $content);

    return $content;
}


// Merge files - only class files, keep entry points separate
$mergedContent = '';
$filesToMerge = [];

// Add all PHP files from src directory (classes only)
foreach (glob($sourceDir . '/*.php') as $file) {
    $filesToMerge[] = $file;
}

foreach ($filesToMerge as $file) {
    $fileContent = inlineIncludes($file, $processed, $sourceDir);
    // Strip PHP tags from main files too (except the first one)
    if (empty($mergedContent)) {
        // First file - keep the opening PHP tag
        $fileContent = preg_replace('/^<\?php\s*/', '<?php ', $fileContent);
    } else {
        // Subsequent files - strip PHP tags
        $fileContent = preg_replace('/^<\?php\s*/', '', $fileContent);
        $fileContent = preg_replace('/\?>$/', '', $fileContent);
    }
    $mergedContent .= $fileContent . "\n";
}

// Remove all PHP closing tags in the middle of the file
$mergedContent = preg_replace('/\?>\s*(?=.)/', '', $mergedContent);

// Save output
file_put_contents($outputFile, $mergedContent);
echo "Merged file saved to: $outputFile\n";
