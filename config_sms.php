<?php
// ---- Clinic + SMS settings ----
date_default_timezone_set('Asia/Manila');      // change to your time zone
define('CLINIC_NAME', 'Health Center');          // shown in every text message
define('SMS_COUNTRY_CODE', '+63');               // used to turn 09xx numbers into +639xx

// 'simulate' = record messages but deliver nothing (safe default for testing)
// 'twilio'   = Twilio (worldwide)    'semaphore' = Semaphore (Philippines)
define('SMS_PROVIDER', 'simulate');

define('TWILIO_SID', '');
define('TWILIO_TOKEN', '');
define('TWILIO_FROM', '');                       // your Twilio number, e.g. +15551234567

define('SEMAPHORE_KEY', '');
define('SEMAPHORE_SENDER', '');                  // optional approved sender name

// Needed only if you call cron_reminders.php from a web URL. Change it, or leave as is to block web access.
define('CRON_KEY', 'change-me');
