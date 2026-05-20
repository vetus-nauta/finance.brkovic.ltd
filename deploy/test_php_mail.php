<?php

$to = $argv[1] ?? '';

if (!$to) {
    echo "Usage: php deploy/test_php_mail.php your@email.com\n";
    exit(1);
}

$code = (string)random_int(100000, 999999);
$subject = 'Captain Fin direct mail test: ' . $code;

$message = "This is a direct PHP mail() test from finance.brkovic.ltd\n\n";
$message .= "Test code: {$code}\n";
$message .= "Time: " . date('c') . "\n";

$headers = [
    'From: Captain Fin <no-reply@finance.brkovic.ltd>',
    'Reply-To: no-reply@finance.brkovic.ltd',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion(),
];

$sent = @mail($to, $subject, $message, implode("\r\n", $headers));

echo "mail() returned: " . ($sent ? "true" : "false") . "\n";
echo "To: {$to}\n";
echo "Subject: {$subject}\n";
echo "Code: {$code}\n";
