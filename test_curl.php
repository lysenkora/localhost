<?php
$url = 'http://localhost/index.php?page=get_operations&page=1&per_page=5';
$response = file_get_contents($url);
echo "URL: $url\n";
echo "Response type: " . (json_decode($response) ? 'JSON' : 'HTML') . "\n";
echo "First 500 chars:\n" . substr($response, 0, 500);