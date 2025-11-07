<?php
/**
 * 邮件发送类
 * 支持SMTP和PHP mail函数两种发送方式
 */
class EmailSender {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    private $use_smtp;
    
    public function __construct() {
        // 从系统设置获取邮件配置
        $this->smtp_host = getSystemSetting('smtp_host', '');
        $this->smtp_port = getSystemSetting('smtp_port', '587');
        $this->smtp_username = getSystemSetting('smtp_username', '');
        $this->smtp_password = getSystemSetting('smtp_password', '');
        $this->from_email = getSystemSetting('from_email', $this->smtp_username);
        $this->from_name = getSystemSetting('from_name', 'ML论坛');
        
        // 判断是否使用SMTP
        $this->use_smtp = !empty($this->smtp_host) && !empty($this->smtp_username) && !empty($this->smtp_password);
    }
    
    /**
     * 发送邮件
     * @param string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $body 邮件正文
     * @param bool $is_html 是否为HTML格式
     * @return bool 发送结果
     */
    public function send($to, $subject, $body, $is_html = true) {
        if ($this->use_smtp) {
            return $this->sendViaSMTP($to, $subject, $body, $is_html);
        } else {
            return $this->sendViaMail($to, $subject, $body, $is_html);
        }
    }
    
    /**
     * 使用SMTP发送邮件
     */
    private function sendViaSMTP($to, $subject, $body, $is_html = true) {
        try {
            // 检查是否安装了PHPMailer
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                return $this->sendWithPHPMailer($to, $subject, $body, $is_html);
            }
            
            // 如果没有PHPMailer，使用socket方式发送SMTP邮件
            return $this->sendWithSocket($to, $subject, $body, $is_html);
            
        } catch (Exception $e) {
            error_log("SMTP邮件发送失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 使用PHPMailer发送邮件
     */
    private function sendWithPHPMailer($to, $subject, $body, $is_html = true) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // 服务器配置
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_port;
            
            // 编码设置
            $mail->CharSet = 'UTF-8';
            
            // 发件人
            $mail->setFrom($this->from_email, $this->from_name);
            
            // 收件人
            $mail->addAddress($to);
            
            // 邮件内容
            $mail->isHTML($is_html);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            if (!$is_html) {
                $mail->AltBody = strip_tags($body);
            }
            
            return $mail->send();
            
        } catch (Exception $e) {
            error_log("PHPMailer发送失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 使用Socket发送SMTP邮件
     */
    private function sendWithSocket($to, $subject, $body, $is_html = true) {
        $socket = fsockopen($this->smtp_host, $this->smtp_port, $errno, $errstr, 30);
        
        if (!$socket) {
            error_log("SMTP连接失败: $errno - $errstr");
            return false;
        }
        
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            error_log("SMTP连接响应错误: $response");
            fclose($socket);
            return false;
        }
        
        // SMTP命令序列
        $commands = [
            "EHLO " . $this->smtp_host . "\r\n",
            "STARTTLS\r\n",
            "AUTH LOGIN\r\n",
            base64_encode($this->smtp_username) . "\r\n",
            base64_encode($this->smtp_password) . "\r\n",
            "MAIL FROM: <{$this->from_email}>\r\n",
            "RCPT TO: <$to>\r\n",
            "DATA\r\n",
            $this->buildEmailHeaders($to, $subject, $body, $is_html) . "\r\n.\r\n",
            "QUIT\r\n"
        ];
        
        foreach ($commands as $command) {
            fputs($socket, $command);
            $response = fgets($socket, 515);
            
            // 检查响应码
            if (substr($command, 0, 4) == 'QUIT') {
                break;
            }
            
            $code = substr($response, 0, 3);
            if (!in_array($code, ['220', '235', '250', '354', '221'])) {
                error_log("SMTP命令失败: $command -> $response");
                fclose($socket);
                return false;
            }
        }
        
        fclose($socket);
        return true;
    }
    
    /**
     * 使用PHP mail函数发送邮件
     */
    private function sendViaMail($to, $subject, $body, $is_html = true) {
        $headers = [];
        $headers[] = "From: {$this->from_name} <{$this->from_email}>";
        $headers[] = "Reply-To: {$this->from_email}";
        $headers[] = "Return-Path: {$this->from_email}";
        $headers[] = "MIME-Version: 1.0";
        
        if ($is_html) {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        } else {
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
        }
        
        $headers[] = "X-Mailer: PHP/" . phpversion();
        
        $header_string = implode("\r\n", $headers);
        
        return mail($to, $subject, $body, $header_string);
    }
    
    /**
     * 构建邮件头
     */
    private function buildEmailHeaders($to, $subject, $body, $is_html = true) {
        $boundary = uniqid('MLF_');
        $headers = [];
        
        $headers[] = "From: {$this->from_name} <{$this->from_email}>";
        $headers[] = "To: $to";
        $headers[] = "Subject: $subject";
        $headers[] = "Date: " . date('r');
        $headers[] = "MIME-Version: 1.0";
        
        if ($is_html) {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        } else {
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
        }
        
        $headers[] = "X-Mailer: ML Forum";
        
        $header_string = implode("\r\n", $headers);
        return $header_string . "\r\n\r\n" . $body;
    }
    
    /**
     * 发送验证码邮件
     */
    public function sendVerificationCode($to, $code, $type = 'register') {
        $template = getSystemSetting('email_template', '亲爱的ML论坛会员，您本次的验证码为{code}，5分钟内有效，如非本人操作，请您忽略。[ML论坛]');
        
        // 根据类型设置主题
        $subject_map = [
            'register' => 'ML论坛 - 注册验证码',
            'login' => 'ML论坛 - 登录验证码', 
            'forgot' => 'ML论坛 - 找回密码验证码',
            'delete_account' => 'ML论坛 - 账号注销验证码',
            'change_email' => 'ML论坛 - 更换邮箱验证码'
        ];
        
        $subject = $subject_map[$type] ?? 'ML论坛 - 验证码';
        
        // 替换模板中的验证码
        $message = str_replace('{code}', $code, $template);
        
        // 添加图片
        $image1_url = getSystemSetting('email_image1_url', '');
        $image2_url = getSystemSetting('email_image2_url', '');
        
        $html_message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        
        if ($image1_url) {
            $html_message .= '<div style="text-align: center; margin-bottom: 20px;">';
            $html_message .= '<img src="' . $image1_url . '" alt="" style="max-width: 100%; height: auto;">';
            $html_message .= '</div>';
        }
        
        $html_message .= '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #007bff;">';
        $html_message .= nl2br(htmlspecialchars($message));
        $html_message .= '</div>';
        
        if ($image2_url) {
            $html_message .= '<div style="text-align: center; margin-top: 20px;">';
            $html_message .= '<img src="' . $image2_url . '" alt="" style="max-width: 100%; height: auto;">';
            $html_message .= '</div>';
        }
        
        $html_message .= '<div style="margin-top: 20px; padding: 15px; background: #e9ecef; border-radius: 5px; font-size: 12px; color: #6c757d;">';
        $html_message .= '此邮件由系统自动发送，请勿回复。如有疑问，请联系网站管理员。';
        $html_message .= '</div>';
        $html_message .= '</div>';
        
        return $this->send($to, $subject, $html_message, true);
    }
}
?>