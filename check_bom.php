<?php
$files = ['index.php', 'config/database.php', 'config/constants.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            echo "$file has BOM! Remove it.\n";
        } else {
            echo "$file OK\n";
        }
    }
}