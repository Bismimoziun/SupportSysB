<?php
$ch = curl_init('http://localhost:8000/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$body = curl_exec($ch);
$err  = curl_error($ch);
curl_close($ch);

echo "<h2>cURL result:</h2>";
echo "<p>Response: " . htmlspecialchars($body ?: 'EMPTY') . "</p>";
echo "<p>Error: " . htmlspecialchars($err ?: 'none') . "</p>";

$ch2 = curl_init('http://127.0.0.1:8000/health');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
$body2 = curl_exec($ch2);
$err2  = curl_error($ch2);
curl_close($ch2);

echo "<h2>127.0.0.1 result:</h2>";
echo "<p>Response: " . htmlspecialchars($body2 ?: 'EMPTY') . "</p>";
echo "<p>Error: " . htmlspecialchars($err2 ?: 'none') . "</p>";