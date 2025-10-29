<?php

declare(strict_types=1);

use Php\Src\Authentification;
use Php\Src\Connection;
use Php\Src\Conversations;
use Php\Src\Messages;
use Php\Src\Users;

require __DIR__ ."/../vendor/autoload.php";

// API :

$users_id = ['admin'=>1, 'user1'=>2,'user2'=> 3];

$users_db = new Users(Connection::connect());
$auth = new Authentification();    
$conversations_db = new Conversations(Connection::connect());
$messages_db = new Messages(Connection::connect());

$method = $_SERVER['REQUEST_METHOD'];
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/');

header('Content-Type:application/json');

function httpOk(int $httpCode, ?array $payload = []): never{
    http_response_code($httpCode);
    if($httpCode === 204){exit;}
    echo json_encode(['data'=> $payload]);
    exit;
}
function httpFail(int $httpCode, string $error): never{
    http_response_code($httpCode);
    echo json_encode(['error'=> $error]);
    exit;
}

switch(true){
    // ON RECUPERE LES USERS DE LA DB
    case $method == 'GET' && $path == '/users':
        $userlist = $users_db->getAll();
        httpOk(200, $userlist);
    
    // ON RECUPERE UN USER SPECIFIQUE AVEC SON ID
    case $method == 'GET' && preg_match('#^/users/(?P<id>\d+)$#', $path, $m):
        $id = (int)$m['id'];
        $user = $users_db->getUserById($id);
        if($user['error']) {
            httpFail(404, "User $id not found");
        }
        httpOk(200, $user['data']);
    
    // ON RECUPERE TOUT LES MESSAGES D UN USER SPECIFIQUE VIA SON ID
    case $method == "GET"&& preg_match("#^/users/(?P<recipient_id>\d+)/messages$#", $path, $m):{
        $recipient_id = (int)$m["recipient_id"];
        $messages = $messages_db->getMessagesBetween($users_id['admin'], $recipient_id);
        if(empty($messages)) {
            httpFail(400, 'Vide');
        }
        httpOk(200, $messages);
    }

    // ON RECUPERE TOUTES LES CONVERSATIONS -si conversations-
    case $method == 'GET' && $path == '/conversations':
        $conversations = $conversations_db->getAll();
        httpOk(200, $conversations['data']);

    // ON RECUPERE UNE CONVERSATION AVEC SON ID -si conversation-
    case $method == 'GET'&& preg_match('#^/conversations/(?P<id>\d+)$#', $path, $m):
        $id = (int)$m['id'];
        $conversation = $conversations_db->getConversationById($id);
        if(!$conversation) {
            httpFail(404, "Conversation $id doesn't exist");
        }
        httpOk(200, $conversation);
    
    // ON RECUPERE LES MESSAGES D'UNE CONVERSATION -si conversation-
    case $method == 'GET'&& preg_match('#^/conversations/(?P<convId>\d+)/messages$#', $path, $m):
        $convId = (int)$m['convId'];
        $messages = $messages_db->getMessageByConversationId($convId);
        httpOk(200, $messages ?? []);
    
    // ON RECUPERE UN MESSAGE PAR SON ID
    case $method == "GET"&& preg_match("#^/messages/(?P<id>\d+)#", $path, $m):
        $id = (int)$m["id"];
        $message = $messages_db->getMessageById($id);
        if(!$message) {
            httpFail(404, "Message $id doesn't exist");
        }
        httpOk(200, $message);
    
    // ON FAIT LA VALIDATION MDP + TODO : CHANGER LE TYPE DE RETOUR
    case $method == "POST"&& $path == "/auth":
        $username = trim($_POST["username"] ?? '');
        $password = trim($_POST["password"] ?? '');
        if(empty($username) || empty($password)) {
            httpFail(422, "Veuillez renseigner les 2 champs svp");
        }
        $errLog = $auth->login($username, $password);
        if($errLog['error']) {
            httpFail(401, $errLog['reason']);
        }
        httpOk(200, $errLog['data']);

    // ON POSTE UN MESSAGE DANS UNE CONVERSATION AVEC CONVID -si conversation-
    case $method == "POST"&& preg_match("#^/conversations/(?P<conv_id>\d+)/messages$#", $path, $m):
        $conv_id = (int)$m["conv_id"];
        $message = $_POST["message"] ??"";
        $user_id = (int)$_POST["user_id"];
        if(empty($conv_id) || empty($user_id)) {
            httpFail(400, "Invalid POST request");
        }
        $res = $messages_db->addMessage($user_id, $conv_id, $message);
        if(!$res) {
            httpFail(500, "Insert failed");
        }
        httpOk(201, $res);
        
    // ON CREE UNE CONVERSATION -si conversation-
    case $method == "POST"&& $path == '/conversations':
        $main_user_id = (int)$_POST['user_id'] ?? "";
        $recipient_id = (int)$_POST['recipient_id'] ?? "";
        $name = (string)$_POST['name'] ?? "";
        if(empty($recipient_id) || empty($name) || empty($main_user_id)){httpFail(400, "Invalid POST request");}
        $res = $conversations_db->addConversation($main_user_id, $recipient_id, $name);
        if(!$res){
            httpFail(500, "Oops, la DB a pas aimé");
        }
        httpOk(201, $res);

    // ON UPDATE UN MESSAGE AVEC SON ID
    case $method == "PATCH" && preg_match("#^/messages/(?P<msg_id>\d+)$#", $path, $m):
        $message_id = (int)$m["msg_id"];
        $body = file_get_contents('php://input');
        if($body === false || $body === ''){
            httpFail(400, "Empty request, did you mean to DELETE ?");
        }
        try{
            $content = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if(!is_array($content)){
                httpFail(400, "Invalid JSON");
            }
            $res = $messages_db->updateMessage($message_id, (string)$content['content']);
            httpOk(200, $res);
        }catch(JsonException $e){
            httpFail(400, 'Bad JSON');
        }
        
    // ON UPDATE UNE CONVERSATION AVEC SON ID -si conversation-
    case $method == "PATCH" && preg_match("#^/conversations/(?P<conv_id>\d+)$#", $path, $m):
        $conv_id = (int)$m["conv_id"];
        $body = file_get_contents("php://input");
        if($body === false || $body === ""){
            httpFail(400, 'Empty request, did you mean to DELETE ?');
        }
        try {
            $content = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if(!is_array($content)){
                httpFail(400, 'Bad JSON');
            }
            $res = $conversations_db->updateConversation($conv_id, (string)$content['name']);
            if(!$res){httpFail(500, 'DB UPDATE error');}
            httpOk(200, $res);
        }catch(JsonException $e){
            httpFail(400, $e->getMessage());
        }
    
    // DELETE USERS BY ID
    case $method == "DELETE"&& preg_match('#^/users/(?P<user_id>\d+)$#', $path, $m):
        $user_id = (int)$m['user_id'];
        $res = $users_db->deleteUserById($user_id);
        if(!$res){httpFail(400, 'DB DELETE error');}
        httpOk(204);

    // DELETE MESSAGES BY ID
    case $method == 'DELETE'&& preg_match('#^/messages/(?P<msg_id>\d+)/$#', $path, $m):
        $msg_id = (int)$m['msg_id'];
        $res = $messages_db->deleteMessageById($msg_id);
        if(!$res){httpFail(400, 'DB DELETE failed');}
        httpOk(204);
    
    // GET ALL MESSAGES
    case $method == 'GET' && $path == '/messages':
        httpOk(200,$messages_db->getAllMessages());
    
    // DELETE CONVERSATION BY ID
    case $method == 'DELETE' && preg_match('#^/conversations/(?P<conv_id>\d+)$#', $path, $m):
        $conv_id = (int)$m['conv_id'];
        $res = $conversations_db->deleteConversation($conv_id);
        if($res['error']){httpFail(404,$res['reason']);}
        httpOk(204);

    case $method == 'POST' && $path == '/users':
        $res = $auth->createUser($_POST['username'], $_POST['password'], $_POST['phone']);
        if($res['error']){httpFail(400,$res['data']);}
        httpOk(201, $res['data']);

    case $method == 'GET' && preg_match('#^/users/(?P<user_id>\d+)/conversations$#', $path, $m):
        $user_id = (int)$m['user_id'];
        $res = $conversations_db->getConversationsAndLastMessagesFromUserId($user_id);
        if($res['error']){
            httpFail(400,$res['data']);
        }
        httpOk(200, $res['data']);

    default:
        http_response_code(404);
        echo json_encode(['error'=> "URI Doesn't exist"]);
}