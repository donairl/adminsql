<?php
/**
 * Merge and minify PHP, HTML, CSS, JS — no external dependencies.
 * Author: Donny + Copilot
 */

$sourceDir = __DIR__ . '/src'; // Change to your source folder
$outputFile = __DIR__ . '/merged.min.php';
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

function minifyPHP($code) {
    // Remove comments
    $code = preg_replace('!/\*.*?\*/!s', '', $code); // Multi-line
    $code = preg_replace('/\/\/.*(?=\n)/', '', $code); // Single-line
    // Remove blank lines and collapse whitespace
    $code = preg_replace('/^\s*$/m', '', $code);
    $code = preg_replace('/\s+/', ' ', $code);
    return trim($code);
}

function minifyHTML($html) {
    // Remove comments
    $html = preg_replace('/<!--.*?-->/s', '', $html);
    // Collapse whitespace between tags
    $html = preg_replace('/>\s+</', '><', $html);
    // Remove excessive spaces
    $html = preg_replace('/\s{2,}/', ' ', $html);
    return trim($html);
}

function minifyInlineCSS($html) {
    return preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function ($matches) {
        $css = $matches[1];
        $css = preg_replace('!/\*.*?\*/!s', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
        return "<style>$css</style>";
    }, $html);
}

function minifyInlineJS($html) {
    return preg_replace_callback('/<script\b[^>]*>(.*?)<\/script>/is', function ($matches) {
        $js = $matches[1];
        $js = preg_replace('!/\*.*?\*/!s', '', $js);
        $js = preg_replace('/\/\/.*(?=\n)/', '', $js);
        $js = preg_replace('/\s+/', ' ', $js);
        $js = preg_replace('/\s*([{}();,:])\s*/', '$1', $js);
        return "<script>$js</script>";
    }, $html);
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

// Minify PHP
$minified = minifyPHP($mergedContent);

// Minify embedded HTML/CSS/JS
$minified = minifyHTML($minified);
$minified = minifyInlineCSS($minified);
$minified = minifyInlineJS($minified);

// Save output
file_put_contents($outputFile, $minified);
echo "Merged and minified file saved to: $outputFile\n";
