<?php

declare(strict_types=1);
namespace Php\Src;

use DateTimeImmutable;
use PDO;
use Php\Src\Utils;

final class Tokens {
    public function __construct(private PDO $pdo){}

    /**
     * Summary of getRefresh
     * @param mixed $userid
     * @return array{data: mixed}
     */
    function getRefresh($userid): array{
        $sql = "SELECT (token_hash) FROM Refresh_token WHERE user_id = :userid";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':userid', $userid, PDO::PARAM_INT);
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return Utils::dbReturn(false, $res);
    }

    function storeRefresh(int $userid, string $hash, DateTimeImmutable $exp):void{
        $sql = 'INSERT INTO Refresh_token (user_id, token_hash, expires_at) VALUES (?,?,?)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userid, $hash, $exp->format('Y-m-d H:i:s')]);
    }

    public function getActiveRT(string $hash): ?array {
        $sql = "SELECT user_id, expires_at, revoked_at FROM Refresh_token
                WHERE token_hash = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        if ($row['revoked_at'] !== null) return null;
        if (new DateTimeImmutable($row['expires_at']) <= Utils::now()) return null;
        return $row;
    }

    public function touchLastUsed(string $hash): void {
        $sql = "UPDATE Refresh_token SET last_used_at = CURRENT_TIMESTAMP(3) WHERE token_hash = ?";
        $this->pdo->prepare($sql)->execute([$hash]);
    }

    public function revokeByHash(string $hash): void {
        $sql = "UPDATE Refresh_token SET revoked_at = CURRENT_TIMESTAMP(3) WHERE token_hash = ?";
        $this->pdo->prepare($sql)->execute([$hash]);
    }

    public function rotate(int $userId, string $oldHashBin, string $newHashBin, \DateTimeImmutable $exp): void {
        $this->pdo->beginTransaction();
        $this->revokeByHash($oldHashBin);
        $this->storeRefresh($userId, $newHashBin, $exp);
        $this->pdo->commit();
    }
}