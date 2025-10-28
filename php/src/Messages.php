<?php

declare(strict_types=1);

namespace Php\Src;

use PDO;


final class Messages {
    public function __construct(private PDO $pdo) {}

    public function getMessageById(int $id): array|bool {
        $sql = "SELECT * FROM Messages WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        return $message;
    }

    /**
     * Summary of getMessageByConversationId
     * @param int $id
     * @return list<array{id:int,content:non-empty-string,recipient_id:int}
     */
    public function getMessageByConversationId(int $convId):array {
        $sql = 'SELECT * FROM Messages WHERE conversation_id = :convId';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':convId', $convId, PDO::PARAM_INT);
        $stmt->execute();
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $messages;
    }

    /**
     * Summary of addMessage
     * @param int $user_id
     * @param int $conv_id
     * @param string $message
     * @return array|null
     */
    public function addMessage(int $user_id, int $conv_id, string $message): ?array {
        try{
            $insertSql = 'INSERT INTO Messages (sender_id, conversation_id, content) VALUES (:user_id, :conv_id, :message)';
            $stmt = $this->pdo->prepare($insertSql);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':conv_id', $conv_id, PDO::PARAM_INT);
            $stmt->bindValue(':message', $message, PDO::PARAM_STR);
            $stmt->execute();

            $id = (int)$this->pdo->lastInsertId();
            if($id <= 0){return null;}

            $getSql = 'SELECT id, sender_id, conversation_id, content, sent_at  FROM Messages WHERE id = :id';
            $getSql = $this->pdo->prepare($getSql);
            $getSql->bindValue(':id', $id, PDO::PARAM_INT);
            $getSql->execute();
            return $getSql->fetch(PDO::FETCH_ASSOC) ?: null;
        }catch(\PDOException $e){
            echo $e->getMessage();
            return null;
        }
    }

    public function updateMessage(int $msg_id, string $content):array|bool{
        try{
        $sql = 'UPDATE Messages SET content = :content WHERE id = :msg_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':msg_id', $msg_id, PDO::PARAM_INT);
        $stmt->bindValue(':content', $content, PDO::PARAM_INT);
        $res = $stmt->execute();
        if(!$res){return false;}

        $getSql = 'SELECT * FROM Messages WHERE id = :msg_id';
        $getStmt = $this->pdo->prepare($getSql);
        $getStmt->bindValue(':msg_id', $msg_id, PDO::PARAM_INT);
        $getStmt->execute();

        return $getStmt->fetch(PDO::FETCH_ASSOC) ?: false;

        }catch(\PDOException $e){
            return false;
        }
    }

    public function getMessagesBetween(int $a, int $b): array|false {
        $sql = 'SELECT id, sender_id, recipient_id, content
                FROM Messages
                WHERE (sender_id = :a1 AND recipient_id = :b1)
                OR (sender_id = :b2 AND recipient_id = :a2)
                ORDER BY id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':a1' => $a, ':b1' => $b,
            ':a2' => $a, ':b2' => $b,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteMessageById(int $msg_id):bool{
        try{
            $sql = 'DELETE FROM Messages WHERE id = :msg_id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':msg_id', $msg_id, PDO::PARAM_INT);
            $res = $stmt->execute();
            if(!$res){return false;}
            return true;
        }catch(\PDOException $e){
            return false;
        }
    }

    public function getAllMessages():array{
        try{
            $sql = 'SELECT * FROM Messages';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }catch(\PDOException $e){
            return ['Error' => $e];
        }
    }

    public function getLastMessageFromConversationId(int $conversation_id): array {
        try{
            $sql = 'SELECT *
                FROM Messages
                WHERE conversation_id = :conversation_id
                ORDER BY sent_at DESC
                LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':conversation_id', $conversation_id, PDO::PARAM_INT);
            $stmt->execute();
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            return $message ?: [];
        }catch(\PDOException $e){
            return ['Error' => $e];
        }
    }
}