<html>
<head>
<title>PHPMailer - Sendmail advanced test</title>
</head>
<body>

<?php

require_once('../class.phpmailer-lite.php');

$mail = new PHPMailerLite(true); // the true param means it will throw exceptions on errors, which we need to catch
$mail->IsSendmail(); // telling the class to use SendMail transport

try {
  $mail->SetFrom('me@you.com', 'Toni Pirhonen');
  $mail->AddAddress('torrenttiposti@gmail.com', 'Toni Pirhonen');
  $mail->Subject = 'Testi, jospa oiski näi heleppoo :D';
  $mail->AltBody = 'To view the message, please use an HTML compatible email viewer!'; // optional - MsgHTML will create an alternate automatically
  $mail->MsgHTML(file_get_contents('contents.html'));
  $mail->AddAttachment('images/phpmailer.gif');      // attachment
  $mail->AddAttachment('images/phpmailer_mini.gif'); // attachment
  $mail->Send();
  echo "Message Sent OK</p>\n";
} catch (phpmailerException $e) {
  echo $e->errorMessage(); //Pretty error messages from PHPMailer
} catch (Exception $e) {
  echo $e->getMessage(); //Boring error messages from anything else!
}
?>

</body>
</html>
