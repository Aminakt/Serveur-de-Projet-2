<?php

declare(strict_types=1);

namespace Php\Src;

use PDO;

final class Conversations {
    public function __construct(private PDO $pdo) {}

    /**
     * GET /conversations
     * @return array
     */
    public function getAll():array {
        $sql = 'SELECT id, name FROM Conversations';
        return Utils::dbReturn(false, $this->pdo->query($sql)->fetchAll());
    }

    /**
     * GET /conversations/:id
     * @param int $id
     * @return array
     */
    public function getConversationById(int $id):array|bool {
        $sql = 'SELECT id, name, recipient_id FROM Conversations WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data;
    }

    public function addConversation(int $main_user_id, int $recipient_id, string $name):array|bool{
        try{$sql = 'INSERT INTO Conversations (main_user_id, recipient_id, name) VALUES (:main_user_id, :recipient_id, :name)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':main_user_id', $main_user_id, PDO::PARAM_INT);
        $stmt->bindValue(':recipient_id', $recipient_id, PDO::PARAM_INT);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $res = $stmt->execute();
        if(!$res){return false;}

        $id = $this->pdo->lastInsertId();
        if($id<=0){return false;}

        $getSql = 'SELECT * FROM Conversations WHERE id = :id';
        $getStmt = $this->pdo->prepare($getSql);
        $getStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $getStmt->execute();
        
        return $getStmt->fetch(PDO::FETCH_ASSOC);}catch(\PDOException $e){return false;}
    }

    public function updateConversation(int $conv_id, string $name):array|bool{
        try{$sql = 'UPDATE Conversations SET name = :name WHERE id = :conv_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':conv_id', $conv_id, PDO::PARAM_INT);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $res = $stmt->execute();
        if(!$res){return false;}

        $getSql = 'SELECT id, name FROM Conversations WHERE id = :conv_id';
        $getStmt = $this->pdo->prepare($getSql);
        $getStmt->bindValue(':conv_id', $conv_id, PDO::PARAM_INT);
        $getStmt->execute();

        return $getStmt->fetch(PDO::FETCH_ASSOC);}catch(\PDOException $e){return false;}
    }

    public function deleteConversation(int $conv_id):array{
        try{
            $sql = 'DELETE FROM Conversations WHERE id = :conv_id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':conv_id', $conv_id, PDO::PARAM_INT);
            $stmt->execute();
            if(!$stmt->rowCount()){return Utils::dbReturn(true, "Aucune row affectée. Mauvais parametre.");}
            return Utils::dbReturn(false, null);
        }catch(\PDOException $e){
            return Utils::dbReturn(true, $e->getMessage());
        }
    }

    public function getAllConversationsFromUserId(int $user_id):array{
        try{$sql = 'SELECT *
            FROM Conversations
            WHERE main_user_id = ? OR recipient_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$user_id, $user_id]);
        return Utils::dbReturn(false, $stmt->fetchAll(PDO::FETCH_ASSOC));}catch(\PDOException $e){
            return Utils::dbReturn(true, $e->getMessage());
        }
    }

    public function getConversationsAndLastMessagesFromUserId(int $user_id):array{
        try{
            $sql = 'SELECT
                c.id  AS conversation_id,
                c.name AS conversation_name,
                c.main_user_id,
                c.recipient_id,

                CASE WHEN c.main_user_id = ? THEN u_rec.id ELSE u_main.id END AS other_user_id,
                CASE WHEN c.main_user_id = ? THEN u_rec.username ELSE u_main.username END AS other_username,
                CASE WHEN c.main_user_id = ? THEN u_rec.avatar_url ELSE u_main.avatar_url END AS other_avatar_url,
                lm.id            AS message_id,
                lm.sender_id,
                lm.conversation_id AS message_conversation_id,
                lm.content       AS message_content,
                lm.sent_at       AS message_sent_at

                FROM Conversations c
                JOIN Users u_main ON u_main.id = c.main_user_id
                JOIN Users u_rec  ON u_rec.id  = c.recipient_id

                LEFT JOIN (
                SELECT m1.*
                FROM Messages m1
                JOIN (
                    SELECT conversation_id, MAX(sent_at) AS max_sent
                    FROM Messages
                    GROUP BY conversation_id
                ) mx ON mx.conversation_id = m1.conversation_id AND mx.max_sent = m1.sent_at
                ) lm ON lm.conversation_id = c.id

                WHERE c.main_user_id = ? OR c.recipient_id = ?
                ORDER BY COALESCE(lm.sent_at, c.id) DESC';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
            return Utils::dbReturn(false, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }catch(\PDOException $e){
            return Utils::dbReturn(true, $e->getMessage());
        }
    }
}