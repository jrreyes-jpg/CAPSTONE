<?php

$name = $_POST['name'];
$email = $_POST['email'];
$message = $_POST['message'];

$to = "ejimenez.edge@gmail.com";
$subject = "New Client Inquiry";

$body = "Name: $name\nEmail: $email\nMessage: $message";

mail($to, $subject, $body);

echo "Message sent!";

?>