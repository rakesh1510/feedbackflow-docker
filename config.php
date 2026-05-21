<?php
// FeedbackFlow Configuration
// Edit these values to match your server setup

//define('DB_HOST', 'localhost');

define('DB_HOST', 'db');
//define('DB_NAME', 'feedbackflow');   // keep old app working
define('DB_USER', 'pmaadmin');
define('DB_PASS', 'Romil@15101990!1319');
define('DB_CHARSET', 'utf8mb4');

//define('MASTER_DB_HOST', 'localhost');

define('MASTER_DB_HOST', 'db');
define('MASTER_DB_PORT', 3306);
define('MASTER_DB_NAME', 'feedbackflow_master');
define('MASTER_DB_USER', 'pmaadmin');
define('MASTER_DB_PASS', 'Romil@15101990!1319');

define('DB_ROOT_USER', 'pmaadmin');
define('DB_ROOT_PASS', 'Romil@15101990!1319');

// App settings
define('APP_NAME', 'FeedbackFlow');
//define('APP_URL', 'http://feedbackflow.rakeshprajapati.tech/');

define('APP_URL', 'http://187.77.184.173/feedbackflow');
define('APP_VERSION', '1.0.0');

// Security
define('SECRET_KEY', 'CHANGE_THIS_TO_A_RANDOM_STRING_64_CHARS_LONG_PLEASE');
define('APP_ENCRYPT_KEY', SECRET_KEY);

// File uploads
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'zip']);

//// Email (SMTP)
//define('SMTP_HOST', 'smtp.hostinger.com');
//define('SMTP_PORT', 587);
//define('SMTP_USER', 'contact@aihubpedia.com');
//define('SMTP_PASS', 'Suresh@13121988');
//define('SMTP_FROM', 'contact@aihubpedia.com');
//define('SMTP_FROM_NAME', 'FeedbackFlow');
//define('SMTP_SECURE', 'tls'); // tls or ssl

// Email (SMTP)
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'info@trustnovatech.de');
define('SMTP_PASS', 'Q!Ls$Ay76Bzf#jQQ');
define('SMTP_FROM', 'info@trustnovatech.de');
define('SMTP_FROM_NAME', 'FeedbackFlow');
define('SMTP_SECURE', 'tls'); // tls or ssl

// AI Features (OpenAI)
define('OPENAI_API_KEY', '');
define('OPENAI_MODEL', 'gpt-4o-mini');
define('AI_ENABLED', !empty(OPENAI_API_KEY));

// Slack
define('SLACK_WEBHOOK_URL', '');

// Session
define('SESSION_LIFETIME', 60 * 60 * 24 * 30);

// Timezone
date_default_timezone_set('UTC');

// Environment
define('DEBUG_MODE', false);

// Mail alias
define('MAIL_FROM', SMTP_FROM);
define('MAIL_FROM_NAME', SMTP_FROM_NAME);

// Stripe
define('STRIPE_SECRET_KEY', '');
define('STRIPE_PUBLISHABLE_KEY', '');
define('STRIPE_WEBHOOK_SECRET', '');

// Default language
define('DEFAULT_LANG', 'en');

// Rate limiting
define('RATE_LIMIT_ENABLED', true);
