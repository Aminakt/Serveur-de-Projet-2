<?php

declare(strict_types=1);

namespace Php\Src;

use DateTimeImmutable;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();


use Firebase\JWT\JWT;
use PDO;
use Php\Src\Connection;
use Php\Src\Users;
use Php\Src\Tokens;

function checkLog(bool $error, string $reason):array{
    return ["error"=> $error,"reason"=> $reason];
}
function normalizedReturn(bool $error, string $data):array{
    if($error)
        return ['error'=>true, 'reason'=>$data];
    return ['error'=>false, 'data'=>$data];
}

final class Authentification {

    private $tokendb;
    private $users_db;
    
    public function __construct(private PDO $pdo){
        $this->tokendb = new Tokens(Connection::connect());
        $this->users_db = new Users(Connection::connect());
    }
    

    public function login(string $username, string $password): array{
        $user = $this->users_db->getUserByUsername($username);
        if($user['error']){
            return ['error'=>true,'reason'=>"Username doesn't exist"];
        }
        if(!password_verify($password, hash: (string)$user['data']['hash_password'])){
            return ['error'=>true, 'reason'=>"Invalid credentials"];
        }
        $userid = (int)$user["data"]["id"];
        $rtC = Utils::base64url_encode(random_bytes(32));
        $rtH = hash_hmac('sha256', $rtC, (string)$_ENV['RT_PEPPER'], true);

        $now = Utils::now();
        $rtExp = $now->modify('+15 day');
        $this->tokendb->storeRefresh($userid, $rtH, $rtExp);
        $at = $this->generationAT($userid, $now);
        // DOIT RETOURNER 2 TOKENS.
        return Utils::dbReturn(false, data: ['at'=>$at, 'rt'=>$rtC, 'conf'=>['sub'=>$userid, 'iat'=>$now->getTimestamp(), 'exp'=>$now->modify('+10 minutes')->getTimestamp()]]);
    }

    public function verifyPassword(string $username, string $password){
        $user = $this->users_db->getUserByUsername($username);
        $hpwd = $user['data']['hash_password'];
        return password_verify($password, $hpwd);
    }

    public function createUser(string $username, string $password, string $phone): array {
        if(strlen($password) < 8){
            return Utils::dbReturn(true, 'password too short');
        }
        $h_pwd = password_hash($password, PASSWORD_BCRYPT);
        $res = $this->users_db->addUser($username, $h_pwd, $phone, $avatar_url ?? 'https://i.pravatar.cc/150?img=4');
        return $res;
    }

    public function generationAT(int $userid, DateTimeImmutable $now):string{
        $conf = [
            'iss' => $_ENV['JWT_ISS'],
            'aud' => $_ENV['JWT_AUD'],
            'iat' => $now->getTimestamp(),
            'nbf' => $now->getTimestamp(),
            'sub' => $userid,
            'exp' => $now->modify('+10 minutes')->getTimestamp(),
        ];
        $accessToken = JWT::encode(payload:$conf, key:$_ENV['JWT_KEY'], alg:'HS256');
        return $accessToken;
    }

        public function refresh(string $rtC): array {
        if ($rtC === '') return ['error'=>true, 'reason'=>'Missing rt'];

        $hash = hash_hmac('sha256', $rtC, $_ENV['RT_PEPPER'], true);
        $row = $this->tokendb->getActiveRT($hash);
        if (!$row) return ['error'=>true, 'reason'=>'Invalid/expired/Revoked RT'];

        $userId = (int)$row['user_id'];
        $this->tokendb->touchLastUsed($hash);

        $newRTC = Utils::base64url_encode(random_bytes(32));
        $newHash = hash_hmac('sha256', $newRTC, $_ENV['RT_PEPPER'], true);
        $newExp = Utils::now()->modify('+15 days');
        $this->tokendb->rotate($userId, $hash, $newHash, $newExp);

        $at = $this->generationAT($userId, Utils::now());

        return ['error'=>false, 'data'=> ['at'=>$at, 'rt'=>$newRTC]];
    }

    public function logout(string $rtC): array {
        $hash = hash_hmac('sha256', $rtC, $_ENV['RT_PEPPER'], true);
        $this->tokendb->revokeByHash($hash);
        return ['error'=>false];
    }
}