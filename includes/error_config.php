<?php
// محیط تولید: خطاها فقط در لاگ، نه روی صفحه
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
// برای دیباگِ موقت، فقط همین فایل را به display_errors=1 تغییر بده