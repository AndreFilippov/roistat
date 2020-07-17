<?php
if(!$_POST) sendMessage('Укажите данные', true);

$data_post = $_POST;

$data_post = changeDataInfo($data_post);

checkRecord($data_post);

function changeDataInfo($data){
    $empty = [];

    foreach ($data as $key => $value) {
        if(empty(trim($value))) $empty[] = $key;
    }

    if($empty){
        sendMessage('Введите: '.implode(', ',$empty), true);
    }

    if((float)$data['amount'] < 0)
        sendMessage('Сумма сделки не может быть меньше 0',true);

    $data['email'] = trim($data['email']);
    $data['amount'] = (float)$data['amount'];
    $data['phone'] = preg_replace('![^0-9]+!', '', $data['phone']);

    return $data;
}

function sendMessage($message, $error = false){
    $query = http_build_query(['message' => $message, 'error' => $error]);
    header("Location: /?$query");
    die();
}

function checkRecord($data){
    $phone = $data['phone'];

    $answer = sendRequest("Leads/search?criteria=((Phone:equals:$phone))");
    if(is_null($answer)){
        createRecord($data);
    } else {
        if(isset($answer['status']) && $answer['status'] == 'error'){
            sendMessage('Возникла ошибка: '.$answer['message'], true);
        } elseif(isset($answer['data']) && count($answer['data'])) {
            $record = $answer['data'][0];
            createLead($data, $record);
        } else {
            sendMessage('Возникла ошибка при обработке данных', true);
        }
    }
}

function createLead($data, $dataRecord){
    $record_id = $dataRecord['id'];
    if(!(int)$record_id) sendMessage('Возникла ошибка во время создания сделки', true);
    $dataLead = [
        "Deals" => [
            'Deal_Name' => $data['name'],
            "Amount" => $data['amount']
        ]
    ];
    $answer = sendRequest("Leads/$record_id/actions/convert", $dataLead, true, true);
    if(isset($answer['data']) && ($lead_data = $answer['data'][0]) && $lead_data['status'] != 'error'){
        $message = 'Сделка была создана: <br />';
        $message .= 'Contacts: '.$lead_data['Contacts'].' <br />';
        $message .= 'Deals: '.$lead_data['Deals'].' <br />';
        $message .= 'Accounts: '.$lead_data['Accounts'].' <br />';
        sendMessage($message);
    } elseif($answer['data'][0]['status'] == 'error') {
        sendMessage('Во время создании сделки произошла ошибка: '.$answer['data'][0]['code'], true);
    }
}

function createRecord($data){
    $dataUser = [
        'Last_Name' => $data['name'],
        'Email' => $data['email'],
        'Company' => $data['company'],
        'Amount' => $data['amount'],
        'Phone' => $data['phone']
    ];

    $answer = sendRequest('Leads', $dataUser, true);
    if(isset($answer['data']) && isset($answer['data'][0]) && $answer['data'][0]['status'] == 'success'){
        sendMessage('Запись была создана');
    } else {
        $e_data = $answer['data'][0];
        $error = $e_data['details']['api_name'].' '.$e_data['message'];
        sendMessage('Во время создания записи возникла ошибка: '.$error, true);
    }
}

function sendRequest($url, $data = '', $post = false, $debug = false){

    $headers[] = 'Authorization: Zoho-oauthtoken 1000.9da876793969f96099c0d3559b147d3f.00879a53ccbf45afd41675e57ba4ee29';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL,'https://www.zohoapis.com/crm/v2/'.$url);
    curl_setopt($ch, CURLOPT_POST, $post);
    if($post) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['data' => [$data]], JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $server_output = curl_exec($ch);
    curl_close ($ch);
    return json_decode($server_output, true);
}
