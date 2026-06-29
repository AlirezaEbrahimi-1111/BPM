<?php
header('Content-Type: text/plain; charset=utf-8');
echo "REMOTE_ADDR: "        . ($_SERVER['REMOTE_ADDR'] ?? '-') . "\n";
echo "X-Forwarded-For: "    . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '-') . "\n";
echo "CF-Connecting-IP: "   . ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '-') . "\n";
echo "X-Real-IP: "          . ($_SERVER['HTTP_X_REAL_IP'] ?? '-') . "\n";