<?php
require_once 'includes/auth.php';

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: index.php');
exit;