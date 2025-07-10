<?php
// Create OTP table for Tutor Login Popup
require_once '../../../wp-load.php';
require_once 'includes/tutor-login-popup.php';

$popup = new TutorLoginPopup();
$popup->create_otp_table();
echo 'OTP table created successfully!';
?>
