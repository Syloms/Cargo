<?php

function sendNotification($token, $title, $body) {

    $serverKey = "YOUR_FCM_SERVER_KEY_HERE";

    $data = [
        "to" => $token,
        "notification" => [
            "title" => $title,
            "body" => $body,
            "sound" => "default"
        ]
    ];

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, "https://fcm.googleapis.com/fcm/send");
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "Authorization: key=$serverKey",
        "Content-Type: application/json"
    ]);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    curl_exec($curl);
    curl_close($curl);
}
?>
