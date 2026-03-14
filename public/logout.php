<?php
require_once '../src/config.php';

session_destroy();
header("Location: index.php");
exit;