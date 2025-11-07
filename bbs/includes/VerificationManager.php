<?php
/**
 * 验证码管理类
 * 负责验证码的生成、存储、验证和清理
 */
class VerificationManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 生成验证码
     * @param int $length 验证码长度
     * @return string 生成的验证码
     */
    public function generateCode($length = 6) {
        $characters = '0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $code;
    }
    
    /**
     * 创建验证码记录
     * @param string $email 邮箱地址
     * @param string $type 验证码类型
     * @param int $expire_minutes 过期时间（分钟）
     * @return array 创建结果
     */
    public function createCode($email, $type = 'register', $expire_minutes = 5) {
        try {
            // 删除过期的验证码
            $this->cleanExpiredCodes();
            
            // 检查该邮箱在短时间内是否发送过验证码（防止频繁发送）
            $checkStmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM verification_codes 
                WHERE email = ? AND type = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            ");
            $checkStmt->execute([$email, $type]);
            $recentCount = $checkStmt->fetch()['count'];
            
            if ($recentCount > 0) {
                return ['success' => false, 'message' => '验证码发送过于频繁，请稍后再试'];
            }
            
            // 生成验证码
            $code = $this->generateCode(6);
            $expires_at = date('Y-m-d H:i:s', time() + ($expire_minutes * 60));
            
            // 插入验证码记录
            $insertStmt = $this->pdo->prepare("
                INSERT INTO verification_codes (email, code, type, expires_at) 
                VALUES (?, ?, ?, ?)
            ");
            $insertStmt->execute([$email, $code, $type, $expires_at]);
            
            return [
                'success' => true,
                'code' => $code,
                'expires_at' => $expires_at
            ];
            
        } catch (PDOException $e) {
            error_log("创建验证码失败: " . $e->getMessage());
            return ['success' => false, 'message' => '系统错误，请稍后再试'];
        }
    }
    
    /**
     * 验证验证码
     * @param string $email 邮箱地址
     * @param string $code 验证码
     * @param string $type 验证码类型
     * @return array 验证结果
     */
    public function verifyCode($email, $code, $type = 'register') {
        try {
            // 查找有效的验证码记录
            $stmt = $this->pdo->prepare("
                SELECT id, expires_at 
                FROM verification_codes 
                WHERE email = ? AND code = ? AND type = ? AND used = 0 AND expires_at > NOW()
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$email, $code, $type]);
            $record = $stmt->fetch();
            
            if (!$record) {
                return ['success' => false, 'message' => '验证码错误或已过期'];
            }
            
            // 标记验证码为已使用
            $updateStmt = $this->pdo->prepare("UPDATE verification_codes SET used = 1 WHERE id = ?");
            $updateStmt->execute([$record['id']]);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log("验证验证码失败: " . $e->getMessage());
            return ['success' => false, 'message' => '系统错误，请稍后再试'];
        }
    }
    
    /**
     * 清理过期验证码
     */
    public function cleanExpiredCodes() {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM verification_codes WHERE expires_at < NOW() OR used = 1");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("清理过期验证码失败: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 获取验证码发送次数（用于频率限制）
     * @param string $email 邮箱地址
     * @param string $type 验证码类型
     * @param int $hours 时间范围（小时）
     * @return int 发送次数
     */
    public function getSendCount($email, $type = 'register', $hours = 24) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM verification_codes 
                WHERE email = ? AND type = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
            ");
            $stmt->execute([$email, $type, $hours]);
            return $stmt->fetch()['count'];
        } catch (PDOException $e) {
            error_log("获取验证码发送次数失败: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 检查邮箱是否被滥用
     * @param string $email 邮箱地址
     * @return bool 是否被滥用
     */
    public function isEmailAbused($email) {
        // 检查24小时内发送总数
        $totalCount = $this->getSendCount($email, '', 24);
        if ($totalCount > 20) {
            return true;
        }
        
        // 检查1小时内发送次数
        $hourlyCount = $this->getSendCount($email, '', 1);
        if ($hourlyCount > 5) {
            return true;
        }
        
        return false;
    }
}
?>