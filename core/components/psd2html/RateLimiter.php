<?php
/**
 * PSD2HTML — RateLimiter (Задача 3): лимиты конвертаций на IP и сессию в сутки.
 */

declare(strict_types=1);

final class RateLimiter
{
    public function __construct(private PDO $pdo) {}

    /**
     * Проверка: можно ли принять новую задачу?
     * @return array{allowed:bool, reason:?string} reason: 'ip'|'session'
     */
    public function check(string $ip, ?string $sessionId): array
    {
        $limitIp = (int)p2h_setting('dailyLimitIp', 5);
        $limitSess = (int)p2h_setting('dailyLimitSession', 5);

        $q = $this->pdo->prepare(
            'SELECT hits FROM `' . P2H_PREFIX . "psd2html_ratelimit` WHERE bucket=:b AND day=CURDATE()"
        );

        $q->execute([':b' => hash('sha256', 'ip|' . $ip)]);
        if ((int)($q->fetchColumn() ?: 0) >= $limitIp) {
            return ['allowed' => false, 'reason' => 'ip'];
        }
        if ($sessionId !== null && $sessionId !== '') {
            $q->execute([':b' => hash('sha256', 'sess|' . $sessionId)]);
            if ((int)($q->fetchColumn() ?: 0) >= $limitSess) {
                return ['allowed' => false, 'reason' => 'session'];
            }
        }
        return ['allowed' => true, 'reason' => null];
    }

    /** Инкремент счётчиков после успешной постановки задачи. */
    public function hit(string $ip, ?string $sessionId): void
    {
        $ins = $this->pdo->prepare(
            'INSERT INTO `' . P2H_PREFIX . "psd2html_ratelimit` (bucket, kind, day, hits)
             VALUES (:b, :k, CURDATE(), 1)
             ON DUPLICATE KEY UPDATE hits = hits + 1"
        );
        $ins->execute([':b' => hash('sha256', 'ip|' . $ip), ':k' => 'ip']);
        if ($sessionId !== null && $sessionId !== '') {
            $ins->execute([':b' => hash('sha256', 'sess|' . $sessionId), ':k' => 'session']);
        }
    }
}
