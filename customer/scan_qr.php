<?php
require_once __DIR__ . '/../includes/init.php';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Scan QR</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <h1>Scan QR Code</h1>
    <div id="reader" style="width:500px"></div>
    <div id="result"></div>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/minified/html5-qrcode.min.js"></script>
    <script src="/assets/js/qr-scanner.js"></script>
</body>
</html>
