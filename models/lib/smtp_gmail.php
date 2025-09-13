<?php

require_once __DIR__ . '/../../models/config/email.php';


function smtp_send_gmail(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
  $host = GMAIL_SMTP_HOST;
  $port = GMAIL_SMTP_PORT;

  
  $fp = stream_socket_client("$host:$port", $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
  if (!$fp) return false;

  $read = function() use ($fp) {
    $data = '';
    while ($line = fgets($fp, 515)) { 
      $data .= $line;
      if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $data;
  };
  $say = function(string $cmd) use ($fp, $read) {
    fwrite($fp, $cmd . "\r\n");
    return $read();
  };

  $read();                   
  $say("EHLO localhost");
  $say("STARTTLS");
  if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
    fclose($fp); return false;
  }
  $say("EHLO localhost");

  
  $say("AUTH LOGIN");
  $say(base64_encode(GMAIL_SMTP_USER));
  $say(base64_encode(GMAIL_SMTP_PASS));

  
  $boundary = "b_" . bin2hex(random_bytes(8));
  $from     = MAIL_FROM;
  $fromName = MAIL_FROM_NAME;

  $headers =
    "From: ".$fromName." <".$from.">\r\n".
    "MIME-Version: 1.0\r\n".
    "Content-Type: multipart/alternative; boundary=\"$boundary\"";

  if ($textBody === '') {
    $textBody = strip_tags(str_replace(["<br>","<br/>","<br />"], "\n", $htmlBody));
  }

  $multipart =
    "--$boundary\r\n".
    "Content-Type: text/plain; charset=UTF-8\r\n\r\n".
    $textBody."\r\n".
    "--$boundary\r\n".
    "Content-Type: text/html; charset=UTF-8\r\n\r\n".
    $htmlBody."\r\n".
    "--$boundary--\r\n";

  
  $say("MAIL FROM:<$from>");
  $say("RCPT TO:<$to>");
  $say("DATA");
  $data =
    "Date: ".date('r')."\r\n".
    "To: <$to>\r\n".
    "Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\n".
    $headers."\r\n\r\n".
    $multipart."\r\n.\r\n";
  fwrite($fp, $data);
  $read();

  $say("QUIT");
  fclose($fp);
  return true;
}


function smtp_gmail(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
  return smtp_send_gmail($to, $subject, $htmlBody, $textBody);
}
